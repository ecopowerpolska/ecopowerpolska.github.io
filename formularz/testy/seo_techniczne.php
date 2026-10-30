<?php
declare(strict_types=1);
/**
 * Moduł audytu: SEO techniczne strony (kod odpowiedzi, przekierowania, title, description, H1, canonical, noindex,
 * robots.txt, sitemap, hreflang, Open Graph, JSON-LD, lang, linki, obrazy bez alt). Kontrakt: KONTRAKT.md.
 * Dodatkowe żądania: 2 × przekierowania (http→https, www↔bez www; ep_pobierz HEAD), robots.txt 1 × pobierz_tekst,
 * sitemapa do 3 + do 3 podmap (razem ≤ 8 pobrań tekstu).
 */
if (!defined('AUDYT_EP')) { http_response_code(404); exit; }

const SEOT_MALE = 'abcdefghijklmnopqrstuvwxyz';
const SEOT_DUZE = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

function seot_xp_maly(string $atrybut): string {
    return "translate({$atrybut},'" . SEOT_DUZE . "','" . SEOT_MALE . "')";
}

/** Ujednolicony klucz adresu do porównań: bez schematu, bez www, bez końcowego ukośnika, małe litery hosta. */
function seot_klucz(string $url): string {
    $p = parse_url($url);
    if (!is_array($p) || !isset($p['host'])) { return strtolower($url); }
    $host = preg_replace('/^www\./', '', strtolower((string)$p['host'])) ?? '';
    $sc = rtrim((string)($p['path'] ?? '/'), '/');
    return $host . $sc . (isset($p['query']) ? '?' . $p['query'] : '');
}
/** Jak wyżej, ale z zachowaniem schematu i hosta (www); tylko ukośnik końcowy ignorowany. */
function seot_dokladny(string $url): string {
    $p = parse_url($url);
    if (!is_array($p) || !isset($p['host'])) { return strtolower($url); }
    return strtolower((string)($p['scheme'] ?? 'https')) . '://' . strtolower((string)$p['host']) . rtrim((string)($p['path'] ?? '/'), '/')
        . (isset($p['query']) ? '?' . $p['query'] : '');
}
function seot_host(string $url): string {
    return strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
}
function seot_bez_www(string $host): string {
    return preg_replace('/^www\./', '', $host) ?? $host;
}

/** Lista wartości dyrektyw robots (po przecinku) → czy zawiera noindex/none (także z prefiksem bota: googlebot: noindex). */
function seot_noindex_w(string $wartosc): bool {
    foreach (explode(',', strtolower($wartosc)) as $t) {
        $t = trim($t);
        if (preg_match('/^(?:[a-z0-9\-]+\s*:\s*)?(noindex|none)$/', $t)) { return true; }
    }
    return false;
}

/** Parsuje robots.txt: ['sitemapy'=>[...], 'zasady'=>[['allow'|'disallow', wzorzec],...] dla grupy *]. */
function seot_robots(string $tekst, string $baza): array {
    $sitemapy = [];
    $grupy = [];
    $agenci = [];
    $zasady = [];
    $bylaRegula = false;
    $zamknij = static function () use (&$grupy, &$agenci, &$zasady): void {
        if ($agenci !== []) { $grupy[] = ['agenci' => $agenci, 'zasady' => $zasady]; }
        $agenci = [];
        $zasady = [];
    };
    foreach (preg_split('/\R/u', $tekst) ?: [] as $linia) {
        $linia = trim(preg_replace('/#.*$/', '', $linia) ?? '');
        if ($linia === '' || !str_contains($linia, ':')) { continue; }
        [$k, $w] = array_map('trim', explode(':', $linia, 2));
        $k = strtolower($k);
        if ($k === 'sitemap') {
            if ($w !== '') { $sitemapy[] = ep_zlacz($baza, $w); }
        } elseif ($k === 'user-agent') {
            if ($bylaRegula) { $zamknij(); $bylaRegula = false; }
            $agenci[] = strtolower($w);
        } elseif ($k === 'allow' || $k === 'disallow') {
            $zasady[] = [$k, $w];
            $bylaRegula = true;
        }
    }
    $zamknij();
    $wszystkie = [];
    foreach ($grupy as $g) {
        if (in_array('*', $g['agenci'], true)) { $wszystkie = array_merge($wszystkie, $g['zasady']); }
    }
    return ['sitemapy' => array_values(array_unique($sitemapy)), 'zasady' => $wszystkie];
}

/** Czy ścieżka jest zablokowana przez zasady grupy * (najdłuższe dopasowanie, przy remisie wygrywa Allow). */
function seot_zablokowana(array $zasady, string $sciezka): bool {
    $najdl = -1;
    $zabl = false;
    foreach ($zasady as [$typ, $wz]) {
        if ($wz === '') { continue; }
        $re = '#^' . str_replace(['\*', '\$'], ['.*', '$'], preg_quote($wz, '#')) . '#';
        if (@preg_match($re, $sciezka) === 1) {
            $dl = strlen($wz);
            if ($dl > $najdl || ($dl === $najdl && $typ === 'allow')) { $najdl = $dl; $zabl = $typ === 'disallow'; }
        }
    }
    return $zabl;
}

