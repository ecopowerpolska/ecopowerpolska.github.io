# Panel treści — logowanie i codzienna praca (instrukcja dla Piotra)

Stan na 2026-09-07. Panel to **Sveltia CMS** — mała aplikacja, która chodzi w Twojej
przeglądarce. Nie ma osobnego serwera ani osobnego hasła: panel zapisuje zmiany prosto
do repozytorium na GitHubie, a strona przebudowuje się sama (sekcja 6).

Ten dokument mówi: gdzie wejść, jak zalogować się **raz**, co robi każda z czterech
pozycji menu, jak dodać grupę i wstawić do niej serwis, i co zrobić, gdy coś nie działa.
Wszystko, co wymaga Twojego kliknięcia w cudzym panelu (GitHub, Cloudflare), zebrane jest
w JEDNEJ liście na samym końcu.

---

## 1. Adres panelu

- **Dziś:** `https://ecopowerpolska.github.io/admin/`
- **Po przełączeniu domeny** (`docs/przelaczenie-domeny.md`, robisz osobno):
  `https://ecopowerpolska.pl/admin/`

Adres wpisujesz w przeglądarce jak każdy inny. Panel jest celowo wyłączony z wyszukiwarek
(`noindex` w kodzie strony panelu i `Disallow: /admin/` w `public/robots.txt`).

---

## 2. Logowanie — jedna droga, token wklejany raz

**Panel ma dokładnie jeden przycisk logowania: „Zaloguj się za pomocą tokenu dostępu".**
Tak jest ustawione świadomie i nie ma tu drugiej drogi do włączania ani żadnych ustawień
do przeklikania gdzie indziej. Token wklejasz **raz na przeglądarkę** — potem panel
otwiera się od razu (sekcja 3 mówi, kiedy trzeba go wkleić ponownie).

> Sveltia umie też logowanie jednym kliknięciem przez GitHub (OAuth), ale wymaga ono
> konta Cloudflare, wdrożenia własnego mikroserwisu i rejestracji aplikacji OAuth —
> czyli dokładnie tych kolejnych progów wejścia do ustawień, których ma nie być.
> Dlatego przycisk OAuth jest w panelu **schowany**, a nie zepsuty. Gdybyś kiedyś chciał
> tę drogę mimo wszystko — kroki są w sekcji 9, punkt B.

**Krok po kroku:**

1. Wejdź na adres panelu (sekcja 1).
2. Kliknij **„Zaloguj się za pomocą tokenu dostępu"** — to jedyny przycisk logowania.
3. W okienku, które się otworzy, kliknij link **„Token możesz wygenerować na stronie
   ustawień użytkownika GitHub"**. Otworzy się karta GitHuba z formularzem
   `Personal access tokens (fine-grained)`, już częściowo wypełnionym przez panel.
4. Na stronie GitHuba ustaw dokładnie tak:
   - **Token name:** `Panel ecopowerpolska.pl` (dowolna rozpoznawalna nazwa).
   - **Resource owner:** `ecopowerpolska`.
   - **Expiration:** `No expiration` (token działa, dopóki go sam nie usuniesz) albo
     konkretna liczba dni, np. `365` — wtedy w tym dniu przestanie działać i wygenerujesz
     nowy tą samą drogą.
   - **Repository access:** zaznacz **`Only select repositories`**, a niżej wybierz
     **`ecopowerpolska.github.io`**. To jest repozytorium tej strony — nazywa się inaczej
     niż domena i po przełączeniu domeny nazwa się NIE zmieni.
   - **Repository permissions → Contents:** ma być **`Read and write`**. Panel wstawia to
     uprawnienie z góry, więc zwykle jest już ustawione — sprawdź, czy nie zniknęło.
     GitHub sam dołoży `Metadata: Read-only` — zostaw. **Innych uprawnień nie zaznaczaj.**
5. Na dole kliknij **`Generate token`**.
6. GitHub pokaże token **tylko raz** — długi ciąg zaczynający się od `github_pat_`.
   Kliknij ikonę kopiowania obok niego.
7. Wróć do karty z panelem, wklej token w pole i kliknij **„Zaloguj się"**.

Po zalogowaniu w lewej kolumnie zobaczysz **cztery pozycje**: **Grupy**,
**Serwisy (kafelki)**, **Teksty strony**, **Dane firmy**.

