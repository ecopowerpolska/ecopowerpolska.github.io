<?php
declare(strict_types=1);
/**
 * Wspólna biblioteka audytu (audyt.php ładuje ją zawsze, moduły testy/<nazwa>.php z niej korzystają).
 * Bezpieczne pobieranie (ochrona przed SSRF: adres publiczny, port 80/443, rozwiązany IP przypięty do połączenia, każdy
 * przeskok przekierowania sprawdzany od nowa), DNS, HEAD z licznikiem, pozycje wyniku, ocena, DOM. Kontrakt: KONTRAKT.md.
 */
if (!defined('AUDYT_EP')) { http_response_code(404); exit; }

const EP_UA = 'EcoPowerAudyt/1.0 (+https://www.ecopowerpolska.pl/darmowe-narzedzia/)';
const EP_MAKS_HTML = 2_097_152;   // 2 MB: strona główna
const EP_LIMIT_HEAD = 15;         // żądań head() na jeden test
const EP_LIMIT_TEKST = 8;         // żądań pobierz_tekst() na jeden test
const EP_BUDZET_S = 20.0;         // łączny czas jednego testu

// ── Licznik i budżet czasu jednego testu ─────────────────────────────────────────────────────────────────────────
function &ep_stan(): array {
    static $s = ['start' => 0.0, 'head' => 0, 'tekst' => 0];
    return $s;
}
function ep_start(): void {
    $s = &ep_stan();
    $s = ['start' => microtime(true), 'head' => 0, 'tekst' => 0];
}
function ep_pozostalo(): float {
    $s = &ep_stan();
    return EP_BUDZET_S - (microtime(true) - (float)$s['start']);
}

// ── Adresy i ochrona przed SSRF ──────────────────────────────────────────────────────────────────────────────────
function ep_ip_publiczny(string $ip): bool {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) { return false; }
    if (str_contains($ip, ':')) {
        $l = strtolower($ip);
        if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/', $l, $m)) { return ep_ip_publiczny($m[1]); }
        foreach (['::', '64:ff9b:', '2002:', 'fe8', 'fe9', 'fea', 'feb', 'fc', 'fd', 'ff'] as $p) {
            if (str_starts_with($l, $p)) { return false; }
        }
        return true;
    }
    $n = ip2long($ip);
    if ($n === false) { return false; }
    foreach ([['100.64.0.0', 10], ['192.0.0.0', 24], ['198.18.0.0', 15], ['192.0.2.0', 24], ['198.51.100.0', 24], ['203.0.113.0', 24]] as [$sieć, $bity]) {
        $maska = -1 << (32 - $bity);
        if (($n & $maska) === (ip2long($sieć) & $maska)) { return false; }
    }
    return true;
}

/** Adres użytkownika → ['url','schemat','host','port','cel'] albo null. Dopisuje https://, gdy brak schematu. */
function ep_normalizuj_url(string $surowy): ?array {
    $s = trim($surowy);
    if ($s === '' || strlen($s) > 2000 || preg_match('/[\s\x00-\x1f\x7f]/', $s)) { return null; }
    if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $s)) { $s = 'https://' . ltrim($s, '/'); }
    $p = parse_url($s);
    if (!is_array($p) || !isset($p['host']) || isset($p['user']) || isset($p['pass'])) { return null; }
    $schemat = strtolower((string)($p['scheme'] ?? ''));
    if ($schemat !== 'http' && $schemat !== 'https') { return null; }
    $port = isset($p['port']) ? (int)$p['port'] : ($schemat === 'https' ? 443 : 80);
    if ($port !== 80 && $port !== 443) { return null; }
    $host = strtolower(rtrim((string)$p['host'], '.'));
    if ($host === '') { return null; }
    if (preg_match('/[^\x00-\x7f]/', $host)) {
        $host = function_exists('idn_to_ascii') ? (string)idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTF46) : '';
        if ($host === '') { return null; }
    }
    $literal = false;
    if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
        $host = substr($host, 1, -1);
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) { return null; }
        $literal = true;
    } elseif (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        $literal = true;
    }
    if ($literal) {
        if (!ep_ip_publiczny($host)) { return null; }
    } else {
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z0-9\-]{2,63}$/', $host) || strlen($host) > 253) { return null; }
        if (preg_match('/(^|\.)(localhost|local|internal|lan|intranet|home\.arpa)$/', $host)) { return null; }
    }
    $sciezka = (string)($p['path'] ?? '/');
    if ($sciezka === '') { $sciezka = '/'; }
    $cel = $sciezka . (isset($p['query']) ? '?' . $p['query'] : '');
    $hostUrl = str_contains($host, ':') ? '[' . $host . ']' : $host;
    $domyslny = $schemat === 'https' ? 443 : 80;
    $url = $schemat . '://' . $hostUrl . ($port !== $domyslny ? ':' . $port : '') . $cel;
    return ['url' => $url, 'schemat' => $schemat, 'host' => $host, 'port' => $port, 'cel' => $cel];
}

