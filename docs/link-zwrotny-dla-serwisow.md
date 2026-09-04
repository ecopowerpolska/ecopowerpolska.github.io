# Link zwrotny do wklejenia na serwisach — gotowe fragmenty

EPP jest węzłem centralnym: **stąd** linki do serwisów robią kafelki na stronie głównej
(automatycznie, z kolekcji). Ten dokument dotyczy **drugiego kierunku** — tego, co trzeba
wkleić **na tamtych stronach**, żeby linkowały zwrotnie do EPP i do siebie nawzajem.

---

## 🔴 Jedno zastrzeżenie, przeczytaj przed wklejeniem

Zlecenie mówi: *„Rzecz NIEWIDOCZNA dla oka — żadnego schematu ani grafiki połączeń."*

**Tak to zrozumiałem i tak jest zrobione:** nie ma **rysunku sieci powiązań**, mapy, diagramu
ani sekcji „nasze serwisy" z kafelkami na stronach klientów. Jest dyskretny wiersz linków
w stopce — element, którego nikt nie zauważy, ale który istnieje w kodzie i w oczach robota.

**Czego świadomie NIE zrobiłem: linków ukrytych przed użytkownikiem** (`display:none`,
`visibility:hidden`, tekst w kolorze tła, `font-size:0`, wyniesienie poza ekran).
Gdyby „niewidoczne dla oka" znaczyło **to** — Google nazywa taki zabieg ukrytym tekstem
i ukrytymi linkami i traktuje jako spam. Ryzykowałoby to widocznością wszystkich czterech
serwisów naraz, żeby ugrać linkowanie, które **działa lepiej, gdy jest widoczne**.

**Jeśli intencja była jednak inna — to jest pytanie do Piotra, nie do mnie.** Do tego czasu
obowiązuje wersja niżej: widoczna, dyskretna, w stopce.

---

## 1. Fragment HTML — stopka `nadihome.pl`

```html
<!-- EcoPower Polska — linkowanie krzyżowe serwisów. Wklej w stopce, przed zamknięciem </footer>. -->
<nav class="epp-serwisy" aria-label="Serwisy EcoPower Polska">
  <span class="epp-serwisy__etykieta">Serwis EcoPower Polska:</span>
  <ul class="epp-serwisy__lista">
    <li><a href="https://ecopowerpolska.pl/">EcoPower Polska — wszystkie serwisy spółki</a></li>
    <li><a href="https://studioagat.pl/">Studio Agat</a></li>
  </ul>
</nav>

<style>
  .epp-serwisy { margin-top: 1.5rem; font-size: 0.85rem; line-height: 1.6; }
  .epp-serwisy__etykieta { opacity: 0.75; }
  .epp-serwisy__lista { list-style: none; display: inline; margin: 0; padding: 0; }
  .epp-serwisy__lista li { display: inline; }
  .epp-serwisy__lista li:not(:last-child)::after { content: " · "; opacity: 0.5; }
  .epp-serwisy a { color: inherit; text-decoration: underline; text-underline-offset: 2px; }
  .epp-serwisy a:hover { text-decoration-thickness: 2px; }
</style>
```

## 2. Fragment HTML — stopka `studioagat.pl`

```html
<!-- EcoPower Polska — linkowanie krzyżowe serwisów. Wklej w stopce, przed zamknięciem </footer>. -->
<nav class="epp-serwisy" aria-label="Serwisy EcoPower Polska">
  <span class="epp-serwisy__etykieta">Serwis EcoPower Polska:</span>
  <ul class="epp-serwisy__lista">
    <li><a href="https://ecopowerpolska.pl/">EcoPower Polska — wszystkie serwisy spółki</a></li>
    <li><a href="https://nadihome.pl/">Nadi Home</a></li>
  </ul>
</nav>
```
(styl ten sam co wyżej — wklejasz go raz, na tej stronie, gdzie go jeszcze nie ma)

## 3. Zasada przy każdym kolejnym serwisie

Serwis X linkuje **do EPP** i **do wszystkich pozostałych serwisów oprócz siebie**.
Przy N serwisach każda stopka ma N linków. To rośnie liniowo — przy kilkunastu serwisach
zostawia się link do EPP i zdejmuje resztę, bo EPP i tak prowadzi do wszystkich.

