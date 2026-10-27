#!/usr/bin/env python3
"""psi-realizacje.py — pomiar PageSpeed Insights każdej realizacji z src/data/serwisy.json.

Wynik: src/data/pomiary.json (adres → data, mobile, desktop, teren), czytany przez
src/components/Kafelek.astro. Plik NIE jest w panelu CMS — odświeża go tylko ten skrypt.

Uruchomienie (z katalogu strony; klucz wyłącznie w zmiennej środowiska, nigdy w pliku):
  KEY=$(bash /home/ecopower/projekty/test-claude/potwor/scripts/klucze-dostepne.sh pagespeed-z440) \
    python3 -I scripts/psi-realizacje.py
Opcje: --adres <url> (tylko ten adres; reszta pliku bez zmian), --proby N (domyślnie 2).

Co mierzy (API v5, Lighthouse po stronie Google), osobno telefon (mobile) i komputer (desktop):
  · pięć kategorii: performance, accessibility, best-practices, seo, agentic-browsing — wynik 0–100 = score × 100;
    agentic-browsing ma tryb „fraction” (pagespeed.web.dev pokazuje np. 4/4) — liczymy zdane/wszystkie
    tą samą regułą co raport Lighthouse: pomijamy audyty notApplicable/manual, zdany = score ≥ 0,9;
  · metryki laboratoryjne FCP, LCP, TBT, CLS, Speed Index z `audits` (ms, CLS bez jednostki);
  · dane terenowe CrUX: loadingExperience.overall_category (strona), a gdy brak — originLoadingExperience
    (domena); brak obu = „brak” (strona nowa albo za mały ruch z 28 dni).
Błąd pomiaru po N próbach: dla tej strategii zostaje poprzedni wynik z pliku (z własną datą), a gdy
go nie ma — null (kafelek pokazuje wtedy „pomiar w toku”).
"""

import concurrent.futures
import datetime
import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path
from zoneinfo import ZoneInfo

KATALOG = Path(__file__).resolve().parent.parent
SERWISY = KATALOG / 'src' / 'data' / 'serwisy.json'
POMIARY = KATALOG / 'src' / 'data' / 'pomiary.json'
API = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
KATEGORIE = {
    'performance': 'wydajnosc',
    'accessibility': 'dostepnosc',
    'best-practices': 'sprawdzoneMetody',
    'seo': 'seo',
    'agentic-browsing': 'przegladanieAgentowe',
}
METRYKI = {
    'first-contentful-paint': 'fcp',
    'largest-contentful-paint': 'lcp',
    'total-blocking-time': 'tbt',
    'cumulative-layout-shift': 'cls',
    'speed-index': 'si',
}
STRATEGIE = ('mobile', 'desktop')
WARSZAWA = ZoneInfo('Europe/Warsaw')


def zapytaj(adres: str, strategia: str, klucz: str) -> dict:
    parametry = [('url', adres), ('strategy', strategia)]
    parametry += [('category', k) for k in KATEGORIE]
    parametry.append(('key', klucz))
    zadanie = urllib.request.Request(API + '?' + urllib.parse.urlencode(parametry))
    with urllib.request.urlopen(zadanie, timeout=240) as odp:
        return json.load(odp)


def ulamek(kategoria: dict, audyty: dict) -> tuple[int, int]:
    """Zdane/wszystkie jak w raporcie Lighthouse (tryb fraction)."""
    zdane = wszystkie = 0
    for ref in kategoria.get('auditRefs', []):
        a = audyty.get(ref['id'], {})
        tryb = a.get('scoreDisplayMode')
        if ref.get('group') == 'hidden' or tryb in ('manual', 'notApplicable', 'informative'):
            continue
        wszystkie += 1
        if (a.get('score') or 0) >= 0.9:
            zdane += 1
    return zdane, wszystkie