/** Rozwiązuje host i zwraca adres IP do przypięcia, a gdy którykolwiek wynik nie jest publiczny albo brak wyniku — null. */
function ep_ip_bezpieczny(string $host): ?string {
    static $pamiec = [];
    if (array_key_exists($host, $pamiec)) { return $pamiec[$host]; }
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return $pamiec[$host] = (ep_ip_publiczny($host) ? $host : null);
    }
    $v4 = dns_a($host);
    $v6 = dns_aaaa($host);
    $wszystkie = array_merge($v4, $v6);
    $wynik = null;
    if ($wszystkie !== []) {
        $wynik = $v4[0] ?? $v6[0];
        foreach ($wszystkie as $ip) {
            if (!ep_ip_publiczny($ip)) { $wynik = null; break; }
        }
    }
    return $pamiec[$host] = $wynik;
}

/** Łączy adres bazowy z odnośnikiem (bezwzględny, //host, /ścieżka, względny, ?zapytanie), usuwa fragment i segmenty ./.. */
function ep_zlacz(string $baza, string $rel): string {
    $rel = trim($rel);
    if ($rel === '') { return $baza; }
    if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $rel)) { return preg_replace('/#.*$/', '', $rel) ?? $rel; }
    $b = parse_url($baza);
    if (!is_array($b) || !isset($b['scheme'], $b['host'])) { return $rel; }
    $zrodlo = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
    if (str_starts_with($rel, '//')) { return preg_replace('/#.*$/', '', $b['scheme'] . ':' . $rel) ?? $rel; }
    $rel = preg_replace('/#.*$/', '', $rel) ?? $rel;
    if ($rel === '') { return $baza; }
    if ($rel[0] === '?') { return $zrodlo . ($b['path'] ?? '/') . $rel; }
    if ($rel[0] === '/') { $sciezka = $rel; }
    else {
        $katalog = preg_replace('#[^/]*$#', '', (string)($b['path'] ?? '/')) ?: '/';
        $sciezka = $katalog . $rel;
    }
    [$czesc, $zapytanie] = array_pad(explode('?', $sciezka, 2), 2, null);
    $seg = [];
    foreach (explode('/', (string)$czesc) as $i => $x) {
        if ($x === '.') { continue; }
        if ($x === '..') { array_pop($seg); continue; }
        $seg[] = $x;
    }
    $wynik = implode('/', $seg);
    if ($wynik === '' || $wynik[0] !== '/') { $wynik = '/' . $wynik; }
    return $zrodlo . $wynik . ($zapytanie !== null ? '?' . $zapytanie : '');
}

// ── Pobieranie ───────────────────────────────────────────────────────────────────────────────────────────────────
/**
 * Jedno pobranie z ręcznym śledzeniem przekierowań. Opcje: metoda (GET|HEAD), limit (bajty ciała), timeout (s), przekierowania (maks.).
 * Opcja bez_weryfikacji_tls (bool): tylko dla testu bezpieczeństwa, który ma zobaczyć stronę z błędnym certyfikatem.
 * Zwraca: ok, kod, url_koncowy, naglowki (małe litery, wartości sklejone ", "), body, czas_ms (do pierwszego bajtu ostatniego
 * przeskoku), czas_pobrania_ms, przekierowania [[z,kod,do]], blad (null|adres_niedozwolony|budzet|za_duzo_przekierowan|siec),
 * errno (curl), obcieto, rozmiar (bajty ciała po dekompresji).
 */
