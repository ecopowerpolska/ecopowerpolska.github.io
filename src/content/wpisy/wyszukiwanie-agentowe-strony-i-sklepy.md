---
tytul: "Wyszukiwanie agentowe: asystent AI sam działa na Twojej stronie i w sklepie"
tytulSeo: "Wyszukiwanie agentowe a strony i sklepy internetowe"
opisSeo: "Czym jest wyszukiwanie agentowe, jak agent AI czyta stronę i sklep, co zmierzyłem na czterech stronach i 8 rzeczy do zrobienia już teraz."
dataPublikacji: "2026-11-05T12:45:00+01:00"
zdjecie: "../../assets/blog/wyszukiwanie-agentowe-asystent-ai.jpg"
zdjecieAlt: "Osoba trzyma telefon z otwartym asystentem AI, na ekranie widać napis „AI”"
autor: "Piotr Czwarnowski"
szkic: false
---

Wyszukiwanie agentowe to wyszukiwanie, w którym asystent AI nie pokazuje listy linków, tylko sam wykonuje zadanie. Szuka terminu, porównuje oferty, wypełnia formularz. Google tak już szuka wolnych stolików w restauracjach (1), a Lighthouse ocenia strony w nowej kategorii dla agentów (10). Dlatego już teraz liczy się, czy agent zrozumie Twoją stronę. Niżej opisuję, jak agent ją czyta, co zmierzyłem 5 listopada 2026 i co zrobić na stronie i w sklepie.

## Wyszukiwanie agentowe: agent wykonuje zadanie, a nie czyta wyniki

W zwykłym wyszukiwaniu dostajesz listę stron i sam klikasz. Agent dostaje zadanie, na przykład „znajdź stolik na czwartek na 19:00”. Sam przechodzi przez strony i wraca z wynikiem albo formularzem do zatwierdzenia. Twoją stronę czyta wtedy program, który ma coś na niej zrobić.

Z czym masz do czynienia dziś:

- **Google AI Mode.** Od 21 sierpnia 2025 ma funkcje agentowe. Zaczął od rezerwacji stolików: przeszukuje wiele platform, pokazuje wolne terminy i prowadzi na stronę rezerwacji. Tam rezerwację kończy człowiek. Partnerami są między innymi OpenTable, Resy i Tock (1). Pod spodem działa przeglądanie Project Mariner oraz dane Knowledge Graph i Map Google (2). Na start funkcję dostali subskrybenci AI Ultra w USA (1).
- **ChatGPT agent (OpenAI).** Od 17 lipca 2025 łączy dwa wcześniejsze narzędzia. Operator klikał po stronach, Deep Research zbierał wiedzę z wielu witryn. OpenAI wyłączyło agentowi pamięć z obawy przed wykradaniem danych przez prompt injection (4).
- **Perplexity Comet.** To przeglądarka z agentem w panelu bocznym, który widzi otwartą stronę. Recenzent TechCrunch chwalił go przy prostych zadaniach. Przy rezerwacji parkingu agent wpisał jednak błędne daty (5).
- **Claude in Chrome (Anthropic).** To agent działający w przeglądarce Chrome. W sierpniu 2025 ruszył jako pilotaż dla 1000 użytkowników planu Max (6).

Agenty wciąż się mylą. TechCrunch ocenia ich działanie w realnym świecie jako kruche (4). Prompt injection to ukryte polecenie na stronie, które próbuje przejąć agenta. Anthropic sprawdził swoje rozszerzenie na 123 przypadkach w 29 scenariuszach ataku. Bez zabezpieczeń atak udawał się w 23,6 % prób. Po zabezpieczeniach, w trybie autonomicznym, w 11,2 % (6). Wniosek dla strony: im prostsza i lepiej opisana, tym mniej miejsca na pomyłkę agenta.

![Kobieta w szarym swetrze siedzi przy oknie i mówi do asystenta głosowego w telefonie trzymanym przy ustach](../../assets/blog/wyszukiwanie-agentowe-asystent-glosowy.jpg "Zdjęcie ilustracyjne: rozmowa z asystentem głosowym w telefonie.")

