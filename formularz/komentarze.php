<?php
declare(strict_types=1);
/**
 * Komentarze pod wpisami ambasadapodroznikow.pl (strona na GitHub Pages, bez zapisu danych) - zaplecze PHP na lh.pl.
 * Adres: https://api.ecopowerpolska.pl/komentarze.php (konto serwer263249, public_html/api.ecopowerpolska.pl/).
 * Kontrakt (GET lista, GET wszystkie, POST, moderacja, tryb CLI), pliki stanu i klucze konfiguracji: README.md, sekcja "komentarze.php".
 * Zapis: stan/komentarze/<strona>/<wpis>.json pod flock. Liczniki: stan/komentarze-limity.json (kasowane po dobie).
 */

date_default_timezone_set('Europe/Warsaw');

const DOMENA_KONFIGURACJI = 'ecopowerpolska.pl';
const TURNSTILE_SITEVERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
const MAKS_ZADANIE_BAJTY = 32768;
const MAKS_AUTOR = 60;
const MIN_AUTOR = 2;
const MAKS_TRESC = 2000;
const MIN_TRESC = 3;
const MAKS_EMAIL = 200;
const MAKS_LINKOW_BEZ_MODERACJI = 2;
const PAMIEC_WPISU_OK = 86400;      // 24 h
const PAMIEC_WPISU_BRAK = 600;      // 10 min: nowy wpis ma szansę się opublikować
const KODY_HTTP = ['autor' => 400, 'tresc' => 400, 'turnstile' => 403, 'limit' => 429, 'wpis' => 400, 'wysylka' => 500, 'inny' => 400];

// Zakresy Cloudflare (https://www.cloudflare.com/ips-v4 i ips-v6, odczyt 2026-11-04): tylko z nich wierzymy w CF-Connecting-IP.
const CLOUDFLARE_ZAKRESY = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
    '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
];

const DOMYSLNE = [
    'komentarze_strony'          => ['ambasadapodroznikow.pl'],
    'komentarze_klucz'           => '',                          // klucz HMAC linków moderacji; pusty = moderacja z maila wyłączona
    'komentarze_mail_do'         => 'kontakt@ecopowerpolska.pl',
    'komentarze_url'             => 'https://api.ecopowerpolska.pl/komentarze.php',
    'komentarze_turnstile_hosty' => ['ambasadapodroznikow.pl'],
    'komentarze_limit_10min'     => 5,
    'komentarze_limit_doba'      => 20,
    'komentarze_limit_globalnie' => 300,
    'komentarze_maks_na_wpis'    => 500,
    'komentarze_head_na_godz'    => 100,                         // bezpiecznik: nowe sprawdzenia istnienia wpisu u GitHub Pages
    'komentarze_proxy'           => [],                          // dodatkowe zakresy CIDR zaufane dla CF-Connecting-IP
    // --- wyłącznie do testów lokalnych ---
    'komentarze_poczta_plik'     => '',                          // niepusty = mail() zastąpiony dopisaniem listu do tego pliku
    'komentarze_wpisy_testowe'   => null,                        // lista slugów zamiast HEAD wpisu na GitHub Pages
    'komentarze_siteverify_url'  => '',                          // inny adres siteverify
    'od'                         => 'formularz@pan-stolarz.pl',
    'turnstile_sekret'           => '',
];

// ---------------------------------------------------------------- konfiguracja, stan, log

function konfig(): array {
    static $k = null;
    if ($k !== null) { return $k; }
    $k = DOMYSLNE;
    $plik = __DIR__ . '/konfig/' . DOMENA_KONFIGURACJI . '.php';
    if (@is_file($plik)) {
        $w = require $plik;
        if (is_array($w)) { $k = array_replace($k, $w); } else { loguj('konfiguracja nie zwraca tablicy'); }
    }
    return $k;
}

function katStanu(): string {
    $d = __DIR__ . '/stan';
    if (!is_dir($d)) { @mkdir($d, 0700, true); }
    return $d;
}

function loguj(string $wiersz): void {
    $plik = katStanu() . '/komentarze.log';
    if (@filesize($plik) > 524288) { @rename($plik, $plik . '.1'); }
    @file_put_contents($plik, date('c') . ' ' . str_replace(["\r", "\n"], ' ', substr($wiersz, 0, 500)) . "\n", FILE_APPEND | LOCK_EX);
}

function slugOk(mixed $s): bool {
    return is_string($s) && preg_match('/^[a-z0-9-]{1,120}$/D', $s) === 1;
}

