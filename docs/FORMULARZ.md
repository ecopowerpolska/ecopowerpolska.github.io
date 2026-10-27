# Formularz /kontakt/ — jak działa, gdzie stoi, jak go wgrać i sprawdzić (od 2026-10-27)

## 1. Układ
- **Strona** `/kontakt/` (`src/pages/kontakt.astro`) z komponentem `src/components/Formularz.astro`; teksty, opcje, adres
  handlera (`akcja`) i klucz witryny Turnstile (`turnstileSitekey`) w `src/data/kontakt.json` (panel: Teksty strony → Kontakt).
- **Wybór przez zaznaczanie:** żetony `potrzeby[]` (11 pozycji), `technologia` (pokazuje się, gdy zaznaczono sklep — CSS `:has`, bez JS),
  `strona` tak/nie z polem `adres` (też `:has`), `pora` (kiedy dzwonić).
- **Karty wycen:** kalkulator `/cena/#kalkulator` zapisuje wyliczenia w `sessionStorage['ep.wyceny']` (`src/lib/wyceny.ts`, kontrakt
  §III rundy 4: ≤ 5 wpisów, kwoty netto, `brutto` ×1,23). Formularz rysuje je jako karty z „Usuń” i linkiem „Dodaj kolejne wyliczenie”,
  a przy wysyłce wkłada JSON do pola ukrytego `wyceny`. Szkic pól: `sessionStorage['ep.szkic']`. `/dziekujemy/` czyści oba klucze.
  Bez ciasteczek; pamięć sesji znika z zamknięciem karty.
- **Wiadomość** jest wymagana tylko, gdy nie zaznaczono żadnego żetonu i nie ma wyliczenia (skrypt zdejmuje `required`, handler sprawdza to samo).
- **Ochrona:** Cloudflare Turnstile (widżet „ecopowerpolska.pl formularz kontaktowy”, tryb Managed, hosty `ecopowerpolska.pl`
  i `www.ecopowerpolska.pl`, klucz witryny `0x4AAAAAAFocIUKeGUjpJcZ3`, założony API 2026-10-27), render jawny i odroczony
  (wejście formularza w okno albo fokus), honeypot `dodatkowe`, czas wypełniania `t` (≥ 3 s), sprawdzenie `Origin`, licznik 30 listów/h.
  Bez JavaScriptu formularz się wysyła, ale bez tokenu Turnstile handler wraca z `?blad=turnstile`; `<noscript>` podaje e-mail i telefon.

## 2. Handler
`formularz/wyslij.php` (opis pól i kodów: `formularz/README.md`). GitHub Pages nie ma PHP, więc handler stoi na lh.pl, konto
`serwer263249`, katalog `public_html/pan-stolarz.pl/formularz/ecopower/`, adres `https://pan-stolarz.pl/formularz/ecopower/wyslij.php`.
Podkatalog `formularz/` pan-stolarz.pl, bo przepływ `build-i-wgranie` pan-stolarz.pl kasuje (`--delete`) wszystko inne w katalogu strony.
List idzie do `kontakt@ecopowerpolska.pl`, nadawca `formularz@pan-stolarz.pl` (SPF konta lh.pl), `Reply-To` = klient;
sekcje `=== WYCENY Z KALKULATORA ===`, `=== WIADOMOŚĆ KLIENTA ===`, `=== WYBÓR ===`.
Konfiguracja: `konfig/ecopowerpolska.pl.php` na koncie (600, katalog 700), dziś jeden klucz `turnstile_sekret`; wzór `formularz/konfig.przyklad`.
Brak sekretu = handler pomija Turnstile z wpisem w logu (formularz dalej działa). Licznik i dziennik wysyłek (bez danych osobowych): `stan/`.

**Po założeniu subdomeny** (np. `formularz.ecopowerpolska.pl` w panelu lh.pl i DNS — robi Piotr): wgrać ten sam katalog pod nowy adres
i zmienić jedno pole `akcja` w `kontakt.json`.

## 3. Wgranie ręczne (przepływ `wgranie-formularza.yml` śpi bez sekretów repo)
```bash
cd strony/ecopowerpolska.pl/formularz
tar -cf - wyslij.php .htaccess konfig/.htaccess stan/.htaccess | ssh -o IdentitiesOnly=yes -i ~/.ssh/lh-lejek -p 40022 \
  serwer263249@serwer263249.lh.pl 'set -e; D=public_html/pan-stolarz.pl/formularz/ecopower; mkdir -p "$D"; cd "$D"; tar -xf -;
  chmod 644 wyslij.php .htaccess konfig/.htaccess stan/.htaccess; chmod 700 konfig stan; php -l wyslij.php'
```
Sekret Turnstile (wartość tylko w zmiennej, z API Cloudflare tokenem `cloudflare-token`):
`GET accounts/a4bd3ef73ffd555540dd362fa6250242/challenges/widgets/0x4AAAAAAFocIUKeGUjpJcZ3` → `result.secret` →
`printf "<?php\nreturn ['turnstile_sekret' => '%s'];\n" "$SEK" | ssh … 'umask 077; cat > …/konfig/ecopowerpolska.pl.php; chmod 600 …'`; `unset SEK`.

## 4. Kontrola
```bash
U=https://pan-stolarz.pl/formularz/ecopower
curl -s -o /dev/null -w '%{http_code}\n' $U/wyslij.php                        # 405
curl -s -o /dev/null -w '%{http_code}\n' $U/konfig/ecopowerpolska.pl.php      # 403
curl -s -o /dev/null -w '%{http_code}\n' $U/stan/                             # 403
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' -H 'Origin: https://www.ecopowerpolska.pl' \
  -d cf-turnstile-response=XXXX.DUMMY.TOKEN.XXXX -d t=1 $U/wyslij.php         # 303 …/kontakt/?blad=turnstile
```
`?blad=turnstile` przy fałszywym tokenie dowodzi, że sekret na koncie jest prawdziwy (zły sekret handler przepuszcza z logiem).
Test pełny 2026-10-27 18:39: podgląd z kluczami testowymi Cloudflare → 303 → `/dziekujemy/`, `mail()` = true (`stan/wyslane.log`).