## Jak agent czyta stronę internetową

Agent korzysta z kilku warstw strony naraz. Opisuję je w kolejności, w jakiej je sprawdzam.

### Drzewo dostępności

Drzewo dostępności to uproszczona mapa strony, którą buduje przeglądarka. Każdy przycisk, pole i link ma w niej rolę i nazwę. Lighthouse w kategorii przeglądania agentowego sprawdza te nazwy i etykiety, poprawność drzewa i widoczność elementów (10). Guzik bez nazwy albo pole bez etykiety to dla agenta zagadka.

### Gotowy HTML zamiast treści ze skryptu

Nasze strony trafiają do przeglądarki jako gotowy HTML z Astro. Treść i formularze są w kodzie od razu, bez czekania na skrypt. Google pisze wprost, że funkcje AI w wyszukiwarce nie mają dodatkowych wymagań technicznych. Strona ma być zindeksowana i kwalifikować się do fragmentu w wynikach (11).

### Dane strukturalne

Dane strukturalne JSON-LD opisują firmę, usługę albo produkt w słowniku schema.org. Google wymaga, żeby zgadzały się z tekstem widocznym na stronie. Do AI Overviews i AI Mode nie trzeba przy tym specjalnych typów (11). Na danych schema.org opiera się NLWeb, otwarty projekt Microsoftu. Każda jego instancja działa jako serwer MCP (Model Context Protocol), czyli źródło narzędzi dla modelu AI (15).

Nie znalazłem jednak dokumentu Google ani Bing, który mówi wprost, że agent czyta JSON-LD.

### Plik llms.txt

llms.txt to propozycja Jeremy’ego Howarda z Answer.AI z 3 września 2024 (12). Plik leży w katalogu głównym i jest Markdownem. Obowiązkowy jest tylko nagłówek H1 z nazwą. Dalej idzie podsumowanie w cytacie i sekcje H2 z listami linków (12). To propozycja, nie standard.

Google Search go nie wymaga (11), Lighthouse go sprawdza (10), a skaner Cloudflare domyślnie pomija (7). Moje zdanie: llms.txt pomaga agentom, ale nie jest warunkiem widoczności w Google.

### robots.txt, Content-Signal i podpis bota

Content-Signal to wiersz w robots.txt, który Cloudflare opisał we wrześniu 2025. Mówi, do czego wolno użyć treści strony (8):

- `search`: indeks i wyniki z linkami i fragmentami,
- `ai-input`: użycie treści w odpowiedzi AI na bieżące pytanie,
- `ai-train`: trenowanie i dostrajanie modeli.

Cloudflare wpisuje go w zarządzanym robots.txt ponad 3,8 mln domen (8). U nas grupa dla bota OpenAI wygląda tak:

```text
User-agent: GPTBot
Allow: /
Disallow: /admin/
Content-Signal: search=yes, ai-input=yes, ai-train=yes
```

Wiersz Content-Signal stawiam pod każdą grupą User-agent. Bot z własną grupą czyta tylko ją, więc sygnał w samej grupie ogólnej by do niego nie dotarł.

Szkic Web Bot Auth proponuje, żeby bot podpisywał żądania HTTP, a serwer sprawdzał podpis (14). Autorami są Thibault Meunier z Cloudflare i Sandor Major z Google. To indywidualny szkic IETF, nie standard (14).

### Agent Readiness Cloudflare i Markdown dla agentów

W kwietniu 2026 Cloudflare uruchomił ocenę gotowości strony dla agentów. Patrzy ona na cztery wymiary (7):

1. **Wykrywalność:** robots.txt, sitemap.xml i nagłówki Link.
2. **Treść:** wersja Markdown dla agentów.
3. **Kontrola dostępu botów:** Content Signals, reguły dla botów AI i Web Bot Auth.
4. **Możliwości:** Agent Skills, katalog API, odkrywanie OAuth, MCP Server Card i WebMCP.

