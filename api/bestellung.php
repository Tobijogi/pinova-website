<?php
// POST /api/bestellung.php – nimmt eine Bestellung an.
//   zahlart "rechnung": speichern, Mails an Pinova Lab und Kunde, fertig.
//   zahlart "online":   speichern, Mollie-Zahlung anlegen, Bezahl-URL zurückgeben.
//                       Die Mails verschickt erst mollie-webhook.php, wenn bezahlt ist.
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') antworte(['fehler' => 'Nur POST erlaubt.'], 405);

try {
    $d = lese_json();
    // Honeypot: echte Besucher sehen dieses Feld nicht.
    if (text($d['website'] ?? '', 200) !== '') antworte(['ok' => true]);

    $zahlart = ($d['zahlart'] ?? '') === 'online' ? 'online' : 'rechnung';
    $positionen = pruefe_warenkorb($d['warenkorb'] ?? null);
    $k = [
        'labor' => text($d['labor'] ?? '', 120),
        'ansprechpartner' => text($d['ansprechpartner'] ?? '', 120),
        'email' => text($d['email'] ?? '', 200),
        'telefon' => text($d['telefon'] ?? '', 40),
        'strasse' => text($d['strasse'] ?? '', 120),
        'plz' => text($d['plz'] ?? '', 10),
        'ort' => text($d['ort'] ?? '', 80),
        'ustid' => strtoupper(preg_replace('/\s+/', '', text($d['ustid'] ?? '', 20)) ?? ''),
        'nachricht' => text($d['nachricht'] ?? '', 2000),
    ];
    if ($k['labor'] === '') throw new Kundenfehler('Bitte geben Sie den Namen Ihres Labors bzw. Ihrer Praxis ein.');
    if ($k['ansprechpartner'] === '') throw new Kundenfehler('Bitte geben Sie eine Ansprechperson an.');
    if (!filter_var($k['email'], FILTER_VALIDATE_EMAIL)) throw new Kundenfehler('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
    if ($k['strasse'] === '' || $k['ort'] === '') throw new Kundenfehler('Bitte geben Sie die vollständige Lieferadresse an.');
    if (!preg_match('/^\d{5}$/', $k['plz'])) throw new Kundenfehler('Bitte geben Sie eine gültige deutsche Postleitzahl ein.');
    if (($d['unternehmer'] ?? false) !== true) throw new Kundenfehler('Bitte bestätigen Sie, dass Sie als Unternehmen bestellen.');
    if ($zahlart === 'online' && !mollie_bereit()) throw new Kundenfehler('Die Online-Zahlung ist gerade nicht verfügbar. Bitte wählen Sie „Kauf auf Rechnung“.');

    $b = [
        'nummer' => neue_bestellnummer(),
        'token' => bin2hex(random_bytes(16)),
        'zeit' => date('d.m.Y H:i'),
        'zahlart' => $zahlart,
        'status' => $zahlart === 'online' ? 'offen' : 'bestellt',
        'kunde' => $k,
        'positionen' => $positionen,
        'summen' => summen($positionen),
        'mails_gesendet' => false,
    ];

    if ($zahlart === 'rechnung') {
        speichere_bestellung($b);
        sende_bestellmails($b);
        $b['mails_gesendet'] = true;
        speichere_bestellung($b);
        antworte(['ok' => true, 'nummer' => $b['nummer']]);
    }

    $url = rtrim((string) (cfg()['shop_url'] ?? ''), '/');
    $zahlung = mollie('POST', 'payments', [
        'amount' => betrag($b['summen']['brutto']),
        'description' => "Pinova Lab {$b['nummer']}",
        'redirectUrl' => "$url/bestellung/danke/?nr={$b['nummer']}&t={$b['token']}",
        'cancelUrl' => "$url/#warenkorb",
        'webhookUrl' => "$url/api/mollie-webhook.php",
        'locale' => 'de_DE',
        'metadata' => ['nummer' => $b['nummer']],
    ]);
    $b['mollie_id'] = $zahlung['id'];
    speichere_bestellung($b);
    antworte(['url' => $zahlung['_links']['checkout']['href']]);
} catch (Throwable $e) {
    fehler_antwort($e);
}
