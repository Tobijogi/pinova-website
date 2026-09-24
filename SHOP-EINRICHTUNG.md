# Online-Shop einrichten (STRATO + Mollie)

Ziel: Alles bleibt in Europa, Kundendaten gehen an keine weiteren Dienste. Beteiligt sind nur

- **STRATO** (Berlin, Rechenzentren in Deutschland) – Domain pinovalab.eu, Postfach info@pinovalab.eu, Website und Bestell-Skripte (PHP) an einem Ort,
- **Mollie** (Amsterdam) – nur für „Online bezahlen“; Mollie bekommt nur Betrag und Bestellnummer.

## So funktioniert es

| Bestellweg | Ablauf |
|---|---|
| **Kauf auf Rechnung** | `api/bestellung.php` speichert die Bestellung auf dem Webserver und schickt aus dem eigenen Postfach eine Mail an info@pinovalab.eu und eine Eingangsbestätigung an den Kunden. Die Rechnung wird in Lexware geschrieben. |
| **Online bezahlen** | `api/bestellung.php` speichert die Bestellung und leitet zu Mollie weiter. Ist die Zahlung da, meldet Mollie das an `api/mollie-webhook.php` → erst dann gehen beide Mails raus („ZAHLUNG EINGEGANGEN“). Der Kunde landet auf `bestellung/danke/`, die den Status anzeigt. |

Ohne PHP-Server (lokale Vorschau, GitHub Pages) öffnet der Button das E-Mail-Programm mit der fertigen Bestellung.

**Preise ändern:** in `api/lib.php` (verbindlich) **und** `data-price` in `index.html` (Anzeige).

---

## 1. Webhoster: STRATO

Domain **pinovalab.eu** und Postfach **info@pinovalab.eu** sind bei STRATO gebucht. Im STRATO-Kundenlogin prüfen bzw. einstellen:

1. Der Vertrag enthält **Webspace mit PHP** (ein reines Domain-Paket reicht nicht) – sonst auf ein Hosting-Paket upgraden.
2. **PHP-Version 8.0 oder neuer** einstellen (Bereich „Einstellungen → PHP-Version“ bzw. „Hosting“).
3. **SSL-Zertifikat** für pinovalab.eu und www.pinovalab.eu aktivieren.
4. **Auftragsverarbeitungsvertrag (AVV)** im Kundenlogin abschließen.
5. Zugangsdaten für **SFTP bzw. FTP** notieren – darüber werden die Dateien hochgeladen.

## 2. Mollie (erst Testmodus)

1. Konto auf mollie.com anlegen (Unternehmensdaten, Bankkonto). Solange die Prüfung läuft, funktioniert der Testmodus schon.
2. **Entwickler → API-Schlüssel:** den **Test-API-Schlüssel** (`test_…`) kopieren.
3. **Einstellungen → Zahlungsmethoden:** gewünschte Methoden aktivieren (z. B. Kreditkarte, Überweisung, SEPA-Lastschrift, PayPal).

## 3. Website hochladen

1. Alle Dateien des Repositorys per FTP bzw. Dateimanager des Hosters ins Web-Verzeichnis laden (ohne `.git` und `.claude`).
2. `api/config.example.php` **beim Hoster** als `api/config.php` kopieren und ausfüllen (Mollie-Schlüssel, `https://www.pinovalab.eu`, info@pinovalab.eu). Diese Datei **nie** ins Repository oder in einen Chat geben.
3. Prüfen: `https://www.pinovalab.eu/api/status.php` zeigt `"online":true,"rechnung":true,"test":true`.
4. Prüfen, dass das hier **nicht** abrufbar ist (Fehler 403): `https://www.pinovalab.eu/api/config.php` liefert nichts, `https://www.pinovalab.eu/api/daten/` ist gesperrt.

## 4. Testen

- Eine Bestellung **auf Rechnung** aufgeben → Mail an info@pinovalab.eu und Bestätigung an die Kunden-Adresse prüfen (auch Spam-Ordner).
- Eine Bestellung **online** aufgeben → auf der Mollie-Testseite „Bezahlt“ wählen → Danke-Seite zeigt „Zahlung eingegangen“, beide Mails kommen an. Dann „Abgebrochen“ testen.
- Beträge vergleichen: Warenkorb, Mollie, Mails.

## 5. Vor dem Livegang

- [ ] AGB (B2B) im Abschnitt `#agb` von `index.html` veröffentlichen
- [ ] Datenschutzerklärung: Abschnitt „Hosting“ von GitHub Pages auf STRATO umstellen; alles prüfen lassen
- [ ] In `api/config.php` den **Live-Schlüssel** (`live_…`) eintragen
- [ ] Eine echte Bestellung mit kleinem Betrag durchspielen und in Mollie erstatten
- [ ] Löschfristen festlegen: Die Bestellungen liegen in `api/daten/bestellungen/` – nach Übernahme in Lexware und Ablauf der Aufbewahrungsfristen löschen

## Später

- **Nachbarländer:** Länderprüfung in `api/bestellung.php` und im Formular erweitern, Versandkosten je Land, Reverse Charge für EU-Firmenkunden mit USt-IdNr. – vorher mit dem Steuerberater abstimmen. Schweiz = Nicht-EU (Ausfuhr, Zoll).
- **Lexware:** Optional könnte `api/bestellung.php` Rechnungsentwürfe direkt in Lexware Office anlegen.