Przy okazji Cloudflare przebadał 200 000 najpopularniejszych domen. Plik robots.txt ma 78 % z nich, deklarację preferencji wobec AI tylko 4 % (7). Negocjację Markdown przechodzi 3,9 % stron (7). Polega ona na tym, że serwer na nagłówek `Accept: text/markdown` oddaje Markdown zamiast HTML.

Token to kawałek tekstu, w którym model liczy swoją pracę. Według Cloudflare Markdown może obniżyć zużycie tokenów nawet o 80 % w niektórych przypadkach. Na dokumentacji Cloudflare agent zużył średnio o 31 % mniej tokenów i działał o 66 % szybciej (7). MCP Server Card i katalog API miało razem mniej niż 15 stron z całej próby (7).

### Ile ruchu robią boty AI

Cloudflare Radar policzył ruch od 1 stycznia do 2 grudnia 2025 (9). Boty AI dały średnio 4,2 % żądań o strony HTML. Googlebot dał 4,5 %, a ludzie 47 % (9).

Ciekawsza jest proporcja pobrań do odesłań. Mówi, ile stron bot pobiera na jedno kliknięcie, które wraca do witryny. U Anthropic sięgała 500 000 do 1, u OpenAI najwyżej 3 700 do 1. Perplexity od września 2025 schodził poniżej 200 do 1 (9). Bot ChatGPT-User, działający na polecenie użytkownika, miał w szczycie 16 razy więcej ruchu niż na początku roku (9).

## Co zmierzyłem na czterech naszych stronach

Mierzę cztery strony, które zbudowaliśmy i prowadzimy: www.ecopowerpolska.pl, studioagat.pl, pan-stolarz.pl i nadihome.pl.

### Studio Agat przed i po przeniesieniu

14 października 2026 strona studioagat.pl działała jeszcze na WordPressie. Dwa dni później przenieśliśmy ją na Astro. Oba pomiary to PageSpeed Insights na telefonie (zmierzyłem 14 października i 5 listopada 2026).

| Wynik testu na telefonie | 14 października 2026, WordPress | 5 listopada 2026, Astro |
|---|---|---|
| Wydajność | 52 | 100 |
| Ułatwienia dostępu | 96 | 100 |
| Sprawdzone metody | 100 | 100 |
| SEO | 100 | 100 |
| Przeglądanie agentowe | 2/3 | 4/4 |
| First Contentful Paint | 4,2 s | 0,9 s |
| Largest Contentful Paint | 8,9 s | 1,4 s |
| Total Blocking Time | 200 ms | 0 ms |
| Cumulative Layout Shift | 0,196 | 0 |

![Raport PageSpeed Insights strony studioagat.pl na telefonie: wydajność 52, ułatwienia dostępu 96, przeglądanie agentowe 2/3, LCP 8,9 s](../../assets/blog/wyszukiwanie-agentowe-psi-studioagat-przed-2026-10-14.png "Raport PageSpeed Insights z 14 października 2026: studioagat.pl na WordPressie, wydajność 52 i przeglądanie agentowe 2/3.")

![Raport PageSpeed Insights strony studioagat.pl na telefonie: cztery wyniki po 100 i przeglądanie agentowe 4/4, LCP 1,4 s](../../assets/blog/wyszukiwanie-agentowe-psi-studioagat-po-2026-11-05.png "Raport PageSpeed Insights z 5 listopada 2026: studioagat.pl w Astro, cztery wyniki po 100 i przeglądanie agentowe 4/4.")

Dwie rzeczy muszę zaznaczyć. W październiku kategoria przeglądania agentowego miała trzy audyty, dziś ma cztery. Google ją rozbudował, więc 2/3 i 4/4 to dwie różne skale. Zrzut z października nie pokazuje, który audyt wtedy nie przeszedł.

Druga rzecz: w tej samej minucie pomiar przez API PageSpeed dał studioagat.pl 98 na telefonie (zmierzyłem 5 listopada 2026). Wynik wydajności waha się między przebiegami.

### Cztery strony w jednym pomiarze