function stronaOk(mixed $s, array $k): bool {
    return is_string($s) && in_array($s, (array)$k['komentarze_strony'], true) && preg_match('/^[a-z0-9.-]{1,100}$/D', $s) === 1;
}

function idOk(mixed $s): bool {
    return is_string($s) && preg_match('/^[0-9a-f]{16}$/D', $s) === 1;
}

/** Ścieżka wyłącznie ze zwalidowanych: strona z listy konfiguracji i slug. */
function plikWpisu(string $strona, string $slug, bool $utworz): string {
    $kat = katStanu() . '/komentarze/' . $strona;
    if ($utworz && !is_dir($kat)) { @mkdir($kat, 0700, true); }
    return $kat . '/' . $slug . '.json';
}

function zakodujJson(mixed $d): string {
    return json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

/** Odczyt listy rekordów wpisu pod blokadą współdzieloną. Brak pliku = []. */
function czytajWpis(string $strona, string $slug): array {
    $plik = plikWpisu($strona, $slug, false);
    if (!is_file($plik)) { return []; }
    $u = @fopen($plik, 'rb');
    if ($u === false) { return []; }
    flock($u, LOCK_SH);
    $t = stream_get_contents($u);
    flock($u, LOCK_UN);
    fclose($u);
    $l = json_decode((string)$t, true);
    return is_array($l) ? $l : [];
}

/** Zmiana listy pod blokadą wyłączną: $f(array &$lista): mixed; zapis tylko gdy $f zmieniła listę. */
function zmienWpis(string $strona, string $slug, callable $f): mixed {
    $plik = plikWpisu($strona, $slug, true);
    $u = @fopen($plik, 'c+');
    if ($u === false || !flock($u, LOCK_EX)) {
        if ($u !== false) { fclose($u); }
        throw new RuntimeException('nie mogę otworzyć wpisu ' . $strona . '/' . $slug);
    }
    try {
        $l = json_decode((string)stream_get_contents($u), true);
        if (!is_array($l)) { $l = []; }
        $przed = $l;
        $wynik = $f($l);
        if ($l !== $przed) {
            $json = zakodujJson(array_values($l));
            ftruncate($u, 0);
            rewind($u);
            fwrite($u, $json);
            fflush($u);
        }
        return $wynik;
    } finally {
        flock($u, LOCK_UN);
        fclose($u);
    }
}

function publiczny(array $r): array {
    return ['id' => (string)$r['id'], 'autor' => (string)$r['autor'], 'tresc' => (string)$r['tresc'], 'data' => (string)$r['data']];
}

function opublikowane(array $lista): array {
    $w = [];
    foreach ($lista as $r) {
        if (is_array($r) && ($r['status'] ?? '') === 'opublikowany') { $w[] = publiczny($r); }
    }
    usort($w, static fn(array $a, array $b) => strcmp($a['data'], $b['data']));
    return $w;
}

/** @return list<string> slugi z plikami w katalogu strony */
function slugiStrony(string $strona): array {
    $kat = katStanu() . '/komentarze/' . $strona;
    $wynik = [];
    foreach (glob($kat . '/*.json') ?: [] as $p) {
        $s = basename($p, '.json');
        if (slugOk($s)) { $wynik[] = $s; }
    }
    sort($wynik);
    return $wynik;
}

// ---------------------------------------------------------------- adres klienta, limity

function wCidr(string $ip, string $cidr): bool {
    $cz = explode('/', $cidr);
    $a = @inet_pton($ip);
    $b = @inet_pton($cz[0]);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) { return false; }
    $bity = (int)($cz[1] ?? (strlen($a) * 8));
    $pelne = intdiv($bity, 8);
    if ($pelne > 0 && substr($a, 0, $pelne) !== substr($b, 0, $pelne)) { return false; }
    $reszta = $bity % 8;
    if ($reszta === 0) { return true; }
    $maska = (0xFF << (8 - $reszta)) & 0xFF;
    return (ord($a[$pelne]) & $maska) === (ord($b[$pelne]) & $maska);
}

function adresKlienta(array $k): string {
    $zdalny = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $cf = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) !== false) {
        foreach (array_merge(CLOUDFLARE_ZAKRESY, (array)$k['komentarze_proxy']) as $z) {
            if (wCidr($zdalny, (string)$z)) { return $cf; }
        }
    }
    return $zdalny;
}