/** Wyciąga <loc> i <lastmod> z sitemapy. */
function seot_mapa(string $xml): array {
    $indeks = (bool)preg_match('/<sitemapindex\b/i', $xml);
    $urlset = (bool)preg_match('/<urlset\b/i', $xml);
    preg_match_all('#<loc>\s*(?:<!\[CDATA\[)?\s*(.*?)\s*(?:\]\]>)?\s*</loc>#is', $xml, $m);
    $loc = array_map(static fn($x) => html_entity_decode(trim($x), ENT_QUOTES | ENT_XML1, 'UTF-8'), $m[1] ?? []);
    preg_match_all('#<lastmod>\s*(.*?)\s*</lastmod>#is', $xml, $l);
    $max = 0;
    foreach ($l[1] ?? [] as $d) {
        $t = strtotime(trim($d));
        if ($t !== false && $t > $max) { $max = $t; }
    }
    return ['ok' => $indeks || $urlset, 'indeks' => $indeks, 'loc' => $loc, 'lastmod' => $max];
}

/** Zbiera nazwy @type z JSON-LD (także @graph i zagnieżdżone). */
function seot_typy(mixed $w, int $glebokosc = 0): array {
    $t = [];
    if (!is_array($w) || $glebokosc > 6) { return $t; }
    if (isset($w['@type'])) {
        foreach ((array)$w['@type'] as $x) { if (is_string($x) && $x !== '') { $t[] = $x; } }
    }
    foreach ($w as $k => $v) {
        if (is_array($v) && $k !== '@type') { $t = array_merge($t, seot_typy($v, $glebokosc + 1)); }
        if (count($t) > 60) { break; }
    }
    return $t;
}

function seot_gorszy(string $a, string $b): string {
    $rz = ['ok' => 0, 'info' => 0, 'uwaga' => 1, 'blad' => 2];
    return ($rz[$b] ?? 0) > ($rz[$a] ?? 0) ? $b : $a;
}

function seot_skoki(int $n): string {
    return $n === 0 ? 'bez przekierowania' : ($n === 1 ? '1 skok' : ($n < 5 ? $n . ' skoki' : $n . ' skoków'));
}

