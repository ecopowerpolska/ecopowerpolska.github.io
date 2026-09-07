# Kompletny CMS — plan wystawienia WSZYSTKICH tekstów strony do panelu

**Rodzaj:** kryteria
**Stan: WYKONANE 2026-09-07**, commit `fb6c496`. Dokument zostaje jako zapis, CO i DLACZEGO
zostało wystawione — nie jako lista do zrobienia.

**Co poszło ponad ten plan:** doszły **grupy kafelków** (`src/data/grupy.json`, kolekcja `grupy`,
pole `grupa` w kafelku jako widget `relation`) i **menu panelu z czterech pozycji** po polsku:
Grupy · Serwisy (kafelki) · Teksty strony · Dane firmy. Tego plan nie przewidywał — weszło
z drugiej i czwartej skargi Piotra tego samego dnia.

**Czego plan nie doceniał:** `og:site_name` i `napisLicznika` też były twardo w kodzie i też
wyszły do panelu. Razem `strona.json` niesie 12 napisów, nie 8 jak niżej.

---

## Co JUŻ było edytowalne przed tą robotą

Kafelki (nazwa, adres, podpis, kategoria, obrazek, opis obrazka, ukrycie, kolejność przez
przeciąganie) · dane rejestrowe w stopce · zdanie pod kafelkami.

## Co BYŁO TWARDO W KODZIE i trafiło do panelu

| Plik | Tekst |
|---|---|
| `src/pages/index.astro` | tytuł karty przeglądarki (dopisek „— serwisy spółki") |
| `src/pages/index.astro` | opis dla wyszukiwarek |
| `src/pages/index.astro` | zdanie w nagłówku („Spółka nadzoruje…") |
| `src/pages/index.astro` | etykieta sekcji zamykającej (`aria-label="O spółce"`) |
| `src/pages/index.astro` | komunikat pustej listy („Brak serwisów do pokazania.") |
| `src/pages/index.astro` | etykieta spisu kategorii, nazwa grupy „Inne", ukryty nagłówek „Serwisy" |
| `src/components/PasekMarki.astro` | dopisek licznika („w sieci") |
| `src/components/Stopka.astro` | pięć etykiet: Forma prawna / Adres WWW / E-mail / Adres / Telefon |
| `src/components/Stopka.astro` | napis guzika zbiorczego, potwierdzenie „Skopiowano", komunikat błędu |
| `src/layouts/Bazowy.astro` | link pomijania („Przejdź do treści"), `og:site_name` |
| `src/pages/404.astro` | tytuł karty, opis, nagłówek, akapit, etykieta linku powrotu |

**Zostaje w kodzie świadomie:** nazwy dla czytnika ekranu przy guzikach kopiowania
(„Skopiuj numer NIP" itd.) — to mechanika dostępności, nie treść; wystawienie ich daje wyłącznie
możliwość zepsucia. Tak samo funkcja odmiany liczebnika (serwis/serwisy/serwisów) — to gramatyka.

---

## Projekt rozwiązania

### 1. Pomocnik z wartością domyślną — `src/lib/tekst.ts`

```ts
export function tekst(wartosc: string | undefined | null, domyslny: string): string {
  return typeof wartosc === 'string' && wartosc.trim() !== '' ? wartosc : domyslny;
}
```

🔴 **To jest zabezpieczenie, nie wygoda.** Panel edytuje produkcję bez podglądu: skasowanie
zawartości pola nie może zostawić strony bez nagłówka ani bez tytułu. Puste pole znaczy
„wróć do domyślnego", nie „usuń napis". Wyjątek: teksty, których brak jest sensowną decyzją
(zdanie pod kafelkami) — tam sprawdza się pustkę wprost i nie renderuje sekcji.

### 2. Trzy pliki danych

`src/data/strona.json`

```json
{
  "tytulKarty": "EcoPower Polska — serwisy spółki",
  "opisWyszukiwarki": "EcoPower Polska nadzoruje kilka niezależnych serwisów internetowych.",
  "zdanieNaglowka": "Spółka nadzoruje kilka niezależnych serwisów internetowych.",
  "napisLicznika": "w sieci",
  "zdanieKoncowe": "Każdy z serwisów działa samodzielnie — pod własnym adresem, z własną ofertą i własną obsługą klienta. Spółka odpowiada za ich prowadzenie, finansowanie i rozwój.",
  "etykietaSekcjiZamykajacej": "O spółce",
  "komunikatPustejListy": "Brak serwisów do pokazania.",
  "linkPomijania": "Przejdź do treści"
}
```

`src/data/stopka.json`

```json
{
  "etykietaFormaPrawna": "Forma prawna:",
  "etykietaAdresWww": "Adres WWW:",
  "etykietaEmail": "E-mail:",
  "etykietaAdres": "Adres:",
  "etykietaTelefon": "Telefon:",
  "guzikKopiujWszystko": "Skopiuj dane spółki",
  "potwierdzenieKopiowania": "Skopiowano",
  "bladKopiowania": "Nie udało się skopiować."
}
```

`src/data/strona-404.json`

```json
{
  "tytulKarty": "Nie ma takiej strony — EcoPower Polska",
  "opisWyszukiwarki": "Pod tym adresem nic nie ma. Wróć na stronę główną z listą serwisów EcoPower Polska.",
  "naglowek": "Nie ma takiej strony",
  "tekst": "Pod tym adresem nic nie ma.",
  "linkPowrotu": "Wróć na stronę główną z listą serwisów"
}
```

🔴 **Żaden z tych plików NIE jest kolekcją.** Importuje się je wprost, tak jak `firma.json`:
loader `file()` rozbiłby pojedynczy obiekt na osobne wpisy po jednym na klucz.

### 3. Skrypt stopki czyta teksty z atrybutów `data-*`

Skrypt kliencki nie widzi zmiennych z frontmatteru. Napisy („Skopiowano", komunikat błędu,
pierwotny napis guzika) trzeba podać na elemencie `<footer>` jako `data-*` i odczytać w skrypcie.

### 4. Panel — trzy nowe pozycje w kolekcji `ustawienia`

`strona` → `src/data/strona.json` · `stopka` → `src/data/stopka.json` ·
`nieznaleziona` → `src/data/strona-404.json`. Każde pole `widget: string` (albo `text` dla
dłuższych) z podpowiedzią mówiącą, co się stanie po wyczyszczeniu pola.

🔴 **Wzorce `pattern` obejmują znak nowej linii** (`[\s\S]`, nie `.`) — inaczej wklejony tekst
z łamaniem wiersza nie przechodzi walidacji i panel odmawia zapisu bez czytelnego powodu.

---

## Kolejność wykonania

1. `src/lib/tekst.ts`
2. trzy pliki danych
3. `Bazowy.astro` → `index.astro` → `PasekMarki.astro` → `Stopka.astro` → `404.astro`
4. `public/admin/config.yml`
5. `npm run verify` + obejrzenie strony
6. commit, push, **i osobne uprzedzenie Piotra, żeby odświeżył panel** (patrz `CMS-ZASADY-PRACY.md`)
