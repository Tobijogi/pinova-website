// Gemeinsame Shop-Logik für die Cloudflare-Pages-Funktionen unter functions/api/.
// Die Preise hier sind verbindlich – der Browser schickt nur Artikelnummer und Menge.
// Bei Preisänderungen auch data-price in index.html anpassen.

export const PRODUKTE = {
  'shera-s01': { name: 'TL² PivotPin SHERA S01', einheit: 'Packung (100 Stk.)', netto: 1989 },
  'shera-s02': { name: 'TL² PivotPin SHERA S02', einheit: 'Packung (100 Stk.)', netto: 1989 },
  'exocad-e01': { name: 'TL² PivotPin exocad E01', einheit: 'Packung (100 Stk.)', netto: 1889 },
};

export const VERSAND = { name: 'Versand innerhalb Deutschlands', netto: 646 };
export const MWST_PROZENT = 19;
const MAX_MENGE = 500;

// Liefert die Positionen oder wirft einen Fehler mit kundentauglicher Meldung.
export function pruefeWarenkorb(warenkorb) {
  if (!warenkorb || typeof warenkorb !== 'object' || Array.isArray(warenkorb)) {
    throw new Kundenfehler('Der Warenkorb ist leer.');
  }
  const positionen = [];
  for (const [sku, menge] of Object.entries(warenkorb)) {
    const produkt = PRODUKTE[sku];
    if (!produkt) throw new Kundenfehler('Ein Artikel im Warenkorb ist nicht mehr verfügbar. Bitte laden Sie die Seite neu.');
    if (!Number.isInteger(menge) || menge < 1 || menge > MAX_MENGE) {
      throw new Kundenfehler(`Bitte wählen Sie eine Menge zwischen 1 und ${MAX_MENGE} Packungen.`);
    }
    positionen.push({ sku, ...produkt, menge });
  }
  if (positionen.length === 0) throw new Kundenfehler('Der Warenkorb ist leer.');
  return positionen;
}

// Rechnet wie Stripe: MwSt. je Position auf den Cent gerundet.
export function summen(positionen) {
  const zeilen = [
    ...positionen.map((p) => ({ name: p.name, menge: p.menge, einzel: p.netto, netto: p.netto * p.menge })),
    { name: VERSAND.name, menge: 1, einzel: VERSAND.netto, netto: VERSAND.netto },
  ];
  let netto = 0;
  let mwst = 0;
  for (const z of zeilen) {
    netto += z.netto;
    mwst += Math.round((z.netto * MWST_PROZENT) / 100);
  }
  return { zeilen, netto, mwst, brutto: netto + mwst };
}

export function euro(cent) {
  return (cent / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

export function text(wert, maxLaenge) {
  if (typeof wert !== 'string') return '';
  return wert.replace(/[\u0000-\u0009\u000b-\u001f\u007f]/g, '').trim().slice(0, maxLaenge);
}

export function istEmail(wert) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(wert) && wert.length <= 200;
}

export class Kundenfehler extends Error {}

export function json(daten, status = 200) {
  return new Response(JSON.stringify(daten), {
    status,
    headers: { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'no-store' },
  });
}

export function fehlerAntwort(err) {
  if (err instanceof Kundenfehler) return json({ fehler: err.message }, 400);
  console.error(err);
  return json({ fehler: 'Die Bestellung konnte gerade nicht verarbeitet werden. Bitte versuchen Sie es später erneut oder schreiben Sie uns eine E-Mail.' }, 500);
}

export async function leseJson(request) {
  try {
    return await request.json();
  } catch {
    throw new Kundenfehler('Ungültige Anfrage.');
  }
}

export function stripeBereit(env) {
  return Boolean(env.STRIPE_SECRET_KEY && env.STRIPE_TAX_RATE_ID);
}

export function rechnungBereit(env) {
  return Boolean(env.RESEND_API_KEY && env.ORDER_EMAIL_TO && env.ORDER_EMAIL_FROM);
}

// Stripe erwartet verschachtelte Parameter als a[b][0][c]=… im Formular-Format.
function formular(obj, prefix = '', out = new URLSearchParams()) {
  for (const [key, wert] of Object.entries(obj)) {
    if (wert === undefined || wert === null) continue;
    const name = prefix ? `${prefix}[${key}]` : key;
    if (typeof wert === 'object') formular(wert, name, out);
    else out.append(name, String(wert));
  }
  return out;
}

export async function stripe(env, methode, pfad, parameter) {
  const url = new URL('https://api.stripe.com/v1/' + pfad);
  const init = {
    method: methode,
    headers: { authorization: 'Bearer ' + env.STRIPE_SECRET_KEY },
  };
  if (parameter && methode === 'GET') url.search = formular(parameter).toString();
  else if (parameter) {
    init.headers['content-type'] = 'application/x-www-form-urlencoded';
    init.body = formular(parameter);
  }
  const antwort = await fetch(url, init);
  const daten = await antwort.json();
  if (!antwort.ok) throw new Error('Stripe ' + pfad + ': ' + (daten.error?.message || antwort.status));
  return daten;
}

export async function sendeMail(env, { an, antwortAn, betreff, inhalt }) {
  const antwort = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: { authorization: 'Bearer ' + env.RESEND_API_KEY, 'content-type': 'application/json' },
    body: JSON.stringify({ from: env.ORDER_EMAIL_FROM, to: [an], reply_to: antwortAn, subject: betreff, text: inhalt }),
  });
  if (!antwort.ok) throw new Error('Resend: ' + antwort.status + ' ' + (await antwort.text()));
}
