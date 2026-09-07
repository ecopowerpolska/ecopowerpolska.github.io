# Braki i decyzje do podjęcia — ecopowerpolska.pl

Otwarte pozycje na **2026-09-06** (dokument założony 2026-09-04). Zlecenie mówiło wprost:
*„Piotr poda, NIE WYMYŚLAJ"*. Poniżej jest wszystko, czego nie wymyśliłem — z informacją,
co strona robi w międzyczasie i co dokładnie trzeba zmienić, gdy brak zostanie uzupełniony.

---

## ✅ ZERO — domena przełączona 2026-09-07, strona jest pod www.ecopowerpolska.pl

**Pozycja ZAMKNIĘTA.** Do 2026-09-07 stało tu wyjaśnienie, czemu zmiany robione w panelu nie
pokazują się pod `ecopowerpolska.pl`: domena wskazywała stary WordPress na lh.pl, a nie GitHub
Pages. To już nieaktualne — rekordy adresowe zostały przełączone.

**Co jest teraz:**
- adresem kanonicznym jest **`https://www.ecopowerpolska.pl`**, apex przekierowuje na niego `301`;
- adres podglądu `ecopowerpolska.github.io` **przekierowuje na domenę docelową** — dlatego panel
  treści stoi pod `https://www.ecopowerpolska.pl/admin/`, nie pod starym adresem;
- **strefa DNS została w lh.pl** (serwery nazw bez zmian), zmieniliśmy wyłącznie rekordy adresowe
  strony. **Poczta nietknięta**: `MX`, SPF, DKIM, DMARC, `autoconfig`, `pop3/smtp/imap/mail`
  i `SRV _autodiscover` mają wartości sprzed zmiany;
- rejestratorem domeny jest OVH, ale **strefę prowadzi lh.pl** — rekordy zmienia się w panelu
  lh.pl; edytor strefy w OVH jest dla tej domeny bez znaczenia, dopóki serwery nazw wskazują lh.pl.

**Punkt powrotu i procedura cofnięcia:** `docs/dns-stan-przed.md`.
**Przebieg przełączenia i lista rekordów:** `docs/przelaczenie-domeny.md`.

---

