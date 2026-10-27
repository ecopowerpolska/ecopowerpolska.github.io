<?php
declare(strict_types=1);
/**
 * Formularz /kontakt/ www.ecopowerpolska.pl — handler PHP 8.4 na lh.pl (strona stoi na GitHub Pages, które PHP nie mają).
 * Wzorzec: pan-stolarz.pl serwer/formularz/index.php (Turnstile, honeypot, czas, licznik, pole `wyceny`).
 * Różnica: zwykły POST z innej domeny, więc KAŻDA odpowiedź to 303 — sukces na /dziekujemy/, błąd na /kontakt/?blad=<kod>#formularz.
 * Miejsce na koncie serwer263249: public_html/pan-stolarz.pl/formularz/ecopower/ (katalog formularz/ pomija --delete
 * przepływu build-i-wgranie pan-stolarz.pl). Konfiguracja: konfig/ecopowerpolska.pl.php (600), licznik: stan/. Opis: README.md.
 */

const DOMENA_KONFIGURACJI = 'ecopowerpolska.pl';
const STRONA = 'https://www.ecopowerpolska.pl';

const DOMYSLNE = [
    'do'               => 'kontakt@ecopowerpolska.pl',
    'od'               => 'formularz@pan-stolarz.pl',
    'dozwolone_origin' => ['https://www.ecopowerpolska.pl', 'https://ecopowerpolska.pl'],
    // Host, dla którego Cloudflare wydał token (pole `hostname` w siteverify). Pusta lista = host z Origin.
    'turnstile_hosty'  => ['www.ecopowerpolska.pl', 'ecopowerpolska.pl'],
    'limit_na_godzine' => 30,
    'min_sekund'       => 3,
    // Klucz TAJNY widżetu Turnstile — wyłącznie w konfig/ na koncie; pusty = kontrola pominięta z wpisem w logu.
    'turnstile_sekret' => '',
];

const TURNSTILE_SITEVERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
const MAKS_IMIE = 100;
const MAKS_TELEFON = 30;
const MAKS_EMAIL = 200;
const MAKS_ADRES = 300;
const MAKS_WIADOMOSC = 4000;
// Koszyk wycen: kontrakt src/lib/wyceny.ts (MAKS_WYCEN, MAKS_POZYCJI, MAKS_NAZWA, MAKS_JEDNOSTKA, MAKS_TYTUL).
const MAKS_WYCEN = 5;
const MAKS_WYCENY_BAJTY = 16384;
const MAKS_POZYCJI = 20;
const MAKS_NAZWA = 120;
const MAKS_JEDNOSTKA = 30;
const MAKS_TYTUL = 160;
const MAKS_KWOTA = 10000000;

// Wartości = src/data/kontakt.json (potrzeby[], technologie[], pory[]); zmiana w jednym miejscu = zmiana w drugim.
const POTRZEBY = [
    'wizytowka' => 'strona wizytówka', 'firmowa' => 'strona firmowa', 'rozbudowana' => 'strona rozbudowana',
    'sklep-maly' => 'sklep mały (do ok. 100 produktów)', 'sklep-duzy' => 'sklep duży', 'panel' => 'panel do edycji treści',
    'blog' => 'blog', 'wizytowka-google' => 'wizytówka Google', 'social' => 'Facebook i Instagram',
    'opieka' => 'opieka techniczna', 'inne' => 'inne',
];
const TECHNOLOGIE = [
    'woocommerce' => 'WooCommerce', 'prestashop' => 'PrestaShop', 'abonament' => 'platforma abonamentowa (Shoper, IdoSell)',
    'astro' => 'sklep na stronie Astro z koszykiem', 'nie-wiem' => 'nie wie, prosi o radę',
];
const PORY = ['rano' => '8–12', 'poludnie' => '12–16', 'wieczor' => '16–20'];

function przekieruj(string $adres): never {
    header('Cache-Control: no-store');
    header('Location: ' . $adres, true, 303);
    exit;
}

