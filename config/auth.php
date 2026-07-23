<?php
/**
 * OTP Authentication System
 * File: config/auth.php
 */


error_reporting(E_ALL);
ini_set('display_errors', 0); // <-- cache les warnings/notices
header('Content-Type: application/json'); // <- important

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
include 'database.php';
require_once __DIR__ . '/env.php';

class OTPAuth {
    private $db;
    private $otpExpiry = 300; // 5 minutes

    public function __construct($database) {
        $this->db = $database;
    }

    private function generateOTP() {
        return sprintf("%06d", mt_rand(100000, 999999));
    }

    private function storeOTP($userId, $otp) {
        $expiryTime = date('Y-m-d H:i:s', time() + $this->otpExpiry);

        $deleteQuery = "DELETE FROM user_otp WHERE user_id = :user_id";
        $this->db->query($deleteQuery, ['user_id' => $userId]);

        $insertQuery = "INSERT INTO user_otp (user_id, otp_code, expires_at, created_at)
                        VALUES (:user_id, :otp_code, :expires_at, NOW())";

        return $this->db->query($insertQuery, [
            'user_id' => $userId,
            'otp_code' => password_hash($otp, PASSWORD_DEFAULT),
            'expires_at' => $expiryTime
        ]);
    }

    // Returns false if an OTP was already sent less than 2 minutes ago (prevent email flood)
    private function canSendOTP($userId) {
        $row = $this->db->fetch(
            "SELECT created_at FROM user_otp WHERE user_id = :uid ORDER BY created_at DESC LIMIT 1",
            ['uid' => $userId]
        );
        if (!$row) return true;
        return (time() - strtotime($row['created_at'])) >= 120;
    }