🔴 **Token jest sekretem — traktuj go jak hasło.** Nie wklejaj go nigdzie poza tym jednym
oknem logowania: nie do maila, nie na czacie, nie do pliku w repozytorium (repozytorium
jest publiczne). Jeśli podejrzewasz, że wyciekł — sekcja 9, punkt C.

---

## 3. Czy token trzeba wklejać za każdym razem?

**Nie.** Po pierwszym wklejeniu panel zapamiętuje token w tej przeglądarce (w jej lokalnej
pamięci, `localStorage`). Kolejne wejścia na adres panelu w tej samej przeglądarce na tym
samym urządzeniu od razu pokazują menu.

Token wklejasz ponownie tylko wtedy, gdy:

- otwierasz panel **w innej przeglądarce** albo **na innym urządzeniu** (telefon, drugi
  komputer) — pierwszy raz na każdym z nich;
- używasz **okna prywatnego / incognito** — ono nic nie pamięta po zamknięciu;
- **wyczyściłeś dane przeglądania** (ciasteczka, dane witryn) w tej przeglądarce;
- token **wygasł** albo **został usunięty** na GitHubie.

W żadnym z tych przypadków nic się nie psuje — powtarzasz sekcję 2.

---

## 4. Cztery pozycje menu — co robi która

| Pozycja | Co w niej ustawiasz | Plik, do którego zapisuje |
|---|---|---|
| **Grupy** | Sekcje, na jakie dzieli się strona główna: klucz, nazwa nagłówka, zdanie pod nagłówkiem. Kolejność na liście = kolejność sekcji na stronie. | `src/data/grupy.json` |
| **Serwisy (kafelki)** | Kafelki na stronie głównej: nazwa, adres, podpis, **grupa (wybierana z listy)**, obrazek, opis obrazka, ukrycie. Kolejność na liście = kolejność kafelków. | `src/data/serwisy.json` |
| **Teksty strony** | Trzy formularze napisów: **Strona główna**, **Stopka**, **Strona błędu 404**. Tytuły kart, opisy dla wyszukiwarek, nagłówki, etykiety, komunikaty. | `src/data/strona.json`, `src/data/stopka.json`, `src/data/strona-404.json` |
| **Dane firmy** | Dane rejestrowe pokazywane w stopce: nazwa pełna i skrócona, rejestr, KRS, NIP, REGON, forma prawna, adres WWW, e-mail, adres siedziby, telefon. | `src/data/firma.json` |

Dwie rzeczy, o które łatwo się potknąć:

- **Podpisy w stopce a dane w stopce to dwa różne miejsca.** Napis `NIP:` czy
  `Forma prawna:` zmieniasz w **Teksty strony → Stopka**; sam numer NIP — w **Dane firmy**.
- **Wyczyszczenie pola w „Teksty strony" nie kasuje napisu ze strony** — przywraca napis
  domyślny wpisany w kodzie (żeby pusty formularz nie zostawił strony bez tytułu).
  Każde pole ma to napisane w podpowiedzi pod sobą, razem z brzmieniem tego domyślnego
  napisu. **Jedyny wyjątek: „Zdanie pod kafelkami"** — tam puste pole naprawdę oznacza
  pusto i cała sekcja pod kafelkami znika ze strony.

---

## 5. Grupy i kafelki

### 5.1 Dodanie grupy

1. Wejdź w **Grupy** → **Grupy serwisów**.
2. Kliknij **„+ Dodaj Grupa"** (przycisk dodawania pod listą).
3. Wypełnij:
   - **Klucz grupy** *(wymagany)* — identyfikator techniczny, małe litery bez polskich
     znaków, cyfry i myślnik, np. `wykonczenia`. Nie widzi go nikt poza Tobą.
     🔴 **Po zapisaniu go nie zmieniaj** — kafelki wskazują grupę właśnie tym napisem;
     po zmianie klucza wypadną z sekcji i trafią na koniec strony.
   - **Nazwa sekcji na stronie** *(wymagana)* — napis nagłówka, np. `Wykończenia wnętrz`.
     To jedyne pole widoczne dla odwiedzającego.
   - **Zdanie pod nagłówkiem sekcji** *(opcjonalne, do 200 znaków)* — puste pole znaczy,
     że pod nagłówkiem nie ma nic; sam nagłówek zostaje.
4. **Kolejność sekcji** ustawiasz przeciąganiem: chwyć grupę za uchwyt po lewej i przesuń.
   Na telefonie służą do tego guziki w górę/w dół.
5. Kliknij **„Save"** w prawym górnym rogu.

### 5.2 Przypisanie serwisu do grupy

