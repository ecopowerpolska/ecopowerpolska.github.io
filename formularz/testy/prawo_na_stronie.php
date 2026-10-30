<?php
declare(strict_types=1);
/**
 * Narzędzie „Prawo na stronie": kontrola automatyczna, czy strona ma elementy, których wymaga polskie prawo (polityka prywatności,
 * zgoda na cookies, regulamin, dane firmy), i czy skrypty śledzące nie ładują się przed zgodą. To NIE jest opinia prawna.
 * Podstawy: PKE art. 399 (Dz.U. 2024 poz. 1221), UŚUDE art. 5 i 8 (Dz.U. 2024 poz. 1513), RODO art. 13, KSH art. 206 (Dz.U. 2024 poz. 18).
 */
if (!defined('AUDYT_EP')) { http_response_code(404); exit; }

const ISAP = 'isap.sejm.gov.pl/isap.nsf/DocDetails.xsp?id=';

/** Tekst widoczny strony: bez script/style/noscript/template, spacje ściśnięte. */
function pns_tekst(string $html): string {
    $h = (string)preg_replace('~<(script|style|noscript|template|svg)\b[^>]*>.*?</\1>~is', ' ', $html);
    $h = (string)preg_replace('~<!--.*?-->~s', ' ', $h);
    $t = html_entity_decode(strip_tags(str_replace('>', '> ', $h)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string)preg_replace('/\s+/u', ' ', $t));
}

/** Suma kontrolna NIP (wagi 6 5 7 2 3 4 5 6 7). */
function pns_nip_ok(string $nip): bool {
    $c = (string)preg_replace('/\D/', '', $nip);
    if (strlen($c) !== 10) { return false; }
    $w = [6, 5, 7, 2, 3, 4, 5, 6, 7];
    $s = 0;
    for ($i = 0; $i < 9; $i++) { $s += (int)$c[$i] * $w[$i]; }
    return $s % 11 === (int)$c[9];
}

/** Adres do pokazania: host + ścieżka bez schematu. */
function pns_skrot(string $u): string {
    return (string)parse_url($u, PHP_URL_HOST) . rtrim((string)parse_url($u, PHP_URL_PATH), '/');
}

/** Wszystkie linki strony: [['href'=>bezwzględny, 'surowy'=>href, 'tekst'=>…, 'klucz'=>mała litera tekst+href+title]]. */
function pns_linki(DOMXPath $xp, string $baza): array {
    $out = [];
    foreach ($xp->query('//a[@href]') as $a) {
        /** @var DOMElement $a */
        $h = trim($a->getAttribute('href'));
        if ($h === '' || preg_match('~^(javascript:|mailto:|tel:|data:)~i', $h)) { continue; }
        $tekst = trim((string)preg_replace('/\s+/u', ' ', $a->textContent));
        $klucz = mb_strtolower($tekst . ' ' . $h . ' ' . $a->getAttribute('title') . ' ' . $a->getAttribute('aria-label'));
        $out[] = ['href' => $h[0] === '#' ? '' : ep_zlacz($baza, $h), 'surowy' => $h, 'tekst' => $tekst, 'klucz' => $klucz];
    }
    return $out;
}

/** Pierwszy link pasujący do wzorca; preferuje taki, który ma adres do pobrania (nie samo #kotwica). */
function pns_znajdz(array $linki, string $wzor, string $wyklucz = ''): ?array {
    $pierwszy = null;
    foreach ($linki as $l) {
        if (!preg_match($wzor, $l['klucz'])) { continue; }
        if ($wyklucz !== '' && preg_match($wyklucz, $l['klucz'])) { continue; }
        if ($l['href'] !== '') { return $l; }
        $pierwszy ??= $l;
    }
    return $pierwszy;
}

