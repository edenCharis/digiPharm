<?php
/**
 * AJAX handler for purchase order operations.
 * POST JSON body → JSON response { ok, ... }
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mailer.php';
ai_check_auth();

header('Content-Type: application/json');

$user = ai_user();
$pid  = $user['pharmacy_id'];
$db   = analytics_db();

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!$body) { echo json_encode(['ok' => false, 'error' => 'Corps JSON invalide']); exit; }

$action = $body['action'] ?? '';

try {
    switch ($action) {

        case 'create':
            $supplierName  = trim($body['supplier_name'] ?? '');
            $supplierEmail = trim($body['supplier_email'] ?? '');
            $items         = $body['items'] ?? [];
            $reqDate       = $body['requested_date'] ?? null;
            $notes         = $body['notes'] ?? '';

            if (!$supplierName) throw new Exception('Nom du fournisseur requis');
            if (!$supplierEmail || !filter_var($supplierEmail, FILTER_VALIDATE_EMAIL)) throw new Exception('Email fournisseur invalide');
            if (!$items)         throw new Exception('Au moins un produit requis');

            $db->beginTransaction();

            // Insert order
            $stmt = $db->prepare("
                INSERT INTO ai_purchase_orders
                    (pharmacy_id, supplier_name, supplier_email, notes,
                     requested_delivery_date, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, 'draft', ?, NOW())
            ");
            $stmt->execute([$pid, $supplierName, $supplierEmail, $notes,
                $reqDate ?: null, $user['id']]);
            $orderId  = $db->lastInsertId();
            $orderRef = 'CMD-' . date('Ym') . '-' . str_pad($orderId, 4, '0', STR_PAD_LEFT);
            $db->prepare("UPDATE ai_purchase_orders SET order_ref=? WHERE id=?")->execute([$orderRef, $orderId]);

            // Insert items
            $itemStmt = $db->prepare("
                INSERT INTO ai_purchase_order_items
                    (order_id, product_id, product_name, quantity_requested, unit_price)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'] ?? null,
                    $item['product_name'] ?? '',
                    (int)($item['quantity'] ?? 1),
                    isset($item['unit_price']) ? (float)$item['unit_price'] : null,
                ]);
            }

            // Generate token (64-char hex, expires in 30 days)
            $token = bin2hex(random_bytes(32));
            $db->prepare("
                INSERT INTO ai_supplier_tokens (order_id, token, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
            ")->execute([$orderId, $token]);

            // Mark as sent
            $db->prepare("UPDATE ai_purchase_orders SET status='sent', sent_at=NOW() WHERE id=?")->execute([$orderId]);
            $db->commit();

            // Send email to supplier
            $portalUrl    = _base_url() . "/supply/order.php?token={$token}";
            $pharmacyName = $user['pharmacy_name'];
            $emailBody    = _order_email_html($orderRef, $pharmacyName, $items, $reqDate, $portalUrl, $notes);
            $subject      = "Nouvelle commande {$orderRef} — {$pharmacyName}";
            $sent         = ai_send_mail($supplierEmail, $subject, $emailBody);

            echo json_encode(['ok' => true, 'order_id' => $orderId, 'order_ref' => $orderRef, 'email_sent' => $sent]);
            break;

        case 'send':
            // Resend existing order email
            $orderId = (int)($body['order_id'] ?? 0);
            $order = _load_order($db, $pid, $orderId);
            $token = _get_token($db, $orderId);
            if (!$token) throw new Exception('Token introuvable');

            $items = _load_items($db, $orderId);
            $portalUrl = _base_url() . "/supply/order.php?token={$token}";
            $emailBody = _order_email_html($order['order_ref'], $user['pharmacy_name'], $items,
                $order['requested_delivery_date'], $portalUrl, $order['notes']);
            $sent = ai_send_mail($order['supplier_email'],
                "Commande {$order['order_ref']} — {$user['pharmacy_name']}", $emailBody);

            $db->prepare("UPDATE ai_purchase_orders SET status='sent', sent_at=NOW() WHERE id=? AND pharmacy_id=?")
               ->execute([$orderId, $pid]);
            echo json_encode(['ok' => true, 'email_sent' => $sent]);
            break;

        case 'deliver':
            $orderId = (int)($body['order_id'] ?? 0);
            _load_order($db, $pid, $orderId); // validates ownership
            $db->prepare("UPDATE ai_purchase_orders SET status='delivered', delivered_at=NOW() WHERE id=? AND pharmacy_id=?")
               ->execute([$orderId, $pid]);
            echo json_encode(['ok' => true]);
            break;

        case 'cancel':
            $orderId = (int)($body['order_id'] ?? 0);
            _load_order($db, $pid, $orderId);
            $db->prepare("UPDATE ai_purchase_orders SET status='cancelled' WHERE id=? AND pharmacy_id=?")
               ->execute([$orderId, $pid]);
            echo json_encode(['ok' => true]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Action inconnue']);
    }
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

// ── Helpers ───────────────────────────────────────────────────────────────

function _load_order(PDO $db, int $pid, int $orderId): array
{
    $stmt = $db->prepare("SELECT * FROM ai_purchase_orders WHERE id=? AND pharmacy_id=? LIMIT 1");
    $stmt->execute([$orderId, $pid]);
    $row = $stmt->fetch();
    if (!$row) throw new Exception('Commande introuvable');
    return $row;
}

function _get_token(PDO $db, int $orderId): ?string
{
    $stmt = $db->prepare("SELECT token FROM ai_supplier_tokens WHERE order_id=? LIMIT 1");
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();
    return $row ? $row['token'] : null;
}

function _load_items(PDO $db, int $orderId): array
{
    $stmt = $db->prepare("SELECT * FROM ai_purchase_order_items WHERE order_id=?");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

function _base_url(): string
{
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "{$proto}://{$host}";
}

function _order_email_html(
    string $ref, string $pharmacyName, array $items,
    ?string $reqDate, string $portalUrl, string $notes = ''
): string {
    $dateLabel = $reqDate ? date('d/m/Y', strtotime($reqDate)) : 'Dès que possible';
    $rows = '';
    foreach ($items as $item) {
        $name  = htmlspecialchars($item['product_name'] ?? '');
        $qty   = (int)($item['quantity_requested'] ?? $item['quantity'] ?? 1);
        $price = isset($item['unit_price']) && $item['unit_price'] ? number_format((float)$item['unit_price'], 0, ',', ' ') . ' CFA' : '—';
        $rows .= "<tr><td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;font-size:14px'>$name</td>"
               . "<td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;font-size:14px;text-align:center'>$qty</td>"
               . "<td style='padding:10px 14px;border-bottom:1px solid #e5e7eb;font-size:14px;text-align:right'>$price</td></tr>";
    }
    $notesHtml = $notes ? "<p style='color:#4b5563;font-size:13px;margin-top:16px'><strong>Notes :</strong> " . htmlspecialchars($notes) . "</p>" : '';

    return "
    <div style='font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:560px;margin:0 auto;padding:32px 24px;'>
      <div style='margin-bottom:24px'>
        <span style='font-size:18px;font-weight:800;color:#1e40af;letter-spacing:-0.5px'>digi<span style='color:#111827'>Supply</span></span>
        <span style='font-size:12px;color:#9ca3af;margin-left:12px'>par digiMind</span>
      </div>

      <h2 style='font-size:20px;font-weight:700;color:#111827;margin:0 0 4px'>Nouvelle commande</h2>
      <p style='color:#6b7280;font-size:14px;margin:0 0 24px'>
        <strong style='color:#111827'>{$pharmacyName}</strong> vous adresse une commande.
      </p>

      <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:20px;font-size:13px'>
        <div><strong>Référence :</strong> {$ref}</div>
        <div style='margin-top:6px'><strong>Livraison souhaitée :</strong> {$dateLabel}</div>
      </div>

      <table style='width:100%;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:20px'>
        <thead>
          <tr style='background:#f1f5f9'>
            <th style='padding:10px 14px;text-align:left;font-size:12px;color:#6b7280;font-weight:600'>Produit</th>
            <th style='padding:10px 14px;text-align:center;font-size:12px;color:#6b7280;font-weight:600'>Qté</th>
            <th style='padding:10px 14px;text-align:right;font-size:12px;color:#6b7280;font-weight:600'>Prix unit.</th>
          </tr>
        </thead>
        <tbody>{$rows}</tbody>
      </table>

      {$notesHtml}

      <div style='text-align:center;margin:32px 0'>
        <a href='{$portalUrl}'
           style='display:inline-block;background:#1e40af;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-size:15px;font-weight:700'>
          Voir et confirmer la commande →
        </a>
      </div>

      <p style='color:#9ca3af;font-size:12px;margin-top:16px'>
        Ce lien est valable 30 jours. Vous pouvez confirmer, proposer une autre date, ou décliner directement depuis la page.
      </p>
      <hr style='border:none;border-top:1px solid #e5e7eb;margin:24px 0'>
      <p style='color:#9ca3af;font-size:11px'>digiSupply · digiMind · Digital Technologies Congo</p>
    </div>";
}
