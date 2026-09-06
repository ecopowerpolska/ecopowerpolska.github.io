# ecopowerpolska.pl — agregat serwisów EcoPower Polska

Jednostronicowa witryna-wizytówka: **kafelki graficzne prowadzące na serwisy spółki** plus
stopka z danymi rejestrowymi. Bez podstron opisowych, bez schematu połączeń, bez transakcji.

- **Narzędzie:** Astro, wyłącznie statycznie (`output: 'static'`), **zero JavaScriptu**.
- **Hosting:** GitHub Pages przez GitHub Actions — nie lh.pl. Podstawa: strona nie prowadzi
  żadnej transakcji, więc mieści się w jedynym nazwanym wyjątku od hostingu warsztatowego
  (skill `github-pages-wizytowka` §B1).
- **Panel treści:** Sveltia CMS pod `/admin/` — **dołożenie kafelka to wpis w panelu,
  nie zmiana kodu.**

## Stan na 2026-09-06

Strona jest **zbudowana i wdrożona na GitHub Pages** (`https://ecopowerpolska.github.io/`,
przepływ `build-i-wdrozenie` zakończony sukcesem trzykrotnie, status Pages „built"; `/admin/`
i `/admin/config.yml` odpowiadają). Domena `ecopowerpolska.pl` wciąż wskazuje starego
WordPressa na lh.pl — przełączenie ręczne, dopiero po akceptacji podglądu przez Piotra
(`docs/przelaczenie-domeny.md`). Piotr nie ma jeszcze działającego dostępu do panelu treści —
stan i kolejne kroki w `docs/CMS-LOGOWANIE.md`.

## 🔴 Zanim cokolwiek zmienisz

1. **`docs/BRAKI.md`** — co zostało otwarte (dane firmowe, referencje wyglądu, decyzje
   o botach); pozycje ①②③ są już rozstrzygnięte, zapis w pliku to teraz historia decyzji.
2. **`docs/CMS-LOGOWANIE.md`** — dostęp Piotra do panelu treści.
3. **`docs/PIERWSZY-PRZEBIEG.md`** — zapis tego, co się wydarzyło przy pierwszym uruchomieniu.
4. **`CLAUDE.md`** — kontrakt pracy nad kodem Astro tej strony. Wiążący dla każdej sesji tutaj.

## Gdzie co leży

| Ścieżka | Co to |
|---|---|
| `src/data/serwisy/*.md` | **kafelki** — jeden plik = jeden serwis; pisze je panel |
| `src/data/firma.json` | dane rejestrowe stopki; edytowalne z panelu |
| `src/assets/serwisy/` | obrazki kafelków (wgrywa panel; dziś nadihome.jpg i studioagat.jpg) |
| `src/content.config.ts` | schemat kolekcji — **lustro `public/admin/config.yml`** |
| `src/pages/index.astro` | jedyna trasa treściowa |
| `src/styles/motyw.css` | **jedyne** miejsce z kolorami, skalą typograficzną i siatką odstępów |
| `public/admin/` | panel treści (Sveltia) |
| `.github/workflows/` | build i wdrożenie na Pages |
| `scripts/`, `guard.config.json`, `docs/rules.md` | pakiet walidatora astro-guard |
| `docs/przelaczenie-domeny.md` | DNS, CNAME, `site` — runbook przełączenia |
| `docs/link-zwrotny-dla-serwisow.md` | gotowe fragmenty do wklejenia na tamtych stronach |
| `docs/CMS-LOGOWANIE.md` | dostęp Piotra do panelu treści — stan i kroki |

## Dwie rzeczy, o które najłatwiej się potknąć

1. **`src/content.config.ts` i `public/admin/config.yml` muszą się zgadzać co do pola.**
   Rozjazd nie wychodzi w panelu — wychodzi czerwonym buildem w Actions, już po tym,
   jak klient zapisał wpis.
2. **`base` w `astro.config.mjs` nie występuje** i przy podpięciu domeny nie ma czego usuwać —
   bo repozytorium jest witryną użytkownika. Gdyby ktoś zmienił nazwę repo, wraca cała
   pułapka z `github-pages-wizytowka` §E: build zielony, linki wewnętrzne donikąd.

## Weryfikacja

```bash
npm run verify   # guard → astro check → build → audyt dist
```
Czerwony `guard` = zadanie nieukończone. Nie „w większości działa". Nieukończone.
