# formularz/ — handler PHP formularza /kontakt/ (www.ecopowerpolska.pl)

Strona stoi na GitHub Pages (bez PHP), więc formularz wysyła zwykły POST na lh.pl:
`https://pan-stolarz.pl/formularz/ecopower/wyslij.php` (konto `serwer263249`, katalog
`public_html/pan-stolarz.pl/formularz/ecopower/`). Podkatalog `formularz/` pan-stolarz.pl, bo jego przepływ
`build-i-wgranie` kasuje (`--delete`) wszystko poza `formularz/`, `_astro/` i `.well-known/`.

| Plik | Rola |
|---|---|
| `wyslij.php` | handler: Origin → honeypot `dodatkowe` → Turnstile `siteverify` → czas `t` → pola → licznik → `mail()` → 303 |
| `.htaccess` | z sieci odpowiada tylko `wyslij.php` (logi, liczniki, `.md`, `.przyklad` zamknięte) |
| `konfig/.htaccess`, `stan/.htaccess` | `Require all denied` |
| `konfig.przyklad` | wzór `konfig/ecopowerpolska.pl.php` (na koncie 600; w repo NIGDY wartości) |

Odpowiedzi (zawsze 303): sukces i honeypot → `<Origin>/dziekujemy/`; błąd → `<Origin>/kontakt/?blad=<kod>#formularz`,
kody = klucze `bledy` w `src/data/kontakt.json` (`turnstile`, `czas`, `imie`, `email`, `telefon`, `adres`, `wiadomosc`,
`zgoda`, `limit`, `wysylka`, `inny`); obcy Origin → `https://www.ecopowerpolska.pl/kontakt/?blad=inny`. `GET` → 405.

Kontrakt pól z `src/components/Formularz.astro`: `imie`, `email` (wymagane), `telefon`, `adres`, `wiadomosc`,
`potrzeby[]`, `technologia`, `strona`, `pora`, `zgoda`, `wyceny` (JSON z `src/lib/wyceny.ts → doWyslania()`),
`t`, `dodatkowe`, `cf-turnstile-response`. Listy wartości `POTRZEBY`/`TECHNOLOGIE`/`PORY` = `kontakt.json`;
zmiana w jednym miejscu = zmiana w drugim. Wgranie i test: `docs/FORMULARZ.md`.
