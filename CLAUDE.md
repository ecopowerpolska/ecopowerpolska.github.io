# CLAUDE.md — kontrakt pracy nad stronami Astro

Ten plik jest wiążący dla każdej sesji w tym repozytorium. Reguły niżej mają pierwszeństwo
przed nawykami wyniesionymi z treningu i przed poradnikami znalezionymi w sieci.

---

## 0. Zasada nadrzędna: weryfikuj, nie wspominaj

Astro zmienia się szybciej, niż utrwala się wiedza modelu. Konkretny dowód: `output: 'hybrid'`
zostało usunięte w Astro 5, ale krąży w tak wielu poradnikach i odpowiedziach modeli, że zespół
Astro dopisał dedykowany komunikat błędu wyłącznie po to, by kierować ludzi **i modele** na
`output: 'static'`. Traktuj to jako miarę własnej omylności w tym obszarze.

Z tego wynikają trzy obowiązki:

1. **Na starcie sesji ustal fakty**, nie zakładaj ich:
   `npx astro info` → wersja Astro, Node, adapter, integracje. Bez tego nie piszesz kodu.
2. **API specyficzne dla wersji sprawdzasz w dokumentacji**, zanim go użyjesz. Jeśli nie masz
   dostępu do sieci — powiedz to wprost i zaproponuj rozwiązanie, którego jesteś pewny.
3. **Nie udajesz pewności.** „Nie wiem, sprawdzam” jest tańsze niż refactor po deployu.

---

## 1. Kontrakt stacku

| Element | Ustalenie | Konsekwencja |
|---|---|---|
| Astro | 6.x lub 7.x | Content Layer API obowiązkowe, `<ClientRouter />` zamiast `<ViewTransitions />` |
| Node | ≥ 22.12 | Zsynchronizuj `engines.node`, `.nvmrc`, CI i runtime hostingu |
| Tailwind | v4 przez `@tailwindcss/vite` | `@astrojs/tailwind` jest legacy (Tailwind 3) — nie instaluj |
| Konfiguracja | wyłącznie ESM (`astro.config.mjs` / `.ts`) | `.cjs` nie jest wspierane od Astro 6 |
| Fonty | wbudowane Fonts API (Astro 6+) | Nie dokładaj `@fontsource*` ani linków do Google Fonts |
| Treść | `src/content.config.ts` + loader | `src/content/config.ts` (legacy) nie istnieje od Astro 6 |

Aktualizację majora Astro robisz **osobnym PR-em** przez `npx @astrojs/upgrade` — nigdy razem
z nowymi funkcjami. Migracja i feature w jednym diffie to gwarantowany chaos przy debugowaniu.

---

## 2. Czego nie robisz nigdy

- `output: 'hybrid'` — usunięte. Domyślnie `static`; dynamiczne trasy oznaczasz `export const prerender = false`.
- `<ViewTransitions />`, `Astro.glob()`, `Astro.site` — usunięte w Astro 6.
- `client:only` bez frameworka (`client:only="react"`).
- Odwołania do `window` / `document` / `localStorage` we frontmatterze `.astro` — ten kod biegnie na serwerze.
- Instalowanie `@astrojs/tailwind` w nowym projekcie.
- Dodawanie zależności „bo tak było w tutorialu”. Każdy nowy pakiet uzasadniasz w raporcie.
- Kopiowanie snippetów z blogów bez sprawdzenia, do której wersji Astro się odnoszą.
- Ogłaszanie ukończenia zadania bez przejścia pełnej pętli weryfikacji z sekcji 8.

---

## 3. Architektura: wyspy i hydratacja

Astro to nie SPA. Domyślny stan strony to czysty HTML bez JavaScriptu; **każdy kilobajt JS jest
wyjątkiem, który trzeba obronić**.

Drabina hydratacji — schodzisz nią zawsze od góry:

1. **Brak dyrektywy** — komponent renderuje się do HTML. To jest domyślny wybór.
2. `client:visible` — hydracja przy wejściu w viewport. Standard dla wszystkiego poniżej folda.
3. `client:idle` — po załadowaniu strony. Dla rzeczy nieistotnych (widżet czatu, banner).
4. `client:media="(min-width: 768px)"` — gdy interakcja dotyczy tylko części urządzeń.
5. `client:load` — **wymaga uzasadnienia w komentarzu** `<!-- guard:allow AG024 powód -->`.
   Dopuszczalne praktycznie tylko dla interaktywnej nawigacji nad foldem.
