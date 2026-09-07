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
 * 🔴 KAŻDE pole sprowadzone do postaci, ktorej schemat na pewno nie odrzuci.
 * Powod jest jeden: blad walidacji zatrzymuje build, a to zamraza publikacje
 * CALEJ strony — bez zadnego komunikatu w panelu (zdarzylo sie 2026-09-07 na polu
 * `alt`). Panel moze zapisac `null` w wyczyszczonym polu opcjonalnym albo wartosc
 * nietekstowa; jedno i drugie musi przejsc, a pilnowac ma formularz.
 */
const naTekst = (v: unknown): string | undefined =>
  typeof v === 'string' ? v : v == null ? undefined : String(v);

/**
 * Grupy kafelków. Kolejność wpisów w pliku = kolejność sekcji na stronie
 * (pola `kolejnosc` nie ma — porządek ustawia się przeciąganiem w panelu, D8).
 * Kafelek wskazuje grupę polem `grupa` niosącym `klucz` stąd.
 *
 * Plik bywa PUSTĄ listą (`{"grupy": []}`) — to stan wyjściowy i poprawny:
 * index.astro renderuje wtedy układ płaski.
 */
const grupy = defineCollection({
  loader: file('src/data/grupy.json', {
    parser: (tekst) => {
      const dane = JSON.parse(tekst);
      const lista = Array.isArray(dane) ? dane : (dane.grupy ?? []);

      return lista.map((wpis: Record<string, unknown>, i: number) => ({
        // Identyfikator wpisu — loader file() wymaga `id` albo `slug` w kazdym elemencie,
        // a panel takiego pola nie zapisuje. Numer pozycji wystarcza: kafelki wskazuja
        // grupe polem `klucz`, nie identyfikatorem wpisu.
        id: String(i),
        klucz: naTekst(wpis.klucz) ?? '',
        nazwa: naTekst(wpis.nazwa) ?? '',
        opis: naTekst(wpis.opis),
      }));
    },
  }),
  schema: z.object({
    // 🔴 ZERO WALIDACJI ZABIJAJACEJ BUILD — ani `.min()`, ani `.regex()` na kluczu.
    // Wzorca `^[a-z0-9-]+$` pilnuje FORMULARZ w public/admin/config.yml, gdzie blad
    // widzi czlowiek. Tu kazdy blad jest niewidoczny i zamraza cala strone (D10).
    klucz: z.string(),
    nazwa: z.string(),
    opis: z.string().optional(),
  }),
});

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
        // Identyfikator wpisu — loader file() wymaga `id` albo `slug` w kazdym elemencie,
        // a panel takiego pola nie zapisuje. Numer pozycji wystarcza: nic w projekcie
        // nie linkuje do wpisu po identyfikatorze.
        id: String(i),
        nazwa: naTekst(wpis.nazwa) ?? '',
        adres: naTekst(wpis.adres) ?? '',
        opis: naTekst(wpis.opis),
        // 🔴 Grupa: pole `grupa`, a gdy go w ogole nie ma — stare `kategoria`.
        // Powod: przegladarka Piotra moze miec w pamieci podrecznej stara config.yml
        // (GitHub Pages podaje ja z max-age=600) i zapisac wpis w starym ukladzie.
        // Ubezpieczenie na te dziesiec minut, nie trwale dwutorowanie.
        grupa: naTekst(wpis.grupa ?? wpis.kategoria),
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
      // klucz grupy z src/data/grupy.json albo pusty tekst (kafelek bez przypisania).
      // Klucz nieistniejacy w grupy.json NIE jest bledem — kafelek idzie do sekcji
      // koncowej (kontrakt §3 regula 3).
      grupa: z.string().optional(),
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

export const collections = { serwisy, grupy };