5 listopada 2026 zmierzyłem cztery strony przez API PageSpeed Insights (Lighthouse 13.6.0). Każdą na telefonie i na komputerze, wszystkie w ciągu dwóch sekund.

| Strona | Wydajność, telefon | Wydajność, komputer | Dostępność | Sprawdzone metody | SEO | Przeglądanie agentowe |
|---|---|---|---|---|---|---|
| www.ecopowerpolska.pl | 99 | 100 | 100 | 100 | 100 | 4/4 |
| studioagat.pl | 98 | 100 | 100 | 100 | 100 | 4/4 |
| pan-stolarz.pl | 99 | 100 | 100 | 100 | 100 | 4/4 |
| nadihome.pl | 99 | 100 | 100 | 100 | 100 | 4/4 |

Na telefonie LCP wyniósł 1,0 s na trzech stronach i 1,4 s na studioagat.pl. TBT wyniósł 0 ms wszędzie poza pan-stolarz.pl na telefonie (10 ms). CLS wyniósł 0, poza nadihome.pl na komputerze (0,001).

### Cztery audyty agentowe po ludzku

![Raport Lighthouse www.ecopowerpolska.pl: kategoria Agentic Browsing 4/4 i cztery zaliczone audyty](../../assets/blog/wyszukiwanie-agentowe-lighthouse-ecopowerpolska-agentic.png "Raport Lighthouse z pomiaru PageSpeed Insights z 5 listopada 2026: www.ecopowerpolska.pl na komputerze, cztery audyty agentowe zaliczone.")

Do wyniku liczą się cztery audyty (zmierzyłem 5 listopada 2026):

1. **Drzewo dostępności jest poprawne.** Każdy przycisk, pole i link ma rolę i nazwę. Po tym drzewie agent porusza się po stronie i klika.
2. **Cumulative Layout Shift.** Elementy nie przesuwają się podczas ładowania. Lighthouse liczy tę stabilność do gotowości strony dla agentów (10).
3. **llms.txt zgodny z zaleceniami.** Plik jest Markdownem i ma co najmniej jeden nagłówek H1. Audyt sprawdza formę pliku, nie jego treść.
4. **Manifest ARD jest poprawny.** ARD (Agentic Resource Discovery) to plik `/.well-known/ard.json` albo plik wskazany atrybutem `rel="ard"`. Według opisu audytu pozwala agentom i rejestrom odnaleźć i zweryfikować zasoby strony.

Trzy audyty WebMCP mają wagę 0 i u nas status „nie dotyczy”, bo WebMCP nie wdrażamy. Google opisuje całą kategorię jako wciąż rozwijaną i podatną na zmiany.

### Sygnały, które sprawdziłem poleceniem curl

Drugą część pomiaru zrobiłem poleceniem `curl`. Możesz je powtórzyć na swojej stronie:

```bash
curl -s https://studioagat.pl/robots.txt | grep -i content-signal
curl -s -o /dev/null -w '%{http_code} %{content_type}\n' https://studioagat.pl/llms.txt
curl -s -o /dev/null -w '%{http_code}\n' https://studioagat.pl/.well-known/ard.json
curl -s -o /dev/null -w '%{content_type}\n' -H 'Accept: text/markdown' https://studioagat.pl/
```

| Sygnał | www.ecopowerpolska.pl | studioagat.pl | pan-stolarz.pl | nadihome.pl |
|---|---|---|---|---|
| Content-Signal w robots.txt | tak | tak | tak | tak |
| GPTBot, ClaudeBot, PerplexityBot | wpuszczone | wpuszczone | wpuszczone | wpuszczone |
| /llms.txt | 200 | 200 | 200 | 200 |
| /llms-full.txt | 404 | 404 | 404 | 200 |
| /.well-known/ard.json | 200 | 200 | 200 | 200 |
| Markdown na `Accept: text/markdown` | nie | nie | nie | nie |
| JSON-LD na stronie głównej | Organization, LocalBusiness, 11 usług z ofertą | LocalBusiness, 5 usług z ofertą | LocalBusiness, 4 usługi z ofertą | LocalBusiness, bez usług |

