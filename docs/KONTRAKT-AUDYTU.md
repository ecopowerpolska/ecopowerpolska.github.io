# KONTRAKT AUDYTU — moduły testów i dane narzędzi (runda 9, 2026-10-30)

Czytają: autorzy modułów `formularz/testy/<nazwa>.php` i plików `src/data/narzedzia/<slug>.json`. Wzór pól: `src/data/narzedzia/_wzor.json`. Wzór modułu: dowolny z `formularz/testy/*.php` (np. `seo_techniczne.php`).

## 1. Przepływ
`GET audyt.php?test=<nazwa>&url=<adres>` → walidacja (metoda, Origin, test z białej listy, adres publiczny) → cache 1 h → limit 20/h na skrót IP → **jedno wspólne pobranie strony** → `test_<nazwa>($url, $ctx)` → odpowiedź. Moduł NIE pobiera strony głównej drugi raz.

Białą listę (nazwy z podkreślnikami) ma `audyt.php` w `BIALA_LISTA`: `wyszukiwanie_agentowe_ai`, `dostepnosc`, `bezpieczenstwo_i_zaufanie`, `poczta_domeny`, `seo_techniczne`, `waga_i_media`, `prawo_na_stronie`, `dane_terenowe_crux` (tryb psi: pliku PHP nie ma), `ochrona_przed_spamem`. Slug z myślnikami też działa (`-` → `_`). Plik `testy/<nazwa>.php` musi istnieć, inaczej `blad: test`.

## 2. Sygnatura modułu
Plik `formularz/testy/<nazwa>.php`, pierwsza linia kodu po `declare(strict_types=1);` to straż:
```php
if (!defined('AUDYT_EP')) { http_response_code(404); exit; }
function test_<nazwa>(string $url, array $ctx): array { … }   // np. test_poczta_domeny
```
Zwraca `['ocena' => ?int, 'pozycje' => [pozycja(...), …], 'surowe' => [...]?]`. `ocena` opcjonalna (brak → `audyt.php` policzy `ocena_z_pozycji($pozycje)` z wagą 1; **przeglądarka i tak liczy ocenę z wag w JSON narzędzia** — jedno źródło prawdy to JSON, ocena z PHP jest pomocnicza dla API). `surowe` = dane pomocnicze (nie wyświetlane). Wyjątek (`throw`) → `blad: zrodlo`. Nie `echo`, nie `exit`, nie `header()`.

## 3. `$ctx` (tablica)
| klucz | typ | znaczenie |
|---|---|---|
| `url` | string | adres znormalizowany po `ep_normalizuj_url` (https dopisane, bez fragmentu) |
| `url_koncowy` | string | adres po przekierowaniach (to jego `html` dotyczy) |
| `host` | string | host końcowy, małe litery, punycode |
| `kod` | int | kod HTTP końcowej odpowiedzi (200, 404, 403…; strona z kodem ≥ 400 też jest audytowana) |
| `naglowki` | array | nagłówki odpowiedzi, klucze małymi literami, powtórzone sklejone „, ” (`naglowek($ctx,'x-frame-options')`) |
| `html` | string | ciało w UTF-8, do 2 MB |
| `czas_ms` | int | czas do pierwszego bajtu ostatniego przeskoku (z DNS/TCP/TLS) |
| `przekierowania` | array | `[['z'=>url,'kod'=>301,'do'=>url],…]`, puste = bez przekierowań |
| `konfig` | array | tylko `['psi_klucz'=>string]` (inne klucze konfiguracji są ukryte) |
| `czas_pobrania_ms`, `rozmiar`, `obcieto` | int, int, bool | łączny czas, bajty ciała po dekompresji, czy ucięto na 2 MB |

Pobranie się nie udało (brak połączenia, nieprawidłowy certyfikat, timeout 8 s) → `audyt.php` zwraca `blad: pobranie` i modułu nie woła. Dlatego test certyfikatu: gdy strona jest dostępna, `certyfikat($host)` czyta go osobno bez weryfikacji łańcucha.

