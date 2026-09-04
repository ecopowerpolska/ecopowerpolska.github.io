# Przełączenie ecopowerpolska.pl na GitHub Pages

**Wykonuje Piotr.** Dopóki ten dokument nie zostanie przejechany do końca, strona żyje
na starym WordPressie na lh.pl i nic jej nie grozi.

---

## 1. Stan zmierzony 2026-09-04 (`dig`, nie z pamięci)

| Rekord | Wartość dzisiaj | Co robimy |
|---|---|---|
| `NS` | `ns.lh.pl`, `ns2.lighthosting.net` | 🟢 **NIE RUSZAMY** — strefa zostaje na lh.pl |
| `A` dla `@` | `178.211.137.59` (WordPress na lh.pl) | 🔴 **podmieniamy** na 4 adresy GitHuba |
| `A` dla `www` | `178.211.137.59` | 🔴 **kasujemy i zastępujemy** rekordem `CNAME` |
| `MX` | `5 mail17.lh.pl` | 🟢 **NIE RUSZAMY** |
| `TXT` (SPF) | `v=spf1 include:_spf.lh.pl -all` | 🟢 **NIE RUSZAMY** |
| `TXT` `_dmarc` | `v=DMARC1; p=quarantine; rua=mailto:dmarc-report@lh.pl;` | 🟢 **NIE RUSZAMY** |
| `CNAME` `autodiscover` | brak (zapytanie nie zwróciło nic — **nie ma też wildcardu**) | 🟢 nic do przepisania |

🔴 **Dlaczego poczta jest tu bezpieczna, choć zwykle to ona pada:** ostrzeżenie z `potwor-www` §O
dotyczy **przepięcia serwerów nazw** — wtedy cała strefa przenosi się do nowego dostawcy i MX, SPF,
DKIM, DMARC oraz `autodiscover`/`autoconfig` trzeba przepisać ręcznie, a zapomniany rekord psuje
pocztę po cichu. **Tutaj serwery nazw zostają na `ns.lh.pl`.** Zmieniamy dwa rekordy adresowe
wewnątrz strefy, która zostaje na miejscu — reszta wpisów jej nie zauważy.
**Warunek: zmieniaj wyłącznie rekordy z kolumny „podmieniamy". Nie „przenoś domeny".**

---

## 2. Adresy GitHub Pages — odczytane u źródła 2026-09-04

`docs.github.com/en/pages/configuring-a-custom-domain-for-your-github-pages-site/managing-a-custom-domain-for-your-github-pages-site`

🔴 **To są liczniki stanu.** Przed wpisaniem sprawdź tę stronę ponownie, jeśli od dziś minął
miesiąc. Nie przepisuj ich z pamięci przy kolejnej stronie.

**`A` dla `@` (cztery rekordy, wszystkie):**
```
185.199.108.153
185.199.109.153
185.199.110.153
185.199.111.153
```

**`AAAA` dla `@` (opcjonalne, ale zalecane — cztery rekordy):**
```
2606:50c0:8000::153
2606:50c0:8001::153
2606:50c0:8002::153
2606:50c0:8003::153
```

**`www` — `CNAME`, nie `A`:**
```
www  CNAME  ecopowerpolska.github.io.
```
🔴 Cel `CNAME` to `<konto>.github.io` **bez nazwy repozytorium**, nawet gdy repozytorium
nazywa się inaczej. Cytat ze źródła: *„The `CNAME` record should always point to
`<user>.github.io` or `<organization>.github.io`, excluding the repository name."*

---

## 3. Kolejność — ta i żadna inna

🔴 **Kolejność jest tu całą treścią.** Odwrotna daje albo utratę podglądu, albo realną przerwę
w działaniu strony:

- **CNAME w repo przed DNS-em** → stary WordPress serwuje dalej, nowa strona czeka gotowa.
  Przerwy nie ma. Podgląd `ecopowerpolska.github.io` zaczyna przekierowywać na docelową
  domenę — **to nie jest usterka, tylko oczekiwane zachowanie**.
- **DNS przed CNAME w repo** → apex wskazuje na GitHuba, GitHub nie wie, które repozytorium
  obsługuje tę domenę, i oddaje **404**. To jest przerwa w działaniu strony.

