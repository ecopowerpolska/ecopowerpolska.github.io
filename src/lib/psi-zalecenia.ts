// Zalecenia po pomiarze PageSpeed: progi Google (Lighthouse 12/13) + audyty. Czysty TS, bez DOM i importów.
export type Urzadzenie = 'telefon' | 'komputer' | 'oba';
export interface Zalecenie { priorytet: 1 | 2 | 3; tytul: string; opis: string; urzadzenie: Urzadzenie; }
export interface WynikLH { categories?: Record<string, { score: number | null }>; audits?: Record<string, { score?: number | null; numericValue?: number; displayValue?: string; title?: string; details?: { overallSavingsMs?: number; overallSavingsBytes?: number } }>; }

// k klucz, p priorytet, w waga (strata), t tytuł, a skrót, v wartość, o opis, g lista przyczyn kategorii, c znalezione przyczyny
interface Poz { k: string; p: 1 | 2 | 3; w: number; t: string; o: string; a?: string; v?: string; g?: string[]; c?: string[]; }

const sek = (n: number) => (n / 1000).toFixed(1).replace('.', ',') + ' s';
const mls = (n: number) => Math.round(n) + ' ms';
const cls = (n: number) => n.toFixed(2).replace('.', ',');

// audyt, skrót, dobry ≤, słaby >, format, tytuł, opis
const MET: [string, string, number, number, (n: number) => string, string, string][] = [
  ['largest-contentful-paint', 'LCP', 2500, 4000, sek, 'Największy element ładuje się za długo', 'Zmniejsz i skompresuj główne zdjęcie, podaj mu wymiary i ładuj je pierwsze; resztę zdjęć ładuj z opóźnieniem.'],
  ['cumulative-layout-shift', 'CLS', 0.1, 0.25, cls, 'Strona „skacze” podczas ładowania', 'Podaj wymiary zdjęciom i osadzonym elementom, by miały miejsce, zanim się wczytają.'],
  ['total-blocking-time', 'TBT', 200, 600, mls, 'Strona zbyt długo jest zajęta po wczytaniu', 'Strona późno reaguje na dotyk; zwykle winne są skrypty zewnętrzne i reklamy, więc usuń zbędne lub ładuj je później.'],
  ['first-contentful-paint', 'FCP', 1800, 3000, sek, 'Pierwsza treść pojawia się zbyt późno', 'Przyspiesz serwer i usuń z początku strony skrypty oraz style wstrzymujące wyświetlanie.'],
  ['speed-index', 'Speed Index', 3400, 5800, sek, 'Strona zbyt wolno „buduje się”', 'Pokaż najpierw to, co widać bez przewijania; resztę doładuj później.'],
];

// szanse: audyt → [tytuł, opis]
const SZA: Record<string, [string, string]> = {
  'render-blocking-resources': ['Skrypty i style wstrzymują wyświetlenie', 'Odłóż skrypty i style niepotrzebne na starcie (defer, async).'],
  'unused-javascript': ['Strona ładuje nieużywany JavaScript', 'Usuń lub odłóż skrypty niepotrzebne na starcie.'],
  'unused-css-rules': ['Strona ładuje nieużywane style', 'Usuń style, których dana podstrona nie używa.'],
  'modern-image-formats': ['Zdjęcia w przestarzałym formacie', 'Zapisz zdjęcia jako WebP lub AVIF, są dużo lżejsze.'],
  'uses-optimized-images': ['Zdjęcia są za ciężkie', 'Skompresuj zdjęcia bez widocznej straty jakości.'],
  'uses-responsive-images': ['Zdjęcia większe niż ich miejsce', 'Podawaj zdjęcia w rozmiarze wyświetlania (srcset).'],
  'offscreen-images': ['Zdjęcia spoza ekranu ładują się od razu', 'Dodaj im loading="lazy".'],
  'server-response-time': ['Serwer odpowiada zbyt wolno', 'Włącz pamięć podręczną lub CDN albo zmień hosting.'],
  'uses-text-compression': ['Teksty nie są kompresowane', 'Włącz na serwerze kompresję gzip lub Brotli.'],
  'uses-long-cache-ttl': ['Pliki są zbyt krótko zapamiętywane', 'Ustaw długi czas cache dla zdjęć, stylów i skryptów.'],
  'font-display': ['Tekst znika do czasu wczytania czcionki', 'Dodaj czcionkom font-display: swap.'],
  'third-party-summary': ['Skrypty zewnętrzne spowalniają stronę', 'Usuń zbędne reklamy, widżety i śledzenie albo ładuj je później.'],
  'unsized-images': ['Zdjęcia bez podanych wymiarów', 'Dodaj im szerokość i wysokość, by strona nie przeskakiwała.'],
  'bootup-time': ['Skrypty wykonują się zbyt długo', 'Skróć lub rozbij długie skrypty.'],
};

