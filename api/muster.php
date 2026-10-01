<?php
// POST /api/muster.php – Musteranfrage per Mail an Pinova Lab.
// Bewusst keine Bestätigungsmail an die angegebene Adresse: sonst ließe sich das
// Formular missbrauchen, um Mails an beliebige Empfänger zu verschicken.
require __DIR__ . '/lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') antworte(['fehler' => 'Nur POST erlaubt.'], 405);

try {
    $d = lese_json();
    // Honeypot: echte Besucher sehen dieses Feld nicht.
    if (text($d['website'] ?? '', 200) !== '') antworte(['ok' => true]);

    $labor = text($d['labor'] ?? '', 120);
    $email = text($d['email'] ?? '', 200);
    $nachricht = text($d['nachricht'] ?? '', 2000);
    if ($labor === '') throw new Kundenfehler('Bitte geben Sie den Namen Ihres Labors ein.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Kundenfehler('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
    // Nur bekannte Produkte übernehmen – wie die Checkboxen im Formular (index.html).
    $erlaubt = ['SHERA S01', 'SHERA S02', 'exocad E01', 'Personalisierte Sockelform'];
    $gewaehlt = is_array($d['pins'] ?? null) ? array_filter($d['pins'], 'is_string') : [];
    $pins = array_values(array_intersect($erlaubt, $gewaehlt));
    if (!$pins) throw new Kundenfehler('Bitte wählen Sie mindestens ein Produkt aus.');

    sende_mail(cfg()['absender'] ?? 'info@pinovalab.eu', "Musteranfrage – $labor (" . implode(', ', $pins) . ')', implode("\n", [
        'Musteranfrage über www.pinovalab.eu (' . date('d.m.Y H:i') . ')',
        '',
        "Labor: $labor",
        "E-Mail: $email",
        'Gewünschte Muster: ' . implode(', ', $pins),
        '',
        'Nachricht:',
        $nachricht !== '' ? $nachricht : '–',
        '',
        'Antworten Sie direkt auf diese E-Mail, um das Labor zu erreichen.',
    ]), $email);
    antworte(['ok' => true]);
} catch (Throwable $e) {
    fehler_antwort($e);
}