### Krok 1 — podgląd zaakceptowany
Strona działa i wygląda jak ma wyglądać pod `https://ecopowerpolska.github.io`.
Dopóki to nie jest prawdą, nie ruszaj dalej.

### Krok 2 — (opcjonalnie) skróć TTL
W panelu lh.pl obniż TTL rekordów `@` i `www` do najniższej dopuszczalnej wartości
i odczekaj tyle, ile wynosił stary TTL. Skraca to okno, w którym część świata widzi
jeszcze stare adresy.

### Krok 3 — trzy zmiany w repozytorium, jednym commitem

1. **Nowy plik `public/CNAME`** — jedna linia, bez `https://`, bez ukośnika:
   ```
   ecopowerpolska.pl
   ```
2. **`astro.config.mjs`** — `site` na docelowy adres:
   ```js
   site: 'https://ecopowerpolska.pl',
   ```
   🔴 **`base` nie występuje i wystąpić nie ma** — repozytorium jest witryną użytkownika.
   (Gdyby kiedyś było inne: to jest dokładnie ten moment, w którym `base` się USUWA.
   Zostawione łamie wszystkie linki wewnętrzne po cichu — build przechodzi, strona wygląda
   żywo, nawigacja prowadzi donikąd.)
3. **`public/robots.txt`** — ostatnia linia:
   ```
   Sitemap: https://ecopowerpolska.pl/sitemap.xml
   ```
   🔴 Nazwa pliku to `sitemap.xml`, nie `sitemap-index.xml` — hak `astro:build:done`
   w `astro.config.mjs` zmienia nazwę indeksu wygenerowanego przez `@astrojs/sitemap`
   przy każdym buildzie, bo bramka przed publikacją szuka dosłownie `dist/sitemap.xml`.

Commit → push do `main` → **poczekaj, aż przebieg Actions zaświeci na zielono.**

### Krok 4 — GitHub: Settings → Pages
Pole **Custom domain** ma pokazywać `ecopowerpolska.pl` (podłapane z pliku `CNAME`).
Jeśli nie — wpisz ręcznie i zapisz.

### Krok 5 — DNS w panelu lh.pl
Dopiero teraz. W strefie `ecopowerpolska.pl`:
- **skasuj** rekord `A` dla `@` ze starą wartością `178.211.137.59`;
- **dodaj** cztery rekordy `A` z §2 (i cztery `AAAA`, jeśli panel je przyjmuje);
- **skasuj** rekord `A` dla `www` (`178.211.137.59`) i **dodaj** `CNAME` z §2.
  ⚠️ Panel może odmówić dodania `CNAME`, dopóki istnieje `A` o tej samej nazwie — najpierw kasuj.
- **niczego więcej nie dotykaj.**

### Krok 6 — certyfikat i HTTPS
GitHub wystawia certyfikat Let's Encrypt sam, po tym jak zobaczy poprawny DNS
(bywa, że po kilkunastu minutach). Gdy w Settings → Pages przestanie się skarżyć,
**zaznacz „Enforce HTTPS"**.

### Krok 7 — sprawdzenie
```bash
dig +short A ecopowerpolska.pl          # cztery adresy 185.199.*
dig +short CNAME www.ecopowerpolska.pl  # ecopowerpolska.github.io.
dig +short MX ecopowerpolska.pl         # NADAL 5 mail17.lh.pl  ← poczta nietknięta
dig +short TXT ecopowerpolska.pl        # NADAL v=spf1 include:_spf.lh.pl -all
curl -sI https://ecopowerpolska.pl | head -3
curl -sI https://www.ecopowerpolska.pl | head -3
```
Oraz: wyślij i odbierz jednego maila na `kontakt@ecopowerpolska.pl`. **To jest test, który
naprawdę rozstrzyga** — `dig` pokazuje rekordy, list pokazuje działanie.

---

## 4. Stara strona

WordPress na lh.pl (`178.211.137.59`) **zostaje na miejscu i nietknięty** do końca tej procedury.
Po przełączeniu przestaje być odwiedzany, ale nadal stoi — decyzję o jego zdjęciu podejmuje Piotr
osobno, nie jest częścią tego dokumentu.
