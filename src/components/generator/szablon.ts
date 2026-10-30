// Składanie szablonu dokumentu generatora (regulamin / polityka) w HTML podczas builda.
// Dane: src/data/generator.json (składnia sekcji opisana w polu `_uwaga` tego pliku).
// Wynik trafia do <template> na stronie; przeglądarka tylko podmienia {{placeholdery}} (span[data-f]) i chowa elementy z data-w.
export type Element = string | { t: string; w?: string; lista?: Element[] };
export type Blok =
  | string
  | { p: string; w?: string }
  | { lista: Element[]; w?: string }
  | { ust: Element[]; w?: string }
  | { tabela: { naglowki: string[]; wiersze: { k: string[]; w?: string }[] }; w?: string };
export type Sekcja = { id: string; tytul: string; warunek: string | null; tresc: Blok[] };

const esc = (s: string) => s.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
// {{pole}} → span wypełniany skryptem; [tekst](https://adres) → link zewnętrzny.
export const tr = (s: string) =>
  esc(s)
    .replace(/\{\{(\w+)\}\}/g, '<span data-f="$1"></span>')
    .replace(/\[([^\]]+)\]\((https:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
const w = (x?: string | null) => (x ? ` data-w="${esc(x)}"` : '');

const el = (e: Element): string => {
  const o = typeof e === 'string' ? { t: e } : e;
  return `<li${w((o as { w?: string }).w)}>${tr(o.t)}${(o as { lista?: Element[] }).lista ? `<ul>${(o as { lista: Element[] }).lista.map(el).join('')}</ul>` : ''}</li>`;
};

const blok = (b: Blok): string => {
  if (typeof b === 'string') return `<p>${tr(b)}</p>`;
  if ('p' in b) return `<p${w(b.w)}>${tr(b.p)}</p>`;
  if ('lista' in b) return `<ul${w(b.w)}>${b.lista.map(el).join('')}</ul>`;
  if ('ust' in b) return `<ol class="ust"${w(b.w)}>${b.ust.map(el).join('')}</ol>`;
  const t = b.tabela;
  return (
    `<div class="dok__tabela"${w(b.w)}><table><thead><tr>${t.naglowki.map((h) => `<th scope="col">${esc(h)}</th>`).join('')}</tr></thead><tbody>` +
    t.wiersze.map((r) => `<tr${w(r.w)}>${r.k.map((k, i) => `<td data-etykieta="${esc(t.naglowki[i] ?? '')}">${tr(k)}</td>`).join('')}</tr>`).join('') +
    `</tbody></table></div>`
  );
};

export const sekcjaHtml = (s: Sekcja, numeracja: boolean) =>
  `<section${w(s.warunek)}><h4>${numeracja ? '<span class="nr"></span>' : ''}${esc(s.tytul)}</h4>${s.tresc.map(blok).join('')}</section>`;

export const dokumentHtml = (tytul: string, wersja: string, sekcje: Sekcja[], numeracja: boolean, stopka: string) =>
  `<h3 class="dok__tytul">${tr(tytul)}</h3><p class="dok__wersja">${tr(wersja)}</p><div class="dok__tresc">${sekcje
    .map((s) => sekcjaHtml(s, numeracja))
    .join('')}</div><p class="dok__stopka">${tr(stopka)}</p>`;
