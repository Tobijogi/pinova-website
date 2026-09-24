<?php
// GET /api/zahlung.php?nr=…&t=… – Zahlungsstatus für die Danke-Seite.
// Der geheime Token verhindert, dass Fremde fremde Bestellungen abfragen.
require __DIR__ . '/lib.php';

$b = lade_bestellung((string) ($_GET['nr'] ?? ''));
if ($b === null || !hash_equals($b['token'], (string) ($_GET['t'] ?? ''))) {
    antworte(['fehler' => 'Bestellung nicht gefunden.'], 404);
}

$status = $b['status'];
// Falls der Webhook noch nicht da war, direkt bei Mollie nachfragen.
if ($b['zahlart'] === 'online' && !in_array($status, ['paid', 'canceled', 'expired', 'failed'], true) && mollie_bereit()) {
    try {
        $status = mollie('GET', 'payments/' . $b['mollie_id'])['status'];
    } catch (Throwable $e) {
        error_log('Pinova-Shop Status: ' . $e->getMessage());
    }
}

antworte(['nummer' => $b['nummer'], 'status' => $status]);
