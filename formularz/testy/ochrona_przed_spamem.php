<?php
declare(strict_types=1);
/**
 * Moduł „Ochrona przed spamem” (runda 9, narzędzie 9). Zależy od testy/_dns.php (agent narzędzia „Poczta domeny”):
 * funkcje spf($domena), dkim($domena, $selektory), dmarc($domena) — wołane tylko przez tę warstwę, bez własnych kopii.
 * Pozycje: spf, dkim, dmarc (skrót), czarne listy (domena + IP serwerów poczty), formularze, komentarze, adresy e-mail
 * w HTML, security.txt, werdykt zbiorczy. Wagi trzyma JSON narzędzia (src/data/narzedzia/ochrona-przed-spamem.json).
 */
if (!defined('AUDYT_EP')) { http_response_code(404); exit; }

require_once __DIR__ . '/_dns.php';

const SP_WAGI = ['spf' => 2, 'dkim' => 1, 'dmarc' => 3, 'czarne-listy' => 3, 'formularze' => 3, 'komentarze' => 2, 'email-w-html' => 1];
const SP_SELEKTORY = ['default', 'google', 'selector1', 'selector2', 'k1', 'mail', 'dkim', 's1', 's2'];

// ── Skrót poczty z _dns.php (dane → jeden wiersz na rekord) ───────────────────────────────────────────────────
function sp_dostepne(string $fn): bool { return function_exists($fn); }

