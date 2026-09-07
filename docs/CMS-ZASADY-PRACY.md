# Praca z panelem Sveltia — zasady wyciągnięte z awarii 2026-09-07

**Rodzaj:** pułapka operacyjna

Dzień, w którym panel po raz pierwszy pracował naprawdę, przyniósł trzy różne awarie tej samej
klasy: **zapis klienta ginął w ciszy, a strona zostawała na starej wersji bez żadnego komunikatu**.
Wszystkie trzy są domknięte mechanizmem — poniżej objaw, przyczyna i co to teraz blokuje.

---

## 🔴 ① Nie wypychaj z terminala, gdy klient pracuje w panelu

**Objaw:** Piotr wprowadza zmianę, klika zapis, **nic się nie dzieje** — bez komunikatu błędu
i bez commita w repozytorium. Panel pokazuje zmianę, strona jej nie ma.

**Przyczyna, zmierzona:** Sveltia commituje mutacją GraphQL `createCommitOnBranch` z parametrem
`expectedHeadOid` — identyfikatorem czubka gałęzi **zapamiętanym przy wczytaniu panelu**.
W kodzie Sveltii nie ma ani ponowienia, ani obsługi konfliktu. Gdy w międzyczasie ktoś wypchnie
commit z terminala, czubek się przesuwa, GitHub odrzuca mutację i **zapis przepada bez śladu**.

**Dowód z metadanych commitów** — zapis z panelu wygląda inaczej niż zapis z terminala:

| co | `committer` | podpis |
|---|---|---|
| zapis z panelu | `GitHub <noreply@github.com>` | ✅ zweryfikowany PGP |
| push z terminala | `Piotr (EcoPower)` | niepodpisany |

2026-09-07 ostatni zapis z panelu to `8221eb5` o 10:23:39 UTC. Trzydzieści pięć sekund później
poszedł push z terminala (`033ded3`, 10:24:14) — i od tej chwili **żaden zapis Piotra nie przeszedł**.

**Zasada:** dopóki Piotr pracuje w panelu, **nie wypycha się niczego z terminala**. Gdy push jest
konieczny — mówi się o tym wprost: „wypchnąłem, odśwież panel przed kolejnym zapisem".
Odświeżenie `/admin/` (Ctrl+Shift+R) każe Sveltii pobrać aktualny czubek gałęzi.

---

## 🔴 ② Po każdej zmianie `config.yml` panel trzeba przeładować twardo

**Objaw:** Piotr zaznacza „ukryty", zapisuje, kafelek dalej stoi na stronie. Build zielony.

**Przyczyna:** przeglądarka trzyma starą `/admin/config.yml` (GitHub Pages podaje ją
z `cache-control: max-age=600`). Panel pracuje wtedy na **poprzedniej konfiguracji** i zapisuje
w starym układzie — do plików, których strona już nie czyta. Zdarzyło się trzy razy jednego dnia:
`allegro.md`, `studioagat-pl.md` i ukrycie allegro trafiły do plików-widm.

**Zasada:** twarde odświeżenie robi się **na samym `/admin/`**, nie na stronie głównej — to inny
adres i inny wpis w pamięci podręcznej. Po każdej zmianie konfiguracji panelu uprzedza się o tym
Piotra osobnym zdaniem.

---

## 🔴 ③ Żadne pole treści nie może być wymagane warunkowo przez schemat

**Objaw:** wpis zapisany w panelu zatrzymuje build w Actions, strona zostaje na poprzedniej wersji,
w panelu ani jednego komunikatu.

**Przyczyna:** schemat kolekcji wymagał `alt` warunkowo („gdy jest obrazek"). Panel nie umie
wymusić pola zależnie od innego, więc warunek pilnował **builda**, nie formularza. Pierwszy realny
zapis klienta ze zdjęciem bez opisu (`Pan Wylewka`) zamroził publikację całej strony — razem
z niezwiązaną edycją danych firmy zrobioną minutę później.

**Domknięcie mechanizmem (2026-09-07):**
- schemat kolekcji **nie ma już walidacji zabijającej build** — długości i postać adresu pilnuje
  formularz w `public/admin/config.yml`, gdzie błąd widzi człowiek i może go poprawić;
- parser w `src/content.config.ts` **sprowadza każde pole do postaci, której schemat nie odrzuci**:
  `null` → `undefined`, wartość nietekstowa → tekst, `ukryty` → wartość logiczna, ścieżka obrazka
  → jedna kanoniczna postać;
- sprawdzone przebiegiem: wpis z `opis: null`, `alt: null` i podpisem 300-znakowym **buduje się
  poprawnie**. Przed poprawką `null` i tekst dłuższy niż 160 znaków wywracały build.

**Zasada ogólna:** walidacja treści należy do formularza, nie do builda. Build ma się udać zawsze —
najgorsze, co wolno mu zrobić, to pokazać brzydki tekst; nigdy nie zamrozić strony.
