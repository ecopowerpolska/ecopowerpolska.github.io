// Test: node --test src/lib/psi-zalecenia.test.mjs  (Node >= 22.18/24: typy .ts wycina sam)
import test from 'node:test';
import assert from 'node:assert/strict';
import { zalecenia, zaleceniaTekst } from './psi-zalecenia.ts';

// pomocnik: wynik Lighthouse z podanych metryk (ms/CLS), ocen kategorii (0..1) i dodatków
const lh = (m = {}, kat = {}, audity = {}) => ({
  categories: Object.fromEntries(Object.entries({ performance: 1, accessibility: 1, 'best-practices': 1, seo: 1, ...kat }).map(([k, v]) => [k, { score: v }])),
  audits: {
    'largest-contentful-paint': { numericValue: m.lcp ?? 900 },
    'cumulative-layout-shift': { numericValue: m.cls ?? 0 },
    'total-blocking-time': { numericValue: m.tbt ?? 0 },
    'first-contentful-paint': { numericValue: m.fcp ?? 900 },
    'speed-index': { numericValue: m.si ?? 2000 },
    ...audity,
  },
});

test('a) prawdziwa próbka PSI nadihome.pl 2026-10-29 (pola z odpowiedzi API) -> brak zaleceń', () => {
  const mobile = {
    categories: { performance: { score: 1 }, accessibility: { score: 1 }, 'best-practices': { score: 1 }, seo: { score: 1 } },
    audits: {
      'largest-contentful-paint': { score: 1, numericValue: 938, displayValue: '0,9 s' },
      'cumulative-layout-shift': { score: 1, numericValue: 0 },
      'total-blocking-time': { score: 1, numericValue: 0 },
      'first-contentful-paint': { score: 1, numericValue: 938 },
      'speed-index': { score: 0.99, numericValue: 2186.3367425698516, displayValue: '2,2 s' },
      'server-response-time': { score: 1, numericValue: 3, details: { overallSavingsMs: 0 } },
      'unused-javascript': { score: 1, numericValue: 0, details: { overallSavingsMs: 0, overallSavingsBytes: 0 } },
    },
  };
  const desktop = {
    categories: mobile.categories,
    audits: { ...mobile.audits, 'largest-contentful-paint': { score: 1, numericValue: 321 }, 'first-contentful-paint': { score: 1, numericValue: 261 }, 'speed-index': { score: 1, numericValue: 261 } },
  };
  assert.deepEqual(zalecenia(mobile, desktop), []);
});

test('b) wszystko zielone -> pusta lista; null/null też', () => {
  assert.deepEqual(zalecenia(lh(), lh()), []);
  assert.deepEqual(zalecenia(null, null), []);
  assert.deepEqual(zalecenia({}, {}), []);
});

test('c) sam mobile, desktop = null', () => {
  const z = zalecenia(lh({ lcp: 4800 }), null);
  assert.equal(z.length, 1);
  assert.equal(z[0].urzadzenie, 'telefon');
  assert.equal(z[0].priorytet, 1);
  assert.equal(z[0].tytul, 'Największy element ładuje się za długo (LCP 4,8 s)');
  assert.match(z[0].opis, /główne zdjęcie/);
  const k = zalecenia(null, lh({ cls: 0.15 }));
  assert.equal(k[0].urzadzenie, 'komputer');
  assert.equal(k[0].priorytet, 2);
  assert.match(k[0].tytul, /CLS 0,15\)$/);
});

test('d) LCP słaby na obu -> jedno zalecenie "oba", priorytet 1, wyższa wartość w tytule', () => {
  const z = zalecenia(lh({ lcp: 4800 }), lh({ lcp: 4200 }));
  assert.equal(z.length, 1);
  assert.deepEqual([z[0].urzadzenie, z[0].priorytet], ['oba', 1]);
  assert.equal(z[0].tytul, 'Największy element ładuje się za długo (LCP 4,8 s)');
});

test('d2) różne strefy -> w tytule obie wartości, priorytet wyższy', () => {
  const z = zalecenia(lh({ lcp: 4800 }), lh({ lcp: 3100 }));
  assert.equal(z.length, 1);
  assert.equal(z[0].priorytet, 1);
  assert.equal(z[0].tytul, 'Największy element ładuje się za długo (LCP: telefon 4,8 s, komputer 3,1 s)');
});