Dwie rzeczy wypadają słabo i piszę o nich wprost. Pełną treść w llms-full.txt ma tylko nadihome.pl, pozostałe trzy strony zwracają 404. Negocjacji Markdown nie ma żadna z czterech stron: na `Accept: text/markdown` każda oddaje HTML. W badaniu Cloudflare jesteśmy więc po stronie większości, nie w tych 3,9 % (7).

## 8 rzeczy do zrobienia na stronie i w sklepie

1. **HTML semantyczny i formularze bez blokady skryptem.** Każde pole ma etykietę, każdy guzik nazwę, a formularz działa bez JavaScriptu. Blokada skryptem to treść albo formularz, które pojawiają się dopiero po uruchomieniu skryptu. Sprawdzisz to audytem drzewa dostępności w PageSpeed Insights (10) i wysyłką formularza z wyłączonym JavaScriptem.
2. **Dane strukturalne JSON-LD.** Strona firmy: Organization albo LocalBusiness. Sklep: Product z Offer przy każdym produkcie. Dane mają zgadzać się z widocznym tekstem (11). Sprawdzisz je w źródle strony, w bloku `<script type="application/ld+json">`.
3. **Plik llms.txt.** W katalogu głównym, w Markdownie: H1 z nazwą, podsumowanie w cytacie, sekcje H2 z linkami (12). Sprawdzisz go audytem llms.txt w PageSpeed Insights (10).
4. **Decyzja o botach AI w robots.txt.** Wpisz Content-Signal z trzema sygnałami (8), pod każdą grupą User-agent. Sprawdzisz to poleceniem `curl` na adresie `/robots.txt`.
5. **Manifest ARD.** Plik `/.well-known/ard.json` opisuje zasoby strony dla agentów i rejestrów. Sprawdzisz go audytem „ARD manifest is valid” w PageSpeed Insights (zmierzyłem 5 listopada 2026).
6. **Szybkość i CLS równe 0.** Lighthouse liczy stabilność układu w kategorii agentowej (10). Sprawdzisz to w PageSpeed Insights, osobno na telefonie i na komputerze.
7. **Feed produktów, a w sklepie przygotowanie do UCP i ACP.** Pełny feed w Google Merchant Center to podstawa, a Google dokłada do niego kilkadziesiąt nowych atrybutów (3). Sprawdź, czy feed obejmuje wszystkie produkty i ma te same ceny co sklep.
8. **WebMCP: obserwować.** To szkic grupy społecznościowej W3C, nie standard. Pozwala stronie udostępnić agentom narzędzia napisane w JavaScripcie (13). Will Huang pokazał w AgentReady.js, jak jeden tag `<script>` zamienia semantyczny HTML w takie narzędzia. Zakup i wysłanie formularza zostawia tam do zatwierdzenia człowiekowi (18).

## Sklepy internetowe: UCP, ACP i token płatności dla agenta

11 stycznia 2026 Google ogłosił Universal Commerce Protocol, w skrócie UCP (3). To otwarty standard dla całej drogi zakupu. Działa razem z protokołami A2A, AP2 i MCP. Współtworzyli go Shopify, Etsy, Wayfair, Target i Walmart. Poparło go ponad 20 firm, w tym Visa, Mastercard, Stripe i Zalando (3). Sprzedawcą pozostaje sklep, nie Google. Płatność w AI Mode ma iść przez Google Pay, w USA i „wkrótce” (3).

Wcześniej, 29 września 2025, Stripe i OpenAI pokazali Agentic Commerce Protocol, czyli ACP (16). To także otwarty standard. Sprzedawca integruje się z nim raz. Sklep bez Stripe może go wdrożyć z własnym operatorem płatności (16).

Najciekawszy element ACP to Shared Payment Token, czyli współdzielony token płatności. Działa tylko u jednego sprzedawcy i tylko na kwotę jednego koszyka. Nie ujawnia danych karty ani sklepowi, ani agentowi (16). Na start z ChatGPT kupowali użytkownicy w USA, od sprzedawców z Etsy (16).

