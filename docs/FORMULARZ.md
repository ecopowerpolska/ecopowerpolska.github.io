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

### 3a. `firma.php` — dane firmy po NIP (od 2026-10-30; generatory w `/darmowe-narzedzia/`)
Wgranie tylko tego pliku i `.htaccess` (reszta zostaje; `konfig/` i `stan/` nietknięte):
```bash
cd strony/ecopowerpolska.pl/formularz
tar -cf - firma.php .htaccess | ssh -o IdentitiesOnly=yes -i ~/.ssh/lh-lejek -p 40022 \
  serwer263249@serwer263249.lh.pl 'set -e; cd public_html/pan-stolarz.pl/formularz/ecopower; tar -xf -; chmod 644 firma.php .htaccess; php -l firma.php'
```
Token CEIDG i sól skrótu IP dopisujemy do TEGO SAMEGO pliku `konfig/ecopowerpolska.pl.php` co sekret Turnstile (zostaje nietknięty). Wartości
idą tylko standardowym wejściem ssh, nigdy w argumentach (nie widać ich w `ps`), kod PHP jedzie jako base64:
```bash
KOD=$(cat <<'PHP'
[$t, $s] = array_map('trim', [(string)fgets(STDIN), (string)fgets(STDIN)]);
if ($t === '' || $s === '') { fwrite(STDERR, "brak wartosci\n"); exit(1); }
$p = 'konfig/ecopowerpolska.pl.php';
$k = is_file($p) ? require $p : [];
$k['ceidg_token'] = $t;
if (empty($k['sol_ip'])) { $k['sol_ip'] = $s; }
file_put_contents($p, "<?php\nreturn " . var_export($k, true) . ";\n");
chmod($p, 0600);
echo count($k), " kluczy w konfiguracji: ", implode(', ', array_keys($k)), "\n";
PHP
)
B64=$(printf '%s' "$KOD" | base64 -w0)
T=$(bash /home/ecopower/projekty/test-claude/potwor/scripts/klucze-dostepne.sh ceidg-token)
printf '%s\n%s\n' "$T" "$(openssl rand -hex 24)" | ssh -o IdentitiesOnly=yes -i ~/.ssh/lh-lejek -p 40022 serwer263249@serwer263249.lh.pl \
  "cd public_html/pan-stolarz.pl/formularz/ecopower && K=\$(echo $B64 | base64 -d) && php -r \"\$K\""
unset T KOD B64
```
Drugi przebieg nie zmienia `sol_ip` (zmiana soli zeruje tylko liczniki IP), a token odnawia. Przed pierwszym użyciem sprawdź `php -l` na koncie.
Test z żywego adresu (CORS i pamięć podręczna; NIP spółki Piotra, nie jednoosobowej działalności):
```bash
U=https://pan-stolarz.pl/formularz/ecopower/firma.php
curl -s -H 'Origin: https://www.ecopowerpolska.pl' "$U?nip=7011019372"                       # JSON: zrodla MF,KRS, cache:false
curl -s -H 'Origin: https://www.ecopowerpolska.pl' "$U?nip=7011019372" | grep -o '"cache":true'  # drugie wywołanie z pamięci
curl -s -o /dev/null -w '%{http_code}\n' "$U?nip=1234567890"                                    # 400 (zła suma kontrolna)
curl -s -o /dev/null -w '%{http_code}\n' -X POST "$U"                                          # 405
curl -s -o /dev/null -w '%{http_code}\n' https://pan-stolarz.pl/formularz/ecopower/stan/firma/7011019372.json   # 403
```
Limit CEIDG jest wspólny dla konta (nagłówek `x-rate-limit-limit: 1000`). Dokumentacja Hurtowni Danych CEIDG API v3 (v1.4, sekcja 3): „ograniczenie do 1000 żądań w okresie 60 minut” — okno 60 minut, nie doba; bezpiecznik `ceidg_limit_na_godz` (100/h) leży wielokrotnie poniżej. Limit dobowy MF: NIE USTALONO. Jednoosobowych działalności nie testuj w pętli.

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
