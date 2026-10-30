<?php
declare(strict_types=1);
/**
 * Poczta domeny: MX, SPF, DKIM, DMARC, BIMI i werdykt „czy można podszyć się pod domenę”. Tylko DNS (bez HTML strony).
 * Logikę DNS (spf/dkim/dmarc/mx/bimi) trzyma _dns.php — dzieli ją moduł ochrona_przed_spamem.
 */
if (!defined('AUDYT_EP')) { http_response_code(404); exit; }
require_once __DIR__ . '/_dns.php';

/** Gotowy rekord SPF dla domeny: na bazie istniejącego (podmiana „all” na -all) albo nowy wg dostawcy poczty. */
function poczta_spf_gotowy(array $spf, array $mx, string $domena): string {
    if ($spf['rekordow'] > 1) {
        $meh = [];
        foreach ($spf['rekordy'] as $r) {
            foreach (array_slice(preg_split('/\s+/', trim($r)) ?: [], 1) as $m) {
                if (!preg_match('/^[+\-~?]?all$/i', $m)) { $meh[strtolower($m)] = $m; }
            }
        }
        return 'v=spf1 ' . implode(' ', $meh) . ' -all';
    }
    if ($spf['rekord'] !== null) {
        $r = trim($spf['rekord']);
        if (preg_match('/(?:^|\s)[+\-~?]?all(?:\s|$)/i', $r)) { return (string)preg_replace('/(^|\s)[+\-~?]?all(?=\s|$)/i', '$1-all', $r, 1); }
        return $r . ' -all';
    }
    if ($mx['null_mx'] || $mx['brak']) { return 'v=spf1 -all'; }
    $d = $mx['dostawcy'][0] ?? '';
    return match ($d) {
        'Google Workspace' => 'v=spf1 include:_spf.google.com -all',
        'Microsoft 365' => 'v=spf1 include:spf.protection.outlook.com -all',
        'lh.pl' => 'v=spf1 include:_spf.lh.pl -all',
        'OVH' => 'v=spf1 include:mx.ovh.com -all',
        default => 'v=spf1 mx -all',
    };
}

