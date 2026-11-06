# SEO — mapa zapytań, briefy i decyzje (ecopowerpolska.pl)

**Rodzaj:** dokument roboczy strony. Plan SEO całej witryny: `/home/ecopower/projekty/WWW/analizy/seo-ecopowerpolska-2026-10-27.md`
(rozdział fraz: usługi = „tworzenie”, `/cena/` = „cennik”, wpisy = pytania informacyjne). Ten plik prowadzi mapę od 2026-11-05;
wpisy wcześniejsze opisuje plan SEO.

## 1. Mapa zapytanie → strona

| strona | zapytanie główne (wyszukań/mies.) | odmiany w treści | title |
|---|---|---|---|
| `/blog/wyszukiwanie-agentowe-strony-i-sklepy` (wpis 2026-11-05, informacyjna; aktualizacje 2026-11-05: trzy cele, metodologia; 2026-11-06: trzy progi, query fan-out, etykiety dowodu) | wyszukiwanie agentowe (NIE ZMIERZONO — Planer/Ads API nie pytane) | agent AI na stronie, przeglądanie agentowe PageSpeed, llms.txt, Content-Signal, agentic commerce / UCP / ACP, query fan-out | Wyszukiwanie agentowe a strony i sklepy internetowe |
| `/autor/piotr-czwarnowski/` (2026-11-06, strona autora) | Piotr Czwarnowski (nawigacyjna, marka osobista) | strony w Astro, PageSpeed realizacji | Piotr Czwarnowski — strony i sklepy internetowe w Astro, Warszawa |

## 2. Briefy

### 2a. Wpis `/blog/wyszukiwanie-agentowe-strony-i-sklepy` (2026-11-05)

Intencja informacyjna, czytelnik: właściciel strony albo sklepu. Autor: Piotr Czwarnowski (pierwsza osoba, podpis i zdjęcie
autora pod tekstem). H1 „Wyszukiwanie agentowe: asystent AI sam działa na Twojej stronie i w sklepie”. H2: czym jest (agent
wykonuje zadanie) · jak agent czyta stronę (drzewo dostępności, HTML, JSON-LD, llms.txt, robots.txt i Content-Signal, Agent
Readiness, ruch botów) · pomiary czterech naszych stron (para przed/po studioagat.pl, PSI 4/4, sygnały `curl`) · 8 rzeczy do
zrobienia · sklepy: UCP, ACP i token płatności · FAQ (4 × H3) · od czego zacząć; 19 źródeł na końcu. Ok. 2 400 słów,
4 ryciny w treści + zdjęcie autora, zdjęcie główne ze stocku Canvy. Linki wewnętrzne: `/jak-sprawdzic-szybkosc-strony/`,
`/#realizacje`, `/kontakt/`. Nie kanibalizuje `/jak-sprawdzic-szybkosc-strony/` (narzędzie, inna intencja).

### 2b. Szablon wpisu i autor (2026-11-06, faza 2)

- **Autor:** wszystkie 11 wpisów mają `autor: Piotr Czwarnowski`. Linia meta pokazuje imię z miniaturą 40 px i link do
  `/autor/piotr-czwarnowski/`; `BlogPosting.author` = `{"@id": "https://www.ecopowerpolska.pl/autor/piotr-czwarnowski/#osoba"}`
  (ten sam `@id` co `Person` na stronie autora, `ProfilePage`). Na końcu wpisu `PodpisAutora.astro` (pomijany, gdy treść ma już
  zdjęcie autora). `sameAs` puste do czasu profili od Piotra.
- **Pola opcjonalne wpisu** (`src/content.config.ts`): `wJednymZdaniu` (ramka pod H1; trafia do `description`, gdy `opisSeo` puste),
  `dataAktualizacji` (`dateModified` i dopisek „aktualizacja: …”), `zTegoWpisu` (akapit „Z tego wpisu dowiesz się…”, tylko wpis
  > 1000 słów; puste = pierwsze trzy H2), `wTwojejFirmie` (`pytanie`, `odpowiedz`, `krok`, `krokAdres`; ramka na końcu).
- **Etykiety dowodu** w Markdownie: `<span class="dowod dowod--dokumentacja|badanie|pomiar|ocena">…</span>` na początku akapitu,
  najwyżej 10–12 na wpis. Klasy w `motyw.css`.
- **Schemat i lejek** w Markdownie: surowy HTML z klasami komponentów `Schemat.astro` / `Lejek.astro` (style globalne z prefiksem
  klasy, import w `[slug].astro`). Wzór: wpis o wyszukiwaniu agentowym.
- **Tabele** z Markdownu dostają `class="tabela-karty"` i `data-label` z wtyczki `src/lib/tabele-karty.mjs`; na ≤ 40rem wiersz = karta.
- **Linkowanie prawne:** oba wpisy prawne linkują zdaniem w treści do `/generator-regulaminu/` i `/generator-polityki-prywatnosci/`.
- **/blog/:** sekcja „Od czego zacząć” (planujący: strona czy sklep → ile kosztuje → ile trwa; mający stronę: Core Web Vitals →
  prawne aspekty), lista w `blog.json` → `odCzegoZaczac`.
- **Okładki:** zrzut pan-stolarz.pl jest okładką 3 wpisów, kuchnia-na-wymiar 2 — czekają na zdjęcia tematyczne od Piotra.

## 3. Brakujące fakty

- Liczba wyszukań „wyszukiwanie agentowe” — NIE ZMIERZONO.
- Funkcja Piotra w spółce do podpisu — NIE USTALONO (podpis bez funkcji).
- Profile do `sameAs` osoby (LinkedIn, GitHub osobisty itp.) — czekają na Piotra.
- Brzmienie dokumentacji Google o „query fan-out” (przypis 11) — niesprawdzone odczytem 2026-11-06 (sesja bez sieci).
