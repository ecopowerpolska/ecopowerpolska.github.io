// Odnośnik wewnętrzny w tekście z panelu: [kotwica](/trasa/) → { tekst, adres }. Tylko adresy zaczynające się od „/”
// (linki w obrębie witryny); reszta tekstu zostaje zwykłym tekstem (D15).
export type Kawalek = { tekst: string; adres?: string };

export function kawalki(t: string): Kawalek[] {
  const wynik: Kawalek[] = [];
  const wzor = /\[([^\]]+)\]\((\/[^)\s]*)\)/g;
  let od = 0;
  for (const m of t.matchAll(wzor)) {
    if (m.index! > od) wynik.push({ tekst: t.slice(od, m.index) });
    wynik.push({ tekst: m[1], adres: m[2] });
    od = m.index! + m[0].length;
  }
  if (od < t.length) wynik.push({ tekst: t.slice(od) });
  return wynik;
}
