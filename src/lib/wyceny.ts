/** Koszyk wycen (runda 4, 2026-10-27): wyceny z kalkulatora /cena/ i szkic formularza /kontakt/ w pamięci SESJI karty.
 *  Bez ciasteczek i bez serwera: nic nie wychodzi z przeglądarki do kliknięcia „Wyślij zapytanie” (pole ukryte `wyceny`).
 *  Kontrakt §III rundy 4: sessionStorage['ep.wyceny'] = tablica Wycena (≤ 5), sessionStorage['ep.szkic'] = szkic pól.
 *  Brak sessionStorage (tryb prywatny, blokada) = funkcje zwracają pusto i nie rzucają.
 *  Wyłącznie w <script> komponentów (kod przeglądarki), nie we frontmatterze. */

export const KLUCZ_WYCENY = 'ep.wyceny';
export const KLUCZ_SZKIC = 'ep.szkic';
export const MAKS_WYCEN = 5;
/** Zdarzenie na window po każdej zmianie listy (detail.wyceny). */
export const ZDARZENIE = 'ep:wyceny';
/** Limity = stałe w formularz/wyslij.php; przycinamy u źródła, żeby handler nie odrzucał wyceny. */
export const MAKS_POZYCJI = 20;
export const MAKS_NAZWA = 120;
export const MAKS_JEDNOSTKA = 30;
export const MAKS_TYTUL = 160;

export interface Pozycja { nazwa: string; ilosc: number; jednostka: string; cena: number }
export interface Kwoty { jednorazowo: number; miesiecznie: number; rocznie: number }
export interface Wycena extends Kwoty {
  id: string;
  /** Chwila dodania, ISO 8601. */
  data: string;
  tytul: string;
  pozycje: Pozycja[];
  /** ×1,23, zaokrąglone do 0,01. */
  brutto: Kwoty;
}
/** Kalkulator może pominąć id, datę i brutto — uzupełnia je dodajWycene(). */
export type NowaWycena = Omit<Wycena, 'id' | 'data' | 'brutto'> & Partial<Pick<Wycena, 'id' | 'data' | 'brutto'>>;

export interface Szkic {
  imie: string; email: string; telefon: string; wiadomosc: string;
  potrzeby: string[]; technologia: string; strona: string; adres: string; pora: string;
}

const pamiec = (): Storage | null => {
  try { return typeof sessionStorage === 'undefined' ? null : sessionStorage; } catch { return null; }
};
const czytaj = (k: string): unknown => {
  try { const s = pamiec()?.getItem(k); return s ? JSON.parse(s) : null; } catch { return null; }
};
const zapisz = (k: string, v: unknown): boolean => {
  const p = pamiec();
  if (!p) return false;
  try { p.setItem(k, JSON.stringify(v)); return true; } catch { return false; }
};
const usunKlucz = (k: string): void => { try { pamiec()?.removeItem(k); } catch { /* brak pamięci */ } };

const tekst = (x: unknown, maks: number): string =>
  typeof x === 'string' ? x.replace(/[\u0000-\u001f\u007f]+/g, ' ').trim().slice(0, maks) : '';
const liczba = (x: unknown, maks = 10_000_000): number =>
  typeof x === 'number' && Number.isFinite(x) && x >= 0 && x <= maks ? x : 0;
const grosze = (x: number): number => Math.round(x * 100) / 100;
export const brutto = (k: Kwoty): Kwoty => ({
  jednorazowo: grosze(k.jednorazowo * 1.23), miesiecznie: grosze(k.miesiecznie * 1.23), rocznie: grosze(k.rocznie * 1.23),
});

function noweId(): string {
  const b = new Uint8Array(6);
  try { crypto.getRandomValues(b); } catch { for (let i = 0; i < b.length; i++) b[i] = Math.floor(Math.random() * 256); }
  return Array.from(b, (x) => x.toString(36).padStart(2, '0')).join('').slice(0, 10);
}

/** Porządkuje jedną wycenę; null = odrzucona (brak pozycji i kwot). */
function oczysc(x: unknown): Wycena | null {
  if (!x || typeof x !== 'object') return null;
  const o = x as Record<string, unknown>;
  const pozycje = (Array.isArray(o.pozycje) ? o.pozycje : [])
    .map((p) => {
      const q = (p ?? {}) as Record<string, unknown>;
      return { nazwa: tekst(q.nazwa, MAKS_NAZWA), ilosc: liczba(q.ilosc, 100_000), jednostka: tekst(q.jednostka, MAKS_JEDNOSTKA), cena: liczba(q.cena) };
    })
    .filter((p) => p.nazwa)
    .slice(0, MAKS_POZYCJI);
  const netto: Kwoty = { jednorazowo: liczba(o.jednorazowo), miesiecznie: liczba(o.miesiecznie), rocznie: liczba(o.rocznie) };
  if (!pozycje.length) return null;
  return {
    id: typeof o.id === 'string' && /^[a-z0-9]{6,16}$/.test(o.id) ? o.id : noweId(),
    data: typeof o.data === 'string' && o.data ? o.data.slice(0, 30) : new Date().toISOString(),
    tytul: tekst(o.tytul, MAKS_TYTUL) || pozycje.map((p) => p.nazwa).join(' + ').slice(0, MAKS_TYTUL),
    pozycje,
    ...netto,
    brutto: brutto(netto),
  };
}

