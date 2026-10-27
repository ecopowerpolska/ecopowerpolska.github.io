// /llms.txt — streszczenie strony dla asystentów AI (wzorzec llmstxt.org), składane z TYCH SAMYCH danych co strony HTML:
// firma.json, strona.json, oferta.json, kolekcja serwisów = realizacje (bez ukrytych) i opublikowane wpisy.
// Audyt Lighthouse `llms-txt` (kategoria Agentic Browsing) wymaga pliku Markdown z nagłówkiem H1.
// Bez daty builda: zmieniałaby się przy każdej przebudowie, choć treść stoi. /cena to cennik orientacyjny EcoPower (netto).
import type { APIRoute } from 'astro';
import { getCollection } from 'astro:content';
import firma from '../data/firma.json';
import strona from '../data/strona.json';
import oferta from '../data/oferta.json';
import { tekst } from '../lib/tekst';
import { opublikowane, adresWpisu, dataPoPolsku } from '../lib/wpisy';

/** Lista pozycji {nazwa, opis} z panelu — wpis bez nazwy pomijany. */
const pozycje = (lista: unknown): { nazwa: string; opis: string }[] =>
  (Array.isArray(lista) ? lista : [])
    .map((p) => ({ nazwa: String(p?.nazwa ?? '').trim(), opis: String(p?.opis ?? '').trim() }))
    .filter((p) => p.nazwa !== '');

export const GET: APIRoute = async () => {
  const baza = new URL('/', import.meta.env.SITE).href.replace(/\/$/, '');
  const realizacje = await getCollection('serwisy', ({ data }) => !data.ukryty);
  const wpisy = await opublikowane();
  const nazwa = tekst(firma.nazwaKrotka, 'EcoPower Polska');
  const telefon = firma.telefon.replace(/\D/g, '').replace(/(\d{3})(?=\d)/g, '$1 ');
  const adres = [firma.adresSiedziby, [firma.kodPocztowy, firma.miejscowosc].filter(Boolean).join(' ')]
    .filter(Boolean)
    .join(', ');
  const dostajesz = (Array.isArray(oferta.dostajesz) ? oferta.dostajesz : [])
    .map((t) => String(t ?? '').trim())
    .filter(Boolean);

  const linie = [
    `# ${nazwa}`,
    '',
    `> ${tekst(strona.opisWyszukiwarki, 'Budujemy strony internetowe i sklepy internetowe dla firm.').trim()}`,
    '',
    `${nazwa} to firma, która projektuje, buduje i utrzymuje strony internetowe i sklepy internetowe. Ceny orientacyjne netto są w cenniku na podstronie /cena/.`,
    '',
    `## ${tekst(oferta.zakresNaglowek, 'Czym się zajmujemy')}`,
    '',
    ...pozycje(oferta.zakres).map((p) => `- **${p.nazwa}**${p.opis ? `: ${p.opis}` : ''}`),
    '',
    `## ${tekst(oferta.procesNaglowek, 'Jak pracujemy')}`,
    '',
    ...pozycje(oferta.proces).map((p, i) => `${i + 1}. **${p.nazwa}**${p.opis ? `: ${p.opis}` : ''}`),
    '',
    `## ${tekst(oferta.dostajeszNaglowek, 'Co dostajesz')}`,
    '',
    ...dostajesz.map((t) => `- ${t}`),
    '',
    `## ${tekst(oferta.technologiaNaglowek, 'Dlaczego Astro i taki hosting')}`,
    '',
    ...pozycje(oferta.technologia).map((p) => `- **${p.nazwa}**${p.opis ? `: ${p.opis}` : ''}`),
    '',
    '## Realizacje',
    '',
    ...realizacje.map((s) => `- [${s.data.nazwa}](${s.data.adres})${s.data.opis ? `: ${s.data.opis}` : ''}`),
    '',
    '## Kontakt i dane spółki',
    '',
    `- E-mail: ${firma.email.toLowerCase()}.${telefon ? ` Telefon: ${telefon}.` : ''}`,
    ...(adres ? [`- Siedziba: ${adres}.`] : []),
    `- Nazwa pełna: ${firma.nazwaPelna}.`,
    `- ${firma.rejestr}: KRS ${firma.krs}, NIP ${firma.nip}, REGON ${firma.regon}.`,
    `- Strona: ${baza}/`,
    '',
    '## Strony tej witryny',
    '',
    `- [Strona główna](${baza}/): oferta, realizacje, kontakt i dane rejestrowe spółki.`,
    `- [Cennik orientacyjny EcoPower Polska (ceny netto)](${baza}/cena/): wykonanie i prowadzenie strony internetowej i sklepu.`,
    `- [Pytania i odpowiedzi](${baza}/faq/): najczęstsze pytania o strony i sklepy.`,
    `- [Blog](${baza}/blog/): wpisy o budowie stron i sklepów internetowych.`,
    '',
  ];

  if (wpisy.length > 0) {
    linie.push('## Wpisy na blogu', '');
    for (const w of wpisy) {
      linie.push(
        `- [${w.data.tytul}](${baza}${adresWpisu(w.id)}) (${dataPoPolsku(w.data.dataPublikacji!)})${w.data.opisSeo ? `: ${w.data.opisSeo}` : ''}`,
      );
    }
    linie.push('');
  }

  linie.push('## Pliki dla maszyn', '', `- [Mapa strony](${baza}/sitemap.xml)`, `- [Kanał RSS bloga](${baza}/rss.xml)`, '');

  return new Response(linie.join('\n'), { headers: { 'Content-Type': 'text/markdown; charset=utf-8' } });
};
