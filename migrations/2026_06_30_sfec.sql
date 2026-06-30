-- SFEC (Congo certified e-invoicing) readiness migration.
-- Safe to run on an empty sfec_api_key: certification stays inert until
-- an admin fills in sfec_taxpayer_niu + sfec_api_key from admin/settings.php.

ALTER TABLE `app_settings`
  MODIFY `setting_type` ENUM('text','textarea','image','color','number','password') DEFAULT 'text';

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `setting_type`, `description`)
SELECT * FROM (SELECT 'sfec_environment' AS setting_key, 'sandbox' AS setting_value, 'text' AS setting_type, 'Environnement SFEC (sandbox ou production)' AS description) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'sfec_environment');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `setting_type`, `description`)
SELECT * FROM (SELECT 'sfec_taxpayer_niu' AS setting_key, '' AS setting_value, 'text' AS setting_type, 'NIU (Numéro d''Identification Unique) du contribuable, fourni par le portail e-Facture' AS description) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'sfec_taxpayer_niu');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `setting_type`, `description`)
SELECT * FROM (SELECT 'sfec_api_key' AS setting_key, '' AS setting_value, 'password' AS setting_type, 'Clé API SFEC obtenue via le portail e-Facture' AS description) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'sfec_api_key');

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `setting_type`, `description`)
SELECT * FROM (SELECT 'sfec_sciet' AS setting_key, '' AS setting_value, 'text' AS setting_type, 'Identifiant SCIET fourni par SFEC lors de l''enregistrement du contribuable' AS description) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM `app_settings` WHERE `setting_key` = 'sfec_sciet');

ALTER TABLE `sale`
  ADD COLUMN `sfec_status` ENUM('not_configured','pending','certified','failed') DEFAULT 'not_configured',
  ADD COLUMN `sfec_certification_number` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN `sfec_invoice_number` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN `sfec_certification_date` DATETIME DEFAULT NULL,
  ADD COLUMN `sfec_signature` TEXT DEFAULT NULL,
  ADD COLUMN `sfec_short_signature` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN `sfec_qr_code` MEDIUMTEXT DEFAULT NULL,
  ADD COLUMN `sfec_error` TEXT DEFAULT NULL;
