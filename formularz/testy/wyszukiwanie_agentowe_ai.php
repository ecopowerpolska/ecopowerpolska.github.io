<?php
declare(strict_types=1);
/**
 * Wyszukiwanie agentowe i AI, część serwerowa: llms.txt, katalog zasobów (ai-catalog.json / ard.json),
 * robots.txt dla botów AI, Content-Signal, dane strukturalne JSON-LD, meta noai. Część PSI (kategoria agentic-browsing)
 * liczy przeglądarka (tryb „oba”), identyfikatory PSI w JSON narzędzia: kat:agentic-browsing, agent-accessibility-tree,
 * cumulative-layout-shift, llms-txt, ard-schema. Identyfikatory serwerowe są inne (llms-plik, ard-plik), żeby nie zderzyły się z PSI.
 */
if (!defined('AUDYT_EP')) { http_response_code(404); exit; }

/** Boty AI sprawdzane w robots.txt: nazwa agenta => opis po polsku. */
const WA_BOTY = [
    'GPTBot' => 'OpenAI (ChatGPT)',
    'ClaudeBot' => 'Anthropic (Claude)',
    'Claude-Web' => 'Anthropic (Claude, starszy)',
    'Google-Extended' => 'Google (Gemini)',
    'PerplexityBot' => 'Perplexity',
    'CCBot' => 'Common Crawl',
    'Applebot-Extended' => 'Apple (Apple Intelligence)',
    'Bytespider' => 'ByteDance',
];

/** Grupy robots.txt: [['agenci'=>[małe litery], 'zasady'=>[['d'|'a', ścieżka]], 'sygnal'=>?string]]. */
function wa_robots(string $tekst): array {
    $grupy = [];
    $akt = null;
    $wZasadach = false;
    foreach (preg_split('/\R/u', $tekst) ?: [] as $w) {
        $w = trim((string)preg_replace('/#.*$/u', '', $w));
        if ($w === '' || !str_contains($w, ':')) { continue; }
        [$k, $v] = array_map('trim', explode(':', $w, 2));
        $k = strtolower($k);
        if ($k === 'user-agent') {
            if ($akt === null || $wZasadach) { $grupy[] = ['agenci' => [], 'zasady' => [], 'sygnal' => null]; $akt = count($grupy) - 1; $wZasadach = false; }
            $grupy[$akt]['agenci'][] = strtolower($v);
        } elseif ($akt !== null && ($k === 'disallow' || $k === 'allow')) {
            $wZasadach = true;
            $grupy[$akt]['zasady'][] = [$k === 'disallow' ? 'd' : 'a', $v];
        } elseif ($akt !== null && $k === 'content-signal') {
            $wZasadach = true;
            $grupy[$akt]['sygnal'] = $v;
        }
    }
    return $grupy;
}

/** Czy agent jest zablokowany od korzeni (Disallow: / bez późniejszego Allow: /). null = brak grupy dla agenta. */
function wa_blokada(array $grupy, string $agent): ?bool {
    $szukany = strtolower($agent);
    foreach ($grupy as $g) {
        if (in_array($szukany, $g['agenci'], true)) { return wa_grupa_blokuje($g); }
    }
    return null;
}

function wa_grupa_blokuje(array $g): bool {
    $blok = false;
    foreach ($g['zasady'] as [$t, $sc]) {
        if ($t === 'd' && $sc === '/') { $blok = true; }
        if ($t === 'a' && ($sc === '/' || $sc === '')) { $blok = false; }
    }
    return $blok;
}

/** Zbiera @type z JSON-LD (także @graph i zagnieżdżone). */
function wa_typy(mixed $w, array &$typy): void {
    if (!is_array($w)) { return; }
    if (isset($w['@type'])) {
        foreach ((array)$w['@type'] as $t) { if (is_string($t)) { $typy[$t] = true; } }
    }
    foreach ($w as $k => $v) { if ($k !== '@context' && is_array($v)) { wa_typy($v, $typy); } }
}

function wa_to_firma(string $t): bool {
    return (bool)preg_match('/(Organization|LocalBusiness|Corporation|Business$|Store$|Contractor$|Dentist|Restaurant|Hotel|Physician|Attorney|Notary|Accountant|Studio|Gym|Salon|Shop|Agency)/', $t);
}

