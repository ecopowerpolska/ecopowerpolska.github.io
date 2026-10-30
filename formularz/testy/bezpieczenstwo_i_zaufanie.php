<?php
declare(strict_types=1);
/**
 * Bezpieczeństwo i zaufanie: HTTPS, certyfikat, nagłówki ochronne, mieszane treści, ujawniane wersje, formularze, ciasteczka.
 * Żądania dodatkowe: 1× HEAD http://host/ (bez śledzenia przekierowań), 1× certyfikat (TLS bez weryfikacji łańcucha), 1× security.txt.
 */
if (!defined('AUDYT_EP')) { http_response_code(404); exit; }

/** Dyrektywy CSP jako [nazwa => lista źródeł] (pierwsza polityka z nagłówka). */
function bz_csp_dyrektywy(string $csp): array {
    $wynik = [];
    $pierwsza = explode(',', $csp)[0];
    foreach (explode(';', $pierwsza) as $d) {
        $cz = preg_split('/\s+/', trim($d)) ?: [];
        $nazwa = strtolower((string)array_shift($cz));
        if ($nazwa !== '' && !isset($wynik[$nazwa])) { $wynik[$nazwa] = array_map('strtolower', $cz); }
    }
    return $wynik;
}

/** Rozdziela połączone nagłówki set-cookie (ep_pobierz skleja je „, ”) na pojedyncze ciasteczka. */
function bz_ciasteczka(?string $sklejone): array {
    if ($sklejone === null || trim($sklejone) === '') { return []; }
    $czesci = preg_split('/,\s*(?=[A-Za-z0-9_\-.%!#$&\'*+^`|~]+=)/', $sklejone) ?: [];
    $wynik = [];
    foreach ($czesci as $c) {
        $atr = array_map('trim', explode(';', $c));
        $para = (string)array_shift($atr);
        $nazwa = trim(explode('=', $para, 2)[0]);
        if ($nazwa === '') { continue; }
        $mala = array_map('strtolower', $atr);
        $wynik[] = [
            'nazwa' => $nazwa,
            'secure' => in_array('secure', $mala, true),
            'httponly' => in_array('httponly', $mala, true),
        ];
    }
    return $wynik;
}

