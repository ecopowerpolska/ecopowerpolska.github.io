// /rss.xml — kanał RSS 2.0 wpisów bloga, bez pakietu @astrojs/rss (nie jest zainstalowany, a instalację uruchamia Piotr).
// Wpisy z opublikowane() (src/lib/wpisy.ts) — ta sama lista co /blog/.
import type { APIRoute } from 'astro';
import firma from '../data/firma.json';
import teksty from '../data/blog.json';
import { tekst } from '../lib/tekst';
import { opublikowane, adresWpisu } from '../lib/wpisy';

const xml = (t: string) =>
  t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');

export const GET: APIRoute = async () => {
  const baza = new URL('/', import.meta.env.SITE).href.replace(/\/$/, '');
  const wpisy = await opublikowane();
  const elementy = wpisy
    .map((w) => {
      const adres = `${baza}${adresWpisu(w.id)}`;
      return [
        '    <item>',
        `      <title>${xml(w.data.tytul)}</title>`,
        `      <link>${adres}</link>`,
        `      <guid isPermaLink="true">${adres}</guid>`,
        `      <pubDate>${w.data.dataPublikacji!.toUTCString()}</pubDate>`,
        ...(w.data.opisSeo ? [`      <description>${xml(w.data.opisSeo)}</description>`] : []),
        '    </item>',
      ].join('\n');
    })
    .join('\n');

  const tresc = `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>${xml(tekst(teksty.tytulKarty, 'Blog — EcoPower Polska'))}</title>
    <link>${baza}/blog/</link>
    <atom:link href="${baza}/rss.xml" rel="self" type="application/rss+xml" />
    <description>${xml(tekst(teksty.opisWyszukiwarki, `Wpisy ${firma.nazwaKrotka}`))}</description>
    <language>pl-PL</language>
${elementy}
  </channel>
</rss>
`;
  return new Response(tresc, { headers: { 'Content-Type': 'application/rss+xml; charset=utf-8' } });
};