function sp_spf(string $dom): array {
    if (!sp_dostepne('spf')) { return pozycja('spf', 'SPF', 'info', 'nie sprawdzono', 'Sprawdzanie poczty jest niedostępne na tym serwerze.'); }
    $r = spf($dom);
    if ($r['rekord'] === null) {
        return pozycja('spf', 'SPF', 'blad', 'brak', 'Domena nie ma rekordu SPF, więc odbiorcy nie wiedzą, które serwery wolno jej używać do wysyłki — łatwo się pod nią podszyć.',
            'Dodaj w DNS rekord TXT dla ' . $dom . ' z listą Twoich serwerów, np. v=spf1 include:_spf.google.com ~all (adres include podaje dostawca poczty).');
    }
    if ($r['rekordow'] > 1 || $r['przekroczono']) {
        return pozycja('spf', 'SPF', 'blad', $r['rekordow'] > 1 ? $r['rekordow'] . ' rekordy SPF' : 'za dużo zapytań (' . $r['lookupy'] . ')',
            $r['rekordow'] > 1 ? 'Domena ma kilka rekordów SPF, więc odbiorcy uznają SPF za nieważny.' : 'SPF wymaga więcej niż 10 zapytań DNS, więc odbiorcy uznają go za nieważny.',
            $r['rekordow'] > 1 ? 'Połącz rekordy SPF w jeden.' : 'Skróć SPF: usuń niepotrzebne include i mechanizmy.');
    }
    $all = (string)$r['all'];
    $zn = ['-' => '-all', '~' => '~all', '?' => '?all', '+' => '+all'][$all] ?? 'bez all';
    if ($all === '-' || $all === '~') {
        return pozycja('spf', 'SPF', $r['bledy'] ? 'uwaga' : 'ok', $zn, ($all === '-' ? 'SPF odrzuca poczty z niewymienionych serwerów.' : 'SPF oznacza poczty z niewymienionych serwerów jako podejrzane (~all).') . ($r['bledy'] ? ' Uwaga: ' . $r['bledy'][0] : ''),
            $r['bledy'] ? 'Popraw błąd w rekordzie SPF.' : '');
    }
    return pozycja('spf', 'SPF', 'uwaga', $zn, $all === '+' ? 'SPF z +all pozwala wysyłać pocztę z Twojej domeny komukolwiek.' : 'SPF nie kończy się ostrym zakończeniem (-all albo ~all), więc nie chroni przed podszywaniem się.',
        'Zakończ rekord SPF dla ' . $dom . ' na ~all (albo -all, gdy masz pewność co do wszystkich serwerów wysyłających).');
}
function sp_dkim(string $dom): array {
    if (!sp_dostepne('dkim')) { return pozycja('dkim', 'DKIM', 'info', 'nie sprawdzono', 'Sprawdzanie poczty jest niedostępne na tym serwerze.'); }
    $r = dkim($dom, SP_SELEKTORY);
    if ($r['znalezione']) {
        return pozycja('dkim', 'DKIM', 'ok', 'jest (' . implode(', ', array_slice(array_column($r['znalezione'], 'selektor'), 0, 3)) . ')', 'Poczty z domeny są podpisywane, więc odbiorca może sprawdzić, że nikt ich nie zmienił.');
    }
    if ($r['uniewazniony']) {
        return pozycja('dkim', 'DKIM', 'uwaga', 'klucz unieważniony', 'Klucz DKIM (' . implode(', ', $r['uniewazniony']) . ') ma pusty zapis p=, czyli został unieważniony.', 'Włącz podpisywanie DKIM u dostawcy poczty i opublikuj nowy klucz.');
    }
    return pozycja('dkim', 'DKIM', 'uwaga', 'nie znaleziono', 'Wśród najczęstszych nazw (' . count($r['sprawdzone']) . ' sprawdzonych) nie ma klucza DKIM — możliwe, że domena używa innej nazwy.',
        'Włącz podpisywanie DKIM w panelu dostawcy poczty (Google Workspace, Microsoft 365, hosting) i wklej podany rekord do DNS.');
}
function sp_dmarc(string $dom): array {
    if (!sp_dostepne('dmarc')) { return pozycja('dmarc', 'DMARC', 'info', 'nie sprawdzono', 'Sprawdzanie poczty jest niedostępne na tym serwerze.'); }
    $r = dmarc($dom);
    if ($r['rekord'] === null) {
        return pozycja('dmarc', 'DMARC', 'blad', 'brak', 'Bez DMARC odbiorcy nie wiedzą, co zrobić z pocztą podszywającą się pod Twoją domenę, i nie dostajesz raportów o nadużyciach.',
            'Dodaj w DNS rekord TXT o nazwie _dmarc.' . $dom . ' z wartością: v=DMARC1; p=none; rua=mailto:dmarc@' . $dom . ' (na start tylko raporty; po kilku tygodniach zmień p=none na p=quarantine, potem p=reject).');
    }
    $p = (string)$r['p'];
    $kto = $r['dziedziczony'] ? ' (rekord domeny ' . $r['domena'] . ')' : '';
    if ($r['bledy'] || $p === '' || $r['rekordow'] > 1) {
        return pozycja('dmarc', 'DMARC', 'uwaga', 'błąd w rekordzie', 'Rekord DMARC jest, ale: ' . ($r['bledy'][0] ?? 'nie ma polityki p='), 'Popraw rekord _dmarc.' . $dom . ' na v=DMARC1; p=quarantine; rua=mailto:dmarc@' . $dom);
    }
    if ($p === 'none') {
        return pozycja('dmarc', 'DMARC', 'uwaga', 'p=none' . $kto, 'DMARC tylko zbiera raporty — podszywające się wiadomości nadal docierają do odbiorców.' . ($r['rua'] ? '' : ' Brakuje też adresu raportów (rua=).'),
            'Zmień w rekordzie _dmarc.' . $dom . ' politykę p=none na p=quarantine, a po kilku tygodniach bez problemów na p=reject.');
    }
    if ($r['pct'] < 100) {
        return pozycja('dmarc', 'DMARC', 'uwaga', 'p=' . $p . ', pct=' . $r['pct'], 'Polityka ' . $p . ' obejmuje tylko ' . $r['pct'] . '% wiadomości.', 'Usuń pct= z rekordu albo ustaw pct=100, gdy raporty są czyste.');
    }
    return pozycja('dmarc', 'DMARC', 'ok', 'p=' . $p . $kto, 'Poczty podszywające się pod domenę są ' . ($p === 'reject' ? 'odrzucane' : 'kierowane do spamu') . '.');
}

