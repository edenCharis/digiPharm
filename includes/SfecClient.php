<?php
/**
 * Minimal client for the SFEC (Congo certified e-invoicing) Online ERP API.
 * https://docs.sfec.gouv.cg/
 */
class SfecClient {
    private $apiKey;
    private $baseUrl;

    public function __construct($apiKey, $baseUrl) {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * POST /v1/invoices — certify a sales invoice.
     * Never throws on HTTP-level failures; only on transport errors (network down, etc).
     *
     * @return array{success: bool, http_code: int, data: array|null, error: string|null}
     */
    public function certifyInvoice(array $payload) {
        $url = $this->baseUrl . '/v1/invoices';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        $responseBody = curl_exec($ch);

        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('SFEC: connexion impossible - ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($responseBody, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'http_code' => $httpCode,
                'data' => $decoded,
                'error' => null,
            ];
        }

        $statusMessages = [
            400 => 'Données invalides (format, champs manquants ou montants incohérents)',
            401 => 'Clé API SFEC invalide ou manquante',
            409 => 'Facture déjà certifiée ou déclarée',
            422 => 'Erreur de validation métier SFEC',
            500 => 'Erreur serveur SFEC',
        ];

        $apiMessage = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? null) : null;
        $error = $statusMessages[$httpCode] ?? ('Erreur SFEC inattendue (HTTP ' . $httpCode . ')');
        if ($apiMessage) {
            $error .= ': ' . $apiMessage;
        }

        return [
            'success' => false,
            'http_code' => $httpCode,
            'data' => $decoded,
            'error' => $error,
        ];
    }
}
?>
