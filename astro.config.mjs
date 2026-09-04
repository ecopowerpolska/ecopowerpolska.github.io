// astro.config.mjs — ecopowerpolska.pl (agregat serwisów EcoPower)
// ESM, Astro 6/7. Wzorce: skill astro-guard.
//
// 🔴 DWA STANY `site` — patrz docs/przelaczenie-domeny.md
//   STAN 1 (teraz, podgląd):  site: 'https://ecopowerpolska.github.io'
//   STAN 2 (po przełączeniu DNS): site: 'https://ecopowerpolska.pl'
// `base` NIE WYSTĘPUJE i wystąpić nie ma: repozytorium nazywa się
// `ecopowerpolska.github.io`, więc to witryna użytkownika serwowana z korzenia
// (docs.astro.build/en/guides/deploy/github/, odczyt 2026-09-04). Przy repozytorium
// o innej nazwie trzeba by dopisać `base` — i usunąć je przy podpięciu domeny,
// co jest najczęstszym cichym zepsuciem linków (github-pages-wizytowka §E).

import { defineConfig } from 'astro/config';
import sitemap from '@astrojs/sitemap';
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

export default defineConfig({
  site: 'https://ecopowerpolska.github.io',
  output: 'static',
  integrations: [sitemap(), sitemapJakoPlikSitemapXml()],

  // `prefetch` świadomie WYŁĄCZONY (walidator zgłosi to jako AG004 INFO).
  // Powód: guard.config.json deklaruje całą witrynę jako zero-JS, a prefetch
  // dokłada skrypt kliencki. Strona jest jednostronicowa i wszystkie kafelki
  // prowadzą na ZEWNĘTRZNE adresy — prefetch nie miałby czego przyspieszyć.
});
