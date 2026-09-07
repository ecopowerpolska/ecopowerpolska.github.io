/**
 * Wartość z panelu albo wartość domyślna.
 *
 * 🔴 To jest ZABEZPIECZENIE, nie wygoda. Panel edytuje produkcję bez podglądu:
 * wyczyszczenie pola nie może zostawić strony bez nagłówka ani bez tytułu karty.
 * Puste pole znaczy „wróć do domyślnego", nie „usuń napis".
 *
 * Wyjątek świadomy: teksty, których brak JEST sensowną decyzją (zdanie pod
 * kafelkami) — tam sprawdza się pustkę wprost i nie renderuje sekcji wcale.
 * Do nich tej funkcji się nie używa.
 */
export function tekst(wartosc: string | undefined | null, domyslny: string): string {
  return typeof wartosc === 'string' && wartosc.trim() !== '' ? wartosc : domyslny;
}
