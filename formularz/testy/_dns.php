<?php
declare(strict_types=1);
/**
 * Wspólne funkcje poczty w DNS: MX, SPF, DKIM, DMARC, BIMI. Używają ich moduły poczta_domeny i ochrona_przed_spamem
 * (jeden kod, nie dwie kopie). Tylko zapytania DNS (dns_txt/dns_mx z _wspolne.php), żadnych żądań HTTP.
 * Wszystkie funkcje zwracają tablice z danymi — teksty po polsku składa moduł, który z nich korzysta.
 */
if (!defined('AUDYT_EP')) { http_response_code(404); exit; }

/** Typowe selektory DKIM (nazwy nie da się odczytać z DNS, dlatego sprawdzamy najczęstsze). */
const DNS_SELEKTORY_DKIM = ['default', 'google', 'selector1', 'selector2', 'k1', 'k2', 'mail', 'dkim', 's1', 's2', 'mandrill', 'zoho', 'protonmail'];

/** Sufiksy dwuczłonowe, przy których „domena główna” ma trzy etykiety (uproszczona lista publicznych sufiksów). */
const DNS_SUFIKSY2 = ['com.pl', 'net.pl', 'org.pl', 'edu.pl', 'info.pl', 'biz.pl', 'waw.pl', 'co.uk', 'org.uk', 'com.au', 'com.br', 'co.jp', 'com.ua', 'com.tr'];

/** Pamięć podręczna TXT w obrębie jednego żądania (rekurencja SPF pyta o te same nazwy). */
function dns_txt_pamiec(string $host): array {
    static $p = [];
    $h = strtolower(rtrim($host, '.'));
    return $p[$h] ??= dns_txt($h);
}

/** Lista domen od podanej w górę do domeny głównej (bez sufiksu publicznego): a.b.c.pl → [a.b.c.pl, b.c.pl, c.pl]. */
function dns_lancuch_domen(string $domena): array {
    $d = strtolower(rtrim($domena, '.'));
    $out = [];
    $e = explode('.', $d);
    while (count($e) >= 2) {
        $kand = implode('.', $e);
        if (in_array($kand, DNS_SUFIKSY2, true)) { break; }
        $out[] = $kand;
        array_shift($e);
        if (count($out) >= 4) { break; }
    }
    return $out ?: [$d];
}

/** Domena, której poczta dotyczy: pierwsza z łańcucha (od hosta w górę), która ma MX albo SPF; w razie braku — sama domena bez „www.”. */
function dns_domena_poczty(string $host): string {
    $start = domena_poczty($host);
    foreach (dns_lancuch_domen($start) as $d) {
        if (dns_mx($d) !== [] || dns_rekord_z_prefiksem(dns_txt_pamiec($d), 'v=spf1') !== []) { return $d; }
    }
    return $start;
}

/** Rekordy TXT zaczynające się od prefiksu (bez względu na wielkość liter), z obciętymi spacjami. */
function dns_rekord_z_prefiksem(array $txt, string $prefiks): array {
    $out = [];
    foreach ($txt as $t) {
        $t = trim((string)$t);
        if (stripos($t, $prefiks) === 0 && (strlen($t) === strlen($prefiks) || in_array($t[strlen($prefiks)], [' ', ';', "\t"], true))) { $out[] = $t; }
    }
    return $out;
}

/**
 * MX domeny. Zwraca: rekordy (pri, host), dostawcy (nazwy rozpoznane po hoście, bez powtórzeń), dostawcy_hosty (nazwa → liczba serwerów),
 * nierozpoznane (hosty bez nazwy), null_mx (true = „0 .” — domena celowo nie przyjmuje poczty), brak (true = żadnego MX).
 */
function mx(string $domena): array {
    $r = dns_mx($domena);
    $nullMx = count($r) === 1 && ($r[0]['host'] === '' || $r[0]['host'] === '.');
    $dostawcy = [];
    $nierozp = [];
    if (!$nullMx) {
        foreach ($r as $m) {
            $n = dns_dostawca_poczty($m['host']);
            if ($n === null) { $nierozp[] = $m['host']; continue; }
            $dostawcy[$n] = ($dostawcy[$n] ?? 0) + 1;
        }
    }
    return ['rekordy' => $r, 'dostawcy' => array_keys($dostawcy), 'dostawcy_hosty' => $dostawcy, 'nierozpoznane' => $nierozp, 'null_mx' => $nullMx, 'brak' => $r === []];
}