// kategorie: id, nazwa, audyty w kolejności ważności
const KAT: [string, string, string[]][] = [
  ['accessibility', 'Dostępność strony', ['color-contrast', 'image-alt', 'label', 'link-name', 'button-name', 'heading-order', 'html-has-lang']],
  ['best-practices', 'Poprawność techniczna strony', ['is-on-https', 'errors-in-console', 'csp-xss', 'deprecations', 'image-aspect-ratio']],
  ['seo', 'Widoczność w wyszukiwarce', ['is-crawlable', 'document-title', 'meta-description', 'canonical', 'robots-txt', 'hreflang']],
];
const PRZ: Record<string, string> = {
  'color-contrast': 'za mały kontrast tekstu', 'image-alt': 'zdjęcia bez opisu (alt)', 'label': 'pola formularza bez podpisów',
  'link-name': 'linki bez nazwy', 'button-name': 'guziki bez nazwy', 'heading-order': 'zła kolejność nagłówków', 'html-has-lang': 'brak języka strony',
  'is-on-https': 'zasoby bez HTTPS', 'errors-in-console': 'błędy w konsoli', 'csp-xss': 'brak nagłówka CSP',
  'deprecations': 'przestarzałe funkcje', 'image-aspect-ratio': 'zniekształcone zdjęcia',
  'is-crawlable': 'blokada indeksowania', 'document-title': 'brak tytułu strony', 'meta-description': 'brak opisu strony',
  'canonical': 'błędny adres kanoniczny', 'robots-txt': 'błędny robots.txt', 'hreflang': 'błędne wersje językowe',
};

function pkt(l: WynikLH, id: string): number | null {
  const x = l.categories?.[id]?.score;
  return x == null ? null : Math.round(x <= 1 ? x * 100 : x);
}

function zbierz(l: WynikLH | null): Poz[] {
  const r: Poz[] = [];
  if (!l) return r;
  const au = l.audits || {};
  const wyd = pkt(l, 'performance');
  if (wyd !== null && wyd < 50) r.push({ k: 'wyd', p: 1, w: 99, t: 'Strona ładuje się bardzo wolno', a: 'wydajność', v: wyd + '/100', o: 'Zacznij od punktów poniżej; zwykle liczą się zdjęcia i skrypty zewnętrzne.' });
  for (const [id, a, dobry, slaby, f, t, o] of MET) {
    const n = au[id]?.numericValue;
    if (typeof n !== 'number' || n <= dobry) continue;
    r.push({ k: id, p: n > slaby ? 1 : 2, w: n / slaby, t, a, v: f(n), o });
  }
  for (const id in SZA) {
    const x = au[id];
    if (!x) continue;
    const d = x.details || {}, ms = d.overallSavingsMs, b = d.overallSavingsBytes;
    let w = Math.max((ms || 0) / 300, (b || 0) / 1e5);
    if (ms === undefined && b === undefined) w = x.score != null && x.score < 0.5 ? 1 : 0;
    if (w >= 1) r.push({ k: id, p: 3, w, t: SZA[id][0], o: SZA[id][1] });
  }
  for (const [id, nazwa, lista] of KAT) {
    const s = pkt(l, id);
    if (s === null || s >= 90) continue;
    r.push({ k: id, p: 3, w: (90 - s) / 5 + 1, t: nazwa + ' wymaga poprawy', v: s + '/100', o: '', g: lista, c: lista.filter((i) => au[i]?.score != null && (au[i].score as number) < 1) });
  }
  return r;
}

function scal(m?: Poz, d?: Poz): Zalecenie {
  const x = (m && d ? (d.w > m.w ? d : m) : (m || d)) as Poz;
  const u: Urzadzenie = m && d ? 'oba' : m ? 'telefon' : 'komputer';
  let tytul = x.t;
  if (m && d && m.p !== d.p && m.a) tytul += ' (' + m.a + ': telefon ' + m.v + ', komputer ' + d.v + ')';
  else if (x.v) tytul += ' (' + (x.a ? x.a + ' ' : '') + x.v + ')';
  let opis = x.o;
  if (x.g) {
    const zb = [...(m?.c || []), ...(d?.c || [])];
    const l = x.g.filter((i) => zb.includes(i)).slice(0, 3).map((i) => PRZ[i]);
    opis = l.length ? 'Popraw m.in.: ' + l.join(', ') + '.' : 'Sprawdź pełny raport PageSpeed.';
  }
  return { priorytet: Math.min(m?.p || 3, d?.p || 3) as 1 | 2 | 3, tytul, opis, urzadzenie: u };
}

export function zalecenia(mobile: WynikLH | null, desktop: WynikLH | null): Zalecenie[] {
  const W = new Map<string, [Poz?, Poz?]>();
  for (const x of zbierz(mobile)) W.set(x.k, [x]);
  for (const x of zbierz(desktop)) W.set(x.k, [W.get(x.k)?.[0], x]);
  return [...W.values()]
    .map(([m, d]) => ({ z: scal(m, d), w: Math.max(m?.w || 0, d?.w || 0) }))
    .sort((a, b) => a.z.priorytet - b.z.priorytet || b.w - a.w)
    .slice(0, 6)
    .map((e) => e.z);
}

export function zaleceniaTekst(lista: Zalecenie[]): string {
  return lista.map((z) => '- ' + z.tytul + ': ' + z.opis + ' (' + (z.urzadzenie === 'oba' ? 'oba urządzenia' : z.urzadzenie) + ')').join('\n');
}
