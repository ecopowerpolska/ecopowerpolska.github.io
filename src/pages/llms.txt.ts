// /llms.txt — streszczenie strony dla asystentów AI (wzorzec llmstxt.org).
// Treść = plan analizy/seo-ecopowerpolska-2026-10-27.md §6.4 (runda 5, 2026-10-27): oferta z czterema stronami usług,
// fakty, realizacje, strony witryny, kontakt. Liczby PageSpeed („97–100”, „4/4”, liczba realizacji, data) liczy kod
// z src/data/pomiary.json, liczbę pytań — z faq.json, listę wpisów — z kolekcji (te same filtry co /blog/).
// Audyt Lighthouse `llms-txt` wymaga: wiersza `# Tytuł`, co najmniej jednego linku Markdown i ≥ 50 znaków.
// llms.txt NIE wpływa na widoczność w Google (Search Central 2026-06-15) — służy botom AI i audytowi PSI.
// Bez daty builda: zmieniałaby się przy każdej przebudowie, choć treść stoi.
import type { APIRoute } from 'astro';
import { getCollection } from 'astro:content';
import firma from '../data/firma.json';
import faq from '../data/faq.json';
import pomiaryJson from '../data/pomiary.json';
import { tekst } from '../lib/tekst';
import { opublikowane, adresWpisu, dataPoPolsku } from '../lib/wpisy';

interface Wynik {
  wydajnosc?: number;
  agentoweZdane?: number;
  agentoweWszystkie?: number;
  data?: string;
}

// Opisy realizacji z planu §6.4 (po adresie); realizacja spoza tej listy bierze opis z panelu (serwisy.json).
const OPISY_REALIZACJI: Record<string, string> = {
  'https://studioagat.pl/': 'projekty wnętrz, kuchnie i meble na wymiar w Warszawie; przeniesione z WordPressa do Astro.',
  'https://pan-stolarz.pl/': 'meble na wymiar w Warszawie, kalkulatory cen i koszyk wycen.',
  'https://nadihome.pl/': 'remonty i wykończenia mieszkań w Warszawie, z cennikiem i panelem treści.',
  'https://kuchnia-na-wymiar-warszawa.pl/': 'kuchnie na wymiar z kalkulatorem ceny.',
  'https://szafy-na-wymiar-warszawa.pl/': 'szafy wnękowe, przesuwne i do sufitu.',
  'https://garderoba-na-wymiar-warszawa.pl/': 'garderoby walk-in, we wnęce i pod skosem.',
  'https://panwylewka.pl/': 'wylewki betonowe w Warszawie i okolicach.',
};

/** „97–100” albo „100”, gdy wszystkie wyniki są równe. */
const zakres = (liczby: number[]): string => {
  const min = Math.min(...liczby);
  const max = Math.max(...liczby);
  return min === max ? String(min) : `${min}–${max}`;
};

