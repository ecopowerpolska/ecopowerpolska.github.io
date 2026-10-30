<?php
declare(strict_types=1);
/**
 * Audyt obcej strony dla darmowych narzędzi (www.ecopowerpolska.pl/darmowe-narzedzia/).
 * GET audyt.php?test=<nazwa>&url=<adres> → JSON {test, url, url_koncowy, pobrano, cache, wynik:{ocena, kolor, pozycje[], surowe?}}
 * albo {blad, opis}. Kontrakt modułów i pól: KONTRAKT.md (runda 9). Stoi obok firma.php (ten sam katalog, konfig/, stan/).
 *
 * Kolejność: metoda → Origin → test (biała lista) → adres (publiczny host) → pamięć podręczna 1 h → limit na IP → pobranie strony
 * (raz, wspólne) → moduł testy/<nazwa>.php. Pobieramy jedną stronę jak przeglądarka; adresów ani treści nie zapisujemy
 * poza pamięcią podręczną 1 h (klucz = skrót), dziennik niesie tylko czas, test i kod.
 */
define('AUDYT_EP', true);

const DOMENA_KONFIGURACJI = 'ecopowerpolska.pl';

/** Nazwy modułów (slug z myślnikami → podkreślniki). Plik testy/<nazwa>.php musi istnieć. */
const BIALA_LISTA = [
    'wyszukiwanie_agentowe_ai', 'dostepnosc', 'bezpieczenstwo_i_zaufanie', 'poczta_domeny', 'seo_techniczne',
    'waga_i_media', 'prawo_na_stronie', 'dane_terenowe_crux', 'ochrona_przed_spamem',
];

const DOMYSLNE = [
    'dozwolone_origin'    => ['https://www.ecopowerpolska.pl', 'https://ecopowerpolska.pl'],
    // Sól skrótu adresu IP w stan/audyt-ip/ (ta sama co w firma.php). Pusta = sól zapasowa, słabsza.
    'sol_ip'              => '',
    'audyt_limit_na_godz' => 20,     // sprawdzeń na jeden adres IP w godzinie (trafienia w pamięć podręczną nie liczą się)
    'cache_sekundy'       => 3600,
    // Klucz serwerowy PageSpeed dla testów, które PHP woła po stronie serwera (lekkie). Pusty = bez PSI.
    'psi_klucz'           => '',
];

require __DIR__ . '/testy/_wspolne.php';

