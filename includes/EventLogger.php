<?php
/**
 * EventLogger — central event bus for the AI data pipeline.
 *
 * Every significant business action (sale, stock change, delivery, etc.)
 * calls EventLogger::log(). Events land in the `events` table and are
 * consumed by the FastAPI AI service for feature engineering + ML.
 */
class EventLogger
{
    // ── Event type constants ──────────────────────────────────────────────
    const SALE_CREATED           = 'sale_created';
    const SALE_REFUNDED          = 'sale_refunded';
    const STOCK_UPDATED          = 'stock_updated';
    const STOCK_DELIVERY         = 'stock_delivery';
    const PRODUCT_CREATED        = 'product_created';
    const PRODUCT_UPDATED        = 'product_updated';
    const PURCHASE_ORDER_CREATED = 'purchase_order_created';
    const USER_LOGIN             = 'user_login';
    const CASH_REGISTER_OPENED   = 'cash_register_opened';
    const CASH_REGISTER_CLOSED   = 'cash_register_closed';

    private static bool $tableEnsured = false;

    // ── Public API ────────────────────────────────────────────────────────

    /**
     * Log an event. Safe to call anywhere — never throws.
     *
     * @param object   $db         Database wrapper (custom) or PDO
     * @param int      $pharmacyId Tenant ID
     * @param string   $eventType  One of the EventLogger::* constants
     * @param array    $payload    Arbitrary data — will be JSON-encoded
     */
    public static function log(object $db, int $pharmacyId, string $eventType, array $payload = []): void
    {
        try {
            self::ensureTable($db);
            $sql = "INSERT INTO events (pharmacy_id, event_type, payload, created_at)
                    VALUES (?, ?, ?, NOW())";
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (method_exists($db, 'execute')) {
                $db->execute($sql, [$pharmacyId, $eventType, $json]);
            } else {
                // Native PDO fallback
                $stmt = $db->prepare($sql);
                $stmt->execute([$pharmacyId, $eventType, $json]);
            }
        } catch (Throwable $e) {
            // Never break the main flow — silently log to PHP error log
            error_log('[EventLogger] ' . $e->getMessage());
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private static function ensureTable(object $db): void
    {
        if (self::$tableEnsured) return;
        $ddl = "CREATE TABLE IF NOT EXISTS events (
            id          BIGINT AUTO_INCREMENT PRIMARY KEY,
            pharmacy_id INT NOT NULL,
            event_type  VARCHAR(60) NOT NULL,
            payload     JSON NOT NULL,
            created_at  DATETIME NOT NULL,
            INDEX idx_pharmacy_type (pharmacy_id, event_type),
            INDEX idx_created_at    (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        try {
            if (method_exists($db, 'execute')) {
                $db->execute($ddl, []);
            } else {
                $db->exec($ddl);
            }
        } catch (Throwable $e) {
            error_log('[EventLogger] table creation: ' . $e->getMessage());
        }
        self::$tableEnsured = true;
    }
}
