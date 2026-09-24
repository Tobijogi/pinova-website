<?php
// POST /api/mollie-webhook.php – Mollie meldet hier jede Statusänderung einer Zahlung.
// Mollie schickt nur die Zahlungs-ID; den Status fragen wir selbst bei Mollie ab,
// deshalb kann niemand eine Zahlung vortäuschen.
require __DIR__ . '/lib.php';

$id = (string) ($_POST['id'] ?? '');
if (!preg_match('/^tr_[A-Za-z0-9]+$/', $id) || !mollie_bereit()) {
    http_response_code(200); // Unbekanntes einfach bestätigen, sonst wiederholt Mollie.
    exit;
}

try {
    $zahlung = mollie('GET', 'payments/' . $id);
    $b = lade_bestellung((string) ($zahlung['metadata']['nummer'] ?? ''));
    if ($b === null || ($b['mollie_id'] ?? '') !== $id) {
        http_response_code(200);
        exit;
    }

    $b['status'] = $zahlung['status']; // open, pending, authorized, paid, canceled, expired, failed
    if ($zahlung['status'] === 'paid' && !$b['mails_gesendet']) {
        speichere_bestellung($b);
        sende_bestellmails($b);
        $b['mails_gesendet'] = true;
    }
    speichere_bestellung($b);
    http_response_code(200);
} catch (Throwable $e) {
    error_log('Pinova-Shop Webhook: ' . $e->getMessage());
    http_response_code(500); // Mollie versucht es später erneut.
}
