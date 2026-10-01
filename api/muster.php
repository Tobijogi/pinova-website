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
    $person = text($d['ansprechpartner'] ?? '', 120);
    $strasse = text($d['strasse'] ?? '', 120);
    $plz = text($d['plz'] ?? '', 10);
    $ort = text($d['ort'] ?? '', 80);
    if ($person === '') throw new Kundenfehler('Bitte geben Sie eine Ansprechperson an.');
    if ($strasse === '' || $ort === '') throw new Kundenfehler('Bitte geben Sie die vollständige Lieferadresse an.');
    if (!preg_match('/^\d{5}$/', $plz)) throw new Kundenfehler('Bitte geben Sie eine gültige deutsche Postleitzahl ein.');
    // Nur bekannte Produkte übernehmen – wie die Checkboxen im Formular (index.html).
    $erlaubt = ['SHERA S01', 'SHERA S02', 'exocad E01', 'Personalisierte Sockelform'];
    $gewaehlt = is_array($d['pins'] ?? null) ? array_filter($d['pins'], 'is_string') : [];
    $pins = array_values(array_intersect($erlaubt, $gewaehlt));
    if (!$pins) throw new Kundenfehler('Bitte wählen Sie mindestens ein Produkt aus.');

    $zeit = date('d.m.Y H:i');
    $h = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $adresse = implode('<br>', array_map($h, [$labor, $person, $strasse, "$plz $ort", 'Deutschland']));
    // HTML-Fassung: Muster und Überschrift „Lieferadresse“ fett, damit beim Versand nichts verwechselt wird.
    $html = '<div style="font-family:Calibri,Arial,sans-serif;font-size:15px;line-height:1.45">'
        . '<p style="margin:0 0 14px">Musteranfrage über www.pinovalab.eu (' . $zeit . ')</p>'
        . '<p style="margin:0 0 14px">Labor: ' . $h($labor) . '<br>E-Mail: ' . $h($email)
        . '<br>Gewünschte Muster: <b>' . $h(implode(', ', $pins)) . '</b></p>'
        . '<p style="margin:0 0 14px"><b>Lieferadresse:</b></p>'
        . '<p style="margin:0 0 14px">' . $adresse . '</p>'
        . '<p style="margin:0 0 14px">Nachricht:<br>' . ($nachricht !== '' ? nl2br($h($nachricht)) : '–') . '</p>'
        . '<p style="margin:0 0 14px">Antworten Sie direkt auf diese E-Mail, um das Labor zu erreichen.</p>'
        . '</div>';

    sende_mail(cfg()['absender'] ?? 'info@pinovalab.eu', "Musteranfrage – $labor (" . implode(', ', $pins) . ')', implode("\n", [
        "Musteranfrage über www.pinovalab.eu ($zeit)",
        '',
        "Labor: $labor",
        "E-Mail: $email",
        'Gewünschte Muster: ' . implode(', ', $pins),
        '',
        'Lieferadresse:',
        '',
        $labor,
        $person,
        $strasse,
        "$plz $ort",
        'Deutschland',
        '',
        'Nachricht:',
        $nachricht !== '' ? $nachricht : '–',
        '',
        'Antworten Sie direkt auf diese E-Mail, um das Labor zu erreichen.',
    ]), $email, $html);
    antworte(['ok' => true]);
} catch (Throwable $e) {
    fehler_antwort($e);
}