export const GET: APIRoute = async () => {
  const baza = new URL('/', import.meta.env.SITE).href.replace(/\/$/, '');
  const realizacje = await getCollection('serwisy', ({ data }) => !data.ukryty);
  const wpisy = await opublikowane();
  const nazwa = tekst(firma.nazwaKrotka, 'EcoPower Polska');
  const nazwaSpolki = tekst((firma as Record<string, unknown>).nazwaStopka as string | undefined, `${nazwa} Sp. z o.o.`);
  const telefon = firma.telefon.replace(/\D/g, '').replace(/(\d{3})(?=\d)/g, '$1 ');
  const adres = [firma.adresSiedziby, [firma.kodPocztowy, firma.miejscowosc].filter(Boolean).join(' ')]
    .filter(Boolean)
    .join(', ');
  const liczbaPytan = (Array.isArray(faq.pytania) ? faq.pytania : []).filter((p) => p?.pytanie && p?.odpowiedz).length;

  // Pomiary PageSpeed realizacji — tylko te, które są dziś na liście realizacji.
  const pomiary = (pomiaryJson as { pomiary?: Record<string, { mobile?: Wynik; desktop?: Wynik }> }).pomiary ?? {};
  const zmierzone = realizacje.map((s) => pomiary[s.data.adres]).filter((p) => p?.mobile?.wydajnosc != null);
  const telefonPsi = zmierzone.map((p) => p!.mobile!.wydajnosc!);
  const komputerPsi = zmierzone.map((p) => p!.desktop?.wydajnosc).filter((w): w is number => w != null);
  const wszystkieAgentowe = zmierzone.every(
    (p) =>
      p!.mobile!.agentoweZdane === p!.mobile!.agentoweWszystkie &&
      p!.desktop?.agentoweZdane === p!.desktop?.agentoweWszystkie,
  );
  const agentoweMax = zmierzone[0]?.mobile?.agentoweWszystkie ?? 4;
  const dataPomiaru = zmierzone
    .map((p) => p!.mobile!.data ?? '')
    .sort()
    .at(-1);
  const dataPomiaruPl = dataPomiaru ? dataPomiaru.split('-').reverse().join('.') : '';
  const psiTelefon = telefonPsi.length > 0 ? zakres(telefonPsi) : '';
  const psiKomputer = komputerPsi.length > 0 ? zakres(komputerPsi) : '';

  const linie = [
    `# ${nazwa}`,
    '',
    `> Budujemy szybkie strony internetowe i sklepy internetowe dla firm, a potem je prowadzimy. Każdą stronę mierzymy w Google PageSpeed Insights i publikujemy wynik${psiTelefon ? `: nasze realizacje mają ${psiTelefon} punktów wydajności na telefonie${psiKomputer ? ` i ${psiKomputer} na komputerze` : ''}` : ''}.`,
    '',
    `${nazwaSpolki} to warszawska firma z Gocławia. Projektujemy, piszemy i wdrażamy strony i sklepy dla małych i średnich firm usługowych — od wizytówki po sklep z płatnością online. Ceny orientacyjne netto są w [cenniku](${baza}/cena/), a [kalkulator](${baza}/cena/#kalkulator) liczy wycenę od razu.`,
    '',
    '## Co robimy',
    '',
    `- [Strony internetowe dla firm](${baza}/strony-internetowe-warszawa/): Strona z ofertą, realizacjami, blogiem i formularzem, zbudowana w Astro jako gotowe pliki HTML. Otwiera się szybko nawet na słabym telefonie, bo przy wejściu nie odpytuje bazy danych i nie ładuje zbędnych skryptów.`,
    `- [Sklepy internetowe](${baza}/sklepy-internetowe-warszawa/): Dobieramy drogę do sklepu: WooCommerce albo PrestaShop na hostingu, platforma abonamentowa (Shoper, IdoSell) albo lekki sklep w Astro z płatnością online. Mówimy wprost, co która droga kosztuje miesięcznie i czego nie potrafi.`,
    `- [Strona wizytówka](${baza}/strona-wizytowka/): Jedna strona albo kilka podstron dla małej firmy: kim jesteś, co robisz, jak się skontaktować. Wizytówka bez sprzedaży może stać na GitHub Pages bez opłat za hosting — tak działa ta strona.`,
    `- [Prowadzenie strony](${baza}/prowadzenie-strony-internetowej/): Po publikacji zmieniamy treści, pilnujemy szybkości i Search Console, prowadzimy wizytówkę Google i raportujemy, co się zmieniło w ruchu i pozycjach.`,
    '- Panel do edycji treści: Teksty, zdjęcia i wpisy zmieniasz sam w panelu pod adresem /admin/ (Sveltia, zapis w repozytorium strony) albo w panelu PHP na hostingu, bez konta na GitHubie.',
    '- Hosting, domena i poczta: Strony z formularzami i sklepy stawiamy na polskim hostingu lh.pl, każdą w osobnym katalogu. Przy przepięciu domeny przenosimy rekordy DNS tak, żeby poczta działała bez przerwy.',
    '- Widoczność w Google: Tytuły, opisy, dane strukturalne, mapa strony, Search Console, IndexNow dla Binga i wizytówka Google z tymi samymi danymi firmy co na stronie.',
    '- Facebook i Instagram: Łączymy stronę z kontami firmy i możemy je prowadzić; nowy wpis na blogu może sam trafiać na Facebooka.',
    '',
    '## Dla kogo',
    '',
    'Dla firm usługowych, które chcą, żeby klient znalazł je w Google, zobaczył prawdziwe realizacje i od razu napisał albo zadzwonił: wykonawców remontów i wylewek, stolarzy i producentów mebli na wymiar, projektantów wnętrz, sklepów z własnym towarem.',
    '',
    '## Jak pracujemy',
    '',
    '1. Zakres: ustalamy, co strona ma robić — pokazać ofertę, zbierać zapytania czy sprzedawać.',
    '2. Treść i zdjęcia: prawdziwe zdjęcia realizacji, nie obrazy z generatora; teksty z faktów firmy.',
    '3. Budowa obok starej strony: nową oglądasz pod adresem roboczym, zanim zastąpi obecną.',
    '4. Kontrola przed publikacją: automatyczna bramka sprawdza tytuły, opisy, mapę strony, dostępność i wagę plików; strona z błędem nie idzie na hosting.',
    '5. Przepięcie domeny: stare adresy przekierowujemy na nowe (301), żeby wyniki w Google nie prowadziły w próżnię.',
    '6. Pomiar: po publikacji PageSpeed na telefonie i komputerze; wynik publikujemy pod realizacją.',
    '',
    '## Fakty',
    '',
    '- Technologia: Astro (statyczne pliki HTML), PHP 8.4 na hostingu lh.pl dla formularzy i paneli, Cloudflare dla DNS i ochrony formularzy (Turnstile).',
    ...(zmierzone.length > 0
      ? [
          `- Szybkość: ${zmierzone.length} realizacji, PageSpeed Insights ${dataPomiaruPl} — wydajność na telefonie ${psiTelefon}, na komputerze ${psiKomputer}${wszystkieAgentowe ? `; przeglądanie agentowe ${agentoweMax}/${agentoweMax} na każdej` : ''}.`,
        ]
      : []),
    '- Każda strona ma osobne repozytorium kodu i osobny dostęp do panelu — strony klientów nie dzielą kodu ani haseł.',
    `- [Sprawdź szybkość swojej strony](${baza}/jak-sprawdzic-szybkosc-strony/): darmowy test w Google PageSpeed Insights i objaśnienie wyniku.`,
    '',
    '## Realizacje',
    '',
    ...realizacje.map((s) => {
      const opis = OPISY_REALIZACJI[s.data.adres] ?? s.data.opis ?? '';
      return `- [${s.data.nazwa}](${s.data.adres})${opis ? `: ${opis}` : ''}`;
    }),
    '',
    '## Strony tej witryny',
    '',
    `- [Strona główna](${baza}/): oferta, realizacje z wynikami PageSpeed, kontakt i dane spółki.`,
    `- [Tworzenie stron internetowych w Warszawie](${baza}/strony-internetowe-warszawa/): proces, czas, technologia, panel, hosting i realizacje z wynikami PageSpeed.`,
    `- [Tworzenie sklepów internetowych w Warszawie](${baza}/sklepy-internetowe-warszawa/): cztery drogi do sklepu, płatności, dostawy, koszty stałe.`,
    `- [Strona wizytówka dla firmy](${baza}/strona-wizytowka/): co zawiera, kiedy wystarczy i ile kosztuje.`,
    `- [Prowadzenie strony internetowej](${baza}/prowadzenie-strony-internetowej/): zmiany treści, pomiar, Search Console, wizytówka Google i raporty.`,
    `- [Cennik](${baza}/cena/): ceny netto wykonania i prowadzenia strony oraz sklepu, z kalkulatorem.`,
    `- [Kontakt i wycena](${baza}/kontakt/): formularz zapytania z dołączonym wyliczeniem z kalkulatora.`,
    `- [Pytania i odpowiedzi](${baza}/faq/): ${liczbaPytan} pytania o czas, panel, hosting, pocztę, sklep, PageSpeed i Google.`,
    `- [Efekty na stronach](${baza}/efekty/): efekty z wagą w kB i wynikiem PageSpeed.`,
    `- [Animacja sterowana przewijaniem](${baza}/efekty/animacja-przewijaniem/): przykład efektu z wagą skryptu i wynikiem PageSpeed.`,
    `- [Blog](${baza}/blog/): wpisy o budowie stron i sklepów — ceny, czas budowy, panel, wybór platformy.`,
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

  linie.push(
    '## Kontakt',
    '',
    `- E-mail: ${firma.email.toLowerCase()}${telefon ? ` · telefon: ${telefon}` : ''}`,
    `- ${nazwaSpolki}${adres ? `, ${adres}` : ''} · KRS ${firma.krs} · NIP ${firma.nip} · REGON ${firma.regon}`,
    '',
    '## Pliki dla maszyn',
    '',
    `- [Mapa strony](${baza}/sitemap.xml)`,
    `- [Kanał RSS bloga](${baza}/rss.xml)`,
    `- [Katalog zasobów ARD](${baza}/.well-known/ard.json)`,
    '',
  );

  return new Response(linie.join('\n'), { headers: { 'Content-Type': 'text/markdown; charset=utf-8' } });
};
