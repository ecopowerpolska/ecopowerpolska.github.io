<?php
declare(strict_types=1);
/**
 * Dane firmy po NIP dla generatorów regulaminu i polityki prywatności (www.ecopowerpolska.pl/darmowe-narzedzia/).
 * GET firma.php?nip=1234567890 → JSON {nazwa, forma_prawna, nip, regon, krs, adres{ulica, numer, kod, miasto, pelny}, status_vat,
 * zrodla[], pobrano, cache} albo {blad, opis}. Kontrakt i kody: README.md. Stoi obok wyslij.php (ten sam katalog i konfig/).
 *
 * Kolejność: walidacja NIP → pamięć podręczna → limit na IP → MF (biała lista) → KRS (gdy MF dało numer KRS)
 * → CEIDG (gdy MF nie zna podmiotu albo podmiot nie ma KRS, czyli jednoosobowa działalność / spółka cywilna).
 * NIP-ów nie zapisujemy w logach (NIP jednoosobowej działalności to dane osobowe); adres IP tylko jako skrót z solą.
 */

const DOMENA_KONFIGURACJI = 'ecopowerpolska.pl';

const DOMYSLNE = [
    'dozwolone_origin'    => ['https://www.ecopowerpolska.pl', 'https://ecopowerpolska.pl'],
    // Token CEIDG API v3 (Bearer) — wyłącznie w konfig/ na koncie. Pusty = źródło CEIDG pominięte.
    'ceidg_token'         => '',
    // Sól skrótu adresu IP w stan/firma-ip/. Pusta = sól zapasowa (działa, ale skrót jest słabszy).
    'sol_ip'              => '',
    'firma_limit_na_godz' => 30,    // wywołań urzędów na jeden adres IP w godzinie (trafienia w pamięć podręczną nie liczą się)
    'ceidg_limit_na_godz' => 100,   // globalny bezpiecznik: wywołań CEIDG w godzinie dla całej usługi (limit konta: 1000)
    'cache_sekundy'       => 86400,
    'cache_brak_sekundy'  => 900,
];

const MF_ADRES = 'https://wl-api.mf.gov.pl/api/search/nip/';
const KRS_ADRES = 'https://api-krs.ms.gov.pl/api/krs/OdpisAktualny/';
const CEIDG_ADRES = 'https://dane.biznes.gov.pl/api/ceidg/v3/firmy';
const TIMEOUT_SEK = 5;
const MAKS_ODPOWIEDZ = 3_000_000;

function opisBledu(string $kod): string {
    return [
        'nip'     => 'Numer NIP jest nieprawidłowy. Wpisz 10 cyfr, bez liter.',
        'brak'    => 'Nie znaleźliśmy firmy o tym numerze NIP w rejestrach. Uzupełnij dane ręcznie.',
        'limit'   => 'Zbyt wiele zapytań. Spróbuj za godzinę albo uzupełnij dane ręcznie.',
        'zrodlo'  => 'Rejestry urzędowe nie odpowiadają. Spróbuj za chwilę albo uzupełnij dane ręcznie.',
        'origin'  => 'To narzędzie działa tylko na stronie www.ecopowerpolska.pl.',
        'metoda'  => 'Ten adres przyjmuje tylko zapytania GET.',
    ][$kod] ?? 'Błąd.';
}