1. Wejdź w **Serwisy (kafelki)** → **Kafelki na stronie głównej**.
2. Rozwiń kafelek z listy (albo dodaj nowy przyciskiem dodawania).
3. Pole **„Grupa (sekcja na stronie)"** to **lista do wyboru**, nie pole do wpisania —
   pokazuje nazwy grup z pozycji **Grupy**. Wybierz jedną.
   - Lista jest pusta, dopóki nie dodasz ani jednej grupy (punkt 5.1).
   - Na górze listy jest **pozycja pusta** — wybierz ją, żeby zdjąć kafelkowi grupę.
     Taki kafelek trafia na koniec strony, do sekcji **Inne** (jej nazwę zmienisz
     w **Teksty strony → Strona główna**).
4. Kliknij **„Save"**.

### 5.3 Kiedy sekcje w ogóle się pokazują

- **Mniej niż dwie realnie użyte grupy → strona pokazuje jedną wspólną siatkę kafelków**,
  bez nagłówków sekcji i bez spisu odnośników. Tak wygląda dziś i to jest poprawne.
- **Grupa bez ani jednego widocznego kafelka nie pokazuje się wcale** — ani nagłówek, ani
  odnośnik w spisie. Kafelek zaznaczony jako **Ukryty** nie liczy się jako widoczny.
- Kolejność kafelków **wewnątrz** sekcji to kolejność z listy w **Serwisy (kafelki)**.

### 5.4 Pozostałe pola kafelka

- **Nazwa serwisu** *(wymagana)* — napis na kafelku, np. `nadihome.pl`.
- **Adres docelowy** *(wymagany)* — pełny adres z `https://`. Panel odrzuci adres bez
  `http://`/`https://` albo ze spacją w środku — to jedyne miejsce, gdzie taka literówka
  zostanie złapana, więc czytaj komunikat pod polem.
- **Krótki podpis** *(opcjonalny, do 160 znaków)* — puste pole jest w porządku.
- **Obrazek kafelka** *(opcjonalny)* — proporcje 3:2, szerokość co najmniej 720 px.
- **Opis obrazka dla czytnika ekranu** *(opcjonalny)* — opisz krótko, co widać.
  ⚠️ **Zmiana wobec starszej wersji tej instrukcji: to pole NIE jest już wymagane i brak
  opisu NIE zatrzymuje przebudowy strony.** Wcześniej zatrzymywał — 2026-09-07 zapis bez
  opisu zamroził publikację całej strony, więc reguła została usunięta. Puste pole znaczy
  „zdjęcie ozdobne"; nazwę serwisu i tak widać na kafelku.
- **Ukryty** — kafelek zostaje w panelu, znika ze strony. Do wyłączania serwisu bez
  kasowania wpisu.
- Pola **„Kolejność"** już nie ma. Kolejność ustawia się wyłącznie przeciąganiem.

---

## 6. Co się dzieje po kliknięciu „Save"

1. Panel zapisuje zmianę jako **commit w repozytorium** `ecopowerpolska.github.io`
   (widać go w zakładce **Code → commits**).
2. Ten commit **sam uruchamia przebudowę** — GitHub Actions buduje stronę i wgrywa ją na
   GitHub Pages. Nie trzeba niczego dodatkowo klikać.
3. **Ile to trwa:** przy pomiarze na tej stronie cały przebieg zajął około pół minuty.
   Licz na „poniżej minuty, rzadko kilka minut".
4. **Gdzie sprawdzić:** zakładka **Actions** w repozytorium — najnowszy przebieg
   `build-i-wdrozenie` ma zielony ptaszek albo czerwony X.
5. Po zielonym przebiegu odśwież stronę. Jeśli widzisz starą wersję — twarde odświeżenie
   (Ctrl+Shift+R / Cmd+Shift+R).

---

## 7. GDY NIE DZIAŁA

### 🔴 Trzy pułapki, które naprawdę się zdarzyły (2026-09-07)

**① Po każdej zmianie ustawień panelu odśwież twardo SAM adres `/admin/`.**
Przeglądarka trzyma plik konfiguracji panelu przez dziesięć minut (GitHub Pages podaje go
z `max-age=600`). Panel pracuje wtedy na **starej** konfiguracji i zapisuje w starym
układzie — do plików, których strona już nie czyta. Trzy zapisy przepadły tak jednego dnia.
Twarde odświeżenie (Ctrl+Shift+R) robi się **stojąc na `/admin/`**, nie na stronie
głównej — to inny adres i inny wpis w pamięci przeglądarki.
**Dziś to Ciebie dotyczy:** konfiguracja panelu właśnie się zmieniła (doszła pozycja
**Grupy**, pole **Kategoria** w kafelku zamieniło się na listę **Grupa**), więc pierwsze
wejście do panelu zacznij od Ctrl+Shift+R na `/admin/`.