/** Nazwa dostawcy poczty po hoście MX albo null. Bramki antyspamowe mają osobne nazwy (nie liczą się jako drugi dostawca skrzynek — patrz dns_czy_bramka). */
function dns_dostawca_poczty(string $hostMx): ?string {
    $h = strtolower(rtrim($hostMx, '.'));
    $wzorce = [
        'Google Workspace' => ['/(^|\.)(aspmx\.l\.google\.com|googlemail\.com|google\.com|smtp\.google\.com)$/'],
        'Microsoft 365' => ['/(^|\.)(mail\.protection\.outlook\.com|outlook\.com|hotmail\.com)$/'],
        'lh.pl' => ['/(^|\.)lh\.pl$/'],
        'OVH' => ['/(^|\.)(ovh\.net|ovh\.com|ovh\.pl)$/'],
        'home.pl' => ['/(^|\.)(home\.pl|homepl\.net)$/'],
        'cyber_Folks' => ['/(^|\.)(cyberfolks\.pl|cyber-folks\.pl|cyfr\.pl)$/'],
        'nazwa.pl' => ['/(^|\.)(nazwa\.pl|az\.pl)$/'],
        'Zoho Mail' => ['/(^|\.)zoho\.(com|eu|in)$/'],
        'Proton Mail' => ['/(^|\.)(protonmail\.ch|proton\.me)$/'],
        'Cloudflare Email Routing' => ['/(^|\.)mx\.cloudflare\.net$/'],
        'iCloud' => ['/(^|\.)(icloud\.com|me\.com)$/'],
        'Yahoo' => ['/(^|\.)(yahoodns\.net|yahoo\.com)$/'],
        'Onet Poczta' => ['/(^|\.)(poczta\.onet\.pl|onet\.pl)$/'],
        'WP Poczta' => ['/(^|\.)wp\.pl$/'],
        'Interia' => ['/(^|\.)interia\.pl$/'],
        'Mailgun' => ['/(^|\.)mailgun\.org$/'],
        'Proofpoint (bramka)' => ['/(^|\.)(pphosted\.com|ppe-hosted\.com)$/'],
        'Mimecast (bramka)' => ['/(^|\.)mimecast\.com$/'],
        'Barracuda (bramka)' => ['/(^|\.)barracudanetworks\.com$/'],
    ];
    foreach ($wzorce as $nazwa => $lista) {
        foreach ($lista as $re) { if (preg_match($re, $h)) { return $nazwa; } }
    }
    return null;
}

/** Czy dostawca to bramka antyspamowa (stoi przed właściwą skrzynką, więc nie liczy się jako osobny dostawca poczty). */
function dns_czy_bramka(string $dostawca): bool {
    return str_contains($dostawca, '(bramka)');
}

/**
 * SPF. Zwraca: rekord (pierwszy; null = brak), rekordy (wszystkie rekordy v=spf1), rekordow (ile rekordów v=spf1 — więcej niż jeden to błąd), mechanizmy (lista napisów),
 * all ('-'|'~'|'?'|'+'|null), lookupy (liczba zapytań DNS wg RFC 7208: include, a, mx, ptr, exists, redirect), include (domeny),
 * redirect (domena|null), bledy (lista opisów składni/działania), przekroczono (lookupy > 10), niepelne (true = przerwano liczenie przy ~25 zapytaniach).
 */