function odpowiedz(int $status, array $dane, ?string $originDozwolony): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Vary: Origin');
    if ($originDozwolony !== null) {
        header('Access-Control-Allow-Origin: ' . $originDozwolony);
    }
    echo json_encode($dane, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Dziennik bez NIP-ów i adresów IP: tylko data, kod wyniku i źródła. */
function dziennik(string $katStan, string $wiersz): void {
    @file_put_contents($katStan . '/firma-' . date('Ymd') . '.log', date('c') . ' ' . $wiersz . "\n", FILE_APPEND | LOCK_EX);
}

function blad(int $status, string $kod, ?string $originDozwolony, string $katStan, string $dodatek = ''): never {
    dziennik($katStan, 'blad=' . $kod . ($dodatek !== '' ? ' ' . $dodatek : ''));
    $glowki = $kod === 'limit' ? 3600 : 0;
    if ($glowki > 0) { header('Retry-After: ' . $glowki); }
    odpowiedz($status, ['blad' => $kod, 'opis' => opisBledu($kod)], $originDozwolony);
}

/** NIP: dopuszcza spacje, myślniki i przedrostek PL; wynik to 10 cyfr albo null. Suma kontrolna: wagi 6 5 7 2 3 4 5 6 7, mod 11. */
function normalizujNip(string $surowy): ?string {
    $s = preg_replace('/^\s*PL/i', '', $surowy) ?? '';
    $s = preg_replace('/[\s\-]+/', '', $s) ?? '';
    if (!preg_match('/^\d{10}$/', $s)) { return null; }
    $wagi = [6, 5, 7, 2, 3, 4, 5, 6, 7];
    $suma = 0;
    foreach ($wagi as $i => $w) { $suma += $w * (int)$s[$i]; }
    $kontrolna = $suma % 11;
    return ($kontrolna !== 10 && $kontrolna === (int)$s[9]) ? $s : null;
}

/** Zapytanie GET przez curl: TLS zweryfikowany, bez przekierowań, timeouty 5 s, limit rozmiaru. Zwraca [kod HTTP, ciało] albo [0, '']. */
function pobierz(string $url, array $naglowki = []): array {
    if (!function_exists('curl_init')) { return [0, '']; }
    $ch = curl_init($url);
    $ciało = '';
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET        => true,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_CONNECTTIMEOUT => TIMEOUT_SEK,
        CURLOPT_TIMEOUT        => TIMEOUT_SEK,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json', 'User-Agent: ecopowerpolska.pl-firma/1.0'], $naglowki),
        CURLOPT_WRITEFUNCTION  => static function ($ch, string $kawalek) use (&$ciało): int {
            $ciało .= $kawalek;
            return strlen($ciało) > MAKS_ODPOWIEDZ ? 0 : strlen($kawalek);
        },
    ]);
    $ok = curl_exec($ch);
    $kod = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $ok === false ? [0, ''] : [$kod, $ciało];
}

function jsonLubNull(string $ciało): ?array {
    if ($ciało === '') { return null; }
    $d = json_decode($ciało, true, 32);
    return is_array($d) ? $d : null;
}

function tekst(mixed $v): string {
    return is_string($v) ? trim((string)preg_replace('/\s+/u', ' ', $v)) : '';
}

/** „Spółka z ograniczoną…” zamiast wersalików (zwykła fraza; nazw firm NIE zmieniamy). */
function zdaniowo(string $s): string {
    if ($s === '') { return ''; }
    $m = mb_strtolower($s);
    return mb_strtoupper(mb_substr($m, 0, 1)) . mb_substr($m, 1);
}

/** „GEN. TADEUSZA BORA-KOMOROWSKIEGO 21/72, 03-982 WARSZAWA” → ulica, numer, kod, miasto (+ pełny jako ratunek). */
function rozbijAdresMf(?string $surowy): ?array {
    $pelny = tekst($surowy);
    if ($pelny === '') { return null; }
    $adres = ['ulica' => '', 'numer' => '', 'kod' => '', 'miasto' => '', 'pelny' => $pelny];
    $poz = strrpos($pelny, ',');
    $lewa = $poz === false ? $pelny : trim(substr($pelny, 0, $poz));
    $prawa = $poz === false ? '' : trim(substr($pelny, $poz + 1));
    if (preg_match('/^(\d{2}-\d{3})\s+(.+)$/u', $prawa, $m)) {
        $adres['kod'] = $m[1];
        $adres['miasto'] = $m[2];
    } elseif ($prawa !== '') {
        $adres['miasto'] = $prawa;
    }
    $numer = '\d+[A-Za-zĄĆĘŁŃÓŚŹŻąćęłńóśźż]?(?:\s*[\/\-]\s*\d+[A-Za-z]?)?(?:\s+(?:lok\.?|m\.?)\s*\d+[A-Za-z]?)?';
    if (preg_match('/^(.*?)\s+(' . $numer . ')$/iu', $lewa, $m)) {
        $adres['ulica'] = trim($m[1]);
        $adres['numer'] = trim($m[2]);
    } else {
        $adres['ulica'] = $lewa;
    }
    return $adres;
}

