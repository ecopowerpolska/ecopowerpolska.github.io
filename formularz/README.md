# formularz/ — handler PHP formularza /kontakt/ (www.ecopowerpolska.pl)

Strona stoi na GitHub Pages (bez PHP), więc formularz wysyła zwykły POST na lh.pl:
`https://pan-stolarz.pl/formularz/ecopower/wyslij.php` (konto `serwer263249`, katalog
`public_html/pan-stolarz.pl/formularz/ecopower/`). Podkatalog `formularz/` pan-stolarz.pl, bo jego przepływ
`build-i-wgranie` kasuje (`--delete`) wszystko poza `formularz/`, `_astro/` i `.well-known/`.

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
`GET https://pan-stolarz.pl/formularz/ecopower/firma.php?nip=7011019372` (przedrostek `PL`, spacje i myślniki są dopuszczone):
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
