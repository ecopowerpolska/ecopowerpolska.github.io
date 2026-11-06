# Żywe elementy Ż1–Ż6 — co, gdzie, jak działa, co zapisuje (od 2026-11-06)

Sześć elementów, które pokazują, że strona „żyje”: liczby z dnia, z przeglądarki odwiedzającego i z naszego zaplecza.
Zasady wspólne: zero skryptów z cudzych CDN, zero bibliotek poza `web-vitals` (plik lokalny), każdy nasz skrypt ≤ 2 kB
(surowy 0,8–1,6 kB, gzip 0,5–0,9 kB), `defer`; bez JS każdy element ma sensowną treść statyczną; wysokość zarezerwowana (CLS 0).
Każdy komponent wstawia się NAJWYŻEJ RAZ na stronę (skrypty szukają pierwszego elementu `data-…`).

| | Komponent | Skrypt / zaplecze | Dane | Bez JS / przy błędzie |
|---|---|---|---|---|
| Ż1 | `src/components/zywe/ZegarStrony.astro` | `public/zywe/zegar.js` + `public/zywe/web-vitals.iife.js` | przeglądarka odwiedzającego | zdanie z kreskami „–” |
| Ż2 | `src/components/zywe/WykresPsi.astro` | `scripts/psi-realizacje.py` (cron Z440 05:30) | `src/data/pomiary.json` | statyczny (build) |
| Ż3 | `src/components/zywe/OstatniaPublikacja.astro` | `public/zywe/publikacja.js` | API GitHuba (publiczne) | data builda |
| Ż4 | `src/components/zywe/LicznikNarzedzi.astro` | `public/zywe/licznik.js` + `formularz/licznik.php` | `stan/licznik-*.json` na lh.pl | „Darmowe i bez logowania.” |
| Ż5 | `src/components/zywe/CzytaTeraz.astro` | `public/zywe/obecni.js` + `formularz/obecni.php` | `stan/obecni.json` na lh.pl | zdanie o sposobie liczenia |
| Ż6 | `src/components/zywe/PasRynku.astro` | — (zero JS) | `src/data/rynek.json` | statyczny |

## Ż1 — zegar Core Web Vitals tej strony
Pasek nad stopką: „Ta strona otworzyła się u Ciebie w X s (LCP), przesunięcie układu Y (CLS), reakcja Z ms (INP)”.
`zegar.js` po zdarzeniu `load` i w czasie bezczynności (`requestIdleCallback`) dokłada `/zywe/web-vitals.iife.js` i woła
`onLCP`, `onCLS`, `onINP` z `reportAllChanges` (wartości dopisują się na bieżąco; INP pojawia się po pierwszym kliknięciu
lub klawiszu). Biblioteka czyta wpisy buforowane przez przeglądarkę, więc późne załadowanie nie gubi LCP ani CLS.
**Nic nie wysyła, nic nie zapisuje** (ani ciasteczek, ani Web Storage) — dlatego nie podlega oknu zgody; nie ma tu danych osobowych.
Bez animacji (także bez `prefers-reduced-motion`). Słaby wynik (`rating: poor`) zmienia tylko kolor liczby, liczba zostaje.

**web-vitals:** wersja **5.3.0**, plik `dist/web-vitals.iife.js` pobrany JEDEN raz 2026-11-06 z
`https://unpkg.com/web-vitals@5.3.0/dist/web-vitals.iife.js` (5 888 B, gzip 2 430 B,
sha256 `bc7a1a0cc6e0eab320c0eae3064eaa4c1b4da607e8f1d896c6a59cafbeccd257`). Pakietu nie ma w `node_modules` ani w `package.json`.
Ładowany leniwie, więc nie liczy się do budżetu JS trasy w `audit-dist.mjs` (liczy tylko `<script src>` z HTML).
Aktualizacja = podmiana pliku i wiersza wyżej.

## Ż2 — wykres PSI realizacji
`WykresPsi.astro`: nagłówek „Wynik na telefonie: nasze realizacje”, średnia (`.liczba`), słupek CSS na realizację
(`style="--w:98%"`, wydajność mobile), „komputer N”, mini-wykres ostatnich 7 pomiarów (gdy są ≥ 2; skala 80–100,
wartości i daty w tekście dla czytnika), link do pagespeed.web.dev. Kolejność i nazwy z `serwisy.json` (bez `ukryty`).
Zero JS. Realizacja bez pomiaru mobile nie dostaje wiersza.

