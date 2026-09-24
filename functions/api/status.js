// GET /api/status – sagt der Website, welche Bestellwege gerade funktionieren.
import { json, stripeBereit, rechnungBereit } from '../_shop.js';

export function onRequestGet({ env }) {
  return json({
    online: stripeBereit(env),
    rechnung: rechnungBereit(env),
    test: !String(env.STRIPE_SECRET_KEY || '').startsWith('sk_live_'),
  });
}