function adresZPol(string $ulica, string $budynek, string $lokal, string $kod, string $miasto): ?array {
    $numer = $budynek . ($lokal !== '' ? '/' . $lokal : '');
    if ($ulica === '' && $numer === '' && $kod === '' && $miasto === '') { return null; }
    $pelny = trim(trim($ulica . ' ' . $numer) . ($kod . $miasto !== '' ? ', ' . trim($kod . ' ' . $miasto) : ''), ', ');
    return ['ulica' => $ulica, 'numer' => $numer, 'kod' => $kod, 'miasto' => $miasto, 'pelny' => $pelny];
}

/** REGON z KRS bywa 14-cyfrowy z zerami na końcu (zakład) — siedziba ma 9 cyfr. */
function regon9(string $r): string {
    return strlen($r) === 14 && substr($r, 9) === '00000' ? substr($r, 0, 9) : $r;
}

function stanFirmy(string $nip): array {
    return [
        'nazwa' => '', 'forma_prawna' => '', 'nip' => $nip, 'regon' => '', 'krs' => '',
        'adres' => null, 'status_vat' => null, 'zrodla' => [],
    ];
}

/** MF: [wynik, podmiot|null]; wynik: 'ok' | 'brak' | 'blad'. */
function zapytajMf(string $nip): array {
    [$kod, $ciało] = pobierz(MF_ADRES . $nip . '?date=' . date('Y-m-d'));
    $d = jsonLubNull($ciało);
    if ($kod === 200 && is_array($d) && array_key_exists('result', $d)) {
        $p = $d['result']['subject'] ?? null;
        return is_array($p) ? ['ok', $p] : ['brak', null];
    }
    // MF odpowiada 400 z kodem WL-… na NIP, którego nie zna w tym formacie — traktujemy jak „brak”, inne kody jako awarię.
    if ($kod === 400 && is_array($d) && str_starts_with((string)($d['code'] ?? ''), 'WL-')) { return ['brak', null]; }
    return ['blad', null];
}

/** KRS: rejestr przedsiębiorców, a przy 404 rejestr stowarzyszeń i fundacji. */
function zapytajKrs(string $krs): ?array {
    foreach (['P', 'S'] as $rejestr) {
        [$kod, $ciało] = pobierz(KRS_ADRES . rawurlencode($krs) . '?rejestr=' . $rejestr . '&format=json');
        if ($kod === 200) {
            $d = jsonLubNull($ciało);
            $dzial1 = $d['odpis']['dane']['dzial1'] ?? null;
            return is_array($dzial1) ? $dzial1 : null;
        }
        if ($kod !== 404) { return null; }
    }
    return null;
}

