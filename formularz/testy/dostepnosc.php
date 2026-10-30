<?php
declare(strict_types=1);
/**
 * Dostępność: sprawdzenia z kodu HTML strony (bez przeglądarki). Kontrast i pełną ocenę kategorii dodaje w przeglądarce
 * Google PageSpeed (tryb „oba”). Identyfikatory zgodne z audytami Lighthouse (image-alt, label, link-name, button-name,
 * html-has-lang, heading-order, document-title): wynik serwera ma pierwszeństwo, a gdy strona buduje treść skryptem
 * (pusty HTML), moduł tych pozycji nie zwraca i uzupełnia je PSI.
 */
if (!defined('AUDYT_EP')) { http_response_code(404); exit; }

/** Tekst widoczny dla czytnika ekranu: pomija aria-hidden/hidden/script/style, obrazy dają alt, SVG tytuł lub aria-label. */
function dost_tekst(DOMNode $w, int $g = 0): string {
    if ($g > 25) { return ''; }
    $t = '';
    foreach ($w->childNodes as $n) {
        if ($n instanceof DOMText) { $t .= ' ' . $n->nodeValue; continue; }
        if (!($n instanceof DOMElement)) { continue; }
        $nazwa = strtolower($n->nodeName);
        if (in_array($nazwa, ['script', 'style', 'noscript', 'template'], true)
            || strtolower($n->getAttribute('aria-hidden')) === 'true' || $n->hasAttribute('hidden')) { continue; }
        if ($nazwa === 'img') { $t .= ' ' . $n->getAttribute('alt'); continue; }
        $al = trim($n->getAttribute('aria-label'));
        if ($al !== '') { $t .= ' ' . $al; continue; }
        if ($nazwa === 'svg') {
            foreach ($n->childNodes as $c) { if ($c instanceof DOMElement && strtolower($c->nodeName) === 'title') { $t .= ' ' . $c->textContent; } }
            continue;
        }
        $t .= ' ' . dost_tekst($n, $g + 1);
    }
    return trim((string)preg_replace('/\s+/u', ' ', $t));
}

/** Nazwa dostępna elementu: aria-labelledby, aria-label, treść, title (kolejność jak w przeglądarce, uproszczona). */
function dost_nazwa(DOMElement $e, DOMXPath $xp): string {
    $by = trim($e->getAttribute('aria-labelledby'));
    if ($by !== '') {
        $t = '';
        foreach (preg_split('/\s+/', $by) ?: [] as $id) {
            if (!preg_match('/^[A-Za-z0-9_:.-]+$/', $id)) { continue; }
            $r = $xp->query('//*[@id="' . $id . '"]');
            if ($r && $r->length) { $t .= ' ' . $r->item(0)->textContent; }
        }
        $t = trim((string)preg_replace('/\s+/u', ' ', $t));
        if ($t !== '') { return $t; }
    }
    $al = trim($e->getAttribute('aria-label'));
    if ($al !== '') { return $al; }
    $t = dost_tekst($e);
    if ($t !== '') { return $t; }
    return trim($e->getAttribute('title'));
}

function dost_ukryty(DOMElement $e): bool {
    $st = strtolower((string)preg_replace('/\s+/', '', $e->getAttribute('style')));
    return strtolower($e->getAttribute('aria-hidden')) === 'true' || $e->hasAttribute('hidden')
        || str_contains($st, 'display:none') || str_contains($st, 'visibility:hidden');
}

/** Stan z liczby braków: 0 → ok, do 10 % → uwaga, więcej → błąd. */
function dost_stan(int $brak, int $razem): string {
    return $brak === 0 ? 'ok' : ($brak * 10 <= $razem ? 'uwaga' : 'blad');
}

