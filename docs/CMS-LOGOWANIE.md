# Panel treści — logowanie i dodawanie kafelków (instrukcja dla Piotra)

Ten dokument opisuje, jak wejść do panelu, zalogować się **raz** (bez wklejania tokena
za każdym razem) i dodać nowy kafelek serwisu. Napisany po ludzku, nie dla programisty.

Panel to **Sveltia CMS** — mała aplikacja w przeglądarce. Nie ma osobnego serwera:
panel zapisuje zmiany bezpośrednio do repozytorium GitHub, a strona przebudowuje się
sama (sekcja 4 niżej).

---

## 1. Adres panelu

- **Dziś (etap podglądu):** `https://ecopowerpolska.github.io/admin/`
- **Po przełączeniu domeny** (`docs/przelaczenie-domeny.md`, wykonuje Piotr osobno):
  `https://ecopowerpolska.pl/admin/`

Adres wpisujesz w przeglądarce jak każdy inny. Panel nie jest w wynikach wyszukiwania
(celowo — to narzędzie robocze, nie treść dla odwiedzających).

---

## 2. Pierwsze logowanie — wygenerowanie tokenu

Token to jednorazowo wygenerowany „klucz" do Twojego repozytorium na GitHubie. Panel
poprosi o niego **tylko raz na tę przeglądarkę** (sekcja 3 wyjaśnia dlaczego).

1. Wejdź na adres panelu (sekcja 1). Zobaczysz dwa przyciski logowania.
2. **Kliknij dolny przycisk: „Zaloguj się za pomocą tokenu dostępu"** (nie górny —
   górny to logowanie bez tokenu, które **dziś jeszcze nie działa**, opisane w sekcji 7).
3. W okienku, które się otworzy, jest link „Token możesz wygenerować na stronie ustawień
   użytkownika GitHub" — kliknij go. Otworzy się nowa karta z formularzem GitHuba,
   już częściowo wypełnionym.
4. Na stronie GitHuba sprawdź/uzupełnij:
   - **Token name** — zostaw „Sveltia CMS" albo wpisz coś rozpoznawalnego, np.
     „Panel ecopowerpolska.pl".
   - **Resource owner** — wybierz **`ecopowerpolska`** (to konto, na którym jest
     repozytorium strony).
   - **Expiration (wygaśnięcie)** — dwie sensowne opcje:
     - **No expiration** (bez wygaśnięcia) — najwygodniejsze, token działa, dopóki go
       sam nie usuniesz. GitHub czasem to odradza ze względów bezpieczeństwa, ale przy
       jednym użytkowniku i repozytorium bez danych wrażliwych to rozsądny wybór.
     - Konkretna liczba dni (np. 90 albo maksymalne 365) — token przestanie działać
       w tym dniu i trzeba będzie wygenerować nowy (sekcja 7 mówi, jak rozpoznać ten
       moment).
   - **Repository access** — zaznacz **„Only select repositories"**, a potem wybierz
     **`ecopowerpolska.github.io`** (to jest repozytorium tej strony — nazwa różni się
     od `ecopowerpolska.pl`, bo tak nazywa się na GitHubie, dopóki nie przełączymy
     domeny; nazwa repozytorium się przez to NIE zmieni).
   - **Repository permissions → Contents** — powinno już być ustawione na
     **„Read and write"** (formularz przychodzi z tym gotowym z linku w panelu).
     GitHub może dodatkowo sam zaznaczyć „Metadata: Read-only" — to jest wymagane
     przez GitHuba automatycznie, zostaw jak jest. Innych uprawnień nie zaznaczaj.
5. Przewiń w dół i kliknij **„Generate token"**.
6. GitHub pokaże token **tylko raz**, w postaci długiego ciągu znaków zaczynającego
   się od `github_pat_…`. Kliknij ikonę kopiowania obok niego.
7. Wróć do karty z panelem, wklej token w pole „Osobisty token dostępu" i kliknij
   **„Zaloguj się"**.

Jeśli wszystko się zgadza, zobaczysz listę kolekcji: **„Serwisy (kafelki)"** i
**„Ustawienia"**.