/** CEIDG v3: lista po NIP zawiera pełny adres działalności; gdy brakuje miasta, dociągamy rekord z `link`. */
function zapytajCeidg(string $nip, string $token): array {
    $naglowek = ['Authorization: Bearer ' . $token];
    [$kod, $ciało] = pobierz(CEIDG_ADRES . '?nip=' . $nip, $naglowek);
    if ($kod === 204 || $kod === 404) { return ['brak', null]; }
    $d = jsonLubNull($ciało);
    if ($kod !== 200 || !is_array($d)) { return ['blad', null]; }
    $firmy = $d['firmy'] ?? [];
    if (!is_array($firmy) || $firmy === []) { return ['brak', null]; }
    // Aktywna przed wykreśloną; w razie braku — pierwsza.
    usort($firmy, static fn($a, $b) => (($b['status'] ?? '') === 'AKTYWNY') <=> (($a['status'] ?? '') === 'AKTYWNY'));
    $f = $firmy[0];
    if (!is_array($f)) { return ['brak', null]; }
    if (tekst($f['adresDzialalnosci']['miasto'] ?? null) === '') {
        $link = tekst($f['link'] ?? null);
        if (str_starts_with($link, 'https://dane.biznes.gov.pl/api/ceidg/v3/firma/')) {
            [$k2, $c2] = pobierz($link, $naglowek);
            $d2 = jsonLubNull($c2);
            $f2 = $k2 === 200 && is_array($d2) ? ($d2['firma'][0] ?? $d2['firmy'][0] ?? $d2) : null;
            if (is_array($f2) && isset($f2['adresDzialalnosci'])) { $f = array_replace($f, $f2); }
        }
    }
    return ['ok', $f];
}

/** Liczy wywołania w godzinie pod blokadą; zwraca false, gdy limit przekroczony (przy braku zapisu — true z wpisem w logu). */
function licznikGodzinowy(string $plik, int $limit): bool {
    $uchwyt = @fopen($plik, 'c+');
    if ($uchwyt === false || !flock($uchwyt, LOCK_EX)) {
        error_log('firma-ecopower: licznik niedostępny — limit NIE jest egzekwowany');
        if ($uchwyt !== false) { fclose($uchwyt); }
        return true;
    }
    $zapis = json_decode((string)stream_get_contents($uchwyt), true);
    $okno = date('YmdH');
    $n = is_array($zapis) && ($zapis['okno'] ?? '') === $okno ? (int)($zapis['n'] ?? 0) : 0;
    $wolno = $n < $limit;
    if ($wolno) {
        ftruncate($uchwyt, 0);
        rewind($uchwyt);
        fwrite($uchwyt, json_encode(['okno' => $okno, 'n' => $n + 1]));
        fflush($uchwyt);
    }
    flock($uchwyt, LOCK_UN);
    fclose($uchwyt);
    @chmod($plik, 0600);
    return $wolno;
}

/** Co jakiś czas sprząta stare pliki pamięci podręcznej, liczników i dzienników. */
function sprzatnij(string $katStan): void {
    if (random_int(1, 100) !== 1) { return; }
    $teraz = time();
    foreach (['firma' => 90000, 'firma-ip' => 7200] as $podkat => $wiek) {
        foreach (glob($katStan . '/' . $podkat . '/*.json') ?: [] as $p) {
            if ($teraz - (int)@filemtime($p) > $wiek) { @unlink($p); }
        }
    }
    foreach (glob($katStan . '/firma-*.log') ?: [] as $p) {
        if ($teraz - (int)@filemtime($p) > 30 * 86400) { @unlink($p); }
    }
}

date_default_timezone_set('Europe/Warsaw');
@set_time_limit(30);

$katStan = __DIR__ . '/stan';

// Konfiguracja: konfig/<domena>.php wewnątrz katalogu handlera (separacja katalogów lh.pl, pułapka ⑱). Brak pliku = DOMYSLNE.
$k = DOMYSLNE;
$plikKonfiguracji = __DIR__ . '/konfig/' . DOMENA_KONFIGURACJI . '.php';
if (@is_file($plikKonfiguracji)) {
    $wczytane = require $plikKonfiguracji;
    if (is_array($wczytane)) {
        $k = array_replace($k, $wczytane);
    } else {
        error_log('firma-ecopower: konfiguracja nie zwraca tablicy');
    }
}

$dozwolone = (array)$k['dozwolone_origin'];
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$originDozwolony = in_array($origin, $dozwolone, true) ? $origin : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    odpowiedz(405, ['blad' => 'metoda', 'opis' => opisBledu('metoda')], $originDozwolony);
}

// Obcy Origin (przeglądarka na cudzej stronie): odmowa bez nagłówka CORS. Brak Origin (curl, test) przechodzi.
if ($origin !== '' && $originDozwolony === null) {
    blad(403, 'origin', null, $katStan);
}

