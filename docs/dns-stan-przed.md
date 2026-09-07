# Stan DNS domeny ecopowerpolska.pl PRZED przełączeniem na GitHub Pages

**Rodzaj:** cechy infrastruktury
**Odczytane 2026-09-07** poleceniem `dig` z serwera autorytatywnego `ns.lh.pl`, nie z pamięci.
**To jest PUNKT POWROTU.** Gdy przełączenie pójdzie źle, przywrócenie tych wartości cofa wszystko.

---

## 🔴 Rejestrator to OVH, ale STREFA JEST W lh.pl — to nie to samo

Zmierzone dwoma niezależnymi odczytami:

| pytanie | odpowiedź | czym sprawdzone |
|---|---|---|
| Kto jest **rejestratorem** domeny? | **OVH SAS** | RDAP rejestru NASK (`rdap.dns.pl`) |
| Kto **prowadzi strefę** (gdzie edytuje się rekordy)? | **lh.pl** — `ns.lh.pl`, `ns2.lighthosting.net` | `dig NS`, potwierdzone przez `SOA` |

**Konsekwencja praktyczna:** rekordy zmienia się **w panelu lh.pl**. Edytor strefy w OVH albo
nie istnieje dla tej domeny, albo istnieje i **jest ignorowany przez internet** — dopóki serwery
nazw wskazują lh.pl, liczy się wyłącznie strefa tam. Wpisanie rekordów w OVH bez zmiany NS
**nie zmieni niczego** i wygląda przy tym jak robota wykonana.

Właściciel wpisany w rejestrze: STREFA BADAŃ PIOTR CZWARNOWSKI.

---

## Komplet rekordów, stan 2026-09-07 (TTL 3600 s = 1 h przy każdym)

| Typ | Nazwa | Wartość | Co z tym robimy |
|---|---|---|---|
| `NS` | `@` | `ns.lh.pl.` · `ns2.lighthosting.net.` | 🟢 **NIE RUSZAMY** — strefa zostaje na lh.pl |
| `SOA` | `@` | `ns.lh.pl. root.lh.pl. 2008052701 1200 600 604800 86400` | 🟢 nie rusza się ręcznie |
| `A` | `@` | `178.211.137.59` (WordPress na lh.pl) | 🔴 **podmieniamy** na 4 adresy GitHuba |
| `AAAA` | `@` | **brak rekordu** | 🔵 opcjonalnie dokładamy 4 adresy IPv6 GitHuba |
| `A` | `www` | `178.211.137.59` | 🔴 **kasujemy**, w zamian `CNAME` |
| `CNAME` | `www` | **brak rekordu** | 🔴 **zakładamy** → `ecopowerpolska.github.io.` |
| `MX` | `@` | `5 mail17.lh.pl.` | 🟢 **NIE RUSZAMY** — poczta zostaje na lh.pl |
| `TXT` (SPF) | `@` | `v=spf1 include:_spf.lh.pl -all` | 🟢 **NIE RUSZAMY** |
| `TXT` (DMARC) | `_dmarc` | `v=DMARC1; p=quarantine; rua=mailto:dmarc-report@lh.pl;` | 🟢 **NIE RUSZAMY** |
| `TXT` (DKIM) | `default._domainkey` | klucz RSA, selektor `default` — pełna wartość niżej | 🟢 **NIE RUSZAMY** |
| `CAA` | `@` | **brak rekordu** | 🟢 brak jest w porządku — bez CAA każdy urząd może wystawić certyfikat, więc Let's Encrypt GitHuba nie jest blokowany |
| `TXT` | `_github-pages-challenge-ecopowerpolska` | **brak rekordu** | 🔴 **zakładamy** wartością z API GitHuba (weryfikacja domeny) |

**Pełna wartość DKIM (selektor `default`), gdyby trzeba było ją odtworzyć:**

```
"v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAyqXLWcHX/AeKBAXNJy9LPenVdFlSI9vomJy4wyqpf14+IHtWoJoQe7XHeaCFJEoO1ElZu8G5kGe1PW5wphkWKCkQOlKQTRR21iyi88Qqf536ztbQwEVFAdpzZJitq+m0ju2NmJJR9X" "bVLwogaj7hYMCGU1hFw6FFwtQQK2Sxms6bmPYThvxI44gc3bvuDzf1+//xUa7cENSME03+HllAO6FjqqAU5w/moGej1hsmIekaA4TT4B8j8+x30ESi/LbT/LajkkFkH/uvRklrPDT86IavT8AO0GQh/P8hF32EWi8pBKVQZLZAIDQf/EBg1MUFPlZ8QPPK4EyK3MQWUM" "EFJQIDAQAB"
```

🔴 **Selektory DKIM inne niż `default` — NIE USTALONO.** Sprawdziłem `default`, `mail`, `dkim`,
`lh`, `selector1`, `selector2`, `k1`, `s1`; odpowiedział wyłącznie `default`. DNS nie pozwala
wylistować poddomen, więc selektora o nazwie, której nie zgadłem, tą drogą nie znajdę.
Pewną listę daje wyłącznie panel lh.pl. **Przy tym przełączeniu to nie ma znaczenia** —
rekordów DKIM nie dotykamy — ale gdyby kiedyś przenosić strefę, ta luka jest do zamknięcia
przed przeniesieniem, nie po.

---

## Stan GitHub Pages przed zmianą (API, 2026-09-07)

```
status .................... built
cname ..................... (puste — domena własna nie jest ustawiona)
html_url .................. https://ecopowerpolska.github.io/
build_type ................ workflow
public .................... true
https_enforced ............ true
protected_domain_state .... (puste)
```

---

## Jak cofnąć przełączenie

W panelu lh.pl przywrócić z tabeli wyżej: `A @` → `178.211.137.59`, `A www` → `178.211.137.59`,
skasować `CNAME www` i dołożone `AAAA @`. W repozytorium strony usunąć `public/CNAME`
i cofnąć `site` w `astro.config.mjs`; w API Pages wyczyścić `cname`. Rekordy poczty przez cały
czas zostają nietknięte, więc cofanie ich nie dotyczy.