function opisBledu(string $kod): string {
    return [
        'url'      => 'Nie rozpoznajemy tego adresu. Wpisz na przykład twojafirma.pl (adresy lokalne i prywatne są wykluczone).',
        'test'     => 'Nieznany test.',
        'limit'    => 'Zbyt wiele sprawdzeń z tego adresu. Spróbuj za godzinę.',
        'pobranie' => 'Nie udało się pobrać tej strony. Sprawdź, czy adres działa i jest publicznie dostępny.',
        'zrodlo'   => 'Test nie mógł się zakończyć. Spróbuj za chwilę.',
        'origin'   => 'To narzędzie działa tylko na stronie www.ecopowerpolska.pl.',
        'metoda'   => 'Ten adres przyjmuje tylko zapytania GET.',
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
    echo json_encode($dane, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

/** Dziennik bez adresów i IP: tylko czas, test i kod. */
function dziennik(string $katStan, string $wiersz): void {
    @file_put_contents($katStan . '/audyt-' . date('Ymd') . '.log', date('c') . ' ' . $wiersz . "\n", FILE_APPEND | LOCK_EX);
}

function blad(int $status, string $kod, ?string $originDozwolony, string $katStan, string $test = '-'): never {
    dziennik($katStan, 'test=' . $test . ' blad=' . $kod);
    if ($kod === 'limit') { header('Retry-After: 3600'); }
    odpowiedz($status, ['blad' => $kod, 'opis' => opisBledu($kod)], $originDozwolony);
}

/** Co jakiś czas sprząta stare pliki pamięci podręcznej, liczników i dzienników. */
function sprzatnij(string $katStan): void {
    if (random_int(1, 100) !== 1) { return; }
    $teraz = time();
    foreach (['audyt' => 7200, 'audyt-ip' => 7200] as $podkat => $wiek) {
        foreach (glob($katStan . '/' . $podkat . '/*.json') ?: [] as $p) {
            if ($teraz - (int)@filemtime($p) > $wiek) { @unlink($p); }
        }
    }
    foreach (glob($katStan . '/audyt-*.log') ?: [] as $p) {
        if ($teraz - (int)@filemtime($p) > 30 * 86400) { @unlink($p); }
    }
}

date_default_timezone_set('Europe/Warsaw');
@set_time_limit(30);

$katStan = __DIR__ . '/stan';

$k = DOMYSLNE;
$plikKonfiguracji = __DIR__ . '/konfig/' . DOMENA_KONFIGURACJI . '.php';
if (@is_file($plikKonfiguracji)) {
    $wczytane = require $plikKonfiguracji;
    if (is_array($wczytane)) {
        $k = array_replace($k, $wczytane);
    } else {
        error_log('audyt-ecopower: konfiguracja nie zwraca tablicy');
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

// ── Test ────────────────────────────────────────────────────────────────────────────────────────────────────────
$nazwa = str_replace('-', '_', is_string($_GET['test'] ?? null) ? strtolower(trim((string)$_GET['test'])) : '');
$dopuszczony = in_array($nazwa, BIALA_LISTA, true);
$plikModulu = __DIR__ . '/testy/' . $nazwa . '.php';
if (!$dopuszczony || !preg_match('/^[a-z][a-z0-9_]*$/', $nazwa) || !is_file($plikModulu)) {
    blad(400, 'test', $originDozwolony, $katStan);
}

// ── Adres ───────────────────────────────────────────────────────────────────────────────────────────────────────
$surowyAdres = is_string($_GET['url'] ?? null) ? (string)$_GET['url'] : '';
$bezSchematu = !preg_match('#^[a-z][a-z0-9+.\-]*://#i', trim($surowyAdres));
$adres = ep_normalizuj_url($surowyAdres);
if ($adres === null || ep_ip_bezpieczny($adres['host']) === null) {
    blad(400, 'url', $originDozwolony, $katStan, $nazwa);
}

foreach (['', '/audyt', '/audyt-ip'] as $podkat) {
    if (!is_dir($katStan . $podkat)) { @mkdir($katStan . $podkat, 0700, true); }
}
sprzatnij($katStan);

// ── Pamięć podręczna 1 h ────────────────────────────────────────────────────────────────────────────────────────
$plikCache = $katStan . '/audyt/' . sha1($nazwa . '|' . $adres['url']) . '.json';
$zCache = is_file($plikCache) ? json_decode((string)@file_get_contents($plikCache), true) : null;
if (is_array($zCache) && isset($zCache['t'], $zCache['dane']) && is_array($zCache['dane']) && time() - (int)$zCache['t'] < (int)$k['cache_sekundy']) {
    $odp = $zCache['dane'];
    $odp['cache'] = true;
    dziennik($katStan, 'test=' . $nazwa . ' ok cache');
    odpowiedz(200, $odp, $originDozwolony);
}

// ── Limit na skrót IP ───────────────────────────────────────────────────────────────────────────────────────────
$ip = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
if (filter_var($ip, FILTER_VALIDATE_IP) === false) { $ip = (string)($_SERVER['REMOTE_ADDR'] ?? ''); }
$sol = (string)$k['sol_ip'] !== '' ? (string)$k['sol_ip'] : 'ecopower-audyt-zapas';
$skrotIp = hash('sha256', $sol . '|' . $ip);
if (!licznik_godzinowy($katStan . '/audyt-ip/' . $skrotIp . '.json', (int)$k['audyt_limit_na_godz'])) {
    blad(429, 'limit', $originDozwolony, $katStan, $nazwa);
}

// ── Wspólne pobranie strony (raz) ───────────────────────────────────────────────────────────────────────────────
ep_start();
$opcje = ['metoda' => 'GET', 'limit' => EP_MAKS_HTML, 'timeout' => 8, 'przekierowania' => 5];
$r = ep_pobierz($adres['url'], $opcje);
// Brak schematu w zapisie użytkownika: https, a gdy port 443 nie przyjmuje połączeń (curl 7) — jeszcze raz po http.
if ($bezSchematu && $r['kod'] === 0 && $r['errno'] === 7 && $adres['schemat'] === 'https') {
    $r = ep_pobierz('http://' . $adres['host'] . $adres['cel'], $opcje);
}
// Błąd TLS (certyfikat wygasły, obcy, niepełny łańcuch): tylko test bezpieczeństwa pobiera stronę ponownie BEZ weryfikacji, żeby zgłosić go
// jako wynik zamiast „nie udało się pobrać”. Inne testy dostają blad: pobranie; treść z niezaufanym certyfikatem nie jest nigdzie używana.
$tlsBlad = 0;
if ($r['kod'] === 0 && $nazwa === 'bezpieczenstwo_i_zaufanie' && in_array($r['errno'], [35, 51, 58, 60, 77, 83, 90, 91], true)) {
    $tlsBlad = (int)$r['errno'];
    $r = ep_pobierz($adres['url'], $opcje + ['bez_weryfikacji_tls' => true]);
}
if ($r['kod'] === 0 || $r['blad'] === 'adres_niedozwolony') {
    blad(502, 'pobranie', $originDozwolony, $katStan, $nazwa);
}
$html = ep_utf8($r['body'], $r['naglowki']);
$koncowy = ep_normalizuj_url($r['url_koncowy']);
$ctx = [
    'url'            => $adres['url'],
    'url_koncowy'    => $r['url_koncowy'],
    'host'           => $koncowy['host'] ?? $adres['host'],
    'kod'            => $r['kod'],
    'naglowki'       => $r['naglowki'],
    'html'           => $html,
    'czas_ms'        => $r['czas_ms'],
    'przekierowania' => $r['przekierowania'],
    'konfig'         => ['psi_klucz' => (string)$k['psi_klucz']],
    'tls_blad'       => $tlsBlad,
    // dodatki: łączny czas pobrania, rozmiar ciała po dekompresji, czy ucięto przy limicie 2 MB
    'czas_pobrania_ms' => $r['czas_pobrania_ms'],
    'rozmiar'        => $r['rozmiar'],
    'obcieto'        => $r['obcieto'],
];

// ── Moduł ───────────────────────────────────────────────────────────────────────────────────────────────────────
try {
    require_once $plikModulu;
    $funkcja = 'test_' . $nazwa;
    if (!function_exists($funkcja)) { throw new RuntimeException('brak funkcji ' . $funkcja); }
    $wynik = $funkcja($adres['url'], $ctx);
} catch (Throwable $e) {
    error_log('audyt-ecopower: ' . $nazwa . ': ' . $e->getMessage());
    blad(502, 'zrodlo', $originDozwolony, $katStan, $nazwa);
}
if (!is_array($wynik) || !isset($wynik['pozycje']) || !is_array($wynik['pozycje'])) {
    error_log('audyt-ecopower: ' . $nazwa . ': moduł nie zwrócił pozycji');
    blad(502, 'zrodlo', $originDozwolony, $katStan, $nazwa);
}

// Normalizacja wyniku: każda pozycja przez pozycja() (obcięcie długości, dozwolone stany), ocena i kolor uzupełnione.
$pozycje = [];
foreach ($wynik['pozycje'] as $p) {
    if (!is_array($p) || !isset($p['id'])) { continue; }
    $pozycje[] = pozycja((string)$p['id'], (string)($p['nazwa'] ?? $p['id']), (string)($p['stan'] ?? 'info'), $p['wartosc'] ?? '', (string)($p['opis'] ?? ''), (string)($p['zalecenie'] ?? ''));
}
$ocena = array_key_exists('ocena', $wynik) ? ($wynik['ocena'] === null ? null : max(0, min(100, (int)$wynik['ocena']))) : ocena_z_pozycji($pozycje);
$gotowy = ['ocena' => $ocena, 'kolor' => kolor($ocena), 'pozycje' => $pozycje];
if (isset($wynik['surowe']) && is_array($wynik['surowe'])) { $gotowy['surowe'] = $wynik['surowe']; }

$odp = [
    'test'        => $nazwa,
    'url'         => $adres['url'],
    'url_koncowy' => $r['url_koncowy'],
    'pobrano'     => date('c'),
    'cache'       => false,
    'wynik'       => $gotowy,
];
@file_put_contents($plikCache, json_encode(['t' => time(), 'dane' => $odp], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR), LOCK_EX);
@chmod($plikCache, 0600);
dziennik($katStan, 'test=' . $nazwa . ' ok kod=' . $r['kod']);
odpowiedz(200, $odp, $originDozwolony);
