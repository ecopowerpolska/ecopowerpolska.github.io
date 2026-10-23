// Wpisy bloga — JEDNO miejsce, które rozstrzyga, co jest opublikowane. Lista /blog/, strony wpisów, mapa strony,
// kanał RSS i llms.txt liczą to samo. Wzorzec: src/lib/wpisy.ts pan-stolarz.pl (2026-10-14), uproszczony do jednej daty.
// 🔴 "Teraz" = chwila BUDOWANIA: wpis z datą w przyszłości wejdzie przy pierwszym buildzie po tej dacie.
import type { CollectionEntry } from 'astro:content';
import { getCollection } from 'astro:content';
import type { ImageMetadata } from 'astro';

export type Wpis = CollectionEntry<'wpisy'>;

/** Wpisy opublikowane: nie szkic, z datą nie późniejszą niż teraz; od najnowszego, remis po `id`. */
export async function opublikowane(): Promise<Wpis[]> {
  const teraz = Date.now();
  const wpisy = await getCollection(
    'wpisy',
    (w: Wpis) => !w.data.szkic && w.data.dataPublikacji !== null && w.data.dataPublikacji.getTime() <= teraz,
  );
  return wpisy.sort(
    (a: Wpis, b: Wpis) =>
      b.data.dataPublikacji!.getTime() - a.data.dataPublikacji!.getTime() || a.id.localeCompare(b.id, 'pl'),
  );
}

/** Adres wpisu z ukośnikiem na końcu — GitHub Pages podaje katalog z index.html, adres bez ukośnika dostaje 301. */
export function adresWpisu(id: string): string {
  return `/blog/${id}/`;
}

const FORMAT_PL = new Intl.DateTimeFormat('pl-PL', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: 'Europe/Warsaw',
});

/** "23 października 2026", dzień w strefie warszawskiej, nie maszyny budującej. */
export function dataPoPolsku(d: Date): string {
  return FORMAT_PL.format(d);
}

// Wszystkie obrazy z src/assets/. Panel zapisuje ścieżkę względną (../../assets/blog/x.jpg), liczy się człon po "assets/".
const OBRAZY = import.meta.glob<{ default: ImageMetadata }>('../assets/**/*.{jpg,jpeg,png,webp,avif}', { eager: true });

/** Plik zdjęcia wpisu albo `undefined`, gdy pliku nie ma — wpis bez zdjęcia, nie zatrzymany build. */
export function plikObrazu(sciezka: string): ImageMetadata | undefined {
  const t = sciezka.replace(/\\/g, '/').trim();
  if (t === '') return undefined;
  const i = t.lastIndexOf('assets/');
  const wzgledna = (i >= 0 ? t.slice(i + 'assets/'.length) : t).replace(/^\/+/, '');
  return OBRAZY[`../assets/${wzgledna}`]?.default;
}