// ── Czarne listy (DNSBL) ─────────────────────────────────────────────────────────────────────────────────────────
/** Zapytanie do listy: ['hit'=>bool,'kod'=>?string,'blad'=>bool]. Brak odpowiedzi = czysto; 127.255.255.x = błąd zapytania. */
function sp_dnsbl(string $zapytanie): array {
    $a = dns_a($zapytanie);
    foreach ($a as $ip) {
        if (!str_starts_with($ip, '127.')) { continue; }
        if (str_starts_with($ip, '127.255.255.')) { return ['hit' => false, 'kod' => $ip, 'blad' => true]; }
        return ['hit' => true, 'kod' => $ip, 'blad' => false];
    }
    return ['hit' => false, 'kod' => null, 'blad' => false];
}
function sp_powod_dbl(string $kod): string {
    return match (true) {
        $kod === '127.0.1.2' => 'spam', $kod === '127.0.1.4' => 'wyłudzanie danych (phishing)', $kod === '127.0.1.5' => 'złośliwe oprogramowanie',
        $kod === '127.0.1.6' => 'botnet', str_starts_with($kod, '127.0.1.10') => 'domena przejęta przez spamerów', default => 'wpis na liście',
    };
}
function sp_czarne_listy(array $ctx, string $dom): array {
    $host = (string)$ctx['host'];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return pozycja('czarne-listy', 'Czarne listy', 'info', 'adres IP', 'Strona jest pod samym adresem IP, więc nie ma domeny do sprawdzenia na czarnych listach.');
    }
    $wpisy = []; $bledy = []; $sprawdzone = 0; $pbl = false;
    foreach ([['DBL Spamhaus', 'dbl.spamhaus.org'], ['SURBL', 'multi.surbl.org']] as [$nazwa, $strefa]) {
        if (ep_pozostalo() < 4) { break; }
        $r = sp_dnsbl($dom . '.' . $strefa);
        if ($r['blad']) { $bledy[] = $nazwa; continue; }
        $sprawdzone++;
        if ($r['hit']) { $wpisy[] = $dom . ' w ' . $nazwa . ($nazwa === 'DBL Spamhaus' ? ' (' . sp_powod_dbl((string)$r['kod']) . ')' : ''); }
    }
    $mx = array_slice(dns_mx($dom), 0, 2);
    $ipSprawdzone = 0;
    foreach ($mx as $m) {
        if (ep_pozostalo() < 4) { break; }
        if ($m['host'] === '' || $m['host'] === '.') { continue; }
        $ip = dns_a($m['host'])[0] ?? '';
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { continue; }
        $odw = implode('.', array_reverse(explode('.', $ip)));
        foreach ([['Spamhaus ZEN', 'zen.spamhaus.org'], ['SpamCop', 'bl.spamcop.net'], ['Barracuda', 'b.barracudacentral.org']] as [$nazwa, $strefa]) {
            if (ep_pozostalo() < 3) { break 2; }
            $r = sp_dnsbl($odw . '.' . $strefa);
            if ($r['blad']) { $bledy[] = $nazwa; continue; }
            $sprawdzone++; $ipSprawdzone++;
            if ($r['hit']) {
                $pbl = $pbl || in_array($r['kod'], ['127.0.0.10', '127.0.0.11'], true);
                $wpisy[] = 'serwer poczty ' . $m['host'] . ' (' . $ip . ') w ' . $nazwa;
            }
        }
    }
    $bledy = array_values(array_unique($bledy));
    if ($wpisy) {
        $tylkoPbl = $pbl && count($wpisy) === 1;
        return pozycja('czarne-listy', 'Czarne listy', $tylkoPbl ? 'uwaga' : 'blad', count($wpisy) . ' ' . (count($wpisy) === 1 ? 'wpis' : 'wpisów'),
            'Znaleziono: ' . implode('; ', $wpisy) . '. Skrzynki odbiorców mogą kierować Twoje wiadomości do spamu albo je odrzucać.',
            $tylkoPbl ? 'Wpis w PBL Spamhaus oznacza adres, z którego nie powinno się wysyłać poczty bezpośrednio — wyślij pocztę przez serwer dostawcy (SMTP z logowaniem).'
                : 'Sprawdź przyczynę na check.spamhaus.org albo surbl.org/surbl-analysis, usuń ją (włamanie, wysyłka spamu, zainfekowany skrypt) i złóż wniosek o wykreślenie. Gdy to serwer współdzielony, napisz do dostawcy poczty.');
    }
    if ($sprawdzone === 0) {
        return pozycja('czarne-listy', 'Czarne listy', 'info', 'nie udało się sprawdzić',
            'Listy nie odpowiedziały z naszego serwera' . ($bledy ? ' (' . implode(', ', $bledy) . ' odrzuciły zapytanie)' : '') . ', więc nie ma wyniku.');
    }
    $opis = 'Domena' . ($ipSprawdzone ? ' i adresy serwerów poczty (' . count($mx) . ')' : '') . ' nie występują na sprawdzonych listach (' . $sprawdzone . ' zapytań).'
        . ($mx ? '' : ' Domena nie ma rekordów MX, więc adresów serwerów poczty nie sprawdzano.')
        . ($bledy ? ' Bez wyniku: ' . implode(', ', $bledy) . ' (lista odrzuciła zapytanie z naszego serwera).' : '');
    return pozycja('czarne-listy', 'Czarne listy', 'ok', 'czysto', $opis);
}

