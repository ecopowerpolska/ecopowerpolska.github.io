# ecopowerpolska.pl — agregat serwisów EcoPower Polska

Jednostronicowa witryna-wizytówka: **kafelki graficzne prowadzące na serwisy spółki** plus
stopka z danymi rejestrowymi. Bez podstron opisowych, bez schematu połączeń, bez transakcji.

- **Narzędzie:** Astro, wyłącznie statycznie (`output: 'static'`), **zero JavaScriptu**.
- **Hosting:** GitHub Pages przez GitHub Actions — nie lh.pl. Podstawa: strona nie prowadzi
  żadnej transakcji, więc mieści się w jedynym nazwanym wyjątku od hostingu warsztatowego
  (skill `github-pages-wizytowka` §B1).
- **Panel treści:** Sveltia CMS pod `/admin/` — **dołożenie kafelka to wpis w panelu,
  nie zmiana kodu.**

## 🔴 Zanim cokolwiek uruchomisz

1. **`docs/BRAKI.md`** — dwie pozycje BLOKUJĄCE (publiczne czy prywatne repozytorium; nazwa repo)
   plus wszystko, czego Piotr nie podał.
2. **`docs/PIERWSZY-PRZEBIEG.md`** — polecenia po kolei. Instalacje i buildy uruchamia Piotr.
3. **`CLAUDE.md`** — kontrakt pracy nad kodem Astro tej strony. Wiążący dla każdej sesji tutaj.

## Gdzie co leży

| Ścieżka | Co to |
|---|---|
| `src/data/serwisy/*.md` | **kafelki** — jeden plik = jeden serwis; pisze je panel |
| `src/data/firma.json` | dane rejestrowe stopki; edytowalne z panelu |
| `src/assets/serwisy/` | obrazki kafelków (wgrywa panel; dziś pusty) |
| `src/content.config.ts` | schemat kolekcji — **lustro `public/admin/config.yml`** |
| `src/pages/index.astro` | jedyna trasa treściowa |
| `src/styles/motyw.css` | **jedyne** miejsce z kolorami, skalą typograficzną i siatką odstępów |
| `public/admin/` | panel treści (Sveltia) |
| `.github/workflows/` | build i wdrożenie na Pages |
| `scripts/`, `guard.config.json`, `docs/rules.md` | pakiet walidatora astro-guard |
| `docs/przelaczenie-domeny.md` | DNS, CNAME, `site` — runbook przełączenia |
| `docs/link-zwrotny-dla-serwisow.md` | gotowe fragmenty do wklejenia na tamtych stronach |

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
