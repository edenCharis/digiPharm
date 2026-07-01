<?php
/**
 * Shared mailer helper — wraps PHPMailer with SMTP constants from env.php.
 * Usage: Mailer::send($to, $toName, $subject, $htmlBody)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

class Mailer
{
    public static function send(string $to, string $toName, string $subject, string $htmlBody): bool
    {
        if (!defined('SMTP_HOST')) {
            // Walk up until env.php is found (works from any subdirectory depth)
            $base = dirname(__DIR__);
            require_once $base . '/env.php';
        }

        try {
            require_once dirname(__DIR__) . '/vendor/autoload.php';

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_USER, 'digiPharm');
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

            return $mail->send();
        } catch (MailerException $e) {
            error_log('Mailer::send failed to ' . $to . ': ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log('Mailer::send error: ' . $e->getMessage());
            return false;
        }
    }

    // ─── Branded email templates ─────────────────────────────────────────────

    public static function registrationConfirmation(
        string $email,
        string $pharmacyName,
        string $responsible,
        string $plan
    ): bool {
        $planLabel = $plan === 'pro' ? 'Pro + IA — $25 HT/mois' : 'Basique — $10 HT/mois';

        $body = self::layout("Demande d'inscription reçue ✅", "
            <p>Bonjour <strong>" . htmlspecialchars($responsible) . "</strong>,</p>
            <p>Nous avons bien reçu la demande d'inscription de <strong>" . htmlspecialchars($pharmacyName) . "</strong> pour le forfait <strong>{$planLabel}</strong>.</p>
            <p>Notre équipe va activer votre compte sous <strong>24 heures</strong> ouvrées et vous envoyer vos identifiants de connexion.</p>
            <div style='background:#e6f4ea;border:1px solid #b7dfc5;border-radius:8px;padding:16px;margin:24px 0;'>
                <p style='margin:0;color:#0d652d;font-weight:500;font-size:14px;'>
                    🎁 Votre période d'essai de 14 jours commencera dès l'activation.
                </p>
            </div>
            <p style='color:#5f6368;font-size:13px;'>Si vous n'avez pas effectué cette demande, ignorez cet email.</p>
        ");

        return self::send(
            $email,
            $responsible,
            "Votre inscription digiPharm — " . $pharmacyName,
            $body
        );
    }

    public static function accountActivation(
        string $email,
        string $pharmacyName,
        string $responsible,
        string $username,
        string $tempPassword,
        string $loginUrl
    ): bool {
        $body = self::layout("Votre compte digiPharm est activé 🎉", "
            <p>Bonjour <strong>" . htmlspecialchars($responsible) . "</strong>,</p>
            <p>Votre compte digiPharm pour <strong>" . htmlspecialchars($pharmacyName) . "</strong> vient d'être activé par notre équipe.</p>
            <div style='background:#f8f9fa;border:1px solid #dadce0;border-radius:10px;padding:20px 24px;margin:24px 0;'>
                <p style='margin:0 0 12px;font-size:13px;color:#5f6368;font-weight:500;text-transform:uppercase;letter-spacing:.05em;'>Vos identifiants de connexion</p>
                <p style='margin:6px 0;font-size:15px;'><strong>Identifiant :</strong> " . htmlspecialchars($username) . "</p>
                <p style='margin:6px 0;font-size:15px;'><strong>Mot de passe temporaire :</strong> <code style='background:#fff;border:1px solid #dadce0;padding:2px 8px;border-radius:4px;font-size:14px;'>" . htmlspecialchars($tempPassword) . "</code></p>
                <p style='margin:14px 0 0;font-size:12px;color:#d93025;'>⚠ Changez votre mot de passe dès votre première connexion.</p>
            </div>
            <a href='" . htmlspecialchars($loginUrl) . "'
               style='display:inline-block;background:#188038;color:#fff;padding:13px 28px;border-radius:9px;text-decoration:none;font-weight:500;font-size:15px;'>
               Se connecter →
            </a>
            <p style='margin-top:24px;color:#5f6368;font-size:13px;'>Votre période d'essai de 14 jours a commencé. Profitez de toutes les fonctionnalités sans engagement.</p>
        ");

        return self::send(
            $email,
            $responsible,
            "Compte activé — Bienvenue sur digiPharm, " . $pharmacyName,
            $body
        );
    }

    public static function registrationRejected(
        string $email,
        string $pharmacyName,
        string $responsible
    ): bool {
        $body = self::layout("Mise à jour de votre demande d'inscription", "
            <p>Bonjour <strong>" . htmlspecialchars($responsible) . "</strong>,</p>
            <p>Nous avons bien reçu votre demande d'inscription pour <strong>" . htmlspecialchars($pharmacyName) . "</strong>.</p>
            <p>Malheureusement, nous ne sommes pas en mesure de donner suite à votre demande pour le moment.</p>
            <p>Pour toute question, n'hésitez pas à nous contacter directement.</p>
        ");

        return self::send(
            $email,
            $responsible,
            "Votre demande d'inscription digiPharm",
            $body
        );
    }

    // ─── Branded HTML layout ─────────────────────────────────────────────────

    private static function layout(string $title, string $content): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f8f9fa;font-family:Roboto,Arial,sans-serif;color:#202124;">
  <div style="max-width:560px;margin:40px auto;background:#fff;border:1px solid #dadce0;border-radius:12px;overflow:hidden;">
    <div style="background:#188038;padding:20px 32px;display:flex;align-items:center;gap:12px;">
      <span style="color:#fff;font-size:20px;font-weight:700;letter-spacing:-.3px;">digiPharm</span>
    </div>
    <div style="padding:32px;line-height:1.65;">
      <h2 style="font-size:19px;font-weight:700;color:#202124;margin:0 0 18px;">{$title}</h2>
      {$content}
    </div>
    <div style="background:#f8f9fa;padding:16px 32px;border-top:1px solid #dadce0;text-align:center;">
      <p style="margin:0;font-size:12px;color:#80868b;">© 2026 Digital Technologies Congo · digiPharm</p>
    </div>
  </div>
</body>
</html>
HTML;
    }
}