**② Nikt nie wypycha zmian z terminala, gdy Ty pracujesz w panelu.**
Sveltia zapamiętuje stan gałęzi w chwili wczytania panelu i nie ponawia zapisu, gdy stan
się przesunie — GitHub odrzuca wtedy zapis, a panel **nie pokazuje żadnego błędu**.
Jeśli ktoś wypchnie zmianę z komputera, ma o tym powiedzieć wprost; Ty wtedy odświeżasz
`/admin/` (Ctrl+Shift+R) **przed** kolejnym zapisem. Poznasz to po metadanych commitów:
zapis z panelu ma autora `GitHub` i zweryfikowany podpis, push z komputera — `Piotr
(EcoPower)` bez podpisu.

**③ Walidacja należy do formularza, nie do przebudowy strony.**
Reguły typu „adres musi mieć https://" i „podpis do 160 znaków" siedzą w formularzu panelu
i widzisz je **od razu, pod polem**, zanim cokolwiek zapiszesz. Schemat po stronie budowy
został z nich celowo ogołocony, żeby żaden wpis nie mógł zamrozić publikacji. Wniosek dla
Ciebie: **czerwony komunikat pod polem to jedyne miejsce, gdzie panel Cię ostrzega —
przeczytaj go, zamiast klikać Save drugi raz.**

### Tabela objawów

| Objaw | Najpierw sprawdź | Co zrobić |
|---|---|---|
| **Biały/pusty ekran** zamiast panelu | Konsola przeglądarki (F12 → Console) — czy jest czerwony błąd | Zwykle chwilowy problem z wczytaniem skryptu panelu z unpkg.com — odśwież za minutę. Jeśli nie pomaga, przekaż treść błędu z konsoli. |
| **Błąd po wklejeniu tokenu** | Treść komunikatu w okienku logowania | „Token nieprawidłowy" → wklejony fragment jest niepełny albo token wygasł/został usunięty → wygeneruj nowy (sekcja 2). „Brak dostępu do repozytorium" → przy generowaniu nie zaznaczyłeś `ecopowerpolska.github.io` → wygeneruj token od nowa i tym razem zaznacz. |
| **Menu ma inne pozycje niż cztery z sekcji 4** (np. jest „Ustawienia", a nie ma „Grupy") | Czy zrobiłeś twarde odświeżenie na `/admin/` | To pułapka ① — Ctrl+Shift+R stojąc na `/admin/`. Dopóki menu wygląda staro, **nie zapisuj niczego**: zapis pójdzie w starym układzie. |
| **Pole „Grupa" w kafelku jest puste, nie ma z czego wybierać** | Pozycja **Grupy** — czy jest tam choć jedna grupa | Lista bierze się z pozycji **Grupy**. Dodaj grupę (sekcja 5.1), zapisz, wróć do kafelka. |
| **Zapisałem, a na stronie bez zmian** | Zakładka **Actions** — czy pojawił się nowy przebieg | Brak przebiegu → sprawdź **Code → commits**: jeśli nie ma commita, zapis nie doszedł do GitHuba (pułapka ② albo wygasły token) → odśwież `/admin/` i zapisz ponownie. Przebieg czerwony → wiersz niżej. Przebieg zielony → twarde odświeżenie strony, a potem chwila cierpliwości (pamięć podręczna GitHuba). |
| **Przebieg w Actions czerwony** | Wejdź w przebieg → krok `build`, rozwiń czerwony punkt | Po zmianach z 2026-09-07 żaden wpis z panelu nie powinien już wywracać przebudowy. Jeśli mimo to padła — **to jest błąd do zgłoszenia, nie do naprawiania przez Ciebie**: skopiuj czerwony fragment i przekaż go. Strona zostaje tymczasem na poprzedniej, działającej wersji. |
| **Kafelek stoi w złej sekcji** | Pole **Grupa** w kafelku i **Klucz grupy** w grupie | Kafelek z grupą, której klucz już nie istnieje, trafia do sekcji **Inne** na końcu strony. Zwykle znaczy to, że klucz grupy został zmieniony po zapisaniu (sekcja 5.1). Wybierz grupę w kafelku ponownie. |

