<?php
// Vorlage: als config.php speichern (direkt beim Webhoster, NICHT ins Git-Repository)
// und die Werte eintragen. config.php ist in .gitignore ausgeschlossen.

return [
    // Mollie-Dashboard → Entwickler → API-Schlüssel. Erst "test_…", zum Start "live_…".
    // Leer lassen, dann bietet die Website nur "Kauf auf Rechnung" an.
    'mollie_api_key' => 'test_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',

    // Adresse der Website ohne Schrägstrich am Ende (für Rücksprung und Webhook von Mollie).
    'shop_url' => 'https://www.pinovalab.eu',

    // Hier kommen die Bestellungen an …
    'empfaenger' => 'info@pinovalab.eu',
    // … und von dieser Adresse gehen die Bestätigungen an Kunden (Postfach beim Webhoster).
    'absender' => 'info@pinovalab.eu',
];