// ── Formularze ───────────────────────────────────────────────────────────────────────────────────────────────────
function sp_attr(DOMElement $e, string $a): string { return strtolower(trim($e->getAttribute($a))); }

/** Czy element (lub jego przodek w obrębie formularza) jest ukryty stylem/atrybutem. */
function sp_ukryty(DOMElement $e, DOMElement $form): bool {
    for ($n = $e; $n instanceof DOMElement; $n = $n->parentNode) {
        $st = (string)preg_replace('/\s+/', '', sp_attr($n, 'style'));
        if ($n->hasAttribute('hidden') || sp_attr($n, 'aria-hidden') === 'true'
            || preg_match('/display:none|visibility:hidden|opacity:0(;|$)|left:-\d{3,}|height:0(px)?(;|$)|width:0(px)?(;|$)/', $st)) { return true; }
        if ($n->isSameNode($form)) { break; }
    }
    return false;
}

/** Analiza formularza: ['ochrona'=>[...], 'komentarz'=>bool, 'wazny'=>bool]. */
function sp_formularz(DOMElement $f, DOMXPath $xp, string $htmlStrony, array $skrypty): array {
    $pola = $xp->query('.//input|.//textarea', $f);
    $tekstowe = 0; $mailTel = 0; $tekstarea = 0; $ochrona = [];
    $komentarz = false;
    foreach ($pola as $p) {
        if (!$p instanceof DOMElement) { continue; }
        $typ = $p->nodeName === 'textarea' ? 'textarea' : (sp_attr($p, 'type') ?: 'text');
        $nazwa = sp_attr($p, 'name') . ' ' . sp_attr($p, 'id');
        if (preg_match('/comment/', $nazwa)) { $komentarz = true; }
        if ($typ === 'textarea') { $tekstarea++; }
        if (in_array($typ, ['email', 'tel'], true)) { $mailTel++; }
        if ($typ === 'hidden') {
            if (preg_match('/^(t|ts|time|timestamp|czas|_?form_?(time|start|ts)|_?(started|render)_?(at|time)?|fp_?ts)$/', sp_attr($p, 'name'))) { $ochrona['czas'] = 'pole czasu'; }
            continue;
        }
        if (in_array($typ, ['submit', 'button', 'image', 'checkbox', 'radio', 'file', 'reset', 'search'], true)) { continue; }
        $tekstowe++;
        // pole-pułapka: tekstowe, niewidoczne, bez autouzupełniania / poza kolejnością tabulatora / o typowej nazwie
        $wzorzecNazwy = (bool)preg_match('/honey|hpot|(^|[_\-\s])hp([_\-\s]|$)|trap|nospam|leave.?blank|ak_hp|validation_container|wpforms-field-hp|^b_[0-9a-f]{16,}/', $nazwa . ' ' . sp_attr($p, 'class'));
        $slabe = in_array(sp_attr($p, 'autocomplete'), ['off', 'nope', 'new-password'], true) || sp_attr($p, 'tabindex') === '-1' || $wzorzecNazwy;
        if ($slabe && (sp_ukryty($p, $f) || ($wzorzecNazwy && sp_attr($p, 'tabindex') === '-1') || preg_match('/honey|hpot|^b_[0-9a-f]{16,}/', $nazwa))) {
            $ochrona['pulapka'] = 'pole-pułapka';
        }
    }
    $htmlF = strtolower((string)$f->ownerDocument->saveHTML($f));
    if (preg_match('/cf-turnstile|challenges\.cloudflare\.com/', $htmlF)) { $ochrona['captcha'] = 'Cloudflare Turnstile'; }
    elseif (preg_match('/g-recaptcha|grecaptcha|google\.com\/recaptcha/', $htmlF)) { $ochrona['captcha'] = 'reCAPTCHA'; }
    elseif (preg_match('/h-captcha|hcaptcha/', $htmlF)) { $ochrona['captcha'] = 'hCaptcha'; }
    elseif (preg_match('/data-sitekey|data-callback=/', $htmlF)) { $ochrona['captcha'] = $skrypty ? (string)reset($skrypty) : 'CAPTCHA'; }
    if (preg_match('/akismet|ak_js|ak_hp_textarea/', $htmlF)) { $ochrona['akismet'] = 'Akismet'; }
    $metoda = sp_attr($f, 'method');
    $akcja = sp_attr($f, 'action');
    $szukajka = sp_attr($f, 'role') === 'search' || $xp->query('.//input[@type="search"]', $f)->length > 0 || preg_match('/[\/?=]search|szukaj|wyszukiw/', $akcja . ' ' . sp_attr($f, 'id') . ' ' . sp_attr($f, 'class'));
    if (preg_match('/wp-comments-post|commentform/', $akcja . ' ' . sp_attr($f, 'id'))) { $komentarz = true; }
    $wazny = !$szukajka && $tekstowe > 0 && ($metoda === 'post' || $tekstarea > 0 || $mailTel > 0);
    return ['ochrona' => $ochrona, 'komentarz' => $komentarz, 'wazny' => $wazny];
}

