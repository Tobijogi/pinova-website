<?php
// Gemeinsame Shop-Logik. Läuft beim deutschen Webhoster (PHP 8.0+ mit curl).
// Bestellungen werden als JSON-Dateien in api/daten/bestellungen/ gespeichert –
// ohne Datenbank und ohne Drittanbieter. Einzige Ausnahme: Online-Zahlungen über Mollie.

declare(strict_types=1);

// Verbindliche Preise in Cent (netto). Bei Änderungen auch data-price in index.html anpassen.
const PRODUKTE = [
    'shera-s01'  => ['name' => 'TL² PivotPin SHERA S01', 'einheit' => 'Packung (100 Stk.)', 'netto' => 1989],
    'shera-s02'  => ['name' => 'TL² PivotPin SHERA S02', 'einheit' => 'Packung (100 Stk.)', 'netto' => 1989],
    'exocad-e01' => ['name' => 'TL² PivotPin exocad E01', 'einheit' => 'Packung (100 Stk.)', 'netto' => 1889],
];
const VERSAND = ['name' => 'Versand innerhalb Deutschlands', 'netto' => 646];
const MWST_PROZENT = 19;
const MAX_MENGE = 500;

final class Kundenfehler extends Exception {}

function cfg(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $datei = __DIR__ . '/config.php';
        $cfg = is_file($datei) ? require $datei : [];
    }
    return $cfg;
}

function mollie_bereit(): bool
{
    return !empty(cfg()['mollie_api_key']);
}

function antworte(array $daten, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($daten, JSON_UNESCAPED_UNICODE);
    exit;
}

function fehler_antwort(Throwable $e): void
{
    if ($e instanceof Kundenfehler) antworte(['fehler' => $e->getMessage()], 400);
    error_log('Pinova-Shop: ' . $e->getMessage());
    antworte(['fehler' => 'Die Bestellung konnte gerade nicht verarbeitet werden. Bitte versuchen Sie es später erneut oder schreiben Sie uns eine E-Mail.'], 500);
}

function lese_json(): array
{
    $roh = file_get_contents('php://input', false, null, 0, 20000);
    $daten = json_decode((string) $roh, true);
    if (!is_array($daten)) throw new Kundenfehler('Ungültige Anfrage.');
    return $daten;
}

function text($wert, int $max): string
{
    if (!is_string($wert)) return '';
    $wert = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $wert) ?? '';
    return mb_substr(trim($wert), 0, $max);
}

function pruefe_warenkorb($warenkorb): array
{
    if (!is_array($warenkorb) || $warenkorb === []) throw new Kundenfehler('Der Warenkorb ist leer.');
    $positionen = [];
    foreach ($warenkorb as $sku => $menge) {
        if (!isset(PRODUKTE[$sku])) throw new Kundenfehler('Ein Artikel im Warenkorb ist nicht mehr verfügbar. Bitte laden Sie die Seite neu.');
        if (!is_int($menge) || $menge < 1 || $menge > MAX_MENGE) {
            throw new Kundenfehler('Bitte wählen Sie eine Menge zwischen 1 und ' . MAX_MENGE . ' Packungen.');
        }
        $positionen[] = ['sku' => $sku, 'menge' => $menge] + PRODUKTE[$sku];
    }
    return $positionen;
}

// MwSt. auf die Nettosumme – so rechnet auch Lexware auf der Rechnung.
function summen(array $positionen): array
{
    $zeilen = [];
    foreach ($positionen as $p) {
        $zeilen[] = ['name' => $p['name'], 'menge' => $p['menge'], 'einzel' => $p['netto'], 'netto' => $p['netto'] * $p['menge']];
    }
    $zeilen[] = ['name' => VERSAND['name'], 'menge' => 1, 'einzel' => VERSAND['netto'], 'netto' => VERSAND['netto']];
    $netto = array_sum(array_column($zeilen, 'netto'));
    $mwst = (int) round($netto * MWST_PROZENT / 100);
    return ['zeilen' => $zeilen, 'netto' => $netto, 'mwst' => $mwst, 'brutto' => $netto + $mwst];
}

function euro(int $cent): string
{
    return number_format($cent / 100, 2, ',', '.') . ' €';
}

function aufstellung(array $s): string
{
    $zeilen = array_map(fn($z) => "{$z['menge']} × {$z['name']} à " . euro($z['einzel']) . ' = ' . euro($z['netto']), $s['zeilen']);
    return implode("\n", $zeilen) . "\n\n"
        . 'Summe netto: ' . euro($s['netto']) . "\n"
        . 'zzgl. ' . MWST_PROZENT . ' % MwSt.: ' . euro($s['mwst']) . "\n"
        . 'Gesamt brutto: ' . euro($s['brutto']);
}

// ---------- Bestellungen speichern ----------

function bestell_ordner(): string
{
    $ordner = __DIR__ . '/daten/bestellungen';
    if (!is_dir($ordner)) mkdir($ordner, 0750, true);
    return $ordner;
}

