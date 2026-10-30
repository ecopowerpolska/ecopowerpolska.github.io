---
tytul: "Core Web Vitals po polsku: LCP, INP i CLS na przykładach"
tytulSeo: "Core Web Vitals po polsku: LCP, INP i CLS na przykładach"
opisSeo: "Co mierzą LCP, INP i CLS, jakie wyniki Google uznaje za dobre i jak wyglądają na siedmiu naszych stronach w pomiarze PageSpeed z października 2026."
dataPublikacji: "2026-10-29T16:00:00+01:00"
zdjecie: "../../assets/serwisy/zrzut-panwylewka.jpg"
zdjecieAlt: "Strona główna panwylewka.pl: logo Pan Wylewka, pomarańczowy guzik z numerem telefonu i napis „Wylewki betonowe Warszawa i okolice” na ciemnym zdjęciu"
szkic: false
---

Core Web Vitals to trzy wskaźniki Google, które opisują, jak strona działa dla człowieka. LCP mówi, jak szybko widać główną treść. INP mówi, jak szybko strona reaguje na kliknięcie. CLS mówi, czy treść nie skacze podczas ładowania. Google uznaje za dobre LCP do 2,5 s, INP do 200 ms i CLS do 0,1. Niżej każdy wskaźnik na przykładzie naszych stron.

## Co to są Core Web Vitals?

To zestaw trzech pomiarów, które Google opisuje w dokumentacji dla właścicieli stron. Każdy dotyczy innej rzeczy: ładowania (LCP), reakcji na działanie użytkownika (INP) i stabilności układu (CLS). W 2024 roku INP zastąpił starszy wskaźnik FID, który mierzył tylko pierwsze kliknięcie. Google zapowiada, że zestaw może się zmieniać, ale nie częściej niż raz w roku.

Czy Core Web Vitals wpływają na pozycję w Google? Według Google są brane pod uwagę przez jego systemy ustalające kolejność wyników. Dobry wynik nie gwarantuje jednak miejsca na górze, bo najważniejsza pozostaje treść. Dlatego pozycji nie obiecujemy, a szybkość traktujemy jako obowiązek wobec klientów, którzy wchodzą na stronę z telefonu.

## Co mierzy LCP i jaki wynik jest dobry?

LCP, czyli Largest Contentful Paint, to czas do pokazania największego elementu na pierwszym ekranie. Zwykle jest to duże zdjęcie albo nagłówek. Dobry wynik to do 2,5 s.

Tak wyglądał LCP na telefonie na siedmiu naszych stronach w pomiarze PageSpeed Insights z 27 października 2026 roku:

- **Pan Stolarz, Kuchnia na wymiar Warszawa, Szafy na wymiar Warszawa, Garderoba na wymiar Warszawa:** 1,5 s.
- **Studio Agat:** 1,8 s.
- **Pan Wylewka:** 2,0 s.
- **Nadi Home:** 2,2 s.

Wszystkie mieszczą się w progu Google. Na komputerze ta sama metryka wyniosła od 0,36 do 0,44 s. Różnica wynika z samego testu: wersja na telefon symuluje wolniejsze urządzenie i słabszą sieć, dlatego wypada niżej.

Na niski LCP pracują trzy rzeczy. Strona w Astro trafia do przeglądarki jako gotowy HTML, bez składania jej z bazy danych przy każdym wejściu. Zdjęcia są przygotowane w kilku rozmiarach, więc telefon pobiera mniejszy plik niż monitor. A obraz z pierwszego ekranu ładuje się od razu, przed pozostałymi.

![Strona główna nadihome.pl: nagłówek „Remonty mieszkań w Warszawie i wykończenia pod klucz” obok zdjęcia układania płytek](../../assets/serwisy/zrzut-nadihome.jpg "Nadi Home: duże zdjęcie na pierwszym ekranie, a LCP na telefonie 2,2 s, najwięcej z siedmiu stron i wciąż poniżej 2,5 s.")

## Co mierzy INP i dlaczego nie ma go w teście PageSpeed?

INP, czyli Interaction to Next Paint, to czas od kliknięcia albo dotknięcia do chwili, w której strona pokaże reakcję. Na przykład rozwinie odpowiedź w FAQ albo przeliczy kalkulator. Dobry wynik to do 200 ms.

INP liczy się z prawdziwych odwiedzin, bo potrzebuje prawdziwych kliknięć. Dlatego nie ma go wśród metryk testu laboratoryjnego w PageSpeed Insights. Test pokazuje pięć innych: First Contentful Paint, Largest Contentful Paint, Total Blocking Time, Cumulative Layout Shift i Speed Index.

Najbliżej INP jest Total Blocking Time (TBT). To suma czasu, w którym długie zadania skryptów, dłuższe niż 50 ms, blokują stronę. Wtedy strona nie reaguje na dotknięcie. Na telefonie dobry wynik to do 200 ms. Na wszystkich siedmiu naszych stronach TBT wyniósł 0 ms, na telefonie i na komputerze.

Powód jest prosty: strony nie ładują zbędnych skryptów. Na tej witrynie każda podstrona ma limit 3 kB skryptów po kompresji, a automatyczna kontrola przed publikacją zatrzymuje stronę, która go przekracza.