🔴 **Tekst kotwicy ma opisywać cel** — „EcoPower Polska — wszystkie serwisy spółki",
nigdy „kliknij tutaj". To jedna z niewielu rzeczy o linkowaniu potwierdzonych wprost
u Google (potwor-www §K).

🔴 **Bez `rel="nofollow"`.** To są własne serwisy spółki, ręczymy za nie. `nofollow`
zakłada się na linki wychodzące, za które nie ręczymy — tutaj byłby zaprzeczeniem celu.

---

## 4. Dane strukturalne dla serwisów — `WebSite` z wydawcą EPP

Wklej w `<head>` każdego serwisu, podmieniając adres i nazwę w dwóch pierwszych polach.

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "@id": "https://nadihome.pl/#witryna",
  "url": "https://nadihome.pl/",
  "name": "Nadi Home",
  "inLanguage": "pl-PL",
  "publisher": {
    "@type": "Organization",
    "@id": "https://ecopowerpolska.pl/#organizacja",
    "name": "EcoPower Polska sp. z o.o.",
    "legalName": "ECOPOWER POLSKA SPÓŁKA Z OGRANICZONĄ ODPOWIEDZIALNOŚCIĄ",
    "url": "https://ecopowerpolska.pl/",
    "identifier": [
      { "@type": "PropertyValue", "propertyID": "KRS", "value": "0000881612" },
      { "@type": "PropertyValue", "propertyID": "NIP", "value": "7011019372" },
      { "@type": "PropertyValue", "propertyID": "REGON", "value": "38814324000000" }
    ]
  }
}
</script>
```

**Dlaczego `publisher`, a nie `parentOrganization` czy `sameAs`:**

| Właściwość | Co ZNACZY | Czy wolno jej tu użyć |
|---|---|---|
| `publisher` | „wydawcą tej witryny jest EPP" | 🟢 **tak** — dokładnie to mówi zlecenie: EPP jest właścicielem serwisów WWW |
| `parentOrganization` | „ten serwis jest **spółką zależną** EPP" | 🔴 nie — stosunku właścicielskiego **między spółkami** nie znamy |
| `sameAs` | „to **ten sam podmiot** pod innym adresem" | 🔴 nie — to nieprawda, to osobne serwisy |

🔴 **`@id` wydawcy jest ten sam we wszystkich czterech miejscach** (`…ecopowerpolska.pl/#organizacja`)
— i to jest cały mechanizm. Tak wyszukiwarka skleja rozsypane po serwisach opisy w jeden byt,
zamiast widzieć cztery niepowiązane firmy o podobnej nazwie. **Nie zmieniaj tego ciągu
w żadnej kopii.**

---

## 5. Kiedy to wklejać

⏳ **Dopiero PO przełączeniu domeny** (`docs/przelaczenie-domeny.md`). Wcześniej wszystkie
odsyłacze prowadziłyby na starego WordPressa, a `@id` wskazywałby adres, pod którym nie ma
jeszcze pasujących danych strukturalnych. Fragmenty leżą tu gotowe i czekają.

🟡 **Nie sprawdziłem, jak zbudowane są `nadihome.pl` i `studioagat.pl`** — nie wiem, czy mają
stopkę w szablonie, w panelu, czy wpisaną na sztywno, ani czy `<head>` jest edytowalny.
Miejsce wklejenia trzeba ustalić na tamtych stronach.

🟢 **Adresy — sprawdzone wywołaniem 2026-09-04, nie przyjęte na słowo.** Zlecenie podało formę
z `www`; oba serwisy **przekierowują ją na apex**:

```
https://www.nadihome.pl    → 301 → https://nadihome.pl/     (200)
https://www.studioagat.pl  → 301 → https://studioagat.pl/   (200)
```

Dlatego i kafelki, i fragmenty wyżej celują w **adres kanoniczny bez `www`**. Linkowanie
przez przekierowanie działa, ale dokłada przeskok przy każdym kliknięciu i rozmywa sygnał
linku — a to jest dokładnie ta rzecz, dla której całe linkowanie krzyżowe powstaje.
Zmiana celu to poprawka pola „Adres docelowy" w panelu, bez dotykania kodu.
