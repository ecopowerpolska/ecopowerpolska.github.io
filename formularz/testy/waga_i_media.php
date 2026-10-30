<?php
declare(strict_types=1);
/**
 * Waga i media: rozmiar HTML, liczba plików, obrazy (wymiary, lazy, formaty), czcionki zewnętrzne, wideo, wstawki,
 * 5 największych plików i szacowana łączna waga strony. Jedno pobranie HTML (wspólne) + do 12 zapytań HEAD.
 */
if (!defined('AUDYT_EP')) { http_response_code(404); exit; }

/** Bajty po polsku: „2,4 MB”, „310 kB”, „900 B”. */
function waga_fmt(int $b): string {
    if ($b >= 1_000_000) { return str_replace('.', ',', (string)round($b / 1_000_000, 1)) . ' MB'; }
    if ($b >= 1000) { return (string)round($b / 1000) . ' kB'; }
    return $b . ' B';
}

/** Rozszerzenie z adresu (bez zapytania); '' gdy brak. */
function waga_rozszerzenie(string $adres): string {
    $p = (string)parse_url($adres, PHP_URL_PATH);
    return strtolower(pathinfo($p, PATHINFO_EXTENSION));
}

/** Krótka nazwa pliku do wyświetlenia. */
function waga_nazwa(string $adres): string {
    $p = (string)parse_url($adres, PHP_URL_PATH);
    $n = basename($p);
    if ($n === '' || $n === '/') { $n = (string)parse_url($adres, PHP_URL_HOST); }
    return mb_strlen($n) > 40 ? mb_substr($n, 0, 37) . '…' : $n;
}

/** Klasa formatu: 'nowy' (WebP/AVIF), 'stary' (JPEG/PNG/GIF/BMP), 'wektor' (SVG), '' nieznany. */
function waga_klasa_formatu(string $ext, string $typ = ''): string {
    if (in_array($ext, ['webp', 'avif'], true) || in_array($typ, ['image/webp', 'image/avif'], true)) { return 'nowy'; }
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp'], true) || in_array($typ, ['image/jpeg', 'image/png', 'image/gif', 'image/bmp'], true)) { return 'stary'; }
    if ($ext === 'svg' || $typ === 'image/svg+xml') { return 'wektor'; }
    return '';
}

/** Pierwszy adres z wartości srcset. */
function waga_srcset_pierwszy(string $srcset): string {
    $x = trim(explode(',', $srcset)[0] ?? '');
    return trim(explode(' ', $x)[0] ?? '');
}

