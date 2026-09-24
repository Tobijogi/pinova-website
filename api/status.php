<?php
// GET /api/status.php – sagt der Website, welche Bestellwege gerade funktionieren.
require __DIR__ . '/lib.php';

antworte([
    'online' => mollie_bereit(),
    'rechnung' => true,
    'test' => str_starts_with((string) (cfg()['mollie_api_key'] ?? ''), 'test_'),
]);