6. `client:only="framework"` — ostateczność. Oznacza zero HTML-a dla robota i użytkownika
   przed wykonaniem JS. Nigdy dla treści istotnej dla SEO.

Zasady dodatkowe:

- **Hydratujesz najmniejszy możliwy fragment.** Nie cały formularz — sam przycisk, który
  potrzebuje stanu. Nie całą sekcję cenową — sam przełącznik miesiąc/rok.
- **Budżet: maksymalnie 3 wyspy na plik.** Więcej oznacza, że projektujesz aplikację w narzędziu
  do stron treściowych. Zatrzymaj się i zgłoś to człowiekowi.
- **Stan między wyspami** nie przechodzi przez propsy Reacta. Potrzebujesz nanostores albo
  natywnych CustomEvents. Jeśli projekt wymaga tego w wielu miejscach — to sygnał, że Astro
  jest złym narzędziem do tego ekranu. Powiedz to.
- **`server:defer`** (Server Islands) zamiast wysyłania klienta bazy danych do przeglądarki:
  strona zostaje statyczną skorupą cache'owalną na CDN, a fragment zależny od użytkownika
  dociera osobno. To właściwe narzędzie dla koszyka, licznika czy bramki logowania.

---

## 4. Media

- Obrazy rastrowe zawsze przez `<Image />` / `<Picture />` z `astro:assets`. Import z `src/`,
  nie ścieżka z `public/` — inaczej tracisz optymalizację i hashowanie.
- `width` i `height` obowiązkowe. Brak wymiarów to CLS, a CLS to mierzalna strata w Core Web Vitals.
- Dokładnie **jeden** obraz na stronę dostaje `loading="eager"` + `fetchpriority="high"` —
  kandydat na LCP. Reszta zostaje przy domyślnym lazy.
- SVG: inline w kodzie albo zwykły `<img>`. Nie przepuszczaj ich przez pipeline optymalizacji.
- Wideo nigdy z `autoplay` bez `preload="none"` i plakatu. Osadzone YouTube ładujesz fasadą
  (klik → iframe), nie bezpośrednio.

---

## 5. Skrypty i style

- `<script>` w komponencie `.astro` jest domyślnie bundlowany, minifikowany i odraczany —
  to preferowana droga. `is:inline` wyłącza to wszystko: używasz tylko dla kodu, który musi
  wykonać się przed hydracją (np. ustawienie motywu przeciw FOUC), z komentarzem uzasadniającym.
- Style piszesz w `<style>` wewnątrz `.astro`. Są scoped i trafiają wyłącznie tam, gdzie są używane.
  Globalny arkusz w layoucie to jeden import, nie pięć.
- Tailwind 4: `@import "tailwindcss"` w jednym pliku CSS, tokeny w `@theme {}`.
  Pliku `tailwind.config.js` nie ma — konfiguracja jest w CSS.
- Third-party (GA, Pixel, GTM) nie ląduje w `<head>` bez zastanowienia. Kolejność wyborów:
  (1) w ogóle nie ładować przed zgodą cookie, (2) ładować po pierwszej interakcji,
  (3) Partytown w Web Workerze. Przy Partytown zmierz TBT przed i po — bywa, że jego service
  worker generuje własne ostrzeżenia w Lighthouse.

---

## 6. Treść i dane

- Kolekcje w `src/content.config.ts`, zawsze z loaderem: `glob()` dla plików, `file()` dla
  pojedynczego źródła, własny loader dla API/CMS.
- Schemat w Zod jest obowiązkowy — nieopisane frontmatter to błędy, które wychodzą na produkcji.
  Zod importujesz z `astro:content`, nie z pakietu `zod` (Astro 6 dostarcza własną instancję).
- Zmienne środowiskowe przez `astro:env` z jawnym podziałem `server`/`client` i walidacją typu.
  `process.env` w kodzie źródłowym to zapowiedź awarii na Cloudflare/Deno/Bun.

---

## 7. Renderowanie i hosting

