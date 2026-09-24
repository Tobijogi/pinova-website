# Online-Shop einrichten (Stripe + Kauf auf Rechnung)

Der Warenkorb auf `index.html` kennt zwei Bestellwege:

| Weg | Was passiert | Code |
|---|---|---|
| **Online bezahlen** | Weiterleitung zur Bezahlseite von Stripe (Karte, SEPA-Lastschrift, Apple/Google Pay). Stripe erstellt die Rechnung und schickt sie dem Kunden. Danach landet der Kunde auf `bestellung/danke/`. | `functions/api/checkout.js` |
| **Kauf auf Rechnung** | Die Bestellung kommt per E-Mail bei Pinova Lab an; die Rechnung wird wie gewohnt selbst geschrieben. | `functions/api/bestellung.js` |

Solange die Server-Funktionen nicht laufen (lokale Vorschau, GitHub Pages), öffnet der Button stattdessen das E-Mail-Programm mit der fertigen Bestellung. Die Website bleibt also immer bestellbar.

**Preise ändern:** in `functions/_shop.js` (verbindlich) **und** `data-price` in `index.html` (Anzeige).

---

## 1. Stripe (zuerst nur Testmodus)

1. Konto auf stripe.com anlegen. Oben rechts bleibt der Schalter auf **Testmodus**.
2. **Entwickler → API-Schlüssel:** den *Geheimschlüssel* kopieren (`sk_test_…`).
3. **Produktkatalog → Steuersätze → Neu:** Name „USt.“, 19 %, Region Deutschland, **exklusiv** (Preise sind netto). Die ID kopieren (`txr_…`).
4. **Einstellungen → Unternehmen / Rechnungen:** Firmenname, Adresse, USt-IdNr. DE288904633 und Rechnungsnummern-Präfix eintragen – das steht dann auf jeder Rechnung.
5. **Einstellungen → Zahlungsmethoden:** Karte, SEPA-Lastschrift, Apple Pay, Google Pay aktivieren.
6. **Einstellungen → Kunden-E-Mails:** „Erfolgreiche Zahlungen“ und Rechnungs-E-Mails einschalten.
7. **Profil → Benachrichtigungen:** E-Mail an dich bei jeder erfolgreichen Zahlung – so erfährst du von neuen Bestellungen.

## 2. Resend (E-Mails für „Kauf auf Rechnung“)

1. Konto auf resend.com anlegen (kostenloser Tarif reicht), Region **EU** wählen.
2. **API Keys → Create:** Schlüssel kopieren (`re_…`).
3. **Zum Testen** ohne eigene Domain: Absender `onboarding@resend.dev`, Empfänger = die E-Mail-Adresse, mit der das Resend-Konto angelegt wurde.
4. **Später**, wenn pinovalab.eu läuft: Domain in Resend verifizieren, Absender `shop@pinovalab.eu`, und `CUSTOMER_CONFIRMATION=on` setzen – dann bekommt auch der Kunde eine Eingangsbestätigung.

## 3. Cloudflare Pages (Hosting mit Server-Funktionen)

1. Konto auf cloudflare.com anlegen → **Workers & Pages → Erstellen → Pages → Mit Git verbinden** → Repository `Tobijogi/pinova-website`, Branch `main`.
2. Build-Einstellungen: Framework **Keines**, Build-Befehl **leer**, Ausgabeverzeichnis **`/`**.
3. **Einstellungen → Variablen und Geheimnisse** (jeweils als *Geheimnis* anlegen):

| Name | Wert |
|---|---|
| `STRIPE_SECRET_KEY` | `sk_test_…` |
| `STRIPE_TAX_RATE_ID` | `txr_…` |
| `RESEND_API_KEY` | `re_…` |
| `ORDER_EMAIL_TO` | Empfänger der Bestellungen, z. B. info@pinovalab.eu |
| `ORDER_EMAIL_FROM` | `Pinova Lab <onboarding@resend.dev>` (später `shop@pinovalab.eu`) |
| `CUSTOMER_CONFIRMATION` | leer lassen; `on`, sobald die Domain bei Resend verifiziert ist |

4. Neu bereitstellen. Die Adresse `…pages.dev` zeigt die Seite; `…pages.dev/api/status` muss `"online":true` zeigen.
5. Domain `pinovalab.eu` unter **Benutzerdefinierte Domains** anbinden.

## 4. Testen

- Testkarte **4242 4242 4242 4242**, beliebiges Ablaufdatum in der Zukunft, beliebige Prüfziffer.
- SEPA-Test-IBAN: **DE89 3704 0044 0532 0130 00**.
- In Stripe unter **Zahlungen** und **Rechnungen** prüfen, ob Beträge, MwSt. und Rechnungsdaten stimmen.

## 5. Vor dem Livegang

- [ ] AGB (B2B) im Abschnitt `#agb` von `index.html` veröffentlichen
- [ ] Datenschutzerklärung: Abschnitt „Hosting“ von GitHub Pages auf Cloudflare umstellen; Texte zu Stripe/Resend prüfen lassen
- [ ] Mit Resend einen Auftragsverarbeitungsvertrag (DPA) abschließen
- [ ] In Stripe auf **Live** umschalten: dort den Steuersatz **neu anlegen** (Test- und Live-IDs sind getrennt), `sk_live_…` und neue `txr_…` in Cloudflare eintragen
- [ ] Eine echte Bestellung mit kleinem Betrag durchspielen und erstatten

## Später: Nachbarländer

In `functions/api/checkout.js` weitere Länder bei `allowed_countries` ergänzen (z. B. `'AT'`). Für EU-Firmenkunden mit gültiger USt-IdNr. gilt dann Reverse Charge (Rechnung ohne deutsche MwSt.) – das braucht eine Anpassung der Steuerlogik und sollte vorher mit dem Steuerberater abgestimmt werden. Die Schweiz ist Nicht-EU (Ausfuhr, Zoll).