/** Błąd → powrót na formularz z kodem; strona /kontakt/ pokazuje zdanie z kontakt.json → bledy.<kod>. */
function blad(string $baza, string $kod): never {
    przekieruj(rtrim($baza, '/') . '/kontakt/?blad=' . rawurlencode($kod) . '#formularz');
}

function tekstPola(mixed $v, int $maks): ?string {
    if (!is_string($v) || !mb_check_encoding($v, 'UTF-8')) { return null; }
    $v = trim((string)preg_replace('/[\p{Cc}\x{2028}\x{2029}]+/u', ' ', str_replace("\u{00A0}", ' ', $v)));
    return mb_strlen($v) > $maks ? null : $v;
}

function kwota(mixed $v): ?float {
    return (is_int($v) || is_float($v)) && $v >= 0 && $v <= MAKS_KWOTA ? (float)$v : null;
}

function zl(float $x): string {
    $groszy = abs($x - round($x)) >= 0.005;
    return number_format($x, $groszy ? 2 : 0, ',', ' ') . ' zł';
}

/**
 * Pole `wyceny` (JSON z wyceny.ts → doWyslania()) → sprawdzone wyceny. Błędny zapis NIE odrzuca zapytania:
 * pozycja odpada, list mówi, ile odpadło ($odpadlo = -1: całe pole złe).
 */
