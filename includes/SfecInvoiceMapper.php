<?php
/**
 * Maps a digiPharm sale into the SFEC "Online ERP" certification request
 * schema documented at https://docs.sfec.gouv.cg/online-report
 *
 * NOTE: the fetched docs listed certification_number/signature/qr_code/
 * certification_date as "required" request fields, which is unusual for a
 * certify-this-invoice call (they also appear in the *response* schema).
 * Treated here as server-generated outputs and left out of the request.
 * Validate against the sandbox once real credentials exist - if SFEC
 * rejects the payload for missing fields, this is the place to add them.
 *
 * @param array      $sale       sale row: id, invoiceNumber, totalAmount, totalVAT, discountAmount, saleDate, createdAt
 * @param array      $lineItems  list of: designation, unit_price, quantity, subtotal, discount_amount, net_amount, tax_rate, tax_amount, total_amount
 * @param array      $sfecSettings  ['taxpayer_niu' => string, 'sciet' => string]
 * @param array|null $client     ['name' => string, 'contact' => string|null] or null for a walk-in sale
 */
function buildSfecInvoicePayload(array $sale, array $lineItems, array $sfecSettings, ?array $client = null): array {
    $totalVAT = (float)($sale['totalVAT'] ?? 0);
    $discountAmount = (float)($sale['discountAmount'] ?? 0);
    $totalAmount = (float)$sale['totalAmount'];
    $subtotal = $totalAmount - $totalVAT + $discountAmount;

    $totalLineDiscount = array_sum(array_column($lineItems, 'discount_amount'));

    $paymentDate = $sale['saleDate'] ?? $sale['createdAt'];

    return [
        'taxpayer_niu' => $sfecSettings['taxpayer_niu'],
        'invoice_id' => $sale['id'],
        'invoice_number' => $sale['invoiceNumber'],
        'invoice_type' => 'salesInvoice',
        'invoice_status' => 'pending',
        'sciet' => $sfecSettings['sciet'],

        'recipient_type' => 'individual',
        'recipient_name' => $client['name'] ?? 'Client comptant',
        'recipient_phone' => $client['contact'] ?? null,
        'is_recipient_taxable' => false,

        'subtotal' => round($subtotal, 2),
        'total_tax_t_amount' => round($totalVAT, 2),
        'total_tax_r_amount' => 0,
        'total_exempt_amount' => 0,
        'total_tax_amount' => round($totalVAT, 2),
        'discount_amount' => round($discountAmount, 2),
        'total_line_discount_amount' => round($totalLineDiscount, 2),
        'additional_cent_tax' => 0,
        'electronic_stamp_duty' => 0,
        'total_amount' => round($totalAmount, 2),
        'amount_due' => round($totalAmount, 2),

        'currency' => 'XAF',
        'payment_method' => 'cash',
        'payment_date' => date('c', strtotime($paymentDate)),

        'line_items' => array_map(function ($item) {
            return [
                'designation' => $item['designation'],
                'type' => 'product',
                'unit_price' => round((float)$item['unit_price'], 2),
                'quantity' => $item['quantity'],
                'subtotal' => round((float)$item['subtotal'], 2),
                'discount_amount' => round((float)$item['discount_amount'], 2),
                'discount_type' => 'amount',
                'net_amount' => round((float)$item['net_amount'], 2),
                'amount_after_discount' => round((float)$item['net_amount'], 2),
                'tax_rate' => $item['tax_rate'],
                'tax_amount' => round((float)$item['tax_amount'], 2),
                'total_amount' => round((float)$item['total_amount'], 2),
            ];
        }, $lineItems),
    ];
}
?>
