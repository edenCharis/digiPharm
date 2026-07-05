<?php
/**
 * digiSupply — handles supplier actions (confirm, decline, ship).
 * POST from supply/order.php forms.
 */
require_once __DIR__ . '/config/db.php';

// Include analytics mailer (same server, shared vendor)
$mailerPath = dirname(__DIR__) . '/analytics/config/mailer.php';
if (file_exists($mailerPath)) require_once $mailerPath;

function _redirect_back(string $token, string $msg = ''): void
{
    $url = '/supply/order.php?token=' . urlencode($token);
    if ($msg) $url .= '&msg=' . urlencode($msg);
    header("Location: $url");
    exit;
}

$token  = trim($_POST['token'] ?? '');
$action = trim($_POST['action'] ?? '');

if (!$token || !$action) {
    header('Location: /supply/');
    exit;
}

$data = supply_resolve_token($token);
if (!$data) {
    header('Location: /supply/');
    exit;
}

$order   = $data;
$orderId = (int)$order['order_id'];
$db      = supply_db();

// Load pharmacy user email for notification
$phStmt = $db->prepare("
    SELECT u.email, u.display_name, u.otp_email
    FROM ai_users u
    WHERE u.pharmacy_id = ? AND u.is_active = 1
    ORDER BY u.role = 'admin' DESC, u.id ASC
    LIMIT 1
");
$phStmt->execute([$order['pharmacy_id']]);
$pharmacyUser = $phStmt->fetch();
$notifyEmail  = $pharmacyUser['otp_email'] ?: ($pharmacyUser['email'] ?? '');

try {
    switch ($action) {

        case 'confirm':
            if ($order['status'] !== 'sent') _redirect_back($token);

            $deliveryDate = $_POST['delivery_date'] ?? '';
            if (!$deliveryDate) _redirect_back($token, 'Date requise');

            $message = trim($_POST['message'] ?? '');

            $db->prepare("
                UPDATE ai_purchase_orders
                SET status='confirmed', confirmed_delivery_date=?, confirmed_at=NOW()
                WHERE id=?
            ")->execute([$deliveryDate, $orderId]);

            // Notify pharmacy
            if ($notifyEmail && function_exists('ai_send_mail')) {
                $ref      = htmlspecialchars($order['order_ref']);
                $supName  = htmlspecialchars($order['supplier_name']);
                $phName   = htmlspecialchars($order['pharmacy_name']);
                $dateFmt  = date('d/m/Y', strtotime($deliveryDate));
                $msgNote  = $message ? "<p style='color:#4b5563;font-size:13px;margin-top:12px'><strong>Message fournisseur :</strong> " . htmlspecialchars($message) . "</p>" : '';
                ai_send_mail($notifyEmail,
                    "✅ Commande $ref confirmée — livraison le $dateFmt",
                    "<div style='font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:32px 24px'>
                      <div style='font-size:18px;font-weight:700;margin-bottom:20px'><span style='color:#1a7f4b'>digi</span>Mind</div>
                      <h2 style='font-size:18px;color:#111827;margin-bottom:8px'>Commande confirmée ✅</h2>
                      <p style='color:#4b5563;font-size:14px'>Le fournisseur <strong>$supName</strong> a confirmé la commande <strong>$ref</strong>.</p>
                      <div style='background:#dcfce7;border:1px solid #bbf7d0;border-radius:8px;padding:16px;margin:20px 0;font-size:14px;color:#166534'>
                        <strong>Livraison prévue le $dateFmt</strong>
                      </div>
                      $msgNote
                      <p style='color:#9ca3af;font-size:12px;margin-top:24px'>digiMind · $phName</p>
                    </div>"
                );
            }
            _redirect_back($token);

        case 'decline':
            if ($order['status'] !== 'sent') _redirect_back($token);

            $reason = trim($_POST['reason'] ?? '');
            if (!$reason) _redirect_back($token, 'Motif requis');

            $db->prepare("
                UPDATE ai_purchase_orders
                SET status='declined', supplier_decline_reason=?
                WHERE id=?
            ")->execute([$reason, $orderId]);

            // Notify pharmacy
            if ($notifyEmail && function_exists('ai_send_mail')) {
                $ref     = htmlspecialchars($order['order_ref']);
                $supName = htmlspecialchars($order['supplier_name']);
                $phName  = htmlspecialchars($order['pharmacy_name']);
                ai_send_mail($notifyEmail,
                    "❌ Commande $ref déclinée par $supName",
                    "<div style='font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:32px 24px'>
                      <div style='font-size:18px;font-weight:700;margin-bottom:20px'><span style='color:#1a7f4b'>digi</span>Mind</div>
                      <h2 style='font-size:18px;color:#111827;margin-bottom:8px'>Commande déclinée ❌</h2>
                      <p style='color:#4b5563;font-size:14px'>Le fournisseur <strong>$supName</strong> a décliné la commande <strong>$ref</strong>.</p>
                      <div style='background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:16px;margin:20px 0;font-size:14px;color:#991b1b'>
                        <strong>Motif :</strong> " . htmlspecialchars($reason) . "
                      </div>
                      <p style='color:#4b5563;font-size:13px;margin-top:8px'>Vous pouvez créer une nouvelle commande auprès d'un autre fournisseur depuis digiMind.</p>
                      <p style='color:#9ca3af;font-size:12px;margin-top:24px'>digiMind · $phName</p>
                    </div>"
                );
            }
            _redirect_back($token);

        case 'ship':
            if ($order['status'] !== 'confirmed') _redirect_back($token);

            $db->prepare("
                UPDATE ai_purchase_orders
                SET status='shipped', shipped_at=NOW()
                WHERE id=?
            ")->execute([$orderId]);

            // Notify pharmacy
            if ($notifyEmail && function_exists('ai_send_mail')) {
                $ref     = htmlspecialchars($order['order_ref']);
                $supName = htmlspecialchars($order['supplier_name']);
                $phName  = htmlspecialchars($order['pharmacy_name']);
                ai_send_mail($notifyEmail,
                    "🚚 Commande $ref expédiée par $supName",
                    "<div style='font-family:system-ui,sans-serif;max-width:480px;margin:0 auto;padding:32px 24px'>
                      <div style='font-size:18px;font-weight:700;margin-bottom:20px'><span style='color:#1a7f4b'>digi</span>Mind</div>
                      <h2 style='font-size:18px;color:#111827;margin-bottom:8px'>Commande expédiée 🚚</h2>
                      <p style='color:#4b5563;font-size:14px'>Le fournisseur <strong>$supName</strong> a expédié la commande <strong>$ref</strong>.</p>
                      <p style='color:#4b5563;font-size:13px;margin-top:12px'>Marquez la commande comme livrée dans digiMind dès réception.</p>
                      <p style='color:#9ca3af;font-size:12px;margin-top:24px'>digiMind · $phName</p>
                    </div>"
                );
            }
            _redirect_back($token);

        default:
            _redirect_back($token);
    }
} catch (Exception $e) {
    error_log('[digiSupply confirm] ' . $e->getMessage());
    _redirect_back($token, 'Erreur serveur');
}
