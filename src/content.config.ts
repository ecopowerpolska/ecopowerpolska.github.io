// src/content.config.ts — Content Layer (Astro 6/7). NIE src/content/config.ts (legacy, AG031).
//
// 🔴 `z` importowane z 'astro/zod' (astro 7.3.1 — 'astro:content' i 'astro:schema' są
// przestarzałe i znikają w Astro 8). Reguła AG043 walidatora pilnuje importu z GOŁEGO
// pakietu 'zod', co jest czym innym, i tutaj się nie zapala.
//
// 🔴 TEN PLIK JEST LUSTREM public/admin/config.yml. Rozjazd nie wychodzi w panelu —
// wychodzi czerwonym buildem w Actions, już PO zapisaniu wpisu przez klienta.

import { defineCollection } from 'astro:content';
import { file } from 'astro/loaders';
import { z } from 'astro/zod';

/** Katalog obrazków kafelków, licząc od pliku src/data/serwisy.json. */
const KATALOG_OBRAZKOW = '../assets/serwisy/';

/**
 * Serwisy leżą w JEDNYM pliku, bo tylko wtedy panel daje przeciąganie kafelków
 * myszą (widget `list`); przy jednym pliku na serwis Sveltia nie umie sortować
 * ręcznie. Kolejność w pliku = kolejność na stronie, pola `kolejnosc` już nie ma.
 * Szczegóły: docs/DECYZJE-WIZUALNE.md D8.
 */
const serwisy = defineCollection({
  loader: file('src/data/serwisy.json', {
    parser: (tekst) => {
      const dane = JSON.parse(tekst);
      const lista = Array.isArray(dane) ? dane : (dane.serwisy ?? []);

      return lista.map((wpis: Record<string, unknown>, i: number) => ({
        ...wpis,
        // Identyfikator wpisu — loader file() wymaga `id` albo `slug` w każdym elemencie,
        // a panel takiego pola nie zapisuje. Numer pozycji wystarcza: nic w projekcie
        // nie linkuje do wpisu po identyfikatorze.
        id: String(i),
        // 🔴 Ścieżka obrazka SPROWADZONA DO JEDNEJ POSTACI. Panel zapisuje ją względnie
        // i liczba `../` zależy od jego konfiguracji — rozjazd zatrzymywałby build,
        // czyli publikację CAŁEJ strony (zdarzyło się 2026-09-07). Wszystkie obrazki
        // kafelków leżą w jednym katalogu, więc sama nazwa pliku wystarcza.
        obrazek: wpis.obrazek
          ? KATALOG_OBRAZKOW + String(wpis.obrazek).split('/').pop()
          : undefined,
      }));
    },
  }),
  schema: ({ image }) =>
    z.object({
      // nazwa serwisu widoczna na kafelku
      nazwa: z.string(),
      // adres docelowy — pełny URL z protokołem
      adres: z.string().url(),
      // krótki podpis pod nazwą; pusty jest w porządku
      opis: z.string().max(160).optional(),
      // kategoria/branża — wolny tekst; grupowanie włącza się od DWÓCH różnych kategorii
      kategoria: z.string().max(60).optional(),
      // obrazek kafelka: plik w src/assets/serwisy/ (ścieżkę normalizuje parser wyżej)
      obrazek: image().optional(),
      // 🔴 Alt NIE JEST wymagany i wymagany być nie może: panel nie umie wymusić pola
      // warunkowo, więc zapis bez altu zatrzymywał build i strona zostawała na starej
      // wersji. Puste = zdjęcie ozdobne, `alt=""` — poprawnie, bo nazwę serwisu niesie
      // widoczny tekst kafelka.
      alt: z.string().optional(),
      // kafelek zostaje w panelu, znika ze strony
      ukryty: z.boolean().default(false),
    }),
});

// Teksty strony głównej (src/data/strona.json) NIE są kolekcją — importuje je wprost
// index.astro, tak samo jak dane firmy. Kolekcja z loaderem file() rozbiłaby pojedynczy
// obiekt na osobne wpisy po jednym na klucz.

export const collections = { serwisy };
