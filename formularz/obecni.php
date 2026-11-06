<?php
declare(strict_types=1);
/**
 * "W tej chwili czyta N osób" (www.ecopowerpolska.pl).
 * POST id=<32 znaki hex>            → odnowienie obecności, odpowiedź {"teraz":N}
 * POST id=<…>&wyjscie=1             → usunięcie wpisu (zamknięcie karty), odpowiedź {"teraz":N}
 * GET                               → {"teraz":N} bez zapisu
 * Id losuje przeglądarka przy każdym otwarciu strony i trzyma tylko w pamięci karty (bez ciasteczek i bez Web Storage).
 * Zapis: stan/obecni.json = {"<16 znaków sha256(id)>": <znacznik czasu unix>}. Bez IP, bez adresu strony, bez nagłówków.
 * Wpisy starsze niż 300 s znikają przy każdym żądaniu. Zapis atomowy pod flock (stan/obecni.lock), prawa 0600.
 */

const OBECNI_ORIGIN = 'https://www.ecopowerpolska.pl';
const OBECNI_OKNO = 300;     // sekundy: kto nie odezwał się 5 minut, nie czyta
const OBECNI_MAKS = 5000;    // bezpiecznik rozmiaru pliku

function obecni_odpowiedz(int $status, array $dane): never {
    http_response_code($status);
    echo json_encode($dane);
    exit;
}

$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Vary: Origin');
if ($origin === OBECNI_ORIGIN) { header('Access-Control-Allow-Origin: ' . OBECNI_ORIGIN); }

$metoda = (string)($_SERVER['REQUEST_METHOD'] ?? '');
if ($metoda !== 'GET' && $metoda !== 'POST') {
    header('Allow: GET, POST');
    obecni_odpowiedz(405, ['blad' => 'metoda']);
}
// POST tylko z naszej strony; obcy Origin dostaje odmowę także przy GET.
if (($origin !== '' && $origin !== OBECNI_ORIGIN) || ($metoda === 'POST' && $origin !== OBECNI_ORIGIN)) {
    obecni_odpowiedz(403, ['blad' => 'origin']);
}

$id = is_string($_POST['id'] ?? null) ? (string)$_POST['id'] : '';
if ($metoda === 'POST' && !preg_match('/^[a-f0-9]{32}$/', $id)) {
    obecni_odpowiedz(400, ['blad' => 'id']);
}

$katStan = __DIR__ . '/stan';
if (!is_dir($katStan)) { @mkdir($katStan, 0700, true); }
$plik = $katStan . '/obecni.json';
$blokada = @fopen($katStan . '/obecni.lock', 'c');
if ($blokada === false || !flock($blokada, LOCK_EX)) {
    if ($blokada !== false) { fclose($blokada); }
    obecni_odpowiedz(503, ['blad' => 'stan']);
}

$teraz = time();
$wpisy = is_file($plik) ? json_decode((string)@file_get_contents($plik), true) : [];
$wpisy = is_array($wpisy) ? $wpisy : [];
$wpisy = array_filter($wpisy, static fn($t) => is_int($t) && $teraz - $t < OBECNI_OKNO);

if ($metoda === 'POST') {
    $klucz = substr(hash('sha256', $id), 0, 16);
    if (($_POST['wyjscie'] ?? '') === '1') {
        unset($wpisy[$klucz]);
    } elseif (isset($wpisy[$klucz]) || count($wpisy) < OBECNI_MAKS) {
        $wpisy[$klucz] = $teraz;
    }
    $tmp = $plik . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, json_encode($wpisy, JSON_FORCE_OBJECT)) !== false) {
        @chmod($tmp, 0600);
        if (!@rename($tmp, $plik)) { @unlink($tmp); }
    }
    @chmod($katStan . '/obecni.lock', 0600);
}
flock($blokada, LOCK_UN);
fclose($blokada);

obecni_odpowiedz(200, ['teraz' => count($wpisy)]);
