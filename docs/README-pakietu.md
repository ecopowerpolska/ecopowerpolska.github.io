# astro-guard

Warstwa wsparcia i walidacji dla Claude Code pracującego nad stronami w Astro.

Problem, który rozwiązuje: agent piszący kod Astro odtwarza wzorce z treningu, a te są
starsze niż framework. Najbardziej wymowny przykład — `output: 'hybrid'` zniknęło w Astro 5,
ale wraca w podpowiedziach tak uporczywie, że zespół Astro dopisał osobny komunikat błędu
adresowany wprost do ludzi **i modeli**. Sama instrukcja tekstowa tego nie zatrzyma.
Zatrzymuje to sprzężenie zwrotne: reguła → wykrycie → komunikat zwrotny w tej samej turze.

## Trzy warstwy

| Warstwa | Plik | Kiedy działa |
|---|---|---|
| Kontrakt | `CLAUDE.md` | zawsze w kontekście sesji |
| Wzorce | `.claude/skills/astro-guard/SKILL.md` | ładowane, gdy agent dotyka konfiguracji, hydratacji, obrazów, kolekcji |
| Egzekucja | `scripts/*.mjs` + hooki | po każdym zapisie pliku i przed zakończeniem tury |

Kontrakt mówi, co robić. Skill pokazuje jak. Walidator sprawdza, czy faktycznie tak zrobiono —
i tylko ta trzecia warstwa jest deterministyczna.

## Instalacja w projekcie Astro

```bash
# 1. skopiuj warstwę wsparcia do repozytorium ze stroną
cp -r astro-guard/{CLAUDE.md,scripts,docs,guard.config.json} twoj-projekt/
cp -r astro-guard/.claude twoj-projekt/

# 2. podłącz skrypty
cd twoj-projekt
npm pkg set scripts.guard="node scripts/validate-astro.mjs"
npm pkg set scripts.guard:build="node scripts/audit-dist.mjs"
npm pkg set scripts.verify="npm run guard && astro check && astro build && npm run guard:build"

# 3. pierwsze uruchomienie na istniejącym kodzie
npm run guard
```

Zero zależności, Node 22+. Nic nie instaluje i nic nie modyfikuje — tylko czyta i raportuje.

## Użycie

```bash
npm run guard                              # cały projekt
node scripts/validate-astro.mjs --strict   # ostrzeżenia też blokują (tryb dla CI)
node scripts/validate-astro.mjs --json     # wyjście maszynowe
npm run build && npm run guard:build       # waga JS/CSS per strona + SEO w dist/
npm run verify                             # pełna pętla przed zgłoszeniem gotowości
```

Kody wyjścia: `0` czysto, `2` naruszenia blokujące.

## Sprzężenie z Claude Code

`.claude/settings.json` podpina dwa hooki:

- **PostToolUse** (`Edit|Write`) — waliduje właśnie zapisany plik. Edycja już się wykonała,
  więc kod wyjścia 2 nie cofa zmiany, tylko natychmiast pokazuje agentowi, co poprawić.
  To najkrótsza możliwa pętla zwrotna: błąd wraca w tej samej turze, nie po pięciu plikach.
- **Stop** — walidacja całego drzewa, zanim agent zakończy turę. Kod wyjścia 2 nie pozwala
  zakończyć pracy z błędami. Wrapper ma licznik (maks. 2 blokady na sesję), więc nie zapętli
  agenta, gdy naprawa naprawdę wymaga decyzji człowieka.

Hooki to wykonanie dowolnego kodu z twoimi uprawnieniami — przeczytaj
`.claude/hooks/astro-guard-hook.mjs`, zanim to włączysz. Podgląd aktywnej konfiguracji: `/hooks`.

## CI

```yaml
- uses: actions/setup-node@v4
  with: { node-version: 22 }
- run: npm ci
- run: node scripts/validate-astro.mjs --strict
- run: npx astro check
- run: npm run build
- run: node scripts/audit-dist.mjs
```

Ta sama pętla lokalnie i w CI. Jeśli agent obszedł regułę przez `guard:allow`,
w diffie widać uzasadnienie — a review dotyczy decyzji, nie samego faktu naruszenia.

## Utrzymanie

Reguły starzeją się razem z frameworkiem. Przy każdym majorze Astro:

1. przeczytaj oficjalny przewodnik migracji,
2. dopisz nowo usunięte API do `LINE_RULES` w walidatorze,
3. zaktualizuj tabelę wersji w `CLAUDE.md` i wzorce w skillu,
4. przenieś reguły dotyczące zniesionych ograniczeń do sekcji historycznej zamiast je kasować —
   modele nadal będą podpowiadać stary kod jeszcze długo po tym, jak zniknie z dokumentacji.

Katalog wszystkich reguł z uzasadnieniami: `docs/rules.md`.