function wycenyZPola(mixed $surowe, ?int &$odpadlo): array {
    $odpadlo = 0;
    if (!is_string($surowe) || trim($surowe) === '') { return []; }
    if (strlen($surowe) > MAKS_WYCENY_BAJTY) { $odpadlo = -1; return []; }
    try {
        $dane = json_decode($surowe, true, 6, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        $odpadlo = -1;
        return [];
    }
    if (!is_array($dane) || !array_is_list($dane)) { $odpadlo = -1; return []; }
    $wynik = [];
    foreach ($dane as $i => $w) {
        if ($i >= MAKS_WYCEN) { $odpadlo += count($dane) - MAKS_WYCEN; break; }
        $zle = !is_array($w) || !is_array($w['pozycje'] ?? null) || !array_is_list($w['pozycje']) || count($w['pozycje']) > MAKS_POZYCJI;
        $pozycje = [];
        if (!$zle) {
            foreach ($w['pozycje'] as $p) {
                $nazwa = is_array($p) ? tekstPola($p['nazwa'] ?? null, MAKS_NAZWA) : null;
                $jedn = is_array($p) ? tekstPola($p['jednostka'] ?? '', MAKS_JEDNOSTKA) : null;
                $ilosc = is_array($p) ? kwota($p['ilosc'] ?? null) : null;
                $cena = is_array($p) ? kwota($p['cena'] ?? null) : null;
                if ($nazwa === null || $nazwa === '' || $jedn === null || $ilosc === null || $cena === null) { $zle = true; break; }
                $pozycje[] = [$nazwa, $ilosc, $jedn, $cena];
            }
        }
        $netto = [];
        $brutto = [];
        if (!$zle) {
            foreach (['jednorazowo', 'miesiecznie', 'rocznie'] as $k) {
                $netto[$k] = kwota($w[$k] ?? 0);
                $brutto[$k] = kwota($w['brutto'][$k] ?? 0);
                if ($netto[$k] === null || $brutto[$k] === null) { $zle = true; }
            }
        }
        $tytul = $zle ? null : tekstPola($w['tytul'] ?? '', MAKS_TYTUL);
        if ($zle || $tytul === null || $pozycje === []) { $odpadlo++; continue; }
        $wynik[] = ['tytul' => $tytul, 'pozycje' => $pozycje, 'netto' => $netto, 'brutto' => $brutto];
    }
    return $wynik;
}

function sekcjaWycen(array $wyceny, int $odpadlo): string {
    if ($wyceny === [] && $odpadlo === 0) { return ''; }
    $nazwy = ['jednorazowo' => 'jednorazowo', 'miesiecznie' => 'miesięcznie', 'rocznie' => 'rocznie'];
    $s = '=== WYCENY Z KALKULATORA (' . count($wyceny) . ") ===\n";
    foreach ($wyceny as $i => $w) {
        $s .= '[' . ($i + 1) . '] ' . $w['tytul'] . "\n";
        foreach ($w['pozycje'] as [$nazwa, $ilosc, $jedn, $cena]) {
            $ile = $ilosc == 1 ? '' : ' × ' . rtrim(rtrim(number_format($ilosc, 2, ',', ''), '0'), ',');
            $s .= '    - ' . $nazwa . $ile . ': ' . zl($cena) . ($jedn !== '' ? ' (' . $jedn . ')' : '') . "\n";
        }
        foreach ($nazwy as $k => $n) {
            if ($w['netto'][$k] > 0) {
                $s .= '    Razem ' . $n . ': ' . zl($w['netto'][$k]) . ' netto · ' . zl($w['brutto'][$k]) . " brutto\n";
            }
        }
    }
    if ($odpadlo === -1) {
        $s .= "(pole z wycenami odrzucone: zły zapis albo za duże)\n";
    } elseif ($odpadlo > 0) {
        $s .= '(pominięto wyceny z błędnym zapisem: ' . $odpadlo . ")\n";
    }
    return $s . "\n";
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Tylko POST.\n";
    exit;
}

// Konfiguracja: konfig/<domena>.php wewnątrz katalogu handlera (separacja katalogów lh.pl, pułapka ⑱). Brak pliku = DOMYSLNE.
$k = DOMYSLNE;
$plikKonfiguracji = __DIR__ . '/konfig/' . DOMENA_KONFIGURACJI . '.php';
if (@is_file($plikKonfiguracji)) {
    $wczytane = require $plikKonfiguracji;
    if (is_array($wczytane)) {
        $k = array_replace($k, $wczytane);
    } else {
        error_log('formularz-ecopower: konfiguracja nie zwraca tablicy');
    }
}

// 1. Pochodzenie: przeglądarka wysyła Origin przy każdym POST z formularza. Obcy Origin = powrót na produkcyjny /kontakt/.
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
if (!in_array($origin, (array)$k['dozwolone_origin'], true)) {
    error_log('formularz-ecopower: niedozwolone pochodzenie: ' . substr($origin, 0, 100));
    blad(STRONA, 'inny');
}

// 2. Pułapka `dodatkowe`: bot dostaje to samo co człowiek (303 na /dziekujemy/), poczta nie idzie.
if (trim((string)($_POST['dodatkowe'] ?? '')) !== '') {
    przekieruj(rtrim($origin, '/') . '/dziekujemy/');
}

$adresKlienta = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');

// 3. Cloudflare Turnstile: token z pola `cf-turnstile-response`; siteverify z kluczem tajnym z konfig/.
$sekret = trim((string)($k['turnstile_sekret'] ?? ''));
if ($sekret === '') {
    error_log('formularz-ecopower: Turnstile wyłączony: brak turnstile_sekret w konfiguracji');
} else {
    $token = trim((string)($_POST['cf-turnstile-response'] ?? ''));
    if ($token === '' || strlen($token) > 2048) {
        blad($origin, 'turnstile');
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query(['secret' => $sekret, 'response' => $token, 'remoteip' => $adresKlienta]),
        'timeout' => 5,
        'ignore_errors' => true,
    ]]);
    $surowe = @file_get_contents(TURNSTILE_SITEVERIFY, false, $ctx);
    $wynik = is_string($surowe) ? json_decode($surowe, true) : null;
    $hosty = (array)($k['turnstile_hosty'] ?? []);
    if ($hosty === []) { $hosty = [(string)parse_url($origin, PHP_URL_HOST)]; }
    if (!is_array($wynik)) {
        error_log('formularz-ecopower: siteverify bez odpowiedzi - wiadomość przepuszczona bez weryfikacji');
    } elseif (($wynik['success'] ?? false) !== true) {
        $kody = (array)($wynik['error-codes'] ?? []);
        if (array_intersect($kody, ['invalid-input-secret', 'missing-input-secret']) !== []) {
            error_log('formularz-ecopower: BŁĘDNY turnstile_sekret (' . implode(',', $kody) . ') - wiadomość przepuszczona bez weryfikacji');
        } else {
            error_log('formularz-ecopower: Turnstile odrzucił token: ' . implode(',', $kody));
            blad($origin, 'turnstile');
        }
    } elseif (!in_array(strtolower((string)($wynik['hostname'] ?? '')), array_map('strtolower', $hosty), true)) {
        error_log('formularz-ecopower: token Turnstile dla innego hosta: ' . (string)($wynik['hostname'] ?? ''));
        blad($origin, 'turnstile');
    }
}

