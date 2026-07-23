<?php
/**
 * Shared mailer — wraps PHPMailer with SMTP from env.php.
 * Call: Mailer::send($to, $toName, $subject, $htmlBody)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

class Mailer
{
    public static function send(string $to, string $toName, string $subject, string $htmlBody): bool
    {
        if (!defined('SMTP_HOST')) {
            require_once dirname(__DIR__) . '/config/env.php';
        }
        try {
            require_once dirname(__DIR__) . '/vendor/autoload.php';

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = (defined('SMTP_ENCRYPTION') && SMTP_ENCRYPTION === 'ssl')
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom(SMTP_USERNAME, defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'digiPharm');
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

    // ─── Transactional templates ──────────────────────────────────────────────

    public static function registrationConfirmation(
        string $email,
        string $pharmacyName,
        string $responsible,
        string $plan
    ): bool {
        $planLabel = $plan === 'pro' ? 'Pro + IA — $25 HT/mois' : 'Basique — $10 HT/mois';
        $planColor = $plan === 'pro' ? '#188038' : '#5f6368';

        $content = self::greeting($responsible)
            . self::paragraph("Nous avons bien reçu la demande d'inscription de <strong>" . htmlspecialchars($pharmacyName) . "</strong>.")
            . self::infoBox([
                'Forfait sélectionné' => "<span style='color:{$planColor};font-weight:600;'>{$planLabel}</span>",
                'Période d\'essai'    => '<strong>14 jours gratuits</strong> dès l\'activation',
                'Délai d\'activation' => 'Sous <strong>24 heures</strong> ouvrées',
              ])
            . self::highlight('🎁', 'Votre période d\'essai de 14 jours commencera dès que notre équipe aura activé votre compte. Vous recevrez alors vos identifiants de connexion par email.')
            . self::paragraph("Si vous n'avez pas effectué cette demande, ignorez simplement cet email.", '#9ca3af', '13px');

        return self::send(
            $email,
            $responsible,
            "Votre inscription digiPharm — " . $pharmacyName,
            self::layout("Demande d'inscription reçue", $content)
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
        $content = self::greeting($responsible)
            . self::paragraph("Bonne nouvelle ! Votre compte digiPharm pour <strong>" . htmlspecialchars($pharmacyName) . "</strong> vient d'être activé. Votre période d'essai de <strong>14 jours</strong> commence maintenant.")
            . self::credentialsBox($username, $tempPassword)
            . self::ctaButton('Accéder à digiPharm', $loginUrl)
            . self::paragraph("Ce lien vous mènera vers la page de connexion. Utilisez les identifiants ci-dessus, puis changez votre mot de passe dès votre première connexion.", '#9ca3af', '12px');

        return self::send(
            $email,
            $responsible,
            "Compte activé — Bienvenue sur digiPharm, " . $pharmacyName,
            self::layout("Votre compte est activé !", $content, 'Bienvenue sur digiPharm')
        );
    }

    public static function registrationRejected(
        string $email,
        string $pharmacyName,
        string $responsible
    ): bool {
        $content = self::greeting($responsible)
            . self::paragraph("Nous avons examiné votre demande d'inscription pour <strong>" . htmlspecialchars($pharmacyName) . "</strong>.")
            . self::paragraph("Nous ne sommes malheureusement pas en mesure de donner suite à cette demande pour le moment.")
            . self::highlight('💬', 'Pour toute question ou si vous souhaitez plus d\'informations, n\'hésitez pas à nous contacter directement à <a href="mailto:support@digitaltechnologiescongo.com" style="color:#188038;text-decoration:none;">support@digitaltechnologiescongo.com</a>. Nous ferons de notre mieux pour vous accompagner.');

        return self::send(
            $email,
            $responsible,
            "Votre demande d'inscription digiPharm",
            self::layout("Mise à jour de votre demande", $content)
        );
    }

    // ─── Layout & component builders ─────────────────────────────────────────

    private static function layout(string $title, string $content, string $preheader = ''): string
    {
        $preheaderHtml = $preheader
            ? '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#f8faf8;">' . htmlspecialchars($preheader) . '</div>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>digiPharm</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f4f0;font-family:Arial,'Helvetica Neue',sans-serif;-webkit-font-smoothing:antialiased;">
{$preheaderHtml}

<!-- Outer wrapper -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f0f4f0" style="background-color:#f0f4f0;">
  <tr>
    <td align="center" style="padding:48px 16px 40px;">

      <!-- Card -->
      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">

        <!-- ── Header ── -->
        <tr>
          <td bgcolor="#188038" style="background-color:#188038;padding:32px 48px;">
            <table cellpadding="0" cellspacing="0" border="0">
              <tr>
                <!-- Pharmacy cross icon -->
                <td width="48" height="48" bgcolor="#0d652d" style="background-color:rgba(0,0,0,0.15);border-radius:10px;text-align:center;vertical-align:middle;width:48px;height:48px;">
                  <span style="display:block;color:#ffffff;font-size:26px;font-weight:900;line-height:48px;text-align:center;">✚</span>
                </td>
                <td style="padding-left:16px;vertical-align:middle;">
                  <div style="color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.5px;line-height:1.2;margin:0;">digiPharm</div>
                  <div style="color:rgba(255,255,255,0.65);font-size:10.5px;font-weight:500;letter-spacing:0.08em;text-transform:uppercase;margin-top:3px;">by Digitech Congo</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Decorative accent line -->
        <tr>
          <td height="3" bgcolor="#0d652d" style="background-color:#0d652d;height:3px;font-size:0;line-height:0;">&nbsp;</td>
        </tr>

        <!-- ── Content ── -->
        <tr>
          <td style="padding:44px 48px 36px;">

            <!-- Title -->
            <h1 style="margin:0 0 6px;font-size:23px;font-weight:700;color:#1a1a1a;letter-spacing:-0.4px;line-height:1.3;">{$title}</h1>
            <!-- Green underline accent -->
            <table cellpadding="0" cellspacing="0" border="0" style="margin:0 0 28px;">
              <tr>
                <td width="36" height="3" bgcolor="#188038" style="background-color:#188038;border-radius:2px;height:3px;font-size:0;">&nbsp;</td>
              </tr>
            </table>

            {$content}

          </td>
        </tr>

        <!-- ── Footer ── -->
        <tr>
          <td bgcolor="#f8faf8" style="background-color:#f8faf8;border-top:1px solid #e0ece4;padding:24px 48px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="vertical-align:middle;">
                  <p style="margin:0;font-size:11.5px;color:#9ca3af;line-height:1.7;">
                    © 2026 <strong style="color:#6b7280;">Digitech Congo</strong> · digiPharm<br>
                    <a href="mailto:support@digitaltechnologiescongo.com" style="color:#188038;text-decoration:none;font-weight:500;">support@digitaltechnologiescongo.com</a>
                  </p>
                </td>
                <td align="right" style="vertical-align:middle;">
                  <span style="font-size:18px;font-weight:700;color:#188038;letter-spacing:-0.3px;">digi<span style="color:#0d652d;">Pharm</span></span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

      </table>
      <!-- End card -->

      <!-- Below-card note -->
      <p style="margin:20px auto 0;max-width:440px;font-size:11px;color:#a8b3a8;text-align:center;line-height:1.6;">
        Vous recevez cet email parce que vous avez interagi avec digiPharm.
        Si ce n'est pas vous, ignorez simplement ce message.
      </p>

    </td>
  </tr>
</table>
<!-- End outer -->

</body>
</html>
HTML;
    }

    // ── Content component helpers ─────────────────────────────────────────────

    private static function greeting(string $name): string
    {
        return '<p style="margin:0 0 18px;font-size:15.5px;color:#374151;line-height:1.6;">Bonjour <strong style="color:#1a1a1a;">' . htmlspecialchars($name) . '</strong>,</p>';
    }

    private static function paragraph(string $text, string $color = '#4b5563', string $size = '15px'): string
    {
        return "<p style=\"margin:0 0 16px;font-size:{$size};color:{$color};line-height:1.7;\">{$text}</p>";
    }

    private static function highlight(string $icon, string $text): string
    {
        return <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;">
  <tr>
    <td bgcolor="#e8f5e9" style="background-color:#e8f5e9;border-left:4px solid #188038;border-radius:0 10px 10px 0;padding:18px 20px;">
      <p style="margin:0;font-size:14px;color:#1a5c2a;line-height:1.6;">{$icon} &nbsp;{$text}</p>
    </td>
  </tr>
</table>
HTML;
    }

    private static function infoBox(array $rows): string
    {
        $rowsHtml = '';
        $first = true;
        foreach ($rows as $label => $value) {
            $border = $first ? '' : 'border-top:1px solid #e5e7eb;';
            $first  = false;
            $rowsHtml .= <<<HTML
<tr>
  <td style="padding:12px 0;font-size:13px;color:#9ca3af;font-weight:600;letter-spacing:0.03em;text-transform:uppercase;width:160px;vertical-align:top;{$border}">{$label}</td>
  <td style="padding:12px 0;font-size:14.5px;color:#1a1a1a;vertical-align:top;{$border}">{$value}</td>
</tr>
HTML;
        }
        return <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0 24px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
  <tr>
    <td bgcolor="#f9fafb" colspan="2" style="background-color:#f9fafb;padding:10px 20px 6px;">
      <span style="font-size:11px;color:#6b7280;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">Récapitulatif</span>
    </td>
  </tr>
  <tr>
    <td colspan="2" height="1" bgcolor="#e5e7eb" style="background:#e5e7eb;font-size:0;padding:0;">&nbsp;</td>
  </tr>
  <tr>
    <td colspan="2" style="padding:0 20px;">
      <table width="100%" cellpadding="0" cellspacing="0" border="0">
        {$rowsHtml}
      </table>
    </td>
  </tr>
</table>
HTML;
    }

    private static function credentialsBox(string $username, string $password): string
    {
        return <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;border-radius:10px;overflow:hidden;border:1px solid #e0e0e0;">
  <tr>
    <td bgcolor="#188038" style="background-color:#188038;padding:10px 22px;">
      <span style="color:rgba(255,255,255,0.9);font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">🔑 Identifiants de connexion</span>
    </td>
  </tr>
  <tr>
    <td bgcolor="#f9fafb" style="background-color:#f9fafb;padding:20px 22px;">
      <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td style="padding:8px 0;font-size:13px;color:#6b7280;width:140px;font-weight:600;">Identifiant</td>
          <td style="padding:8px 0;font-size:15px;color:#111827;font-family:'Courier New',monospace;font-weight:700;" align="right">{$username}</td>
        </tr>
        <tr>
          <td colspan="2" height="1" bgcolor="#e5e7eb" style="background:#e5e7eb;font-size:0;line-height:0;padding:0;">&nbsp;</td>
        </tr>
        <tr>
          <td style="padding:8px 0;font-size:13px;color:#6b7280;font-weight:600;">Mot de passe</td>
          <td style="padding:8px 0;font-size:15px;color:#111827;font-family:'Courier New',monospace;font-weight:700;" align="right">{$password}</td>
        </tr>
      </table>
      <p style="margin:14px 0 0;font-size:12px;color:#dc2626;">⚠ Changez votre mot de passe dès votre première connexion.</p>
    </td>
  </tr>
</table>
HTML;
    }

    private static function ctaButton(string $label, string $url): string
    {
        return <<<HTML
<table cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 20px;">
  <tr>
    <td bgcolor="#188038" style="background-color:#188038;border-radius:10px;">
      <a href="{$url}" target="_blank"
         style="display:inline-block;padding:15px 40px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;letter-spacing:0.02em;white-space:nowrap;">
        {$label} &rarr;
      </a>
    </td>
  </tr>
</table>
HTML;
    }
}