function test_dostepnosc(string $url, array $ctx): array {
    $html = (string)$ctx['html'];
    [$d, $xp] = dom($html);
    $poz = [];

    // ── język strony ──
    $h = $xp->query('//html')->item(0);
    $lang = $h instanceof DOMElement ? trim($h->getAttribute('lang')) : '';
    $cialo = $xp->query('//body')->item(0);
    $tresc = $cialo ? dost_tekst($cialo) : '';
    $liter = preg_match_all('/\pL/u', $tresc);
    $pl = $liter > 200 && preg_match_all('/[ąćęłńóśźż]/iu', $tresc) / $liter > 0.02;
    if ($lang === '') {
        $poz[] = pozycja('html-has-lang', 'Język strony', 'blad', 'brak',
            'Strona nie podaje języka, więc czytnik ekranu może czytać polski tekst z obcym akcentem.',
            'Ustaw język strony na pl (znacznik html lang="pl"); w panelu treści lub szablonie.');
    } elseif (!preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $lang)) {
        $poz[] = pozycja('html-has-lang', 'Język strony', 'blad', $lang,
            'Zapis języka „' . $lang . '” jest nieprawidłowy, więc czytnik ekranu go nie rozpozna.', 'Wpisz kod języka, np. pl albo pl-PL.');
    } elseif (!preg_match('/^pl(-|$)/i', $lang) && $pl) {
        $poz[] = pozycja('html-has-lang', 'Język strony', 'blad', $lang,
            'Strona wygląda na polską, a ustawiono język „' . $lang . '”; czytnik ekranu przeczyta ją z obcą wymową.', 'Zmień język strony na pl.');
    } elseif (!preg_match('/^pl(-|$)/i', $lang)) {
        $poz[] = pozycja('html-has-lang', 'Język strony', 'uwaga', $lang,
            'Język strony to „' . $lang . '”. Jeśli strona jest po polsku, czytnik ekranu będzie ją czytał błędnie.', 'Jeśli treść jest polska, ustaw język strony na pl.');
    } else {
        $poz[] = pozycja('html-has-lang', 'Język strony', 'ok', $lang, 'Język strony jest ustawiony poprawnie (' . $lang . ').');
    }

    // ── viewport ──
    $vp = '';
    foreach ($xp->query('//meta[@name]') as $m) {
        if (strtolower(trim($m->getAttribute('name'))) === 'viewport') { $vp = strtolower((string)preg_replace('/\s+/', '', $m->getAttribute('content'))); break; }
    }
    $blok = $vp !== '' && (preg_match('/user-scalable=(no|0)/', $vp) || (preg_match('/maximum-scale=([0-9.]+)/', $vp, $mm) && (float)$mm[1] < 2));
    if ($vp === '') {
        $poz[] = pozycja('viewport', 'Widok na telefonie i powiększanie', 'blad', 'brak',
            'Strona nie ma ustawienia widoku mobilnego, więc na telefonie wyświetla się jako zmniejszony pulpit.',
            'Dodaj w nagłówku strony znacznik meta viewport z width=device-width, initial-scale=1.');
    } elseif ($blok) {
        $poz[] = pozycja('viewport', 'Widok na telefonie i powiększanie', 'blad', 'zablokowane powiększanie',
            'Strona blokuje powiększanie palcami, a osoby słabowidzące nie przeczytają drobnego tekstu.',
            'Usuń z meta viewport wpisy user-scalable=no oraz maximum-scale=1.');
    } else {
        $poz[] = pozycja('viewport', 'Widok na telefonie i powiększanie', 'ok', 'poprawnie', 'Strona dopasowuje się do telefonu i pozwala powiększać tekst.');
    }

    // ── tytuł ──
    $ti = $xp->query('//title')->item(0);
    $tytul = $ti ? trim((string)preg_replace('/\s+/u', ' ', $ti->textContent)) : '';
    $poz[] = $tytul === ''
        ? pozycja('document-title', 'Tytuł strony', 'blad', 'brak', 'Strona nie ma tytułu; czytnik ekranu i karta przeglądarki nie mówią, gdzie jesteś.', 'Dodaj tytuł strony z nazwą firmy i tematem.')
        : pozycja('document-title', 'Tytuł strony', 'ok', mb_strlen($tytul) . ' znaków', 'Strona ma tytuł: ' . $tytul);

    // ── treść budowana skryptem? wtedy zliczeń z HTML nie wiążemy (uzupełnia je PSI z wykonanej strony) ──
    $skryptem = mb_strlen($tresc) < 80 && $xp->query('//img|//a[@href]')->length < 3 && $xp->query('//script')->length > 0;
    if ($skryptem) {
        $poz[] = pozycja('tresc-skryptem', 'Treść budowana skryptem', 'info', 'tak',
            'Strona dopiero w przeglądarce buduje swoją treść, więc zliczenia obrazów, linków i pól podaje Google (poniżej), jeśli odpowie.');
        $poz[] = dost_ustawa();
        return ['ocena' => ocena_z_pozycji($poz, dost_wagi()), 'pozycje' => $poz, 'surowe' => ['skryptem' => true]];
    }

    // ── obrazy bez alt ──
    $razem = 0; $brak = 0;
    foreach ($xp->query('//img') as $im) {
        if (!($im instanceof DOMElement)) { continue; }
        $razem++;
        $rola = strtolower($im->getAttribute('role'));
        if (!$im->hasAttribute('alt') && $rola !== 'presentation' && $rola !== 'none' && strtolower($im->getAttribute('aria-hidden')) !== 'true'
            && trim($im->getAttribute('aria-label')) === '' && trim($im->getAttribute('title')) === '') { $brak++; }
    }
    $poz[] = $razem === 0
        ? pozycja('image-alt', 'Opisy zdjęć (alt)', 'info', 'brak zdjęć', 'Na stronie nie znaleziono zdjęć w kodzie HTML.')
        : pozycja('image-alt', 'Opisy zdjęć (alt)', dost_stan($brak, $razem), $brak === 0 ? 'wszystkie opisane' : $brak . ' z ' . $razem . ' bez opisu',
            $brak === 0 ? 'Wszystkie zdjęcia (' . $razem . ') mają opis; niewidomi i Google wiedzą, co przedstawiają.'
                : 'Zdjęcia bez opisu: ' . $brak . ' z ' . $razem . ' — osoby niewidome i Google nie wiedzą, co przedstawiają.',
            $brak === 0 ? '' : 'Dopisz krótki opis do każdego zdjęcia w panelu treści (jedno zdanie: co jest na zdjęciu).');

    // ── linki bez tekstu ──
    $razem = 0; $brak = 0;
    foreach ($xp->query('//a[@href]') as $a) {
        if (!($a instanceof DOMElement) || dost_ukryty($a) || strtolower($a->getAttribute('role')) === 'presentation') { continue; }
        $razem++;
        if (dost_nazwa($a, $xp) === '') { $brak++; }
    }
    $poz[] = $razem === 0
        ? pozycja('link-name', 'Linki z opisem', 'info', 'brak linków', 'Na stronie nie znaleziono linków.')
        : pozycja('link-name', 'Linki z opisem', dost_stan($brak, $razem), $brak === 0 ? 'wszystkie opisane' : $brak . ' z ' . $razem . ' bez tekstu',
            $brak === 0 ? 'Każdy link (' . $razem . ') ma tekst, który czytnik ekranu może odczytać.'
                : 'Linki bez tekstu: ' . $brak . ' z ' . $razem . ' — to zwykle ikony (np. Facebook, telefon), które dla niewidomych są „linkiem bez nazwy”.',
            $brak === 0 ? '' : 'Dodaj do ikon-linków ukryty opis (aria-label), np. „Facebook” albo „Zadzwoń”.');

    // ── przyciski bez tekstu ──
    $razem = 0; $brak = 0;
    foreach ($xp->query('//button|//input[@type]|//*[@role="button"]') as $b) {
        if (!($b instanceof DOMElement) || dost_ukryty($b)) { continue; }
        $n = strtolower($b->nodeName);
        $typ = strtolower($b->getAttribute('type'));
        if ($n === 'input') {
            if (!in_array($typ, ['button', 'submit', 'reset', 'image'], true)) { continue; }
            $razem++;
            if ($typ === 'submit' || $typ === 'reset') { continue; }
            $nazwa = trim($b->getAttribute('aria-label')) . trim($b->getAttribute('title')) . trim($b->getAttribute('value')) . ($typ === 'image' ? trim($b->getAttribute('alt')) : '');
            if ($nazwa === '') { $brak++; }
            continue;
        }
        if ($n !== 'button' && strtolower($b->getAttribute('role')) !== 'button') { continue; }
        $razem++;
        if (dost_nazwa($b, $xp) === '') { $brak++; }
    }
    $poz[] = $razem === 0
        ? pozycja('button-name', 'Przyciski z opisem', 'info', 'brak przycisków', 'Na stronie nie znaleziono przycisków.')
        : pozycja('button-name', 'Przyciski z opisem', dost_stan($brak, $razem), $brak === 0 ? 'wszystkie opisane' : $brak . ' z ' . $razem . ' bez tekstu',
            $brak === 0 ? 'Każdy przycisk (' . $razem . ') ma nazwę, którą odczyta czytnik ekranu.'
                : 'Przyciski bez tekstu: ' . $brak . ' z ' . $razem . ' — np. sama ikona menu, koszyka albo lupy; niewidomi nie wiedzą, do czego służą.',
            $brak === 0 ? '' : 'Dodaj do przycisków-ikon opis (aria-label), np. „Menu”, „Szukaj”.');

    // ── pola formularza bez etykiety ──
    $etykiety = [];
    foreach ($xp->query('//label[@for]') as $l) {
        if ($l instanceof DOMElement && dost_tekst($l) !== '') { $etykiety[$l->getAttribute('for')] = true; }
    }
    $razem = 0; $brak = 0;
    foreach ($xp->query('//input|//select|//textarea') as $f) {
        if (!($f instanceof DOMElement) || dost_ukryty($f)) { continue; }
        $typ = strtolower($f->getAttribute('type'));
        if (in_array($typ, ['hidden', 'submit', 'button', 'reset', 'image'], true) || strtolower($f->getAttribute('role')) === 'presentation') { continue; }
        if ($f->getAttribute('tabindex') === '-1' && strtolower($f->getAttribute('autocomplete')) === 'off' && $typ !== 'checkbox') { continue; } // pułapka na boty (honeypot)
        $razem++;
        $id = $f->getAttribute('id');
        $ma = trim($f->getAttribute('aria-label')) !== '' || trim($f->getAttribute('aria-labelledby')) !== '' || trim($f->getAttribute('title')) !== ''
            || ($id !== '' && isset($etykiety[$id]));
        if (!$ma) {
            for ($p = $f->parentNode, $i = 0; $p instanceof DOMElement && $i < 6; $p = $p->parentNode, $i++) {
                if (strtolower($p->nodeName) === 'label' && dost_tekst($p) !== '') { $ma = true; break; }
            }
        }
        if (!$ma) { $brak++; }
    }
    $poz[] = $razem === 0
        ? pozycja('label', 'Opisy pól formularza', 'info', 'brak pól', 'Na stronie nie znaleziono pól formularza.')
        : pozycja('label', 'Opisy pól formularza', dost_stan($brak, $razem), $brak === 0 ? 'wszystkie opisane' : $brak . ' z ' . $razem . ' bez etykiety',
            $brak === 0 ? 'Każde pole formularza (' . $razem . ') ma etykietę, więc wiadomo, co w nie wpisać.'
                : 'Pola bez etykiety: ' . $brak . ' z ' . $razem . ' — osoba z czytnikiem ekranu nie wie, co wpisać (sam napis wewnątrz pola nie wystarcza).',
            $brak === 0 ? '' : 'Dodaj do każdego pola widoczny podpis (znacznik label) albo przynajmniej aria-label.');

    // ── nagłówki ──
    $h1 = 0; $skoki = 0; $poprz = 0; $nagl = 0;
    foreach ($xp->query('//h1|//h2|//h3|//h4|//h5|//h6') as $n) {
        if (!($n instanceof DOMElement) || dost_ukryty($n)) { continue; }
        $nagl++;
        $p = (int)$n->nodeName[1];
        if ($p === 1) { $h1++; }
        if ($poprz > 0 && $p > $poprz + 1) { $skoki++; }
        $poprz = $p;
    }
    $poz[] = $h1 === 1
        ? pozycja('h1', 'Główny nagłówek (H1)', 'ok', '1', 'Strona ma jeden główny nagłówek, więc od razu wiadomo, o czym jest.')
        : ($h1 === 0
            ? pozycja('h1', 'Główny nagłówek (H1)', 'blad', 'brak', 'Strona nie ma głównego nagłówka; czytnik ekranu i Google nie wiedzą, o czym ona jest.', 'Dodaj jeden nagłówek H1 z tematem strony.')
            : pozycja('h1', 'Główny nagłówek (H1)', 'uwaga', (string)$h1, 'Strona ma ' . $h1 . ' główne nagłówki; powinien być jeden.', 'Zostaw jeden H1, pozostałe zamień na nagłówki niższego poziomu (H2).'));
    $poz[] = $nagl === 0
        ? pozycja('heading-order', 'Kolejność nagłówków', 'info', 'brak nagłówków', 'Na stronie nie znaleziono nagłówków.')
        : ($skoki === 0
            ? pozycja('heading-order', 'Kolejność nagłówków', 'ok', 'bez przeskoków', 'Nagłówki idą po kolei (H1, H2, H3…), więc łatwo poruszać się po stronie.')
            : pozycja('heading-order', 'Kolejność nagłówków', 'uwaga', $skoki . ' przeskoków', 'Nagłówki przeskakują poziomy (np. z H2 od razu na H4): ' . $skoki . '×; czytnik ekranu gubi strukturę strony.', 'Nie pomijaj poziomów: po H2 następuje H3, nie H4.'));

    // ── landmarki i link „przejdź do treści” ──
    $zn = [];
    foreach (['main' => '//main|//*[@role="main"]', 'nawigacja' => '//nav|//*[@role="navigation"]', 'nagłówek' => '//header|//*[@role="banner"]', 'stopka' => '//footer|//*[@role="contentinfo"]'] as $k => $q) {
        if ($xp->query($q)->length) { $zn[] = $k; }
    }
    $poz[] = in_array('main', $zn, true)
        ? pozycja('landmarki', 'Obszary strony (main, nawigacja)', 'ok', implode(', ', $zn), 'Strona ma wyznaczoną treść główną (' . implode(', ', $zn) . '), więc czytnik ekranu może do niej przeskoczyć.')
        : pozycja('landmarki', 'Obszary strony (main, nawigacja)', 'uwaga', $zn ? implode(', ', $zn) . ' (bez main)' : 'brak', 'Strona nie oznacza treści głównej znacznikiem main, więc niewidomi muszą czytać całe menu przy każdej stronie.',
            'Zamknij treść główną strony w znaczniku main (raz na stronę).');
    $skip = false;
    $i = 0;
    foreach ($xp->query('//a[starts-with(@href,"#") and string-length(@href)>1]') as $a) {
        if ($i++ >= 8) { break; }
        if (!($a instanceof DOMElement)) { continue; }
        if (preg_match('/przejd|pomi[ńn]|do\s+tre|skip|main\s+content|zawarto/iu', dost_nazwa($a, $xp))) {
            $cel = substr($a->getAttribute('href'), 1);
            if (preg_match('/^[A-Za-z0-9_:.-]+$/', $cel) && $xp->query('//*[@id="' . $cel . '"]')->length) { $skip = true; break; }
        }
    }
    $poz[] = $skip
        ? pozycja('przejdz-do-tresci', 'Link „Przejdź do treści”', 'ok', 'jest', 'Strona ma pierwszy link „Przejdź do treści”; osoby obsługujące stronę klawiaturą omijają menu jednym kliknięciem.')
        : pozycja('przejdz-do-tresci', 'Link „Przejdź do treści”', 'uwaga', 'brak', 'Strona nie ma linku „Przejdź do treści” na początku; użytkownik klawiatury musi przeklikać całe menu przy każdej stronie.',
            'Dodaj na początku strony link „Przejdź do treści” prowadzący do znacznika main.');

    // ── tabindex > 0 i autoplay ──
    $tab = 0;
    foreach ($xp->query('//*[@tabindex]') as $e) {
        if ($e instanceof DOMElement && (int)$e->getAttribute('tabindex') > 0) { $tab++; }
    }
    $poz[] = $tab === 0
        ? pozycja('tabindex', 'Własna kolejność klawisza Tab', 'info', 'brak', 'Strona nie narzuca własnej kolejności przechodzenia klawiszem Tab, i dobrze.')
        : pozycja('tabindex', 'Własna kolejność klawisza Tab', 'info', $tab . ' elementów', 'Na stronie jest ' . $tab . ' elementów z dodatnim tabindex; to zwykle myli osoby obsługujące stronę klawiaturą.', 'Usuń dodatnie wartości tabindex, kolejność ma wynikać z układu strony.');
    $auto = 0; $bezdzw = 0;
    foreach ($xp->query('//video[@autoplay]|//audio[@autoplay]') as $e) {
        if (!($e instanceof DOMElement)) { continue; }
        $auto++;
        if ($e->hasAttribute('muted')) { $bezdzw++; }
    }
    $poz[] = $auto === 0
        ? pozycja('autoplay', 'Samoczynnie odtwarzane wideo i dźwięk', 'ok', 'brak', 'Na stronie nic nie rusza się ani nie gra samo.')
        : ($auto === $bezdzw
            ? pozycja('autoplay', 'Samoczynnie odtwarzane wideo i dźwięk', 'info', $auto . ' bez dźwięku', 'Na stronie samoczynnie rusza ' . $auto . ' wideo bez dźwięku; dobrze, jeśli da się je zatrzymać.', 'Dodaj przycisk zatrzymania albo wyłącz samoczynne odtwarzanie.')
            : pozycja('autoplay', 'Samoczynnie odtwarzane wideo i dźwięk', 'uwaga', $auto . ' z dźwiękiem', 'Wideo albo dźwięk włączają się same (' . $auto . '×); to przeszkadza osobom z czytnikiem ekranu i wielu innym.', 'Wyłącz samoczynne odtwarzanie albo wycisz dźwięk i dodaj przycisk zatrzymania.'));
    $poz[] = dost_ustawa();

    return ['ocena' => ocena_z_pozycji($poz, dost_wagi()), 'pozycje' => $poz, 'surowe' => ['naglowki' => $nagl, 'landmarki' => $zn]];
}

function dost_ustawa(): array {
    return pozycja('ustawa', 'Obowiązek prawny dostępności', 'info', 'informacja',
        'Od 28.06.2025 ustawa o zapewnianiu spełniania wymagań dostępności niektórych produktów i usług obejmuje m.in. sklepy internetowe i usługi online; mikroprzedsiębiorcy (poniżej 10 osób, obrót do 2 mln euro) świadczący usługi są z niej wyłączeni.');
}

function dost_wagi(): array {
    return ['image-alt' => 2, 'label' => 2, 'link-name' => 2, 'button-name' => 2, 'viewport' => 2, 'html-has-lang' => 1, 'heading-order' => 1,
        'h1' => 1, 'document-title' => 1, 'landmarki' => 1, 'przejdz-do-tresci' => 1, 'autoplay' => 1];
}
