// POST /api/checkout – legt eine Stripe-Checkout-Sitzung an und gibt deren URL zurück.
// Body: { warenkorb: { sku: menge }, labor, email }
import {
  VERSAND, Kundenfehler, pruefeWarenkorb, text, istEmail,
  json, fehlerAntwort, leseJson, stripe, stripeBereit,
} from '../_shop.js';

export async function onRequestPost({ request, env }) {
  try {
    if (!stripeBereit(env)) return json({ fehler: 'Die Online-Zahlung ist noch nicht eingerichtet.' }, 503);

    const daten = await leseJson(request);
    const positionen = pruefeWarenkorb(daten.warenkorb);
    const labor = text(daten.labor, 120);
    const email = text(daten.email, 200);
    if (!labor) throw new Kundenfehler('Bitte geben Sie den Namen Ihres Labors bzw. Ihrer Praxis ein.');
    if (!istEmail(email)) throw new Kundenfehler('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
    if (daten.unternehmer !== true) throw new Kundenfehler('Bitte bestätigen Sie, dass Sie als Unternehmen bestellen.');

    const kunde = await findeOderErstelleKunde(env, labor, email);
    const steuer = [env.STRIPE_TAX_RATE_ID];
    const zeile = (name, beschreibung, netto, menge) => ({
      price_data: {
        currency: 'eur',
        unit_amount: netto,
        tax_behavior: 'exclusive',
        product_data: { name, description: beschreibung },
      },
      quantity: menge,
      tax_rates: steuer,
    });

    const origin = new URL(request.url).origin;
    const kurzliste = positionen.map((p) => `${p.menge}× ${p.sku}`).join(', ');
    const sitzung = await stripe(env, 'POST', 'checkout/sessions', {
      mode: 'payment',
      locale: 'de',
      submit_type: 'pay',
      customer: kunde.id,
      customer_update: { name: 'auto', address: 'auto', shipping: 'auto' },
      billing_address_collection: 'required',
      shipping_address_collection: { allowed_countries: ['DE'] },
      tax_id_collection: { enabled: true },
      line_items: [
        ...positionen.map((p) => zeile(p.name, p.einheit, p.netto, p.menge)),
        zeile(VERSAND.name, undefined, VERSAND.netto, 1),
      ],
      invoice_creation: {
        enabled: true,
        invoice_data: {
          description: 'Bestellung über pinovalab.eu',
          footer: 'Verkauf ausschließlich an Unternehmer im Sinne von § 14 BGB.',
          metadata: { labor },
        },
      },
      custom_text: {
        submit: { message: 'Verkauf ausschließlich an Labore und Praxen (Unternehmer i. S. d. § 14 BGB). Es gelten unsere AGB.' },
      },
      payment_intent_data: { description: `Pinova Lab – ${labor}: ${kurzliste}` },
      metadata: { labor, artikel: kurzliste },
      success_url: origin + '/bestellung/danke/?session_id={CHECKOUT_SESSION_ID}',
      cancel_url: origin + '/#warenkorb',
    });

    return json({ url: sitzung.url });
  } catch (err) {
    return fehlerAntwort(err);
  }
}

// Stammkunden (gleiche E-Mail) nicht bei jeder Bestellung neu anlegen.
async function findeOderErstelleKunde(env, labor, email) {
  const treffer = await stripe(env, 'GET', 'customers', { email, limit: 1 });
  if (treffer.data.length > 0) return treffer.data[0];
  return stripe(env, 'POST', 'customers', {
    name: labor,
    email,
    preferred_locales: ['de'],
    metadata: { quelle: 'pinovalab.eu' },
  });
}
