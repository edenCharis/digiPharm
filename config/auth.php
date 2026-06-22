<?php
/**
 * OTP Authentication System
 * File: config/auth.php
 */
 


error_reporting(E_ALL);
ini_set('display_errors', 0); // <-- cache les warnings/notices
header('Content-Type: application/json'); // <- important




use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
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
            $mail->Host = 'smtp.hostinger.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'system@sgp-galy.com';
            $mail->Password = 'F:6SEc>3';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('system@sgp-galy.com', 'Pharmacie');
            $mail->addAddress($email, $username);

            $mail->isHTML(true);
            $mail->Subject = 'Code de vérification - Pharmacie';
            $mail->Body = "
                <h2>Bonjour $username,</h2>
                <p>Votre code de vérification est: <strong>$otp</strong></p>
                <p>Ce code expire dans 5 minutes.</p>
                <p>Si vous n'avez pas demandé ce code, ignorez cet email.</p>
                <br>
                <p>Cordialement,<br>L'équipe Pharmacie</p>
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
