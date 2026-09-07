# Decyzje wizualne — ecopowerpolska.pl

Rozstrzygnięcia Piotra dotyczące wyglądu tej strony. **Dokument istnieje po to, żeby żadna
kolejna sesja nie zmieniła tych rzeczy „bo tak wypada".** Zmiana czegokolwiek z tej listy
wymaga nowej decyzji Piotra i dopisania jej tutaj — nie wystarczy komentarz w kodzie.

---

## D1 — Motyw jest jeden i jest jasny (2026-09-07)

**Rozstrzygnięcie:** strona ma wyglądać tak samo u każdego. Żadnego ciemnego wariantu,
żadnego podążania za `prefers-color-scheme`.

**Dlaczego to tu stoi:** ciemny motyw pojawił się na stronie 2026-09-06 w commicie `e7c0d84`,
dołożony przez sesję AI przy przebudowie kierunku wizualnego — **bez decyzji właściciela
i bez zapisu**. Przed tym commitem strona deklarowała `color-scheme: light` i była wyłącznie
jasna. Piotr zobaczył ciemne tło, zapytał kto tak zdecydował, i przywrócił stan jasny.

**Co zostało zrobione:** usunięty blok `@media (prefers-color-scheme: dark)` z `motyw.css`
razem z jego paletą i policzonymi kontrastami, powrót do `color-scheme: light`, jeden
`<meta name="theme-color">` zamiast dwóch w `Bazowy.astro`.

**Skutek dla dalszej pracy:** nie ma przełącznika motywu i nie ma go być — wymagałby JS-a,
a ten budżet jest zajęty przez guziki kopiowania (D4). Ciemny wariant to nowa decyzja Piotra,
nie „uzupełnienie".

---

## D2 — Nazwa spółki pada raz na ekran (2026-09-07)

**Rozstrzygnięcie:** „EcoPower Polska sp. z o.o." nie powtarza się w tytule, pasku i nagłówku.

**Stan przed:** ten sam napis leciał trzy razy nad foldem — `<title>`, pasek marki, H1 —
a człon „sp. z o.o." (dane rejestrowe) był w każdym z nich.

**Co zostało zrobione:**
- `firma.nazwaKrotka` = **„EcoPower Polska"** (bez formy prawnej). To nazwa wyświetlana:
  H1, tytuł karty, `schema.org` → `name`.
- Forma prawna została **wyłącznie w stopce**, w `firma.nazwaPelna` (brzmienie rejestrowe)
  i w `schema.org` → `legalName`. Tak jest poprawnie: `name` to nazwa, `legalName` to nazwa
  rejestrowa.
- **Pasek marki nie ma napisu** — sam znak. Nazwę niesie H1 tuż pod nim, a przy przewinięciu
  tożsamość trzyma znak.
- Pole „Nazwa skrócona" w panelu treści dostało podpowiedź, żeby nikt nie wkleił tam formy
  prawnej z powrotem.

---

## D3 — Zdanie w nagłówku: „nadzoruje", nie „prowadzi" (2026-09-07)

Brzmienie zatwierdzone przez Piotra — **jedno zdanie, kropka**:

> Spółka nadzoruje kilka niezależnych serwisów internetowych.

Zdanie „Tu znajdziesz je wszystkie w jednym miejscu." zostało **wycięte na polecenie Piotra**
(2026-09-07, ta sama tura co D6). Nie wraca ani do hero, ani do opisu meta.

Opis meta (`index.astro`, stała `opis`) musi zostać zbieżny z hero — inaczej wyszukiwarka
pokazuje inne zdanie, niż widzi człowiek na stronie. To jedyny powód, dla którego oba są
utrzymywane razem.

---

## D4 — Guziki „skopiuj" w stopce, kosztem reguły zero-JS (2026-09-07)

**Rozstrzygnięcie:** każda dana rejestrowa w stopce ma guzik kopiujący ją do schowka, a nad
nimi stoi jeden guzik „Skopiuj dane spółki" kopiujący komplet jako gotowy blok tekstu.

**Warunek postawiony wprost przy zleceniu: UKŁAD WERSÓW ZOSTAJE.** Dane rejestrowe (KRS, NIP,
REGON) stoją dalej w jednym wierszu — guzik jest dopisany w linii przy wartości i nie wolno
używać go jako pretekstu do rozbicia wiersza na listę.