## Co mierzy CLS i skąd biorą się przesunięcia?

CLS, czyli Cumulative Layout Shift, mierzy, jak bardzo elementy przesuwają się podczas ładowania. Znasz to z telefonu: chcesz dotknąć linku, a tekst ucieka spod palca, bo nad nim wczytało się zdjęcie. Dobry wynik to do 0,1.

W pomiarze z 27 października sześć z siedmiu naszych stron miało CLS równe 0. Pan Wylewka miał 0,004 na telefonie i 0,006 na komputerze, czyli wielokrotnie mniej niż próg.

Przesunięcia biorą się najczęściej z dwóch źródeł:

1. **Zdjęcia bez wymiarów.** Przeglądarka nie wie, ile miejsca zostawić, i rozsuwa tekst, gdy obraz się wczyta. U nas każde zdjęcie ma z góry zarezerwowane miejsce.
2. **Kroje pisma.** Zanim wczyta się właściwy krój, przeglądarka pokazuje zapasowy. Jeśli ma inne proporcje, tekst przeskakuje. Na tej stronie zapasowy krój dopasowaliśmy proporcjami do właściwego, więc tekst nie przeskakuje, a CLS wynosi 0.

![Strona główna studioagat.pl: nagłówek „Projektant wnętrz Warszawa, architekt wnętrz” nad zdjęciem salonu z szarą sofą](../../assets/serwisy/zrzut-studioagat.jpg "Studio Agat po przeniesieniu z WordPressa do Astro: LCP 1,8 s, TBT 0 ms i CLS 0 na telefonie.")

## Czym różnią się dane z terenu od testu laboratoryjnego?

PageSpeed Insights pokazuje dwa rodzaje wyników. U góry są wrażenia prawdziwych użytkowników Chrome z ostatnich 28 dni (raport CrUX). Tylko tam pojawia się INP. Niżej jest test laboratoryjny: jeden pomiar na symulowanym telefonie i komputerze.

Strona nowa albo rzadko odwiedzana nie ma dość wizyt i widzi napis „Brak danych”. Tak było 27 października na wszystkich siedmiu naszych stronach. To nie błąd. Do tego czasu o szybkości mówi test laboratoryjny. Więcej piszemy w odpowiedzi [o braku danych w PageSpeed Insights](/faq/#brak-danych).

![Strona główna garderoba-na-wymiar-warszawa.pl: szałwiowe tło, wyśrodkowany tytuł „Garderoby na wymiar w Warszawie” i zdjęcie garderoby z półkami](../../assets/serwisy/zrzut-garderoba-na-wymiar-warszawa.jpg "Garderoba na wymiar Warszawa: na telefonie FCP 1,0 s, LCP 1,5 s, TBT 0 ms i CLS 0 w teście laboratoryjnym.")

## Jak sprawdzić Core Web Vitals swojej strony?

Wystarczy kilka minut:

1. Otwórz nasz [darmowy test szybkości strony](/jak-sprawdzic-szybkosc-strony/) i wpisz adres.
2. Sprawdź wynik osobno na telefonie i na komputerze.
3. Jeśli u góry raportu są dane z terenu, zacznij od nich. Jeśli widzisz „Brak danych”, czytaj test laboratoryjny.
4. Zmierz kilka podstron, nie tylko stronę główną. Każda ma inne zdjęcia i inny kod.

Gdy wynik jest słaby, zaczynamy od tego, co waży najwięcej. Sprawdzamy, czy zdjęcia mają wymiary i wersje w kilku rozmiarach. Szukamy skryptów, bez których strona może się obejść. Patrzymy też, czy strona nie składa się z bazy danych przy każdym wejściu. Jeśli tak, czasem lepiej przenieść ją na gotowe pliki niż łatać. Tak zrobiliśmy ze stroną Studio Agat, przeniesioną z WordPressa do Astro.

Jeden pomiar to zdjęcie chwili. Wynik zmienia się po każdej zmianie treści, nowym zdjęciu albo dodanym skrypcie. Dlatego w ramach [opieki nad stroną mierzymy szybkość regularnie](/prowadzenie-strony-internetowej/) i wysyłamy raport z listą poprawek w rytmie ustalonym z Tobą, na przykład co miesiąc.

Koszt opieki po publikacji [policzysz w kalkulatorze](/cena/#kalkulator). Jeśli Twoja strona ma słaby wynik i chcesz wiedzieć dlaczego, [napisz do nas](/kontakt/) i podaj jej adres.

## Źródła

1. [Google Search Central: Core Web Vitals](https://developers.google.com/search/docs/appearance/core-web-vitals), aktualizacja 10 grudnia 2025.
2. [Google Search Central: page experience](https://developers.google.com/search/docs/appearance/page-experience).
3. [web.dev: Web Vitals](https://web.dev/articles/vitals), aktualizacja 31 października 2024.
4. Pomiary PageSpeed Insights (Lighthouse 13.5.0) siedmiu naszych stron z 27 października 2026, publikowane pod [naszymi realizacjami](/#realizacje).
