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

      // 🔴 KAŻDE pole sprowadzone do postaci, ktorej schemat na pewno nie odrzuci.
      // Powod jest jeden: blad walidacji zatrzymuje build, a to zamraza publikacje
      // CALEJ strony — bez zadnego komunikatu w panelu (zdarzylo sie 2026-09-07 na polu
      // `alt`). Panel moze zapisac `null` w wyczyszczonym polu opcjonalnym albo tekst
      // dluzszy od limitu; jedno i drugie musi przejsc, a pilnowac ma formularz.
      const naTekst = (v: unknown): string | undefined =>
        typeof v === 'string' ? v : v == null ? undefined : String(v);

      return lista.map((wpis: Record<string, unknown>, i: number) => ({
        // Identyfikator wpisu — loader file() wymaga `id` albo `slug` w kazdym elemencie,
        // a panel takiego pola nie zapisuje. Numer pozycji wystarcza: nic w projekcie
        // nie linkuje do wpisu po identyfikatorze.
        id: String(i),
        nazwa: naTekst(wpis.nazwa) ?? '',
        adres: naTekst(wpis.adres) ?? '',
        opis: naTekst(wpis.opis),
        kategoria: naTekst(wpis.kategoria),
        alt: naTekst(wpis.alt),
        ukryty: wpis.ukryty === true || wpis.ukryty === 'true',
        // 🔴 Sciezka obrazka sprowadzona do jednej postaci: panel zapisuje ja wzglednie,
        // a liczba `../` zalezy od jego konfiguracji. Wszystkie obrazki kafelkow leza
        // w jednym katalogu, wiec sama nazwa pliku wystarcza.
        obrazek: wpis.obrazek
          ? KATALOG_OBRAZKOW + String(wpis.obrazek).split('/').pop()
          : undefined,
      }));
    },
  }),
  schema: ({ image }) =>
    z.object({
      // 🔴 ZERO WALIDACJI ZABIJAJACEJ BUILD. Limity dlugosci i postac adresu pilnuje
      // FORMULARZ w public/admin/config.yml — tam blad widzi czlowiek i moze go poprawic.
      // Tu kazdy blad jest niewidoczny i zamraza cala strone (D10).
      nazwa: z.string(),
      adres: z.string(),
      opis: z.string().optional(),
      kategoria: z.string().optional(),
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