    // Auto-create table if missing — called before any read or write to user_otp_rate,
    // since isRateLimited() runs first and used to crash on a fresh/dropped table.
    private function ensureOtpRateTable() {
        $this->db->query("CREATE TABLE IF NOT EXISTS user_otp_rate (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip         VARCHAR(45) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_time (ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Returns true if this IP has exceeded max login attempts in the last minute
    private function isRateLimited($ip) {
        $this->ensureOtpRateTable();
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS cnt FROM user_otp_rate
             WHERE ip = :ip AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            ['ip' => $ip]
        );
        return ($row && $row['cnt'] >= 10);
    }

    private function recordLoginAttempt($ip) {
        $this->ensureOtpRateTable();

        $this->db->query(
            "INSERT INTO user_otp_rate (ip) VALUES (:ip)",
            ['ip' => $ip]
        );
        // Clean up old records (older than 1 hour) to keep the table small
        $this->db->query("DELETE FROM user_otp_rate WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    }

    private function sendOTPEmail($email, $username, $otp) {
        require_once '../vendor/autoload.php';

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = SMTP_PORT;

            $mail->CharSet = 'UTF-8';
            $mail->XMailer = ' ';
            $mail->setFrom(SMTP_USERNAME, defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'digiPharm');
            $mail->addAddress($email, $username);
            $mail->addReplyTo(SMTP_USERNAME, defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'digiPharm');

            $mail->isHTML(true);
            $mail->Subject = "digiPharm — Votre code de connexion";
            $otpSpaced = implode(' ', str_split($otp));
            $mail->AltBody = "Bonjour $username,\n\nVotre code de connexion digiPharm : $otp\n\nCe code expire dans 5 minutes. Ne le partagez avec personne.\n\n© Digitech Congo";
            $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>digiPharm — Code de vérification</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f4f0;font-family:Arial,'Helvetica Neue',sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f0f4f0" style="background-color:#f0f4f0;">
  <tr>
    <td align="center" style="padding:48px 16px 40px;">

      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">

        <!-- Header -->
        <tr>
          <td bgcolor="#188038" style="background-color:#188038;padding:32px 48px;">
            <table cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td width="48" height="48" bgcolor="#0d652d" style="background-color:rgba(0,0,0,0.15);border-radius:10px;text-align:center;vertical-align:middle;">
                  <span style="display:block;color:#ffffff;font-size:26px;font-weight:900;line-height:48px;text-align:center;">✚</span>
                </td>
                <td style="padding-left:16px;vertical-align:middle;">
                  <div style="color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.5px;line-height:1.2;">digiPharm</div>
                  <div style="color:rgba(255,255,255,0.65);font-size:10.5px;font-weight:500;letter-spacing:0.08em;text-transform:uppercase;margin-top:3px;">by Digitech Congo</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Accent line -->
        <tr>
          <td height="3" bgcolor="#0d652d" style="background-color:#0d652d;height:3px;font-size:0;line-height:0;">&nbsp;</td>
        </tr>

        <!-- Content -->
        <tr>
          <td style="padding:44px 48px 36px;">

            <h1 style="margin:0 0 6px;font-size:23px;font-weight:700;color:#1a1a1a;letter-spacing:-0.4px;">Code OTP</h1>
            <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px;">
              <tr>
                <td width="36" height="3" bgcolor="#188038" style="background-color:#188038;border-radius:2px;height:3px;font-size:0;">&nbsp;</td>
              </tr>
            </table>

            <p style="margin:0 0 24px;font-size:15.5px;color:#374151;line-height:1.6;">
              Bonjour <strong style="color:#1a1a1a;">$username</strong>,
            </p>
            <p style="margin:0 0 24px;font-size:15px;color:#4b5563;line-height:1.7;">
              Entrez le code ci-dessous pour finaliser votre connexion à digiPharm.
            </p>

            <!-- OTP Box -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 32px;">
              <tr>
                <td align="center" bgcolor="#e8f5e9" style="background-color:#e8f5e9;border:2px solid #188038;border-radius:14px;padding:32px 24px;">
                  <div style="font-family:'Courier New',Courier,monospace;font-size:46px;font-weight:700;letter-spacing:14px;color:#188038;text-align:center;line-height:1;text-indent:14px;">$otpSpaced</div>
                  <p style="margin:16px 0 0;font-size:13px;color:#388e3c;text-align:center;">
                    ⏱&nbsp; Ce code expire dans <strong>5 minutes</strong>
                  </p>
                </td>
              </tr>
            </table>

            <!-- Security note -->
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">
              <tr>
                <td bgcolor="#fafafa" style="background-color:#fafafa;border:1px solid #e5e7eb;border-radius:8px;padding:14px 18px;">
                  <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">
                    🔒 &nbsp;digiPharm ne vous demandera <strong>jamais</strong> ce code par téléphone ou chat. Ne le partagez avec personne.
                  </p>
                </td>
              </tr>
            </table>

          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td bgcolor="#f8faf8" style="background-color:#f8faf8;border-top:1px solid #e0ece4;padding:24px 48px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td>
                  <p style="margin:0;font-size:11.5px;color:#9ca3af;line-height:1.7;">
                    © 2026 <strong style="color:#6b7280;">Digitech Congo</strong> · digiPharm<br>
                    <a href="mailto:support@digitaltechnologiescongo.com" style="color:#188038;text-decoration:none;font-weight:500;">support@digitaltechnologiescongo.com</a>
                  </p>
                </td>
                <td align="right">
                  <span style="font-size:18px;font-weight:700;color:#188038;letter-spacing:-0.3px;">digi<span style="color:#0d652d;">Pharm</span></span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

      </table>

      <p style="margin:20px auto 0;max-width:440px;font-size:11px;color:#a8b3a8;text-align:center;line-height:1.6;">
        Si vous n'avez pas demandé ce code, ignorez simplement cet email.
      </p>

    </td>
  </tr>
</table>

</body>
</html>
HTML;

            return $mail->send();
        } catch (Exception $e) {
            error_log("Email sending failed: {$mail->ErrorInfo}");
            return false;
        }
    }

    public function authenticateCredentials($username, $password) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ip = trim(explode(',', $ip)[0]);

        if ($this->isRateLimited($ip)) {
            return ['success' => false, 'message' => 'Trop de tentatives. Réessayez dans une minute.'];
        }

        $query = "SELECT id, username, email, role, password, pharmacy_id FROM user WHERE username = :username";
        $user = $this->db->fetch($query, ['username' => $username]);

        if (!$user) {
            $this->recordLoginAttempt($ip);
            return ['success' => false, 'message' => 'Nom d\'utilisateur incorrect'];
        }

        if (!password_verify($password, $user['password'])) {
            $this->recordLoginAttempt($ip);
            return ['success' => false, 'message' => 'Mot de passe incorrect'];
        }

        // Emergency bypass: when OTP_BYPASS=true in env.php, skip email and log in directly.
        if (defined('OTP_BYPASS') && OTP_BYPASS === true) {
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['username']    = $user['username'];
            $_SESSION['role']        = $user['role'];
            $_SESSION['pharmacy_id'] = (int)($user['pharmacy_id'] ?? 1);
            $_SESSION['id']          = session_id();
            $_SESSION['login_time']  = time();

            $redirect = match($user['role']) {
                'SELLER'        => 'seller/index.php',
                'CASHIER'       => 'cashier/index.php',
                'ADMIN'         => 'admin/index.php',
                'STOCK-MANAGER' => 'stock-manager/index.php',
                default         => '',
            };
            return ['success' => true, 'role' => $user['role'], 'redirect' => $redirect, 'message' => 'Connexion réussie'];
        }

        // If an OTP was sent less than 2 minutes ago, don't send another email
        if (!$this->canSendOTP($user['id'])) {
            $_SESSION['temp_user'] = [
                'id'          => $user['id'],
                'username'    => $user['username'],
                'email'       => $user['email'],
                'role'        => $user['role'],
                'pharmacy_id' => (int)($user['pharmacy_id'] ?? 1),
            ];
            return ['success' => true, 'message' => 'Un code a déjà été envoyé. Vérifiez votre boîte mail.'];
        }

        $this->recordLoginAttempt($ip);
        $otp = $this->generateOTP();

        if ($this->storeOTP($user['id'], $otp)) {
            $this->sendOTPEmail($user['email'], $user['username'], $otp);
            $_SESSION['temp_user'] = [
                'id'          => $user['id'],
                'username'    => $user['username'],
                'email'       => $user['email'],
                'role'        => $user['role'],
                'pharmacy_id' => (int)($user['pharmacy_id'] ?? 1),
            ];
            return ['success' => true, 'message' => 'Code OTP envoyé à votre email'];
        }
        return ['success' => false, 'message' => 'Erreur lors de l\'envoi du code OTP'];
    }

    public function verifyOTP($inputOTP) {
        if (!isset($_SESSION['temp_user'])) {
            return ['success' => false, 'message' => 'Session expirée. Reconnectez-vous.'];
        }
        $userId = $_SESSION['temp_user']['id'];
        $query = "SELECT otp_code, expires_at FROM user_otp 
                  WHERE user_id = :user_id AND expires_at > NOW() 
                  ORDER BY created_at DESC LIMIT 1";

        $otpRecord = $this->db->fetch($query, ['user_id' => $userId]);

        if (!$otpRecord) {
            return ['success' => false, 'message' => 'Code OTP expiré ou invalide'];
        }

        if (password_verify($inputOTP, $otpRecord['otp_code'])) {
            $user = $_SESSION['temp_user'];
            unset($_SESSION['temp_user']);

            $deleteQuery = "DELETE FROM user_otp WHERE user_id = :user_id";
            $this->db->query($deleteQuery, ['user_id' => $userId]);

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['pharmacy_id'] = (int)($user['pharmacy_id'] ?? 1);
            $_SESSION['id']         = session_id();
            $_SESSION['login_time'] = time();

            $logQuery = "INSERT INTO log (userId, action, tableName, recordId, description, createdAt)
                         VALUES (:iduser, 'login_success', 'user', :recordId, 'Utilisateur connecté avec OTP', NOW())";
            $this->db->query($logQuery, [
                'iduser' => $user['id'],
                'recordId' => $user['id']
            ]);

            $redirectUrl = '';
            switch ($user['role']) {
                case 'SELLER': $redirectUrl = 'seller/index.php'; break;
                case 'CASHIER': $redirectUrl = 'cashier/index.php'; break;
                case 'ADMIN': $redirectUrl = 'admin/index.php'; break;
                case 'STOCK-MANAGER': $redirectUrl = 'stock-manager/index.php'; break;
                default: $redirectUrl = '../';
            }

            return [
                'success' => true,
                'role' => $user['role'],
                'redirect' => $redirectUrl,
                'message' => 'Connexion réussie'
            ];
        }

        return ['success' => false, 'message' => 'Code OTP incorrect'];
    }

    public function resendOTP() {
        if (!isset($_SESSION['temp_user'])) {
            return ['success' => false, 'message' => 'Session expirée. Reconnectez-vous.'];
        }

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $ip = trim(explode(',', $ip)[0]);

        if ($this->isRateLimited($ip)) {
            return ['success' => false, 'message' => 'Trop de demandes. Réessayez dans une minute.'];
        }

        $user = $_SESSION['temp_user'];

        if (!$this->canSendOTP($user['id'])) {
            return ['success' => false, 'message' => 'Veuillez attendre 2 minutes avant de renvoyer un code.'];
        }

        $this->recordLoginAttempt($ip);
        $otp = $this->generateOTP();

        if ($this->storeOTP($user['id'], $otp)) {
            $this->sendOTPEmail($user['email'], $user['username'], $otp);
            return ['success' => true, 'message' => 'Nouveau code OTP envoyé'];
        }

        return ['success' => false, 'message' => 'Erreur lors de l\'envoi du code OTP'];
    }
}

// Initialize OTP Auth
$otpAuth = new OTPAuth($db);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login' && isset($_POST['username'], $_POST['password'])) {
        echo json_encode($otpAuth->authenticateCredentials($_POST['username'], $_POST['password']));
        exit;
    }

    if ($action === 'verify_otp' && isset($_POST['otp'])) {
        echo json_encode($otpAuth->verifyOTP($_POST['otp']));
        exit;
    }

    if ($action === 'resend_otp') {
        echo json_encode($otpAuth->resendOTP());
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit;
}

// Si GET → réponse JSON (au lieu d’une redirection HTML)
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Déjà connecté', 'role' => $_SESSION['role']]);
exit;