function test_seo_techniczne(string $url, array $ctx): array {
    $poz = [];
    $html = (string)$ctx['html'];
    $bazaUrl = (string)$ctx['url_koncowy'];
    $hostKoncowy = (string)$ctx['host'];
    [, $xp] = dom($html);

    // ── Kod odpowiedzi ──────────────────────────────────────────────────────────────────────────────────────────
    $kod = (int)$ctx['kod'];
    $poz[] = pozycja('kod', 'Kod odpowiedzi', $kod >= 200 && $kod < 300 ? 'ok' : ($kod >= 400 ? 'blad' : 'uwaga'), (string)$kod,
        $kod >= 200 && $kod < 300 ? 'Strona odpowiada kodem ' . $kod . ', czyli działa poprawnie.'
            : 'Strona odpowiada kodem ' . $kod . ($kod >= 400 ? ' — to błąd, wyszukiwarka nie zaindeksuje takiej strony.' : '.'),
        $kod >= 400 ? 'Sprawdź, dlaczego adres zwraca błąd (usunięta strona, błąd serwera, blokada), i przywróć odpowiedź 200.' : '');

    // ── Przekierowania: adres podany, http→https, www↔bez www ───────────────────────────────────────────────────
    $stan = 'ok';
    $czesci = [];
    $uwagi = [];
    $zal = '';
    $glowny = (array)$ctx['przekierowania'];
    $n = count($glowny);
    $czesci[] = 'podany adres: ' . seot_skoki($n);
    if ($n === 2) { $stan = seot_gorszy($stan, 'uwaga'); $uwagi[] = 'adres przechodzi przez 2 przekierowania — każdy skok spowalnia wejście'; }
    if ($n >= 3) { $stan = seot_gorszy($stan, 'blad'); $uwagi[] = 'adres przechodzi przez ' . $n . ' przekierowania pod rząd — roboty mogą się zniechęcić, a strona wczytuje się wolniej'; }
    $tymcz = array_filter($glowny, static fn($x) => in_array((int)$x['kod'], [302, 303, 307], true));
    $schemat = (string)(parse_url((string)$ctx['url'], PHP_URL_SCHEME) ?? 'https');
    $hostPodany = seot_host((string)$ctx['url']);
    $sciezkaPodana = (string)(parse_url((string)$ctx['url'], PHP_URL_PATH) ?? '/');
    $zapyt = parse_url((string)$ctx['url'], PHP_URL_QUERY);
    $cel = ($sciezkaPodana === '' ? '/' : $sciezkaPodana) . ($zapyt ? '?' . $zapyt : '');
    $wynikiDod = [];

    if ($schemat === 'https' && $hostPodany !== '') {
        $r = ep_pobierz('http://' . $hostPodany . $cel, ['metoda' => 'HEAD', 'timeout' => 3, 'przekierowania' => 3, 'limit' => 1024]);
        $wynikiDod['http'] = $r;
        if ($r['kod'] === 0 || $r['blad'] === 'siec' || $r['blad'] === 'adres_niedozwolony') {
            $czesci[] = 'http→https: brak odpowiedzi na porcie 80';
        } else {
            $kn = count($r['przekierowania']);
            $konczy = str_starts_with((string)$r['url_koncowy'], 'https://');
            $czesci[] = 'http→https: ' . ($konczy ? seot_skoki($kn) : 'brak przekierowania');
            if (!$konczy && $r['kod'] > 0 && $r['kod'] < 400) {
                $stan = seot_gorszy($stan, 'blad');
                $uwagi[] = 'wersja http:// działa bez przekierowania na https — wejście bez szyfrowania jest możliwe, a Google widzi dwie kopie strony';
                $zal = 'Dodaj przekierowanie 301 z http:// na https:// (zwykle w pliku .htaccess albo w panelu hostingu).';
            } elseif ($konczy && $kn === 2) { $stan = seot_gorszy($stan, 'uwaga'); $uwagi[] = 'z http:// do końcowego adresu prowadzą 2 skoki; da się je zastąpić jednym'; }
            elseif ($konczy && $kn >= 3) { $stan = seot_gorszy($stan, 'blad'); $uwagi[] = 'z http:// do końcowego adresu prowadzi ' . $kn . ' skoków'; }
            if (array_filter($r['przekierowania'], static fn($x) => in_array((int)$x['kod'], [302, 303, 307], true))) { $tymcz[] = 1; }
        }
    }

    $alt = str_starts_with($hostPodany, 'www.') ? substr($hostPodany, 4) : 'www.' . $hostPodany;
    $dwuczlon = substr_count(seot_bez_www($hostPodany), '.') >= 1;
    if ($dwuczlon && filter_var($hostPodany, FILTER_VALIDATE_IP) === false && $hostPodany !== '') {
        $r = ep_pobierz('https://' . $alt . $cel, ['metoda' => 'HEAD', 'timeout' => 3, 'przekierowania' => 3, 'limit' => 1024]);
        $wynikiDod['alt'] = $r;
        $opisAlt = str_starts_with($alt, 'www.') ? 'bez www→www' : 'www→bez www';
        if ($r['kod'] === 0) {
            $czesci[] = $opisAlt . ': druga wersja adresu nie odpowiada';
        } else {
            $kn = count($r['przekierowania']);
            $koncowyAlt = seot_host((string)$r['url_koncowy']);
            if ($r['kod'] >= 200 && $r['kod'] < 400 && $koncowyAlt === $alt) {
                $czesci[] = $opisAlt . ': osobna strona, bez przekierowania';
                $stan = seot_gorszy($stan, 'uwaga');
                $uwagi[] = 'adres ' . $alt . ' działa jako druga, osobna kopia strony — Google musi zgadywać, która jest główna';
                $zal = $zal !== '' ? $zal : 'Wybierz jedną wersję adresu (z www albo bez) i przekieruj drugą stałym przekierowaniem 301.';
            } elseif ($koncowyAlt === $hostKoncowy && $r['kod'] < 400) {
                $czesci[] = $opisAlt . ': ' . seot_skoki($kn);
                if ($kn >= 2) { $stan = seot_gorszy($stan, $kn >= 3 ? 'blad' : 'uwaga'); $uwagi[] = 'z ' . $alt . ' do głównego adresu prowadzi ' . $kn . ' skoki'; }
            } elseif ($r['kod'] >= 400) {
                $czesci[] = $opisAlt . ': druga wersja zwraca błąd ' . $r['kod'];
                $stan = seot_gorszy($stan, 'uwaga');
                $uwagi[] = 'adres ' . $alt . ' zwraca błąd ' . $r['kod'] . ' zamiast przekierowania na główny adres';
            } else {
                $czesci[] = $opisAlt . ': prowadzi na inny adres (' . $koncowyAlt . ')';
            }
            if (array_filter($r['przekierowania'], static fn($x) => in_array((int)$x['kod'], [302, 303, 307], true))) { $tymcz[] = 1; }
        }
    }
    if ($tymcz !== []) {
        $stan = seot_gorszy($stan, 'uwaga');
        $uwagi[] = 'część przekierowań jest tymczasowa (302/307) — dla stałego przeniesienia adresu użyj 301 lub 308';
        $zal = $zal !== '' ? $zal : 'Zamień przekierowania tymczasowe (302, 307) na stałe (301, 308), żeby wyszukiwarka przejęła nowy adres.';
    }
    if ($zal === '' && $stan !== 'ok') { $zal = 'Skróć łańcuch tak, żeby każda wersja adresu (http, https, z www i bez) prowadziła do głównego adresu jednym przekierowaniem 301.'; }
    $poz[] = pozycja('przekierowania', 'Przekierowania', $stan, implode('; ', $czesci),
        $uwagi === [] ? 'Każda wersja adresu prowadzi do jednego głównego adresu najwyżej jednym skokiem.' : 'Wykryto: ' . implode('; ', $uwagi) . '.', $zal);

    // ── noindex ────────────────────────────────────────────────────────────────────────────────────────────────
    $zrodlaNoindex = [];
    $meta = $xp->query("//meta[" . seot_xp_maly('@name') . "='robots' or " . seot_xp_maly('@name') . "='googlebot' or " . seot_xp_maly('@name') . "='bingbot']");
    foreach ($meta ?: [] as $m) {
        if ($m instanceof DOMElement && seot_noindex_w($m->getAttribute('content'))) {
            $zrodlaNoindex[] = 'znacznik meta ' . strtolower($m->getAttribute('name'));
        }
    }
    $xr = naglowek($ctx, 'x-robots-tag');
    if ($xr !== null && seot_noindex_w($xr)) { $zrodlaNoindex[] = 'nagłówek X-Robots-Tag'; }
    $poz[] = $zrodlaNoindex === []
        ? pozycja('noindex', 'Blokada indeksowania (noindex)', 'ok', 'brak', 'Strona nie prosi wyszukiwarek o pominięcie — może trafić do wyników Google.')
        : pozycja('noindex', 'Blokada indeksowania (noindex)', 'blad', 'jest: ' . implode(', ', array_unique($zrodlaNoindex)),
            'Strona ma dyrektywę noindex (' . implode(', ', array_unique($zrodlaNoindex)) . ') — Google nie pokaże jej w wynikach wyszukiwania.',
            'Jeśli strona ma być widoczna w Google, usuń noindex ze znacznika meta robots albo z nagłówka X-Robots-Tag (często to ustawienie „zniechęcaj wyszukiwarki” w CMS po budowie strony).');

    // ── title ──────────────────────────────────────────────────────────────────────────────────────────────────
    $t = $xp->query('//head/title')->item(0) ?? $xp->query('//title[not(ancestor::svg)]')->item(0);
    $tytul = $t ? trim((string)preg_replace('/\s+/u', ' ', $t->textContent)) : '';
    $dl = mb_strlen($tytul);
    if ($dl === 0) {
        $poz[] = pozycja('title', 'Tytuł strony (title)', 'blad', 'brak', 'Strona nie ma tytułu — Google sam wymyśli nagłówek w wynikach, zwykle gorszy.',
            'Dodaj znacznik title: usługa lub temat, miejscowość i nazwa firmy, razem do ok. 60 znaków.');
    } elseif ($dl > 70) {
        $poz[] = pozycja('title', 'Tytuł strony (title)', 'uwaga', $dl . ' znaków', 'Tytuł strony ma ' . $dl . ' znaków — Google go utnie w wynikach. Tytuł: ' . ep_skroc($tytul, 90),
            'Skróć tytuł do ok. 60 znaków, najważniejsze słowa daj na początek.');
    } elseif ($dl < 30) {
        $poz[] = pozycja('title', 'Tytuł strony (title)', 'uwaga', $dl . ' znaków', 'Tytuł strony ma tylko ' . $dl . ' znaków — za mało, żeby opisać stronę. Tytuł: ' . ep_skroc($tytul, 90),
            'Rozbuduj tytuł do 30–60 znaków: czego dotyczy strona, gdzie i kto ją prowadzi.');
    } else {
        $poz[] = pozycja('title', 'Tytuł strony (title)', 'ok', $dl . ' znaków', 'Tytuł ma ' . $dl . ' znaków' . ($dl > 60 ? ' (Google może lekko skrócić końcówkę)' : ', mieści się w wynikach Google') . '. Tytuł: ' . ep_skroc($tytul, 90));
    }

    // ── meta description ───────────────────────────────────────────────────────────────────────────────────────
    $md = $xp->query("//meta[" . seot_xp_maly('@name') . "='description']/@content")->item(0);
    $opisM = $md ? trim((string)preg_replace('/\s+/u', ' ', $md->nodeValue ?? '')) : '';
    $dd = mb_strlen($opisM);
    if ($dd === 0) {
        $poz[] = pozycja('description', 'Opis strony (meta description)', 'uwaga', 'brak', 'Strona nie ma opisu — Google pokaże przypadkowy fragment tekstu pod tytułem.',
            'Dodaj meta description: 1–2 zdania (70–155 znaków) o tym, co klient znajdzie na stronie, z zachętą do kliknięcia.');
    } elseif ($dd < 70) {
        $poz[] = pozycja('description', 'Opis strony (meta description)', 'uwaga', $dd . ' znaków', 'Opis strony ma tylko ' . $dd . ' znaków — marnujesz miejsce w wynikach wyszukiwania.',
            'Rozbuduj opis do 70–155 znaków: konkret, miejscowość, powód, żeby kliknąć.');
    } elseif ($dd > 155) {
        $poz[] = pozycja('description', 'Opis strony (meta description)', 'uwaga', $dd . ' znaków', 'Opis strony ma ' . $dd . ' znaków — Google utnie końcówkę.',
            'Skróć opis do ok. 155 znaków, najważniejsze zdanie daj na początek.');
    } else {
        $poz[] = pozycja('description', 'Opis strony (meta description)', 'ok', $dd . ' znaków', 'Opis ma ' . $dd . ' znaków i mieści się w wynikach Google.');
    }

    // ── H1 ─────────────────────────────────────────────────────────────────────────────────────────────────────
    $h1 = $xp->query('//h1');
    $nh1 = $h1 ? $h1->length : 0;
    if ($nh1 === 1) {
        $poz[] = pozycja('h1', 'Nagłówek główny (H1)', 'ok', '1', 'Strona ma dokładnie jeden nagłówek główny' . (($h1t = ep_skroc((string)preg_replace('/\s+/u', ' ', trim($h1->item(0)->textContent)), 80)) !== '' ? ': ' . $h1t : ' (bez tekstu — sprawdź, czy nie jest samym obrazkiem).'));
    } elseif ($nh1 === 0) {
        $poz[] = pozycja('h1', 'Nagłówek główny (H1)', 'blad', 'brak', 'Strona nie ma nagłówka głównego H1 — wyszukiwarka i czytniki ekranu nie wiedzą, o czym jest strona.',
            'Dodaj jeden nagłówek H1 z głównym tematem strony (np. usługa i miejscowość).');
    } else {
        $poz[] = pozycja('h1', 'Nagłówek główny (H1)', 'uwaga', (string)$nh1, 'Nagłówek H1 występuje na stronie ' . $nh1 . ' razy, a powinien raz.',
            'Zostaw jeden H1 z głównym tematem, pozostałe zamień na H2 lub H3.');
    }

    // ── canonical ──────────────────────────────────────────────────────────────────────────────────────────────
    $can = $xp->query("//link[contains(concat(' '," . seot_xp_maly('@rel') . ",' '),' canonical ')]/@href");
    $ncan = $can ? $can->length : 0;
    $canonSurowy = $ncan > 0 ? trim((string)$can->item(0)->nodeValue) : '';
    if ($ncan === 0 || $canonSurowy === '') {
        $poz[] = pozycja('canonical', 'Adres kanoniczny (canonical)', 'uwaga', 'brak', 'Strona nie wskazuje swojego głównego adresu — przy wersjach z parametrami lub bez ukośnika Google musi zgadywać.',
            'Dodaj w nagłówku strony znacznik link rel=canonical wskazujący jej pełny adres.');
    } else {
        $canon = ep_zlacz($bazaUrl, $canonSurowy);
        if ($ncan > 1 && count(array_unique(array_map(static fn($x) => ep_zlacz($bazaUrl, trim((string)$x->nodeValue)), iterator_to_array($can)))) > 1) {
            $poz[] = pozycja('canonical', 'Adres kanoniczny (canonical)', 'uwaga', $ncan . ' różne', 'Strona ma kilka różnych adresów kanonicznych — Google je zignoruje.',
                'Zostaw jeden znacznik canonical z adresem tej strony.');
        } elseif (seot_dokladny($canon) === seot_dokladny($bazaUrl)) {
            $poz[] = pozycja('canonical', 'Adres kanoniczny (canonical)', 'ok', 'zgodny', 'Strona wskazuje sama siebie jako główny adres: ' . ep_skroc($canon, 90));
        } elseif (seot_klucz($canon) === seot_klucz($bazaUrl)) {
            $poz[] = pozycja('canonical', 'Adres kanoniczny (canonical)', 'uwaga', 'inna postać', 'Canonical (' . ep_skroc($canon, 80) . ') różni się od adresu końcowego tylko schematem albo wersją z www.',
                'Ustaw canonical dokładnie na adres, na którym kończy się strona (ten sam protokół, host i ukośnik).');
        } else {
            $poz[] = pozycja('canonical', 'Adres kanoniczny (canonical)', 'uwaga', 'inny adres', 'Canonical wskazuje inny adres (' . ep_skroc($canon, 80) . ') niż ten, na którym kończy się strona — Google może pominąć tę stronę w wynikach.',
                'Jeśli ta strona ma być w Google, ustaw canonical na jej własny adres.');
        }
    }

    // ── robots.txt i sitemapa ──────────────────────────────────────────────────────────────────────────────────
    $robots = pobierz_tekst(zrodlo_url($ctx, '/robots.txt'), 200000, 4);
    $robotsJest = $robots['ok'] && !preg_match('/<\s*(html|body|head)\b/i', substr($robots['tekst'], 0, 2000));
    $sitemapyZRobots = [];
    $sciezkaStrony = (string)(parse_url($bazaUrl, PHP_URL_PATH) ?? '/');
    $zapStrony = parse_url($bazaUrl, PHP_URL_QUERY);
    $sciezkaDoRobots = ($sciezkaStrony === '' ? '/' : $sciezkaStrony) . ($zapStrony ? '?' . $zapStrony : '');
    if (!$robotsJest) {
        $poz[] = pozycja('robots', 'Plik robots.txt', 'uwaga', $robots['kod'] > 0 && $robots['kod'] !== 200 ? 'brak (kod ' . $robots['kod'] . ')' : 'brak',
            'Strona nie ma pliku robots.txt — roboty mogą wszystko, ale nie dowiedzą się, gdzie jest mapa strony.',
            'Dodaj plik robots.txt w głównym katalogu z linijką Sitemap: wskazującą mapę strony.');
    } else {
        $rb = seot_robots($robots['tekst'], $robots['url']);
        $sitemapyZRobots = $rb['sitemapy'];
        $zablokowana = seot_zablokowana($rb['zasady'], $sciezkaDoRobots);
        $calaBlokada = false;
        foreach ($rb['zasady'] as [$tp, $wz]) { if ($tp === 'disallow' && $wz === '/') { $calaBlokada = true; } }
        if ($zablokowana) {
            $poz[] = pozycja('robots', 'Plik robots.txt', 'blad', $calaBlokada ? 'Disallow: / dla wszystkich robotów' : 'ta strona zablokowana',
                $calaBlokada ? 'Plik robots.txt zabrania wszystkim robotom wstępu na całą stronę (Disallow: /) — Google jej nie przeszuka.'
                    : 'Plik robots.txt blokuje robotom wejście na ten adres.',
                'Usuń linijkę Disallow: / (albo regułę blokującą ten adres) z robots.txt. Często to pozostałość po budowie strony.');
        } elseif ($sitemapyZRobots === []) {
            $poz[] = pozycja('robots', 'Plik robots.txt', 'uwaga', 'jest, bez mapy strony', 'Plik robots.txt istnieje, ale nie wskazuje mapy strony (Sitemap:).',
                'Dopisz do robots.txt linijkę Sitemap: z pełnym adresem mapy strony.');
        } else {
            $poz[] = pozycja('robots', 'Plik robots.txt', 'ok', 'jest, wskazuje mapę strony', 'Plik robots.txt istnieje, nie blokuje strony i wskazuje mapę: ' . ep_skroc($sitemapyZRobots[0], 90));
        }
    }

    // sitemapa
    $kandydaci = array_slice($sitemapyZRobots, 0, 3);
    $zRobots = $kandydaci !== [];
    if (!$zRobots) { $kandydaci = [zrodlo_url($ctx, '/sitemap.xml'), zrodlo_url($ctx, '/sitemap_index.xml')]; }
    $mapa = null;
    $mapaUrl = '';
    foreach ($kandydaci as $kUrl) {
        if (ep_pozostalo() < 2.0) { break; }
        $p = pobierz_tekst($kUrl, 1_500_000, 5);
        if ($p['ok']) {
            $mm = seot_mapa($p['tekst']);
            if ($mm['ok']) { $mapa = $mm + ['obcieto' => $p['obcieto']]; $mapaUrl = $p['url']; break; }
        }
    }
    if ($mapa === null) {
        $poz[] = pozycja('sitemap', 'Mapa strony (sitemap)', 'uwaga', 'nie znaleziono',
            $zRobots ? 'Robots.txt wskazuje mapę strony, ale pod tym adresem nie ma poprawnego pliku XML.' : 'Nie znaleziono mapy strony (sprawdzono /sitemap.xml i /sitemap_index.xml) — wyszukiwarka dłużej odkrywa nowe podstrony.',
            'Wygeneruj mapę strony (sitemap.xml) i wpisz jej adres w robots.txt oraz w Google Search Console.');
    } else {
        $lokacje = $mapa['loc'];
        $lastmod = (int)$mapa['lastmod'];
        $zawiera = null;
        $kompletna = !$mapa['obcieto'];
        $liczba = count($lokacje);
        $ile = $liczba;
        $opisCzesci = '';
        $szukany = seot_dokladny($bazaUrl);
        $szukanyK = seot_klucz($bazaUrl);
        $sprawdz = static function (array $locs) use ($szukany, $szukanyK): ?string {
            $przybl = false;
            foreach ($locs as $l) {
                if (seot_dokladny($l) === $szukany) { return 'dokladnie'; }
                if (seot_klucz($l) === $szukanyK) { $przybl = true; }
            }
            return $przybl ? 'przyblizone' : null;
        };
        if ($mapa['indeks']) {
            $podmapy = $lokacje;
            $ile = 0;
            $zawiera = null;
            $pobrane = 0;
            foreach (array_slice($podmapy, 0, 3) as $pm) {
                if (ep_pozostalo() < 2.0 || preg_match('/\.gz(\?|$)/i', $pm)) { continue; }
                $p = pobierz_tekst($pm, 1_500_000, 5);
                if (!$p['ok']) { continue; }
                $m2 = seot_mapa($p['tekst']);
                if (!$m2['ok'] || $m2['indeks']) { continue; }
                $pobrane++;
                if ($p['obcieto']) { $kompletna = false; }
                $ile += count($m2['loc']);
                $lastmod = max($lastmod, (int)$m2['lastmod']);
                $s = $sprawdz($m2['loc']);
                if ($s === 'dokladnie' || ($s === 'przyblizone' && $zawiera === null)) { $zawiera = $s; }
            }
            $kompletna = $kompletna && $pobrane === count($podmapy);
            $opisCzesci = 'Indeks map zawiera ' . count($podmapy) . ' ' . (count($podmapy) === 1 ? 'mapę' : (count($podmapy) < 5 ? 'mapy' : 'map'))
                . ($ile > 0 ? ' (w sprawdzonych ' . ($pobrane === count($podmapy) ? '' : $pobrane . ' z nich ') . 'jest ' . $ile . ' adresów)' : '') . '.';
            $wartosc = count($podmapy) . ' ' . (count($podmapy) === 1 ? 'mapa' : (count($podmapy) < 5 ? 'mapy' : 'map')) . ' w indeksie';
        } else {
            $zawiera = $sprawdz($lokacje);
            $opisCzesci = 'Mapa zawiera ' . $liczba . ' adresów.';
            $wartosc = $liczba . ' adresów';
        }
        $dataTxt = $lastmod > 0 ? date('Y-m-d', $lastmod) : 'brak';
        if ($lastmod > 0) { $wartosc .= ', ostatnia zmiana ' . $dataTxt; }
        $opisCzesci .= $lastmod > 0 ? ' Najnowsza data zmiany (lastmod): ' . $dataTxt . '.' : ' Mapa nie podaje dat zmian (lastmod).';
        $stanMapy = 'ok';
        $zalMapy = '';
        if ($zawiera === 'dokladnie') {
            $opisCzesci .= ' Adres sprawdzanej strony jest w mapie.';
        } elseif ($zawiera === 'przyblizone') {
            $stanMapy = 'uwaga';
            $opisCzesci .= ' Strona jest w mapie, ale w innej postaci adresu (inny protokół, www albo ukośnik).';
            $zalMapy = 'Wpisz w mapie strony dokładnie ten adres, na którym kończy się strona (protokół, host, ukośnik).';
        } elseif ($kompletna) {
            $stanMapy = 'uwaga';
            $opisCzesci .= ' Adresu sprawdzanej strony w mapie nie ma.';
            $zalMapy = 'Dodaj tę stronę do mapy strony — albo sprawdź, czy mapa jest aktualna.';
        } else {
            $opisCzesci .= ' Nie sprawdziliśmy całej mapy, więc nie wiadomo, czy ta strona w niej jest.';
        }
        if ($ile > 50000) { $stanMapy = seot_gorszy($stanMapy, 'uwaga'); $opisCzesci .= ' Mapa przekracza limit 50 000 adresów.'; $zalMapy = $zalMapy !== '' ? $zalMapy : 'Podziel mapę na kilka plików połączonych indeksem (limit 50 000 adresów na plik).'; }
        $poz[] = pozycja('sitemap', 'Mapa strony (sitemap)', $stanMapy, $wartosc, $opisCzesci . ' Adres mapy: ' . ep_skroc($mapaUrl, 90), $zalMapy);
    }

    // ── hreflang ───────────────────────────────────────────────────────────────────────────────────────────────
    $hl = $xp->query("//link[contains(concat(' '," . seot_xp_maly('@rel') . ",' '),' alternate ') and @hreflang]/@hreflang");
    $kody = [];
    foreach ($hl ?: [] as $a) { $kody[] = strtolower(trim((string)$a->nodeValue)); }
    $kody = array_values(array_unique($kody));
    $poz[] = pozycja('hreflang', 'Wersje językowe (hreflang)', 'info', $kody === [] ? 'brak' : implode(', ', array_slice($kody, 0, 12)),
        $kody === [] ? 'Strona nie deklaruje wersji w innych językach — to normalne, jeśli jest tylko jedna wersja.'
            : 'Strona wskazuje ' . count($kody) . ' wersji językowych/regionalnych' . (in_array('x-default', $kody, true) ? ' i wersję domyślną.' : ' (brak wersji x-default).'));

    // ── Open Graph ─────────────────────────────────────────────────────────────────────────────────────────────
    $og = static function (string $nazwa) use ($xp): string {
        $n = $xp->query("//meta[(" . seot_xp_maly('@property') . "='" . $nazwa . "' or " . seot_xp_maly('@name') . "='" . $nazwa . "')]/@content")->item(0);
        return $n ? trim((string)$n->nodeValue) : '';
    };
    $ogT = $og('og:title');
    $ogI = $og('og:image');
    if ($ogT !== '' && $ogI !== '') {
        $poz[] = pozycja('og', 'Podgląd w komunikatorach (Open Graph)', 'ok', 'og:title i og:image', 'Po wklejeniu linku do Facebooka, Messengera czy LinkedIna pokaże się tytuł i obrazek.');
    } else {
        $brak = array_values(array_filter([$ogT === '' ? 'og:title' : '', $ogI === '' ? 'og:image' : '']));
        $poz[] = pozycja('og', 'Podgląd w komunikatorach (Open Graph)', 'uwaga', 'brak: ' . implode(', ', $brak),
            (count($brak) > 1 ? 'Brakuje znaczników ' : 'Brakuje znacznika ') . implode(' i ', $brak) . ' — po wklejeniu linku w komunikatorze podgląd będzie ubogi albo bez obrazka.',
            'Dodaj znaczniki meta property og:title i og:image (obrazek min. 1200 × 630 px).');
    }

    // ── JSON-LD ────────────────────────────────────────────────────────────────────────────────────────────────
    $typy = [];
    $zepsute = 0;
    $skrypty = $xp->query("//script[" . seot_xp_maly('@type') . "='application/ld+json']");
    foreach ($skrypty ?: [] as $s) {
        $d = json_decode(trim((string)$s->textContent), true);
        if (!is_array($d)) { $zepsute++; continue; }
        $typy = array_merge($typy, seot_typy($d));
    }
    $typy = array_values(array_unique($typy));
    $firma = array_values(array_filter($typy, static fn($x) => preg_match('/^(Organization|LocalBusiness|Corporation|NGO|OnlineStore|Store|OnlineBusiness)$|Business$|Store$/', $x) === 1));
    if ($typy === []) {
        $poz[] = pozycja('json-ld', 'Dane strukturalne (JSON-LD)', 'uwaga', $zepsute > 0 ? 'błąd składni' : 'brak',
            $zepsute > 0 ? 'Dane strukturalne są na stronie, ale mają błąd składni i Google ich nie odczyta.' : 'Strona nie ma danych strukturalnych — Google gorzej rozumie, czym jest firma i co oferuje.',
            'Dodaj dane JSON-LD typu Organization albo LocalBusiness (nazwa, adres, telefon, logo).');
    } elseif ($firma === []) {
        $poz[] = pozycja('json-ld', 'Dane strukturalne (JSON-LD)', 'uwaga', implode(', ', array_slice($typy, 0, 8)),
            'Strona ma dane strukturalne (' . implode(', ', array_slice($typy, 0, 8)) . '), ale żaden nie opisuje firmy.',
            'Dopisz dane typu Organization albo LocalBusiness — nazwa, adres, telefon, logo.');
    } else {
        $poz[] = pozycja('json-ld', 'Dane strukturalne (JSON-LD)', $zepsute > 0 ? 'uwaga' : 'ok', implode(', ', array_slice($typy, 0, 8)),
            'Strona opisuje firmę danymi strukturalnymi (' . implode(', ', array_slice($firma, 0, 3)) . ')' . ($zepsute > 0 ? ', ale ' . $zepsute . ' blok(ów) ma błąd składni.' : '.'),
            $zepsute > 0 ? 'Popraw składnię uszkodzonego bloku JSON-LD (np. w teście wyników rozszerzonych Google).' : '');
    }

    // ── lang ───────────────────────────────────────────────────────────────────────────────────────────────────
    $html0 = $xp->query('//html')->item(0);
    $lang = $html0 instanceof DOMElement ? trim($html0->getAttribute('lang')) : '';
    if ($lang === '') {
        $poz[] = pozycja('lang', 'Język strony (lang)', 'uwaga', 'brak', 'Znacznik html nie podaje języka — czytniki ekranu i wyszukiwarki muszą go zgadywać.', 'Dodaj do znacznika html atrybut lang, np. lang=pl.');
    } elseif (!preg_match('/^[a-zA-Z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $lang)) {
        $poz[] = pozycja('lang', 'Język strony (lang)', 'uwaga', ep_skroc($lang, 20), 'Wartość lang (' . ep_skroc($lang, 20) . ') nie wygląda na poprawny kod języka.', 'Użyj kodu języka, np. pl albo pl-PL.');
    } else {
        $poz[] = pozycja('lang', 'Język strony (lang)', 'ok', $lang, 'Strona deklaruje język: ' . $lang . '.');
    }

    // ── Linki ──────────────────────────────────────────────────────────────────────────────────────────────────
    $hostBazy = seot_bez_www(seot_host($bazaUrl));
    $wewn = [];
    $zewn = [];
    $nfW = 0;
    $nfZ = 0;
    $i = 0;
    foreach ($xp->query('//a[@href]') ?: [] as $a) {
        if (++$i > 5000 || !($a instanceof DOMElement)) { break; }
        $h = trim($a->getAttribute('href'));
        if ($h === '' || $h[0] === '#' || preg_match('#^(mailto|tel|javascript|sms|data):#i', $h)) { continue; }
        $abs = ep_zlacz($bazaUrl, $h);
        if (!preg_match('#^https?://#i', $abs)) { continue; }
        $nf = in_array('nofollow', preg_split('/\s+/', strtolower($a->getAttribute('rel'))) ?: [], true);
        if (seot_bez_www(seot_host($abs)) === $hostBazy) { $wewn[seot_klucz($abs)] = true; if ($nf) { $nfW++; } }
        else { $zewn[seot_klucz($abs)] = true; if ($nf) { $nfZ++; } }
    }
    $poz[] = $wewn === []
        ? pozycja('linki', 'Linki wewnętrzne', 'uwaga', '0', 'Na stronie nie ma linków do innych podstron — roboty nie mają dokąd przejść, a odwiedzający nie ma dokąd iść.', 'Dodaj linki do ważnych podstron: usług, cennika, kontaktu.')
        : pozycja('linki', 'Linki wewnętrzne', 'info', count($wewn) . ' wewnętrznych, ' . count($zewn) . ' zewnętrznych', 'Strona prowadzi do ' . count($wewn) . ' własnych podstron (różnych adresów) i ' . count($zewn) . ' adresów zewnętrznych.');
    $poz[] = pozycja('nofollow', 'Linki z nofollow', 'info', ($nfW + $nfZ) . ' (wewnętrzne ' . $nfW . ', zewnętrzne ' . $nfZ . ')',
        ($nfW + $nfZ) === 0 ? 'Żaden link nie ma rel=nofollow.'
            : 'Linki z rel=nofollow mówią wyszukiwarce, żeby nie przekazywała im wartości' . ($nfW > 0 ? '; wewnętrzne nofollow zwykle nie ma sensu' : '') . '.');

    // ── Obrazy bez alt ─────────────────────────────────────────────────────────────────────────────────────────
    $img = $xp->query('//img');
    $nImg = $img ? $img->length : 0;
    $bez = 0;
    foreach ($img ?: [] as $im) { if ($im instanceof DOMElement && !$im->hasAttribute('alt')) { $bez++; } }
    $poz[] = $bez === 0
        ? pozycja('alt', 'Obrazy bez opisu (alt)', 'ok', $nImg === 0 ? 'brak obrazów' : '0 z ' . $nImg, $nImg === 0 ? 'Na stronie nie wykryto obrazów w kodzie HTML.' : 'Wszystkie ' . $nImg . ' obrazów ma atrybut alt.')
        : pozycja('alt', 'Obrazy bez opisu (alt)', 'uwaga', $bez . ' z ' . $nImg, $bez . ' z ' . $nImg . ' obrazów nie ma atrybutu alt — Google Grafika i czytniki ekranu ich nie rozumieją. Szczegóły w narzędziu dostępności.',
            'Dodaj każdemu obrazowi krótki opis w atrybucie alt (dla dekoracji pusty alt=).');

    return [
        'ocena' => ocena_z_pozycji($poz, ['kod' => 3, 'noindex' => 3, 'title' => 3, 'h1' => 2, 'description' => 2, 'canonical' => 2, 'robots' => 2, 'sitemap' => 2, 'przekierowania' => 2, 'og' => 1, 'json-ld' => 1]),
        'pozycje' => $poz,
        'surowe' => [
            'title' => ep_skroc($tytul, 200),
            'description' => ep_skroc($opisM, 300),
            'przekierowania' => $glowny,
            'sitemap' => $mapaUrl,
            'jsonld_typy' => $typy,
            'obrazy' => $nImg,
        ],
    ];
}