function ep_pobierz(string $url, array $o = []): array {
    $metoda = ($o['metoda'] ?? 'GET') === 'HEAD' ? 'HEAD' : 'GET';
    $limit = (int)($o['limit'] ?? EP_MAKS_HTML);
    $timeout = (float)($o['timeout'] ?? 8);
    $maks = (int)($o['przekierowania'] ?? 5);
    $w = ['ok' => false, 'kod' => 0, 'url_koncowy' => $url, 'naglowki' => [], 'body' => '', 'czas_ms' => 0, 'czas_pobrania_ms' => 0,
          'przekierowania' => [], 'blad' => null, 'errno' => 0, 'obcieto' => false, 'rozmiar' => 0];
    if (!function_exists('curl_init')) { $w['blad'] = 'siec'; return $w; }
    $biezacy = $url;
    for ($przeskok = 0; $przeskok <= $maks; $przeskok++) {
        $u = ep_normalizuj_url($biezacy);
        $ip = $u === null ? null : ep_ip_bezpieczny($u['host']);
        if ($u === null || $ip === null) { $w['blad'] = 'adres_niedozwolony'; return $w; }
        $w['url_koncowy'] = $u['url'];
        $zostalo = ep_pozostalo();
        if ($zostalo < 0.5) { $w['blad'] = 'budzet'; return $w; }
        $t = max(1.0, min($timeout, $zostalo));
        $nag = [];
        $body = '';
        $obciete = false;
        $ch = curl_init($u['url']);
        $opcje = [
            CURLOPT_RESOLVE        => [$u['host'] . ':' . $u['port'] . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
            CURLOPT_CONNECTTIMEOUT_MS => (int)(min(4.0, $t) * 1000),
            CURLOPT_TIMEOUT_MS     => (int)($t * 1000),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => empty($o['bez_weryfikacji_tls']),
            CURLOPT_SSL_VERIFYHOST => empty($o['bez_weryfikacji_tls']) ? 2 : 0,
            CURLOPT_USERAGENT      => EP_UA,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Accept-Language: pl,en;q=0.5'],
            CURLOPT_HEADERFUNCTION => static function ($ch, string $linia) use (&$nag): int {
                $n = strlen($linia);
                if (str_starts_with($linia, 'HTTP/')) { $nag = []; return $n; }
                $poz = strpos($linia, ':');
                if ($poz > 0) {
                    $k = strtolower(trim(substr($linia, 0, $poz)));
                    $v = trim(substr($linia, $poz + 1));
                    $nag[$k] = isset($nag[$k]) ? $nag[$k] . ', ' . $v : $v;
                }
                return $n;
            },
            CURLOPT_WRITEFUNCTION  => static function ($ch, string $kawalek) use (&$body, &$obciete, $limit): int {
                $body .= $kawalek;
                if (strlen($body) > $limit) { $body = substr($body, 0, $limit); $obciete = true; return 0; }
                return strlen($kawalek);
            },
        ];
        if ($metoda === 'HEAD') { $opcje[CURLOPT_NOBODY] = true; } else { $opcje[CURLOPT_HTTPGET] = true; }
        curl_setopt_array($ch, $opcje);
        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $kod = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ttfb = (int)round(((float)curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME)) * 1000);
        $calosc = (int)round(((float)curl_getinfo($ch, CURLINFO_TOTAL_TIME)) * 1000);
        curl_close($ch);
        $w['kod'] = $kod;
        $w['naglowki'] = $nag;
        $w['body'] = $body;
        $w['czas_ms'] = $ttfb;
        $w['czas_pobrania_ms'] = $calosc;
        $w['errno'] = $obciete ? 0 : $errno;
        $w['obcieto'] = $obciete;
        $w['rozmiar'] = strlen($body);
        if ($ok === false && !$obciete) { $w['blad'] = 'siec'; return $w; }
        if (in_array($kod, [301, 302, 303, 307, 308], true) && isset($nag['location']) && $nag['location'] !== '') {
            $nastepny = ep_zlacz($u['url'], $nag['location']);
            $w['przekierowania'][] = ['z' => $u['url'], 'kod' => $kod, 'do' => $nastepny];
            if ($przeskok >= $maks) { $w['blad'] = 'za_duzo_przekierowan'; return $w; }
            $biezacy = $nastepny;
            continue;
        }
        $w['ok'] = true;
        return $w;
    }
    return $w;
}

