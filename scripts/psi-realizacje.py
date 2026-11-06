#!/usr/bin/env python3
"""psi-realizacje.py — pomiar PageSpeed Insights każdej realizacji z src/data/serwisy.json.

Wynik: src/data/pomiary.json (adres → data, mobile, desktop, teren), czytany przez
src/components/Realizacje.astro. Plik NIE jest w panelu CMS — odświeża go tylko ten skrypt.

Uruchomienie (z katalogu strony; klucz wyłącznie w zmiennej środowiska, nigdy w pliku):
  KEY=$(bash /home/ecopower/projekty/test-claude/potwor/scripts/klucze-dostepne.sh pagespeed-z440) \
    python3 -I scripts/psi-realizacje.py
Opcje: --adres <url> (tylko ten adres; reszta pliku bez zmian), --proby N (domyślnie 2),
  --sucho (mierzy i wypisuje, nic nie zapisuje), --bez-commita (zapisuje plik, bez commita i pusha).
Bez tych dwóch flag (tryb cronu, /home/ecopower/projekty/WWW/narzedzia/psi-realizacje/uruchom.sh): po zapisie commit
samego pomiary.json komunikatem "Pomiar PSI realizacji <data>" i push na main (tożsamość i poświadczenie z .git/config
repo, bez tokenu w skrypcie). Gdy w drzewie są zmiany w innych śledzonych plikach albo gałąź nie jest main — plik się
zapisuje, ale commit i push są pomijane (kod 3), żeby nie wciągnąć cudzej pracy.

Historia (od 2026-11-06): każdy adres ma `historia: [{data, telefon, komputer}]` = wydajność mobile/desktop z danego
dnia, najstarszy pierwszy, najwyżej HISTORIA_MAKS wpisów; drugi pomiar tego samego dnia zastępuje wpis dnia.
Czyta ją src/components/zywe/WykresPsi.astro. Pomiary idą SZEREGOWO z odstępem 10 s (równoległe PSI dają timeouty).

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

import datetime
import json
import os
import subprocess
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
ODSTEP_S = 10
HISTORIA_MAKS = 60
PLIK_W_REPO = 'src/data/pomiary.json'


def git(*argumenty: str, sprawdz: bool = True) -> subprocess.CompletedProcess:
    return subprocess.run(['git', '-C', str(KATALOG), *argumenty], capture_output=True, text=True, check=sprawdz)


def repo_gotowe() -> str:
    """Pusty napis = można commitować; inaczej powód pominięcia commita."""
    if git('rev-parse', '--abbrev-ref', 'HEAD').stdout.strip() != 'main':
        return 'gałąź inna niż main'
    obce = [w[3:] for w in git('status', '--porcelain', '--untracked-files=no').stdout.splitlines() if w[3:] != PLIK_W_REPO]
    return f'zmiany w innych plikach: {", ".join(obce[:5])}' if obce else ''


def opublikuj(data: str) -> int:
    git('add', PLIK_W_REPO)
    if git('diff', '--cached', '--quiet', '--', PLIK_W_REPO, sprawdz=False).returncode == 0:
        print('pomiary.json bez zmian — commit pominięty')
        return 0
    git('commit', '--quiet', '-m', f'Pomiar PSI realizacji {data}', '--', PLIK_W_REPO)
    if git('push', '--quiet', 'origin', 'main', sprawdz=False).returncode != 0:
        # Ktoś wypchnął w międzyczasie: drzewo czyste poza naszym commitem, więc rebase jest bezpieczny.
        git('pull', '--rebase', '--quiet', 'origin', 'main')
        git('push', '--quiet', 'origin', 'main')
    print(f'commit i push: {git("rev-parse", "--short", "HEAD").stdout.strip()} Pomiar PSI realizacji {data}')
    return 0


def dopisz_historie(stara: list, data: str, mobile: dict | None, desktop: dict | None) -> list:
    wpis = {'data': data,
            'telefon': (mobile or {}).get('wydajnosc'),
            'komputer': (desktop or {}).get('wydajnosc')}
    historia = [h for h in (stara or []) if isinstance(h, dict) and h.get('data') != data]
    if wpis['telefon'] is not None or wpis['komputer'] is not None:
        historia.append(wpis)
    historia.sort(key=lambda h: h.get('data') or '')
    return historia[-HISTORIA_MAKS:]


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
    sucho = '--sucho' in argumenty
    commit = not sucho and '--bez-commita' not in argumenty
    powod = ''
    if commit:
        powod = repo_gotowe()
        if not powod:
            # Najpierw dogonić origin (tylko przewinięcie), żeby commit pomiaru nie rozjechał historii.
            if git('pull', '--ff-only', '--quiet', 'origin', 'main', sprawdz=False).returncode != 0:
                powod = 'git pull --ff-only nie przeszedł'

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
    wyniki = {}
    for i, (a, s) in enumerate(zadania):
        if i:
            time.sleep(ODSTEP_S)
        wyniki[(a, s)] = zmierz(a, s, klucz, proby)

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
        dzien = datetime.datetime.now(WARSZAWA).date().isoformat()
        swieze = {s: (wyniki[(adres, s)] or (None,))[0] for s in STRATEGIE}
        historia = dopisz_historie((stare_pomiary.get(adres) or {}).get('historia', []), dzien,
                                   swieze['mobile'], swieze['desktop'])
        nowe[adres] = {'data': min(daty) if daty else None, **wpis, 'teren': teren, 'historia': historia}
        m, d = wpis.get('mobile') or {}, wpis.get('desktop') or {}
        skrot = lambda x: ' '.join(str(x.get(n, '–')) for n in KATEGORIE.values()) if x else 'brak pomiaru'
        print(f'{adres}  telefon: {skrot(m)}  |  komputer: {skrot(d)}  |  teren: {teren}')

    wynik = {
        '_opis': 'Wyniki PageSpeed Insights realizacji (scripts/psi-realizacje.py, docs/POMIARY.md). '
                 'Plik generowany — nie edytować ręcznie, nie jest w panelu CMS.',
        'pomiary': nowe,
    }
    kod = 0 if all(wyniki.values()) else 1
    if sucho:
        print(f'--sucho: nic nie zapisano ({len(nowe)} adresów; historia np. {next(iter(nowe.values()), {}).get("historia", [])[-2:]})')
        return kod
    POMIARY.write_text(json.dumps(wynik, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
    print(f'zapisano {POMIARY.relative_to(KATALOG)}: {len(nowe)} adresów')
    if not commit:
        return kod
    if powod:
        print(f'commit i push pominięte: {powod}', file=sys.stderr)
        return 3
    return opublikuj(datetime.datetime.now(WARSZAWA).date().isoformat()) or kod


if __name__ == '__main__':
    sys.exit(main())
