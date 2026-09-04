# Pierwszy przebieg — polecenia dla Piotra

Wszystko poniżej **uruchamia Piotr** (kontrakt warsztatu §4.2: instalacje, buildy, publikacja,
pierwsze uruchomienia). Kod jest napisany i sprawdzony walidatorem statycznym; **nie był
zbudowany ani wdrożony** — bo to jest przebieg.

Katalog roboczy dla wszystkich poleceń: `/home/ecopower/projekty/WWW/strony/ecopowerpolska.pl`

---

## 0. Zanim cokolwiek — rozstrzygnięcie z `docs/BRAKI.md` ① i ②

Repozytorium publiczne (konto Free) czy prywatne (konto Pro)? I jaka nazwa repo?
**Bez tej odpowiedzi kroki 4–6 nie mają dokąd pójść.** Kroki 1–3 można zrobić od razu.

---

## 1. Instalacja zależności

```bash
npm install astro@latest @astrojs/sitemap@latest
npm install -D @astrojs/check@latest typescript@latest
```

🔴 **Dlaczego `@latest`, a nie numery wpisane w `package.json`:** wersji Astro nie wpisuje się
z pamięci modelu (kontrakt §4.3, zakaz nr 5 skilla `potwor-astro`). To polecenie **samo zapisuje**
rozwiązane numery w `package.json` i tworzy `package-lock.json`, którego wymaga `npm ci`
w GitHub Actions. Blok `dependencies` jest dziś celowo pusty.

**Nie uruchamiaj `npm create astro@latest`** — kreator zakłada projekt od zera i pytałby
o nadpisanie plików, które już tu leżą. Szkielet jest kompletny, brakuje wyłącznie zależności.

Po instalacji — odczyt stanu, nie założenie:
```bash
npx astro info
```

## 2. Pętla weryfikacji (kolejność obowiązkowa, bez skrótów)

```bash
npm run guard          # walidator reguł projektu — MA WYPISAĆ WŁASNY WYNIK
npx astro check        # typy i diagnostyka Astro
npm run build          # kompilator Astro jest surowy: niezamknięty tag = błąd
npm run guard:build    # audyt dist: waga JS/CSS per strona + podstawy SEO
```
albo jednym poleceniem: `npm run verify`

🔴 **Milczenie `npm run guard` jest sygnałem awarii, nie sukcesu** — znaczy, że skrypt nie
znalazł walidatora i skończył się kodem 0. Poprawny wynik na czystym projekcie brzmi
`astro-guard: czysto. Zero naruszeń.` albo wypisuje listę naruszeń.

**Stan zmierzony 2026-09-04:** `npm run guard` → `astro-guard: czysto. Zero naruszeń.`

⚠️ **Jedna rzecz do wiedzenia o tym „czysto":** reguła AG004 (brak `prefetch`) **nie zapaliła się,
choć powinna** — walidator sprawdza obecność słowa `prefetch` w treści `astro.config.mjs`,
a to słowo pada tam w komentarzu wyjaśniającym, dlaczego prefetchu NIE MA. Ustawienia
naprawdę nie ma i tak ma zostać (cała witryna jest zadeklarowana jako zero-JS, a prefetch
dokłada skrypt kliencki). **Zielony wynik jest tu prawdziwy, ale nie z tego powodu, z którego
wygląda.** To ta sama klasa usterki co samoskanowanie walidatora (patrz nagłówek
`scripts/validate-astro.mjs`) — zgłoszona do kanonu razem z tamtą.

**Czego się spodziewać w audycie `dist` po pierwszym buildzie:**
**`Stron bez JavaScriptu: 2/2`** (strona główna i 404). Jeśli pokaże inaczej — coś dołożyło JS
i trzeba ustalić co, zanim pójdzie dalej; `zeroJsRoutes: ["/**"]` zapali wtedy B04 jako BŁĄD.

## 3. Podgląd lokalny

```bash
npm run dev      # serwer roboczy
npm run preview  # serwuje TO, CO ZBUDOWANE — nie źródła
```

## 4. Repozytorium — decyzja Piotra z 2026-09-04: **publiczne, `ecopowerpolska.github.io`**

🔴 **KOLEJNOŚĆ: repo → Pages → DOPIERO PUSH.** Ustawienie „Source: GitHub Actions" przed
pierwszym pushem oszczędza typowe wywalenie się zadania `deploy` na świeżym repozytorium.
⚠️ Ta kolejność wynika z ostrożności, nie z cytatu w dokumentacji — gdyby `deploy` mimo to
padł, przepływ ma `workflow_dispatch`, więc powtarza się go przyciskiem „Run workflow"
w zakładce Actions, bez nowego commitu.