/** Zamienia ciało na UTF-8 (nagłówek Content-Type, potem meta charset, domyślnie windows-1250 dla niepoprawnego UTF-8). */
function ep_utf8(string $body, array $naglowki): string {
    if ($body === '' || mb_check_encoding($body, 'UTF-8')) { return $body; }
    $cs = '';
    if (preg_match('/charset=([\w\-]+)/i', (string)($naglowki['content-type'] ?? ''), $m)) { $cs = $m[1]; }
    elseif (preg_match('/<meta[^>]+charset=["\']?([\w\-]+)/i', substr($body, 0, 4096), $m)) { $cs = $m[1]; }
    $cs = $cs !== '' ? $cs : 'windows-1250';
    $konw = @mb_convert_encoding($body, 'UTF-8', $cs);
    return is_string($konw) && $konw !== '' ? $konw : (string)mb_convert_encoding($body, 'UTF-8', 'UTF-8');
}

// ── Pomocnicze dla modułów ───────────────────────────────────────────────────────────────────────────────────────
/** Zapytanie HEAD (≤ EP_LIMIT_HEAD na test). Zwraca kod, dlugosc (?int), typ, url, blad (?string), naglowki. */
function head(string $url, int $timeout = 3): array {
    $s = &ep_stan();
    $pusty = ['kod' => 0, 'dlugosc' => null, 'typ' => '', 'url' => $url, 'blad' => null, 'naglowki' => []];
    if ($s['head'] >= EP_LIMIT_HEAD) { $pusty['blad'] = 'limit_zadan'; return $pusty; }
    $s['head']++;
    $r = ep_pobierz($url, ['metoda' => 'HEAD', 'timeout' => $timeout, 'przekierowania' => 3]);
    $dl = $r['naglowki']['content-length'] ?? null;
    return [
        'kod' => $r['kod'],
        'dlugosc' => $dl !== null && ctype_digit($dl) ? (int)$dl : null,
        'typ' => strtolower(trim(explode(';', (string)($r['naglowki']['content-type'] ?? ''))[0])),
        'url' => $r['url_koncowy'],
        'blad' => $r['blad'],
        'naglowki' => $r['naglowki'],
    ];
}

/** GET pliku tekstowego (robots.txt, sitemap, llms.txt, security.txt; ≤ EP_LIMIT_TEKST na test). Zwraca ok (kod 200), kod, tekst (UTF-8), url, typ, obcieto, blad. */
function pobierz_tekst(string $url, int $limitB = 200000, int $timeout = 4): array {
    $s = &ep_stan();
    $pusty = ['ok' => false, 'kod' => 0, 'tekst' => '', 'url' => $url, 'typ' => '', 'obcieto' => false, 'blad' => null];
    if ($s['tekst'] >= EP_LIMIT_TEKST) { $pusty['blad'] = 'limit_zadan'; return $pusty; }
    $s['tekst']++;
    $r = ep_pobierz($url, ['metoda' => 'GET', 'limit' => $limitB, 'timeout' => $timeout, 'przekierowania' => 3]);
    return [
        'ok' => $r['kod'] === 200 && $r['blad'] === null,
        'kod' => $r['kod'],
        'tekst' => ep_utf8($r['body'], $r['naglowki']),
        'url' => $r['url_koncowy'],
        'typ' => strtolower(trim(explode(';', (string)($r['naglowki']['content-type'] ?? ''))[0])),
        'obcieto' => $r['obcieto'],
        'blad' => $r['blad'],
    ];
}

