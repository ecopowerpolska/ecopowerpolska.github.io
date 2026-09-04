// src/content.config.ts — Content Layer (Astro 6/7). NIE src/content/config.ts (legacy, AG031).
//
// 🔴 `z` importowane z 'astro/zod'. Odczytane z ZAINSTALOWANEGO pakietu (astro 7.3.1,
// node_modules/astro/types/content.d.ts), nie z pamięci ani ze skilla:
//   „`import { z } from 'astro:content'` is deprecated and will be removed in Astro 8.
//    Use `import { z } from 'astro/zod'` instead."
// To samo ostrzeżenie dotyczy 'astro:schema'. Reguła AG043 walidatora nadal odsyła
// do obu przestarzałych form — zgłoszone do kanonu. Sama reguła nie zapala się tutaj
// i zapalać nie powinna: pilnuje importu z GOŁEGO pakietu 'zod', a to co innego.
//
// 🔴 TEN PLIK JEST LUSTREM public/admin/config.yml. Każde pole poniżej ma tam swój
// odpowiednik o TEJ SAMEJ nazwie i zgodnym typie. Rozjazd nie wychodzi w panelu —
// wychodzi dopiero czerwonym buildem w Actions (potwor-cms-strony §D2).

import { defineCollection } from 'astro:content';
import { glob } from 'astro/loaders';
import { z } from 'astro/zod';

const serwisy = defineCollection({
  // loader obowiązkowy — kolekcja bez niego nie istnieje w Astro 6+ (AG032)
  loader: glob({ pattern: '**/*.md', base: './src/data/serwisy' }),
  schema: ({ image }) =>
    z.object({
      // nazwa serwisu widoczna na kafelku
      nazwa: z.string(),
      // adres docelowy — pełny URL z protokołem; przekierowanie po kliknięciu kafelka
      adres: z.string().url(),
      // krótki podpis pod nazwą; pusty jest w porządku
      opis: z.string().max(160).optional(),
      // obrazek kafelka: plik w src/assets/serwisy/, ścieżka względna z pliku wpisu.
      // OPCJONALNY — dopóki Piotr nie poda grafik, kafelek renderuje czytelny
      // placeholder z samą nazwą (zero podrzuconych plików udających grafikę).
      obrazek: image().optional(),
      // tekst alternatywny obrazka — wymagany, gdy obrazek jest (kryterium bramki)
      alt: z.string().optional(),
      // porządek kafelków rosnąco; równe wartości rozstrzyga nazwa
      kolejnosc: z.number().default(100),
      // kafelek zostaje w repo, znika ze strony
      ukryty: z.boolean().default(false),
    })
      .refine((d) => !d.obrazek || (d.alt && d.alt.trim().length > 0), {
        message: 'Kafelek z obrazkiem musi mieć niepusty tekst alternatywny (alt).',
        path: ['alt'],
      }),
});

export const collections = { serwisy };
