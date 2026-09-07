# Przełączenie ecopowerpolska.pl na GitHub Pages — adresem docelowym jest www

**Rodzaj:** instrukcja
**Wykonuje Piotr** (rekordy DNS). Sesja WWW robi część repozytoryjną i sprawdzenia.
**Stan zmierzony 2026-09-07** — pełny komplet rekordów sprzed zmiany, z TTL-ami i sposobem
cofnięcia, leży w **`docs/dns-stan-przed.md`**. Ten dokument mówi, CO zrobić; tamten — do czego wrócić.

🔴 **Dopóki ten dokument nie zostanie przejechany do końca, strona żyje na starym WordPressie
na lh.pl i nic jej nie grozi.**

---

## 1. Rejestrator to OVH, ale STREFA jest w lh.pl — od tego zależy, gdzie klikać

| pytanie | odpowiedź (2026-09-07) | czym sprawdzone |
|---|---|---|
| Kto jest **rejestratorem**? | **OVH SAS** | RDAP rejestru NASK |
| Gdzie **edytuje się rekordy**? | **w panelu lh.pl** — `ns.lh.pl`, `ns2.lighthosting.net` | `dig NS`, potwierdzone `SOA` |

🔴 **Wpisanie rekordów w edytorze strefy OVH nie zmieni NICZEGO, dopóki serwery nazw wskazują
lh.pl** — a wygląda przy tym jak robota wykonana. Liczy się wyłącznie strefa u tego, na kogo
wskazują `NS`. W OVH zmienia się **delegację NS**, nie rekordy.

**Dwie drogi — rozstrzyga Piotr:**

| | Co robimy | Poczta | Ile pracy |
|---|---|---|---|
| **(a) ZOSTAWIAMY NS na lh.pl** — rekomendacja | dwie zmiany rekordów w panelu lh.pl | 🟢 **zero ryzyka** — MX, SPF, DKIM, DMARC zostają nietknięte, bo strefa się nie przenosi | kilka minut |
| **(b) przenosimy strefę do OVH** | w OVH zmienić NS na własne, potem **odtworzyć CAŁĄ strefę** z `docs/dns-stan-przed.md`, łącznie z MX, SPF, DKIM i DMARC | 🔴 **realne ryzyko przerwy** — zapomniany rekord psuje pocztę po cichu, a `potwor-www` §O ostrzega przed tym wprost | godziny plus czas propagacji |

**Rekomendacja: (a).** Zadanie brzmi „strona ma stanąć na GitHub Pages, poczta zostaje na lh.pl".
Droga (a) robi dokładnie to i nie dotyka ani jednego rekordu poczty. Droga (b) rozwiązuje problem,
którego nie mamy, i wprowadza ten, którego najbardziej nie chcemy.
🔴 **Selektory DKIM inne niż `default` są NIEUSTALONE** (DNS nie pozwala listować poddomen) —
przy drodze (b) trzeba je najpierw wyciągnąć z panelu lh.pl, **przed** przeniesieniem, nie po.

---

## 2. Adresy GitHub Pages — odczytane u źródła

`docs.github.com/en/pages/configuring-a-custom-domain-for-your-github-pages-site/managing-a-custom-domain-for-your-github-pages-site`

🔴 **To są liczniki stanu.** Sprawdź tę stronę ponownie, jeśli od dziś minął miesiąc.

**`A` dla `@` (cztery rekordy):** `185.199.108.153` · `185.199.109.153` · `185.199.110.153` · `185.199.111.153`
**`AAAA` dla `@` (opcjonalne, zalecane):** `2606:50c0:8000::153` · `2606:50c0:8001::153` · `2606:50c0:8002::153` · `2606:50c0:8003::153`
**`www` — `CNAME` na `ecopowerpolska.github.io.`**

🔴 Cel `CNAME` to `<konto>.github.io` **bez nazwy repozytorium**, nawet gdy repozytorium nazywa
się inaczej. Cytat ze źródła: *„The `CNAME` record should always point to `<user>.github.io` or
`<organization>.github.io`, excluding the repository name."*

---

## 3. Dlaczego adresem kanonicznym jest `www`, a nie apex

Wybór Piotra 2026-09-07. Apex (`ecopowerpolska.pl`) **nie znika** — GitHub Pages przekierowuje
go `301` na `www`, gdy apex wskazuje jego cztery adresy `A`, a `public/CNAME` niesie wariant
z „www". Jeden adres jest kanoniczny, drugi na niego prowadzi; dokładnie jeden komplet treści
w wyszukiwarkach.

---

## 4. Kolejność — ta i żadna inna

🔴 **Kolejność jest tu całą treścią**, ale ma dziś JEDEN nowy warunek, którego nie było
2026-09-04: **panel treści jest w użyciu.** Piotr edytuje stronę pod
`https://ecopowerpolska.github.io/admin/`.

🔴 **Wdrożenie `public/CNAME` sprawia, że `ecopowerpolska.github.io` zaczyna przekierowywać na
`www.ecopowerpolska.pl`.** Dopóki DNS nie jest przełączony, ten adres pokazuje **stary WordPress**
— czyli **panel przestaje być dostępny** aż do wpisania rekordów. To nie jest usterka, to
mechanizm; ale to znaczy, że **krok repozytoryjny i krok DNS robi się w jednym posiedzeniu**,
nie „kiedyś potem".