/** Rezerwuje jedno zgłoszenie w limicie. Zwraca skrót IP albo null, gdy limit przekroczony. */
function rezerwujLimit(array $k, string $ip): ?string {
    $plik = katStanu() . '/komentarze-limity.json';
    $u = @fopen($plik, 'c+');
    if ($u === false || !flock($u, LOCK_EX)) {
        loguj('licznik limitów niedostępny: limit NIE jest egzekwowany');
        if ($u !== false) { fclose($u); }
        return hash('sha256', $ip . date('Y-m-d') . 'zapasowa');
    }
    try {
        $d = json_decode((string)stream_get_contents($u), true);
        $dzis = date('Y-m-d');
        if (!is_array($d) || ($d['data'] ?? '') !== $dzis || !is_string($d['sol'] ?? null)) {
            $d = ['data' => $dzis, 'sol' => bin2hex(random_bytes(16)), 'ip' => [], 'globalnie' => 0];
        }
        $skrot = hash('sha256', $ip . $d['sol']);
        $teraz = time();
        $znaczniki = array_map('intval', (array)($d['ip'][$skrot] ?? []));
        $ost10 = count(array_filter($znaczniki, static fn(int $t) => $t > $teraz - 600));
        if ($ost10 >= (int)$k['komentarze_limit_10min']
            || count($znaczniki) >= (int)$k['komentarze_limit_doba']
            || (int)$d['globalnie'] >= (int)$k['komentarze_limit_globalnie']) {
            return null;
        }
        $znaczniki[] = $teraz;
        $d['ip'][$skrot] = $znaczniki;
        $d['globalnie'] = (int)$d['globalnie'] + 1;
        ftruncate($u, 0);
        rewind($u);
        fwrite($u, zakodujJson($d));
        fflush($u);
        return $skrot;
    } finally {
        flock($u, LOCK_UN);
        fclose($u);
    }
}

// ---------------------------------------------------------------- istnienie wpisu (HEAD, pamięć 24 h)

function wpisIstnieje(array $k, string $strona, string $slug): bool|string {
    if (is_array($k['komentarze_wpisy_testowe'])) {
        return in_array($slug, $k['komentarze_wpisy_testowe'], true);
    }
    $plik = katStanu() . '/komentarze-wpisy.json';
    $u = @fopen($plik, 'c+');
    if ($u === false || !flock($u, LOCK_EX)) {
        if ($u !== false) { fclose($u); }
        loguj('pamięć wpisów niedostępna');
        return sprawdzHead($strona, $slug) ?? 'limit';
    }
    try {
        $d = json_decode((string)stream_get_contents($u), true);
        if (!is_array($d)) { $d = []; }
        $teraz = time();
        $klucz = $strona . '/' . $slug;
        foreach ($d as $kl => $w) {
            if ($kl === '_head') { continue; }
            if (!is_array($w) || $w[0] < $teraz - ($w[1] ? PAMIEC_WPISU_OK : PAMIEC_WPISU_BRAK)) { unset($d[$kl]); }
        }
        $godz = date('YmdH');
        $head = (is_array($d['_head'] ?? null) && ($d['_head'][0] ?? '') === $godz) ? (int)$d['_head'][1] : 0;
        $wynik = null;
        if (isset($d[$klucz])) {
            $wynik = (bool)$d[$klucz][1];
        } elseif ($head >= (int)$k['komentarze_head_na_godz']) {
            $wynik = 'limit';
        } else {
            $head++;
            $ok = sprawdzHead($strona, $slug);
            if ($ok === null) {
                // Brak odpowiedzi GitHub Pages: przyjmij tylko wpis, pod którym już są komentarze.
                $ok = is_file(plikWpisu($strona, $slug, false));
                loguj('HEAD wpisu bez odpowiedzi: ' . $klucz);
            } else {
                $d[$klucz] = [$teraz, $ok];
            }
            $wynik = $ok;
        }
        $d['_head'] = [$godz, $head];
        ftruncate($u, 0);
        rewind($u);
        fwrite($u, zakodujJson($d));
        fflush($u);
        return $wynik;
    } finally {
        flock($u, LOCK_UN);
        fclose($u);
    }
}

/** HEAD na stały adres https://<strona>/blog/<slug>/ (strona z listy, slug zwalidowany: bez SSRF). null = brak odpowiedzi. */
function sprawdzHead(string $strona, string $slug): ?bool {
    $ctx = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 4, 'ignore_errors' => true, 'follow_location' => 0, 'user_agent' => 'komentarze-ecopowerpolska/1']]);
    @file_get_contents('https://' . $strona . '/blog/' . $slug . '/', false, $ctx);
    $nag = $http_response_header ?? [];
    foreach ($nag as $w) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~', (string)$w, $m)) { return $m[1] === '200'; }
    }
    return null;
}

