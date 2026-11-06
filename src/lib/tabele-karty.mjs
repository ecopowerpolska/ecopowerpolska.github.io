// Wtyczka hast Sätteri dla wpisów bloga: każda tabela z Markdownu dostaje klasę `tabela-karty`,
// a każda komórka `data-label` z nagłówkiem swojej kolumny. Na ≤ 40rem motyw.css składa wiersz w kartę
// i pokazuje etykietę przez `td::before { content: attr(data-label) }`. Bez JS, HTML dla robota bez zmian.
// Wpięcie: astro.config.mjs → satteri({ hastPlugins: [tabeleKarty] }).

/** Tekst węzła hast (nagłówek kolumny może mieć pogrubienie albo kod). */
const tekstWezla = (w) =>
  w?.type === 'text' ? String(w.value ?? '') : (w?.children ?? []).map(tekstWezla).join('');

const elementy = (w, nazwa) => (w?.children ?? []).filter((d) => d.type === 'element' && d.tagName === nazwa);

export const tabeleKarty = {
  name: 'tabele-karty',
  element: {
    filter: ['table'],
    visit(tabela, ctx) {
      const klasy = Array.isArray(tabela.properties?.className) ? tabela.properties.className : [];
      ctx.setProperty(tabela, 'className', [...klasy, 'tabela-karty']);
      const glowa = elementy(tabela, 'thead')[0];
      const naglowki = elementy(elementy(glowa, 'tr')[0], 'th').map((th) => tekstWezla(th).trim());
      if (naglowki.length === 0) return;
      for (const cialo of elementy(tabela, 'tbody')) {
        for (const wiersz of elementy(cialo, 'tr')) {
          [...elementy(wiersz, 'td'), ...elementy(wiersz, 'th')].forEach((komorka, i) => {
            if (naglowki[i]) ctx.setProperty(komorka, 'dataLabel', naglowki[i]);
          });
        }
      }
    },
  },
};