function test_waga_i_media(string $url, array $ctx): array {
    $poz = [];
    $baza = (string)($ctx['url_koncowy'] ?? $url);
    $html = (string)$ctx['html'];
    [, $xp] = dom($html);

    $b = $xp->query('//base[@href]')->item(0);
    if ($b instanceof DOMElement) { $baza = ep_zlacz($baza, $b->getAttribute('href')); }

    $abs = static fn(string $rel): string => ep_zlacz($baza, $rel);
    $mozna = static fn(string $a): bool => (bool)preg_match('#^https?://#i', $a);

    // ── HTML ──────────────────────────────────────────────────────────────────────────────────────────────────────
    $rozmiar = (int)($ctx['rozmiar'] ?? strlen($html));
    $cl = naglowek($ctx, 'content-length');
    $przesylka = ($cl !== null && ctype_digit($cl) && naglowek($ctx, 'content-encoding') !== null) ? (int)$cl : $rozmiar;
    $poz[] = pozycja('html', 'Rozmiar kodu strony (HTML)', $rozmiar > 300_000 ? 'blad' : ($rozmiar > 100_000 ? 'uwaga' : 'ok'),
        waga_fmt($rozmiar) . (!empty($ctx['obcieto']) ? ' (ucięto na 2 MB)' : ''),
        'Sam kod strony ma ' . waga_fmt($rozmiar) . ($przesylka < $rozmiar ? ', po kompresji około ' . waga_fmt($przesylka) : '') . '.',
        $rozmiar > 100_000 ? 'Odchudź kod strony: usuń wstawki, nadmiarowe znaczniki i zbędne dane wbudowane w HTML.' : '');

    // ── Zasoby z HTML ─────────────────────────────────────────────────────────────────────────────────────────────
    $nsnoscript = 'not(ancestor::noscript)';
    $skrypty = [];
    foreach ($xp->query("//script[@src and $nsnoscript]") as $s) {
        $skrypty[] = $abs(trim($s->getAttribute('src')));
    }
    $arkusze = [];
    foreach ($xp->query("//link[@href and $nsnoscript and contains(concat(' ', translate(normalize-space(@rel), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), ' '), ' stylesheet ')]") as $l) {
        $arkusze[] = $abs(trim($l->getAttribute('href')));
    }

    // Obrazy: src / data-src / pierwszy srcset; format z <picture><source type>, rozszerzenia lub typu zmierzonego.
    $obrazy = [];
    foreach ($xp->query("//img[$nsnoscript]") as $i => $img) {
        if (!$img instanceof DOMElement) { continue; }
        $src = trim($img->getAttribute('src'));
        $dsrc = trim($img->getAttribute('data-src'));
        $srcset = trim($img->getAttribute('srcset') ?: $img->getAttribute('data-srcset'));
        $zrodlo = $src !== '' && !str_starts_with($src, 'data:') ? $src : ($dsrc !== '' ? $dsrc : ($srcset !== '' ? waga_srcset_pierwszy($srcset) : $src));
        $dane = str_starts_with($zrodlo, 'data:');
        $adres = ($zrodlo !== '' && !$dane) ? $abs($zrodlo) : '';

        $wImg = $img->parentNode instanceof DOMElement && strtolower($img->parentNode->nodeName) === 'picture';
        $maSrcset = $srcset !== '' || $wImg;
        $klasa = '';
        if ($wImg) {
            foreach ($img->parentNode->childNodes as $dz) {
                if ($dz instanceof DOMElement && strtolower($dz->nodeName) === 'source') {
                    $maSrcset = true;
                    $k = waga_klasa_formatu(waga_rozszerzenie(waga_srcset_pierwszy($dz->getAttribute('srcset'))), strtolower(trim($dz->getAttribute('type'))));
                    if ($k === 'nowy') { $klasa = 'nowy'; break; }
                }
            }
        }
        if ($klasa === '') {
            $klasa = waga_klasa_formatu(waga_rozszerzenie($zrodlo), '');
            if ($klasa === '' && $srcset !== '') { $klasa = waga_klasa_formatu(waga_rozszerzenie(waga_srcset_pierwszy($srcset)), ''); }
            if ($dane) { $klasa = str_contains($zrodlo, 'image/svg') ? 'wektor' : (str_contains($zrodlo, 'image/webp') || str_contains($zrodlo, 'image/avif') ? 'nowy' : 'stary'); }
        }

        $styl = strtolower($img->getAttribute('style'));
        $wym = ($img->getAttribute('width') !== '' && $img->getAttribute('height') !== '')
            || str_contains($styl, 'aspect-ratio')
            || (str_contains($styl, 'width') && str_contains($styl, 'height'));
        $kl = strtolower($img->getAttribute('class'));
        $lazy = strtolower($img->getAttribute('loading')) === 'lazy' || $dsrc !== '' || $img->hasAttribute('data-srcset')
            || str_contains($kl, 'lazy');

        $obrazy[] = ['adres' => $adres, 'klasa' => $klasa, 'wym' => $wym, 'lazy' => $lazy, 'srcset' => $maSrcset, 'kolejnosc' => $i, 'dane' => $dane];
    }
    $liczbaObr = count($obrazy);

    // ── Pomiar HEAD (≤ 12): pierwsze 6 obrazów, 3 skrypty, 3 arkusze ───────────────────────────────────────────────
    $cele = [];
    $widziane = [];
    $dodaj = static function (string $a, string $rodzaj) use (&$cele, &$widziane, $mozna): void {
        if ($a === '' || !$mozna($a) || isset($widziane[$a])) { return; }
        $widziane[$a] = true;
        $cele[] = ['adres' => $a, 'rodzaj' => $rodzaj];
    };
    $ile = 0;
    foreach ($obrazy as $o) { if ($ile >= 6) { break; } $przed = count($cele); $dodaj($o['adres'], 'obraz'); $ile += count($cele) - $przed; }
    $ile = 0;
    foreach ($skrypty as $a) { if ($ile >= 3) { break; } $przed = count($cele); $dodaj($a, 'skrypt'); $ile += count($cele) - $przed; }
    $ile = 0;
    foreach ($arkusze as $a) { if ($ile >= 3) { break; } $przed = count($cele); $dodaj($a, 'arkusz'); $ile += count($cele) - $przed; }
    $cele = array_slice($cele, 0, 12);

    $pliki = [];       // zmierzone: adres, rodzaj, bajty, typ
    $niezmierzone = 0;
    foreach ($cele as $c) {
        if (ep_pozostalo() < 1.5) { $niezmierzone++; continue; }
        $h = head($c['adres'], 3);
        if ($h['blad'] !== null || $h['kod'] < 200 || $h['kod'] >= 400 || $h['dlugosc'] === null) { $niezmierzone++; continue; }
        $pliki[] = ['adres' => $c['adres'], 'rodzaj' => $c['rodzaj'], 'bajty' => $h['dlugosc'], 'typ' => $h['typ']];
    }
    // Dopasuj format do zmierzonego typu, gdy rozszerzenia brakowało.
    foreach ($obrazy as &$o) {
        if ($o['klasa'] === '' && $o['adres'] !== '') {
            foreach ($pliki as $p) { if ($p['adres'] === $o['adres']) { $o['klasa'] = waga_klasa_formatu('', $p['typ']); break; } }
        }
    }
    unset($o);

    // ── Łączna waga ───────────────────────────────────────────────────────────────────────────────────────────────
    $suma = $przesylka;
    foreach ($pliki as $p) { $suma += $p['bajty']; }
    $nPlikow = count($pliki) + 1;
    $wszystkich = 1 + count($skrypty) + count($arkusze) + $liczbaObr;
    $stanSumy = $suma > 3_000_000 ? 'blad' : ($suma >= 1_000_000 ? 'uwaga' : 'ok');
    $poz[] = pozycja('lacznie', 'Łączna waga strony', $stanSumy,
        'co najmniej ' . waga_fmt($suma) . ' (oszacowanie z ' . $nPlikow . ' plików)',
        'Kod strony i ' . count($pliki) . ' zmierzonych plików ważą razem ' . waga_fmt($suma) . '; na stronie jest ich w sumie około ' . $wszystkich . ', więc pełna waga jest zwykle większa.',
        $stanSumy === 'ok' ? '' : 'Zmniejsz zdjęcia (WebP, szerokość do 1600 px), usuń nieużywane skrypty i wtyczki — to najprostsza droga do strony poniżej 1 MB.');

    // ── Największy obraz i lista 5 największych plików ────────────────────────────────────────────────────────────
    $zmObr = array_values(array_filter($pliki, static fn($p) => $p['rodzaj'] === 'obraz'));
    usort($zmObr, static fn($a, $b) => $b['bajty'] <=> $a['bajty']);
    if ($zmObr) {
        $w = $zmObr[0];
        $kl = waga_klasa_formatu(waga_rozszerzenie($w['adres']), $w['typ']);
        $st = $w['bajty'] > 1_000_000 ? 'blad' : ($w['bajty'] > 300_000 ? 'uwaga' : 'ok');
        $zal = '';
        if ($st !== 'ok') {
            $zal = 'Największe zdjęcie waży ' . waga_fmt($w['bajty']) . ' — ' . ($kl === 'nowy' ? 'zmniejsz je do szerokości 1600 px i zwiększ kompresję' : 'zapisz je w WebP w szerokości 1600 px')
                . ', zwykle zejdzie do 150–300 kB (plik: ' . waga_nazwa($w['adres']) . ').';
        }
        $poz[] = pozycja('najwiekszy-obraz', 'Największy obraz', $st, waga_fmt($w['bajty']),
            'Największe zmierzone zdjęcie (' . waga_nazwa($w['adres']) . ') waży ' . waga_fmt($w['bajty']) . ' (zmierzono ' . count($zmObr) . ' z ' . $liczbaObr . ' obrazów).', $zal);
    } else {
        $poz[] = pozycja('najwiekszy-obraz', 'Największy obraz', 'info', $liczbaObr === 0 ? 'brak obrazów' : 'nie zmierzono',
            $liczbaObr === 0 ? 'Na stronie nie znaleziono znaczników obrazów.' : 'Serwer obrazów nie podał ich rozmiaru, więc nie da się ocenić wagi zdjęć.');
    }

    $top = $pliki;
    usort($top, static fn($a, $b) => $b['bajty'] <=> $a['bajty']);
    $top = array_slice($top, 0, 5);
    $wiersze = [];
    foreach ($top as $p) { $wiersze[] = waga_nazwa($p['adres']) . ' ' . waga_fmt($p['bajty']); }
    $poz[] = pozycja('najwieksze-pliki', '5 największych plików', 'info',
        $top ? count($top) . ' z ' . count($pliki) . ' zmierzonych' : 'brak danych',
        $top ? implode('; ', $wiersze) . '.' : 'Nie udało się zmierzyć żadnego pliku (serwer nie podał rozmiarów).');

    // ── Formaty obrazów ───────────────────────────────────────────────────────────────────────────────────────────
    $nowe = $stare = 0;
    foreach ($obrazy as $o) { if ($o['klasa'] === 'nowy') { $nowe++; } elseif ($o['klasa'] === 'stary') { $stare++; } }
    $raster = $nowe + $stare;
    if ($raster === 0) {
        $poz[] = pozycja('formaty', 'Formaty obrazów', $liczbaObr === 0 ? 'info' : 'ok', $liczbaObr === 0 ? 'brak obrazów' : 'tylko grafika wektorowa',
            $liczbaObr === 0 ? 'Na stronie nie znaleziono obrazów.' : 'Obrazy to grafika wektorowa (SVG) albo format nierozpoznany po adresie.');
    } else {
        $udzial = (int)round(100 * $nowe / $raster);
        $st = $udzial >= 70 ? 'ok' : ($udzial >= 30 || $raster < 3 ? 'uwaga' : 'blad');
        $poz[] = pozycja('formaty', 'Formaty obrazów', $st, $udzial . '% w WebP/AVIF',
            $nowe . ' z ' . $raster . ' obrazów rastrowych ma nowoczesny format (WebP lub AVIF), ' . $stare . ' to JPEG/PNG/GIF.',
            $st === 'ok' ? '' : 'Zapisz zdjęcia w WebP (albo AVIF) — przy tej samej jakości są o 25–35% lżejsze niż JPEG.');
    }

    // ── Wymiary obrazów (CLS) ─────────────────────────────────────────────────────────────────────────────────────
    $bezWym = 0;
    foreach ($obrazy as $o) { if (!$o['wym'] && !$o['dane']) { $bezWym++; } }
    $stW = $bezWym === 0 ? 'ok' : (($liczbaObr > 0 && $bezWym / $liczbaObr > 0.25) ? 'blad' : 'uwaga');
    $poz[] = pozycja('wymiary', 'Obrazy z podanymi wymiarami', $stW, $bezWym === 0 ? 'wszystkie' : $bezWym . ' bez wymiarów',
        $bezWym === 0 ? 'Każdy obraz ma szerokość i wysokość, więc strona nie „skacze” podczas ładowania.' : $bezWym . ' z ' . $liczbaObr . ' obrazów nie ma atrybutów width i height — strona przeskakuje, gdy zdjęcia się doładują.',
        $bezWym === 0 ? '' : 'Dodaj do każdego obrazu atrybuty width i height (albo styl aspect-ratio), żeby przeglądarka zarezerwowała miejsce.');

    // ── Leniwe ładowanie (poza pierwszymi 2) ──────────────────────────────────────────────────────────────────────
    $kandydaci = 0; $bezLazy = 0; $poz2 = 0;
    foreach ($obrazy as $o) {
        $poz2++;
        if ($poz2 <= 2 || $o['dane']) { continue; }
        $kandydaci++;
        if (!$o['lazy']) { $bezLazy++; }
    }
    $stL = $bezLazy === 0 ? 'ok' : (($bezLazy > 3 && $bezLazy / max(1, $kandydaci) > 0.5) ? 'blad' : 'uwaga');
    $poz[] = pozycja('lazy', 'Leniwe ładowanie obrazów', $stL, $bezLazy === 0 ? 'jest' : $bezLazy . ' bez loading="lazy"',
        $bezLazy === 0 ? 'Obrazy poniżej pierwszego ekranu ładują się dopiero przy przewijaniu.' : $bezLazy . ' z ' . $kandydaci . ' obrazów poza pierwszymi dwoma ładuje się od razu, choć użytkownik jeszcze ich nie widzi.',
        $bezLazy === 0 ? '' : 'Dodaj loading="lazy" do obrazów poniżej pierwszego ekranu (pierwsze dwa zostaw bez).');

    // ── srcset ────────────────────────────────────────────────────────────────────────────────────────────────────
    $zSrcset = count(array_filter($obrazy, static fn($o) => $o['srcset']));
    if ($raster >= 3 && $zSrcset === 0) {
        $poz[] = pozycja('srcset', 'Obrazy w kilku rozmiarach (srcset)', 'uwaga', 'nie używa',
            'Żaden obraz nie ma srcset ani <picture>, więc telefon pobiera to samo duże zdjęcie co komputer.',
            'Użyj srcset (kilka szerokości tego samego zdjęcia), żeby telefon dostawał mniejszy plik.');
    } else {
        $poz[] = pozycja('srcset', 'Obrazy w kilku rozmiarach (srcset)', $liczbaObr === 0 ? 'info' : 'ok',
            $liczbaObr === 0 ? 'brak obrazów' : $zSrcset . ' z ' . $liczbaObr,
            $liczbaObr === 0 ? 'Na stronie nie znaleziono obrazów.' : $zSrcset . ' z ' . $liczbaObr . ' obrazów ma srcset lub <picture>.');
    }

    // ── Skrypty i arkusze ─────────────────────────────────────────────────────────────────────────────────────────
    $ns = count($skrypty);
    $poz[] = pozycja('skrypty', 'Liczba skryptów', $ns > 20 ? 'blad' : ($ns > 10 ? 'uwaga' : 'ok'), (string)$ns,
        'Strona wczytuje ' . $ns . ' zewnętrznych plików skryptów.',
        $ns > 10 ? 'Usuń nieużywane wtyczki i skrypty, a pozostałe połącz w mniej plików i dodaj atrybut defer.' : '');
    $na = count($arkusze);
    $poz[] = pozycja('arkusze', 'Liczba arkuszy stylów', $na > 10 ? 'blad' : ($na > 5 ? 'uwaga' : 'ok'), (string)$na,
        'Strona wczytuje ' . $na . ' osobnych arkuszy stylów.',
        $na > 5 ? 'Połącz arkusze stylów w jeden i usuń style nieużywane na tej stronie.' : '');
    $poz[] = pozycja('obrazy', 'Liczba obrazów', 'info', (string)$liczbaObr, 'Na stronie jest ' . $liczbaObr . ' znaczników obrazów.');

    // ── Czcionki zewnętrzne ───────────────────────────────────────────────────────────────────────────────────────
    $hosty = [];
    $szukaj = static function (string $tekst) use (&$hosty): void {
        if (preg_match_all('~(?:https?:)?//(fonts\.googleapis\.com|fonts\.gstatic\.com|use\.typekit\.(?:net|com)|p\.typekit\.net|kit\.fontawesome\.com|use\.fontawesome\.com|fast\.fonts\.net|fonts\.bunny\.net)~i', $tekst, $m)) {
            foreach ($m[1] as $h) { $hosty[strtolower($h)] = true; }
        }
    };
    foreach ($xp->query('//link[@href]|//script[@src]') as $n) {
        $szukaj($n->getAttribute('href') . ' ' . $n->getAttribute('src'));
    }
    $inlineStyle = 0;
    foreach ($xp->query('//style') as $st) { $t = (string)$st->textContent; $inlineStyle += strlen($t); $szukaj($t); }
    if ($hosty) {
        $poz[] = pozycja('czcionki', 'Czcionki z zewnętrznych serwisów', 'uwaga', implode(', ', array_keys($hosty)),
            'Strona pobiera czcionki od zewnętrznego dostawcy, co dokłada kolejne połączenie i przekazuje adres IP odwiedzającego dalej.',
            'Pobierz czcionki na swój serwer (format WOFF2) i wczytuj je stamtąd — jest szybciej i prościej pod względem RODO.');
    } else {
        $poz[] = pozycja('czcionki', 'Czcionki z zewnętrznych serwisów', 'ok', 'brak', 'Nie wykryto czcionek ładowanych z Google Fonts, Typekit ani podobnych serwisów.');
    }

    // ── Wideo i iframe ────────────────────────────────────────────────────────────────────────────────────────────
    $yt = 0; $inneIframe = 0; $wideo = 0;
    foreach ($xp->query("//iframe[$nsnoscript]") as $f) {
        $s = strtolower(trim($f->getAttribute('src') ?: $f->getAttribute('data-src')));
        if (preg_match('~(youtube\.com|youtube-nocookie\.com|youtu\.be)/~', $s) && trim($f->getAttribute('src')) !== '') { $yt++; } else { $inneIframe++; }
    }
    foreach ($xp->query("//video[$nsnoscript]") as $v) { $wideo++; }
    $fasady = (int)$xp->query('//*[starts-with(local-name(), "lite-") or contains(@class, "lite-youtube")]')->length;
    $razem = $yt + $inneIframe + $wideo;
    if ($yt > 0) {
        $poz[] = pozycja('wideo', 'Wideo i osadzone ramki', 'uwaga', $yt . ' YouTube bez fasady' . ($inneIframe + $wideo ? ', ' . ($inneIframe + $wideo) . ' innych' : ''),
            'Odtwarzacz YouTube wczytuje się od razu przy otwarciu strony i dokłada kilkaset kilobajtów skryptów.',
            'Zamiast osadzonego odtwarzacza pokaż miniaturę z przyciskiem odtwarzania (fasada) — film wczyta się dopiero po kliknięciu.');
    } else {
        $poz[] = pozycja('wideo', 'Wideo i osadzone ramki', 'ok', $razem === 0 ? 'brak' : $razem . ($fasady ? ' (z fasadą)' : ''),
            $razem === 0 ? 'Na stronie nie ma osadzonych filmów ani ramek.' : 'Na stronie jest ' . $razem . ' osadzonych elementów (wideo lub ramka), bez odtwarzacza YouTube ładowanego od razu.');
    }

    // ── Wstawki inline ────────────────────────────────────────────────────────────────────────────────────────────
    $inlineSkrypt = 0;
    foreach ($xp->query('//script[not(@src)]') as $s) {
        $typ = strtolower($s->getAttribute('type'));
        if (str_contains($typ, 'json')) { continue; }
        $inlineSkrypt += strlen((string)$s->textContent);
    }
    $wstawki = $inlineStyle + $inlineSkrypt;
    $poz[] = pozycja('wstawki', 'Style i skrypty wpisane w HTML', $wstawki > 150_000 ? 'blad' : ($wstawki > 50_000 ? 'uwaga' : 'ok'), waga_fmt($wstawki),
        'Wstawione bezpośrednio w kod strony style ważą ' . waga_fmt($inlineStyle) . ', a skrypty ' . waga_fmt($inlineSkrypt) . ' (bez danych strukturalnych).',
        $wstawki > 50_000 ? 'Wynieś duże style i skrypty do osobnych plików — przeglądarka zapamięta je i nie pobierze ich przy kolejnej stronie.' : '');

    // ── Preload / preconnect (informacja) ─────────────────────────────────────────────────────────────────────────
    $rel = static fn(string $r): int => (int)$xp->query("//link[contains(concat(' ', translate(normalize-space(@rel), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), ' '), ' $r ')]")->length;
    $pre = $rel('preload'); $con = $rel('preconnect'); $dns = $rel('dns-prefetch');
    $poz[] = pozycja('preload', 'Podpowiedzi dla przeglądarki', 'info', $pre + $con + $dns === 0 ? 'brak' : "preload $pre, preconnect $con",
        'Strona ma ' . $pre . ' znaczników preload, ' . $con . ' preconnect i ' . $dns . ' dns-prefetch; to dobre narzędzia, jeśli wskazują najważniejsze pliki.');

    return [
        'ocena' => ocena_z_pozycji($poz, ['lacznie' => 3, 'najwiekszy-obraz' => 3, 'formaty' => 2, 'wymiary' => 2]),
        'pozycje' => $poz,
        'surowe' => [
            'html_bajty' => $rozmiar, 'html_przesylka' => $przesylka, 'suma_bajty' => $suma, 'zmierzone' => count($pliki), 'niezmierzone' => $niezmierzone,
            'skrypty' => $ns, 'arkusze' => $na, 'obrazy' => $liczbaObr,
            'pliki' => array_map(static fn($p) => ['plik' => waga_nazwa($p['adres']), 'rodzaj' => $p['rodzaj'], 'bajty' => $p['bajty']], $pliki),
        ],
    ];
}