// ---------------------------------------------------------------- Turnstile

/** @return string|null kod błędu albo null = w porządku */
function sprawdzTurnstile(array $k, string $token, string $ip): ?string {
    $sekret = trim((string)$k['turnstile_sekret']);
    if ($sekret === '') {
        loguj('brak turnstile_sekret w konfiguracji: komentarze zablokowane');
        return 'inny';
    }
    if ($token === '' || strlen($token) > 2048) { return 'turnstile'; }
    $url = trim((string)$k['komentarze_siteverify_url']) !== '' ? (string)$k['komentarze_siteverify_url'] : TURNSTILE_SITEVERIFY;
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query(['secret' => $sekret, 'response' => $token, 'remoteip' => $ip]),
        'timeout' => 5,
        'ignore_errors' => true,
    ]]);
    $surowe = @file_get_contents($url, false, $ctx);
    $w = is_string($surowe) ? json_decode($surowe, true) : null;
    if (!is_array($w)) {
        loguj('siteverify bez odpowiedzi');
        return 'inny';
    }
    if (($w['success'] ?? false) !== true) {
        $kody = (array)($w['error-codes'] ?? []);
        loguj('Turnstile odrzucił: ' . implode(',', array_map('strval', $kody)));
        return array_intersect($kody, ['invalid-input-secret', 'missing-input-secret']) !== [] ? 'inny' : 'turnstile';
    }
    $hosty = array_map('strtolower', (array)$k['komentarze_turnstile_hosty']);
    if (!in_array(strtolower((string)($w['hostname'] ?? '')), $hosty, true)) {
        loguj('token Turnstile dla innego hosta: ' . substr((string)($w['hostname'] ?? ''), 0, 100));
        return 'turnstile';
    }
    return null;
}

// ---------------------------------------------------------------- pola

function czysc(string $v, bool $wielowierszowy): string {
    $v = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $v);
    $ster = '\p{Cc}\x{2028}\x{2029}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}';
    if ($wielowierszowy) {
        $v = (string)preg_replace('/(?!\n)[' . $ster . ']/u', ' ', $v);
        $v = (string)preg_replace('/[ ]*\n[ ]*/u', "\n", $v);
        $v = (string)preg_replace('/\n{3,}/', "\n\n", $v);
    } else {
        $v = (string)preg_replace('/[' . $ster . ']+/u', ' ', $v);
        $v = (string)preg_replace('/ {2,}/', ' ', $v);
    }
    return trim($v);
}

function liczbaLinkow(string $tresc): int {
    return (int)preg_match_all('~(?:https?://|www\.)\S+~iu', $tresc);
}

function poleTekst(array $d, string $nazwa): ?string {
    $v = $d[$nazwa] ?? '';
    return is_string($v) && mb_check_encoding($v, 'UTF-8') ? $v : null;
}

// ---------------------------------------------------------------- odpowiedzi

function dozwoloneOrigin(array $k): array {
    return array_map(static fn($s) => 'https://' . $s, (array)$k['komentarze_strony']);
}

function naglowkiWspolne(): void {
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Vary: Origin');
}

function naglowkiCors(string $origin): void {
    header('Access-Control-Allow-Origin: ' . $origin);
}

function jsonOdp(int $http, array $dane): never {
    naglowkiWspolne();
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo zakodujJson($dane);
    exit;
}

/** Błąd POST: JSON (Accept: application/json) albo 303 na wpis. */
function bladOdp(array $k, bool $json, string $kod, ?string $strona, ?string $slug, ?int $http = null): never {
    if ($json) { jsonOdp($http ?? KODY_HTTP[$kod] ?? 400, ['ok' => false, 'blad' => $kod]); }
    $baza = 'https://' . ($strona ?? ((array)$k['komentarze_strony'])[0]);
    $adres = $slug !== null ? $baza . '/blog/' . $slug . '/?blad=' . rawurlencode($kod) . '#komentarze' : $baza . '/?blad=' . rawurlencode($kod);
    naglowkiWspolne();
    header('Location: ' . $adres, true, 303);
    exit;
}

