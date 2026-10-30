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

> 2026-10-30: wiersz „Forma prawna” (z guzikiem kopiowania) usunięty ze stopki na polecenie Piotra; pole `formaPrawna` z `firma.json` i panelu skasowane. Pozostałe guziki (nazwa spółki, KRS, NIP, REGON, www, e-mail, adres, telefon, zbiorczy) bez zmian; D6 (wiersz rejestrowy bez przecinków) bez zmian.

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

⛔ **UNIEWAŻNIONA 2026-09-07 — następca: D11.** Piotr dostarczył gotowe logo firmowe i ono
zastąpiło wariant A. Rysunek w kodzie i `public/favicon.svg` zostały usunięte. Zapis niżej
zostaje jako ślad, CO rozważano i dlaczego — nie opisuje już stanu strony.

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

**Co z tego przeżyło do D11:** wymaganie, żeby znak czytał się przy 16 px, i ustalenie, że
ikona zakładki potrzebuje TŁA — bez kafla grafitowy znak ginie na ciemnym pasku kart. Oba
warunki spełnia dzisiejsze logo (kafel w kolorze tła strony, 32 px odrysowane osobno).

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

⛔ **UNIEWAŻNIONA 2026-10-30.** Piotr: „Usuń też ten tekst […] Spółka z ograniczoną odpowiedzialnością z siedzibą w Warszawie, wpisana do Rejestru Przedsiębiorców KRS. Pełne dane rejestrowe są w stopce.” Sekcja `#o-spolce`, pola `zdanieKoncowe` i `etykietaSekcjiZamykajacej` usunięte z kodu, `strona.json` i panelu.

Pod listą serwisów stoi krótkie domknięcie treści, **edytowalne w panelu**
(Teksty strony → Strona główna → „Zdanie pod kafelkami”). Puste pole = sekcja
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

---

## D11 — Logo firmowe zastępuje rysowany znak (2026-09-07)

**Piotr dostarczył gotowe logo** (`~/Obrazy/ecopower-polska-logo.png`) i rozstrzygnął dwie rzeczy:

- **w pasku strony stoi PEŁNE logo** — kwadrat „EP" razem z napisem ECOPOWER POLSKA;
- **ikoną zakładki w przeglądarce jest SAM KWADRAT** z literami EP.