**Cena:** kopiowanie do schowka nie ma odpowiednika w czystym CSS. Do 2026-09-07
`guard.config.json` deklarował **całą** witrynę jako `zeroJsRoutes: ["/**"]`. Piotr regułę
poluzował świadomie: `zeroJsRoutes` puste, w zamian **twardy budżet `jsGzipKB: 3`**.
To nadal zacisk — dzisiejsza stopka mieści się w ułamku progu, a każda biblioteka, framework
albo wyspa `client:*` go przekroczy i zapali B01 jako BŁĄD. Podniesienie tej liczby jest
decyzją Piotra, nie porządkami przy okazji innego zadania.

**Bez JS stopka wygląda dokładnie jak przed zmianą** — guziki wychodzą z `hidden` dopiero,
gdy skrypt wystartuje i przeglądarka ma Clipboard API. Nie ma stanu, w którym na stronie
wisi guzik, który nic nie robi.

**Kopiowana jest wartość dokładnie taka, jak wyświetlona** (łącznie z wersalikami w adresie
e-mail — dane rejestrowe są przepisane ze starej strony znak w znak i strona ich nie poprawia).

**Guzika nie dostaje napis „Rejestr Przedsiębiorców"** — to nazwa rejestru, nie dana, którą
się gdzieś wkleja; w bloku zbiorczym jest.

---

## D5 — Znak firmowy: wybór otwarty (2026-09-07)

Trzy warianty przygotowane i przedstawione Piotrowi (podgląd renderowany w każdej realnej
wielkości: duży, 30 px w pasku, 16 px jako favikona na jasnej i ciemnej karcie):

| | Wariant | Pomysł |
|---|---|---|
| A | **E rejestrowe** | litera E z rejestru: zielony pion = linia marginesu akt, trzy grafitowe ramiona = wiersze wpisu |
| B | **Pieczęć EP** | monogram w ramce, czyta się jak pieczęć na dokumencie rejestrowym |
| C | **Nadzór** | spółka u góry, pod nią trzy serwisy — znak mówi to samo, co zdanie z D3 |

**WYBRANY: A „E rejestrowe”** (decyzja Piotra 2026-09-07, zgodna z rekomendacją — jedyny
wariant, który przy 16 px nie traci ani jednego elementu, i jedyny niosący inicjał firmy
zamiast ogólnego obrazka).

Znak żyje w dwóch miejscach i zmienia się w obu naraz:
- `src/components/PasekMarki.astro` — cztery prostokąty w kodzie, kolory brane z motywu
  (`var(--akcent)` na pion, `var(--tekst)` na ramiona); rysunek w kodzie zamiast pliku,
  bo waży mniej niż zapytanie o obrazek i podąża za paletą;
- `public/favicon.svg` — ten sam rysunek na grafitowym kafelku (bez tła znak ginie na ciemnym
  pasku kart), z zielenią rozjaśnioną do `#5fbf9d`. Plik nie zna zmiennych CSS — przy zmianie
  palety trzeba go poprawić ręcznie.

---

## D6 — Bez przecinków w wierszu rejestrowym (2026-09-07)

Wiersz KRS / NIP / REGON w stopce nie ma przecinków. Granicę między pozycjami niesie
**odstęp** (`.pozycja`, `margin-right`), nie interpunkcja — przecinek stojący między liczbą
a guzikiem kopiowania zaśmiecał wiersz i odklejał się od wartości.

Układ wersów bez zmian (D4): to nadal jeden wiersz, łamiący się na wąskim ekranie
**między** pozycjami, nigdy w środku pary etykieta+wartość.

