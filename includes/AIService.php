<?php
/**
 * AIService — PHP bridge to the DigiPharm FastAPI AI service.
 *
 * All methods return arrays (never throw). On timeout / service down,
 * they return ['error' => '...', 'available' => false] so the UI
 * can gracefully degrade to "Insights IA indisponibles".
 */
class AIService
{
    const BASE_URL = 'http://localhost:8000';
    const TIMEOUT  = 4; // seconds — fast fallback if service is down

    // ── Public endpoints ──────────────────────────────────────────────────

    /** Demand forecast for a specific product over the next N days */
    public static function forecast(int $pharmacyId, int $productId, int $days = 30): array
    {
        return self::get("/forecast/{$pharmacyId}/{$productId}", ['days' => $days]);
    }

    /** Top inventory recommendations: reorder now, slow movers, overstocked */
    public static function inventoryRecommendations(int $pharmacyId): array
    {
        return self::get("/inventory/recommendations/{$pharmacyId}");
    }

    /** Active alerts: expiry risk, stockout risk, anomalies */
    public static function alerts(int $pharmacyId): array
    {
        return self::get("/alerts/{$pharmacyId}");
    }

    /** Revenue trends + growth rate for the last N days */
    public static function trends(int $pharmacyId, int $days = 30): array
    {
        return self::get("/trends/{$pharmacyId}", ['days' => $days]);
    }

    /** Dashboard summary — single call that aggregates all KPIs */
    public static function dashboardSummary(int $pharmacyId): array
    {
        return self::get("/dashboard/{$pharmacyId}");
    }

    /** Top products by predicted demand next 7 days */
    public static function topProductsForecast(int $pharmacyId, int $limit = 5): array
    {
        return self::get("/forecast/top/{$pharmacyId}", ['limit' => $limit]);
    }

    /** Is the AI service reachable? */
    public static function ping(): bool
    {
        $r = self::get('/health');
        return isset($r['status']) && $r['status'] === 'ok';
    }

    // ── HTTP layer ────────────────────────────────────────────────────────

    private static function get(string $path, array $params = []): array
    {
        $url = self::BASE_URL . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => self::TIMEOUT,
                'header'  => "Accept: application/json\r\nX-Pharmacy-Id: digipharm\r\n",
                'ignore_errors' => true,
            ],
        ]);

        try {
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                return ['available' => false, 'error' => 'Service IA injoignable'];
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                return ['available' => false, 'error' => 'Réponse invalide du service IA'];
            }
            $data['available'] = true;
            return $data;
        } catch (Throwable $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    private static function post(string $path, array $body = []): array
    {
        $url     = self::BASE_URL . $path;
        $payload = json_encode($body);

        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'timeout'       => self::TIMEOUT,
                'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content'       => $payload,
                'ignore_errors' => true,
            ],
        ]);

        try {
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) return ['available' => false, 'error' => 'Service IA injoignable'];
            $data = json_decode($raw, true);
            if (!is_array($data)) return ['available' => false, 'error' => 'Réponse invalide'];
            $data['available'] = true;
            return $data;
        } catch (Throwable $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }
}