$nip = normalizujNip(is_string($_GET['nip'] ?? null) ? (string)$_GET['nip'] : '');
if ($nip === null) {
    blad(400, 'nip', $originDozwolony, $katStan);
}

foreach (['', '/firma', '/firma-ip'] as $podkat) {
    if (!is_dir($katStan . $podkat)) { @mkdir($katStan . $podkat, 0700, true); }
}
sprzatnij($katStan);

// Pamięć podręczna 24 h (wynik „brak” 15 min). Trafienie nie zużywa limitu IP ani wywołań urzędów.
$plikCache = $katStan . '/firma/' . $nip . '.json';
$zCache = is_file($plikCache) ? json_decode((string)@file_get_contents($plikCache), true) : null;
if (is_array($zCache) && isset($zCache['t'])) {
    $wiek = time() - (int)$zCache['t'];
    if (isset($zCache['dane']) && is_array($zCache['dane']) && $wiek < (int)$k['cache_sekundy']) {
        $wynik = $zCache['dane'];
        $wynik['cache'] = true;
        dziennik($katStan, 'ok cache');
        odpowiedz(200, $wynik, $originDozwolony);
    }
    if (($zCache['brak'] ?? false) === true && $wiek < (int)$k['cache_brak_sekundy']) {
        blad(404, 'brak', $originDozwolony, $katStan, 'cache');
    }
}

// Limit na adres IP — skrót z solą, nie goły adres.
$ip = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
if (filter_var($ip, FILTER_VALIDATE_IP) === false) { $ip = (string)($_SERVER['REMOTE_ADDR'] ?? ''); }
$sol = (string)$k['sol_ip'] !== '' ? (string)$k['sol_ip'] : 'ecopower-firma-zapas';
$skrotIp = hash('sha256', $sol . '|' . $ip);
if (!licznikGodzinowy($katStan . '/firma-ip/' . $skrotIp . '.json', (int)$k['firma_limit_na_godz'])) {
    blad(429, 'limit', $originDozwolony, $katStan, 'ip');
}

// --- Źródła ---------------------------------------------------------------------------------------------------------
$firma = stanFirmy($nip);
$awarie = [];

[$wynikMf, $mf] = zapytajMf($nip);
if ($wynikMf === 'blad') { $awarie[] = 'MF'; }
$krsNumer = '';
if ($mf !== null) {
    $firma['zrodla'][] = 'MF';
    $firma['nazwa'] = tekst($mf['name'] ?? null);
    $firma['regon'] = regon9(tekst($mf['regon'] ?? null));
    $krsNumer = preg_match('/^\d{1,10}$/', tekst($mf['krs'] ?? null)) ? str_pad(tekst($mf['krs']), 10, '0', STR_PAD_LEFT) : '';
    $firma['krs'] = $krsNumer;
    $status = tekst($mf['statusVat'] ?? null);
    $firma['status_vat'] = $status !== '' ? $status : null;
    // Spółka (z KRS): siedziba = residenceAddress; bez KRS: miejsce działalności = workingAddress, w razie braku residenceAddress.
    $surowyAdres = $krsNumer !== ''
        ? ($mf['residenceAddress'] ?? $mf['workingAddress'] ?? null)
        : ($mf['workingAddress'] ?? $mf['residenceAddress'] ?? null);
    $firma['adres'] = rozbijAdresMf(is_string($surowyAdres) ? $surowyAdres : null);
}