function test_poczta_domeny(string $url, array $ctx): array {
    $host = (string)($ctx['host'] ?? '');
    $domena = dns_domena_poczty($host);
    $mx = mx($domena);
    $spf = spf($domena);
    $dkim = dkim($domena);
    $dmarc = dmarc($domena);
    $bimi = bimi($domena);
    $bezPoczty = $mx['null_mx'] || $mx['brak'];
    $poz = [];

    // ── MX ──────────────────────────────────────────────────────────────────────────────────────────────
    if ($mx['null_mx']) {
        $poz[] = pozycja('mx', 'Serwery poczty (MX)', 'ok', 'domena nie przyjmuje poczty', 'Domena ma celowy wpis „brak poczty” (null MX), więc nikt nie wyśle do niej wiadomości.', '');
    } elseif ($mx['brak']) {
        $poz[] = pozycja('mx', 'Serwery poczty (MX)', 'uwaga', 'brak', 'Domena nie ma rekordu MX, więc nie odbiera poczty (maile na adresy w tej domenie wrócą z błędem). Jeśli tak ma być — w porządku, ale warto wtedy zabezpieczyć domenę przed podszywaniem.',
            'Jeśli firma ma adresy e-mail w tej domenie, dodaj rekord MX podany przez dostawcę poczty. Jeśli domena nie obsługuje poczty, ustaw rekord TXT „v=spf1 -all” dla @ i DMARC z p=reject.');
    } else {
        $skrzynki = array_values(array_filter($mx['dostawcy'], static fn($d) => !dns_czy_bramka($d)));
        $opis = [];
        foreach ($mx['dostawcy_hosty'] as $n => $ile) { $opis[] = $n . ' (' . $ile . ' ' . ($ile === 1 ? 'serwer' : ($ile < 5 ? 'serwery' : 'serwerów')) . ')'; }
        foreach ($mx['nierozpoznane'] as $h) { $opis[] = $h; }
        $wart = implode(', ', array_slice($opis, 0, 3)) . (count($opis) > 3 ? ' i inne' : '');
        // czy serwery z MX w ogóle istnieją w DNS
        $nieMa = [];
        foreach (array_slice($mx['rekordy'], 0, 3) as $m) {
            if ($m['host'] !== '' && dns_a($m['host']) === [] && dns_aaaa($m['host']) === []) { $nieMa[] = $m['host']; }
        }
        if ($nieMa !== []) {
            $poz[] = pozycja('mx', 'Serwery poczty (MX)', 'blad', $wart, 'Serwer poczty „' . $nieMa[0] . '” nie ma adresu w DNS — wiadomości do Twojej domeny mogą nie dochodzić.', 'Popraw rekord MX tak, by wskazywał działający serwer poczty (nazwę poda dostawca poczty).');
        } elseif (count($skrzynki) + count($mx['nierozpoznane']) > 1 && count($skrzynki) > 1) {
            $poz[] = pozycja('mx', 'Serwery poczty (MX)', 'uwaga', $wart, 'Poczta kierowana jest do kilku różnych dostawców naraz, więc część wiadomości może trafiać do innej skrzynki, niż myślisz (zwykle to pozostałość po zmianie dostawcy).', 'Zostaw rekordy MX tylko jednego dostawcy poczty, a stare usuń.');
        } else {
            $poz[] = pozycja('mx', 'Serwery poczty (MX)', 'ok', $wart, 'Poczta domeny jest obsługiwana przez ' . ($skrzynki[0] ?? ($mx['rekordy'][0]['host'] ?? 'zewnętrznego dostawcę')) . '.', '');
        }
    }

    // ── SPF ─────────────────────────────────────────────────────────────────────────────────────────────
    $spfGotowy = poczta_spf_gotowy($spf, $mx, $domena);
    $zalSpf = 'Dodaj lub zmień rekord TXT dla @ (nazwa domeny) na: ' . $spfGotowy . ' — jeśli wysyłasz maile także z innych usług (newsletter, sklep), dopisz ich „include:” przed „-all”.';
    if ($spf['rekord'] === null) {
        $poz[] = pozycja('spf', 'SPF (lista uprawnionych nadawców)', 'blad', 'brak',
            'Domena nie ma rekordu SPF, więc odbiorcy nie wiedzą, kto może wysyłać maile w jej imieniu. Twoje maile mogą trafiać do spamu, a ktoś obcy może łatwiej podszyć się pod Twój adres.', $zalSpf);
        $poz[] = pozycja('spf-lookupy', 'SPF: liczba zapytań DNS', 'info', 'brak SPF', 'Bez rekordu SPF nie ma czego liczyć.', '');
    } else {
        $all = $spf['all'];
        $zasadaOpis = ['-' => 'kończy się „-all” (obcy nadawcy są odrzucani)', '~' => 'kończy się „~all” (obcy nadawcy są tylko oznaczani jako podejrzani)', '?' => 'kończy się „?all” (SPF nie mówi nic o obcych nadawcach)', '+' => 'kończy się „+all” (SPF zezwala na wysyłkę każdemu na świecie)'];
        $krytyczne = array_values(array_filter($spf['bledy'], static fn($b) => !str_contains($b, 'przestarzały') && !str_contains($b, 'ignorowane')));
        if ($spf['rekordow'] > 1) {
            $poz[] = pozycja('spf', 'SPF (lista uprawnionych nadawców)', 'blad', $spf['rekordow'] . ' rekordy SPF',
                'Domena ma kilka rekordów SPF, a ma być jeden. Odbiorcy uznają wtedy SPF za błędny i Twoje maile mogą trafiać do spamu.', 'Połącz rekordy w jeden: ' . $spfGotowy);
        } elseif ($krytyczne !== []) {
            $poz[] = pozycja('spf', 'SPF (lista uprawnionych nadawców)', 'blad', 'błąd składni', 'Rekord SPF ma błąd: ' . $krytyczne[0] . ' Odbiorcy mogą go odrzucić, a wtedy maile trafiają do spamu.', 'Popraw rekord TXT dla @. Przykład poprawnego: ' . $spfGotowy);
        } elseif ($all === '-') {
            $poz[] = pozycja('spf', 'SPF (lista uprawnionych nadawców)', 'ok', '-all', 'Rekord SPF ' . $zasadaOpis['-'] . '.', '');
        } elseif ($all === '~') {
            $poz[] = pozycja('spf', 'SPF (lista uprawnionych nadawców)', 'uwaga', '~all', 'Rekord SPF ' . $zasadaOpis['~'] . '. To dobry początek, ale podszywający się nadawca nadal może dostać się do skrzynki odbiorcy.', 'Gdy wszystkie Twoje usługi wysyłające są na liście, zmień „~all” na „-all”: ' . $spfGotowy);
        } elseif ($all === '?' || $all === '+') {
            $poz[] = pozycja('spf', 'SPF (lista uprawnionych nadawców)', 'blad', $all . 'all', 'Rekord SPF ' . $zasadaOpis[$all] . ', więc w praktyce nie chroni domeny — każdy może wysłać maila w Twoim imieniu.', 'Zmień zakończenie na „-all”: ' . $spfGotowy);
        } else {
            $poz[] = pozycja('spf', 'SPF (lista uprawnionych nadawców)', 'blad', 'bez „all”', 'Rekord SPF nie kończy się zasadą „all”, więc nie mówi, co zrobić z obcymi nadawcami — nie chroni domeny.', 'Dopisz na końcu „-all”: ' . $spfGotowy);
        }
        $l = $spf['lookupy'];
        $wartL = $l . ' z 10' . ($spf['niepelne'] ? ' (co najmniej)' : '');
        if ($l > 10) {
            $poz[] = pozycja('spf-lookupy', 'SPF: liczba zapytań DNS', 'blad', $wartL, 'SPF wymaga ' . $l . ' zapytań DNS, a limit to 10. Po jego przekroczeniu odbiorcy uznają SPF za błędny i Twoje maile mogą trafiać do spamu.', 'Usuń „include:” usług, z których już nie wysyłasz, lub zastąp długi łańcuch adresami IP (ip4:) swojego serwera.');
        } elseif ($l >= 9) {
            $poz[] = pozycja('spf-lookupy', 'SPF: liczba zapytań DNS', 'uwaga', $wartL, 'SPF zużywa prawie cały limit 10 zapytań DNS — kolejna usługa na liście może go przekroczyć i unieważnić SPF.', 'Usuń z SPF usługi, z których już nie wysyłasz.');
        } else {
            $poz[] = pozycja('spf-lookupy', 'SPF: liczba zapytań DNS', 'ok', $wartL, 'SPF mieści się w limicie 10 zapytań DNS.', '');
        }
    }

    // ── DKIM ────────────────────────────────────────────────────────────────────────────────────────────
    if ($bezPoczty) {
        $poz[] = pozycja('dkim', 'DKIM (podpis wiadomości)', 'info', 'nie dotyczy', 'Domena nie obsługuje poczty, więc podpis wiadomości nie jest potrzebny.', '');
    } elseif ($dkim['znalezione'] !== []) {
        $nazwy = array_map(static fn($z) => $z['selektor'], $dkim['znalezione']);
        $poz[] = pozycja('dkim', 'DKIM (podpis wiadomości)', 'ok', implode(', ', array_slice($nazwy, 0, 4)), 'Znaleziono klucz DKIM (selektor: ' . implode(', ', array_slice($nazwy, 0, 4)) . '). Odbiorca może sprawdzić, że wiadomość nie została zmieniona po drodze.', '');
    } elseif ($dkim['sprawdzone'] === []) {
        $poz[] = pozycja('dkim', 'DKIM (podpis wiadomości)', 'info', 'nie udało się sprawdzić', 'Serwer DNS tej domeny nie odpowiedział w czasie na zapytania o klucze DKIM, więc nie wiemy, czy podpis wiadomości jest włączony. Spróbuj ponownie za chwilę.', 'Sprawdź w panelu poczty, czy podpis DKIM jest włączony.');
    } else {
        $dod = $dkim['uniewazniony'] !== [] ? ' Znalezione klucze (' . implode(', ', $dkim['uniewazniony']) . ') są unieważnione.' : '';
        $dod .= $dkim['niesprawdzone'] !== [] ? ' Serwer DNS domeny nie odpowiedział w czasie na ' . count($dkim['niesprawdzone']) . ' z ' . (count($dkim['niesprawdzone']) + count($dkim['sprawdzone'])) . ' zapytań, więc wynik jest niepełny.' : '';
        $poz[] = pozycja('dkim', 'DKIM (podpis wiadomości)', 'uwaga', 'nie znaleziono typowych selektorów',
            'Sprawdziliśmy ' . count($dkim['sprawdzone']) . ' najczęstszych nazw kluczy DKIM i żadnej nie znaleźliśmy. Nazwa klucza bywa dowolna, więc to nie dowód, że podpisu nie ma — ale jeśli go nie ma, Twoje maile mogą częściej trafiać do spamu.' . $dod,
            'Włącz podpis DKIM w panelu poczty (Google Workspace, Microsoft 365, hosting) i dodaj rekord TXT, który panel poda. Nazwa klucza (selektor) jest w panelu.');
    }

    // ── DMARC ───────────────────────────────────────────────────────────────────────────────────────────
    $rua = $dmarc['rua'][0] ?? '';
    $ruaGotowy = 'mailto:dmarc@' . $domena;
    $dmarcGotowy = 'v=DMARC1; p=quarantine; rua=' . ($rua !== '' ? implode(',', $dmarc['rua']) : $ruaGotowy) . '; pct=100';
    $zalDmarc = 'Dodaj rekord TXT o nazwie _dmarc o treści: ' . 'v=DMARC1; p=quarantine; rua=' . $ruaGotowy . '; pct=100 — adres dmarc@' . $domena . ' musi istnieć (albo wpisz inny). Jeśli nie masz pewności, że każda Twoja usługa wysyłająca przechodzi SPF lub DKIM, zacznij na dwa tygodnie od p=none i czytaj raporty.';
    $pol = $dmarc['p'];
    if ($dmarc['rekord'] === null) {
        $poz[] = pozycja('dmarc', 'DMARC (polityka wobec podróbek)', 'blad', 'brak', 'Domena nie ma DMARC, więc odbiorcy nie wiedzą, co zrobić z mailami podszywającymi się pod Ciebie — a Google i Yahoo wymagają DMARC od nadawców wysyłających dużo poczty. Twoje maile mogą trafiać do spamu.', $zalDmarc);
        $poz[] = pozycja('dmarc-raporty', 'DMARC: raporty', 'info', 'brak DMARC', 'Raporty przychodzą dopiero po dodaniu DMARC.', '');
    } else {
        $dz = $dmarc['dziedziczony'] ? ' (rekord z domeny nadrzędnej ' . $dmarc['domena'] . ')' : '';
        if ($pol === null) {
            $poz[] = pozycja('dmarc', 'DMARC (polityka wobec podróbek)', 'blad', 'błąd w rekordzie', 'Rekord DMARC jest błędny: ' . ($dmarc['bledy'][0] ?? 'brak polityki') . ' Odbiorcy zignorują go, jakby go nie było.', 'Popraw rekord _dmarc, np.: ' . $dmarcGotowy);
        } elseif ($pol === 'none') {
            $poz[] = pozycja('dmarc', 'DMARC (polityka wobec podróbek)', 'uwaga', 'p=none' . $dz, 'DMARC działa tylko w trybie obserwacji (p=none): podróbki nadal trafiają do odbiorców, a Ty tylko dostajesz raporty.', 'Gdy raporty pokażą, że prawdziwa poczta przechodzi, zmień na: ' . $dmarcGotowy);
        } elseif ($dmarc['pct'] < 100) {
            $poz[] = pozycja('dmarc', 'DMARC (polityka wobec podróbek)', 'uwaga', 'p=' . $pol . '; pct=' . $dmarc['pct'] . $dz, 'Polityka DMARC obejmuje tylko ' . $dmarc['pct'] . '% wiadomości, więc reszta podróbek przechodzi bez kontroli.', 'Podnieś pct do 100: ' . $dmarcGotowy);
        } elseif ($dmarc['sp'] === 'none' && $dmarc['sp'] !== $pol) {
            $poz[] = pozycja('dmarc', 'DMARC (polityka wobec podróbek)', 'uwaga', 'p=' . $pol . '; sp=none' . $dz, 'Domena główna jest chroniona, ale subdomeny (sp=none) nie — z nich można wysyłać podróbki.', 'Usuń „sp=none” z rekordu albo ustaw sp=' . $pol . '.');
        } else {
            $poz[] = pozycja('dmarc', 'DMARC (polityka wobec podróbek)', 'ok', 'p=' . $pol . $dz, 'DMARC każe odbiorcom ' . ($pol === 'reject' ? 'odrzucać' : 'kierować do spamu') . ' wiadomości, które podszywają się pod domenę.', '');
        }
        if ($dmarc['rua'] === []) {
            $poz[] = pozycja('dmarc-raporty', 'DMARC: raporty', 'uwaga', 'brak rua', 'Rekord DMARC nie ma adresu na raporty (rua=), więc nie dowiesz się, kto i skąd wysyła maile w Twoim imieniu.', 'Dopisz do rekordu _dmarc: rua=' . $ruaGotowy . ' (skrzynka musi istnieć).');
        } else {
            $poz[] = pozycja('dmarc-raporty', 'DMARC: raporty', 'ok', count($dmarc['rua']) === 1 ? $dmarc['rua'][0] : count($dmarc['rua']) . ' adresy', 'Raporty o próbach podszycia trafiają na adres z rekordu DMARC.', '');
        }
    }

    // ── BIMI ────────────────────────────────────────────────────────────────────────────────────────────
    if ($bimi['rekord'] !== null) {
        $poz[] = pozycja('bimi', 'BIMI (logo przy wiadomościach)', 'info', $bimi['logo'] ? 'jest' : 'niepełny', 'Domena ma rekord BIMI — logo firmy może pojawiać się przy jej wiadomościach u odbiorców, którzy to obsługują.', '');
    } else {
        $poz[] = pozycja('bimi', 'BIMI (logo przy wiadomościach)', 'info', 'brak', 'BIMI to opcjonalna ozdoba: logo firmy przy wiadomościach w Gmailu i Yahoo. Wymaga DMARC z p=quarantine lub reject i zwykle płatnego certyfikatu logo.', '');
    }

    // ── Werdykt: czy można się podszyć ──────────────────────────────────────────────────────────────────
    $spfMocny = $spf['rekord'] !== null && $spf['rekordow'] === 1 && in_array($spf['all'], ['-', '~'], true) && !$spf['przekroczono'];
    $dmarcMocny = $dmarc['rekord'] !== null && in_array($pol, ['quarantine', 'reject'], true) && $dmarc['pct'] >= 100;
    if ($dmarcMocny && $spfMocny) {
        $poz[] = pozycja('podszycie', 'Czy ktoś może podszyć się pod Twoją domenę?', 'ok', 'trudno', 'SPF i DMARC razem sprawiają, że wiadomości podszywające się pod Twoją domenę są kierowane do spamu albo odrzucane.', '');
    } elseif ($spf['rekord'] === null && $dmarc['rekord'] === null) {
        $poz[] = pozycja('podszycie', 'Czy ktoś może podszyć się pod Twoją domenę?', 'blad', 'tak, łatwo', 'Nie ma ani SPF, ani DMARC, więc każdy może wysłać maila z Twoim adresem w polu „od” i odbiorca nie ma jak tego wykryć.', 'Dodaj SPF: ' . $spfGotowy . ' oraz DMARC: v=DMARC1; p=quarantine; rua=' . $ruaGotowy . '; pct=100');
    } elseif ($dmarc['rekord'] === null) {
        $poz[] = pozycja('podszycie', 'Czy ktoś może podszyć się pod Twoją domenę?', 'blad', 'tak', 'Bez DMARC odbiorcy sami decydują, co zrobić z podróbką, a większość ją przyjmie. Sam SPF nie wystarcza, bo nie dotyczy adresu widocznego dla człowieka.', 'Dodaj DMARC: ' . $dmarcGotowy);
    } else {
        $poz[] = pozycja('podszycie', 'Czy ktoś może podszyć się pod Twoją domenę?', 'uwaga', 'częściowo', 'Zabezpieczenia są, ale niepełne: ' . ($pol === 'none' ? 'DMARC tylko obserwuje (p=none)' : ($dmarc['pct'] < 100 ? 'DMARC obejmuje część wiadomości' : 'SPF lub DMARC ma usterkę')) . ', więc część podróbek może dojść do odbiorców.', 'Wdróż zalecenia powyżej (SPF z -all i DMARC z p=quarantine lub reject).');
    }

    $wagi = ['mx' => 2, 'spf' => 3, 'spf-lookupy' => 1, 'dkim' => 2, 'dmarc' => 3, 'dmarc-raporty' => 1, 'bimi' => 1, 'podszycie' => 1];
    return [
        'ocena' => ocena_z_pozycji($poz, $wagi),
        'pozycje' => $poz,
        'surowe' => [
            'domena' => $domena,
            'mx' => $mx['rekordy'],
            'spf' => ['rekord' => $spf['rekord'], 'all' => $spf['all'], 'lookupy' => $spf['lookupy'], 'bledy' => $spf['bledy']],
            'dkim' => array_map(static fn($z) => $z['selektor'], $dkim['znalezione']),
            'dmarc' => ['rekord' => $dmarc['rekord'], 'p' => $dmarc['p'], 'sp' => $dmarc['sp'], 'rua' => $dmarc['rua'], 'ruf' => $dmarc['ruf'], 'pct' => $dmarc['pct'], 'adkim' => $dmarc['adkim'], 'aspf' => $dmarc['aspf']],
            'bimi' => $bimi['rekord'],
        ],
    ];
}
