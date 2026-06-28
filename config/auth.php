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

    private function sendOTPEmail($email, $username, $otp) {
        require_once '../vendor/autoload.php';

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;

            $mail->setFrom(SMTP_USER, 'DigiPharma');
            $mail->addAddress($email, $username);

            $mail->isHTML(true);
            $mail->Subject = 'Code de vérification - DigiPharma';
            $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:500px;margin:auto;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;'>
                <div style='background:#22C55E;padding:24px;text-align:center;'>
                    <svg viewBox='0 0 200 200' width='60' height='60' xmlns='http://www.w3.org/2000/svg'>
                        <path d='M60 20 L140 20 L140 60 L180 60 L180 140 L140 140 L140 180 L60 180 L60 140 L20 140 L20 60 L60 60 Z' fill='white'/>
                        <path d='M75 35 L125 35 L125 75 L165 75 L165 125 L125 125 L125 165 L75 165 L75 125 L35 125 L35 75 L75 75 Z' fill='#22C55E'/>
                        <rect x='97' y='50' width='6' height='100' fill='white'/>
                    </svg>
                    <h1 style='color:white;margin:8px 0 0;font-size:22px;'>DigiPharma</h1>
                </div>
                <div style='padding:32px;'>
                    <h2 style='color:#111827;margin-top:0;'>Bonjour $username,</h2>
                    <p style='color:#6b7280;'>Votre code de vérification est :</p>
                    <div style='background:#f0fdf4;border:2px solid #22C55E;border-radius:8px;text-align:center;padding:16px;margin:24px 0;'>
                        <span style='font-size:36px;font-weight:bold;letter-spacing:8px;color:#22C55E;'>$otp</span>
                    </div>
                    <p style='color:#6b7280;'>Ce code expire dans <strong>5 minutes</strong>.</p>
                    <p style='color:#9ca3af;font-size:13px;'>Si vous n'avez pas demandé ce code, ignorez cet email.</p>
                </div>
                <div style='background:#f9fafb;padding:16px;text-align:center;border-top:1px solid #e5e7eb;'>
                    <p style='color:#6b7280;margin:0;font-size:13px;'>Cordialement,<br><strong>L'équipe DigiPharma</strong></p>
                </div>
            </div>
            ";

            return $mail->send();
        } catch (Exception $e) {
            error_log("Email sending failed: {$mail->ErrorInfo}");
            return false;
        }
    }

    public function authenticateCredentials($username, $password) {
        $query = "SELECT id, username, email, role, password FROM user WHERE username = :username";
        $user = $this->db->fetch($query, ['username' => $username]);

        if (!$user) {
            return ['success' => false, 'message' => 'Nom d\'utilisateur incorrect'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Mot de passe incorrect'];
        }

        $otp = $this->generateOTP();

        if ($this->storeOTP($user['id'], $otp)) {
            $this->sendOTPEmail($user['email'], $user['username'], $otp);
            $_SESSION['temp_user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            return [
                'success' => true,
                'message' => 'Code OTP envoyé à votre email'
            ];
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

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['id'] = session_id();
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

        $user = $_SESSION['temp_user'];
        $otp = $this->generateOTP();

        if ($this->storeOTP($user['id'], $otp)) {
            $this->sendOTPEmail($user['email'], $user['username'], $otp);

            return [
                'success' => true,
                'message' => 'Nouveau code OTP envoyé'
            ];
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