## 4. Pomocnicze (`formularz/testy/_wspolne.php`, ładowane zawsze)
```php
pozycja(string $id, string $nazwa, string $stan, mixed $wartosc, string $opis, string $zalecenie = ''): array
   // stan: ok|uwaga|blad|info; teksty są obcinane (nazwa 120, wartość 300, opis/zalecenie 600)
ocena_z_pozycji(array $pozycje, array $wagi = []): ?int   // ok=1, uwaga=0,5, blad=0, info pomijane; wagi[id] domyślnie 1; brak ocenianych → null
kolor(?int $ocena): string                                 // ≥90 'dobry', ≥50 'srednio', niżej 'slabo', null 'brak'
dom(string $html): array                                   // [DOMDocument, DOMXPath]; użycie: [, $xp] = dom($ctx['html']); $xp->query('//title')
head(string $url, int $timeout = 3): array                 // ['kod','dlugosc'(?int),'typ','url','blad','naglowki']; ≤ 15 na test, potem blad='limit_zadan'
pobierz_tekst(string $url, int $limitB = 200000, int $timeout = 4): array  // ['ok'(kod 200),'kod','tekst'(UTF-8),'url','typ','obcieto','blad']; ≤ 8 na test
naglowek(array $ctx, string $nazwa): ?string               // nagłówek odpowiedzi strony
zrodlo_url(array $ctx, string $sciezka): string            // zrodlo_url($ctx,'/robots.txt') → https://host/robots.txt na końcowym hoście
ep_zlacz(string $baza, string $rel): string                // względny href/src → adres bezwzględny
domena_poczty(string $host): string                        // host bez „www.” (domena do MX/SPF/DMARC)
dns_txt(string $host): array   // lista napisów TXT (części rekordu sklejone)
dns_mx(string $host): array    // [['pri'=>int,'host'=>string],…] rosnąco po priorytecie
dns_a(string $host): array     // IPv4;  dns_aaaa(string $host): array — IPv6
certyfikat(string $host, int $port = 443): ?array          // ['nazwa','wystawca','od','do'(timestamp),'dni','pasuje'] albo null
ep_pobierz(string $url, array $o = []): array              // niskopoziomowe (metoda GET|HEAD, limit, timeout, przekierowania); nie zużywa liczników, używać oszczędnie
ep_pozostalo(): float                                      // sekundy budżetu testu (łącznie 20 s)
```
Każde żądanie sieciowe (także `head`, `pobierz_tekst`, `ep_pobierz`, `certyfikat`) sprawdza adres jak `audyt.php`: tylko hosty publiczne, porty 80/443, IP przypięty do połączenia, każdy przeskok przekierowania osobno. Adres wyciągnięty z HTML (obrazy, skrypty) można więc śmiało przekazać do `head()`. DNS-owe zapytania poczty/DNSBL (`dns_txt`, `dns_a`…) nie mają licznika, ale mieszczą się w budżecie 20 s — nie rób ich setek. Czasu nie marnuj: `head()` przy 3 s × 15 = 45 s w najgorszym razie, dlatego od ~20 s `ep_pobierz` zwraca `blad = 'budzet'` i test kończy się z tym, co zebrał (moduł powinien radzić sobie z pozycją „nie udało się sprawdzić” jako `info`).

## 5. Identyfikatory pozycji (`id`)
- `id` = **stałe, krótkie, małe litery z myślnikami** (`spf`, `dmarc-polityka`, `hsts`), te same w PHP i w JSON narzędzia. Nowa pozycja w PHP bez wpisu w JSON też się wyświetli (nazwa z modułu, waga 1), ale bez opisu z JSON — dopisz do JSON.
- **Nazwa, opis i zalecenie w JSON mają wartość domyślną; moduł może je nadpisać konkretem**: `opis` z modułu (np. „SPF kończy się ~all, wiadomości podszywające się nie są odrzucane.”) wygrywa z `opis` z JSON; `zalecenie` z modułu wygrywa z JSON. `nazwa` wyświetla zawsze JSON.
- `wartosc`: krótko, po ludzku („200”, „87 dni”, „brak”, „~all”); `stan`: `ok` = spełnione, `uwaga` = da się poprawić, `blad` = brak lub błąd, `info` = informacja bez oceny (nie wchodzi do oceny).
- Tryb PSI (w przeglądarce, bez PHP): `id` w JSON = **id audytu Lighthouse** (np. `color-contrast`, `llms-txt`), `kat:<kategoria>` (np. `kat:accessibility` = wynik 0–100: ≥90 ok, ≥50 uwaga, inaczej błąd), `crux:<METRYKA>` (dane terenowe adresu, `loadingExperience`) albo `crux-origin:<METRYKA>` (całej witryny); METRYKA to klucz PSI: `LARGEST_CONTENTFUL_PAINT_MS`, `INTERACTION_TO_NEXT_PAINT`, `CUMULATIVE_LAYOUT_SHIFT_SCORE`, `FIRST_CONTENTFUL_PAINT_MS`, `EXPERIMENTAL_TIME_TO_FIRST_BYTE`. Stan z audytu: score ≥ 0,9 ok, > 0 uwaga, 0 błąd, brak wyniku (informacyjny/nie dotyczy) info; z CrUX: FAST ok, AVERAGE uwaga, SLOW błąd. Przy `psiOba: true` stan to gorszy z telefonu i komputera, a wartość „telefon X, komputer Y”.
- Tryb `oba`: dla pozycji z JSON najpierw szuka się jej w wyniku serwera po `id`, potem w PSI.
- Jeśli nie ma ani jednej pozycji (np. brak danych terenowych) strona pokazuje `pusto` z JSON (albo komunikat domyślny).