🔴 **`gh` NIE JEST zainstalowany na tej maszynie** (sprawdzone 2026-09-04). Albo instalujesz
go osobno, albo — prościej — zakładasz repozytorium w przeglądarce.

**Krok 4a — commit lokalny:**
```bash
git init -b main
git add -A
git commit -m "Strona ecopowerpolska.pl — agregat serwisów, Astro + GitHub Pages"
```

**Krok 4b — repozytorium** (`github.com/new`, zalogowany jako `ecopowerpolska`):
nazwa `ecopowerpolska.github.io` · **Public** · **bez** README, `.gitignore` i licencji
(pliki są już lokalnie). Nazwa jest wolna — sprawdzone 2026-09-04.
Potem: `git remote add origin <adres z ekranu>`

🔴 **To jest krok NIEODWRACALNY w skutkach.** Od chwili pushu kod i **cała historia commitów**
są jawne. Późniejsze sprywatyzowanie repozytorium nie cofa tego, co zdążyło zostać
skopiowane. W katalogu strony sprawdzono brak sekretów (tokeny, klucze, hasła, prywatne
adresy naszej sieci) — 2026-09-04, nic nie znaleziono.

**Krok 4c — Pages, JESZCZE PRZED PUSHEM:**
**Settings → Pages → Source: `GitHub Actions`.**
🔴 Nie `Deploy from a branch` — to inny, starszy mechanizm, nasz przepływ z nim nie współpracuje.
Ten krok jest w pełni odwracalny.

**Krok 4d — push:**
```bash
git push -u origin main
```

## 6. Obserwacja pierwszego przebiegu

Zakładka **Actions**. Przepływ ma dwa zadania: `build` i `deploy`.
Adres podglądu pojawia się w wyniku zadania `deploy` — powinien brzmieć
`https://ecopowerpolska.github.io`.

**⚠️ To jest pierwszy przepływ GitHub Pages w tym warsztacie — pierwszy przebieg jest POMIAREM.
Co konkretnie zapisać w tym pliku po przebiegu:**

1. Czy `withastro/action@v6` zostawia `dist/` tam, gdzie audyt go szuka — czyli czy krok
   „astro-guard — audyt dist" w ogóle znalazł katalog. **To jedyne niepewne miejsce w przepływie.**
   Gdyby zgłosił „brak katalogu dist" — audyt trzeba przenieść do osobnego zadania z własnym
   buildem albo zastąpić `withastro/action` jawnymi krokami `npm ci && npm run build`.
2. Jakiej wersji Node użył `withastro/action` — czy zgadza się z `.nvmrc` (24). Akcja przyjmuje
   wejście `node-version`, ale jego nazwy **nie potwierdziłem u źródła**, więc go nie ustawiłem.
3. Ile minut zajął cały przebieg (przy repo prywatnym to zużycie z puli konta).
4. Czy Pages faktycznie serwuje `404.html` pod nieistniejącym adresem.

## 7. Bramka przed publikacją

Z katalogu warsztatu (`/home/ecopower/projekty/WWW`):
```bash
python3 narzedzia/bramka/bramka-przed-publikacja.py \
    strony/ecopowerpolska.pl/dist --motyw strony/ecopowerpolska.pl/src/styles/motyw.css
```
🔴 Sekcje `WYMAGA OKA` i `NIEZMIERZONE` **nie są pozycjami zdanymi**. „Brak blokad" znaczy
„to, co skrypt umie sprawdzić, przeszło" — nie „strona jest w porządku".

## 8. Kontrola cudzej roboty

Podagenta `nadi` uruchamia **wyłącznie Piotr** (kontrakt §4.7). To jest właściwy moment:
kod napisała ta sesja, więc jej własne „gotowe" nie jest weryfikacją.

## 9. Panel treści — pierwsze logowanie

`https://ecopowerpolska.github.io/admin/` → „Sign In with Token" → token GitHuba.
🔴 **Token jest sekretem: nie wchodzi do repo, do plików ani do rozmowy.**

⚠️ To pierwszy panel Sveltia w tym warsztacie — **pierwsze logowanie i pierwszy zapis są
pomiarem konfiguracji**. Sprawdź w tej kolejności: (1) czy panel się otwiera i loguje,
(2) czy widzi kolekcję „Serwisy" i dwa istniejące wpisy, (3) czy **zapis nowego kafelka
z obrazkiem** ląduje w `src/assets/serwisy/` i czy ścieżka we frontmatterze zaczyna się od
`../../assets/serwisy/`. Punkt (3) jest jedynym realnie niepewnym — jeśli ścieżka wyjdzie inna,
build stanie z błędem schematu i trzeba poprawić `media_folder`/`public_folder`
w `public/admin/config.yml`.

## 10. Przełączenie domeny

Osobny dokument: **`docs/przelaczenie-domeny.md`**. Dopiero po zaakceptowaniu podglądu.