function stronaHtml(int $http, string $tytul, string $tresc): never {
    naglowkiWspolne();
    http_response_code($http);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    $t = htmlspecialchars($tytul, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<!doctype html><html lang=\"pl\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">"
        . "<meta name=\"robots\" content=\"noindex, nofollow\"><title>$t</title>"
        . "<style>body{font:16px/1.5 system-ui,sans-serif;max-width:40rem;margin:2rem auto;padding:0 1rem;color:#1b1b1b}"
        . "blockquote{margin:1rem 0;padding:.75rem 1rem;background:#f3f3f3;border-left:4px solid #999;white-space:pre-wrap;overflow-wrap:anywhere}"
        . "button{font:inherit;padding:.6rem 1.2rem;border:0;border-radius:.4rem;background:#b3261e;color:#fff;cursor:pointer}"
        . "button.zatwierdz{background:#1f6f3a}</style></head><body><h1>$t</h1>$tresc</body></html>";
    exit;
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------- moderacja

function podpis(string $akcja, string $strona, string $slug, string $id, string $klucz): string {
    return hash_hmac('sha256', implode("\n", [$akcja, $strona, $slug, $id]), $klucz);
}

function linkModeracji(array $k, string $akcja, string $strona, string $slug, string $id): string {
    $klucz = (string)$k['komentarze_klucz'];
    if ($klucz === '') { return '(brak komentarze_klucz w konfiguracji: link niedostępny)'; }
    return (string)$k['komentarze_url'] . '?' . http_build_query([
        'akcja' => $akcja, 'strona' => $strona, 'wpis' => $slug, 'id' => $id, 'podpis' => podpis($akcja, $strona, $slug, $id, $klucz),
    ], '', '&', PHP_QUERY_RFC3986);
}

/** @return string 'ok' | 'brak' | 'juz' */
function wykonajModeracje(string $akcja, string $strona, string $slug, string $id): string {
    if (!is_file(plikWpisu($strona, $slug, false))) { return 'brak'; }
    return (string)zmienWpis($strona, $slug, static function (array &$lista) use ($akcja, $id): string {
        foreach ($lista as $i => $r) {
            if (!is_array($r) || ($r['id'] ?? '') !== $id) { continue; }
            if ($akcja === 'usun') { unset($lista[$i]); return 'ok'; }
            if (($r['status'] ?? '') === 'opublikowany') { return 'juz'; }
            $lista[$i]['status'] = 'opublikowany';
            return 'ok';
        }
        return 'brak';
    });
}

function obsluzModeracje(array $k, array $d, bool $post): never {
    $akcja = $d['akcja'] ?? null;
    $strona = $d['strona'] ?? null;
    $slug = $d['wpis'] ?? null;
    $id = $d['id'] ?? null;
    $podpis = $d['podpis'] ?? null;
    $klucz = (string)$k['komentarze_klucz'];
    if ($klucz === '') { stronaHtml(403, 'Moderacja wyłączona', '<p>Brak klucza moderacji w konfiguracji serwera.</p>'); }
    if (!in_array($akcja, ['usun', 'zatwierdz'], true) || !stronaOk($strona, $k) || !slugOk($slug) || !idOk($id) || !is_string($podpis)
        || !hash_equals(podpis($akcja, $strona, $slug, $id, $klucz), $podpis)) {
        loguj('moderacja: zły podpis albo parametry');
        stronaHtml(403, 'Nieprawidłowy link', '<p>Link jest nieprawidłowy albo został zmieniony.</p>');
    }
    if ($post) {
        $w = wykonajModeracje($akcja, $strona, $slug, $id);
        loguj('moderacja: ' . $akcja . ' ' . $strona . '/' . $slug . '/' . $id . ' = ' . $w);
        $komunikat = match (true) {
            $w === 'ok' && $akcja === 'usun' => 'Komentarz został usunięty.',
            $w === 'ok' => 'Komentarz został zatwierdzony i jest już widoczny (strona statyczna odświeży się przy najbliższej przebudowie).',
            $w === 'juz' => 'Komentarz był już opublikowany.',
            default => 'Nie ma takiego komentarza. Mógł zostać już usunięty.',
        };
        stronaHtml($w === 'brak' ? 404 : 200, 'Gotowe', '<p>' . e($komunikat) . '</p>');
    }
    $rekord = null;
    foreach (czytajWpis($strona, $slug) as $r) {
        if (is_array($r) && ($r['id'] ?? '') === $id) { $rekord = $r; break; }
    }
    if ($rekord === null) { stronaHtml(404, 'Brak komentarza', '<p>Nie ma takiego komentarza. Mógł zostać już usunięty.</p>'); }
    $czynnosc = $akcja === 'usun' ? 'Usuń komentarz' : 'Zatwierdź komentarz';
    $html = '<p>Wpis: <strong>' . e($strona . '/blog/' . $slug . '/') . '</strong><br>Autor: <strong>' . e((string)$rekord['autor'])
        . '</strong><br>Status: ' . e((string)$rekord['status']) . '<br>Data: ' . e((string)$rekord['data']) . '</p>'
        . '<blockquote>' . e((string)$rekord['tresc']) . '</blockquote>'
        . '<form method="post" action="' . e((string)$k['komentarze_url']) . '">';
    foreach (['akcja' => $akcja, 'strona' => $strona, 'wpis' => $slug, 'id' => $id, 'podpis' => $podpis] as $n => $v) {
        $html .= '<input type="hidden" name="' . e($n) . '" value="' . e((string)$v) . '">';
    }
    $html .= '<button type="submit" class="' . e((string)$akcja) . '">' . e($czynnosc) . '</button></form>';
    stronaHtml(200, $czynnosc, $html);
}

// ---------------------------------------------------------------- poczta

function wyslijPoczte(array $k, string $temat, string $cialo, string $replyTo): bool {
    $do = (string)$k['komentarze_mail_do'];
    $plik = trim((string)$k['komentarze_poczta_plik']);
    if ($plik !== '') {
        return @file_put_contents($plik, "TO: $do\nSUBJECT: $temat\nREPLY-TO: $replyTo\n\n$cialo\n=====\n", FILE_APPEND | LOCK_EX) !== false;
    }
    $nagl = 'From: Komentarze ambasadapodroznikow.pl <' . $k['od'] . ">\r\n"
        . ($replyTo !== '' ? "Reply-To: $replyTo\r\n" : '')
        . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    return mail($do, '=?UTF-8?B?' . base64_encode($temat) . '?=', $cialo, $nagl);
}

function powiadom(array $k, string $strona, string $slug, array $r): void {
    $czeka = $r['status'] === 'czeka';
    $temat = ($czeka ? '[Ambasada] Komentarz czeka: ' : '[Ambasada] Komentarz: ') . $slug;
    $cialo = 'Wpis: https://' . $strona . '/blog/' . $slug . "/\n"
        . 'Autor: ' . $r['autor'] . "\n"
        . 'E-mail: ' . ($r['email'] !== '' ? $r['email'] : '(brak)') . "\n"
        . 'Status: ' . ($czeka ? 'czeka na zatwierdzenie (więcej niż ' . MAKS_LINKOW_BEZ_MODERACJI . ' linki)' : 'opublikowany') . "\n"
        . 'Data: ' . $r['data'] . "\n\n"
        . "Treść:\n" . $r['tresc'] . "\n\n"
        . 'Usuń: ' . linkModeracji($k, 'usun', $strona, $slug, $r['id']) . "\n"
        . ($czeka ? 'Zatwierdź: ' . linkModeracji($k, 'zatwierdz', $strona, $slug, $r['id']) . "\n" : '')
        . "\nLinki otwierają stronę z przyciskiem; akcja wykona się dopiero po jego kliknięciu.\n";
    $ok = false;
    try {
        $ok = wyslijPoczte($k, $temat, $cialo, $r['email']);
    } catch (Throwable $t) {
        loguj('mail: wyjątek ' . get_class($t));
    }
    if (!$ok) { loguj('mail: wysyłka nieudana dla komentarza ' . $r['id']); }
}

// ---------------------------------------------------------------- GET, OPTIONS, POST

function obsluzGet(array $k): never {
    $strona = $_GET['strona'] ?? null;
    if (!stronaOk($strona, $k)) { jsonOdp(400, ['ok' => false, 'blad' => 'inny']); }
    naglowkiCors('https://' . $strona);
    if (($_GET['wszystkie'] ?? '') === '1') {
        $wpisy = [];
        foreach (slugiStrony($strona) as $s) {
            $l = opublikowane(czytajWpis($strona, $s));
            if ($l !== []) { $wpisy[$s] = $l; }
        }
        jsonOdp(200, ['wpisy' => (object)$wpisy]);
    }
    $slug = $_GET['wpis'] ?? null;
    if (!slugOk($slug)) { jsonOdp(400, ['ok' => false, 'blad' => 'wpis']); }
    jsonOdp(200, ['wpis' => $slug, 'komentarze' => opublikowane(czytajWpis($strona, $slug))]);
}

function obsluzOptions(array $k): never {
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    naglowkiWspolne();
    if (!in_array($origin, dozwoloneOrigin($k), true)) { http_response_code(403); exit; }
    naglowkiCors($origin);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    header('Access-Control-Max-Age: 600');
    http_response_code(204);
    exit;
}

function obsluzPost(array $k): never {
    $json = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');

    // Dane: formularz (urlencoded/multipart) albo JSON w treści żądania.
    $dlugosc = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $dane = $_POST;
    if (stripos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') === 0) {
        $surowe = (string)stream_get_contents(fopen('php://input', 'rb'), MAKS_ZADANIE_BAJTY + 1);
        $d = strlen($surowe) <= MAKS_ZADANIE_BAJTY ? json_decode($surowe, true, 4) : null;
        $dane = is_array($d) ? $d : [];
        if (!is_array($d)) { $dlugosc = MAKS_ZADANIE_BAJTY + 1; }
    }

    // Moderacja: POST przycisku ze strony z linku w mailu; chroni go podpis HMAC, nie Origin.
    if (isset($dane['akcja'])) { obsluzModeracje($k, $dane, true); }

    // 1. Origin
    if (!in_array($origin, dozwoloneOrigin($k), true)) {
        loguj('niedozwolone pochodzenie: ' . substr($origin, 0, 100));
        bladOdp($k, $json, 'inny', null, null, 403);
    }
    naglowkiCors($origin);

    // 3. strona
    $strona = $dane['strona'] ?? null;
    $slug = slugOk($dane['wpis'] ?? null) ? $dane['wpis'] : null;
    if ($dlugosc > MAKS_ZADANIE_BAJTY) { bladOdp($k, $json, 'inny', null, $slug); }
    if (!stronaOk($strona, $k) || $origin !== 'https://' . $strona) { bladOdp($k, $json, 'inny', null, $slug); }

    // 4. wpis
    if ($slug === null) { bladOdp($k, $json, 'wpis', $strona, null); }
    $istnieje = wpisIstnieje($k, $strona, $slug);
    if ($istnieje === 'limit') { bladOdp($k, $json, 'limit', $strona, $slug); }
    if ($istnieje !== true) { bladOdp($k, $json, 'wpis', $strona, $slug); }

    // 5. Turnstile
    $ip = adresKlienta($k);
    $token = $dane['cf-turnstile-response'] ?? '';
    $blad = sprawdzTurnstile($k, is_string($token) ? trim($token) : '', $ip);
    if ($blad !== null) { bladOdp($k, $json, $blad, $strona, $slug, $blad === 'inny' ? 500 : null); }

    // 6. limit
    $skrot = rezerwujLimit($k, $ip);
    if ($skrot === null) { bladOdp($k, $json, 'limit', $strona, $slug); }

    // 7. pola
    $autor = poleTekst($dane, 'autor');
    $tresc = poleTekst($dane, 'tresc');
    $email = poleTekst($dane, 'email');
    $autor = $autor === null ? null : czysc($autor, false);
    $tresc = $tresc === null ? null : czysc($tresc, true);
    $email = $email === null ? null : czysc($email, false);
    if ($autor === null || mb_strlen($autor) < MIN_AUTOR || mb_strlen($autor) > MAKS_AUTOR) { bladOdp($k, $json, 'autor', $strona, $slug); }
    if ($tresc === null || mb_strlen($tresc) < MIN_TRESC || mb_strlen($tresc) > MAKS_TRESC) { bladOdp($k, $json, 'tresc', $strona, $slug); }
    if ($email === null || ($email !== '' && (mb_strlen($email) > MAKS_EMAIL || filter_var($email, FILTER_VALIDATE_EMAIL) === false))) {
        bladOdp($k, $json, 'inny', $strona, $slug);
    }

    // 8. zapis
    $status = liczbaLinkow($tresc) > MAKS_LINKOW_BEZ_MODERACJI ? 'czeka' : 'opublikowany';
    $rekord = ['id' => bin2hex(random_bytes(8)), 'autor' => $autor, 'tresc' => $tresc, 'email' => $email,
        'data' => date('c'), 'status' => $status];
    $maks = (int)$k['komentarze_maks_na_wpis'];
    $wynik = zmienWpis($strona, $slug, static function (array &$lista) use ($rekord, $maks): array|string {
        foreach ($lista as $r) {
            if (is_array($r) && ($r['autor'] ?? null) === $rekord['autor'] && ($r['tresc'] ?? null) === $rekord['tresc']) { return ['powtorka', $r]; }
        }
        if (count($lista) >= $maks) { return 'limit'; }
        $lista[] = $rekord;
        return ['nowy', $rekord];
    });
    if ($wynik === 'limit') { bladOdp($k, $json, 'limit', $strona, $slug); }
    [$rodzaj, $zapisany] = $wynik;

    // 9. mail (błąd wysyłki nie cofa zapisu)
    if ($rodzaj === 'nowy') { powiadom($k, $strona, $slug, $zapisany); }

    if ($json) {
        jsonOdp(200, ['ok' => true, 'status' => $zapisany['status'], 'komentarz' => publiczny($zapisany)]);
    }
    naglowkiWspolne();
    header('Location: https://' . $strona . '/blog/' . $slug . '/?komentarz=' . rawurlencode((string)$zapisany['status']) . '#komentarze', true, 303);
    exit;
}

function obsluzZadanie(): never {
    ini_set('display_errors', '0');
    $k = konfig();
    $metoda = (string)($_SERVER['REQUEST_METHOD'] ?? '');
    try {
        if ($metoda === 'OPTIONS') { obsluzOptions($k); }
        if ($metoda === 'GET' || $metoda === 'HEAD') {
            if (isset($_GET['akcja'])) { obsluzModeracje($k, $_GET, false); }
            obsluzGet($k);
        }
        if ($metoda === 'POST') { obsluzPost($k); }
        header('Allow: GET, POST, OPTIONS');
        jsonOdp(405, ['ok' => false, 'blad' => 'inny']);
    } catch (Throwable $t) {
        loguj('wyjątek ' . get_class($t) . ': ' . $t->getMessage() . ' @' . basename($t->getFile()) . ':' . $t->getLine());
        if (!headers_sent()) {
            if ($metoda === 'POST') {
                $json = stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
                $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
                $s = ltrim(strtolower(substr($origin, 8)), '/');
                if (in_array($origin, dozwoloneOrigin($k), true)) { naglowkiCors($origin); }
                bladOdp($k, $json, 'inny', stronaOk($s, $k) ? $s : null, slugOk($_POST['wpis'] ?? null) ? $_POST['wpis'] : null, 500);
            }
            jsonOdp(500, ['ok' => false, 'blad' => 'inny']);
        }
        exit;
    }
}

// ---------------------------------------------------------------- tryb CLI (SSH)

function obsluzCli(array $argv): never {
    $k = konfig();
    $pol = $argv[1] ?? '';
    $blad = static function (string $t): never { fwrite(STDERR, $t . "\n"); exit(2); };
    if ($pol === 'lista') {
        $strona = $argv[2] ?? '';
        if (!stronaOk($strona, $k)) { $blad('Użycie: php komentarze.php lista <strona>  (strona z komentarze_strony)'); }
        $n = 0;
        foreach (slugiStrony($strona) as $s) {
            foreach (czytajWpis($strona, $s) as $r) {
                if (!is_array($r)) { continue; }
                $n++;
                $linia = str_replace(["\r", "\n"], ' ', (string)$r['tresc']);
                echo $s . "\t" . $r['id'] . "\t" . $r['status'] . "\t" . $r['data'] . "\t" . $r['autor'] . "\t" . mb_substr($linia, 0, 80) . "\n";
            }
        }
        echo "Razem: $n\n";
        exit(0);
    }
    if ($pol === 'usun' || $pol === 'zatwierdz') {
        [$strona, $slug, $id] = [$argv[2] ?? '', $argv[3] ?? '', $argv[4] ?? ''];
        if (!stronaOk($strona, $k) || !slugOk($slug) || !idOk($id)) { $blad("Użycie: php komentarze.php $pol <strona> <wpis> <id>"); }
        $w = wykonajModeracje($pol, $strona, $slug, $id);
        loguj("CLI: $pol $strona/$slug/$id = $w");
        echo match ($w) { 'ok' => $pol === 'usun' ? "Usunięto.\n" : "Zatwierdzono.\n", 'juz' => "Już opublikowany.\n", default => "Nie znaleziono.\n" };
        exit($w === 'brak' ? 1 : 0);
    }
    $blad("Użycie:\n  php komentarze.php lista <strona>\n  php komentarze.php usun <strona> <wpis> <id>\n  php komentarze.php zatwierdz <strona> <wpis> <id>");
}

if (PHP_SAPI === 'cli') {
    obsluzCli($argv);
}
obsluzZadanie();