function test_prawo_na_stronie(string $url, array $ctx): array {
    $html = (string)$ctx['html'];
    $baza = (string)$ctx['url_koncowy'];
    [, $xp] = dom($html);
    $htmlM = mb_strtolower($html);
    $tekst = pns_tekst($html);
    $tekstM = mb_strtolower($tekst);
    $linki = pns_linki($xp, $baza);
    $poz = [];

    // ── Sklep? (wpływa na wagę i stan regulaminu) ──
    $gen = '';
    foreach ($xp->query('//meta[translate(@name,"GENERATOR","generator")="generator"]/@content') as $n) { $gen .= ' ' . $n->nodeValue; }
    $gen = trim($gen);
    // Silnik sklepu poznajemy po generatorze i adresach zasobów (nie po słowie w treści — usługodawca może o sklepach pisać).
    $zasoby = $gen;
    foreach ($xp->query('//script/@src | //link/@href | //img/@src | //body/@class') as $n) { $zasoby .= ' ' . $n->nodeValue; }
    $zasoby = mb_strtolower($zasoby);
    $sklep = '';
    foreach (['woocommerce' => 'WooCommerce', 'prestashop' => 'PrestaShop', '/modules/ps_' => 'PrestaShop', 'shoper' => 'Shoper', 'shopify' => 'Shopify',
              'magento' => 'Magento', 'opencart' => 'OpenCart', 'idosell' => 'IdoSell', 'sky-shop' => 'Sky-Shop', 'sellingo' => 'Sellingo'] as $k => $nazwa) {
        if (str_contains($zasoby, $k)) { $sklep = $nazwa; break; }
    }
    if ($sklep === '' && preg_match('~dodaj do koszyka|add to cart|przejd[źz] do kasy|twój koszyk~u', $tekstM)) { $sklep = 'sklep (koszyk na stronie)'; }
    $jestSklep = $sklep !== '';
    $poz[] = pozycja('sklep', 'Czy to sklep internetowy', 'info', $jestSklep ? 'tak: ' . $sklep : 'nie wykryto',
        $jestSklep
            ? 'Wykryto sklep (' . $sklep . '). Sklep zawiera umowy na odległość, więc wymaga regulaminu i informacji dla konsumentów (UŚUDE art. 8, ' . ISAP . 'WDU20240001513; prawa konsumenta, ' . ISAP . 'WDU20260001244).'
            : 'Nie widać koszyka ani znanego silnika sklepu. Strona-wizytówka bez umów przez internet ma lżejsze wymagania.',
        $jestSklep ? 'Sprawdź, czy sklep ma regulamin, zasady zwrotów (14 dni) i oznaczony przycisk zamówienia z obowiązkiem zapłaty (prawa konsumenta art. 17).' : '');

    // ── Polityka prywatności ──
    $lPol = pns_znajdz($linki, '~polityk\w*\s+(prywatno|cookie|ciastecz|danych)|prywatno|privacy|\brodo\b|gdpr|ochron\w+\s+danych|klauzula\s+informacyjna~u');
    $polAdres = $lPol['href'] ?? '';
    $pol = $polAdres !== '' ? pobierz_tekst($polAdres) : null;
    $polTekst = $pol !== null && $pol['ok'] ? pns_tekst($pol['tekst']) : '';
    $polDuza = $pol !== null && $pol['ok'] && strlen($pol['tekst']) > 1000;
    if ($lPol === null) {
        $poz[] = pozycja('polityka', 'Polityka prywatności', 'blad', 'brak linku',
            'Na stronie głównej nie widać linku do polityki prywatności. Przy zbieraniu danych (formularz, analityka) trzeba poinformować, kto je przetwarza i po co (RODO art. 13, eur-lex.europa.eu/eli/reg/2016/679/oj/pol).',
            'Dodaj w stopce link „Polityka prywatności" i opisz w niej administratora, cele, podstawy, okres przechowywania i prawa osoby.');
    } elseif ($polAdres === '') {
        $poz[] = pozycja('polityka', 'Polityka prywatności', 'uwaga', 'link bez adresu',
            'Link do polityki jest tylko kotwicą w obrębie strony, więc nie dało się sprawdzić, czy dokument istnieje (RODO art. 13).',
            'Wystaw politykę prywatności pod własnym adresem i podlinkuj ją w stopce.');
    } elseif (!$polDuza) {
        $poz[] = pozycja('polityka', 'Polityka prywatności', 'blad', $pol['ok'] ? 'za krótka' : 'kod ' . ($pol['kod'] ?: 'brak'),
            'Link do polityki prywatności prowadzi donikąd albo do prawie pustej strony (' . ($pol['ok'] ? strlen($pol['tekst']) . ' B' : 'kod ' . ($pol['kod'] ?: 'brak odpowiedzi')) . ') (RODO art. 13).',
            'Napraw adres i wstaw pełną treść polityki prywatności.');
    } else {
        $poz[] = pozycja('polityka', 'Polityka prywatności', 'ok', 'jest: ' . pns_skrot($polAdres),
            'Strona ma link do polityki prywatności, a podstrona odpowiada i ma treść (RODO art. 13).');
        // Treść: 6 elementów informacji z art. 13 RODO (wyszukiwanie słów, nie ocena jakości).
        $m = mb_strtolower($polTekst);
        $el = [
            'administrator' => preg_match('~administrator|współadministrator~u', $m),
            'cele i podstawa' => preg_match('~(cel\w*|podstaw\w*)\s.{0,60}(przetwarzani|prawn)|art\.\s*6~u', $m),
            'odbiorcy' => preg_match('~odbiorc|podmiot\w*\s+przetwarzając|powierz~u', $m),
            'okres' => preg_match('~okres\w*\s.{0,40}(przechowywan|przetwarzani)|przez\s+okres|przechowujemy~u', $m),
            'prawa osoby' => preg_match('~prawo\s+do\s+(dostępu|usunięcia|sprostowania|ograniczenia|przenoszenia|sprzeciwu)|prawa\s+osoby|usunięcia\s+danych~u', $m),
            'skarga do UODO' => preg_match('~uodo|prezes\w*\s+urzędu\s+ochrony\s+danych|organ\w*\s+nadzorcz|skarg\w*\s+do~u', $m),
        ];
        $maxEl = count($el);
        $ile = count(array_filter($el));
        $brak = array_keys(array_filter($el, static fn($v) => !$v));
        $poz[] = pozycja('polityka-tresc', 'Czy polityka zawiera wymagane informacje', $ile >= 5 ? 'ok' : ($ile >= 3 ? 'uwaga' : 'blad'),
            $ile . ' z ' . $maxEl,
            $ile === $maxEl
                ? 'W treści polityki znaleziono wszystkie sześć grup informacji z art. 13 RODO: administrator, cele i podstawa, odbiorcy, okres, prawa osoby, skarga do organu (RODO art. 13; kontrola słów, nie ocena jakości).'
                : 'W treści polityki nie znaleziono: ' . implode(', ', $brak) . ' (RODO art. 13, eur-lex.europa.eu/eli/reg/2016/679/oj/pol; kontrola słów, nie ocena jakości).',
            $ile >= 5 ? '' : 'Uzupełnij politykę o: ' . implode(', ', $brak) . '.');
    }

    // ── Regulamin ──
    $lReg = pns_znajdz($linki, '~regulamin|\bterms\b|warunki\s+(korzystania|sprzeda|us[łl]ug|zakup)|terms\s+of\s+(use|service)~u');
    $regAdres = $lReg['href'] ?? '';
    $reg = $regAdres !== '' && $regAdres !== $polAdres ? pobierz_tekst($regAdres) : null;
    $regOk = $lReg !== null && ($regAdres === '' || ($reg !== null && $reg['ok'] && strlen($reg['tekst']) > 1000));
    $idReg = $jestSklep ? 'regulamin-sklep' : 'regulamin';
    if ($regOk) {
        $poz[] = pozycja($idReg, 'Regulamin', 'ok', $regAdres !== '' ? 'jest: ' . pns_skrot($regAdres) : 'link w stopce',
            'Strona ma link do regulaminu' . ($regAdres !== '' ? ', a podstrona odpowiada i ma treść' : '') . ' (UŚUDE art. 8, ' . ISAP . 'WDU20240001513).');
    } elseif ($lReg !== null) {
        $poz[] = pozycja($idReg, 'Regulamin', 'blad', $reg !== null ? 'kod ' . ($reg['kod'] ?: 'brak') : 'niedostępny',
            'Link do regulaminu prowadzi donikąd albo do prawie pustej strony (UŚUDE art. 8, ' . ISAP . 'WDU20240001513).',
            'Napraw adres i wstaw pełną treść regulaminu.');
    } elseif ($jestSklep) {
        $poz[] = pozycja($idReg, 'Regulamin sklepu', 'blad', 'brak linku',
            'Sklep nie ma widocznego regulaminu. Usługodawca udostępnia regulamin usług świadczonych drogą elektroniczną (UŚUDE art. 8, ' . ISAP . 'WDU20240001513), a konsument ma prawo do informacji o zwrotach (' . ISAP . 'WDU20260001244).',
            'Dodaj w stopce link „Regulamin" i opisz zamawianie, płatność, dostawę, odstąpienie od umowy (14 dni) i reklamacje.');
    } else {
        $poz[] = pozycja($idReg, 'Regulamin', 'uwaga', 'brak linku',
            'Nie widać regulaminu. Dla wizytówki bez umów przez internet zwykle nie jest wymagany, ale formularz kontaktowy lub kalkulator bywa usługą elektroniczną, której regulamin trzeba udostępnić (UŚUDE art. 8, ' . ISAP . 'WDU20240001513).',
            'Jeśli strona ma formularz, koszyk lub konto, dodaj krótki regulamin i link do niego w stopce.');
    }

    // ── Cookies: informacja / baner zgody ──
    $cmp = '';
    foreach ([
        'Cookiebot' => 'cookiebot', 'CookieYes' => 'cookieyes', 'Complianz' => 'complianz|cmplz', 'Osano' => 'osano|cookieconsent\.min',
        'Borlabs Cookie' => 'borlabs', 'OneTrust' => 'onetrust|cookielaw\.org', 'Klaro' => 'klaro', 'tarteaucitron' => 'tarteaucitron',
        'Real Cookie Banner' => 'real-cookie-banner|devowl', 'Usercentrics' => 'usercentrics', 'Iubenda' => 'iubenda',
        'Cookie Notice' => 'cookie-notice', 'CookieScript' => 'cookie-script', 'Cookie Law Info' => 'cookie-law-info|cli-',
        'CookieFirst' => 'cookiefirst', 'Termly' => 'termly', 'Didomi' => 'didomi', 'Quantcast' => 'quantcast',
    ] as $nazwa => $wz) {
        if (preg_match('~' . $wz . '~i', $html)) { $cmp = $nazwa; break; }
    }
    $wlasny = (int)$xp->query('//*[@id or @class][contains(translate(concat(@id," ",@class),"COKIEONSTZGD","cokieonstzgd"),"cookie") or contains(translate(concat(@id," ",@class),"CONSENT","consent"),"consent") or contains(translate(concat(@id," ",@class),"CIASTECZ","ciastecz"),"ciastecz") or contains(translate(concat(@id," ",@class),"ZGOD","zgod"),"zgod")]')->length;
    $lCook = pns_znajdz($linki, '~cookie|ciastecz|pliki\s+cookies~u');
    $tekstCook = (bool)preg_match('~(używamy|korzystamy|wykorzystujemy|strona\s+używa|ta\s+strona)\s.{0,40}(plik\w*\s+cookie|ciasteczek|cookies)|pliki\s+cookie|zgoda\s+na\s+(pliki\s+)?cookie|akceptuj\w*\s+(wszystkie|cookie)|odrzuć\s+(wszystkie|cookie)~u', $tekstM);

    // ── Skrypty śledzące (przed zgodą) ──
    $sledzace = [
        'Google Tag Manager' => 'googletagmanager\.com/gtm\.js|googletagmanager\.com/gtag/js\?id=GTM',
        'Google Analytics 4' => 'googletagmanager\.com/gtag/js\?id=G-|google-analytics\.com/(analytics|ga)\.js|gtag\([\'"]config[\'"],\s*[\'"]G-|[\'"]UA-\d{4,}',
        'Meta Pixel (Facebook)' => 'connect\.facebook\.net/[^"\']*/(fbevents|sdk)\.js|fbq\(\s*[\'"]init',
        'Hotjar' => 'static\.hotjar\.com|hj\(\s*[\'"]init|_hjSettings',
        'TikTok Pixel' => 'analytics\.tiktok\.com|ttq\.load',
        'Microsoft Clarity' => 'clarity\.ms',
        'LinkedIn Insight' => 'snap\.licdn\.com|_linkedin_partner_id',
        'Google Ads / DoubleClick' => 'googleadservices\.com|doubleclick\.net|googlesyndication\.com',
    ];
    $przed = [];   // nazwa => true: ładowane bez blokady
    $warunk = [];  // nazwa => true: obecne, ale z blokadą zgody lub ładowane warunkowo
    foreach ($xp->query('//script') as $s) {
        /** @var DOMElement $s */
        $src = $s->getAttribute('src');
        $kod = $s->textContent;
        $material = $src . ' ' . $kod;
        if (mb_strlen($material) > 60000) { $material = mb_substr($material, 0, 60000); }
        $typ = strtolower(trim($s->getAttribute('type')));
        $blokada = ($typ !== '' && !preg_match('~^(text|application)/(javascript|ecmascript)$|^module$~', $typ))
            || $s->hasAttribute('data-cookieconsent') || $s->hasAttribute('data-category') || $s->hasAttribute('data-consent')
            || $s->hasAttribute('data-cmp-src') || $s->hasAttribute('data-cmplz-src') || $s->hasAttribute('data-src-cmplz')
            || $s->hasAttribute('data-cookie-consent') || $s->hasAttribute('data-usercentrics') || $s->hasAttribute('data-borlabs-script-blocker')
            || (bool)preg_match('~cmplz-script|cmplz-blocked|optanon-category|cc-script~', $s->getAttribute('class'));
        // Inline, który dopiero po zgodzie dokłada skrypt (createElement + słowa o zgodzie) albo ustawia Consent Mode na „denied".
        $warunkowy = $src === '' && (
            (bool)preg_match('~[\'"]consent[\'"]\s*,\s*[\'"]default[\'"]~i', $kod)
            || ((bool)preg_match('~createElement|appendChild|insertBefore~', $kod) && (bool)preg_match('~zgod|consent|localStorage|cookie~i', $kod)));
        foreach ($sledzace as $nazwa => $wz) {
            if (!preg_match('~' . $wz . '~i', $material)) { continue; }
            if ($blokada || $warunkowy) { $warunk[$nazwa] = true; } else { $przed[$nazwa] = true; }
        }
    }
    foreach ($przed as $n => $_) { unset($warunk[$n]); }
    $nPrzed = array_keys($przed);
    $nWarunk = array_keys($warunk);

    $dowodCookies = $cmp !== '' ? 'narzędzie zgody ' . $cmp : ($wlasny > 0 || $tekstCook ? 'własny baner lub komunikat' : ($lCook !== null ? 'link do polityki cookies' : ''));
    if ($dowodCookies !== '') {
        $poz[] = pozycja('cookies', 'Informacja o cookies i zgoda', 'ok', $dowodCookies,
            'Wykryto ' . $dowodCookies . '. Pliki inne niż niezbędne wolno zapisać po informacji o celu i po zgodzie użytkownika (PKE art. 399, ' . ISAP . 'WDU20240001221). Kontrola nie sprawdza, czy baner naprawdę działa i ma przycisk „Odrzuć".',
            '');
    } elseif ($nPrzed || $nWarunk) {
        $poz[] = pozycja('cookies', 'Informacja o cookies i zgoda', 'blad', 'brak banera',
            'Brak informacji o cookies, a na stronie są skrypty analityczne lub reklamowe — wymagana zgoda przed zapisaniem plików innych niż niezbędne (PKE art. 399, ' . ISAP . 'WDU20240001221). Kary do 3% przychodu nakłada UKE.',
            'Dodaj baner zgody (z równorzędnymi przyciskami „Akceptuj" i „Odrzuć") i uruchamiaj analitykę dopiero po zgodzie.');
    } else {
        $poz[] = pozycja('cookies', 'Informacja o cookies i zgoda', 'uwaga', 'nie wykryto',
            'Nie wykryto banera ani informacji o cookies; nie wykryto też skryptów śledzących. Jeśli strona zapisuje wyłącznie pliki niezbędne do działania, zgoda nie jest potrzebna (PKE art. 399 ust. 3, ' . ISAP . 'WDU20240001221), ale warto o tym poinformować w polityce.',
            'Dopisz w polityce prywatności, jakich plików używasz, albo dodaj baner, gdy włączysz analitykę.');
    }

    $setCookie = (string)(naglowek($ctx, 'set-cookie') ?? '');
    if ($nPrzed) {
        $poz[] = pozycja('skrypty-przed-zgoda', 'Skrypty śledzące przed zgodą', $dowodCookies === '' ? 'blad' : 'uwaga', implode(', ', $nPrzed),
            'W kodzie strony główne skrypty (' . implode(', ', $nPrzed) . ') ładują się bez widocznej blokady do czasu zgody (PKE art. 399, ' . ISAP . 'WDU20240001221).'
            . ($dowodCookies === '' ? '' : ' Jeśli narzędzie zgody wstrzymuje je dopiero w przeglądarce, kontrola może tego nie widzieć.'),
            'Przenieś te skrypty za zgodę: dodaj im type="text/plain" i atrybut narzędzia zgody albo włącz Consent Mode ze stanem „denied" do chwili akceptacji.');
    } else {
        $poz[] = pozycja('skrypty-przed-zgoda', 'Skrypty śledzące przed zgodą', 'ok',
            $nWarunk ? 'wstrzymane: ' . implode(', ', $nWarunk) : 'nie wykryto',
            $nWarunk
                ? 'Skrypty (' . implode(', ', $nWarunk) . ') są oznaczone jako wstrzymane do zgody albo ładowane warunkowo (PKE art. 399, ' . ISAP . 'WDU20240001221).'
                : 'W kodzie strony głównej nie wykryto GTM, GA4, Meta Pixela, Hotjara, TikToka ani innych znanych skryptów śledzących.');
    }
    if ($setCookie !== '') {
        preg_match_all('~(?:^|, )([A-Za-z0-9_.\-]+)=~', $setCookie, $mm);
        $nazwy = array_values(array_unique($mm[1] ?? []));
        $podejrz = array_values(array_filter($nazwy, static fn($c) => (bool)preg_match('~^(_ga|_gid|_fbp|_gcl|_hj|_ttp|_clck|IDE|test_cookie)~', $c)));
        if ($podejrz) {
            $poz[] = pozycja('cookies-serwer', 'Pliki cookies z nagłówka odpowiedzi', 'uwaga', implode(', ', $podejrz),
                'Serwer od razu ustawia pliki, które wyglądają na analityczne lub reklamowe (' . implode(', ', $podejrz) . '), czyli przed jakąkolwiek zgodą (PKE art. 399, ' . ISAP . 'WDU20240001221).',
                'Wyłącz ustawianie tych plików po stronie serwera do czasu zgody.');
        }
    }

    // ── Dane rejestrowe firmy ──
    $nip = '';
    $nipOk = false;
    if (preg_match('~\bNIP\b[:\s.\-]*(?:PL\s*)?(\d{3}[- ]?\d{3}[- ]?\d{2}[- ]?\d{2}|\d{10})\b~iu', $tekst, $m)) {
        $nip = (string)preg_replace('/\D/', '', $m[1]);
        $nipOk = pns_nip_ok($nip);
    }
    $krs = preg_match('~\bKRS\b[:\s.\-nr]*(\d{10})\b~iu', $tekst, $m) ? $m[1] : '';
    $regon = preg_match('~\bREGON\b[:\s.\-]*(\d{14}|\d{9})\b~iu', $tekst, $m) ? $m[1] : '';
    $sad = (bool)preg_match('~sąd\s+(rejonowy|okręgowy)|sądu\s+rejonowego|sąd\s+rejestrowy|Krajowy\s+Rejestr\s+Sądowy~iu', $tekst);
    $kapital = (bool)preg_match('~kapita[łl]\w*\s+zak[łl]adow\w*~iu', $tekst);
    $spolka = (bool)preg_match('~sp\.\s*z\s*o\.\s*o\.|spółk\w+\s+z\s+ograniczoną|\bS\.\s?A\.|spółk\w+\s+akcyjn|prosta\s+spółka\s+akcyjna|\bP\.S\.A\.~iu', $tekst);
    $spolkaOsob = !$spolka && (bool)preg_match('~\bsp\.\s*(k|j|p|komandyt)\.|spółk\w+\s+(jawna|komandytow|partnersk)~iu', $tekst);

    if ($nip !== '' || $regon !== '') {
        $w = trim(($nip !== '' ? 'NIP ' . $nip . ($nipOk ? '' : ' (suma kontrolna się nie zgadza)') : '') . ($regon !== '' ? ' REGON ' . $regon : ''));
        $poz[] = pozycja('dane-firmy', 'Dane firmy (NIP, REGON)', $nip !== '' && !$nipOk ? 'uwaga' : 'ok', $w,
            $nip !== '' && !$nipOk
                ? 'Na stronie jest numer NIP, ale nie przechodzi kontroli sumy — prawdopodobnie literówka (UŚUDE art. 5, ' . ISAP . 'WDU20240001513).'
                : 'Strona podaje dane identyfikacyjne firmy, jak wymaga tego informacja o usługodawcy (UŚUDE art. 5, ' . ISAP . 'WDU20240001513; Prawo przedsiębiorców art. 20 ust. 3, ' . ISAP . 'WDU20250001480).',
            $nip !== '' && !$nipOk ? 'Sprawdź numer NIP w rejestrze i popraw go.' : '');
    } else {
        $poz[] = pozycja('dane-firmy', 'Dane firmy (NIP, REGON)', $jestSklep ? 'blad' : 'uwaga', 'brak na stronie głównej',
            'Na stronie głównej nie znaleziono NIP ani REGON. Usługodawca podaje m.in. nazwę, adres i NIP (UŚUDE art. 5, ' . ISAP . 'WDU20240001513); przy ofercie na odległość także firmę i siedzibę (Prawo przedsiębiorców art. 20 ust. 3, ' . ISAP . 'WDU20250001480). Dane mogą być na podstronie „Kontakt" — jej nie sprawdzamy.',
            'Podaj w stopce pełną nazwę firmy, adres, NIP (i REGON) albo link do podstrony z tymi danymi.');
    }

    if ($spolka) {
        $brakS = [];
        if ($krs === '') { $brakS[] = 'numer KRS'; }
        if (!$sad) { $brakS[] = 'sąd rejestrowy'; }
        if (!$kapital) { $brakS[] = 'kapitał zakładowy'; }
        $poz[] = pozycja('dane-spolki', 'Dane spółki (KRS, sąd, kapitał)', $brakS ? ($krs === '' ? 'blad' : 'uwaga') : 'ok',
            $brakS ? 'brakuje: ' . implode(', ', $brakS) : 'KRS ' . $krs,
            $brakS
                ? 'Strona podaje formę spółki, ale nie widać: ' . implode(', ', $brakS) . '. Spółka kapitałowa podaje na stronie firmę, siedzibę, adres, sąd rejestrowy, KRS, NIP i wysokość kapitału zakładowego (KSH art. 206 § 1, ' . ISAP . 'WDU20240000018).'
                : 'Strona podaje KRS, sąd rejestrowy i kapitał zakładowy (KSH art. 206 § 1, ' . ISAP . 'WDU20240000018).',
            $brakS ? 'Dopisz do stopki: ' . implode(', ', $brakS) . '.' : '');
    } else {
        $poz[] = pozycja('dane-spolki', 'Dane spółki (KRS, sąd, kapitał)', 'info',
            $spolkaOsob ? 'spółka osobowa' : 'nie wykryto formy spółki',
            $spolkaOsob
                ? 'Wykryto spółkę osobową; wymóg kapitału zakładowego i sądu rejestrowego z KSH art. 206 dotyczy spółek z o.o., a odpowiednio S.A. (art. 374 § 1).'
                : 'Nie wykryto „sp. z o.o." ani „S.A." — wymóg KRS, sądu i kapitału zakładowego (KSH art. 206 § 1) dotyczy spółek kapitałowych.');
    }

    // ── Kontakt ──
    $mail = preg_match('~[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}~', $tekst, $m) ? $m[0] : '';
    if ($mail === '' && $xp->query('//a[starts-with(translate(@href,"MAILTO","mailto"),"mailto:")]')->length > 0) { $mail = 'mailto'; }
    $tel = $xp->query('//a[starts-with(translate(@href,"TEL","tel"),"tel:")]')->length > 0
        || (bool)preg_match('~(?:\+48[ \-]?)?\b\d{3}[ \-]\d{3}[ \-]\d{3}\b~', $tekst);
    $poz[] = pozycja('kontakt', 'Adres e-mail i telefon', $mail !== '' || $tel ? 'ok' : 'uwaga',
        trim(($mail !== '' ? 'e-mail' : '') . ($mail !== '' && $tel ? ' + ' : '') . ($tel ? 'telefon' : '')) ?: 'brak',
        $mail !== '' || $tel
            ? 'Na stronie jest ' . ($mail !== '' ? 'adres e-mail' : '') . ($mail !== '' && $tel ? ' i ' : '') . ($tel ? 'numer telefonu' : '') . ' do szybkiego kontaktu (UŚUDE art. 5, ' . ISAP . 'WDU20240001513).'
            : 'Na stronie głównej nie widać e-maila ani telefonu; usługodawca podaje dane do szybkiego kontaktu (UŚUDE art. 5, ' . ISAP . 'WDU20240001513).',
        $mail !== '' || $tel ? '' : 'Dodaj adres e-mail i telefon w stopce albo na podstronie „Kontakt".');

    // ── Deklaracja dostępności (informacja) ──
    $lDost = pns_znajdz($linki, '~deklaracj\w*\s+dostępno|deklaracja-dostepno|accessibility\s+statement|dostępność\s+cyfrowa~u');
    $poz[] = pozycja('deklaracja-dostepnosci', 'Deklaracja dostępności', 'info', $lDost ? 'jest link' : 'nie znaleziono',
        $lDost
            ? 'Strona ma link do deklaracji dostępności. Dla stron podmiotów publicznych deklaracja jest obowiązkowa.'
            : 'Nie ma linku do deklaracji dostępności. Dla stron podmiotów publicznych jest obowiązkowa; sklepy internetowe muszą spełniać wymogi dostępności, a mikroprzedsiębiorcy są z nich wyłączeni (ustawa o dostępności produktów i usług, art. 3 ust. 2 pkt 6 i art. 4 pkt 1, ' . ISAP . 'WDU20240000731).');

    // ── Prawa autorskie w stopce (informacja) ──
    $copy = (bool)preg_match('~©|\(c\)\s*\d{4}|copyright|wszelkie\s+prawa\s+zastrzeżone|all\s+rights\s+reserved~iu', $tekst);
    $poz[] = pozycja('prawa-autorskie', 'Zastrzeżenie praw autorskich', 'info', $copy ? 'jest' : 'nie znaleziono',
        $copy
            ? 'Stopka zawiera zastrzeżenie praw autorskich. To dobry zwyczaj, choć prawa autorskie powstają z mocy prawa (Prawo autorskie art. 1, ' . ISAP . 'WDU20250000024).'
            : 'W stopce nie ma znaku © ani zdania „prawa zastrzeżone". Prawa autorskie powstają z mocy prawa (art. 1), ale zastrzeżenie ułatwia dochodzenie roszczeń (' . ISAP . 'WDU20250000024).');

    $wagi = ['polityka' => 3, 'polityka-tresc' => 2, 'cookies' => 3, 'cookies-serwer' => 1, 'skrypty-przed-zgoda' => 3, 'dane-firmy' => 2, 'dane-spolki' => 2,
             'regulamin' => 2, 'regulamin-sklep' => 3, 'kontakt' => 1];
    return [
        'ocena' => ocena_z_pozycji($poz, $wagi),
        'pozycje' => $poz,
        'surowe' => [
            'sklep' => $sklep, 'cmp' => $cmp, 'elementy_cookie_w_html' => $wlasny, 'sledzace_bez_blokady' => $nPrzed, 'sledzace_wstrzymane' => $nWarunk,
            'polityka_adres' => $polAdres, 'regulamin_adres' => $regAdres, 'nip' => $nip, 'krs' => $krs, 'regon' => $regon, 'spolka' => $spolka,
        ],
    ];
}
