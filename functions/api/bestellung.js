// POST /api/bestellung – Kauf auf Rechnung: schickt die Bestellung per E-Mail an Pinova Lab
// (und, sobald die Absender-Domain bei Resend bestätigt ist, eine Eingangsbestätigung an den Kunden).
import {
  Kundenfehler, pruefeWarenkorb, summen, euro, text, istEmail,
  json, fehlerAntwort, leseJson, rechnungBereit, sendeMail, MWST_PROZENT,
} from '../_shop.js';

export async function onRequestPost({ request, env }) {
  try {
    if (!rechnungBereit(env)) return json({ fehler: 'Die Bestellung auf Rechnung ist noch nicht eingerichtet.' }, 503);

    const daten = await leseJson(request);
    // Honeypot: echte Besucher sehen dieses Feld nicht.
    if (text(daten.website, 200)) return json({ ok: true });

    const positionen = pruefeWarenkorb(daten.warenkorb);
    const k = {
      labor: text(daten.labor, 120),
      ansprechpartner: text(daten.ansprechpartner, 120),
      email: text(daten.email, 200),
      telefon: text(daten.telefon, 40),
      strasse: text(daten.strasse, 120),
      plz: text(daten.plz, 10),
      ort: text(daten.ort, 80),
      ustid: text(daten.ustid, 20).toUpperCase().replace(/\s/g, ''),
      nachricht: text(daten.nachricht, 2000),
    };
    if (!k.labor) throw new Kundenfehler('Bitte geben Sie den Namen Ihres Labors bzw. Ihrer Praxis ein.');
    if (!k.ansprechpartner) throw new Kundenfehler('Bitte geben Sie eine Ansprechperson an.');
    if (!istEmail(k.email)) throw new Kundenfehler('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
    if (!k.strasse || !k.ort) throw new Kundenfehler('Bitte geben Sie die vollständige Lieferadresse an.');
    if (!/^\d{5}$/.test(k.plz)) throw new Kundenfehler('Bitte geben Sie eine gültige deutsche Postleitzahl ein.');
    if (daten.unternehmer !== true) throw new Kundenfehler('Bitte bestätigen Sie, dass Sie als Unternehmen bestellen.');

    const s = summen(positionen);
    const nummer = bestellnummer();
    const aufstellung = [
      ...s.zeilen.map((z) => `${z.menge} × ${z.name} à ${euro(z.einzel)} = ${euro(z.netto)}`),
      '',
      `Summe netto: ${euro(s.netto)}`,
      `zzgl. ${MWST_PROZENT} % MwSt.: ${euro(s.mwst)}`,
      `Gesamt brutto: ${euro(s.brutto)}`,
    ].join('\n');
    const adresse = [k.labor, k.ansprechpartner, k.strasse, `${k.plz} ${k.ort}`, 'Deutschland'].join('\n');

    await sendeMail(env, {
      an: env.ORDER_EMAIL_TO,
      antwortAn: k.email,
      betreff: `Neue Bestellung auf Rechnung ${nummer} – ${k.labor}`,
      inhalt: [
        `Bestellung ${nummer} (Kauf auf Rechnung)`,
        '',
        aufstellung,
        '',
        'Liefer- und Rechnungsadresse:',
        adresse,
        '',
        `E-Mail: ${k.email}`,
        `Telefon: ${k.telefon || '–'}`,
        `USt-IdNr.: ${k.ustid || '–'}`,
        '',
        'Nachricht:',
        k.nachricht || '–',
        '',
        'Der Kunde hat bestätigt, als Unternehmer (§ 14 BGB) zu bestellen, und die AGB akzeptiert.',
      ].join('\n'),
    });

    if (env.CUSTOMER_CONFIRMATION === 'on') {
      await sendeMail(env, {
        an: k.email,
        antwortAn: env.ORDER_EMAIL_TO,
        betreff: `Ihre Bestellung ${nummer} bei Pinova Lab`,
        inhalt: [
          `Guten Tag ${k.ansprechpartner},`,
          '',
          `vielen Dank für Ihre Bestellung. Wir haben sie erhalten und melden uns mit der Rechnung und den Versandinformationen.`,
          '',
          aufstellung,
          '',
          'Lieferadresse:',
          adresse,
          '',
          'Bei Fragen antworten Sie einfach auf diese E-Mail.',
          '',
          'Pinova Lab · Tobias Löw',
        ].join('\n'),
      });
    }

    return json({ ok: true, nummer });
  } catch (err) {
    return fehlerAntwort(err);
  }
}

// Kurz, eindeutig genug für ein kleines Labor-Geschäft und ohne Datenbank: PL-JJMMTT-XXXX
function bestellnummer() {
  const d = new Date();
  const datum = d.toISOString().slice(2, 10).replace(/-/g, '');
  const zufall = crypto.getRandomValues(new Uint16Array(1))[0].toString(36).toUpperCase().padStart(4, '0');
  return `PL-${datum}-${zufall}`;
}