function test_wyszukiwanie_agentowe_ai(string $url, array $ctx): array {
    $poz = [];
    $surowe = [];

    // 1) llms.txt
    $llms = pobierz_tekst(zrodlo_url($ctx, '/llms.txt'), 200000);
    $tekst = trim($llms['tekst']);
    $wyglada = $llms['ok'] && $tekst !== '' && !preg_match('/^\s*<(!doctype|html|head|body)/i', $tekst) && !str_contains($llms['typ'], 'html');
    if ($wyglada) {
        $kb = round(strlen($tekst) / 1024, 1);
        $linki = preg_match_all('~\]\((https?://[^)\s]+|/[^)\s]*)\)|https?://\S+~', $tekst);
        $naglowek = (bool)preg_match('/^#\s+\S/m', $tekst);
        if ($linki >= 3 && $naglowek) {
            $poz[] = pozycja('llms-plik', 'Plik llms.txt', 'ok', $kb . ' kB, ' . $linki . ' linków',
                'Plik llms.txt jest i wymienia ' . $linki . ' adresów, więc asystent AI od razu wie, które strony są ważne.');
        } else {
            $poz[] = pozycja('llms-plik', 'Plik llms.txt', 'uwaga', $kb . ' kB, ' . $linki . ' linków',
                'Plik llms.txt jest, ale ' . ($linki < 3 ? 'wymienia za mało stron' : 'nie ma nagłówka z nazwą firmy') . ', więc asystent AI niewiele się z niego dowie.',
                'Zacznij od nagłówka „# Nazwa firmy”, dopisz jedno zdanie o firmie i listę najważniejszych stron w formie „- [Nazwa](adres): opis”.');
        }
    } else {
        $poz[] = pozycja('llms-plik', 'Plik llms.txt', 'blad', 'brak',
            'Strona nie ma pliku llms.txt, czyli krótkiego spisu treści dla asystentów AI.',
            'Dodaj w katalogu głównym strony plik llms.txt: nazwa firmy, jedno zdanie o niej i lista najważniejszych stron z krótkim opisem.');
    }
    $surowe['llms'] = ['kod' => $llms['kod'], 'ok' => $wyglada];

    // 2) Katalog zasobów dla agentów: ai-catalog.json i ard.json
    $pliki = [];
    foreach (['ai-catalog.json', 'ard.json'] as $nazwa) {
        $p = pobierz_tekst(zrodlo_url($ctx, '/.well-known/' . $nazwa), 200000);
        $json = $p['ok'] ? json_decode($p['tekst'], true) : null;
        $pliki[$nazwa] = is_array($json) ? 'ok' : ($p['ok'] ? 'zly' : 'brak');
    }
    $surowe['ard'] = $pliki;
    $ile = count(array_filter($pliki, fn($s) => $s === 'ok'));
    $wart = [];
    foreach ($pliki as $n => $s) { $wart[] = $n . ': ' . ['ok' => 'jest', 'zly' => 'uszkodzony', 'brak' => 'brak'][$s]; }
    if ($pliki['ai-catalog.json'] === 'ok') {
        $poz[] = pozycja('ard-plik', 'Katalog zasobów dla agentów AI', 'ok', implode(', ', $wart),
            'Strona publikuje katalog zasobów w ' . ($ile === 2 ? 'obu plikach' : 'pliku ai-catalog.json') . ', więc agenci AI mogą ją znaleźć i zrozumieć bez zgadywania.');
    } elseif ($ile === 1) {
        $poz[] = pozycja('ard-plik', 'Katalog zasobów dla agentów AI', 'uwaga', implode(', ', $wart),
            'Jest tylko jeden z dwóch plików katalogu, a agenci szukają przede wszystkim ai-catalog.json.',
            'Dodaj plik /.well-known/ai-catalog.json (poprawny JSON, lista wpisów z adresem i opisem) obok ard.json.');
    } else {
        $uszk = in_array('zly', $pliki, true);
        $poz[] = pozycja('ard-plik', 'Katalog zasobów dla agentów AI', 'blad', implode(', ', $wart),
            $uszk ? 'Plik katalogu istnieje, ale nie jest poprawnym JSON-em, więc agenci go odrzucą.' : 'Strona nie publikuje katalogu zasobów dla agentów AI.',
            $uszk ? 'Napraw składnię JSON w pliku /.well-known/ai-catalog.json.' : 'Dodaj plik /.well-known/ai-catalog.json: lista usług i stron z adresem oraz krótkim opisem.');
    }

    // 3) robots.txt: boty AI i Content-Signal
    $rob = pobierz_tekst(zrodlo_url($ctx, '/robots.txt'), 200000);
    $robOk = $rob['ok'] && !preg_match('/^\s*<(!doctype|html)/i', $rob['tekst']);
    if ($robOk) {
        $grupy = wa_robots($rob['tekst']);
        $gwiazdka = null;
        foreach ($grupy as $g) { if (in_array('*', $g['agenci'], true)) { $gwiazdka = wa_grupa_blokuje($g); break; } }
        $zablok = [];
        $szczegoly = [];
        foreach (WA_BOTY as $bot => $opisBota) {
            $b = wa_blokada($grupy, $bot);
            $efekt = $b ?? ($gwiazdka === true);
            $szczegoly[$bot] = $b === null ? ($gwiazdka === true ? 'blokada z grupy *' : 'brak wpisu (domyślnie dozwolony)') : ($b ? 'zablokowany' : 'dozwolony');
            if ($efekt) { $zablok[] = $bot; }
        }
        $surowe['boty'] = $szczegoly;
        $n = count(WA_BOTY);
        $wpuszczone = $n - count($zablok);
        // CCBot i Bytespider zbierają dane do trenowania, a nie odpowiadają użytkownikom, więc ich blokada nie obniża oceny.
        $istotne = array_diff($zablok, ['CCBot', 'Bytespider']);
        if (!$zablok) {
            $poz[] = pozycja('robots-ai', 'Dostęp botów AI (robots.txt)', 'ok', $n . ' z ' . $n . ' botów ma wstęp',
                'Boty asystentów AI mają wstęp: Twoje treści mogą być cytowane w odpowiedziach AI. Jeśli tego nie chcesz, zablokuj je w robots.txt.');
        } elseif (!$istotne) {
            $poz[] = pozycja('robots-ai', 'Dostęp botów AI (robots.txt)', 'ok', $wpuszczone . ' z ' . $n . ' botów ma wstęp',
                'Asystenci AI (ChatGPT, Claude, Gemini, Perplexity) mają wstęp. Zablokowane: ' . implode(', ', $zablok) . ' (boty zbierające dane do trenowania, to rozsądna decyzja).');
        } else {
            $poz[] = pozycja('robots-ai', 'Dostęp botów AI (robots.txt)', 'uwaga', $wpuszczone . ' z ' . $n . ' botów ma wstęp',
                'Zablokowane boty: ' . implode(', ', $zablok) . '. Te asystenty AI nie przeczytają strony i nie zacytują jej w odpowiedziach.',
                'Jeśli chcesz być widoczny w odpowiedziach AI, usuń blokady dla tych botów z robots.txt. Jeśli blokada jest zamierzona, zostaw ją.');
        }
        $sygnal = null;
        foreach ($grupy as $g) { if ($g['sygnal'] !== null) { $sygnal = $g['sygnal']; break; } }
        if ($sygnal === null && preg_match('/^\s*content-signal\s*:\s*(.+)$/mi', $rob['tekst'], $m)) { $sygnal = trim($m[1]); }
        if ($sygnal !== null && $sygnal !== '') {
            $poz[] = pozycja('content-signal', 'Zgoda na użycie treści (Content-Signal)', 'ok', $sygnal,
                'Robots.txt mówi wprost, do czego wolno użyć Twoich treści (wyszukiwanie, odpowiedzi AI, trenowanie modeli).');
        } else {
            $poz[] = pozycja('content-signal', 'Zgoda na użycie treści (Content-Signal)', 'uwaga', 'brak',
                'Robots.txt nie zawiera deklaracji Content-Signal, więc nie mówi, czy wolno używać treści do odpowiedzi AI i trenowania.',
                'Dopisz w robots.txt wiersz Content-Signal, np. „Content-Signal: search=yes, ai-input=yes, ai-train=no”.');
        }
    } else {
        $surowe['boty'] = 'brak robots.txt';
        $poz[] = pozycja('robots-ai', 'Dostęp botów AI (robots.txt)', 'info', 'brak pliku robots.txt',
            'Strona nie ma pliku robots.txt, więc boty AI mają wstęp domyślnie, a Ty nie wyrażasz żadnej woli w tej sprawie.',
            'Dodaj plik robots.txt i zdecyduj, które boty AI mają wstęp.');
        $poz[] = pozycja('content-signal', 'Zgoda na użycie treści (Content-Signal)', 'uwaga', 'brak',
            'Bez robots.txt nie ma też deklaracji Content-Signal.',
            'Dodaj robots.txt z wierszem „Content-Signal: search=yes, ai-input=yes, ai-train=no”.');
    }

    // 4) Dane strukturalne JSON-LD
    [, $xp] = dom((string)$ctx['html']);
    $typy = [];
    $zepsute = 0;
    foreach ($xp->query('//script[translate(@type,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="application/ld+json"]') as $s) {
        $j = json_decode(trim($s->textContent), true);
        if (is_array($j)) { wa_typy($j, $typy); } else { $zepsute++; }
    }
    $nazwyTypow = array_keys($typy);
    $firma = (bool)array_filter($nazwyTypow, 'wa_to_firma');
    $witryna = isset($typy['WebSite']);
    $surowe['jsonld'] = ['typy' => $nazwyTypow, 'zepsute' => $zepsute];
    $lista = $nazwyTypow ? implode(', ', array_slice($nazwyTypow, 0, 8)) . (count($nazwyTypow) > 8 ? '…' : '') : 'brak';
    if ($firma && $witryna) {
        $poz[] = pozycja('jsonld', 'Dane strukturalne (JSON-LD)', 'ok', $lista,
            'Strona opisuje maszynom firmę i witrynę, więc asystent AI i Google wiedzą, kim jesteś i czym się zajmujesz.');
    } elseif ($nazwyTypow && ($firma || $witryna)) {
        $poz[] = pozycja('jsonld', 'Dane strukturalne (JSON-LD)', 'uwaga', $lista,
            'Dane strukturalne są, ale brakuje ' . ($firma ? 'opisu witryny (WebSite)' : 'opisu firmy (Organization albo LocalBusiness)') . '.',
            'Dodaj blok JSON-LD z typem ' . ($firma ? 'WebSite' : 'Organization albo LocalBusiness (nazwa, adres, telefon, logo)') . '.');
    } elseif ($nazwyTypow) {
        $poz[] = pozycja('jsonld', 'Dane strukturalne (JSON-LD)', 'uwaga', $lista,
            'Strona ma dane strukturalne, ale żadne nie opisuje firmy ani witryny.',
            'Dodaj blok JSON-LD z typami Organization (albo LocalBusiness) i WebSite.');
    } else {
        $poz[] = pozycja('jsonld', 'Dane strukturalne (JSON-LD)', 'blad', $zepsute ? 'uszkodzone (' . $zepsute . ')' : 'brak',
            $zepsute ? 'Strona ma blok JSON-LD, ale nie da się go odczytać, więc maszyny go pomijają.' : 'Strona nie opisuje maszynom, kim jest firma, więc asystent AI musi zgadywać z treści.',
            'Dodaj blok JSON-LD z typami Organization (albo LocalBusiness) i WebSite: nazwa, adres strony, logo, telefon.');
    }

    // 5) Meta noai / noimageai (informacja)
    $robotsMeta = [];
    foreach ($xp->query('//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="robots"]/@content') as $a) { $robotsMeta[] = strtolower($a->value); }
    $xrt = strtolower((string)naglowek($ctx, 'x-robots-tag'));
    $zbiorczo = implode(',', $robotsMeta) . ',' . $xrt;
    $noai = [];
    foreach (['noai', 'noimageai'] as $f) { if (preg_match('/(^|[\s,])' . $f . '($|[\s,])/', $zbiorczo)) { $noai[] = $f; } }
    $poz[] = pozycja('noai', 'Znaczniki noai / noimageai', 'info', $noai ? implode(', ', $noai) : 'brak',
        $noai ? 'Strona prosi, by nie używać jej treści do trenowania AI (' . implode(', ', $noai) . '). To prośba, nie wszystkie firmy ją respektują.' : 'Strona nie zawiera znaczników noai ani noimageai, więc nie prosi o wyłączenie treści z trenowania AI. To zwykle dobrze dla widoczności.');

    $wagi = ['llms-plik' => 2, 'ard-plik' => 2, 'robots-ai' => 1, 'content-signal' => 1, 'jsonld' => 2];
    return ['ocena' => ocena_z_pozycji($poz, $wagi), 'pozycje' => $poz, 'surowe' => $surowe];
}