### Krok 1 — podgląd zaakceptowany
Strona działa i wygląda jak ma wyglądać pod `https://ecopowerpolska.github.io`.
Dopóki to nie jest prawdą, nie ruszaj dalej.

### Krok 2 — (opcjonalnie) skróć TTL
W panelu lh.pl obniż TTL rekordów `@` i `www` (dziś **3600 s**) do najniższej dopuszczalnej
wartości i odczekaj godzinę. Skraca okno, w którym część świata widzi jeszcze stare adresy.

### Krok 3 — repozytorium (robi sesja WWW, jednym commitem)
- `public/CNAME` z treścią `www.ecopowerpolska.pl`;
- `astro.config.mjs` → `site: 'https://www.ecopowerpolska.pl'` (z tej jednej linii biorą się
  canonical, `og:url`, sitemapa i `url` w danych strukturalnych — **nie ma drugiego miejsca**);
- `public/robots.txt` → `Sitemap: https://www.ecopowerpolska.pl/sitemap.xml`;
- `public/admin/index.html` → canonical panelu na `https://www.ecopowerpolska.pl/admin/`.
Potem `npm run verify`, commit, push, przebieg Actions na zielono.

### Krok 4 — DNS w panelu lh.pl (klika Piotr) — lista w sekcji 5

### Krok 5 — GitHub: domena i certyfikat
Settings → Pages → Custom domain: `www.ecopowerpolska.pl`. **`Enforce HTTPS` zaznacza się
DOPIERO**, gdy GitHub napisze, że certyfikat jest wystawiony — wcześniej pole jest nieaktywne,
bo Let's Encrypt musi najpierw zobaczyć domenę wskazującą na Pages.
🔵 **Weryfikacja domeny** (Settings → Pages → Add a domain) dokłada rekord
`TXT _github-pages-challenge-ecopowerpolska` i **blokuje przejęcie domeny przez cudze repo**.
Token pokazuje się dopiero po kliknięciu „Add a domain" — nie da się go pobrać z API.
Opcjonalne, ale zalecane.

### Krok 6 — sprawdzenie
`https://www.ecopowerpolska.pl` → 200 i nowa strona · `https://ecopowerpolska.pl` → 301 na www ·
`http://` → przekierowanie na `https` · `ecopowerpolska.github.io` → przekierowanie na www ·
`https://www.ecopowerpolska.pl/admin/` → panel się ładuje ·
**poczta: `dig MX` i `dig TXT` mają oddać to samo, co `docs/dns-stan-przed.md`** — i wyślij sobie
próbną wiadomość na adres `@ecopowerpolska.pl`, bo `dig` sprawdza rekordy, nie doręczenie.

---

## 5. Lista rekordów dla panelu lh.pl — dokładnie to, co wpisać

| Akcja | Typ | Nazwa | TTL | Wartość |
|---|---|---|---|---|
| 🔴 **ZMIEŃ** | `A` | `@` | 3600 | `185.199.108.153` |
| 🔴 **DODAJ** | `A` | `@` | 3600 | `185.199.109.153` |
| 🔴 **DODAJ** | `A` | `@` | 3600 | `185.199.110.153` |
| 🔴 **DODAJ** | `A` | `@` | 3600 | `185.199.111.153` |
| 🔵 dodaj (zalecane) | `AAAA` | `@` | 3600 | `2606:50c0:8000::153` |
| 🔵 dodaj (zalecane) | `AAAA` | `@` | 3600 | `2606:50c0:8001::153` |
| 🔵 dodaj (zalecane) | `AAAA` | `@` | 3600 | `2606:50c0:8002::153` |
| 🔵 dodaj (zalecane) | `AAAA` | `@` | 3600 | `2606:50c0:8003::153` |
| 🔴 **SKASUJ** | `A` | `www` | — | `178.211.137.59` |
| 🔴 **DODAJ** | `CNAME` | `www` | 3600 | `ecopowerpolska.github.io.` (z kropką na końcu) |
| 🔵 dodaj (po kroku 5) | `TXT` | `_github-pages-challenge-ecopowerpolska` | 3600 | token z GitHuba |

**ZOSTAJE BEZ ZMIAN — nie tykać (wartości sprzed zmiany, do porównania po):**

| Typ | Nazwa | Wartość |
|---|---|---|
| `NS` | `@` | `ns.lh.pl.` · `ns2.lighthosting.net.` |
| `SOA` | `@` | `ns.lh.pl. root.lh.pl. …` |
| `MX` | `@` | `5 mail17.lh.pl.` |
| `TXT` (SPF) | `@` | `v=spf1 include:_spf.lh.pl -all` |
| `TXT` (DMARC) | `_dmarc` | `v=DMARC1; p=quarantine; rua=mailto:dmarc-report@lh.pl;` |
| `TXT` (DKIM) | `default._domainkey` | klucz RSA — pełna wartość w `docs/dns-stan-przed.md` |

🔴 **Apex `A` ma mieć CZTERY rekordy, nie jeden.** Jeden adres działa, ale znosi redundancję,
dla której GitHub je publikuje.

---

## 6. Stara strona na lh.pl

**Nie kasujemy jej.** Po przełączeniu DNS przestaje być widoczna pod tą domeną, ale pliki zostają
na hostingu do decyzji Piotra. Hosting lh.pl pozostaje opłacony, bo **stoi na nim poczta**.

## 7. Jak cofnąć

Wartości sprzed zmiany i pełna procedura powrotu: **`docs/dns-stan-przed.md`**, sekcja
„Jak cofnąć przełączenie".