if ($krsNumer !== '') {
    $d1 = zapytajKrs($krsNumer);
    if ($d1 === null) {
        $awarie[] = 'KRS';
    } else {
        $firma['zrodla'][] = 'KRS';
        $podmiot = (array)($d1['danePodmiotu'] ?? []);
        if (tekst($podmiot['nazwa'] ?? null) !== '') { $firma['nazwa'] = tekst($podmiot['nazwa']); }
        $firma['forma_prawna'] = zdaniowo(tekst($podmiot['formaPrawna'] ?? null));
        if ($firma['regon'] === '') { $firma['regon'] = regon9(tekst($podmiot['identyfikatory']['regon'] ?? null)); }
        $a = (array)($d1['siedzibaIAdres']['adres'] ?? []);
        $adresKrs = adresZPol(
            tekst($a['ulica'] ?? null),
            tekst($a['nrDomu'] ?? null),
            tekst($a['nrLokalu'] ?? null),
            tekst($a['kodPocztowy'] ?? null),
            tekst($a['miejscowosc'] ?? null),
        );
        if ($adresKrs !== null) { $firma['adres'] = $adresKrs; }
    }
}

// CEIDG: podmiot nieznany MF albo bez KRS (jednoosobowa działalność, spółka cywilna). Bez tokenu — źródło pominięte.
$token = trim((string)$k['ceidg_token']);
$ceidgPominiety = false;
if ($krsNumer === '' && $token !== '') {
    if (!licznikGodzinowy($katStan . '/firma-ceidg-globalny.json', (int)$k['ceidg_limit_na_godz'])) {
        $ceidgPominiety = true;
        dziennik($katStan, 'ceidg-bezpiecznik');
    } else {
        [$wynikC, $f] = zapytajCeidg($nip, $token);
        if ($wynikC === 'blad') {
            $awarie[] = 'CEIDG';
        } elseif ($f !== null) {
            $firma['zrodla'][] = 'CEIDG';
            if (tekst($f['nazwa'] ?? null) !== '') { $firma['nazwa'] = tekst($f['nazwa']); }
            $firma['forma_prawna'] = 'Jednoosobowa działalność gospodarcza';
            $w = (array)($f['wlasciciel'] ?? []);
            if ($firma['regon'] === '') { $firma['regon'] = tekst($w['regon'] ?? null); }
            $a = (array)($f['adresDzialalnosci'] ?? []);
            $adresC = adresZPol(
                tekst($a['ulica'] ?? null),
                tekst($a['budynek'] ?? null),
                tekst($a['lokal'] ?? null),
                tekst($a['kod'] ?? null),
                tekst($a['miasto'] ?? null),
            );
            if ($adresC !== null) { $firma['adres'] = $adresC; }
            if (tekst($f['status'] ?? null) !== '') { $firma['status_dzialalnosci'] = tekst($f['status']); }
        }
    }
}

if ($firma['zrodla'] === []) {
    if ($awarie !== []) { blad(502, 'zrodlo', $originDozwolony, $katStan, 'zrodla=' . implode(',', $awarie)); }
    if ($ceidgPominiety) { blad(429, 'limit', $originDozwolony, $katStan, 'ceidg'); }
    // Nikt nie zna tego NIP-u. Krótka pamięć podręczna, żeby ten sam błędny numer nie zjadał wywołań.
    @file_put_contents($plikCache, json_encode(['t' => time(), 'brak' => true]), LOCK_EX);
    @chmod($plikCache, 0600);
    blad(404, 'brak', $originDozwolony, $katStan);
}

if ($firma['adres'] === null) { $firma['adres'] = ['ulica' => '', 'numer' => '', 'kod' => '', 'miasto' => '', 'pelny' => '']; }
$firma['pobrano'] = date('Y-m-d\TH:i');
$firma['cache'] = false;

// Zapis do pamięci podręcznej tylko, gdy żadne źródło nie zawiodło (częściowy wynik nie może wisieć 24 h).
if ($awarie === [] && !$ceidgPominiety) {
    $tmp = $plikCache . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, json_encode(['t' => time(), 'dane' => $firma], JSON_UNESCAPED_UNICODE)) !== false) {
        @chmod($tmp, 0600);
        @rename($tmp, $plikCache);
    }
}
dziennik($katStan, 'ok zrodla=' . implode(',', $firma['zrodla']) . ($awarie !== [] ? ' awarie=' . implode(',', $awarie) : ''));
odpowiedz(200, $firma, $originDozwolony);