🔴 **Token jest sekretem** — traktuj go jak hasło. Nie wklejaj go nigdzie poza tym
jednym oknem logowania panelu (nie do maila, nie na czacie, nie do pliku). Jeśli
podejrzewasz, że gdzieś wyciekł: GitHub → `Settings → Developer settings → Fine-grained
personal access tokens` → znajdź token po nazwie → **Delete**, potem wygeneruj nowy
tą samą drogą.

---

## 3. Czy trzeba wklejać token przy KAŻDYM logowaniu?

**Nie.** Po pierwszym wklejeniu panel zapamiętuje token w tej przeglądarce (dokładnie:
w jej lokalnej pamięci, tzw. `localStorage`) — kolejne wejścia na adres panelu w tej
samej przeglądarce na tym samym komputerze/telefonie od razu pokazują listę kolekcji,
bez pytania o token.

Token trzeba wkleić ponownie tylko gdy:
- otwierasz panel **w innej przeglądarce** albo **na innym urządzeniu** (telefon,
  drugi komputer) — pierwszy raz na każdym z nich;
- używasz **okna prywatnego/incognito** — ono nic nie pamięta po zamknięciu;
- **wyczyściłeś dane przeglądania** (historia, ciasteczka, „dane witryn") w tej
  przeglądarce;
- token **wygasł** (jeśli przy generowaniu wybrałeś datę wygaśnięcia zamiast
  „No expiration") albo **został usunięty** na GitHubie.

W żadnym z tych przypadków nic się nie psuje — po prostu powtarzasz sekcję 2.

---

## 4. Dodanie nowego kafelka serwisu

1. W panelu wejdź w kolekcję **„Serwisy (kafelki)"**.
2. Kliknij **„New Serwis"** (albo podobny przycisk „+ Nowy wpis" — panel bywa
   częściowo po angielsku).
3. Wypełnij pola:
   - **Nazwa serwisu** — napis na kafelku, np. „nadihome.pl". *(wymagane)*
   - **Adres docelowy** — pełny adres z `https://`, np. `https://nadihome.pl` —
     tam trafi kliknięcie kafelka. *(wymagane; panel odrzuci adres bez `http(s)://`
     albo ze spacją w środku)*
   - **Krótki podpis** — jedno zdanie pod nazwą, do 160 znaków. *(opcjonalne — puste
     pole jest w porządku, kafelek pokaże samą nazwę)*
   - **Obrazek kafelka** — zdjęcie/grafika w proporcji **16:9**, szerokość co najmniej
     **720 pikseli**. *(opcjonalne — bez obrazka kafelek pokazuje czytelny placeholder
     z inicjałami nazwy)*
   - **Opis obrazka dla czytnika ekranu (alt)** — ⚠️ **WYMAGANE, JEŚLI dodałeś
     obrazek.** Krótko opisz, co jest na zdjęciu, np. „Wnętrze salonu Nadi Home".
     To pole istnieje dla osób niewidomych korzystających z czytnika ekranu — bez
     tego opisu strona **nie zbuduje się po zapisie** (patrz sekcja 6, „build na
     czerwono"). Jeśli **nie** dodajesz obrazka, to pole możesz zostawić puste.
   - **Kolejność** — liczba; mniejsza = kafelek pojawia się wcześniej. Domyślnie 100.
   - **Ukryty** — włącz, żeby wpis został w panelu, ale zniknął ze strony (np. serwis
     tymczasowo nieczynny) — bez kasowania całego wpisu.
4. Kliknij **„Save"** (zapisz) w prawym górnym rogu.

To samo okno służy do **edycji** istniejącego kafelka — wejdź w niego z listy zamiast
klikać „New".

**Dane rejestrowe w stopce** (KRS, NIP, telefon itd.) edytuje się w kolekcji
**„Ustawienia" → „Dane rejestrowe (stopka)"** — jeden formularz, bez tworzenia nowych
wpisów.

---

## 5. Co się dzieje po kliknięciu „Save"

1. Panel od razu zapisuje zmianę jako **commit w repozytorium** na GitHubie (widać go
   w zakładce **Code → commits** repozytorium `ecopowerpolska.github.io` na koncie
   `ecopowerpolska`).
2. Ten commit **sam uruchamia przebudowę strony** — GitHub Actions (zakładka
   **Actions** w repozytorium) buduje stronę od nowa i wgrywa ją na GitHub Pages.
   Nie trzeba niczego dodatkowo klikać ani wołać Piotra do komputera.
3. **Ile to trwa:** przy pomiarze na tej stronie (2026-09-04) cały przebieg —
   budowa + wdrożenie — zajął około **pół minuty**. W praktyce licz na
   „poniżej minuty, rzadko więcej niż kilka minut" — zależy od obciążenia serwerów
   GitHuba, nie od Ciebie.
4. **Gdzie zobaczyć, czy poszło:** zakładka **Actions** w repozytorium — najnowszy
   wpis „build-i-wdrozenie" ma zielony ptaszek (poszło) albo czerwony X (coś nie
   zbudowało się — sekcja 6 mówi, co wtedy).
5. Po zielonym przebiegu odśwież stronę główną (`ecopowerpolska.github.io` albo
   docelowa domena) — zmiana powinna być widoczna. Jeśli przeglądarka pokazuje
   starą wersję, zrób twarde odświeżenie (Ctrl+Shift+R / Cmd+Shift+R) — to zwykle
   pamięć podręczna przeglądarki, nie błąd strony.

---

## 6. GDY NIE DZIAŁA

| Objaw | Najpierw sprawdź | Co zrobić |
|---|---|---|
| **Biały/pusty ekran** zamiast panelu | Konsola przeglądarki (F12 → Console) — czy jest czerwony błąd | Zwykle chwilowy problem z wczytaniem skryptu panelu (unpkg.com) — odśwież stronę za minutę. Jeśli nie pomaga, zgłoś to z opisem błędu z konsoli. |
| **Błąd logowania** po wklejeniu tokenu | Treść komunikatu w okienku logowania | „Podany token jest nieprawidłowy" — wklejony fragment jest niepełny albo token został usunięty/wygasł na GitHubie → wygeneruj nowy (sekcja 2). „Nie masz dostępu do repozytorium" → token nie ma zaznaczonego repozytorium `ecopowerpolska.github.io` przy generowaniu → wygeneruj token od nowa i tym razem zaznacz repozytorium. |
| **Kliknięcie „Zaloguj się przez GitHub"** (górny przycisk) nic nie robi albo pokazuje błąd | To jest oczekiwane — ta droga logowania **nie jest jeszcze wdrożona** (sekcja 7) | Użyj dolnego przycisku „Zaloguj się za pomocą tokenu dostępu" (sekcja 2). |
| **Wpis zapisany w panelu, a na stronie bez zmian** | Zakładka **Actions** repozytorium — czy w ogóle pojawił się nowy przebieg | Brak nowego przebiegu → sprawdź zakładkę **Code → commits** — czy zapis w ogóle trafił do repozytorium (jeśli nie, to błąd zapisu w panelu, nie przebudowy). Przebieg jest, ale czerwony → zobacz wiersz niżej. Przebieg zielony, a strony wciąż nie widać → twarde odświeżenie przeglądarki (Ctrl+Shift+R); jeśli to nie pomaga, poczekaj chwilę — cache serwera GitHuba (kilka minut) też potrafi być przyczyną. |
| **Przebieg w Actions czerwony (build padł)** | Wejdź w ten przebieg → krok `build`, rozwiń czerwony punkt | Najczęstsza przyczyna przy kafelkach: **dodałeś obrazek bez pola „Opis obrazka dla czytnika ekranu"** — build celowo się zatrzymuje, to nie jest awaria, tylko zabezpieczenie (sekcja 4). Wróć do wpisu w panelu, uzupełnij pole „alt", zapisz ponownie. Inna możliwa przyczyna: adres w polu „Adres docelowy" bez `http(s)://` — panel powinien to złapać sam, ale gdyby jednak przeszło, popraw adres i zapisz ponownie. |

Jeśli żaden z powyższych opisów nie pasuje — zrzuć ekran z błędem (i z konsoli
przeglądarki, jeśli jest widoczna) i przekaż go do dalszej analizy. Nic z tego nie
wymaga grzebania w kodzie samodzielnie.

---

## 7. Logowanie bez tokenu (dla przyszłości) — czego jeszcze brakuje

Dziś działa wyłącznie logowanie tokenem (sekcja 2) — jest proste i **działa od razu**,
więc to jest droga zalecana na teraz. Sveltia CMS umie też logowanie „jednym
kliknięciem" przez GitHub (bez wklejania tokenu w ogóle), ale wymaga to wdrożenia
własnego, małego serwisu pośredniczącego (**`sveltia-cms-auth`**) na Cloudflare —
Piotr korzysta z niego dopiero, gdy z panelu ma korzystać ktoś **inny niż on sam**
(dla jednej osoby token jest prostszy i tyle samo bezpieczny). Poniżej dokładne kroki,
gdyby jednak ta droga miała powstać — **nikt z automatu ich nie wykonał**, to lista
do ręcznego przejścia w przeglądarce:

### Krok 1 — Cloudflare Workers
Załóż konto na `cloudflare.com` (jeśli go nie ma) i wdróż projekt
`sveltia-cms-auth` — najprościej przyciskiem „Deploy to Cloudflare Workers" na stronie
`github.com/sveltia/sveltia-cms-auth`. Po wdrożeniu zapisz adres Workera — wygląda
tak: `https://sveltia-cms-auth.<TWOJA-SUBDOMENA>.workers.dev`.

### Krok 2 — Aplikacja OAuth na GitHubie
Zalogowany jako `ecopowerpolska`, wejdź na `github.com/settings/applications/new`
i wypełnij:
- **Application name:** `Sveltia CMS Authenticator` (dowolna nazwa rozpoznawalna)
- **Homepage URL:** `https://github.com/sveltia/sveltia-cms-auth` (dowolny poprawny
  adres — pole wymagane, ale nieużywane funkcjonalnie)
- **Application description:** można zostawić puste
- **Authorization callback URL:** `<adres Workera z kroku 1>/callback`, np.
  `https://sveltia-cms-auth.twoja-subdomena.workers.dev/callback`

Po rejestracji kliknij **„Generate a new client secret"** — zapisz **Client ID**
i **Client Secret** (Secret pokazuje się tylko raz).

### Krok 3 — zmienne Workera na Cloudflare
W panelu Cloudflare → usługa `sveltia-cms-auth` → **Settings → Variables**, dodaj:
- `GITHUB_CLIENT_ID` — Client ID z kroku 2
- `GITHUB_CLIENT_SECRET` — Client Secret z kroku 2 (zaznacz „Encrypt")
- `ALLOWED_DOMAINS` — `ecopowerpolska.github.io` (dziś); po przełączeniu domeny
  dopisz też `ecopowerpolska.pl` jako listę: `ecopowerpolska.github.io, ecopowerpolska.pl`

Zapisz i wdróż.

### Krok 4 — dwie linijki w `public/admin/config.yml`
Ten plik jest w zakresie sesji, która pisała ten dokument — poprosisz o wpięcie
gotowego adresu Workera (odkomentowanie linii `base_url` przygotowanej w pliku)
zamiast robić to samodzielnie.

Po tych czterech krokach na ekranie logowania panelu pojawi się (obok tokenu)
działający przycisk „Zaloguj się przez GitHub" — logowanie jednym kliknięciem,
bez wklejania czegokolwiek.

---

## Bezpieczeństwo — przypomnienie

- Token nie trafia nigdy do repozytorium, do żadnego pliku ani do rozmowy z Claude —
  to repozytorium jest **publikowane publicznie** (GitHub Pages), więc każdy plik
  w nim jest jawny.
- `Client Secret` z kroku 2 w sekcji 7 to również sekret — trafia wyłącznie do
  zmiennych Workera na Cloudflare (zaszyfrowanych), nigdzie indziej.
