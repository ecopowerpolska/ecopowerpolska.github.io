# formularz/ — handler PHP formularza /kontakt/ (www.ecopowerpolska.pl)

Strona stoi na GitHub Pages (bez PHP), więc formularz wysyła zwykły POST na lh.pl:
`https://api.ecopowerpolska.pl/wyslij.php` (konto `serwer263249`, katalog
`public_html/api.ecopowerpolska.pl/`, własna subdomena z certyfikatem Let's Encrypt).

| Plik | Rola |
|---|---|
| `wyslij.php` | handler: Origin → honeypot `dodatkowe` → Turnstile `siteverify` → czas `t` → pola → licznik → `mail()` → 303 |
| `firma.php` | `GET ?nip=` → dane firmy z rejestrów (MF → KRS → CEIDG) jako JSON dla generatorów w `/darmowe-narzedzia/` (kontrakt niżej) |
| `audyt.php` | `GET ?test=<nazwa>&url=<adres>` → JSON audytu obcej strony dla narzędzi `/narzedzia/<slug>/` (runda 9); wspólne pobranie strony, limit 20/h na IP, pamięć podręczna 1 h; kontrakt modułów: `docs/KONTRAKT-AUDYTU.md` |
| `testy/_wspolne.php`, `testy/_dns.php` | biblioteki: pobranie z ochroną adresu (SSRF), nagłówki, HEAD, DNS, ocena; `_dns.php` = MX/SPF/DKIM/DMARC/BIMI wspólne dla testów poczty i spamu |
| `testy/<nazwa>.php` (8) | moduły testów: `wyszukiwanie_agentowe_ai`, `dostepnosc`, `bezpieczenstwo_i_zaufanie`, `poczta_domeny`, `seo_techniczne`, `waga_i_media`, `prawo_na_stronie`, `ochrona_przed_spamem` (dziewiąty, `dane_terenowe_crux`, idzie z PSI w przeglądarce, bez PHP) |
| `testy/.htaccess` | `Require all denied` (moduły woła tylko `audyt.php`) |
| `.htaccess` | z sieci odpowiadają `wyslij.php`, `firma.php` i `audyt.php` (logi, liczniki, `.json`, `.md`, `.przyklad` zamknięte) |
| `konfig/.htaccess`, `stan/.htaccess` | `Require all denied` (`stan/firma/` i `stan/firma-ip/` dziedziczą zakaz) |
| `konfig.przyklad` | wzór `konfig/ecopowerpolska.pl.php` (na koncie 600; w repo NIGDY wartości); od 2026-10-30 także `ceidg_token`, `sol_ip` |

Odpowiedzi (zawsze 303): sukces i honeypot → `<Origin>/dziekujemy/`; błąd → `<Origin>/kontakt/?blad=<kod>#formularz`,
kody = klucze `bledy` w `src/data/kontakt.json` (`turnstile`, `czas`, `imie`, `email`, `telefon`, `adres`, `wiadomosc`,
`zgoda`, `limit`, `wysylka`, `inny`); obcy Origin → `https://www.ecopowerpolska.pl/kontakt/?blad=inny`. `GET` → 405.

Kontrakt pól z `src/components/Formularz.astro`: `imie`, `email` (wymagane), `telefon`, `adres`, `wiadomosc`,
`potrzeby[]`, `technologia`, `strona`, `pora`, `zgoda`, `wyceny` (JSON z `src/lib/wyceny.ts → doWyslania()`),
`t`, `dodatkowe`, `cf-turnstile-response`. Listy wartości `POTRZEBY`/`TECHNOLOGIE`/`PORY` = `kontakt.json`;
zmiana w jednym miejscu = zmiana w drugim. Wgranie i test: `docs/FORMULARZ.md`.


## firma.php — kontrakt JSON (od 2026-10-30)
`GET https://api.ecopowerpolska.pl/firma.php?nip=7011019372` (przedrostek `PL`, spacje i myślniki są dopuszczone):
```json
{"nazwa":"ECOPOWER POLSKA SPÓŁKA Z OGRANICZONĄ ODPOWIEDZIALNOŚCIĄ","forma_prawna":"Spółka z ograniczoną odpowiedzialnością",
 "nip":"7011019372","regon":"388143240","krs":"0000881612",
 "adres":{"ulica":"UL. GEN. TADEUSZA BORA-KOMOROWSKIEGO","numer":"21/72","kod":"03-982","miasto":"WARSZAWA","pelny":"UL. GEN. … 21/72, 03-982 WARSZAWA"},
 "status_vat":"Czynny","zrodla":["MF","KRS"],"pobrano":"2026-10-30T12:39","cache":false}
```
- Pola puste = `""` (`krs` u jednoosobowej działalności), `status_vat` = `null`, gdy MF nie zna podmiotu. Dla CEIDG dochodzi `status_dzialalnosci` (`AKTYWNY`, `WYKRESLONY`, `ZAWIESZONY`…).
  Nazwy i adresy idą tak, jak podaje rejestr (często WERSALIKAMI); `adres.pelny` to ratunek, gdy rozbicie na `ulica`/`numer` się nie uda.
- Źródła: MF biała lista po NIP → KRS po numerze z MF (nazwa pełna, forma prawna, siedziba; rejestr P, przy 404 S) → CEIDG po NIP,
  gdy MF nie zna podmiotu albo podmiot nie ma KRS (jednoosobowa działalność, spółka cywilna). CEIDG tylko z `ceidg_token` w konfiguracji; bez niego pominięty.
- Błędy `{"blad":"<kod>","opis":"po polsku"}`: `nip` (400, zły format lub suma kontrolna) · `brak` (404, żadne źródło nie zna NIP-u; cache 15 min) ·
  `limit` (429 + `Retry-After`, 30 wywołań urzędów na IP/h albo bezpiecznik CEIDG 100/h dla całej usługi) · `zrodlo` (502, urząd nie odpowiada) ·
  `origin` (403, obcy `Origin`, bez nagłówka CORS) · `metoda` (405, poza GET).
- Nagłówki: `Access-Control-Allow-Origin` tylko dla `https://www.ecopowerpolska.pl` i `https://ecopowerpolska.pl` (+ `Vary: Origin`), `Cache-Control: no-store`, `nosniff`.
- Pamięć podręczna `stan/firma/<nip>.json` 24 h (trafienie ma `cache:true` i nie zużywa limitu IP); licznik IP `stan/firma-ip/<sha256(sol_ip|ip)>.json`;
  dziennik `stan/firma-RRRRMMDD.log` (data, kod wyniku, źródła — bez NIP-ów i adresów IP). Klucze konfiguracji: `ceidg_token`, `sol_ip`,
  opcjonalnie `firma_limit_na_godz`, `ceidg_limit_na_godz`, `cache_sekundy`, `cache_brak_sekundy`. Timeouty 5 s na źródło, TLS zweryfikowany.


## komentarze.php: komentarze pod wpisami ambasadapodroznikow.pl (od 2026-11-04)
`https://api.ecopowerpolska.pl/komentarze.php`. Strona na GitHub Pages nie zapisuje danych, więc komentarze przyjmuje to zaplecze.
Origin dozwolony: `https://<strona>` dla każdej strony z `komentarze_strony` (dziś tylko `https://ambasadapodroznikow.pl`).
- **GET `?strona=<strona>&wpis=<slug>`** → `200 {"wpis":"<slug>","komentarze":[{"id":"<16 hex>","autor","tresc","data":"2026-11-04T12:34:56+01:00"}]}`,
  tylko opublikowane, od najstarszego. **GET `?strona=<strona>&wszystkie=1`** → `{"wpisy":{"<slug>":[…]}}` (tylko wpisy z komentarzami; dla builda).
  Nagłówki: `Access-Control-Allow-Origin: https://<strona>`, `Cache-Control: no-store`. GET działa bez Origin (build w Actions). Błędny `strona`/`wpis` → 400 `{"ok":false,"blad":…}`.
- **POST** (`multipart/form-data`, `x-www-form-urlencoded` albo `application/json`, najwyżej 32 KB): `strona`, `wpis`, `autor` (2–60), `tresc` (3–2000),
  `email` (opcjonalny, nigdy nie publikowany), `cf-turnstile-response`. Z `Accept: application/json` odpowiedź to JSON:
  `{"ok":true,"status":"opublikowany"|"czeka","komentarz":{id,autor,tresc,data}}` albo `{"ok":false,"blad":"<kod>"}` (400/403/429/500). Bez niego: 303 na
  `https://<strona>/blog/<slug>/?komentarz=<status>#komentarze` albo `…?blad=<kod>#komentarze`.
  Kody: `autor`, `tresc`, `turnstile` (403), `limit` (429), `wpis`, `wysylka` (zarezerwowany: błąd poczty NIE cofa zapisu, trafia do logu), `inny` (obcy lub brak Origin 403, zła `strona`, e-mail, za duże żądanie, brak `turnstile_sekret` 500).
  Kolejność kontroli: Origin → metoda → `strona` → `wpis` (`^[a-z0-9-]{1,120}$` i HEAD `https://<strona>/blog/<slug>/` = 200; pamięć 24 h dla wpisów istniejących, 10 min dla nieistniejących, bezpiecznik 100 nowych HEAD na godzinę) →
  Turnstile (`success` i `hostname` z `komentarze_turnstile_hosty`) → limit (5 na 10 min i 20 na dobę na skrót IP, 300 na dobę globalnie, 500 komentarzy na wpis) → pola → zapis → mail.
  Tekst przechowywany surowo, bez HTML; usuwane są znaki sterujące i znaczniki kierunku tekstu. Więcej niż 2 linki (`https?://…`, `www.…`) → status `czeka` (niewidoczny). Ten sam autor i ta sama treść pod wpisem drugi raz = zwrot istniejącego komentarza bez nowego zapisu i maila.
  Adres klienta: `REMOTE_ADDR`; `CF-Connecting-IP` tylko gdy `REMOTE_ADDR` leży w zakresach Cloudflare (stała w kodzie, odczyt 2026-11-04).
- **Moderacja:** mail zawiera linki `komentarze.php?akcja=usun|zatwierdz&strona=…&wpis=…&id=…&podpis=<HMAC-SHA256>` (podpis z `akcja\nstrona\nwpis\nid`, klucz `komentarze_klucz`).
  GET pokazuje stronę z podglądem (treść ucieczką `htmlspecialchars`) i przyciskiem; akcję wykonuje dopiero POST przycisku (skanery poczty nic nie skasują). Podpis porównuje `hash_equals`.
- **Mail:** `kontakt@ecopowerpolska.pl` (`komentarze_mail_do`), nadawca jak `wyslij.php` (`od`), temat `[Ambasada] Komentarz: <slug>` albo `[Ambasada] Komentarz czeka: <slug>`.
- **Dane:** `stan/komentarze/<strona>/<wpis>.json` (lista rekordów `id, autor, tresc, email, data, status`; zapis pod `flock`). **Skrótu IP w rekordzie NIE ma** (polityka prywatności: IP służy tylko limitom).
  Limity: `stan/komentarze-limity.json` (znaczniki czasu per skrót `sha256(IP + dobowa sól)`; plik zeruje się z nową solą przy pierwszym żądaniu następnego dnia). Pamięć istnienia wpisów: `stan/komentarze-wpisy.json`.
  Błędy: `stan/komentarze.log` (bez treści komentarzy i adresów IP; po 512 KB przechodzi do `.log.1`). `stan/` i `konfig/` zamykają własne `.htaccess`.
- **Tryb CLI** (SSH, z katalogu `public_html/api.ecopowerpolska.pl/`): `php komentarze.php lista <strona>` (kolumny: wpis, id, status, data, autor, początek treści),
  `php komentarze.php usun <strona> <wpis> <id>`, `php komentarze.php zatwierdz <strona> <wpis> <id>`.
- **Konfiguracja** (`konfig/ecopowerpolska.pl.php`, klucze w `konfig.przyklad`): `turnstile_sekret` (wspólny z `wyslij.php`; widżet musi mieć w hostnames `ambasadapodroznikow.pl`), `komentarze_klucz` (generuje integrator na serwerze), pozostałe mają wartości domyślne.
  Klucze testowe (`komentarze_poczta_plik`, `komentarze_wpisy_testowe`, `komentarze_siteverify_url`) nie wchodzą na serwer.
- **Test lokalny:** kopia katalogu, `konfig/ecopowerpolska.pl.php` z sekretem testowym Cloudflare `1x0000000000000000000000000000000AA`, `komentarze_turnstile_hosty => ['example.com']` (siteverify z kluczem testowym zwraca hostname `example.com`), `php -S 127.0.0.1:<port>`.