/** Nagłówek odpowiedzi strony (nazwa bez względu na wielkość liter) albo null. */
function naglowek(array $ctx, string $nazwa): ?string {
    return $ctx['naglowki'][strtolower($nazwa)] ?? null;
}

/** Adres w tym samym źródle (schemat+host+port) co końcowy adres strony: zrodlo_url($ctx, '/robots.txt'). */
function zrodlo_url(array $ctx, string $sciezka): string {
    return ep_zlacz((string)$ctx['url_koncowy'], $sciezka);
}

/** Domena do zapytań pocztowych/DNS: host bez wiodącego „www.”. */
function domena_poczty(string $host): string {
    return preg_replace('/^www\./', '', strtolower($host)) ?? $host;
}

// ── DNS ──────────────────────────────────────────────────────────────────────────────────────────────────────────
function ep_dns(string $host, int $typ): array {
    try { $r = @dns_get_record($host, $typ); } catch (Throwable) { $r = false; }
    return is_array($r) ? $r : [];
}
/** Rekordy TXT jako lista napisów (części jednego rekordu sklejone). */
function dns_txt(string $host): array {
    return array_values(array_map(static fn($r) => (string)($r['txt'] ?? ''), ep_dns($host, DNS_TXT)));
}
/** Rekordy MX posortowane rosnąco po priorytecie: [['pri'=>int,'host'=>string], …]. */
function dns_mx(string $host): array {
    $m = array_map(static fn($r) => ['pri' => (int)($r['pri'] ?? 0), 'host' => strtolower(rtrim((string)($r['target'] ?? ''), '.'))], ep_dns($host, DNS_MX));
    usort($m, static fn($a, $b) => $a['pri'] <=> $b['pri']);
    return $m;
}
/** Adresy IPv4 (rekordy A). */
function dns_a(string $host): array {
    return array_values(array_filter(array_map(static fn($r) => (string)($r['ip'] ?? ''), ep_dns($host, DNS_A))));
}
/** Adresy IPv6 (rekordy AAAA). */
function dns_aaaa(string $host): array {
    return array_values(array_filter(array_map(static fn($r) => (string)($r['ipv6'] ?? ''), ep_dns($host, DNS_AAAA))));
}

/** Certyfikat TLS hosta (port 443): ['nazwa','wystawca','od','do' (znaczniki czasu),'dni' (do końca ważności),'pasuje' (nazwa hosta)] albo null. */
function certyfikat(string $host, int $port = 443): ?array {
    $ip = ep_ip_bezpieczny($host);
    if ($ip === null || ep_pozostalo() < 1.0) { return null; }
    $kontekst = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $gniazdo = @stream_socket_client('ssl://' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip) . ':' . $port, $en, $es, min(4.0, ep_pozostalo()), STREAM_CLIENT_CONNECT, $kontekst);
    if ($gniazdo === false) { return null; }
    $par = stream_context_get_params($gniazdo);
    fclose($gniazdo);
    $c = isset($par['options']['ssl']['peer_certificate']) ? @openssl_x509_parse($par['options']['ssl']['peer_certificate']) : false;
    if (!is_array($c)) { return null; }
    $nazwy = array_filter(array_map(static fn($x) => str_starts_with(trim($x), 'DNS:') ? strtolower(trim(substr(trim($x), 4))) : '', explode(',', (string)($c['extensions']['subjectAltName'] ?? ''))));
    $pasuje = false;
    foreach ($nazwy as $n) {
        if ($n === $host || (str_starts_with($n, '*.') && substr_count($host, '.') === substr_count($n, '.') && str_ends_with($host, substr($n, 1)))) { $pasuje = true; break; }
    }
    return [
        'nazwa' => (string)($c['subject']['CN'] ?? ''),
        'wystawca' => (string)($c['issuer']['O'] ?? $c['issuer']['CN'] ?? ''),
        'od' => (int)($c['validFrom_time_t'] ?? 0),
        'do' => (int)($c['validTo_time_t'] ?? 0),
        'dni' => (int)floor(((int)($c['validTo_time_t'] ?? 0) - time()) / 86400),
        'pasuje' => $pasuje,
    ];
}