def wyciagnij(odp: dict) -> tuple[dict, str]:
    lr = odp['lighthouseResult']
    kat, aud = lr['categories'], lr['audits']
    wynik: dict = {}
    for k, nazwa in KATEGORIE.items():
        s = kat.get(k, {}).get('score')
        wynik[nazwa] = None if s is None else round(s * 100)
    if 'agentic-browsing' in kat:
        z, w = ulamek(kat['agentic-browsing'], aud)
        wynik['agentoweZdane'], wynik['agentoweWszystkie'] = z, w
    for a, nazwa in METRYKI.items():
        v = aud.get(a, {}).get('numericValue')
        wynik[nazwa] = None if v is None else (round(v, 3) if nazwa == 'cls' else round(v))
    wynik['lighthouse'] = lr.get('lighthouseVersion')
    czas = datetime.datetime.fromisoformat(lr['fetchTime'].replace('Z', '+00:00'))
    wynik['data'] = czas.astimezone(WARSZAWA).date().isoformat()
    teren = 'brak'
    for klucz_le in ('loadingExperience', 'originLoadingExperience'):
        kat_le = (odp.get(klucz_le) or {}).get('overall_category')
        if kat_le:
            teren = kat_le if klucz_le == 'loadingExperience' else f'{kat_le} (domena)'
            break
    return wynik, teren


def zmierz(adres: str, strategia: str, klucz: str, proby: int):
    for proba in range(1, proby + 1):
        try:
            return wyciagnij(zapytaj(adres, strategia, klucz))
        except urllib.error.HTTPError as e:
            # Bez e.url i bez repr — adres zapytania niesie klucz.
            blad = f'HTTP {e.code}'
        except (urllib.error.URLError, TimeoutError, KeyError, ValueError) as e:
            blad = type(e).__name__
        print(f'  {adres} {strategia}: próba {proba}/{proby} — {blad}', file=sys.stderr)
        if proba < proby:
            time.sleep(15)
    return None


def main() -> int:
    klucz = os.environ.get('KEY', '')
    if not klucz:
        print('Brak klucza w zmiennej KEY (polecenie w nagłówku skryptu).', file=sys.stderr)
        return 2
    argumenty = sys.argv[1:]
    proby = int(argumenty[argumenty.index('--proby') + 1]) if '--proby' in argumenty else 2
    tylko = argumenty[argumenty.index('--adres') + 1] if '--adres' in argumenty else None

    serwisy = json.loads(SERWISY.read_text(encoding='utf-8'))
    lista = serwisy['serwisy'] if isinstance(serwisy, dict) else serwisy
    adresy = [s['adres'] for s in lista if s.get('adres') and not s.get('ukryty')]
    if tylko:
        adresy = [a for a in adresy if a == tylko]
        if not adresy:
            print(f'{tylko}: nie ma go wśród nieukrytych realizacji w serwisy.json', file=sys.stderr)
            return 2

    stare = json.loads(POMIARY.read_text(encoding='utf-8')) if POMIARY.exists() else {}
    stare_pomiary = stare.get('pomiary', {})

    zadania = [(a, s) for a in adresy for s in STRATEGIE]
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pula:
        wyniki = dict(zip(zadania, pula.map(lambda z: zmierz(z[0], z[1], klucz, proby), zadania)))

    nowe = {} if not tylko else {a: v for a, v in stare_pomiary.items() if a in
                                 [s['adres'] for s in lista if not s.get('ukryty')]}
    for adres in adresy:
        wpis = {}
        teren = (stare_pomiary.get(adres) or {}).get('teren', 'brak')
        for s in STRATEGIE:
            r = wyniki[(adres, s)]
            if r is None:
                wpis[s] = (stare_pomiary.get(adres) or {}).get(s)
            else:
                wpis[s] = r[0]
                if s == 'mobile' or teren == 'brak':
                    teren = r[1]
        daty = [wpis[s]['data'] for s in STRATEGIE if wpis.get(s)]
        nowe[adres] = {'data': min(daty) if daty else None, **wpis, 'teren': teren}
        m, d = wpis.get('mobile') or {}, wpis.get('desktop') or {}
        skrot = lambda x: ' '.join(str(x.get(n, '–')) for n in KATEGORIE.values()) if x else 'brak pomiaru'
        print(f'{adres}  telefon: {skrot(m)}  |  komputer: {skrot(d)}  |  teren: {teren}')

    wynik = {
        '_opis': 'Wyniki PageSpeed Insights realizacji (scripts/psi-realizacje.py, docs/POMIARY.md). '
                 'Plik generowany — nie edytować ręcznie, nie jest w panelu CMS.',
        'pomiary': nowe,
    }
    POMIARY.write_text(json.dumps(wynik, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(f'zapisano {POMIARY.relative_to(KATALOG)}: {len(nowe)} adresów')
    return 0 if all(wyniki.values()) else 1


if __name__ == '__main__':
    sys.exit(main())