// 4. Czas wypełnienia: `t` = Date.now() z chwili załadowania strony (ms).
$t = trim((string)($_POST['t'] ?? ''));
if (!preg_match('/^\d{9,13}$/', $t)) {
    blad($origin, 'czas');
}
$tMs = strlen($t) <= 10 ? (int)$t * 1000 : (int)$t;
$uplynelo = (int)floor(microtime(true) * 1000) - $tMs;
if ($uplynelo >= 0 && $uplynelo < (int)$k['min_sekund'] * 1000) {
    blad($origin, 'czas');
}

// 5. Pola.
$imie = tekstPola($_POST['imie'] ?? '', MAKS_IMIE);
$email = tekstPola($_POST['email'] ?? '', MAKS_EMAIL);
$telefon = tekstPola($_POST['telefon'] ?? '', MAKS_TELEFON);
$adres = tekstPola($_POST['adres'] ?? '', MAKS_ADRES);
$wiadomosc = is_string($_POST['wiadomosc'] ?? '') && mb_check_encoding((string)($_POST['wiadomosc'] ?? ''), 'UTF-8')
    ? trim(str_replace("\r\n", "\n", (string)($_POST['wiadomosc'] ?? ''))) : null;
$zgoda = trim((string)($_POST['zgoda'] ?? '')) !== '';