**Format `pomiary.json`:** bez zmian dla `Realizacje.astro` (`pomiary[adres].{data, mobile, desktop, teren}`) + nowe pole
`pomiary[adres].historia: [{data, telefon, komputer}]` (wydajność mobile/desktop dnia, najstarszy pierwszy, najwyżej 60;
drugi pomiar tego samego dnia zastępuje wpis dnia). Pierwszy wpis historii: 2026-11-06.

**Skrypt** `scripts/psi-realizacje.py` — pomiary SZEREGOWO, odstęp 10 s (równoległe PSI dają timeouty), 2 próby z odstępem 15 s.
Tryby: `--sucho` (mierzy i wypisuje, nic nie zapisuje) · `--bez-commita` (zapisuje plik) · domyślnie (cron): zapis, commit
SAMEGO `src/data/pomiary.json` komunikatem „Pomiar PSI realizacji <RRRR-MM-DD>” i `git push origin main`. Push idzie przez
pomocnika poświadczeń z `.git/config` repo (czyta `~/.sekrety/github-token` sam) — skrypt nie dotyka tokenu.
Bezpieczniki: gałąź ≠ `main` albo zmiany w INNYCH śledzonych plikach → plik się zapisuje, commit i push pominięte, kod 3;
przed pomiarem `git pull --ff-only`; odrzucony push → `git pull --rebase` i drugi push.
Klucz `pagespeed-z440` czyta `uruchom.sh` do zmiennej środowiska procesu Pythona (`KEY`), nigdy w argumentach ani na ekranie.

**Cron (Z440, dopisuje Piotr `crontab -e`):**
```
30 5 * * * bash /home/ecopower/projekty/WWW/narzedzia/psi-realizacje/uruchom.sh >> /home/ecopower/projekty/WWW/narzedzia/psi-realizacje/dziennik.log 2>&1
```
Każdy commit cronu uruchamia Actions i przebudowę strony (repo publiczne — bez kosztu minut). Push o 05:30 nie zrywa
zapisów w panelu Sveltia, chyba że ktoś w nim wtedy pracuje.

## Ż3 — ostatnia publikacja
HTML ma zawsze datę builda (prop `zbudowano`, domyślnie chwila renderowania = chwila publikacji). `publikacja.js` pyta
`https://api.github.com/repos/ecopowerpolska/ecopowerpolska.github.io/commits?per_page=1` bez klucza (limit GitHuba 60/h na IP)
i pokazuje `commit.committer.date` względnie: „przed chwilą”, „minutę temu”, „3 minuty temu”, „5 minut temu”, „godzinę temu”,
„22 godziny temu”, „wczoraj”, „N dni temu”; `title` = pełna data. Błąd lub limit → zostaje data builda. Nic nie zapisuje.
Uwaga: od uruchomienia cronu Ż2 codzienny commit pomiaru też jest „publikacją” (strona się wtedy przebudowuje).

## Ż4 — licznik użyć darmowych narzędzi
`audyt.php` po każdym udanym sprawdzeniu (świeżym i z pamięci podręcznej) woła `licznik_zlicz($katStan)` z `formularz/licznik.php`.
Zapis: `stan/licznik-RRRR-MM-DD.json` (`{"n":N}`, dzień w strefie Europe/Warsaw) i `stan/licznik-razem.json` (`{"n":M}`);
blokada `stan/licznik.lock` (flock), zapis atomowy (plik tymczasowy + `rename`), prawa 0600; pliki dzienne starsze niż 400 dni
kasowane. **Bez IP, bez adresu sprawdzanej strony, bez nazwy testu.** Test `dane_terenowe_crux` idzie z przeglądarki do PSI
(bez PHP), więc się nie liczy.
`GET https://api.ecopowerpolska.pl/licznik.php` → `{"dzis":N,"razem":M}`, `Cache-Control: public, max-age=60`,
`Access-Control-Allow-Origin` tylko dla `https://www.ecopowerpolska.pl` (+ `Vary: Origin`); obcy Origin 403, inna metoda niż GET/HEAD 405.
Komponent: „Dziś sprawdzono N stron, a od początku M.” (odmiana stronę/strony/stron); przy zerze, błędzie i bez JS: „Darmowe i bez logowania.”