function sp_formularze(array $ctx): array {
    [$d, $xp] = dom((string)$ctx['html']);
    $html = (string)$ctx['html'];
    // Skrypty CAPTCHA wczytane na stronie (nazwa dostawcy po adresie skryptu).
    $skrypty = [];
    if (preg_match('/challenges\.cloudflare\.com\/turnstile/i', $html)) { $skrypty['turnstile'] = 'Cloudflare Turnstile'; }
    if (preg_match('/(google\.com|gstatic\.com)\/recaptcha|grecaptcha/i', $html)) { $skrypty['recaptcha'] = 'reCAPTCHA'; }
    if (preg_match('/hcaptcha\.com/i', $html)) { $skrypty['hcaptcha'] = 'hCaptcha'; }
    $widzet = (bool)preg_match('/cf-turnstile|g-recaptcha|h-captcha|data-sitekey/i', $html);
    $wszystkie = $xp->query('//form');
    $wazne = 0; $bez = 0; $komBez = 0; $kom = 0; $komSlabe = 0; $rodzaje = [];
    foreach ($wszystkie as $f) {
        if (!$f instanceof DOMElement) { continue; }
        $a = sp_formularz($f, $xp, $html, $skrypty);
        // Skrypt CAPTCHA bez żadnego widżetu na stronie = ochrona programowa (np. reCAPTCHA v3) dla wszystkich formularzy.
        if ($skrypty && !$widzet && !isset($a['ochrona']['captcha'])) { $a['ochrona']['captcha'] = (string)reset($skrypty) . ' (skrypt na stronie)'; }
        if ($a['komentarz']) {
            $kom++;
            if (!isset($a['ochrona']['captcha']) && !isset($a['ochrona']['akismet'])) { $komBez++; $komSlabe += isset($a['ochrona']['pulapka']) ? 1 : 0; }
        }
        if (!$a['wazny'] && !$a['komentarz']) { continue; }
        $wazne++;
        if (!$a['ochrona']) { $bez++; }
        foreach ($a['ochrona'] as $r) { $rodzaje[$r] = true; }
    }
    // Komentarze zewnętrzne (Disqus) mają własną moderację.
    $disqus = (bool)preg_match('/disqus_thread|disqus\.com\/embed/i', $html);

    $wsp = ['wszystkie' => $wszystkie->length, 'wazne' => $wazne, 'bez' => $bez, 'rodzaje' => array_keys($rodzaje)];
    if ($wazne === 0) {
        $form = pozycja('formularze', 'Formularze', 'info', 'brak formularzy', 'Na stronie głównej nie ma formularza z polami tekstowymi, więc nie ma przez co wysyłać spamu.');
    } elseif ($bez > 0) {
        $form = pozycja('formularze', 'Formularze', 'uwaga', $wazne . ' ' . sp_odm($wazne, 'formularz', 'formularze', 'formularzy') . ', bez ochrony: ' . $bez,
            ($bez === 1 ? 'Jeden formularz nie ma' : $bez . ' formularze nie mają') . ' żadnego zabezpieczenia (CAPTCHA, pola-pułapki ani pola czasu), więc roboty mogą zalewać Cię wiadomościami.'
            . ($rodzaje ? ' Zabezpieczenia gdzie indziej: ' . implode(', ', array_keys($rodzaje)) . '.' : ''),
            'Dodaj Cloudflare Turnstile (darmowy, zwykle bez łamigłówek: dash.cloudflare.com → Turnstile → dodaj widżet, wklej fragment do formularza i sprawdzaj token po stronie serwera) oraz ukryte pole-pułapkę (niewidoczne pole tekstowe z autocomplete="off"; jeśli ktoś je wypełni, odrzuć wiadomość).');
    } else {
        $form = pozycja('formularze', 'Formularze', 'ok', $wazne . ' ' . sp_odm($wazne, 'formularz', 'formularze', 'formularzy') . ', wszystkie zabezpieczone',
            'Wykryte zabezpieczenia: ' . implode(', ', array_keys($rodzaje)) . '.');
    }
    if ($kom === 0) {
        $kompoz = pozycja('komentarze', 'Komentarze', 'info', $disqus ? 'komentarze zewnętrzne' : 'brak', $disqus ? 'Komentarze obsługuje zewnętrzny system (Disqus) z własną moderacją.' : 'Na stronie głównej nie ma sekcji komentarzy.');
    } elseif ($komBez > 0) {
        $kompoz = pozycja('komentarze', 'Komentarze', 'uwaga', 'bez CAPTCHA',
            'Formularz komentarzy' . ($komSlabe ? ' ma tylko pole-pułapkę, a to słaba' : ' nie ma żadnej') . ' ochrony przed robotami — to ulubiony cel spamerów (linki do podejrzanych stron).',
            'Włącz Akismet albo CAPTCHA przy komentarzach (Turnstile jest darmowy) i ustaw moderację: komentarz najpierw czeka na zatwierdzenie.');
    } else {
        $kompoz = pozycja('komentarze', 'Komentarze', 'ok', 'chronione', 'Formularz komentarzy ma CAPTCHA albo Akismet.');
    }
    return [$form, $kompoz, $wsp];
}