const odcisk = (w: Wycena): string =>
  JSON.stringify([w.pozycje.map((p) => [p.nazwa, p.ilosc, p.jednostka, p.cena]), w.jednorazowo, w.miesiecznie, w.rocznie]);

const oglos = (wyceny: Wycena[]): void => {
  try { window.dispatchEvent(new CustomEvent(ZDARZENIE, { detail: { wyceny } })); } catch { /* bez window */ }
};

const zapiszListe = (w: Wycena[]): boolean => {
  const ok = w.length ? zapisz(KLUCZ_WYCENY, w) : (usunKlucz(KLUCZ_WYCENY), true);
  if (ok) oglos(w);
  return ok;
};

/** Czy pamięć sesji działa. */
export function dostepna(): boolean {
  const p = pamiec();
  if (!p) return false;
  try { p.setItem('ep.proba', '1'); p.removeItem('ep.proba'); return true; } catch { return false; }
}

/** Wyceny w kolejności dodania (≤ 5); śmieci w pamięci = pusto. */
export function listaWycen(): Wycena[] {
  const d = czytaj(KLUCZ_WYCENY);
  if (!Array.isArray(d)) { if (d !== null) usunKlucz(KLUCZ_WYCENY); return []; }
  return d.map(oczysc).filter((w): w is Wycena => w !== null).slice(0, MAKS_WYCEN);
}

/** Dodaje wycenę; zwraca liczbę wpisów po dodaniu. Duplikat nie dubluje.
 *  Limit 5 albo brak pamięci = lista bez zmian; wtedy odczytaj czyMoznaDodac() przed kliknięciem. */
export function dodajWycene(w: NowaWycena | Wycena): number {
  const obecne = listaWycen();
  const nowa = oczysc({ ...w, id: undefined, data: undefined });
  if (!nowa || !dostepna()) return obecne.length;
  if (obecne.some((x) => odcisk(x) === odcisk(nowa))) return obecne.length;
  if (obecne.length >= MAKS_WYCEN) return obecne.length;
  const lista = [...obecne, nowa];
  return zapiszListe(lista) ? lista.length : obecne.length;
}

/** false = limit 5 albo brak pamięci sesji. */
export function czyMoznaDodac(): boolean {
  return dostepna() && listaWycen().length < MAKS_WYCEN;
}

export function usunWycene(id: string): Wycena[] {
  const nowe = listaWycen().filter((w) => w.id !== id);
  zapiszListe(nowe);
  return nowe;
}

/** Po wysłaniu zapytania: wyceny, szkic i wynik testu szybkości (ep.psi) znikają. */
export function wyczysc(): void {
  usunKlucz(KLUCZ_WYCENY);
  usunKlucz(KLUCZ_SZKIC);
  usunKlucz('ep.psi');
  oglos([]);
}

export function zapiszSzkic(s: Szkic): boolean {
  return zapisz(KLUCZ_SZKIC, {
    imie: tekst(s.imie, 100), email: tekst(s.email, 200), telefon: tekst(s.telefon, 30),
    wiadomosc: typeof s.wiadomosc === 'string' ? s.wiadomosc.slice(0, 4000) : '',
    potrzeby: Array.isArray(s.potrzeby) ? s.potrzeby.filter((m) => typeof m === 'string').slice(0, 15) : [],
    technologia: tekst(s.technologia, 30), strona: tekst(s.strona, 5), adres: tekst(s.adres, 300), pora: tekst(s.pora, 20),
  });
}

export function odczytajSzkic(): Szkic | null {
  const d = czytaj(KLUCZ_SZKIC) as Partial<Szkic> | null;
  if (!d || typeof d !== 'object') return null;
  return {
    imie: tekst(d.imie, 100), email: tekst(d.email, 200), telefon: tekst(d.telefon, 30),
    wiadomosc: typeof d.wiadomosc === 'string' ? d.wiadomosc.slice(0, 4000) : '',
    potrzeby: Array.isArray(d.potrzeby) ? d.potrzeby.filter((m): m is string => typeof m === 'string') : [],
    technologia: tekst(d.technologia, 30), strona: tekst(d.strona, 5), adres: tekst(d.adres, 300), pora: tekst(d.pora, 20),
  };
}

export function wyczyscSzkic(): void { usunKlucz(KLUCZ_SZKIC); }

/** Wartość pola ukrytego `wyceny` (JSON, bez id; handler ma limit 16 KiB). */
export function doWyslania(w: Wycena[] = listaWycen()): string {
  if (!w.length) return '';
  return JSON.stringify(w.slice(0, MAKS_WYCEN).map(({ id: _id, ...reszta }) => reszta));
}

/** Kwota po polsku: 13 222,50 zł / 5 750 zł. */
export function zl(x: number): string {
  const ma = Math.round(x * 100) % 100 !== 0;
  return x.toLocaleString('pl-PL', { minimumFractionDigits: ma ? 2 : 0, maximumFractionDigits: 2 }) + ' zł';
}