// ── Wynik ────────────────────────────────────────────────────────────────────────────────────────────────────────
function ep_skroc(mixed $v, int $maks): string {
    $t = is_scalar($v) ? trim((string)preg_replace('/\s+/u', ' ', (string)$v)) : '';
    return mb_strlen($t) > $maks ? mb_substr($t, 0, $maks - 1) . '…' : $t;
}

/** Jedna pozycja wyniku. stan: ok|uwaga|blad|info. id musi być zgodny z JSON narzędzia (src/data/narzedzia/<slug>.json). */
function pozycja(string $id, string $nazwa, string $stan, mixed $wartosc, string $opis, string $zalecenie = ''): array {
    return [
        'id' => ep_skroc($id, 60),
        'nazwa' => ep_skroc($nazwa, 120),
        'stan' => in_array($stan, ['ok', 'uwaga', 'blad', 'info'], true) ? $stan : 'info',
        'wartosc' => ep_skroc($wartosc, 300),
        'opis' => ep_skroc($opis, 600),
        'zalecenie' => ep_skroc($zalecenie, 600),
    ];
}

/** Ocena 0–100 z pozycji: ok=1, uwaga=0,5, blad=0, info pomijane; wagi[id] (domyślnie 1). Brak pozycji ocenianych → null. */
function ocena_z_pozycji(array $pozycje, array $wagi = []): ?int {
    $suma = 0.0;
    $waga = 0.0;
    foreach ($pozycje as $p) {
        $pkt = ['ok' => 1.0, 'uwaga' => 0.5, 'blad' => 0.0][$p['stan'] ?? 'info'] ?? null;
        if ($pkt === null) { continue; }
        $w = (float)($wagi[$p['id'] ?? ''] ?? 1);
        $suma += $w * $pkt;
        $waga += $w;
    }
    return $waga > 0 ? (int)round(100 * $suma / $waga) : null;
}

/** Strefa oceny wg progów Google: ≥ 90 dobry, ≥ 50 srednio, niżej slabo; null → brak. */
function kolor(?int $ocena): string {
    return $ocena === null ? 'brak' : ($ocena >= 90 ? 'dobry' : ($ocena >= 50 ? 'srednio' : 'slabo'));
}

// ── HTML ─────────────────────────────────────────────────────────────────────────────────────────────────────────
/** [DOMDocument, DOMXPath] z HTML (ostrzeżenia libxml wyciszone, bez sieci). */
function dom(string $html): array {
    $d = new DOMDocument();
    $poprzedni = libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($poprzedni);
    return [$d, new DOMXPath($d)];
}

// ── Liczniki godzinowe (limit na skrót IP) ───────────────────────────────────────────────────────────────────────
/** Liczy wywołania w godzinie pod blokadą; false = limit przekroczony. Brak możliwości zapisu → true i wpis w error_log. */
function licznik_godzinowy(string $plik, int $limit): bool {
    $u = @fopen($plik, 'c+');
    if ($u === false || !flock($u, LOCK_EX)) {
        error_log('audyt-ecopower: licznik niedostępny — limit NIE jest egzekwowany');
        if ($u !== false) { fclose($u); }
        return true;
    }
    $zapis = json_decode((string)stream_get_contents($u), true);
    $okno = date('YmdH');
    $n = is_array($zapis) && ($zapis['okno'] ?? '') === $okno ? (int)($zapis['n'] ?? 0) : 0;
    $wolno = $n < $limit;
    if ($wolno) {
        ftruncate($u, 0);
        rewind($u);
        fwrite($u, json_encode(['okno' => $okno, 'n' => $n + 1]));
        fflush($u);
    }
    flock($u, LOCK_UN);
    fclose($u);
    @chmod($plik, 0600);
    return $wolno;
}