Jeśli nic z powyższego nie pasuje — zrzuć ekran z błędem (i z konsoli przeglądarki, jeśli
jest widoczna) i przekaż do analizy. Nic z tego nie wymaga grzebania w kodzie.

---

## 8. Czego panel nie zmienia

Panel edytuje **treść**: kafelki, grupy, napisy, dane firmy. Nie zmienia wyglądu (kolory,
układ, czcionki), nie dodaje nowych podstron ani zdjęć poza obrazkami kafelków. To wchodzi
przez repozytorium — czyli przez pracę na komputerze, nie w przeglądarce.

---

## 9. Lista rzeczy do kliknięcia w cudzym panelu

Wszystko, co wymaga Twojej ręki poza panelem treści. Punkt A jest jedynym potrzebnym do
codziennej pracy.

### A. GitHub — wygenerowanie tokenu (potrzebne raz na przeglądarkę)

Adres: link „Token możesz wygenerować…" w oknie logowania panelu (prowadzi na
`github.com/settings/personal-access-tokens/new`).

| Pole | Wartość |
|---|---|
| Token name | `Panel ecopowerpolska.pl` |
| Resource owner | `ecopowerpolska` |
| Expiration | `No expiration` (albo `365 days`) |
| Repository access | `Only select repositories` → `ecopowerpolska.github.io` |
| Repository permissions → Contents | `Read and write` |
| Repository permissions → Metadata | `Read-only` (GitHub dokłada sam — zostaw) |
| pozostałe uprawnienia | nie zaznaczaj żadnego |

Na końcu: **`Generate token`** → skopiuj ciąg `github_pat_…` → wklej w oknie panelu.

### B. Cloudflare + GitHub — logowanie bez tokenu (OPCJONALNE, dziś niepotrzebne)

Robi się to **tylko wtedy**, gdy z panelu ma korzystać ktoś inny niż Ty. Dla jednej osoby
token z punktu A jest prostszy i tak samo bezpieczny. Nikt tych kroków nie wykonał.

1. **Cloudflare Workers** — załóż konto na `cloudflare.com` i wdróż projekt
   `sveltia-cms-auth` (przycisk „Deploy to Cloudflare Workers" na
   `github.com/sveltia/sveltia-cms-auth`). Zapisz adres Workera:
   `https://sveltia-cms-auth.<TWOJA-SUBDOMENA>.workers.dev`.
2. **Aplikacja OAuth na GitHubie** — `github.com/settings/applications/new`:
   - Application name: `Sveltia CMS Authenticator`
   - Homepage URL: `https://github.com/sveltia/sveltia-cms-auth`
   - Authorization callback URL: `<adres Workera>/callback`
   Po rejestracji: **„Generate a new client secret"** — zapisz **Client ID** i **Client
   Secret** (Secret pokazuje się raz).
3. **Zmienne Workera** (Cloudflare → usługa `sveltia-cms-auth` → Settings → Variables):
   - `GITHUB_CLIENT_ID` — Client ID z kroku 2
   - `GITHUB_CLIENT_SECRET` — Client Secret z kroku 2, zaznacz **Encrypt**
   - `ALLOWED_DOMAINS` — `ecopowerpolska.github.io` (po przełączeniu domeny:
     `ecopowerpolska.github.io, ecopowerpolska.pl`)
   Zapisz i wdróż.
4. **Dwie linijki w `public/admin/config.yml`** — odkomentowanie `base_url` z gotowym
   adresem Workera i zmiana `auth_methods` na `[token, oauth]`. Tego nie robisz sam:
   podajesz adres Workera i prosisz o wpięcie.

`Client Secret` z kroku 2 to sekret — trafia wyłącznie do zaszyfrowanych zmiennych
Workera, nigdzie indziej.

### C. GitHub — unieważnienie tokenu (gdy podejrzewasz wyciek)

`Settings → Developer settings → Personal access tokens → Fine-grained tokens` → znajdź
token po nazwie → **`Delete`**. Potem wygeneruj nowy według punktu A.

---

## Bezpieczeństwo — przypomnienie

- Token nie trafia nigdy do repozytorium, do żadnego pliku ani do rozmowy z Claude.
  Repozytorium tej strony jest **publiczne** (GitHub Pages), więc każdy plik w nim jest jawny.
- Sekrety w tym warsztacie mają jedno miejsce: `~/.sekrety/` na komputerze Z440. W plikach
  projektu wolno wskazać lokalizację, nigdy wartość.