**Pozycje ① i ② są ROZSTRZYGNIĘTE — decyzja Piotra z 2026-09-04, zapis niżej.**
**Pozycja ③ jest ROZSTRZYGNIĘTA — grafiki wgrane 2026-09-04, zapis niżej.**
Strona jest **opublikowana pod docelową domeną `https://www.ecopowerpolska.pl`** (GitHub Pages,
certyfikat wystawiony, apex przekierowuje na wariant z „www"). Żadna z pozostałych pozycji nie
blokuje ani budowy, ani publikacji — to uzupełnienia treści i decyzje wizualne.

---

## ✅ DECYZJA PIOTRA — 2026-09-04

> **Repozytorium PUBLICZNE, nazwa `ecopowerpolska.github.io`.**

To jest to „osobne, wyraźne polecenie Piotra dla konkretnej strony", którego wymaga
`github-pages-wizytowka` §L2 dla wariantu A. **Dotyczy WYŁĄCZNIE tej strony** — domyślnym
wariantem warsztatu zostaje B (repo prywatne), a każda kolejna strona wymaga własnego
rozstrzygnięcia. Konto `ecopowerpolska` zostaje na planie **Free**.

**Co z tego wynika:**
- repozytorium jest witryną użytkownika → **`base` nie występuje**, adres podglądu to
  `https://ecopowerpolska.github.io` (bez podścieżki), a przy podpięciu domeny nie ma
  czego usuwać z konfiguracji;
- kod, `config.yml` panelu i **cała historia commitów są jawne** — nie kładź tu niczego,
  czego nie chcesz pokazać; sekretów w repo nie ma i być nie może (kontrakt §4.4);
- budowania w Actions **bez limitu minut** (repo publiczne), więc przebudowa po każdym
  zapisie w panelu nie zjada puli konta.

Pozycje ① i ② niżej zostają jako **zapis, na czym stanęła decyzja** — nie jako otwarte pytania.

---

## ① ~~BLOKUJĄCE~~ ROZSTRZYGNIĘTE — repozytorium publiczne czy konto na planie Pro?

**Zmierzony stan konta `ecopowerpolska` (API GitHuba, 2026-09-04):**
plan **`free`**, 0 repozytoriów publicznych, 8 prywatnych.

**Z czego wynika problem:**
- GitHub Pages na koncie **Free** publikuje **wyłącznie z repozytorium PUBLICZNEGO**.
- Skill `github-pages-wizytowka` mówił wtedy: **wariant B — repo PRYWATNE, konto na planie Pro**,
  i zakazywał zakładania repozytorium publicznego bez osobnego polecenia Piotra.
  ⚠️ **Tego zapisu w skillu JUŻ NIE MA** — został skasowany 2026-09-07, bo pomiar wyżej mu przeczy;
  skill opisuje dziś wybór publiczne/prywatne zgodnie z planem konta. Zdanie zostaje tutaj
  wyłącznie jako **ślad, wobec czego Piotr wtedy rozstrzygał** — nie szukaj go w skillu.
- Zlecenie przyszło z sesji Potwora i mówi „repo publiczne". **Sesja Potwora nie jest Piotrem**
  i nie może wydać polecenia, które regulamin skilla rezerwuje dla Piotra.
- 🔴 **Zapis w skillu `potwor-cms-strony` §E2 („Konto jest na Pro") jest DZIŚ NIEPRAWDZIWY** —
  pomiar wyżej mu przeczy. Poprawka należy do kanonu w Potworze.

**Dwa wyjścia, rozstrzyga Piotr:**

| | Co trzeba zrobić | Co widać publicznie | Minuty Actions |
|---|---|---|---|
| **A — repo publiczne, konto zostaje Free** | nic poza założeniem repo | kod, `config.yml`, **cała historia commitów** | bez limitu (repo publiczne) |
| **B — repo prywatne, konto na Pro** | wykupić Pro (cennik: `github.com/pricing` — nie przepisuję kwoty, to licznik stanu) | nic; publiczna jest sama strona | wspólna pula konta, dzielona ze wszystkimi repo prywatnymi |

**Moja rekomendacja: A.** Powody: (1) na tej stronie nie ma nic, czego historia commitów
mogłaby zdradzić — dane rejestrowe są jawne w KRS, a jedyna treść to nazwy i adresy serwisów,
które i tak mają być znalezione; (2) repo publiczne zdejmuje zużycie minut Actions z puli konta,
a przebudowa chodzi tu po **każdym** zapisie w panelu; (3) wariant B kosztuje abonament dla
jednej strony-wizytówki. **Jeśli Piotr woli B — jedyna zmiana to plan konta, kod bez zmian.**

---

## ② 🔴 BLOKUJĄCE — nazwa repozytorium

**Rekomendacja: `ecopowerpolska.github.io`** (witryna użytkownika).

| | `ecopowerpolska.github.io` (rekomendacja) | `ecopowerpolska.pl` (repo projektowe) |
|---|---|---|
| adres podglądu | `https://ecopowerpolska.github.io` | `https://ecopowerpolska.github.io/ecopowerpolska.pl` |
| `base` w konfiguracji | **nie występuje** | `base: '/ecopowerpolska.pl'`, i **trzeba je USUNĄĆ** przy podpięciu domeny |
| ryzyko | brak | zostawione `base` łamie linki po cichu — build zielony, nawigacja donikąd |

Zlecenie samo podaje adres podglądu `ecopowerpolska.github.io` — a to jest dokładnie adres
witryny użytkownika. Kod jest napisany pod ten wariant. Wybór drugiego = dopisanie `base`
w `astro.config.mjs` i poprawka pola `repo` w `public/admin/config.yml`.

⚠️ Katalog lokalny zostaje `strony/ecopowerpolska.pl/` niezależnie od nazwy repozytorium —
konwencja warsztatu mówi o **adresie strony**, nie o nazwie repo (`strony/README.md`).

---

## ③ ~~Pliki graficzne kafelków~~ ROZSTRZYGNIĘTE — grafiki wgrane 2026-09-04

**Stan teraz:** `src/assets/serwisy/nadihome.jpg` i `src/assets/serwisy/studioagat.jpg`
istnieją i są w repozytorium. Kafelek NIE pokazuje już pola z inicjałami ani podpisu
„grafika do uzupełnienia" dla tych dwóch serwisów.
**Jak dołożyć kolejny kafelek z obrazkiem (dla przyszłego serwisu):** panel → Serwisy →
wpis → pole „Obrazek kafelka" + „Opis obrazka dla czytnika ekranu". **Żadnej zmiany kodu.**
**Format:** proporcje 16:9, szerokość ≥ 720 px, WebP albo JPEG (zdjęcia); reszta to robota
`<Image>` Astro. Materiał ze starych stron zbiera się narzędziami Potwora — `zbierz-zdjecia-www.py`,
`przemiel-zdjecia.py` (kontrakt warsztatu §5), uruchamia Piotr.

## ④ Logo / znak firmowy — ZAMKNIĘTE 2026-09-07 (logo firmowe Piotra)

Piotr dostarczył gotowe logo i rozstrzygnął: **w pasku strony stoi PEŁNE logo** (kwadrat „EP"
razem z napisem ECOPOWER POLSKA), a **ikoną zakładki jest SAM KWADRAT** z literami EP.
Zapis i uzasadnienie: `docs/DECYZJE-WIZUALNE.md` **D11**.

Znak stoi w trzech plikach:
- `src/assets/logo-ecopower-polska.png` — pełne logo do paska (wydawane przez `<Image>` jako WebP);
- `public/favicon.png` i `public/favicon-32.png` — kwadrat EP na kaflu w kolorze tła strony;
  32 px odrysowane osobno, bo cienkie kreski ramki rozmywają się przy zmniejszaniu z 512.

Rysowany w kodzie wariant A „E rejestrowe" i `public/favicon.svg` **zostały usunięte** —
zastąpiło je logo wyżej. Są w historii gita, gdyby kiedyś wróciły.

## ⑤ Kolory marki

**Co robi strona teraz:** neutralny grafit bez barwy firmowej, kontrasty policzone i zgodne
z WCAG 2.2 (4,5:1 dla tekstu, 3:1 dla elementów nietekstowych).
**Jak uzupełnić:** wartości w bloku `:root` w `src/styles/motyw.css` — **jedyne miejsce**,
w którym stoją kolory. Po podmianie **przelicz kontrasty**; komentarz w pliku podaje, które
pary muszą się zgadzać.

## ⑥ Treść opisowa strony głównej

**Czego nie ma:** żadnego akapitu o firmie. Zlecenie mówi, że strona to kafelki i stopka,
„nic więcej się nie dzieje" — więc **niczego nie dopisałem**.
**Jedyny tekst, który złożyłem sam:** `meta description` (opis dla wyszukiwarki, wymagany —
bramka zgłasza jego brak). Brzmi: *„EcoPower Polska sp. z o.o. — serwisy internetowe spółki
zebrane w jednym miejscu."* Zbudowany wyłącznie ze zdań zlecenia. **Do potwierdzenia albo
podmiany przez Piotra** — `src/pages/index.astro`, stała `opis`.

## ⑦ Adres siedziby — i co przez to tracimy

**Co robi strona teraz:** puste pole `adresSiedziby` nie renderuje się w stopce (brak dziury).
**Co przez to tracimy:** dane strukturalne są typu **`Organization`**, a nie **`LocalBusiness`**.
`LocalBusiness` wymaga `address` — wpisanie zmyślonego adresu przeszłoby walidację i **kłamało**.
To jest realna strata w widoczności lokalnej. **Po podaniu adresu**: uzupełnić pole w panelu
i zmienić typ w `src/components/DaneStrukturalne.astro` (jedna linia + blok `address`).

## ⑧ Telefon

Puste pole `telefon`. Nie renderuje się. Uzupełnienie: panel → **Dane firmy**.

## ⑨ Decyzja o botach AI — podjęta domyślnie, do potwierdzenia

`public/robots.txt` wpuszcza **wszystkie** boty, w tym trenujące (`GPTBot`, `ClaudeBot`,
`PerplexityBot`, `Google-Extended` — tokeny z dokumentacji dostawców, nie z pamięci).
Uzasadnienie stoi w pliku. Bramka przed publikacją sprawdza, **czy decyzja zapadła**, nie ocenia
która — obie odpowiedzi są dopuszczalne, brak decyzji nie jest. **Odwrót = `Disallow: /`
w grupie wybranego tokenu.**

## ⑩ Typografia

Stos systemowy (`system-ui` i zapasy) — zero pobierania, zero blokowania renderu, zero
wycieku IP użytkownika do zewnętrznego serwisu. **Nie jest to wybór kroju firmowego.**
Gdy Piotr wskaże krój: Fonts API Astro w `astro.config.mjs` (`fonts: [...]`) + `<Font>`
w layoucie — **nigdy** `<link>` do Google Fonts ani pakiety `@fontsource*`.

## ⑪ Zestaw referencyjny wyglądu

Dokument kryteriów Potwora ma tu otwartą lukę: brakuje **3–5 stron opisanych cechami**
jako punkt odniesienia dla warstwy wizualnej. Nie wypełniam jej własnym gustem
(`potwor-www` §H). Do wskazania przez Piotra — dotyczy wszystkich stron warsztatu, nie tylko tej.

## ⑫ Napis na kafelku — `nadihome.pl` czy `www.nadihome.pl`?

Zlecenie wymieniło serwisy w formie z `www`. Na kafelkach stoi forma bez `www`, bo tak
brzmi adres kanoniczny (oba serwisy przekierowują `www` → apex, sprawdzone wywołaniem).
**Adres docelowy linku to osobne pole i celuje już w wersję kanoniczną** — tu chodzi wyłącznie
o napis. Zmiana: panel → Serwisy → pole „Nazwa serwisu". Bez zmiany kodu.