test('progi: dokładnie na granicy dobrego = bez zalecenia; TBT, FCP, Speed Index; wydajność < 50', () => {
  assert.deepEqual(zalecenia(lh({ lcp: 2500, cls: 0.1, tbt: 200, fcp: 1800, si: 3400 }), null), []);
  const z = zalecenia(lh({ tbt: 700, fcp: 3500, si: 6000 }, { performance: 0.38 }), null);
  assert.equal(z[0].tytul, 'Strona ładuje się bardzo wolno (wydajność 38/100)');
  assert.ok(z.slice(0, 4).every((x) => x.priorytet === 1));
  assert.ok(z.some((x) => x.tytul === 'Strona zbyt długo jest zajęta po wczytaniu (TBT 700 ms)' && /skrypty zewnętrzne i reklamy/.test(x.opis)));
  assert.ok(z.some((x) => /Speed Index 6,0 s/.test(x.tytul)));
});

test('kategorie < 90: 2-3 przyczyny z audytów score < 1; szanse wg progów; nieznany audyt pominięty', () => {
  const a = {
    'color-contrast': { score: 0 }, 'image-alt': { score: 0 }, label: { score: 0 }, 'link-name': { score: 0 }, 'document-title': { score: 1 },
    'unused-javascript': { score: 0.2, details: { overallSavingsMs: 450, overallSavingsBytes: 50000 } },
    'modern-image-formats': { score: 0.5, details: { overallSavingsMs: 100, overallSavingsBytes: 150000 } },
    'uses-text-compression': { score: 0.5, details: { overallSavingsMs: 100, overallSavingsBytes: 20000 } },
    'nieznany-audyt': { score: 0, details: { overallSavingsMs: 5000 } },
  };
  const z = zalecenia(lh({}, { accessibility: 0.82 }, a), null);
  const dost = z.find((x) => x.tytul.startsWith('Dostępność strony'));
  assert.equal(dost.tytul, 'Dostępność strony wymaga poprawy (82/100)');
  assert.equal(dost.opis, 'Popraw m.in.: za mały kontrast tekstu, zdjęcia bez opisu (alt), pola formularza bez podpisów.');
  assert.equal(dost.priorytet, 3);
  assert.ok(z.some((x) => x.tytul === 'Strona ładuje nieużywany JavaScript'));
  assert.ok(z.some((x) => x.tytul === 'Zdjęcia w przestarzałym formacie'));
  assert.ok(!z.some((x) => /Teksty nie są kompresowane/.test(x.tytul)));
  assert.equal(z.length, 3);
});

test('e) duplikaty i limit 6', () => {
  const a = Object.fromEntries(['render-blocking-resources', 'unused-javascript', 'unused-css-rules', 'modern-image-formats', 'uses-optimized-images', 'offscreen-images'].map((k) => [k, { score: 0, details: { overallSavingsMs: 1000 } }]));
  const w = lh({ lcp: 5000, cls: 0.3, tbt: 800 }, { performance: 0.3, seo: 0.7 }, a);
  const z = zalecenia(w, w);
  assert.equal(z.length, 6);
  assert.ok(z.every((x) => x.urzadzenie === 'oba'));
  assert.equal(new Set(z.map((x) => x.tytul)).size, 6);
  assert.deepEqual(z.map((x) => x.priorytet), [...z.map((x) => x.priorytet)].sort());
  assert.equal(z[0].priorytet, 1);
});

test('sortowanie: ten sam priorytet -> większa strata pierwsza', () => {
  const z = zalecenia(lh({ lcp: 4100, tbt: 2000 }), null);
  assert.match(z[0].tytul, /^Strona zbyt długo/);
  assert.match(z[1].tytul, /^Największy element/);
});

test('f) zaleceniaTekst: format wierszy do schowka', () => {
  const z = zalecenia(lh({ lcp: 4800 }), lh({ lcp: 4200, cls: 0.12 }));
  const t = zaleceniaTekst(z);
  const w = t.split('\n');
  assert.equal(w.length, 2);
  assert.match(w[0], /^- Największy element ładuje się za długo \(LCP 4,8 s\): .+\. \(oba urządzenia\)$/);
  assert.match(w[1], /^- Strona „skacze” podczas ładowania \(CLS 0,12\): .+\. \(komputer\)$/);
  assert.equal(zaleceniaTekst([]), '');
});
