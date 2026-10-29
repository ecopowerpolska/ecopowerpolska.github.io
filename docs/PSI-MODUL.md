# Test szybkości strony na ecopowerpolska.pl: moduł PageSpeed Insights na miejscu, klucz przeglądarkowy, zmienna PUBLIC_PSI_KLUCZ, fallback do pagespeed.web.dev

Stan: 2026-10-29. Dotyczy strony `src/pages/jak-sprawdzic-szybkosc-strony.astro`
(adres: `https://www.ecopowerpolska.pl/jak-sprawdzic-szybkosc-strony/`).

## Co robi strona

1. Klient wpisuje adres, jak mu wygodnie: `twojafirma.pl`, `www.twojafirma.pl`, `https://twojafirma.pl/oferta`.
   Formularz sam dokłada `https://`, niczego nie każe poprawiać.
2. Klient zostaje na naszej stronie — bez nowej karty i bez przekierowania do Google (gdy działa JS i build dostał klucz).
3. Pod formularzem pasek stanu („Mierzymy telefon…", „Mierzymy komputer…”). Pomiar trwa zwykle 15–40 s i wykonuje Google.
4. Wynik na naszej stronie: 4–5 ocen 0–100 dla telefonu i komputera, metryki po polsku, jedno zdanie po ludzku, co wynik
   znaczy, oraz wezwanie „Chcesz, żeby było szybciej? Napisz do nas” z linkiem do `/kontakt/`.
5. „Pełny raport w Google” zostaje jako mała opcja na końcu.
6. Gdy Google odmówi (429/403) albo adres nie odpowiada: komunikat po polsku na naszej stronie, bez przerzucania do Google
   (link do raportu Google jest tylko opcją).
7. Bez JS albo bez klucza: formularz GET do `pagespeed.web.dev` działa jak dotąd (nowa karta) i też przyjmuje adres bez `https://`.

Skrypt na dole pliku strony woła `https://www.googleapis.com/pagespeedonline/v5/runPagespeed?...&key=<klucz>` bezpośrednio
z przeglądarki klienta. Klucz pochodzi z `import.meta.env.PUBLIC_PSI_KLUCZ` (czytany w trakcie builda) i jest wpisany w atrybut
`data-klucz` formularza `#psi`. **Bez klucza w buildzie atrybutu nie ma i skrypt niczego nie przechwytuje** — działa wariant z pkt 7
(Google bez klucza odpowiada 429 — zmierzone 2026-10-27).

## Skąd klucz: jak go utworzyć w Google Cloud

Klucz tworzy Piotr (albo sesja w jego Chrome) w projekcie Google Cloud, który ma już PageSpeed Insights API.

1. W konsoli Google Cloud wybierz właściwy projekt.
2. **Interfejsy API i usługi → Biblioteka** → „PageSpeed Insights API” → upewnij się, że jest **włączone**
   (bez tego klucz zwraca 403).
3. **Interfejsy API i usługi → Dane logowania → Utwórz dane logowania → Klucz interfejsu API**.
4. W oknie klucza (albo „Edytuj klucz”) ustaw **Ograniczenia aplikacji: „Strony internetowe”** i dodaj dwa referery:
   - `https://www.ecopowerpolska.pl/*`
   - `https://ecopowerpolska.pl/*`
5. **Ograniczenia interfejsu API → Ogranicz klucz** → zaznacz wyłącznie **„PageSpeed Insights API”**.
6. **Zapisz.** Ograniczenia działają zwykle po kilku minutach.
7. Wartość klucza Piotr zapisuje w sejfie pod nazwą `psi-klucz-przegladarkowy` (okno `dodaj-klucz.sh`, nie w rozmowie).

Nazwa klucza w konsoli: np. „ecopowerpolska.pl — PSI w przeglądarce”, żeby nie pomylić go z kluczem serwerowym.

## Dlaczego klucz jest jawny i czego nie wolno

- Klucz leży w HTML strony, więc widzi go każdy. To **zamierzone**: ochroną jest ograniczenie do referera i do jednego API,
  nie tajność. Dokumentacja Google przewiduje klucz doklejany do adresu zapytania (`key=`), bez dodatkowego kodowania (odczyt 2026-10-29); jego ochroną są ograniczenia nałożone w konsoli.
- Dlatego jest **zmienną Actions (`vars`)**, nie sekretem. Nagłówek workflow („żadnego sekretu w GitHub Secrets”) pozostaje prawdziwy.
- 🔴 **Nigdy nie wstawiaj do strony klucza serwerowego `pagespeed-z440` z `~/.sekrety/`.** Nie ma ograniczenia referera
  (służy skryptom z Z440), więc po wstawieniu do HTML każdy mógłby go przejąć i wyczerpać limity albo użyć z własnego serwera.
- Klucz przeglądarkowy działa tylko z żądań z nagłówkiem `Referer` z dozwolonych adresów — z `curl` z Z440 bez
  `-e https://www.ecopowerpolska.pl/` dostaniesz 403 („referer blocked”); to nie usterka.

## Jak klucz trafia do builda

- Plik `.github/workflows/build-i-wdrozenie.yml`, krok „Instalacja, build i spakowanie artefaktu”: blok
  `env: PUBLIC_PSI_KLUCZ: ${{ vars.PUBLIC_PSI_KLUCZ }}`. `withastro/action@v6` jest akcją złożoną (composite) bez inputu na
  zmienne; `env` kroku jest widoczne dla jej wewnętrznych kroków, w tym `npm run build`, a Astro czyta `PUBLIC_*` z procesu
  (źródło: `action.yml` i README `withastro/action`, odczyt 2026-10-29).
- **Kto ustawia:** integrator/Piotr, raz, w repo `ecopowerpolska/ecopowerpolska.github.io` (Settings → Secrets and variables →
  Actions → zakładka **Variables**) albo poleceniem:

  ```bash
  K=$(bash /home/ecopower/projekty/test-claude/potwor/scripts/klucze-dostepne.sh psi-klucz-przegladarkowy)
  GH_TOKEN=$(bash /home/ecopower/projekty/test-claude/potwor/scripts/klucze-dostepne.sh github-token) \
    gh variable set PUBLIC_PSI_KLUCZ --repo ecopowerpolska/ecopowerpolska.github.io --body "$K"
  ```

  Gdy `gh` nie ma — przez API (`POST /repos/ecopowerpolska/ecopowerpolska.github.io/actions/variables` z
  `{"name":"PUBLIC_PSI_KLUCZ","value":"<klucz>"}`; gdy zmienna już istnieje: `PATCH …/actions/variables/PUBLIC_PSI_KLUCZ`
  z `{"value":"<klucz>"}`).
- **Jak podmienić klucz:** utwórz nowy w Google Cloud (kroki wyżej), zaktualizuj sejf, nadpisz zmienną (`gh variable set` nadpisuje),
  uruchom przebieg (`workflow_dispatch` albo dowolny push na `main`), potem usuń stary klucz w Google Cloud.
  Zmiana zmiennej **sama nie przebudowuje strony** — klucz wchodzi do HTML dopiero w następnym buildzie.
- **Jak sprawdzić w `dist` (pokazuje tylko początek klucza):**

  ```bash
  grep -o 'data-klucz="[^"]*"' dist/jak-sprawdzic-szybkosc-strony/index.html | cut -c1-20
  ```

  Pusty wynik = build nie dostał klucza (zmienna nieustawiona/pusta) i strona działa w wariancie bez modułu.
  Na żywej stronie ten sam test: `curl -s https://www.ecopowerpolska.pl/jak-sprawdzic-szybkosc-strony/ | grep -o 'data-klucz="[^"]*"' | cut -c1-20`.

## Limity

- Dokumentacja Google (`developers.google.com/speed/docs/insights/v5/get-started`, odczyt 2026-10-29) mówi tylko, że klucz jest
  opcjonalny do prób, a potrzebny do częstych, zautomatyzowanych zapytań. **Liczb limitów (na dobę, na 100 s) ta strona nie
  podaje — NIE USTALONO.** Aktualny limit dla klucza widać w Google Cloud: Interfejsy API i usługi → PageSpeed Insights API → Limity.
- Limit liczy się **na projekt/klucz, nie na odwiedzającego**: wszyscy klienci strony dzielą jedną pulę. Skrypt `psi-realizacje.py`
  z Z440 (kluczem serwerowym `pagespeed-z440`) to osobny klucz, ale ten sam projekt może mieć wspólną pulę — jeśli tak, wpisz tu
  wynik sprawdzenia w konsoli.

## Co zrobić, gdy Google zwraca 429 albo 403

- **429** (za dużo zapytań / limit): strona pokazuje komunikat po polsku (brzmienie: wg implementacji W1) i link do raportu
  w Google jako opcję. Sprawdź w konsoli Google Cloud wykres zużycia limitu PSI API; jeśli pula jest wyczerpana — poczekaj
  na reset albo zgłoś zwiększenie limitu.
- **403**: najczęściej (a) PageSpeed Insights API wyłączone w projekcie, (b) referer spoza listy (np. adres podglądu, inna domena),
  (c) klucz ograniczony do innego API, (d) klucz usunięty. Sprawdź kolejno w Google Cloud → Dane logowania → klucz → ograniczenia.
- **Adres klienta nie odpowiada / zwraca błąd**: Google zwraca 400/500 z komunikatem — strona pokazuje to po polsku, to nie awaria modułu.
- Tymczasowe obejście: usuń zmienną `PUBLIC_PSI_KLUCZ` (albo ustaw pustą) i przebuduj — strona wraca do formularza GET na `pagespeed.web.dev`.

## Kontrola po wdrożeniu (lista dla integratora)

Po pushu i zielonym przebiegu Actions, na żywej stronie `https://www.ecopowerpolska.pl/jak-sprawdzic-szybkosc-strony/` (z `?v=1`
przy odczycie `curl`, żeby ominąć pamięć podręczną):

1. `grep` z `data-klucz` powyżej — klucz jest (wartość niepusta).
2. W przeglądarce wpisz `ecopowerpolska.pl` (bez `https` i `www`) → pomiar rusza na miejscu, pasek stanu, po 15–40 s wynik.
3. Wpisz `www.ecopowerpolska.pl` → to samo.
4. Wpisz `https://www.ecopowerpolska.pl/kontakt/` (ze ścieżką) → pomiar tego adresu.
5. Wpisz adres nieistniejący (np. `to-na-pewno-nie-istnieje-xyz123.pl`) → komunikat po polsku na stronie, bez przerzucenia do Google.
6. Telefon 390 px (headless albo emulacja): formularz, pasek stanu i tabele wyników bez przewijania w poziomie, cele dotyku ≥ 44 px.
7. Sprawdź w konsoli przeglądarki brak błędów 403/„referer blocked” dla żądania do `googleapis.com`.
8. Wyłącz JS (albo odczyt `curl`) → formularz `action="https://pagespeed.web.dev/analysis"` nadal jest i działa.
9. Wezwanie „Napisz do nas” prowadzi na `/kontakt/`; link „Pełny raport w Google” otwiera `pagespeed.web.dev`.
