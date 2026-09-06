# Pierwszy przebieg — zapis tego, co się wydarzyło

Ten dokument prowadził przez pierwsze uruchomienie strony: instalację, weryfikację,
założenie repozytorium i pierwszy build/deploy na GitHub Pages. **Przebieg się odbył
2026-09-04 i zakończył sukcesem.** Treść niżej opisuje wynik, nie plan — jeśli szukasz
poleceń do odtworzenia całego przebiegu od zera (np. po katastrofie repozytorium),
kroki są zachowane jako materiał źródłowy w historii commitów tego pliku.

Katalog roboczy: `/home/ecopower/projekty/WWW/strony/ecopowerpolska.pl`

---

## Stan na 2026-09-06

- **Zależności zainstalowane** — `package.json` ma rozwiązane numery (`astro ^7.3.1`,
  `@astrojs/sitemap ^3.7.4`), jest `package-lock.json`. Instalację uruchomił Piotr
  poleceniem `npm install astro@latest @astrojs/sitemap@latest` +
  `npm install -D @astrojs/check@latest typescript@latest`, zgodnie z zakazem wpisywania
  numerów wersji z pamięci (kontrakt warsztatu §4.3).
- **Repozytorium:** decyzja Piotra z 2026-09-04 — **publiczne**, `ecopowerpolska.github.io`,
  konto na planie Free (zapis decyzji i uzasadnienie: `docs/BRAKI.md` ①②). Kolejność
  „repo → Pages (Source: GitHub Actions) → dopiero push" zachowana.
- **Przepływ `build-i-wdrozenie`** (`.github/workflows/build-i-wdrozenie.yml`) **zakończony
  sukcesem trzykrotnie.** Status GitHub Pages: **built**.
- **Adresy odpowiadają 200:** `https://ecopowerpolska.github.io/`, `https://ecopowerpolska.github.io/admin/`,
  `https://ecopowerpolska.github.io/admin/config.yml`.
- **Audyt `dist` w kroku `astro-guard — audyt dist`** — skoro przepływ kończy się sukcesem,
  krok znalazł katalog `dist/` tam, gdzie się go spodziewał; osobnego pomiaru „ile stron
  bez JS-a" nie zapisano tutaj.

**Czego z pierwotnych pytań pomiarowych NIE potwierdzono** (nie zgaduję):
- czy wersja Node użyta przez `withastro/action`/`actions/setup-node@v7` zgadza się co do
  liczby z `.nvmrc` — NIEPOTWIERDZONE;
- ile minut zajął pojedynczy przebieg — NIEPOTWIERDZONE;
- czy GitHub Pages faktycznie serwuje `404.html` pod nieistniejącym adresem — NIEPOTWIERDZONE.

## Co zostało otwarte

1. **Logowanie Piotra do panelu treści (Sveltia CMS)** — nie działa. Osobny wątek, w toku;
   stan i kolejne kroki: **`docs/CMS-LOGOWANIE.md`**. Nie opisuję procedury logowania tutaj
   drugi raz — źródłem jest tamten plik.
2. **Domena `ecopowerpolska.pl`** — DNS nadal wskazuje starego WordPressa na lh.pl.
   Przełączenie jest ręczne i następuje dopiero po akceptacji podglądu przez Piotra.
   Procedura: **`docs/przelaczenie-domeny.md`**.
3. **`public/CNAME`** — celowo nieobecny na etapie podglądu (patrz punkt 2).
4. **Dane firmowe** — `src/data/firma.json` ma puste `adresSiedziby` i `telefon`; Piotr ich
   nie podał. Skutki i sposób uzupełnienia: `docs/BRAKI.md` ⑦⑧.
5. **Warstwa wizualna (UX/UI)** — przebudowywana 2026-09-06 w osobnym wątku pracy.

## Kontrola cudzej roboty

Podagenta `nadi` uruchamia **wyłącznie Piotr** (kontrakt §4.7).
