<?php
/**
 * digiMind import handler — accepts mapped CSV/Excel data and writes to
 * ai_sales or ai_inventory. Called by import.php via fetch() POST.
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
ai_check_auth();

header('Content-Type: application/json');

$user       = ai_user();
$pharmacyId = (int) $user['pharmacy_id'];
$db         = analytics_db();

function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ── Parse input ────────────────────────────────────────────────────────────
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) respond(['ok' => false, 'error' => 'Payload invalide'], 400);

$type         = $input['type']          ?? '';
$rows         = $input['rows']          ?? [];
$replaceRange = (bool)($input['replace_range'] ?? true);

if (!in_array($type, ['sales', 'inventory'], true)) {
    respond(['ok' => false, 'error' => 'Type inconnu'], 400);
}
if (empty($rows) || !is_array($rows)) {
    respond(['ok' => false, 'error' => 'Aucune donnée à importer'], 400);
}

// ── Helpers ────────────────────────────────────────────────────────────────
function parseDate(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') return null;

    // Common patterns
    $patterns = [
        '/^(\d{4})-(\d{1,2})-(\d{1,2})$/'     => '$1-$2-$3',   // ISO
        '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/'   => '$3-$2-$1',   // DD/MM/YYYY
        '/^(\d{1,2})-(\d{1,2})-(\d{4})$/'     => '$3-$2-$1',   // DD-MM-YYYY
        '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/'   => '$3-$2-$1',   // DD.MM.YYYY
        '/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/'   => '20$3-$2-$1', // DD/MM/YY
    ];

    foreach ($patterns as $re => $repl) {
        if (preg_match($re, $raw)) {
            $candidate = preg_replace($re, $repl, $raw);
            $ts = strtotime($candidate);
            if ($ts !== false) {
                $y = (int) date('Y', $ts);
                if ($y >= 2000 && $y <= 2099) return date('Y-m-d', $ts);
            }
        }
    }

    // Try PHP's native parsing as last resort
    $ts = strtotime($raw);
    if ($ts !== false) {
        $y = (int) date('Y', $ts);
        if ($y >= 2000 && $y <= 2099) return date('Y-m-d', $ts);
    }
    return null;
}

function parseNumber(string $raw): ?float
{
    $raw = trim($raw);
    if ($raw === '') return null;
    // Remove non-numeric except comma and dot
    $clean = preg_replace('/[^\d,.\-]/', '', $raw);
    // French decimal: replace last comma with dot if no dot after it
    if (substr_count($clean, ',') === 1 && substr_count($clean, '.') === 0) {
        $clean = str_replace(',', '.', $clean);
    } elseif (substr_count($clean, ',') >= 1) {
        // Thousands separator: 1.234,56 → 1234.56
        $clean = str_replace(['.', ','], ['', '.'], $clean);
    }
    return is_numeric($clean) ? (float) $clean : null;
}

// ── Route ──────────────────────────────────────────────────────────────────
if ($type === 'sales') {
    importSales($db, $pharmacyId, $rows, $replaceRange);
} else {
    importInventory($db, $pharmacyId, $rows, $replaceRange);
}

// ── Sales import ───────────────────────────────────────────────────────────
function importSales(PDO $db, int $pharmacyId, array $rows, bool $replace): void
{
    $inserted = 0;
    $skipped  = 0;
    $errors   = [];
    $today    = date('Y-m-d');
    $dates    = [];

    // Pre-validate and normalise
    $clean = [];
    foreach ($rows as $i => $row) {
        $dateRaw    = $row['sale_date']      ?? '';
        $name       = trim($row['product_name']   ?? '');
        $qtyRaw     = $row['quantity']       ?? '';
        $priceRaw   = $row['unit_price']     ?? '';

        $date = parseDate($dateRaw);
        if (!$date) {
            if ($dateRaw !== '') {
                $errors[] = "Ligne " . ($i + 1) . " : date invalide « $dateRaw »";
            } else {
                $date = $today;
            }
        }
        if ($name === '') { $skipped++; continue; }
        $qty   = parseNumber($qtyRaw);
        $price = parseNumber($priceRaw);
        if ($qty === null || $qty <= 0) { $skipped++; $errors[] = "Ligne " . ($i + 1) . " : quantité invalide « $qtyRaw »"; continue; }
        if ($price === null || $price < 0) { $skipped++; continue; }

        $revenue = parseNumber($row['revenue'] ?? '') ?? round($qty * $price, 2);
        $cost    = parseNumber($row['cost']    ?? '');

        $clean[]  = [
            'sale_date'      => $date,
            'product_name'   => mb_substr($name, 0, 200),
            'product_id'     => mb_substr(trim($row['product_id'] ?? ''), 0, 64) ?: null,
            'category'       => mb_substr(trim($row['category']   ?? ''), 0, 100) ?: null,
            'quantity'       => $qty,
            'unit_price'     => $price,
            'revenue'        => $revenue,
            'cost'           => $cost,
            'source_sale_id' => mb_substr(trim($row['source_sale_id'] ?? ''), 0, 64) ?: null,
        ];
        $dates[] = $date;
    }

    if (empty($clean)) {
        respond(['ok' => false, 'error' => 'Aucune ligne valide à importer.', 'errors' => $errors]);
    }

    // Replace existing data for covered date range
    $replaced = false;
    if ($replace && $dates) {
        $minDate = min($dates);
        $maxDate = max($dates);
        $db->prepare("DELETE FROM ai_sales WHERE pharmacy_id = ? AND sale_date BETWEEN ? AND ?")
           ->execute([$pharmacyId, $minDate, $maxDate]);
        $replaced = true;
    }

    // Batch insert
    $sql = "INSERT INTO ai_sales
                (pharmacy_id, sale_date, product_id, product_name, category,
                 quantity, unit_price, revenue, cost, source_sale_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);

    $db->beginTransaction();
    try {
        foreach ($clean as $r) {
            $stmt->execute([
                $pharmacyId,
                $r['sale_date'], $r['product_id'], $r['product_name'], $r['category'],
                $r['quantity'],  $r['unit_price'],  $r['revenue'],      $r['cost'],
                $r['source_sale_id'],
            ]);
            $inserted++;
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        respond(['ok' => false, 'error' => 'Erreur base de données : ' . $e->getMessage()]);
    }

    respond([
        'ok'       => true,
        'inserted' => $inserted,
        'skipped'  => $skipped,
        'replaced' => $replaced,
        'errors'   => array_slice($errors, 0, 10),
    ]);
}

// ── Inventory import ───────────────────────────────────────────────────────
function importInventory(PDO $db, int $pharmacyId, array $rows, bool $replace): void
{
    $inserted = 0;
    $skipped  = 0;
    $errors   = [];
    $today    = date('Y-m-d');
    $dates    = [];

    $clean = [];
    foreach ($rows as $i => $row) {
        $name    = trim($row['product_name']   ?? '');
        $qtyRaw  = $row['stock_quantity'] ?? '';
        if ($name === '') { $skipped++; continue; }
        $qty = parseNumber($qtyRaw);
        if ($qty === null || $qty < 0) { $skipped++; $errors[] = "Ligne " . ($i + 1) . " : stock invalide « $qtyRaw »"; continue; }

        $snapDate  = parseDate($row['snapshot_date'] ?? '') ?? $today;
        $expiryRaw = $row['expiry_date'] ?? '';
        $expiry    = $expiryRaw !== '' ? parseDate($expiryRaw) : null;

        $clean[] = [
            'snapshot_date' => $snapDate,
            'product_name'  => mb_substr($name, 0, 200),
            'product_id'    => mb_substr(trim($row['product_id'] ?? ''), 0, 64) ?: null,
            'category'      => mb_substr(trim($row['category']  ?? ''), 0, 100) ?: null,
            'stock_quantity'=> $qty,
            'unit_cost'     => parseNumber($row['unit_cost']  ?? ''),
            'unit_price'    => parseNumber($row['unit_price'] ?? ''),
            'expiry_date'   => $expiry,
        ];
        $dates[] = $snapDate;
    }

    if (empty($clean)) {
        respond(['ok' => false, 'error' => 'Aucune ligne valide à importer.', 'errors' => $errors]);
    }

    $replaced = false;
    if ($replace && $dates) {
        $minDate = min($dates);
        $maxDate = max($dates);
        $db->prepare("DELETE FROM ai_inventory WHERE pharmacy_id = ? AND snapshot_date BETWEEN ? AND ?")
           ->execute([$pharmacyId, $minDate, $maxDate]);
        $replaced = true;
    }

    $sql = "INSERT INTO ai_inventory
                (pharmacy_id, snapshot_date, product_id, product_name, category,
                 stock_quantity, unit_cost, unit_price, expiry_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);

    $db->beginTransaction();
    try {
        foreach ($clean as $r) {
            $stmt->execute([
                $pharmacyId,
                $r['snapshot_date'], $r['product_id'], $r['product_name'], $r['category'],
                $r['stock_quantity'], $r['unit_cost'],  $r['unit_price'],   $r['expiry_date'],
            ]);
            $inserted++;
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        respond(['ok' => false, 'error' => 'Erreur base de données : ' . $e->getMessage()]);
    }

    respond([
        'ok'       => true,
        'inserted' => $inserted,
        'skipped'  => $skipped,
        'replaced' => $replaced,
        'errors'   => array_slice($errors, 0, 10),
    ]);
}