Przecinek zostaje wyłącznie w **tekście kopiowanym** guzikiem zbiorczym („Rejestr
Przedsiębiorców, KRS …") — tam jest to zdanie do wklejenia w dokument, nie układ na ekranie.

---

## D7 — Pasek u góry: przyklejony (2026-09-07)

Piotr poprosił o „sticky header". **Pasek był już przyklejony** — `position: sticky; top: 0`
stoi w `PasekMarki.astro` od przebudowy 2026-09-06 i działa (zmierzone: przy oknie 420×720
pasek trzyma górną krawędź na 0 przez całe 802 px przewijania). Powód, dla którego tego nie
widać: na szerokim oknie strona z dwoma kafelkami **w ogóle się nie przewija** (1132 px treści
przy 1025 px okna — 107 px zapasu), więc nie ma czego przykleić.

**Czego natomiast brakowało i co zostało dołożone:** `scroll-padding-top: 4.5rem` na `html`.
Bez tego każda kotwica — link „Przejdź do treści" i przyszły spis kategorii `#kat-*` —
przewija cel dokładnie **pod** przyklejony pasek i pierwszy wiersz sekcji jest niewidoczny.
Sprawdzone na kotwicy do dolnego kafelka przy oknie 420×640: przed poprawką cel wchodził pod
pasek, po poprawce stoi 17 px pod jego krawędzią.

Wysokość paska (55 px) i ta wartość są związane — zmiana jednego wymaga zmiany drugiego.

---

## D8 — Serwisy w JEDNYM pliku, kolejność przez przeciąganie (2026-09-07)

**Rozstrzygnięcie Piotra:** kolejność kafelków ustawia się w panelu **przeciąganiem**,
nie wpisywaniem liczb.

**Dlaczego zmienił się sposób trzymania treści:** uchwyt do przeciągania ma w Sveltii
wyłącznie widget `list`, a ten wymaga jednego pliku. Ręcznego sortowania wpisów kolekcji
katalogowej (jeden plik na serwis) Sveltia nie umie. Wybór był twardy: albo jeden plik
i przeciąganie, albo pliki osobno i liczby.

**Co się zmieniło:**
- `src/data/serwisy/*.md` → **`src/data/serwisy.json`** (klucz `serwisy`, tablica wpisów);
- loader `glob()` → **`file()`** z parserem dokładającym `id` (numer pozycji) — loader
  wymaga identyfikatora, a panel takiego pola nie zapisuje;
- **pole `kolejnosc` zniknęło** ze schematu, z panelu i z sortowania: kolejność w pliku
  JEST kolejnością na stronie (sprawdzone: przestawienie wpisu przestawia kafelek);
- sekcje kategorii idą w kolejności pierwszego wystąpienia, nie po najmniejszej liczbie.

**Zabezpieczenie ścieżek obrazków:** parser sprowadza `obrazek` do jednej postaci
(`../assets/serwisy/` + nazwa pliku). Panel zapisuje ścieżkę względnie i liczba `../`
zależy od jego konfiguracji — rozjazd zatrzymywałby build, czyli publikację całej strony.
Sprawdzone: wpis ze ścieżką `../../assets/…` buduje się poprawnie.

⚠️ **Czego nie dało się sprawdzić stąd:** jak Sveltia zapisze plik po pierwszym
przeciągnięciu i po pierwszym dodaniu kafelka z panelu. Pierwszy zapis Piotra jest
pomiarem tej konfiguracji.

---

## D9 — Zdanie zamykające pod kafelkami (2026-09-07)

Pod listą serwisów stoi krótkie domknięcie treści, **edytowalne w panelu**
(Ustawienia → Teksty strony głównej → „Zdanie pod kafelkami”). Puste pole = sekcja
nie renderuje się wcale.

Tekst świadomie NIE powtarza zdania z nagłówka (D3) — powtórzony napis na jednym ekranie
to ten sam błąd, który zdejmowaliśmy z nazwy spółki (D2).

---

## D10 — Pusty opis obrazka nie może zatrzymywać publikacji (2026-09-07)

Schemat wymagał `alt` warunkowo (gdy jest zdjęcie). Panel nie umie wymusić pola zależnie
od innego, więc warunek pilnował **builda**, nie formularza: pierwszy realny zapis Piotra
ze zdjęciem bez opisu (wpis „Pan Wylewka”) zatrzymał publikację CAŁEJ strony — razem
z niezwiązaną edycją danych firmy zrobioną minutę później.

Warunek usunięty. Puste `alt` jest **poprawne**, nie ustępstwem: kafelek niesie nazwę
serwisu jako widoczny tekst, więc zdjęcie jest ozdobne i `alt=""` to właściwy zapis.

**Reguła ogólna:** żadne pole treści nie może być wymagane warunkowo przez schemat, dopóki
panel nie umie tego wymusić. Inaczej zapis klienta zamraża stronę na starej wersji,
a klient nie widzi żadnego komunikatu.
