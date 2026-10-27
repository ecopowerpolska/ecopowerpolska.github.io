# POMIARY.md — wyniki PageSpeed Insights pod kafelkami Realizacji

Od 2026-10-27 (decyzja Piotra) pod każdym kafelkiem sekcji Realizacje stoi tabela wyników PageSpeed Insights:
pięć kategorii (Wydajność, Ułatwienia dostępu, Sprawdzone metody, SEO, Przeglądanie agentowe) osobno dla telefonu
i komputera, data pomiaru, zdanie o danych terenowych (CrUX) i link „Sprawdź sam” do `pagespeed.web.dev`.

## Skąd są liczby

- `scripts/psi-realizacje.py` woła API PageSpeed Insights v5 dla każdego nieukrytego adresu z `src/data/serwisy.json`,
  strategie `mobile` i `desktop`, kategorie `performance`, `accessibility`, `best-practices`, `seo`, `agentic-browsing`.
- Wynik zapisuje do **`src/data/pomiary.json`** (adres → `data`, `mobile`, `desktop`, `teren`); czyta go `src/components/Realizacje.astro`.
- Przeglądanie agentowe API podaje jako `score` (0–1) w trybie „fraction”: na stronie stoi wynik × 100 i obok liczba testów
  zdanych/wszystkich (np. 4/4 — tak samo jak pokazuje pagespeed.web.dev). Testy WebMCP są „nie dotyczy” i się nie liczą.
- Dane terenowe: `loadingExperience.overall_category`, a gdy brak — `originLoadingExperience` (cała domena). Brak obu = „brak”
  — nowa strona nie ma jeszcze próbki z 28 dni odwiedzin w Chrome; kafelek mówi to wprost.
- Metryki laboratoryjne (FCP, LCP, TBT, CLS, Speed Index) są w pliku, na stronie ich nie pokazujemy.

## Jak odświeżyć (z katalogu strony)

```bash
KEY=$(bash /home/ecopower/projekty/test-claude/potwor/scripts/klucze-dostepne.sh pagespeed-z440) python3 -I scripts/psi-realizacje.py
# jeden adres:      … python3 -I scripts/psi-realizacje.py --adres https://panwylewka.pl/
```

Klucz istnieje wyłącznie w zmiennej polecenia — nigdy w pliku ani w repozytorium. Pomiar 7 adresów × 2 trwa ok. 2–3 min.
Po pomiarze: build, bramka, commit `src/data/pomiary.json`, push (zwykły łańcuch publikacji strony).

- Błąd API po 2 próbach: zostaje poprzedni wynik tej strategii (z własną datą), a gdy go nie ma — kafelek pokazuje „pomiar w toku”.
- Nowa realizacja w panelu: kafelek pokazuje „pomiar w toku” do pierwszego uruchomienia skryptu.
- Kluczem jest `adres` z `serwisy.json` dosłownie (z ukośnikiem na końcu) — zmiana adresu w panelu wymaga nowego pomiaru.

## Co ile

Propozycja: raz w miesiącu i po każdej większej zmianie na którejś realizacji. Czy ma to robić rutyna (cron / rutyna chmurowa)
— decyzja Piotra (🟡). Wyniki laboratoryjne wahają się o kilka punktów między przebiegami — liczy się trend, nie pojedynczy punkt.

## Panel CMS

`pomiary.json` **nie jest w panelu** (`public/admin/config.yml`) i być nie ma: liczby mają pochodzić z pomiaru, nie z ręcznej edycji.