## Ż5 — „W tej chwili czyta N osób”
**Co jest zapisywane (do polityki prywatności — F):** w `stan/obecni.json` na serwerze lh.pl wyłącznie pary
`<pierwsze 16 znaków SHA-256 losowego identyfikatora>: <czas ostatniego sygnału, sekundy unix>`. Identyfikator to 32 losowe
znaki szesnastkowe wylosowane przez przeglądarkę przy otwarciu strony i trzymane **tylko w pamięci tej karty** — bez ciasteczek,
bez `sessionStorage`/`localStorage`, nie przeżywa przejścia na inną podstronę. Nie zapisujemy adresu IP, adresu strony,
przeglądarki ani żadnych nagłówków. Wpis znika po 5 minutach bez sygnału albo od razu po zamknięciu karty (sygnał `wyjscie`).
Serwer, jak każdy, widzi IP w chwili żądania; ten skrypt go nie zapisuje (dzienniki serwera lh.pl — wg ustawień hostingu).
Mechanizm: `obecni.js` wysyła `POST id=<…>` (formularz URL-encoded, bez preflight CORS) od razu i co 60 s, gdy karta jest
widoczna; przy powrocie do karty od razu; przy `pagehide` `sendBeacon` z `wyjscie=1`. Odpowiedź `{"teraz":N}`.
`obecni.php`: POST tylko z Origin `https://www.ecopowerpolska.pl` (inaczej 403), `id` = `^[a-f0-9]{32}$` (inaczej 400),
GET zwraca liczbę bez zapisu; blokada `stan/obecni.lock` (flock), zapis atomowy, prawa 0600, bezpiecznik 5 000 wpisów.
Dlaczego bez `sessionStorage` (zlecenie mówiło o nim): zapis czegokolwiek w urządzeniu użytkownika (także Web Storage) to
przechowywanie informacji w urządzeniu końcowym (PKE art. 399), a licznik obecności nie jest niezbędny do usługi — wymagałby
zgody. Id w pamięci karty + sygnał wyjścia daje tę samą dokładność bez zapisu w urządzeniu.
Ograniczenie: kto chce, może skryptem zawyżyć liczbę (nie ma IP do limitu) — sufit 5 000 i 5 minut ogranicza skutek.

## Ż6 — pas rynku
`PasRynku.astro` czyta `src/data/rynek.json` (3–4 pozycje: `nazwa`, `wartosc` w %, `zrodlo`, `zrodloAdres`, `odczyt`).
Dziś: Astro 71 %, WordPress 48 %, Next.js 35 % — „HTTP Archive / Chrome UX Report (CrUX), dane cytowane przez astro.build”,
odczyt 2026-11-06, `zweryfikowaneUZrodla: false`. **Liczb NIE zweryfikowano u źródła pierwotnego:** raport technologii
HTTP Archive (`httparchive.org/reports/techreport/`) renderuje liczby JavaScriptem (WebFetch 2026-11-06 zwrócił same
symbole zastępcze). Weryfikacja: otworzyć raport w Chrome, technologie Astro / WordPress / Next.js, „Good Core Web Vitals”,
urządzenie i miesiąc → wpisać liczby, miesiąc danych i `zweryfikowaneUZrodla: true`.

## Wgranie PHP na lh.pl (ręcznie, wg `docs/FORMULARZ.md §3`; robi integrator)
Konto `serwer263249`, katalog `public_html/api.ecopowerpolska.pl/`, klucz `~/.ssh/lh-lejek`, port 40022. `konfig/` i `stan/` nietknięte
(`stan/.htaccess` już zamyka katalog; pliki `licznik-*`, `obecni.*` powstają same).
```bash
cd /home/ecopower/projekty/WWW/strony/ecopowerpolska.pl/formularz
tar -cf - licznik.php obecni.php audyt.php | ssh -o IdentitiesOnly=yes -i ~/.ssh/lh-lejek -p 40022 serwer263249@serwer263249.lh.pl \
  'set -e; cd public_html/api.ecopowerpolska.pl; tar -xf -; chmod 644 licznik.php obecni.php audyt.php;
   for f in licznik.php obecni.php audyt.php; do php -l "$f"; done'
```
Kontrola po wgraniu:
```bash
U=https://api.ecopowerpolska.pl; O='Origin: https://www.ecopowerpolska.pl'
curl -s -D- -H "$O" $U/licznik.php | grep -iE '^HTTP|allow-origin|^\{'     # 200, ACAO www, {"dzis":…,"razem":…}
curl -s -H "$O" "$U/audyt.php?test=seo-techniczne&url=https://nadihome.pl/" >/dev/null; curl -s -H "$O" $U/licznik.php   # dzis +1
curl -s -H "$O" -d id=$(openssl rand -hex 16) $U/obecni.php                 # {"teraz":1} (lub więcej)
curl -s -o /dev/null -w '%{http_code}\n' -d id=$(openssl rand -hex 16) $U/obecni.php    # 403 (bez Origin)
curl -s -o /dev/null -w '%{http_code}\n' $U/stan/obecni.json                # 403
```