if ($imie === null || $imie === '') { blad($origin, 'imie'); }
if ($email === null || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { blad($origin, 'email'); }
if ($telefon === null || ($telefon !== '' && (!preg_match('/^[0-9 +()\-]+$/', $telefon) || strlen((string)preg_replace('/\D/', '', $telefon)) < 9))) {
    blad($origin, 'telefon');
}
if ($adres === null) { blad($origin, 'adres'); }
if ($wiadomosc === null || mb_strlen($wiadomosc) > MAKS_WIADOMOSC) { blad($origin, 'wiadomosc'); }

$potrzeby = array_values(array_unique(array_filter(
    array_map(static fn($v) => is_string($v) ? trim($v) : '', (array)($_POST['potrzeby'] ?? [])),
    static fn(string $v) => isset(POTRZEBY[$v])
)));
$jeden = static function (string $pole, array $lista): string {
    $v = $_POST[$pole] ?? '';
    $v = is_string($v) ? trim($v) : '';
    return isset($lista[$v]) ? $v : '';
};
$technologia = $jeden('technologia', TECHNOLOGIE);
$pora = $jeden('pora', PORY);
$strona = $jeden('strona', ['tak' => 1, 'nie' => 1]);
$sklep = array_intersect($potrzeby, ['sklep-maly', 'sklep-duzy']) !== [];
if (!$sklep) { $technologia = ''; }

$odpadlo = 0;
$wyceny = wycenyZPola($_POST['wyceny'] ?? null, $odpadlo);
// Wiadomość nieobowiązkowa, gdy zapytanie niesie wybór zakresu albo wycenę (formularz zdejmuje wtedy `required`).
if ($wiadomosc === '' && $potrzeby === [] && $wyceny === []) { blad($origin, 'wiadomosc'); }
if (!$zgoda) { blad($origin, 'zgoda'); }
if (preg_match('/[\r\n]/', $imie . $email . $telefon)) { blad($origin, 'inny'); }

// 6. Licznik godzinowy pod blokadą — chroni wspólny limit poczty konta lh.pl (150/h).
$katStan = __DIR__ . '/stan';
if (!is_dir($katStan)) { @mkdir($katStan, 0700, true); }
$plikLicznika = $katStan . '/formularz-' . date('YmdH') . '.licznik';
$uchwyt = @fopen($plikLicznika, 'c+');
if ($uchwyt === false || !flock($uchwyt, LOCK_EX)) {
    error_log('formularz-ecopower: licznik niedostępny (' . $plikLicznika . ') — limit NIE jest egzekwowany');
    if ($uchwyt !== false) { fclose($uchwyt); }
} else {
    $ile = (int)stream_get_contents($uchwyt);
    if ($ile >= (int)$k['limit_na_godzine']) {
        flock($uchwyt, LOCK_UN);
        fclose($uchwyt);
        blad($origin, 'limit');
    }
    ftruncate($uchwyt, 0);
    rewind($uchwyt);
    fwrite($uchwyt, (string)($ile + 1));
    fflush($uchwyt);
    flock($uchwyt, LOCK_UN);
    fclose($uchwyt);
}

// 7. List: nadawca z domeny konta wysyłającego (SPF lh.pl), Reply-To = klient.
$potrzebyTekst = implode(', ', array_map(static fn(string $v) => POTRZEBY[$v], $potrzeby));
$temat = 'Zapytanie ze strony ecopowerpolska.pl'
    . ($potrzeby !== [] ? ' · ' . $potrzebyTekst : '')
    . ($wyceny !== [] ? ' · wyceny: ' . count($wyceny) : '');
if (mb_strlen($temat) > 180) { $temat = mb_substr($temat, 0, 177) . '…'; }
$cialo = "Imię: $imie\n"
    . "E-mail: $email\n"
    . 'Telefon: ' . ($telefon !== '' ? $telefon : '—') . "\n"
    . 'Kiedy dzwonić: ' . ($pora !== '' ? PORY[$pora] : 'dowolnie') . "\n\n"
    . sekcjaWycen($wyceny, $odpadlo)
    . "=== WIADOMOŚĆ KLIENTA ===\n"
    . ($wiadomosc !== '' ? $wiadomosc : '(bez wiadomości)') . "\n\n"
    . "=== WYBÓR ===\n"
    . 'Czego potrzebuje: ' . ($potrzeby !== [] ? $potrzebyTekst : 'nie zaznaczono') . "\n"
    . ($sklep ? 'Na czym sklep: ' . ($technologia !== '' ? TECHNOLOGIE[$technologia] : 'nie zaznaczono') . "\n" : '')
    . 'Ma już stronę: ' . ($strona === 'tak' ? 'tak' . ($adres !== '' ? ', ' . $adres : '') : ($strona === 'nie' ? 'nie' : 'nie zaznaczono')) . "\n\n"
    . "Strona: $origin/kontakt/\n"
    . 'IP: ' . $adresKlienta . "\n"
    . 'Czas: ' . date('c') . "\n";

$naglowki = 'From: Formularz ecopowerpolska.pl <' . $k['od'] . ">\r\n"
    . "Reply-To: $email\r\n"
    . "MIME-Version: 1.0\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: 8bit\r\n";
$wyslano = mail((string)$k['do'], '=?UTF-8?B?' . base64_encode($temat) . '?=', $cialo, $naglowki);
if (!$wyslano) {
    @file_put_contents($katStan . '/formularz-niewyslane.log', date('c') . ' ' . $email . "\n", FILE_APPEND | LOCK_EX);
    blad($origin, 'wysylka');
}
@file_put_contents($katStan . '/wyslane.log', date('c') . ' wyslano' . ($wyceny !== [] ? ' wyceny=' . count($wyceny) : '') . "\n", FILE_APPEND | LOCK_EX);

przekieruj(rtrim($origin, '/') . '/dziekujemy/');