Co to znaczy dla małego sklepu w Polsce? Oba protokoły wystartowały w USA (3, 16), a żadne ze źródeł nie podaje daty dla Polski. Nie obiecuję więc, że agent kupi w Twoim sklepie w tym roku. Już teraz możesz uporządkować dane produktów, feed i formularze. Te same prace pomagają w zwykłym wyszukiwaniu.

## Najczęstsze pytania

### Czy Google wymaga pliku llms.txt?

Nie. Google Search Central pisze, że funkcje AI w wyszukiwarce nie mają dodatkowych wymagań technicznych. Nie trzeba osobnych plików ani znaczników dla AI. Strona ma być zindeksowana i kwalifikować się do fragmentu w wynikach (11). llms.txt sprawdza natomiast Lighthouse w kategorii przeglądania agentowego (10). Dlatego go mamy, ale nie traktujemy go jako warunku widoczności w Google.

### Czy agent kupi dziś w moim sklepie?

Przez protokół raczej jeszcze nie. UCP i ACP działają w USA, a płatność w AI Mode Google zapowiada „wkrótce” (3, 16). Agent w przeglądarce, jak Comet, widzi otwartą stronę i może w niej działać. Recenzje opisują jednak pomyłki, na przykład błędne daty (5). Narzędzia takie jak AgentReady.js zostawiają zakup do zatwierdzenia człowiekowi (18).

### Czy blokować boty AI?

To decyzja właściciela, nie sprawa techniczna. Content-Signal pozwala ją rozdzielić na trzy zgody: wyszukiwanie, odpowiedzi AI i trenowanie modeli (8). Token Google-Extended ogranicza użycie treści do trenowania modeli Google (11). Dane Radaru pokazują, że niektóre boty pobierają setki tysięcy stron na jedno odesłanie (9). Nasze strony otwieramy, bo mają być znalezione.

### Czy wyszukiwanie agentowe zastępuje SEO?

Nie. Google wymaga od stron w funkcjach AI tego samego co w zwykłych wynikach: indeksu i kwalifikacji do fragmentu (11). Praca o GEO, czyli optymalizacji pod silniki generatywne, pokazała do 40 % większą widoczność w ich odpowiedziach (17). To wynik badania, nie gwarancja dla Twojej strony. Skuteczność metod zależała tam od dziedziny (17).

## Od czego zacząć