function test_bezpieczenstwo_i_zaufanie(string $url, array $ctx): array {
    $poz = [];
    $host = (string)$ctx['host'];
    $koncowy = (string)$ctx['url_koncowy'];
    $https = stripos($koncowy, 'https://') === 0;
    // audyt.php ponowił pobranie bez weryfikacji TLS, bo certyfikat strony jest odrzucany (wygasły, obcy, niepełny łańcuch).
    $tlsBlad = !empty($ctx['tls_blad']);
    $htaccessHttps = 'W pliku .htaccess dodaj: RewriteEngine On, RewriteCond %{HTTPS} off, RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]. W Cloudflare włącz „Always Use HTTPS”.';

    // 1) HTTPS
    $poz[] = pozycja('https', 'Połączenie szyfrowane (HTTPS)', $https && !$tlsBlad ? 'ok' : 'blad',
        $tlsBlad ? 'certyfikat odrzucony' : ($https ? 'tak' : 'nie'),
        $tlsBlad ? 'Szyfrowanie jest włączone, ale przeglądarki odrzucają certyfikat tej strony i pokazują ostrzeżenie o niezaufanym połączeniu.' : ($https ? 'Strona otwiera się szyfrowanym połączeniem HTTPS.' : 'Strona otwiera się bez szyfrowania (http://): przeglądarki oznaczają ją jako „Niezabezpieczona”, a dane z formularzy mogą być podsłuchane.'),
        $tlsBlad ? 'Napraw certyfikat (pozycja niżej), bo dopóki jest odrzucany, odwiedzający nie wejdą na stronę bez klikania w ostrzeżenie.' : ($https ? '' : 'Zainstaluj certyfikat (np. bezpłatny Let’s Encrypt w panelu hostingu) i ustaw stronę tak, by odpowiadała pod https://.'));

    // 2) Przekierowanie http -> https (jedno żądanie, bez śledzenia dalszych przeskoków)
    $r = ep_pobierz('http://' . $host . '/', ['metoda' => 'HEAD', 'timeout' => 3, 'przekierowania' => 0]);
    $loc = (string)($r['naglowki']['location'] ?? '');
    $locAbs = $loc !== '' ? ep_zlacz('http://' . $host . '/', $loc) : '';
    $przekierowanie = in_array($r['kod'], [301, 302, 303, 307, 308], true) && $locAbs !== '';
    if ($r['kod'] === 0) {
        $poz[] = pozycja('przekierowanie-https', 'Przekierowanie z http na https', 'info', 'nie sprawdzono',
            'Port 80 (http) nie odpowiedział w czasie testu, więc nie ma jak sprawdzić przekierowania.',
            'Upewnij się, że adres http://' . $host . ' przekierowuje na https:// — wpisując adres bez „https” ludzie trafiają zwykle na http.');
    } elseif ($przekierowanie && stripos($locAbs, 'https://') === 0) {
        $trwale = in_array($r['kod'], [301, 308], true);
        $poz[] = pozycja('przekierowanie-https', 'Przekierowanie z http na https', $trwale ? 'ok' : 'uwaga',
            'kod ' . $r['kod'],
            $trwale ? 'Adres http:// przekierowuje trwale (' . $r['kod'] . ') na https://.' : 'Adres http:// przekierowuje na https:// tymczasowo (' . $r['kod'] . '), więc wyszukiwarki mogą nadal zapamiętywać wersję http.',
            $trwale ? '' : 'Zmień przekierowanie na trwałe (301). W .htaccess: RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301].');
    } elseif ($przekierowanie) {
        $poz[] = pozycja('przekierowanie-https', 'Przekierowanie z http na https', 'uwaga', 'kod ' . $r['kod'] . ', dalej http',
            'Adres http:// przekierowuje najpierw na inny adres http://, a dopiero kolejny przeskok może prowadzić do https — ruch przez chwilę idzie bez szyfrowania.',
            'Ustaw przekierowanie bezpośrednio na https://' . $host . '/. ' . $htaccessHttps);
    } else {
        $poz[] = pozycja('przekierowanie-https', 'Przekierowanie z http na https', 'blad', 'brak (kod ' . $r['kod'] . ')',
            'Adres http://' . $host . '/ otwiera stronę bez przekierowania na https, więc część odwiedzających zostaje na wersji nieszyfrowanej.',
            $htaccessHttps);
    }

    // 3) Certyfikat
    $c = certyfikat($host);
    if ($tlsBlad) {
        $wystTls = $c !== null && $c['wystawca'] !== '' ? $c['wystawca'] : 'nieznany wystawca';
        $poz[] = pozycja('certyfikat', 'Certyfikat TLS', 'blad', 'odrzucany przez przeglądarki',
            'Certyfikat strony (' . $wystTls . ') nie przechodzi weryfikacji: jest wygasły, wystawiony na inną nazwę albo ma niepełny łańcuch. Przeglądarka pokaże „Połączenie nie jest prywatne”.',
            'Wystaw nowy certyfikat dla ' . $host . ' (np. Let’s Encrypt w panelu hostingu), wgraj go razem z certyfikatami pośrednimi i włącz automatyczne odnawianie.');
    } elseif ($c === null) {
        $poz[] = pozycja('certyfikat', 'Certyfikat TLS', $https ? 'info' : 'blad', $https ? 'nie odczytano' : 'brak',
            $https ? 'Nie udało się odczytać certyfikatu w czasie testu (serwer nie odpowiedział na zapytanie TLS).' : 'Serwer nie przedstawił certyfikatu na porcie 443, więc nie ma strony pod https://.',
            $https ? '' : 'Wystaw certyfikat dla ' . $host . ' (np. Let’s Encrypt w panelu hostingu) i włącz HTTPS.');
    } else {
        $dni = (int)$c['dni'];
        $wyst = $c['wystawca'] !== '' ? $c['wystawca'] : 'nieznany wystawca';
        $data = $c['do'] > 0 ? date('d.m.Y', (int)$c['do']) : '?';
        if (!$c['pasuje']) {
            $stan = 'blad';
            $opis = 'Certyfikat (' . $c['nazwa'] . ') nie obejmuje nazwy ' . $host . ' — przeglądarka pokaże ostrzeżenie o niezaufanym połączeniu.';
            $zal = 'Wystaw certyfikat, który obejmuje ' . $host . ' (także wersję z „www” i bez), i wgraj go na serwer.';
        } elseif ($dni < 0) {
            $stan = 'blad';
            $opis = 'Certyfikat wygasł ' . $data . ' (wystawca: ' . $wyst . ') — przeglądarki blokują wejście na stronę.';
            $zal = 'Odnów certyfikat od razu i włącz automatyczne odnawianie w panelu hostingu.';
        } elseif ($dni < 14) {
            $stan = 'blad';
            $opis = 'Certyfikat (' . $wyst . ') wygasa za ' . $dni . ' dni (' . $data . ') — grozi blokadą strony w przeglądarkach.';
            $zal = 'Odnów certyfikat teraz i sprawdź, czy automatyczne odnawianie działa.';
        } elseif ($dni < 30) {
            $stan = 'uwaga';
            $opis = 'Certyfikat (' . $wyst . ') wygasa za ' . $dni . ' dni (' . $data . ').';
            $zal = 'Sprawdź, czy automatyczne odnawianie certyfikatu jest włączone; jeśli nie, odnów go ręcznie.';
        } else {
            $stan = 'ok';
            $opis = 'Certyfikat wystawił ' . $wyst . ', ważny do ' . $data . ' (jeszcze ' . $dni . ' dni) i pasuje do nazwy ' . $host . '.';
            $zal = '';
        }
        $poz[] = pozycja('certyfikat', 'Certyfikat TLS', $stan, $wyst . ($dni >= 0 ? ', ' . $dni . ' dni' : ', wygasł'), $opis, $zal);
    }

    // 4) HSTS
    $hsts = naglowek($ctx, 'strict-transport-security');
    $hstsZal = 'Dodaj nagłówek Strict-Transport-Security w .htaccess: Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains". W Cloudflare: SSL/TLS → Edge Certificates → HSTS. Włączaj dopiero, gdy wszystkie podstrony i subdomeny działają po https.';
    if (!$https) {
        $poz[] = pozycja('hsts', 'HSTS (zawsze https)', 'blad', 'brak', 'HSTS działa tylko na stronie szyfrowanej, a ta jest dostępna wyłącznie przez http.', 'Najpierw włącz HTTPS, potem dodaj nagłówek Strict-Transport-Security.');
    } elseif ($hsts === null || trim($hsts) === '') {
        $poz[] = pozycja('hsts', 'HSTS (zawsze https)', 'blad', 'brak',
            'Serwer nie wysyła nagłówka HSTS, więc przeglądarka nie zapamiętuje, że strona ma być otwierana tylko przez https.', $hstsZal);
    } else {
        $maxAge = preg_match('/max-age\s*=\s*"?(\d+)/i', $hsts, $m) ? (int)$m[1] : 0;
        $subs = (bool)preg_match('/includeSubDomains/i', $hsts);
        $dniMax = (int)round($maxAge / 86400);
        if ($maxAge < 15552000) {
            $poz[] = pozycja('hsts', 'HSTS (zawsze https)', 'uwaga', 'max-age ' . $maxAge . ' s',
                'HSTS jest włączony, ale zapamiętywany tylko przez ' . $dniMax . ' dni — zalecane minimum to 180 dni (15552000 s).',
                'Zwiększ max-age do co najmniej 15552000, najlepiej 31536000: Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains".');
        } elseif (!$subs) {
            $poz[] = pozycja('hsts', 'HSTS (zawsze https)', 'uwaga', 'max-age ' . $dniMax . ' dni, bez subdomen',
                'HSTS jest włączony na ' . $dniMax . ' dni, ale nie obejmuje subdomen, więc one pozostają bez tej ochrony.',
                'Dopisz includeSubDomains: Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains" (po upewnieniu się, że subdomeny też mają https).');
        } else {
            $poz[] = pozycja('hsts', 'HSTS (zawsze https)', 'ok', 'max-age ' . $dniMax . ' dni, z subdomenami',
                'HSTS jest włączony na ' . $dniMax . ' dni i obejmuje subdomeny.');
        }
    }

    // 5) CSP
    $csp = naglowek($ctx, 'content-security-policy');
    $cspRo = naglowek($ctx, 'content-security-policy-report-only');
    [, $xp] = dom((string)$ctx['html']);
    $cspMeta = '';
    foreach ($xp->query('//meta[@http-equiv]') ?: [] as $mt) {
        if (strtolower(trim((string)$mt->getAttribute('http-equiv'))) === 'content-security-policy') { $cspMeta = (string)$mt->getAttribute('content'); break; }
    }
    $cspZal = 'Dodaj nagłówek Content-Security-Policy w .htaccess, np. Header always set Content-Security-Policy "default-src \'self\'; img-src \'self\' data: https:; frame-ancestors \'self\'" — najpierw wypróbuj jako Content-Security-Policy-Report-Only, by nie zablokować skryptów strony.';
    $aktywna = ($csp !== null && trim($csp) !== '') ? $csp : $cspMeta;
    if ($aktywna !== '') {
        $dyr = bz_csp_dyrektywy($aktywna);
        $zrodla = $dyr['script-src'] ?? ($dyr['default-src'] ?? []);
        $slaba = in_array("'unsafe-inline'", $zrodla, true) && !preg_grep("/^'(nonce-|sha(256|384|512)-)|^'strict-dynamic'/", $zrodla);
        $zMeta = ($csp === null || trim($csp) === '');
        if ($slaba) {
            $poz[] = pozycja('csp', 'Polityka treści (CSP)', 'uwaga', $zMeta ? 'jest (w znaczniku meta), osłabiona' : 'jest, osłabiona',
                'Polityka CSP jest ustawiona, ale pozwala na skrypty wbudowane (\'unsafe-inline\') bez nonce ani skrótu, co mocno ogranicza jej ochronę przed wstrzyknięciem kodu.',
                'Zastąp \'unsafe-inline\' w script-src losowymi wartościami nonce albo skrótami sha256 skryptów wbudowanych.');
        } else {
            $poz[] = pozycja('csp', 'Polityka treści (CSP)', 'ok', $zMeta ? 'jest (w znaczniku meta)' : 'jest',
                'Strona ogranicza, skąd mogą się ładować skrypty i inne zasoby (Content-Security-Policy).' . ($zMeta ? ' Polityka siedzi w znaczniku meta, który nie obsługuje frame-ancestors ani raportowania.' : ''));
        }
    } elseif ($cspRo !== null && trim($cspRo) !== '') {
        $poz[] = pozycja('csp', 'Polityka treści (CSP)', 'uwaga', 'tylko raportuje',
            'Serwer wysyła CSP wyłącznie w trybie raportowania (Report-Only): nic nie blokuje, tylko zgłasza naruszenia.',
            'Gdy raporty będą czyste, zmień nagłówek Content-Security-Policy-Report-Only na Content-Security-Policy.');
    } else {
        $poz[] = pozycja('csp', 'Polityka treści (CSP)', 'blad', 'brak',
            'Brak Content-Security-Policy: gdyby ktoś wstrzyknął do strony obcy skrypt, przeglądarka nie ma reguł, które by go zatrzymały.', $cspZal);
    }

    // 6) Ochrona przed osadzaniem w ramce (X-Frame-Options / frame-ancestors)
    $xfo = strtoupper(trim((string)naglowek($ctx, 'x-frame-options')));
    $fa = ($csp !== null && preg_match('/frame-ancestors\s+([^;]+)/i', $csp, $m)) ? trim($m[1]) : '';
    if ($fa !== '' || in_array($xfo, ['DENY', 'SAMEORIGIN'], true)) {
        $poz[] = pozycja('ramki', 'Ochrona przed podszyciem w ramce', 'ok', $fa !== '' ? 'frame-ancestors ' . $fa : $xfo,
            'Obca strona nie może osadzić tej w ramce, więc nie da się jej użyć do wyłudzenia kliknięć (clickjacking).');
    } elseif ($xfo !== '') {
        $poz[] = pozycja('ramki', 'Ochrona przed podszyciem w ramce', 'uwaga', $xfo,
            'Wartość X-Frame-Options („' . $xfo . '”) jest nieobsługiwana przez nowoczesne przeglądarki, więc ochrona może nie działać.',
            'Ustaw Header always set X-Frame-Options "SAMEORIGIN" albo w CSP frame-ancestors \'self\'.');
    } else {
        $poz[] = pozycja('ramki', 'Ochrona przed podszyciem w ramce', 'blad', 'brak',
            'Strona może zostać osadzona w ramce na obcej witrynie i posłużyć do wyłudzenia kliknięć (clickjacking).',
            'Dodaj w .htaccess: Header always set X-Frame-Options "SAMEORIGIN" (albo w CSP frame-ancestors \'self\').');
    }

    // 7) nosniff
    $nos = strtolower(trim((string)naglowek($ctx, 'x-content-type-options')));
    $poz[] = pozycja('nosniff', 'Zakaz zgadywania typu plików', $nos === 'nosniff' ? 'ok' : 'blad', $nos === 'nosniff' ? 'nosniff' : 'brak',
        $nos === 'nosniff' ? 'Przeglądarka nie zgaduje typu plików, więc nie wykona obrazu czy tekstu jako skryptu.' : 'Brak nagłówka X-Content-Type-Options: przeglądarka może „zgadywać” typ plików i potraktować podrzucony plik jako skrypt.',
        $nos === 'nosniff' ? '' : 'Dodaj w .htaccess: Header always set X-Content-Type-Options "nosniff".');

    // 8) Referrer-Policy
    $ref = strtolower(trim((string)naglowek($ctx, 'referrer-policy')));
    if ($ref === '') {
        $mr = $xp->query('//meta[translate(@name,"REFERER","referer")="referrer"]/@content')->item(0);
        $ref = $mr ? strtolower(trim($mr->nodeValue)) : '';
    }
    if ($ref === '') {
        $poz[] = pozycja('referrer', 'Polityka przekazywania adresu (Referrer-Policy)', 'uwaga', 'brak',
            'Brak Referrer-Policy: przeglądarka sama zdecyduje, ile z adresu odwiedzanej strony zdradzić serwisom, do których prowadzą linki.',
            'Dodaj w .htaccess: Header always set Referrer-Policy "strict-origin-when-cross-origin".');
    } elseif (preg_match('/unsafe-url|no-referrer-when-downgrade|origin-when-cross-origin$/', $ref) && !str_contains($ref, 'strict-')) {
        $poz[] = pozycja('referrer', 'Polityka przekazywania adresu (Referrer-Policy)', 'uwaga', $ref,
            'Wartość „' . $ref . '” przekazuje obcym serwisom pełny adres odwiedzanej strony, także z parametrami.',
            'Zmień na: Header always set Referrer-Policy "strict-origin-when-cross-origin".');
    } else {
        $poz[] = pozycja('referrer', 'Polityka przekazywania adresu (Referrer-Policy)', 'ok', $ref,
            'Strona ogranicza, ile z adresu zdradza obcym serwisom (Referrer-Policy: ' . $ref . ').');
    }

    // 9) Permissions-Policy (info)
    $pp = naglowek($ctx, 'permissions-policy') ?? naglowek($ctx, 'feature-policy');
    $poz[] = pozycja('permissions', 'Zakaz użycia kamery, mikrofonu i lokalizacji (Permissions-Policy)', 'info', $pp ? 'jest' : 'brak',
        $pp ? 'Strona określa, z jakich funkcji przeglądarki (kamera, mikrofon, lokalizacja) wolno korzystać.' : 'Strona nie wyłącza funkcji przeglądarki, których nie potrzebuje (kamera, mikrofon, lokalizacja) — to zalecenie dodatkowe, bez wpływu na ocenę.',
        $pp ? '' : 'Opcjonalnie dodaj: Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()".');

    // 10) Mieszane treści
    if (!$https) {
        $poz[] = pozycja('mieszane-tresci', 'Mieszane treści (http na stronie https)', 'info', 'nie dotyczy',
            'Strona sama jest po http, więc pojęcie mieszanych treści nie ma zastosowania — pierwszy krok to HTTPS.');
    } else {
        $aktywne = [];
        $pasywne = [];
        $reguly = [
            ['//script[@src]', 'src', 'a'], ['//link[@href][contains(translate(@rel,"STYLESHET","stylesheet"),"stylesheet")]', 'href', 'a'],
            ['//iframe[@src]|//embed[@src]|//object[@data]', 'src', 'a'],
            ['//img[@src]|//source[@src]|//video[@src]|//audio[@src]', 'src', 'p'],
        ];
        foreach ($reguly as [$q, $atr, $rodzaj]) {
            foreach ($xp->query($q) ?: [] as $el) {
                $w = trim((string)$el->getAttribute($atr) ?: (string)$el->getAttribute('data'));
                if ($w === '' || !preg_match('#^http://#i', ep_zlacz($koncowy, $w))) { continue; }
                if ($rodzaj === 'a') { $aktywne[] = $w; } else { $pasywne[] = $w; }
            }
        }
        $przyklad = static fn(array $l): string => implode(', ', array_map(static fn($x) => mb_substr($x, 0, 60), array_slice(array_values(array_unique($l)), 0, 2)));
        if ($aktywne !== []) {
            $poz[] = pozycja('mieszane-tresci', 'Mieszane treści (http na stronie https)', 'blad', count($aktywne) . ' skryptów/arkuszy przez http',
                'Strona https ładuje ' . count($aktywne) . ' skryptów, arkuszy lub ramek przez nieszyfrowane http (np. ' . $przyklad($aktywne) . ') — przeglądarki je blokują, a strona może działać wadliwie i traci kłódkę.',
                'Zmień w kodzie strony adresy http:// na https:// (albo na adresy względne) dla każdego skryptu, arkusza i ramki; jeśli zasób nie ma https, wgraj go na własny serwer.');
        } elseif ($pasywne !== []) {
            $poz[] = pozycja('mieszane-tresci', 'Mieszane treści (http na stronie https)', 'uwaga', count($pasywne) . ' obrazów/plików przez http',
                'Strona https ładuje ' . count($pasywne) . ' obrazów lub plików multimedialnych przez http (np. ' . $przyklad($pasywne) . '); przeglądarki je podmieniają lub blokują, a kłódka bywa przekreślona.',
                'Zmień w kodzie strony adresy http:// obrazów i plików na https:// (w WordPressie: wtyczka „Better Search Replace”, zamiana http://' . $host . ' na https://' . $host . ').');
        } else {
            $poz[] = pozycja('mieszane-tresci', 'Mieszane treści (http na stronie https)', 'ok', 'brak',
                'W kodzie strony nie ma skryptów, arkuszy, ramek ani obrazów ładowanych przez nieszyfrowane http.' . ($ctx['obcieto'] ? ' Sprawdzono pierwsze 2 MB kodu.' : ''));
        }
    }

    // 11) Ujawniane wersje
    $ujawnione = [];
    $serwer = (string)naglowek($ctx, 'server');
    if ($serwer !== '' && preg_match('/\d+\.\d+/', $serwer)) { $ujawnione[] = 'Server: ' . $serwer; }
    $xpb = (string)naglowek($ctx, 'x-powered-by');
    if ($xpb !== '' && preg_match('/\d/', $xpb)) { $ujawnione[] = 'X-Powered-By: ' . $xpb; }
    $gen = $xp->query('//meta[translate(@name,"GENERATOR","generator")="generator"]/@content')->item(0);
    $genTxt = $gen ? trim((string)$gen->nodeValue) : '';
    if ($genTxt !== '' && preg_match('/\d+\.\d+|\bv\d+/i', $genTxt)) { $ujawnione[] = 'generator: ' . $genTxt; }
    if ($ujawnione !== []) {
        $poz[] = pozycja('wersje', 'Ujawniane wersje oprogramowania', 'uwaga', implode('; ', array_slice($ujawnione, 0, 3)),
            'Strona zdradza wersje używanego oprogramowania (' . implode('; ', array_slice($ujawnione, 0, 3)) . '), co ułatwia atakującym dobór znanych luk.',
            'Ukryj wersje: w .htaccess Header unset X-Powered-By, w php.ini expose_php = Off, ServerTokens Prod (Apache) lub server_tokens off (nginx); znacznik meta generator usuń z szablonu.');
    } else {
        $poz[] = pozycja('wersje', 'Ujawniane wersje oprogramowania', 'ok', 'nie ujawnia',
            'Nagłówki ani kod strony nie podają numerów wersji serwera, PHP ani systemu zarządzania treścią.');
    }

    // 12) Formularze z action po http
    $formularze = $xp->query('//form');
    $liczba = $formularze ? $formularze->length : 0;
    $zle = [];
    foreach ($formularze ?: [] as $f) {
        $a = trim((string)$f->getAttribute('action'));
        $abs = $a === '' ? $koncowy : ep_zlacz($koncowy, $a);
        if (stripos($abs, 'http://') === 0) { $zle[] = $a !== '' ? $a : $koncowy; }
    }
    if ($liczba === 0) {
        $poz[] = pozycja('formularze', 'Formularze wysyłane przez https', 'info', 'brak formularzy', 'Na stronie głównej nie ma formularzy do sprawdzenia.');
    } elseif ($zle !== []) {
        $poz[] = pozycja('formularze', 'Formularze wysyłane przez https', 'blad', count($zle) . ' z ' . $liczba . ' przez http',
            count($zle) . ' z ' . $liczba . ' formularzy wysyła dane przez nieszyfrowane http — wpisane imię, telefon czy hasło może przechwycić ktoś w tej samej sieci.',
            'Ustaw w atrybucie action każdego formularza adres z https:// (albo adres względny na stronie https) i włącz przekierowanie http → https.');
    } else {
        $poz[] = pozycja('formularze', 'Formularze wysyłane przez https', 'ok', $liczba . ' z szyfrowaniem',
            ($liczba === 1 ? 'Formularz wysyła' : 'Formularze (' . $liczba . ') wysyłają') . ' dane szyfrowanym połączeniem https.');
    }

    // 13) Ciasteczka z odpowiedzi
    $ciast = bz_ciasteczka(naglowek($ctx, 'set-cookie'));
    if ($ciast === []) {
        $poz[] = pozycja('ciasteczka', 'Zabezpieczenie ciasteczek', 'ok', 'brak ciasteczek',
            'Serwer nie ustawia ciasteczek przy wejściu na stronę, więc nie ma czego zabezpieczać.');
    } else {
        $bezSecure = [];
        $bezHttp = [];
        $sesyjne = false;
        foreach ($ciast as $ck) {
            $wrazliwe = (bool)preg_match('/sess|sid$|token|auth|login|jwt|csrf/i', $ck['nazwa']);
            if ($https && !$ck['secure']) { $bezSecure[] = $ck['nazwa']; if ($wrazliwe) { $sesyjne = true; } }
            if (!$ck['httponly']) { $bezHttp[] = $ck['nazwa']; if ($wrazliwe) { $sesyjne = true; } }
        }
        if ($bezSecure === [] && $bezHttp === []) {
            $poz[] = pozycja('ciasteczka', 'Zabezpieczenie ciasteczek', 'ok', count($ciast) . ' z Secure i HttpOnly',
                'Wszystkie ciasteczka ustawiane przy wejściu (' . count($ciast) . ') mają flagi Secure i HttpOnly.');
        } else {
            $braki = [];
            if ($bezSecure !== []) { $braki[] = 'bez Secure: ' . implode(', ', array_slice($bezSecure, 0, 3)); }
            if ($bezHttp !== []) { $braki[] = 'bez HttpOnly: ' . implode(', ', array_slice($bezHttp, 0, 3)); }
            $poz[] = pozycja('ciasteczka', 'Zabezpieczenie ciasteczek', $sesyjne ? 'blad' : 'uwaga', implode('; ', $braki),
                'Ciasteczka ustawiane przy wejściu nie mają wszystkich flag ochronnych (' . implode('; ', $braki) . '), więc skrypt na stronie lub połączenie bez szyfrowania mogą je odczytać.',
                'Dodaj flagi Secure i HttpOnly: w PHP session.cookie_secure=1 i session.cookie_httponly=1, w .htaccess Header edit Set-Cookie ^(.*)$ $1;HttpOnly;Secure.');
        }
    }

    // 14) security.txt (info)
    $st = pobierz_tekst(zrodlo_url($ctx, '/.well-known/security.txt'), 20000, 3);
    $jest = $st['ok'] && preg_match('/^\s*Contact\s*:/mi', $st['tekst']) === 1 && stripos($st['typ'], 'html') === false;
    $poz[] = pozycja('security-txt', 'Kontakt dla zgłaszających luki (security.txt)', 'info', $jest ? 'jest' : 'brak',
        $jest ? 'Strona publikuje plik security.txt z adresem kontaktowym dla osób, które znajdą w niej lukę.' : 'Strona nie publikuje pliku security.txt, więc osoba, która znajdzie lukę, nie wie, do kogo ją zgłosić — zalecenie dodatkowe, bez wpływu na ocenę.',
        $jest ? '' : 'Utwórz plik /.well-known/security.txt z wierszami: Contact: mailto:adres@twojadomena.pl oraz Expires: (data za rok).');

    $wagi = ['https' => 3, 'przekierowanie-https' => 2, 'certyfikat' => 3, 'hsts' => 2, 'csp' => 1, 'ramki' => 2, 'nosniff' => 1,
             'referrer' => 1, 'mieszane-tresci' => 3, 'wersje' => 1, 'formularze' => 2, 'ciasteczka' => 1];
    return ['ocena' => ocena_z_pozycji($poz, $wagi), 'pozycje' => $poz,
            'surowe' => ['hsts' => $hsts, 'csp' => $csp !== null ? 'jest' : null, 'cookies' => count($ciast), 'http_kod' => $r['kod']]];
}
