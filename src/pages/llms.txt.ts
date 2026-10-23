// /llms.txt — streszczenie strony dla asystentów AI (wzorzec llmstxt.org), składane z TYCH SAMYCH danych co strony HTML:
// firma.json, strona.json, kolekcja serwisów (bez ukrytych) i opublikowane wpisy. Wzorzec: studioagat.pl src/pages/llms.txt.ts.
// Audyt Lighthouse `llms-txt` (kategoria Agentic Browsing) wymaga pliku Markdown z nagłówkiem H1.
// Bez daty builda: zmieniałaby się przy każdej przebudowie, choć treść stoi.
import type { APIRoute } from 'astro';
import { getCollection } from 'astro:content';
import firma from '../data/firma.json';
import strona from '../data/strona.json';
import { tekst } from '../lib/tekst';
import { opublikowane, adresWpisu, dataPoPolsku } from '../lib/wpisy';

export const GET: APIRoute = async () => {
  const baza = new URL('/', import.meta.env.SITE).href.replace(/\/$/, '');
  const serwisy = await getCollection('serwisy', ({ data }) => !data.ukryty);
  const wpisy = await opublikowane();
  const nazwa = tekst(firma.nazwaKrotka, 'EcoPower Polska');

  const linie = [
    `# ${nazwa}`,
    '',
    `> ${[tekst(strona.zdanieNaglowka, 'Spółka nadzoruje grupę niezależnych serwisów'), tekst(strona.zdanieKoncowe, '')]
      .map((z) => z.trim().replace(/[.\s]+$/, ''))
      .filter(Boolean)
      .join('. ')}.`,
    '',
    '## Tożsamość spółki',
    '',
    `- Nazwa pełna: ${firma.nazwaPelna}.`,
    `- ${firma.rejestr}: KRS ${firma.krs}, NIP ${firma.nip}, REGON ${firma.regon}.`,
    ...(firma.adresSiedziby ? [`- Adres: ${firma.adresSiedziby}.`] : []),
    `- E-mail: ${firma.email.toLowerCase()}.${firma.telefon ? ` Telefon: ${firma.telefon}.` : ''}`,
    `- Strona: ${baza}/`,
    '',
    '## Serwisy nadzorowane przez spółkę',
    '',
    ...serwisy.map((s) => `- [${s.data.nazwa}](${s.data.adres})${s.data.opis ? `: ${s.data.opis}` : ''}`),
    '',
    '## Strony tej witryny',
    '',
    `- [Strona główna](${baza}/): lista serwisów i dane rejestrowe spółki.`,
    `- [Blog](${baza}/blog/): wpisy o spółce i jej serwisach.`,
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