function spf(string $domena): array {
    $txt = dns_rekord_z_prefiksem(dns_txt_pamiec($domena), 'v=spf1');
    $wynik = ['rekord' => $txt[0] ?? null, 'rekordy' => $txt, 'rekordow' => count($txt), 'mechanizmy' => [], 'all' => null, 'lookupy' => 0, 'include' => [], 'redirect' => null, 'bledy' => [], 'przekroczono' => false, 'niepelne' => false];
    if ($txt === []) { return $wynik; }
    if (count($txt) > 1) { $wynik['bledy'][] = 'Domena ma ' . count($txt) . ' rekordy SPF — musi być dokładnie jeden, inaczej SPF jest nieważny.'; }
    $odwiedzone = [strtolower($domena) => true];
    $zapytania = 0;
    $analiza = null;
    $analiza = function (string $rekord, string $nazwa, int $poziom, bool $glowny) use (&$wynik, &$odwiedzone, &$zapytania, &$analiza): void {
        $czesci = preg_split('/\s+/', trim($rekord)) ?: [];
        array_shift($czesci); // v=spf1
        $widzialAll = false;
        foreach ($czesci as $c) {
            if ($c === '') { continue; }
            if ($widzialAll && $glowny) { $wynik['bledy'][] = 'Po „all” stoi jeszcze „' . $c . '” — wszystko po „all” jest ignorowane.'; $widzialAll = false; }
            if (!preg_match('/^([+\-~?]?)([a-z0-9]+)(?:([:=\/])(.*))?$/i', $c, $m)) { if ($glowny) { $wynik['bledy'][] = 'Nierozpoznany zapis „' . $c . '” w rekordzie SPF.'; } continue; }
            $kw = strtolower($m[2]);
            $kwal = $m[1];
            $arg = $m[4] ?? '';
            if (($m[3] ?? '') === '=' && $kw !== 'redirect' && $kw !== 'exp') { if ($glowny) { $wynik['mechanizmy'][] = $c; } continue; } // modyfikator nieznany
            if ($glowny) { $wynik['mechanizmy'][] = $c; }
            switch ($kw) {
                case 'all':
                    if ($glowny) { $wynik['all'] = $kwal === '' ? '+' : $kwal; $widzialAll = true; }
                    break;
                case 'ip4':
                case 'ip6':
                    $baza = explode('/', $arg)[0];
                    $ok = $kw === 'ip4' ? filter_var($baza, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false : filter_var($baza, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
                    if (!$ok) { $wynik['bledy'][] = 'Adres w „' . $c . '” nie jest poprawnym ' . ($kw === 'ip4' ? 'IPv4' : 'IPv6') . '.'; }
                    break;
                case 'a':
                case 'mx':
                case 'exists':
                case 'ptr':
                    $wynik['lookupy']++;
                    if ($kw === 'ptr' && $glowny) { $wynik['bledy'][] = 'Mechanizm „ptr” jest przestarzały i spowalnia sprawdzanie — usuń go.'; }
                    if ($kw === 'exists' && $arg === '') { $wynik['bledy'][] = 'Mechanizm „exists” bez domeny.'; }
                    break;
                case 'include':
                case 'redirect':
                    if ($arg === '') { $wynik['bledy'][] = 'Mechanizm „' . $kw . '” bez domeny.'; break; }
                    $wynik['lookupy']++;
                    if ($glowny) { if ($kw === 'include') { $wynik['include'][] = $arg; } else { $wynik['redirect'] = $arg; } }
                    $cel = strtolower(rtrim($arg, '.'));
                    if (str_contains($cel, '%')) { break; } // makra — nie rozwijamy
                    if (isset($odwiedzone[$cel])) { $wynik['bledy'][] = 'Pętla w SPF: „' . $cel . '” odwołuje się do siebie.'; break; }
                    if ($poziom >= 6 || $zapytania >= 25 || ep_pozostalo() < 2.0) { $wynik['niepelne'] = true; break; }
                    $odwiedzone[$cel] = true;
                    $zapytania++;
                    $tam = dns_rekord_z_prefiksem(dns_txt_pamiec($cel), 'v=spf1');
                    if ($tam === []) { $wynik['bledy'][] = 'Domena „' . $cel . '” z „' . $kw . '” nie ma rekordu SPF — takie odwołanie unieważnia cały SPF.'; break; }
                    if (count($tam) > 1) { $wynik['bledy'][] = 'Domena „' . $cel . '” ma kilka rekordów SPF.'; }
                    $analiza($tam[0], $cel, $poziom + 1, false);
                    break;
                case 'exp':
                    break;
                default:
                    if ($glowny) { $wynik['bledy'][] = 'Nieznany mechanizm „' . $c . '” w rekordzie SPF.'; }
            }
        }
        // redirect bez własnego „all” przejmuje politykę z celu (na poziomie głównym już obsłużone rekurencją, ale „all” celu zapisujemy poniżej)
    };
    // „all” w celu redirect też obowiązuje: analiza zapisuje „all” tylko dla głównego, więc doczytujemy je osobno.
    $analiza($txt[0], $domena, 0, true);
    if ($wynik['all'] === null && $wynik['redirect'] !== null) {
        $cel = strtolower(rtrim($wynik['redirect'], '.'));
        $tam = dns_rekord_z_prefiksem(dns_txt_pamiec($cel), 'v=spf1');
        if ($tam !== [] && preg_match('/(?:^|\s)([+\-~?]?)all(?:\s|$)/i', $tam[0], $m)) { $wynik['all'] = $m[1] === '' ? '+' : $m[1]; }
    }
    $wynik['przekroczono'] = $wynik['lookupy'] > 10;
    return $wynik;
}

/** Nazwa DNS → pakiet wire (zapytanie TXT, rekurencja włączona). */
function dns_pakiet_txt(string $nazwa, int $id): string {
    $q = pack('nnnnnn', $id, 0x0100, 1, 0, 0, 0);
    foreach (explode('.', rtrim($nazwa, '.')) as $l) { $q .= chr(strlen($l)) . $l; }
    return $q . "\0" . pack('nn', 16, 1);
}

/** Pomija nazwę DNS (z kompresją wskaźnikiem) i zwraca przesunięcie za nią. */
function dns_pomin_nazwe(string $p, int $o): int {
    while ($o < strlen($p)) {
        $l = ord($p[$o]);
        if ($l === 0) { return $o + 1; }
        if (($l & 0xC0) === 0xC0) { return $o + 2; }
        $o += $l + 1;
    }
    return $o;
}

/** Odpowiedź DNS → ['kod'=>rcode, 'obcieta'=>bool, 'txt'=>[napisy]] albo null (błąd parsowania). */
function dns_parsuj_txt(string $p, int $id): ?array {
    if (strlen($p) < 12) { return null; }
    $h = unpack('nid/nflagi/nqd/nan', $p);
    if ($h['id'] !== $id) { return null; }
    $o = 12;
    for ($i = 0; $i < $h['qd']; $i++) { $o = dns_pomin_nazwe($p, $o) + 4; }
    $txt = [];
    for ($i = 0; $i < $h['an'] && $o + 10 <= strlen($p); $i++) {
        $o = dns_pomin_nazwe($p, $o);
        $r = unpack('ntyp/nklasa/Nttl/ndl', substr($p, $o, 10));
        $o += 10;
        if ($r['typ'] === 16) {
            $napis = '';
            for ($k = $o, $kon = $o + $r['dl']; $k < $kon && $k < strlen($p);) { $dl = ord($p[$k]); $napis .= substr($p, $k + 1, $dl); $k += $dl + 1; }
            $txt[] = $napis;
        }
        $o += $r['dl'];
    }
    return ['kod' => $h['flagi'] & 0x0F, 'obcieta' => (bool)($h['flagi'] & 0x0200), 'txt' => $txt];
}

/**
 * Równoległe zapytania TXT o wiele nazw naraz (UDP do resolwera z /etc/resolv.conf). Zwraca nazwa → lista napisów TXT albo null,
 * gdy resolwer nie odpowiedział w czasie $limitS. Gdy UDP nie działa, pyta po kolei przez dns_txt, w tym samym limicie czasu.
 */
function dns_txt_rownolegle(array $nazwy, float $limitS = 4.0): array {
    $wynik = array_fill_keys($nazwy, null);
    $limitS = max(0.5, min($limitS, ep_pozostalo() - 1.0));
    $ns = [];
    foreach (@file('/etc/resolv.conf', FILE_IGNORE_NEW_LINES) ?: [] as $linia) {
        if (preg_match('/^\s*nameserver\s+(\d{1,3}(?:\.\d{1,3}){3})\b/', $linia, $m)) { $ns[] = $m[1]; }
    }
    $gniazda = [];
    $utworzone = array_fill_keys($nazwy, false);
    if ($ns !== []) {
        foreach (array_values($nazwy) as $i => $nazwa) {
            $g = @stream_socket_client('udp://' . $ns[0] . ':53', $en, $es, 1.0);
            if ($g === false) { continue; }
            stream_set_blocking($g, false);
            $id = 0x2000 + $i;
            @fwrite($g, dns_pakiet_txt($nazwa, $id));
            $gniazda[$nazwa] = [$g, $id];
            $utworzone[$nazwa] = true;
        }
    }
    $koniec = microtime(true) + $limitS;
    while ($gniazda !== [] && microtime(true) < $koniec) {
        $r = array_map(static fn($x) => $x[0], $gniazda);
        $w = $e = [];
        $ile = @stream_select($r, $w, $e, 0, 200000);
        if ($ile === false) { break; }
        foreach ($gniazda as $nazwa => [$g, $id]) {
            if (!in_array($g, $r, true)) { continue; }
            $pak = (string)@fread($g, 4096);
            $p = dns_parsuj_txt($pak, $id);
            fclose($g);
            unset($gniazda[$nazwa]);
            if ($p === null || $p['obcieta']) { $wynik[$nazwa] = dns_txt($nazwa); continue; }
            $wynik[$nazwa] = $p['kod'] === 0 || $p['kod'] === 3 ? $p['txt'] : null; // 0 OK, 3 NXDOMAIN; reszta (SERVFAIL…) = nie wiadomo
        }
    }
    foreach ($gniazda as [$g]) { fclose($g); }
    // brak resolwera albo gniazda dla nazwy: pytamy po kolei systemowym DNS, dopóki starcza czasu
    foreach ($nazwy as $nazwa) {
        if (!$utworzone[$nazwa] && microtime(true) < $koniec) { $wynik[$nazwa] = dns_txt($nazwa); }
    }
    return $wynik;
}

/**
 * DKIM: sprawdza typowe selektory (nazwy nie da się wyczytać z DNS) jednym równoległym zapytaniem. Zwraca: znalezione (lista [selektor, rekord]),
 * sprawdzone (selektory, na które DNS odpowiedział), niesprawdzone (selektory bez odpowiedzi w czasie — wolne serwery DNS domeny),
 * uniewazniony (selektory z pustym p=). Rekord będący CNAME-em do dostawcy (np. Microsoft 365) resolwer rozwija sam.
 */
function dkim(string $domena, ?array $selektory = null): array {
    $selektory = $selektory ?? DNS_SELEKTORY_DKIM;
    $nazwy = [];
    foreach ($selektory as $s) { $nazwy[$s . '._domainkey.' . $domena] = $s; }
    $odp = dns_txt_rownolegle(array_keys($nazwy), 4.0);
    $znalezione = [];
    $uniewazniony = [];
    $sprawdzone = [];
    $niesprawdzone = [];
    foreach ($nazwy as $nazwa => $s) {
        $txt = $odp[$nazwa] ?? null;
        if ($txt === null) { $niesprawdzone[] = $s; continue; }
        $sprawdzone[] = $s;
        $klucz = dns_rekord_z_prefiksem($txt, 'v=DKIM1');
        if ($klucz === []) { foreach ($txt as $t) { if (preg_match('/(^|;)\s*p=/i', $t)) { $klucz[] = $t; } } } // rekord bez „v=” też bywa
        if ($klucz === []) { continue; }
        if (preg_match('/(^|;)\s*p=\s*(;|$)/i', $klucz[0])) { $uniewazniony[] = $s; continue; }
        $znalezione[] = ['selektor' => $s, 'rekord' => $klucz[0]];
    }
    return ['znalezione' => $znalezione, 'sprawdzone' => $sprawdzone, 'niesprawdzone' => $niesprawdzone, 'uniewazniony' => $uniewazniony];
}

/**
 * DMARC. Szuka _dmarc.<domena>, a gdy brak — na domenie nadrzędnej (jak robią to odbiorcy poczty).
 * Zwraca: rekord (null = brak), domena (gdzie znaleziono), dziedziczony (true = z domeny nadrzędnej), p, sp, rua[], ruf[], pct (int, domyślnie 100),
 * adkim, aspf (r|s, domyślnie r), fo, rekordow (liczba rekordów), bledy.
 */
function dmarc(string $domena): array {
    $wynik = ['rekord' => null, 'domena' => $domena, 'dziedziczony' => false, 'p' => null, 'sp' => null, 'rua' => [], 'ruf' => [], 'pct' => 100, 'adkim' => 'r', 'aspf' => 'r', 'fo' => null, 'rekordow' => 0, 'bledy' => []];
    $lancuch = dns_lancuch_domen($domena);
    foreach ($lancuch as $i => $d) {
        $txt = dns_rekord_z_prefiksem(dns_txt('_dmarc.' . $d), 'v=DMARC1');
        if ($txt === []) { continue; }
        $wynik['rekord'] = $txt[0];
        $wynik['rekordow'] = count($txt);
        $wynik['domena'] = $d;
        $wynik['dziedziczony'] = $i > 0;
        break;
    }
    if ($wynik['rekord'] === null) { return $wynik; }
    if ($wynik['rekordow'] > 1) { $wynik['bledy'][] = 'Jest kilka rekordów DMARC — odbiorcy zignorują je wszystkie.'; }
    $tagi = [];
    foreach (explode(';', $wynik['rekord']) as $par) {
        $par = trim($par);
        if ($par === '' || !str_contains($par, '=')) { continue; }
        [$k, $v] = array_map('trim', explode('=', $par, 2));
        $tagi[strtolower($k)] ??= $v;
    }
    $p = strtolower($tagi['p'] ?? '');
    if ($p !== '' && !in_array($p, ['none', 'quarantine', 'reject'], true)) { $wynik['bledy'][] = 'Wartość p=' . $p . ' jest niepoprawna (dozwolone: none, quarantine, reject).'; $p = ''; }
    if ($p === '') { $wynik['bledy'][] = 'W rekordzie brakuje polityki „p=”.'; }
    $wynik['p'] = $p ?: null;
    $sp = strtolower($tagi['sp'] ?? '');
    $wynik['sp'] = in_array($sp, ['none', 'quarantine', 'reject'], true) ? $sp : null;
    foreach (['rua', 'ruf'] as $k) {
        if (!empty($tagi[$k])) { $wynik[$k] = array_values(array_filter(array_map('trim', explode(',', $tagi[$k])))); }
    }
    if (isset($tagi['pct'])) {
        if (ctype_digit($tagi['pct']) && (int)$tagi['pct'] <= 100) { $wynik['pct'] = (int)$tagi['pct']; } else { $wynik['bledy'][] = 'Wartość pct=' . $tagi['pct'] . ' jest niepoprawna (dozwolone 0–100).'; }
    }
    foreach (['adkim', 'aspf'] as $k) {
        $v = strtolower($tagi[$k] ?? '');
        if (in_array($v, ['r', 's'], true)) { $wynik[$k] = $v; }
    }
    $wynik['fo'] = $tagi['fo'] ?? null;
    return $wynik;
}

/** BIMI (logo marki przy wiadomościach): rekord default._bimi.<domena>. Zwraca: rekord (null = brak), logo (adres l=), certyfikat (adres a=, VMC). */
function bimi(string $domena): array {
    $txt = dns_rekord_z_prefiksem(dns_txt('default._bimi.' . $domena), 'v=BIMI1');
    $w = ['rekord' => $txt[0] ?? null, 'logo' => null, 'certyfikat' => null];
    if ($txt === []) { return $w; }
    foreach (explode(';', $txt[0]) as $par) {
        if (!str_contains($par, '=')) { continue; }
        [$k, $v] = array_map('trim', explode('=', $par, 2));
        if (strtolower($k) === 'l' && $v !== '') { $w['logo'] = $v; }
        if (strtolower($k) === 'a' && $v !== '') { $w['certyfikat'] = $v; }
    }
    return $w;
}