To **zamyka D5** („znak firmowy: wybór otwarty") i **unieważnia wariant A »E rejestrowe«**,
rysowany dotąd czterema prostokątami w `PasekMarki.astro`. Rysunek i `public/favicon.svg`
zostały usunięte — są w historii gita, gdyby kiedyś wróciły.

**Co się przez to zmienia w zasadach:** D2 mówiło „sam znak, BEZ nazwy — nazwa jest w H1 niżej".
Logo niesie nazwę w sobie, więc napis pojawia się teraz dwa razy: w pasku (jako część znaku)
i w H1. **To jest świadome** — tak wygląda każde logo z sygnaturą słowną. Dla czytnika ekranu
logo ma `alt` równy nazwie skróconej, dokładnie jak poprzedni znak.

**Dlaczego raster, nie wektor.** Piotr dał PNG; wersji wektorowej nie ma nigdzie na dysku
(`find` po `Obrazy`, `Pobrane`, `Dokumenty`, `Pulpit`), a w systemie nie ma `potrace` ani
`autotrace`, więc obrysu z bitmapy nie da się zrobić wiarygodnie. Inkscape 1.2.2 nie wystawia
obrysu w wierszu poleceń. Źródło ma 3142 × 560 px, a `<Image>` wydaje z niego WebP w potrzebnych
szerokościach — przy wyświetlanych 180 px zapasu starcza na każdy ekran.
🟡 **Gdyby Piotr znalazł plik `.ai`, `.eps`, `.pdf` albo `.svg` z tym logo — warto podmienić:**
wektor byłby ostrzejszy w druku i lżejszy o rząd wielkości.

**Ikona zakładki na jasnym kaflu, nie na przezroczystości.** Kwadrat EP jest grafitowy; na
przezroczystym tle zniknąłby w ciemnym motywie przeglądarki. Kafel ma kolor tła strony
(`#f4f5f1`), więc ikona wygląda tak samo jak logo na stronie. Dwa rozmiary: 512 px i **osobno
odrysowane 32 px** — cienkie kreski ramki rozmywają się przy zmniejszaniu z 512, a to jest
rozmiar, w którym zakładkę widać naprawdę.

**Kolor.** Tusz logo to `#424F54` (chłodny grafit) i **różni się** od tekstu strony (`#15201a`)
oraz od akcentu (`#166653`, zielony). Logo jest więc odrobinę chłodniejsze niż napisy obok.
Zostawione bez przemalowania: to znak firmowy, a nie element motywu — przemalowanie logo pod
paletę strony byłoby zmianą marki, nie zmianą strony.

**Odstępstwo walidatora, świadome.** `AG034 [INFO]` zgłasza, że logo ma `loading="eager"`
bez `fetchpriority="high"`. Tak ma być: `fetchpriority` należy do zdjęcia pierwszego kafelka,
czyli realnego kandydata na LCP (`CLAUDE.md` §4 — dokładnie jeden obraz na stronę).

---

## D12 — Zakładka „Cena”: cennik orientacyjny EcoPower Polska (2026-10-27)

**Słowa Piotra (skrót):** „Dodaj na stronie EcoPower Polska zakładkę cena (...) średnie ceny warszawskie za wykonanie
strony www i za wykonanie sklepu.” Po pierwszej wersji (raport rynkowy ze źródłami, rano 2026-10-27) Piotr poprawił:
„Ty masz przygotować cennik dla mnie, który będę miał ja jako strona EcoPower (...) wywal widełki i zostaw tylko ten środek,
ale środek ma się nazywać cena. Źródła też wywal.”

**Co stoi:** podstrona `/cena/` (`src/pages/cena.astro`, treść `src/data/cena.json`, panel: Teksty strony → „Cennik (/cena)”)
z H1 „Cennik stron i sklepów internetowych” i czterema tabelami — strona: wykonanie i prowadzenie, sklep: uruchomienie
i prowadzenie. Kolumny: **Półka** (nazwa i opis) · **Cena** (kwota, pod nią jednostka). Liczby to dawne „środki” z badania
rynku; zmienił się ich status — z „średniej rynkowej” na „naszą cenę orientacyjną”. Pozycja „Cena” w menu górnym i w stopce,
między Realizacjami a FAQ.

**Zasady, które zostają:**
- ramka mówi: ceny netto, do faktury 23 % VAT, cennik aktualny na dany miesiąc; na stronie NIE ma źródeł, widełek, median
  ani słów o rynku — źródła i metoda są we wpisie na blogu `/blog/ile-kosztuje-strona-i-sklep-internetowy/`;
- wiersze sklepu tylko dla dróg, które umiemy (WooCommerce, PrestaShop, platforma abonamentowa — wdrożenie, sklep na stronie
  Astro z koszykiem i płatnością online); bez sklepu dedykowanego, opieki z SLA i pakietu prawnego;
- kwoty w panelu są TEKSTEM — dowolny wpis buduje się poprawnie (CMS-ZASADY-PRACY ③); puste pole ceny = „wycena indywidualna”;
- tabele to prawdziwe `<table>` z `<th scope>`; poniżej 40 rem wiersz staje się kartą (role ARIA trzymają semantykę), bez przewijania w bok;
- menu ma sześć pozycji; na ekranie ≤ 26 rem mniejszy odstęp między pozycjami i węższy margines paska, żeby menu zostało w jednym wierszu przy 390 px.

**Zmiana cen** jest decyzją Piotra — liczby wpisuje w panelu, a miesiąc aktualności w ramce („Zastrzeżenie”).

---

## D13 — Cennik zamiast raportu, PageSpeed pod Realizacjami, strona przykładowa (2026-10-27)

**Słowa Piotra (skrót):** „Na stronie EcoPower w zakładce realizacje pod każdą stroną musimy podać informacje na temat
wydajności (...) na komórkę, na stacjonarny” oraz „30 pomiar uruchomię ja, ale ty musisz to wykonać na jakiejś stronie przykładowej.”

- **Cennik zamiast raportu.** `/cena/` jest cennikiem EcoPower (D12 w brzmieniu po poprawce); raport rynkowy ze źródłami
  przeszedł do wpisu na blogu.
- **Tabele PageSpeed pod kafelkami Realizacji.** Pod każdym kafelkiem tabela semantyczna 5 wierszy (Wydajność · Ułatwienia
  dostępu · Sprawdzone metody · SEO · Przeglądanie agentowe) × 2 kolumny (Telefon · Komputer); liczby w pastylkach w kolorze
  akcentu (`#f4f5f1` na `#166653`, 6,27:1), wynik poniżej 90 ma sam obrys. Układ odwrócony wobec „dwóch wierszy po 5”, bo
  w karcie 340–390 px pięć pełnych polskich nazw nie mieści się w kolumnach. Pod tabelą data pomiaru, zdanie o braku danych
  CrUX i link „Sprawdź sam” do pagespeed.web.dev. Dane: `scripts/psi-realizacje.py` → `src/data/pomiary.json` (poza panelem,
  `docs/POMIARY.md`). Link kafelka jest wewnątrz karty, tabela poza nim (link w linku jest niedozwolony).
- Efekt przewijania (dawniej `/przyklady/scrollytelling/`) stoi od 2026-10-27 pod `/efekty/animacja-przewijaniem/`, w sitemapie i Google. Stary adres przekierowuje tam meta refresh. `/efekty/` to siatka kart »Wybierz efekt«: kolumny stałej szerokości wyśrodkowane, jedna karta na środku.

## D14 — FAQ akordeonem, skrót nazwy w stopce, guzik 24 px, okruszki w jednej linii (2026-10-27)

Słowa Piotra 2026-10-27 13:50/13:58 (FAQ: „ma się otwierać to jedno i wyłącznie jedno pytanie”, blok wyśrodkowany, bez
przewijania w dół; stopki: „EcoPower Polska Sp. z o.o.”; guzik: „jeżeli jest wymagane 24, to zrób 24”).
- **`/faq/`** — każde pytanie to `<details name="faq" id="<kotwica>">` z H2 w `<summary>`: natywny akordeon wyłączny,
  bez spisu treści. Kolumna 42 rem wyśrodkowana jak wpis bloga, H1 i lead wyśrodkowane. Adres z `#kotwica` otwiera to
  jedno pytanie (skrypt inline, `load` + `hashchange`); `scroll-margin-top` trzyma pytanie pod przyklejonym paskiem.
  Plus/minus jak FAQ satelit, otwarte = sam minus. Bez `data-ujawnij` na pytaniach. Nadal bez FAQPage w JSON-LD (A10).
- **Stopka** — nazwa i guzik „skopiuj” biorą pole `nazwaStopka` z `firma.json` („EcoPower Polska Sp. z o.o.”, panel:
  Dane rejestrowe → Nazwa w stopce; puste = nazwa pełna). `legalName` w JSON-LD i `llms.txt` zostają pełne.
- **Guzik „kopiuj”** — 24×24 px (WCAG 2.5.8), ujemne marginesy pionowe, wiersz nie rośnie; ikona bez zmian.
- **Okruszki** (`/faq/`, `/cena/`, `/blog/<wpis>/`) — jedna linia na każdej szerokości: ostatnia pozycja dostaje resztę
  wiersza (`flex: 1 1 0`, `contain: inline-size`) i skraca się wielokropkiem. Przyczyna przerwy: `flex-wrap` + `min-height:
  44px` na każdej pozycji (zawinięty tytuł stał 44 px niżej). `/przyklady/scrollytelling/` nietknięte.
- **Bez sierot** — Realizacje i „Czym się zajmujemy” na siatce 6 ścieżek: przy 3 kolumnach i reszcie 1 cztery ostatnie
  kafle stają parami na środku (3 + 2 + 2), przy 2 kolumnach i reszcie 1 trzy ostatnie w jednym rzędzie.
- **Cennik** — tabela do 56 rem (kwota w zasięgu wzroku na 1440 px). **Krój zapasowy nagłówków** liczony względem
  Times New Roman (`fallbacks: ['serif']`), nie Ariala.
- **D15 (2026-10-27)** — `/jak-sprawdzic-szybkosc-strony/`: poradnik + formularz GET do oficjalnego raportu pagespeed.web.dev (działa bez JS); wynik na miejscu z PSI API tylko z kluczem przeglądarkowym `PUBLIC_PSI_KLUCZ` (bez klucza Google daje limit 0, 429); w menu NIE (7. pozycja łamie pasek na 390 px na 2 rzędy), link w stopce, FAQ i llms.txt.

## D16–D18 (2026-10-27, runda 4)

**D16 — Menu: 8 pozycji, hamburger na telefonie i tablecie (2026-10-27).** Pozycje: Oferta · Realizacje · Efekty · Cena · FAQ · Blog · Test szybkości · Kontakt. Od 53 rem szerokości paska (zapytanie kontenera, próg przesuwa się z powiększeniem pisma) wszystkie stoją w jednej linii przyklejonego paska 69 px. Poniżej przycisk „Menu” otwiera panel na resztę ekranu z pozycjami po 48 px; treść i stopka dostają `inert`, Esc zamyka i oddaje fokus przełącznikowi; bez JS menu zawija się pod logo. Próg zmierzony CDP: 818 px treści paska.

**D17 — Blok PageSpeed jako osobna karta „oficjalne narzędzie Google” (2026-10-27).** Wyniki pod kafelkiem Realizacji stoją we własnej karcie: cienki obrys, tło o stopień jaśniejsze od kafelka, nagłówek „Oficjalny pomiar Google PageSpeed Insights” z własną ikoną prędkościomierza (nie logo Google), zdanie, że pomiar robi narzędzie Google, i dwa linki: „Sprawdź sam” (nowa karta) oraz „Jak czytać wynik →”. Strony klientów i PageSpeed otwierają się w nowej karcie z dopiskiem dla czytnika ekranu (Piotr 17:45). W wąskiej karcie blok się zwęża (kontener kafelka ≤ 20 rem).

**D18 — Kontakt i kalkulator (2026-10-27).** `/kontakt/` to formularz z żetonami zakresu i technologii sklepu, kartami wycen z kalkulatora (z „Usuń”) i Cloudflare Turnstile; handler PHP na lh.pl (`https://pan-stolarz.pl/formularz/ecopower/wyslij.php`), bo GitHub Pages nie ma PHP. Kalkulator na `/cena/#kalkulator` liczy wyłącznie z kwot cennika (`cena.json`), netto i brutto, a „Dodaj do formularza” przenosi wyliczenie do `/kontakt/` przez pamięć sesji.

## D19 — Strony usług, klikalne kafelki oferty, „Usługi” w stopce (2026-10-27, runda 5)

**D19 — Cztery strony usług jednym szkieletem (2026-10-27).** `/strony-internetowe-warszawa/`, `/sklepy-internetowe-warszawa/`, `/strona-wizytowka/`, `/prowadzenie-strony-internetowej/` mają ten sam układ (klasy `.usluga__*`): okruszki → H1 + wstęp z faktem → „Co dostajesz” (lista ze znacznikiem ✓) → H2 na pytania ludzi z planu SEO (akapity, lista, tabela; tabela na telefonie = karty) → „Przykłady” (zrzut realizacji, wynik PageSpeed z `pomiary.json`) → „Jak pracujemy” (kroki) → film (tylko gdy pole `wideo` niepuste; fasada bez JS) → pytania w `<details>` (bez FAQPage) → „Ile to kosztuje” (kwota „od” z `cena.json` kodem, przyciski: kalkulator i kontakt) → „Zobacz też”. Treść w `src/data/uslugi/<trasa>.json`, panel: kolekcja „Usługi”. 0 kB własnego JS; menu bez zmian (8 pozycji). Kafelki „Czym się zajmujemy” na stronie głównej są w całości odnośnikami do pasującej strony usługi (odnośnik w `<h3>`, `::after` na cały kafelek; w spoczynku wygląd bez zmian, najechanie podkreśla nazwę, fokus = obrys 2 px w kolorze akcentu). Stopka ma listę „Usługi” z 4 linkami (cele 44 px). Linki w akapitach, odpowiedziach FAQ, cenniku i pod Realizacjami są **podkreślone** (Lighthouse `link-in-text-block`).

## D20 — Polityka prywatności (2026-10-27, runda 6)

**D20 — `/polityka-prywatnosci/` wg wzoru pan-stolarz.pl (ta sama spółka).** Decyzja Piotra (3) z 22:05: polityka na ecopowerpolska.pl jak na pan-stolarz.pl. Układ jak `/jak-sprawdzic-szybkosc-strony/`: okruszki w jednej linii → H1 + wstęp → kolumna 44 rem z sekcjami H2 (akapity, lista, akapity pod listą, przycisk e-mail w sekcji o prawach). Treść opisuje TĘ witrynę: GitHub Pages (logi IP GitHub), handler formularza na lh.pl pod `pan-stolarz.pl/formularz/ecopower/`, Cloudflare Turnstile tylko na `/kontakt/`, `sessionStorage` `ep.wyceny` i `ep.szkic`, test szybkości = przeglądarka otwiera pagespeed.web.dev, brak ciasteczek, statystyk i paska zgody. Bez sekcji GA4 i tłumacza (tu ich nie ma). Treść w `src/data/polityka.json` (panel: kolekcja „Polityka prywatności”), wstawki `{nazwa}`…`{telefon}` z `firma.json`, `[tekst](/trasa/)` = link. Indeksowana, JSON-LD WebPage + BreadcrumbList, 0 kB JS. Linki: menu stopki (pole `linkPolityki` w `stopka.json`), zdanie zgody pod formularzem (`kontakt.json`, link podkreślony), FAQ `#rodo`, lista „Co dostajesz” na `/strona-wizytowka/`, `llms.txt`. Przy zmianie formularza, hostingu albo usług zewnętrznych poprawia się politykę i datę w sekcji „Zmiany polityki”.

## D21 — Budżet JS 6 kB dla testu szybkości (2026-10-29)

> Pomiar 2026-10-30 (po rundzie 7, okno zgody + tłumacz na każdej trasie): trasa testu **5,8 kB** JS gzip (limit 6), `/kontakt/` 3,1 kB, `/cena/` 3,0 kB, `/efekty/animacja-przewijaniem/` 2,0 kB, pozostałe ≤ 1,8 kB. Limit zostaje 6; zapas testu ok. 0,2 kB.

**D21 — `guard.config.json` `jsGzipKB`: 3 → 6.** Powód: `/jak-sprawdzic-szybkosc-strony/` dostała zegary wg wzorca Piotra (wskazówka, animacja pomiaru i dojazdu) oraz sekcję „Co poprawić na tej stronie” z modułem `src/lib/psi-zalecenia.ts` (progi Google i audyty Lighthouse). Wersja z zegarami v1 (2026-10-29, 23:0x) zajmowała cały dawny budżet: 3,0 kB. Podziałka zegara powstaje w buildzie (trzy ścieżki SVG), więc nie kosztuje JS; rośnie tylko logika zaleceń. Zmierzone `npm run guard:build` 2026-10-29: trasa testu **5,1 kB** JS gzip (CSS 9,7 kB), pozostałe najcięższe: `/cena/` 2,4 kB, `/kontakt/` 2,1 kB, reszta ≤ 1,5 kB — czyli poza tą jedną trasą budżet 3 kB nadal obowiązuje w praktyce, a zapas trasy testu to 0,9 kB. Zegary na telefonie stoją obok siebie, a sekcja testu ma pełną szerokość kontenera (`--szerokosc-tresci`), nie 44 rem; poradnik pod spodem zostaje w kolumnie 44 rem.

## D22 — Treść wyśrodkowana na każdej podstronie (2026-10-30)

> Uzupełnienie 2026-10-30 (runda 7, D23): D22 dotyczy H1, leadu i nagłówków nad siatkami; kolumny tekstu 42/44/46 rem dla list, siatek, akordeonów i tabel są UCHYLONE — patrz D23.

Słowa Piotra 2026-10-30 (gniew): „A dlaczego wszystkie te teksty są do lewej strony? Dlaczego to nie jest wypośrodkowane? Strona, która ma robić strony internetowe, ma spieprzony najważniejszy parametr, wypośrodkowanie materiałów na stronie. I to prawie na każdej podstronie.” — „Ma to być naprawione.” Uchyla układ lewostronny z przebudowy 2026-09-06 (to była decyzja sesji, nie Piotra); D14 (FAQ i wpisy wyśrodkowane) bez zmian, reguła obejmuje teraz całą witrynę.
- **Wąska kolumna tekstu** (44 rem, na wpisach i FAQ 42 rem, na stronach usług 46 rem) stoi na środku kontenera (`margin-inline: auto`); akapity, listy i tabele w kolumnie zostają do lewej (czytelność). Okruszki stoją w tej samej kolumnie.
- **H1 i wstęp** każdej podstrony są wyśrodkowane (`text-align: center`), także H1 wpisu bloga, jego data i pierwszy akapit (wstęp), na stronie głównej hero z przyciskiem.
- **Nagłówki sekcji H2 nad siatkami** (oferta, realizacje, ostatnie wpisy, FAQ-skrót, cena, kalkulator, przykłady na stronach usług) i ich wstępy są wyśrodkowane. H2 nad zwykłym tekstem w kolumnie stoi razem z tekstem.
- **Siatki kart i ramki** (oferta, realizacje, efekty, cennik, kalkulator, formularz, test szybkości, poradnik w dwóch kolumnach) zajmują całą szerokość kontenera; gdy mają mniej kolumn niż miejsca, stoją na środku (`justify-content: center`, samotny ostatni wpis bloga na środku rzędu).
- **Nagłówek witryny (logo + menu) i stopka bez zmian.**

## D23 — Runda 7 (2026-10-30): pełna szerokość każdej sekcji, porządek podstron

Słowa Piotra (skrót): „masz zrobić wszystkie strony podstrony na szerokość, tak żeby to było ładne, transparentne. […] Masz skasować z pamięci te zapisy, które ci mówiły, że strony nie mogą być na pełną szerokość strony. To nie były moje zalecenia. […] H2 czasami pasuje na środku, czasami pasuje do lewej. […] Mamy zagospodarować całą stronę. Im mniej przewijania dla klienta, tym więcej zobaczy.”

- **Szerokość:** każda sekcja na komputerze zajmuje cały kontener `--szerokosc-tresci` (76 rem). Wąska kolumna tekstu tylko jako układ wielokolumnowy albo tekst obok obrazu/paska, nie pusta prawa połowa. Telefon: jedna kolumna, bez przewijania poziomego (zmierzone na 10 trasach).
- **H2 wg kontekstu:** na środku, gdy otwiera siatkę lub kafelki; do lewej, gdy stoi nad akapitem w kolumnie. H1 i lead zawsze na środku (D22).
- **Blog:** lista 3 / 2 / 1 kolumn (karty 3:2); wpis z paskiem bocznym 24 rem (spis sekcji `sticky`, wezwanie do kontaktu i kalkulatora); „Czytaj także” 3 karty; tabele Markdown przewijane w poziomie wewnątrz tabeli. Każdy wpis ma inną datę (dzień i godzinę), żaden w przyszłości; codzienny cron 07:00 UTC w Actions publikuje wpisy, których data minęła.
- **FAQ:** lista pełnej szerokości, odpowiedź > 450 znaków w dwóch kolumnach od 64 rem.
- **Cennik:** tabele w 4 kolumnach (Pozycja · Zakres · Cena · Rozliczenie), na telefonie karty. Ceny (jedno źródło `cena.json`): wpis na blogu **450 zł**, prowadzenie bloga 4 wpisy **1 600 zł/mies.** (= 4 × 400 zł, rabat 11 %), krótki wpis bez publikacji **150 zł**; integracje rozbite: płatności **1 200 zł**, Allegro **2 500 zł**, hurtownia **3 500 zł**; każda liczona w kalkulatorze po swojej cenie.
- **Realizacje:** kafelki po 3 w rzędzie, niepełny rząd do lewej; „Co zrobiliśmy” (lista punktów) i „Szybkość i optymalizacja” (wynik PageSpeed) rozwijają się `<details>` bez JS, tylko w swoim kafelku.
- **Efekty:** tytuł „Efekty wizualne możliwe do zaimplementowania na stronę”, kafelki 3 / 2 / 1 otwierają podstrony z działającym pokazem (8 efektów, 3 nowe); nowy efekt = wpis w `efekty.json` + plik na szkielecie `SzkieletEfektu`.
- **Strona główna:** „Jak pracujemy” i „Co dostajesz” jako kafelki; „Dlaczego Astro i jaki hosting” w dwóch kolumnach „Proponujemy” / „Wykonamy też” (WordPress, WooCommerce, PrestaShop, Shoper, hosting klienta); usunięty tekst o spółce (D9).
- **Stopka:** „Usługi” jako nagłówek grupy (nie link) z kreską; brak formy prawnej; dwa wiersze menu (strony, potem Regulamin · Polityka prywatności · Ustawienia cookie); przełącznik języka GTranslate; wiersz „© rok EcoPower Polska Sp. z o.o. Wszelkie prawa zastrzeżone.”.
- **Zgoda na cookie:** dolny pasek, dwa równe przyciski („Tylko niezbędne”, „Zgadzam się na wszystkie”), wybór w `localStorage['ep.zgoda']`; podstawa: art. 399 ust. 1 i 3 oraz art. 400 PKE (nie 398, który dotyczy marketingu).
- **Nowe strony:** `/regulamin/` (usługi elektroniczne serwisu), `/darmowe-narzedzia/` (test szybkości działa, generatory regulaminu i polityki „wkrótce”), dwa wpisy (Polityka prywatności 2027, Prawne aspekty strony).