function neue_bestellnummer(): string
{
    return 'PL-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function speichere_bestellung(array $b): void
{
    file_put_contents(bestell_ordner() . '/' . $b['nummer'] . '.json', json_encode($b, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function lade_bestellung(string $nummer): ?array
{
    if (!preg_match('/^PL-\d{6}-[0-9A-F]{6}$/', $nummer)) return null;
    $datei = bestell_ordner() . '/' . $nummer . '.json';
    if (!is_file($datei)) return null;
    $b = json_decode((string) file_get_contents($datei), true);
    return is_array($b) ? $b : null;
}

// ---------- E-Mail über das eigene Postfach des Webhosters ----------

function sende_mail(string $an, string $betreff, string $inhalt, string $antwortAn): void
{
    $absender = cfg()['absender'] ?? 'info@pinovalab.eu';
    $headers = [
        'From' => mb_encode_mimeheader('Pinova Lab', 'UTF-8') . ' <' . $absender . '>',
        'Reply-To' => $antwortAn,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
    ];
    if (!mail($an, mb_encode_mimeheader($betreff, 'UTF-8'), $inhalt, $headers, '-f' . $absender)) {
        throw new RuntimeException('E-Mail an ' . $an . ' konnte nicht gesendet werden.');
    }
}

function kundenblock(array $k): string
{
    return implode("\n", [
        $k['labor'], $k['ansprechpartner'], $k['strasse'], $k['plz'] . ' ' . $k['ort'], 'Deutschland',
        '', 'E-Mail: ' . $k['email'], 'Telefon: ' . ($k['telefon'] ?: '–'), 'USt-IdNr.: ' . ($k['ustid'] ?: '–'),
    ]);
}

function zahlart_text(string $zahlart): string
{
    return $zahlart === 'online' ? 'Online-Zahlung über Mollie' : 'Kauf auf Rechnung';
}

// Mail an Pinova Lab + Eingangsbestätigung an den Kunden.
function sende_bestellmails(array $b): void
{
    $cfg = cfg();
    $k = $b['kunde'];
    $bezahlt = $b['zahlart'] === 'online' ? "\nZAHLUNG EINGEGANGEN (Mollie " . ($b['mollie_id'] ?? '') . ") – bitte Rechnung als bezahlt erstellen.\n" : "\nBitte Rechnung erstellen und zusenden.\n";
    sende_mail($cfg['empfaenger'] ?? 'info@pinovalab.eu', "Neue Bestellung {$b['nummer']} – {$k['labor']}", implode("\n", [
        "Bestellung {$b['nummer']} ({$b['zeit']})",
        'Zahlart: ' . zahlart_text($b['zahlart']),
        $bezahlt,
        aufstellung($b['summen']),
        '',
        'Liefer- und Rechnungsadresse:',
        kundenblock($k),
        '',
        'Nachricht:',
        $k['nachricht'] ?: '–',
        '',
        'Der Kunde hat bestätigt, als Unternehmer (§ 14 BGB) zu bestellen, und die AGB akzeptiert.',
    ]), $k['email']);

    $weiter = $b['zahlart'] === 'online'
        ? 'Ihre Zahlung ist eingegangen. Die Rechnung senden wir Ihnen separat per E-Mail.'
        : 'Die Rechnung senden wir Ihnen separat per E-Mail.';
    sende_mail($k['email'], "Ihre Bestellung {$b['nummer']} bei Pinova Lab", implode("\n", [
        "Guten Tag {$k['ansprechpartner']},",
        '',
        "vielen Dank für Ihre Bestellung {$b['nummer']}. $weiter",
        '',
        aufstellung($b['summen']),
        '',
        'Lieferadresse:',
        implode("\n", [$k['labor'], $k['ansprechpartner'], $k['strasse'], $k['plz'] . ' ' . $k['ort']]),
        '',
        'Bei Fragen antworten Sie einfach auf diese E-Mail.',
        '',
        'Pinova Lab · Tobias Löw',
        'Johann-Sebastian-Bach-Str. 4 · 85435 Erding',
        'info@pinovalab.eu',
    ]), $cfg['empfaenger'] ?? 'info@pinovalab.eu');
}

// ---------- Mollie (Amsterdam) ----------

function mollie(string $methode, string $pfad, ?array $daten = null): array
{
    $ch = curl_init('https://api.mollie.com/v2/' . $pfad);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $methode,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . cfg()['mollie_api_key'], 'Content-Type: application/json'],
    ]);
    if ($daten !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($daten, JSON_UNESCAPED_UNICODE));
    $roh = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $fehler = curl_error($ch);
    curl_close($ch);
    $antwort = json_decode((string) $roh, true);
    if ($status < 200 || $status >= 300 || !is_array($antwort)) {
        throw new RuntimeException("Mollie $pfad: HTTP $status " . ($antwort['detail'] ?? $fehler));
    }
    return $antwort;
}

function betrag(int $cent): array
{
    return ['currency' => 'EUR', 'value' => number_format($cent / 100, 2, '.', '')];
}