- Domyślnie `output: 'static'`. Trasa dynamiczna dostaje `export const prerender = false` —
  punktowo, nie globalnie.
- `output: 'server'` tylko wtedy, gdy przeważająca część serwisu naprawdę jest dynamiczna.
  To decyzja architektoniczna, nie technikalium — konsultujesz ją z człowiekiem.
- `site` w konfiguracji jest wymagane: bez tego nie ma poprawnych kanonicznych URL-i ani sitemapy.
- `prefetch` włączony, strategia `hover` jako domyślna, `data-astro-prefetch="viewport"` na
  kluczowych CTA. Nie na wszystkich linkach naraz — to marnowanie transferu na mobile.
- `<ClientRouter />` dodajesz świadomie: zmienia moment wykonania skryptów. Każdy kod pod
  `astro:page-load` / `astro:after-swap` wymaga testu po włączeniu.
- Nagłówki `Cache-Control` dla `/_astro/*` (hashowane, `immutable`) ustawiasz w konfiguracji
  hostingu lub middleware. Brotli po stronie hostingu — sprawdź, nie zakładaj.

---

## 8. Pętla pracy — obowiązkowa

Każde zadanie przechodzi przez wszystkie fazy. Pominięcie fazy jest błędem procesu.

**Faza 0 — rozpoznanie.** `npx astro info`, odczyt `astro.config.*` i `package.json`.
Ustalasz wersję, adapter, tryb renderowania. Dopiero teraz planujesz.

**Faza 1 — plan.** Zanim napiszesz linijkę: wypisz komponenty i przypisz każdemu poziom
hydratacji z drabiny (sekcja 3). Plan z trzema `client:load` odrzucasz sam, bez czekania na review.

**Faza 2 — implementacja.** Małymi krokami. Hook `PostToolUse` waliduje każdy zapis i zwróci
naruszenia natychmiast — czytaj te komunikaty, nie ignoruj ich.

**Faza 3 — weryfikacja.** W tej kolejności, bez skrótów:

```bash
npm run guard          # statyczny walidator reguł projektu
npx astro check        # typy i diagnostyka Astro
npm run build          # kompilator Rusta jest surowy: niezamknięty tag = błąd, nie ostrzeżenie
npm run guard:build    # audyt dist: waga JS/CSS per strona + SEO
```

Czerwone `guard` = zadanie nieukończone. Nie „w większości działa”. Nieukończone.

**Faza 4 — raport.** Format w sekcji 9.

---

## 9. Definicja ukończenia i format raportu

Zadanie jest ukończone, gdy: `guard` czysty, `astro check` bez błędów, build przechodzi,
audyt `dist` mieści się w budżetach z `guard.config.json`.

Raport końcowy zawiera dokładnie te punkty:

1. **Co zmieniłem** — lista plików z jednozdaniowym uzasadnieniem każdego.
2. **Wyspy** — każda dyrektywa `client:*` dodana w tym zadaniu, z powodem.
3. **Budżet** — JS i CSS (gzip) dla dotkniętych stron, przed i po.
4. **Odstępstwa** — każde `guard:allow`, z uzasadnieniem.
5. **Czego nie zweryfikowałem** — wprost. Np. „nie sprawdzałem zachowania z `<ClientRouter />`
   przy nawigacji wstecz”. Ta sekcja nigdy nie jest pusta bez powodu.

---

## 10. Kiedy przerwać i zapytać człowieka

Zatrzymujesz się i pytasz, gdy:

- zadanie wymaga więcej niż 3 wysp na jednej stronie lub współdzielonego stanu między nimi,
- pojawia się potrzeba `output: 'server'` dla całego serwisu,
- realizowany ekran to w istocie dashboard/panel aplikacyjny — Astro jest wtedy złym wyborem
  i uczciwie to mówisz, zamiast heroicznie obchodzić ograniczenia frameworka,
- migracja majora dotknęłaby więcej niż kilku plików,
- reguła z tego pliku blokuje sensowne rozwiązanie. Wtedy proponujesz zmianę reguły —
  nie obchodzisz jej po cichu przez `guard:ignore`.

Obejście walidatora bez zgody człowieka jest poważniejszym naruszeniem niż błąd, który
walidator wykrył.