function sp_odm(int $n, string $a, string $b, string $c): string {
    return $n === 1 ? $a : (($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14)) ? $b : $c);
}

// ── Adresy e-mail gołym tekstem ──────────────────────────────────────────────────────────────────────────────────
function sp_maile(string $html): array {
    $czysty = (string)preg_replace(['~<!--.*?-->~s', '~<(script|style)\b.*?</\1>~is'], ' ', $html);
    $adresy = [];
    $dodaj = static function (string $m) use (&$adresy): void {
        $m = strtolower(trim(html_entity_decode(rawurldecode($m), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (!preg_match('/^[a-z0-9._%+\-]{1,64}@([a-z0-9\-]+\.)+([a-z]{2,24})$/', $m, $x)) { return; }
        if (in_array($x[2], ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'avif', 'css', 'js', 'ico', 'woff', 'woff2'], true)) { return; }
        if (preg_match('/@(example\.(com|org|net)|domain\.(com|pl)|email\.com|test\.com|sentry\.)/', $m) || preg_match('/\.(wixpress|sentry)\./', $m)) { return; }
        $adresy[$m] = true;
    };
    if (preg_match_all('/mailto:([^"\'?#\s>]+)/i', $czysty, $m)) { foreach ($m[1] as $a) { $dodaj($a); } }
    $tekst = html_entity_decode(strip_tags((string)preg_replace('/<[^>]+>/', ' ', $czysty)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match_all('/[A-Za-z0-9._%+\-]{1,64}@(?:[A-Za-z0-9\-]+\.)+[A-Za-z]{2,24}/', $tekst, $m)) { foreach ($m[0] as $a) { $dodaj($a); } }
    return array_keys($adresy);
}
function sp_maska(string $adres): string {
    [$l, $d] = explode('@', $adres, 2) + ['', ''];
    return mb_substr($l, 0, 2) . '***@' . $d;
}
function sp_email(array $ctx): array {
    $html = (string)$ctx['html'];
    $adresy = sp_maile($html);
    $zasloniete = (bool)preg_match('/data-cfemail|cdn-cgi\/l\/email-protection/i', $html);
    if (!$adresy) {
        return [pozycja('email-w-html', 'Adresy e-mail w kodzie strony', 'ok', 'brak', 'W kodzie strony głównej nie ma adresów e-mail widocznych gołym tekstem.'
            . ($zasloniete ? ' Adresy są zasłonięte przed robotami.' : '')), $adresy];
    }
    $n = count($adresy);
    $pokaz = implode(', ', array_map('sp_maska', array_slice($adresy, 0, 3)));
    return [pozycja('email-w-html', 'Adresy e-mail w kodzie strony', 'uwaga', $n . ' ' . sp_odm($n, 'adres', 'adresy', 'adresów'),
        'W kodzie strony widać gołym tekstem: ' . $pokaz . ($n > 3 ? ' i ' . ($n - 3) . ' kolejne' : '') . '. Roboty zbierają takie adresy i wysyłają na nie spam.',
        'Zamiast adresu wstaw formularz kontaktowy albo zapisz adres obrazkiem lub skryptem składającym go w przeglądarce (nie jako zwykły tekst ani mailto:).'), $adresy];
}

// ── security.txt i adresy do zgłoszeń nadużyć ────────────────────────────────────────────────────────────────────
function sp_security(array $ctx, array $maile, string $dom): array {
    $r = ep_pozostalo() > 3 ? pobierz_tekst(zrodlo_url($ctx, '/.well-known/security.txt'), 20000, 3) : ['ok' => false, 'tekst' => '', 'typ' => ''];
    $jest = $r['ok'] && stripos((string)$r['tekst'], 'contact:') !== false && stripos((string)$r['typ'], 'html') === false;
    $abuse = [];
    foreach ($maile as $m) { if (preg_match('/^(abuse|security)@/', $m)) { $abuse[] = $m; } }
    if ($jest && preg_match('/contact:\s*(?:mailto:)?\s*(\S+)/i', (string)$r['tekst'], $x)) {
        return pozycja('security-txt', 'Kontakt do zgłaszania nadużyć', 'info', 'security.txt jest', 'Plik /.well-known/security.txt wskazuje kontakt: ' . ep_skroc($x[1], 120) . '. Tak ludzie i firmy wiedzą, gdzie zgłosić problem.');
    }
    if ($abuse) {
        return pozycja('security-txt', 'Kontakt do zgłaszania nadużyć', 'info', 'jest ' . $abuse[0], 'Na stronie jest adres do zgłoszeń (' . $abuse[0] . '), ale brakuje pliku /.well-known/security.txt.');
    }
    return pozycja('security-txt', 'Kontakt do zgłaszania nadużyć', 'info', 'brak', 'Brak pliku /.well-known/security.txt i adresu abuse@ lub security@. To nie jest błąd, ale ułatwia zgłaszanie nadużyć i wykreślanie z czarnych list.',
        'Dodaj plik /.well-known/security.txt z linijkami „Contact: mailto:security@' . $dom . '” i „Expires: <data za rok>” oraz załóż skrzynkę abuse@' . $dom . '.');
}

// ── Główna funkcja ───────────────────────────────────────────────────────────────────────────────────────────────
function test_ochrona_przed_spamem(string $url, array $ctx): array {
    $host = (string)$ctx['host'];
    $domenaPoczty = !filter_var($host, FILTER_VALIDATE_IP);
    $dom = $domenaPoczty ? (function_exists('dns_domena_poczty') ? dns_domena_poczty($host) : domena_poczty($host)) : $host;
    $ip = static fn(string $id, string $n): array => pozycja($id, $n, 'info', 'adres IP', 'Strona jest pod adresem IP, poczty domenowej nie sprawdzano.');

    $spf = $domenaPoczty ? sp_spf($dom) : $ip('spf', 'SPF');
    $dkim = $domenaPoczty ? sp_dkim($dom) : $ip('dkim', 'DKIM');
    $dmarc = $domenaPoczty ? sp_dmarc($dom) : $ip('dmarc', 'DMARC');
    foreach ([&$spf, &$dkim, &$dmarc] as &$p) { $p['opis'] = trim($p['opis'] . ' Szczegóły: narzędzie „Poczta domeny” (/narzedzia/poczta-domeny/).'); }
    unset($p);

    $czarne = $domenaPoczty ? sp_czarne_listy($ctx, $dom) : pozycja('czarne-listy', 'Czarne listy', 'info', 'adres IP', 'Strona jest pod adresem IP, więc nie ma domeny do sprawdzenia na czarnych listach.');
    [$form, $kom, $wsp] = sp_formularze($ctx);
    [$email, $maile] = sp_email($ctx);
    $sec = sp_security($ctx, $maile, $dom);

    $poz = [$spf, $dkim, $dmarc, $czarne, $form, $kom, $email, $sec];
    $ocena = ocena_z_pozycji($poz, SP_WAGI);

    // Werdykt zbiorczy: informacja (nie wchodzi do oceny), trzy strefy jak zegar.
    $ile = static fn(string $s): int => count(array_filter($poz, static fn($p) => $p['stan'] === $s));
    if ($ocena === null) { $w = ['brak', 'Nie udało się ocenić.']; }
    elseif ($ocena >= 90) { $w = ['Tak', 'Podstawowe zabezpieczenia przed spamem i podszywaniem się są na miejscu.']; }
    elseif ($ocena >= 50) { $w = ['Częściowo', 'Część zabezpieczeń działa, ale ' . ($ile('blad') + $ile('uwaga')) . ' z nich wymaga poprawy.']; }
    else { $w = ['Nie', 'Brakuje ważnych zabezpieczeń: roboty mogą zalewać formularz, a ktoś może podszyć się pod Twoją domenę.']; }
    array_unshift($poz, pozycja('werdykt', 'Czy jesteś zabezpieczony przed spamem?', 'info', $w[0] . ($ocena !== null ? ' (' . $ocena . '/100)' : ''), $w[1]));

    return ['ocena' => $ocena, 'pozycje' => $poz, 'surowe' => ['formularze' => $wsp, 'adresy_email' => count($maile)]];
}
