<?php
declare(strict_types=1);
/**
 * Licznik użyć darmowych narzędzi (www.ecopowerpolska.pl/darmowe-narzedzia/).
 * 1) Biblioteka: audyt.php woła licznik_zlicz($katStan) przy każdym udanym sprawdzeniu (także z pamięci podręcznej).
 * 2) Punkt końcowy: GET licznik.php → {"dzis":N,"razem":M}; CORS tylko dla https://www.ecopowerpolska.pl.
 * Zapis: stan/licznik-RRRR-MM-DD.json {"n":N} i stan/licznik-razem.json {"n":M}. Bez adresów IP, adresów stron i nazw testów.
 * Zapis atomowy: blokada stan/licznik.lock (flock), plik tymczasowy + rename, prawa 0600.
 */

const LICZNIK_STREFA = 'Europe/Warsaw';
const LICZNIK_ORIGIN = 'https://www.ecopowerpolska.pl';
const LICZNIK_DNI = 400; // pliki dzienne starsze niż tyle dni są kasowane

function licznik_dzien(): string {
    return (new DateTimeImmutable('now', new DateTimeZone(LICZNIK_STREFA)))->format('Y-m-d');
}

function licznik_odczyt(string $plik): int {
    $d = is_file($plik) ? json_decode((string)@file_get_contents($plik), true) : null;
    return is_array($d) ? max(0, (int)($d['n'] ?? 0)) : 0;
}

function licznik_zapisz(string $plik, int $n): void {
    $tmp = $plik . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, json_encode(['n' => $n])) === false) { return; }
    @chmod($tmp, 0600);
    if (!@rename($tmp, $plik)) { @unlink($tmp); }
}

/** +1 dla dnia i sumy. Błąd zapisu nigdy nie psuje odpowiedzi audytu. */
function licznik_zlicz(string $katStan): void {
    try {
        if (!is_dir($katStan)) { @mkdir($katStan, 0700, true); }
        $blokada = @fopen($katStan . '/licznik.lock', 'c');
        if ($blokada === false || !flock($blokada, LOCK_EX)) {
            if ($blokada !== false) { fclose($blokada); }
            error_log('licznik-ecopower: blokada niedostępna');
            return;
        }
        $dzien = $katStan . '/licznik-' . licznik_dzien() . '.json';
        $razem = $katStan . '/licznik-razem.json';
        $nowyDzien = !is_file($dzien);
        licznik_zapisz($dzien, licznik_odczyt($dzien) + 1);
        licznik_zapisz($razem, licznik_odczyt($razem) + 1);
        if ($nowyDzien) {
            $granica = (new DateTimeImmutable('now', new DateTimeZone(LICZNIK_STREFA)))->modify('-' . LICZNIK_DNI . ' days')->format('Y-m-d');
            foreach (glob($katStan . '/licznik-????-??-??.json') ?: [] as $p) {
                if (substr(basename($p), 8, 10) < $granica) { @unlink($p); }
            }
        }
        @chmod($katStan . '/licznik.lock', 0600);
        flock($blokada, LOCK_UN);
        fclose($blokada);
    } catch (Throwable $e) {
        error_log('licznik-ecopower: ' . $e->getMessage());
    }
}

// ── Punkt końcowy: tylko gdy plik wywołano bezpośrednio, nie przez require z audyt.php ──────────────────────────
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Vary: Origin');
    if ($origin === LICZNIK_ORIGIN) { header('Access-Control-Allow-Origin: ' . LICZNIK_ORIGIN); }
    $metoda = (string)($_SERVER['REQUEST_METHOD'] ?? '');
    if ($metoda !== 'GET' && $metoda !== 'HEAD') {
        http_response_code(405);
        header('Allow: GET, HEAD');
        echo '{"blad":"metoda"}';
        exit;
    }
    if ($origin !== '' && $origin !== LICZNIK_ORIGIN) {
        http_response_code(403);
        echo '{"blad":"origin"}';
        exit;
    }
    $katStan = __DIR__ . '/stan';
    header('Cache-Control: public, max-age=60');
    echo json_encode([
        'dzis'  => licznik_odczyt($katStan . '/licznik-' . licznik_dzien() . '.json'),
        'razem' => licznik_odczyt($katStan . '/licznik-razem.json'),
    ]);
}