Zacznij od tego, co pomaga ludziom i agentom naraz: drzewa dostępności i formularzy bez blokady skryptem. Potem dane strukturalne, llms.txt i decyzja o botach w robots.txt. Na koniec zmierz stronę w PageSpeed Insights, na przykład [naszym testem szybkości strony](/jak-sprawdzic-szybkosc-strony/). Sygnały dla agentów, czyli llms.txt, robots.txt, manifest ARD i dane strukturalne, sprawdzisz naszym darmowym [audytem AI i wyszukiwania agentowego](/narzedzia/wyszukiwanie-agentowe-ai/). WebMCP i protokoły zakupowe obserwuj, ale nie przebudowuj pod nie strony już dziś. Wyniki pomiarów wszystkich naszych stron są w sekcji [nasze realizacje](/#realizacje).

**Piotr Czwarnowski**, EcoPower Polska Sp. z o.o. Budujemy i prowadzimy strony oraz sklepy internetowe dla firm. [Napisz do nas](/kontakt/).

![Piotr Czwarnowski, autor wpisu, w lupie nagłownej z podświetleniem, uśmiechnięty, w zielonoszarej koszulce](../../assets/blog/autor-piotr-czwarnowski.jpg "Piotr Czwarnowski, EcoPower Polska Sp. z o.o.")

## Źródła

1. Robby Stein (Google), [AI Mode: funkcje agentowe i personalizacja](https://blog.google/products-and-platforms/products/search/ai-mode-agentic-personalized/), 21 sierpnia 2025.
2. 9to5Google, [Google AI Mode w nowych krajach i z funkcjami agentowymi](https://9to5google.com/2025/08/21/google-ai-mode-countries-agentic/), 21 sierpnia 2025.
3. Vidhya Srinivasan (Google), [Handel agentowy i Universal Commerce Protocol](https://blog.google/products/ads-commerce/agentic-commerce-ai-tools-protocol-retailers-platforms/), 11 stycznia 2026.
4. Maxwell Zeff (TechCrunch), [OpenAI uruchamia agenta ogólnego w ChatGPT](https://techcrunch.com/2025/07/17/openai-launches-a-general-purpose-agent-in-chatgpt/), 17 lipca 2025.
5. Maxwell Zeff (TechCrunch), [Perplexity uruchamia przeglądarkę Comet](https://techcrunch.com/2025/07/09/perplexity-launches-comet-an-ai-powered-web-browser/), 9 lipca 2025.
6. Anthropic, [Claude for Chrome](https://claude.com/blog/claude-for-chrome), 25 sierpnia 2025, aktualizacje 24 listopada i 18 grudnia 2025.
7. Cloudflare, [Agent Readiness](https://blog.cloudflare.com/agent-readiness/), 17 kwietnia 2026, zmiana 15 lipca 2026.
8. Will Allen (Cloudflare), [Content Signals Policy](https://blog.cloudflare.com/content-signals-policy/), 24 września 2025. Polityka na licencji CC0.
9. Cloudflare, [Radar 2025 Year in Review](https://blog.cloudflare.com/radar-2025-year-in-review), dane od 1 stycznia do 2 grudnia 2025.
10. Chrome for Developers (Google), [Lighthouse: Agentic Browsing](https://developer.chrome.com/docs/lighthouse/agentic-browsing), bez daty na stronie, odczyt 5 listopada 2026.
11. Google Search Central, [Funkcje AI a Twoja witryna](https://developers.google.com/search/docs/appearance/ai-features), bez daty na stronie, odczyt 5 listopada 2026.
12. Jeremy Howard (Answer.AI), [llms.txt](https://llmstxt.org/), 3 września 2024. Repozytorium [AnswerDotAI/llms-txt](https://github.com/AnswerDotAI/llms-txt), licencja Apache-2.0.
13. Web Machine Learning Community Group (W3C), redaktorzy Brandon Walderman i Samuel Dallstream (Microsoft) oraz Khushal Sagar i Dominic Farolino (Google), [WebMCP, szkic raportu grupy](https://webmachinelearning.github.io/webmcp/), wersja z 2 listopada 2026. Repozytorium [webmachinelearning/webmcp](https://github.com/webmachinelearning/webmcp).
14. Thibault Meunier (Cloudflare) i Sandor Major (Google), [Web Bot Auth, szkic IETF](https://datatracker.ietf.org/doc/draft-meunier-web-bot-auth-architecture/), wersja 05 z 2 marca 2026. Repozytorium [cloudflare/web-bot-auth](https://github.com/cloudflare/web-bot-auth), licencja Apache-2.0.
15. nlweb-ai (Microsoft), [NLWeb](https://github.com/nlweb-ai/NLWeb), ogłoszenie 19 maja 2025, licencja MIT.
16. Stripe, [Instant Checkout i Agentic Commerce Protocol](https://stripe.com/newsroom/news/stripe-openai-instant-checkout), 29 września 2025.
17. Pranjal Aggarwal, Vishvak Murahari, Tanmay Rajpurohit, Ashwin Kalyan, Karthik Narasimhan, Ameet Deshpande, [GEO: Generative Engine Optimization](https://arxiv.org/abs/2311.09735), KDD 2024, licencja CC BY 4.0. Repozytorium [GEO-optim/GEO](https://github.com/GEO-optim/GEO), licencja Apache-2.0.
18. Will Huang, [AgentReady.js](https://github.com/doggy8088/agentready), licencja MIT.
19. Pomiary własne: raport PageSpeed Insights studioagat.pl z 14 października 2026, pomiar API PageSpeed Insights (Lighthouse 13.6.0) czterech stron i odczyt sygnałów poleceniem `curl` z 5 listopada 2026.