## 6. Odpowiedź
Sukces (200):
```json
{"test":"seo_techniczne","url":"https://nadihome.pl/","url_koncowy":"https://nadihome.pl/","pobrano":"2026-10-30T13:24:48+01:00","cache":false,
 "wynik":{"ocena":87,"kolor":"srednio","pozycje":[{"id":"title","nazwa":"Tytuł strony","stan":"ok","wartosc":"60 znaków","opis":"…","zalecenie":""}],"surowe":{}}}
```
Błąd: `{"blad":"<kod>","opis":"po polsku"}` — `url` (400, adres nieprawidłowy/niepubliczny), `test` (400), `limit` (429 + `Retry-After`), `pobranie` (502), `zrodlo` (502, moduł zawiódł), `origin` (403), `metoda` (405). Nagłówki: `Cache-Control: no-store`, `Vary: Origin`, CORS tylko dla `https://www.ecopowerpolska.pl` i apex.

## 7. Limity
Pobranie strony: timeout 8 s, ≤ 5 przekierowań, ≤ 2 MB, UA `EcoPowerAudyt/1.0 (+https://www.ecopowerpolska.pl/darmowe-narzedzia/)`. Moduł: `head()` ≤ 15, `pobierz_tekst()` ≤ 8, łącznie ≤ 20 s (budżet liczony od pobrania strony). Pamięć podręczna 1 h na (test, adres). Etyka: tylko jedno pobranie strony głównej + robots/sitemap/DNS/HEAD, bez logowania i skanowania portów. Dziennik: tylko czas, test, kod.

## 8. Pola JSON narzędzia
Pełna lista z opisami: `src/data/narzedzia/_wzor.json` (`slug, nazwa, tytulSeo, opis, lead, tryb, test, kategoriePsi, psiOba, pozycje[{id,nazwa,opis,zalecenie,waga}], progi, tekstDoKopiowania, wezwanie, pelnyRaport`; opcjonalnie `nota`, `pusto`, `tresc[]`). `test` = nazwa modułu (z podkreślnikami), puste przy `tryb: psi`. Skrypt strony (≈ 2,7 kB gzip) nie wymaga zmian per narzędzie. Wpis kafelka do `narzedzia.json` podaje autor modułu integratorowi: `{"id":"<slug>","ikona":"…","nazwa":"…","opis":"…","stan":"dziala","adres":"/narzedzia/<slug>/","guzik":"Otwórz","api":""}`.

## 9. Próba lokalna modułu
```
cp -r formularz <scratchpad>/p && echo '<?php return ["sol_ip"=>"losowa"];' > <scratchpad>/p/konfig/ecopowerpolska.pl.php
php -l <plik>; (cd <scratchpad>/p && php -S 127.0.0.1:8099 &) ; curl 'http://127.0.0.1:8099/audyt.php?test=<nazwa>&url=ecopowerpolska.pl'
```
Działają tylko moduły z białej listy `BIALA_LISTA`. Cache (`stan/audyt/`) kasujesz plikami przed ponowną próbą. Limit IP: 20/h, więc przy próbach `rm stan/audyt-ip/*.json`.
