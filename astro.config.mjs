// astro.config.mjs — ecopowerpolska.pl (agregat serwisów EcoPower)
// ESM, Astro 6/7. Wzorce: skill astro-guard.
//
// 🔴 `site` = DOCELOWA domena z „www" — patrz docs/przelaczenie-domeny.md.
// Adresem kanonicznym jest `https://www.ecopowerpolska.pl`; apex bez „www"
// przekierowuje na niego 301 (robi to GitHub Pages sam, gdy apex wskazuje jego
// cztery adresy A, a `public/CNAME` niesie wariant z „www").
// Z tej jednej linii biorą się: canonical w layoucie, `og:url`, sitemapa
// i pole `url` w danych strukturalnych — nie ma drugiego miejsca do poprawienia.
// `base` NIE WYSTĘPUJE i wystąpić nie ma: repozytorium nazywa się
// `ecopowerpolska.github.io`, więc to witryna użytkownika serwowana z korzenia
// (docs.astro.build/en/guides/deploy/github/, odczyt 2026-09-04). Przy repozytorium
// o innej nazwie trzeba by dopisać `base` — i usunąć je przy podpięciu domeny,
// co jest najczęstszym cichym zepsuciem linków (github-pages-wizytowka §E).

import { defineConfig, fontProviders } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import { satteri } from '@astrojs/markdown-satteri';
import { fileURLToPath } from 'node:url';
import { existsSync, renameSync, readdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';

// Bramka przed publikacją (narzedzia/bramka/bramka-przed-publikacja.py, funkcja
// sprawdz_sitemape) szuka DOSŁOWNIE `dist/sitemap.xml`. @astrojs/sitemap produkuje
// `sitemap-index.xml` + `sitemap-0.xml`. Zmiana nazwy SAMEGO indeksu jest bezpieczna:
// jego wpis wskazuje `sitemap-0.xml` pełnym adresem (https://…/sitemap-0.xml), więc
// nic w treści pliku nie zależy od jego własnej nazwy. Zero duplikatów — to rename,
// nie kopia.
function sitemapJakoPlikSitemapXml() {
  return {
    name: 'sitemap-jako-plik-sitemap-xml',
    hooks: {
      'astro:build:done': ({ dir, logger }) => {
        const katalog = fileURLToPath(dir);
        const cel = join(katalog, 'sitemap.xml');
        const indeks = join(katalog, 'sitemap-index.xml');
        const czesci = readdirSync(katalog)
          .filter((n) => /^sitemap-\d+\.xml$/.test(n))
          .sort();

        if (czesci.length === 1) {
          // Jedna część — `sitemap.xml` ma być SAMĄ LISTĄ ADRESÓW, nie indeksem
          // wskazującym na listę. Indeks jest formalnie poprawny, ale zawiera
          // zero adresów, więc bramka melduje „sitemap: 0 adresów" i nikt nie
          // sprawdza, czy w sitemapie cokolwiek jest.
          renameSync(join(katalog, czesci[0]), cel);
          if (existsSync(indeks)) rmSync(indeks);
        } else if (existsSync(indeks)) {
          // Wiele części (>50 000 adresów) — wtedy indeks jest jedyną poprawną
          // formą i zostaje, tylko pod nazwą `sitemap.xml`. Części zostają obok.
          renameSync(indeks, cel);
          logger.warn(
            `sitemapa ma ${czesci.length} części — sitemap.xml zostaje indeksem, nie listą adresów`,
          );
        }
      },
    },
  };
}

// Zdjęcie w treści wpisu z podpisem (2026-10-26): akapit z SAMYM obrazkiem Markdown z tytułem,
// `![alt](../../assets/serwisy/x.jpg "Podpis")`, staje się <figure> z <figcaption>. Obraz zostaje węzłem
// `image`, więc Astro dalej go optymalizuje (WebP, width/height, loading="lazy"). Astro 7 renderuje Markdown
// procesorem Sätteri (zależność `astro`); zmianę drzewa zapisuje się przez `ctx.replaceNode` (wzorzec: nadihome.pl).
const zdjeciaZPodpisem = {
  name: 'zdjecia-z-podpisem',
  paragraph(wezel, ctx) {
    const dzieci = (wezel.children ?? []).filter((d) => !(d.type === 'text' && String(d.value ?? '').trim() === ''));
    if (dzieci.length !== 1 || dzieci[0].type !== 'image') return;
    const obraz = dzieci[0];
    const podpis = typeof obraz.title === 'string' ? obraz.title.trim() : '';
    if (podpis === '') return;
    ctx.replaceNode(wezel, {
      type: 'zdjecieWpisu',
      data: { hName: 'figure', hProperties: { className: ['wpis-zdjecie'] } },
      children: [
        { type: 'image', url: obraz.url, alt: obraz.alt ?? '' },
        { type: 'podpisZdjecia', data: { hName: 'figcaption' }, children: [{ type: 'text', value: podpis }] },
      ],
    });
  },
};

export default defineConfig({
  site: 'https://www.ecopowerpolska.pl',
  output: 'static',
  integrations: [sitemap({ filter: (adres) => !adres.includes('/przyklady/') && !adres.includes('/dziekujemy/') }), sitemapJakoPlikSitemapXml()],

  // Markdown jest tylko we wpisach bloga; wtyczka przy definicji wyżej.
  markdown: { processor: satteri({ mdastPlugins: [zdjeciaZPodpisem] }) },

  // `prefetch` świadomie WYŁĄCZONY (walidator zgłosi to jako AG004 INFO).
  // Powód: guard.config.json deklaruje całą witrynę jako zero-JS, a prefetch
  // dokłada skrypt kliencki. Strona jest jednostronicowa (plus kotwice #kat-*
  // do sekcji na tej samej stronie) i wszystkie kafelki prowadzą na ZEWNĘTRZNE
  // adresy — prefetch nie miałby czego przyspieszyć.

  // Fonts API (Astro 6+, wbudowane — NIE @fontsource, NIE <link> do Google Fonts,
  // AG013/AG028). Provider `google()` POBIERA pliki czcionek W CZASIE BUDOWANIA
  // i serwuje je z własnej domeny — w wyniku nie ma ani jednego zapytania do
  // fonts.googleapis.com/fonts.gstatic.com z przeglądarki użytkownika (self-hosting,
  // zero wycieku IP). Wymaga dostępu do sieci PRZY BUDOWANIU (sprawdzone `curl` do
  // fonts.googleapis.com i fonts.gstatic.com, 2026-09-06, oba dostępne stąd) — GitHub
  // Actions ma sieć zawsze; na maszynie bez sieci build tego kroku nie wykona.
  //
  // Dwie rodziny, każda innej roli (frontend-design: „typography carries personality"):
  //   — Source Serif 4  → nagłówki: poważny, edytorski krój (Adobe), niesie ciężar
  //     „spółka prowadząca rejestr serwisów", bez efekciarstwa.
  //   — IBM Plex Sans    → tekst, etykiety, nawigacja: techniczny, inżynierski krój
  //     (IBM), niesie skojarzenie z infrastrukturą/„Power" bez dosłownej ikonografii.
  // `latin-ext` OBOWIĄZKOWY — bez niego polskie znaki (ą ć ę ł ń ó ś ź ż) w nagłówkach
  // i tekście spadają cicho na font systemowy w środku zdania.
  fonts: [
    {
      provider: fontProviders.google(),
      name: 'Source Serif 4',
      cssVariable: '--font-display',
      // Zapas szeryfowy (D14, 2026-10-27): Astro liczy metryki zastępczego kroju względem
      // Times New Roman zamiast Ariala — przy Arialu nagłówek strony głównej łamał się
      // w innym miejscu do czasu wczytania fontu (CLS 0,015 w pomiarze cls-cdp z Arialem).
      fallbacks: ['serif'],
      weights: [500, 600],
      styles: ['normal'],
      subsets: ['latin', 'latin-ext'],
    },
    {
      provider: fontProviders.google(),
      name: 'IBM Plex Sans',
      cssVariable: '--font-sans',
      weights: [400, 600],
      styles: ['normal'],
      subsets: ['latin', 'latin-ext'],
    },
  ],
});
