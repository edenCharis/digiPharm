<?php
/**
 * SMTP mailer for DigiPharm AI — OTP codes, notifications.
 * Uses PHPMailer via the main vendor/autoload.php.
 */

function ai_send_mail(string $to, string $subject, string $htmlBody): bool
{
    $vendorPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!file_exists($vendorPath)) {
        error_log('[DigiPharm Mailer] vendor/autoload.php not found');
        return false;
    }
    require_once $vendorPath;

    $envPath = dirname(__DIR__, 2) . '/env.php';
    if (file_exists($envPath) && !defined('SMTP_HOST')) {
        require_once $envPath;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USER') ? SMTP_USER : '';
        $mail->Password   = defined('SMTP_PASS') ? SMTP_PASS : '';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = defined('SMTP_PORT') ? (int) SMTP_PORT : 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($mail->Username, 'DigiPharm AI');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('[DigiPharm Mailer] ' . $e->getMessage());
        return false;
    }
}

function ai_otp_email_html(string $otp, string $displayName, string $reason): string
{
    return "
    <div style='font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:480px;margin:0 auto;padding:32px 24px;'>
      <div style='margin-bottom:24px;'>
        <span style='font-size:20px;font-weight:700;color:#111827;'>DigiPharm <span style='color:#1a7f4b;'>AI</span></span>
      </div>
      <h2 style='font-size:20px;font-weight:700;color:#111827;margin-bottom:8px;'>Code de vérification</h2>
      <p style='color:#4b5563;font-size:14px;line-height:1.6;margin-bottom:24px;'>
        Bonjour {$displayName},<br><br>{$reason}
      </p>
      <div style='background:#f0faf4;border:1px solid #bbf7d0;border-radius:12px;padding:24px;text-align:center;margin-bottom:24px;'>
        <div style='font-size:36px;font-weight:800;letter-spacing:8px;color:#155e38;font-variant-numeric:tabular-nums;'>{$otp}</div>
        <div style='font-size:12px;color:#6b7280;margin-top:8px;'>Valable 10 minutes</div>
      </div>
      <p style='color:#9ca3af;font-size:12px;'>Si vous n'avez pas demandé ce code, ignorez cet email.</p>
      <hr style='border:none;border-top:1px solid #e5e7eb;margin:24px 0;'>
      <p style='color:#9ca3af;font-size:11px;'>DigiPharm AI · Digital Technologies Congo</p>
    </div>";
}
