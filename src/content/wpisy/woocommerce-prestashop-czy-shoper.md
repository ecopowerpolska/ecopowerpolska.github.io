---
tytul: "WooCommerce, PrestaShop czy Shoper: jaki sklep wybrać"
tytulSeo: "WooCommerce, PrestaShop czy Shoper: jaki sklep wybrać"
opisSeo: "WooCommerce, PrestaShop, Shoper i IdoSell albo szybki sklep w Astro: dla kogo jest każda droga, kto obsługuje serwer i za co płacisz co miesiąc."
dataPublikacji: "2026-10-20T15:00:00+02:00"
zdjecie: "../../assets/serwisy/zrzut-pan-stolarz.jpg"
zdjecieAlt: "Strona główna pan-stolarz.pl: nagłówek „Meble na wymiar w Warszawie”, przycisk „Sprawdź cenę kuchni, szafy albo garderoby” i zdjęcie kuchni w drewnie"
szkic: false
---

Wybór zależy od tego, kto będzie prowadził sklep i ile masz produktów. WooCommerce i PrestaShop stawiamy na Twoim hostingu, gdy sam dodajesz produkty i obsługujesz zamówienia. Shoper albo IdoSell pasują, gdy wolisz płacić abonament i nie zajmować się serwerem. Przy krótkiej ofercie sklep może być częścią szybkiej strony w Astro. Niżej opisujemy każdą drogę i to, za co płacisz po starcie.

## Czym różnią się WooCommerce, PrestaShop i Shoper?

Te trzy nazwy oznaczają dwa różne rodzaje sklepu.

**WooCommerce i PrestaShop to oprogramowanie open source.** Nie płacisz za licencję, ale sklep musi gdzieś stać. Instalujemy go na Twoim hostingu, razem z bazą danych. Panel, koszyk, kasa, magazyn i zamówienia działają na Twoim koncie. Za wdrożenie, hosting i opiekę płacisz osobno.

**Shoper i IdoSell to platformy abonamentowe.** Sklep, koszyk, kasa, płatności i serwer są u dostawcy platformy. Hosting jest w cenie abonamentu, a o aktualizacje dba dostawca. Nasza praca to wdrożenie i konfiguracja: wygląd, ustawienia, płatności i dostawy.

Jest też trzecia droga, o której rzadziej się mówi: sklep jako część szybkiej strony w Astro. Opisujemy ją niżej.

## Kiedy wybrać WooCommerce?

WooCommerce to wtyczka do WordPressa. Sklep na WooCommerce jest więc stroną na WordPressie z dodanym koszykiem, kasą i zamówieniami.

Ta droga pasuje, gdy sam prowadzisz sklep: dodajesz produkty, obsługujesz zamówienia i magazyn. Do WooCommerce są gotowe wtyczki płatności i dostaw, w tym polskich operatorów i paczkomatów. Przed wskazaniem konkretnej wtyczki sprawdzamy, kto ją wydał i czy działa z koszykiem i kasą w obecnej wersji sklepu. Paczkomatów w kasie nie obiecujemy, zanim tego nie sprawdzimy.

W naszym cenniku sklep WooCommerce obejmuje płatności, dostawy, certyfikat SSL i podstawowe SEO. Stawiamy go na hostingu lh.pl, w osobnym katalogu z oddzieloną przestrzenią. Jeśli kiedyś zaatakowana zostanie jedna z wtyczek, szkoda nie wyjdzie poza katalog sklepu.

## Kiedy wybrać PrestaShop?

PrestaShop to osobny silnik sklepu, niezwiązany z WordPressem. Sam silnik jest bezpłatny. Działa na tym samym rodzaju hostingu co WooCommerce: PHP i baza MySQL.

Wybierz go, gdy chcesz sklepu niezależnego od WordPressa i sam prowadzisz sprzedaż: dodajesz produkty, obsługujesz zamówienia i magazyn. Wdrożenie PrestaShop jest u nas wyraźnie droższe niż WooCommerce. W przeglądzie cen z 18 źródeł, który opisujemy we wpisie o [cenach stron i sklepów na rynku](/blog/ile-kosztuje-strona-i-sklep-internetowy/), PrestaShop miał najwyższą medianę wśród sklepów na gotowym silniku.

Przy obu silnikach, WooCommerce i PrestaShop, przejmujesz serwer. Ktoś musi aktualizować sklep, robić kopie i sprawdzać, czy płatności nadal działają. Robimy to w ramach opieki technicznej sklepu: aktualizacje z testem ścieżki zakupu, kopie zapasowe i kontrola płatności oraz zamówień.

## Kiedy wybrać Shopera albo IdoSell?

Platforma abonamentowa pasuje, gdy nie chcesz utrzymywać serwera i kopii bazy. Albo gdy masz już sklep na takiej platformie i chcesz go uporządkować, a nie budować od nowa.

Pamiętaj o trzech rzeczach:

1. **Abonament płacisz, dopóki działa sklep.** Pierwszy rok bywa tańszy w promocji platformy, a potem cena rośnie do stawki z cennika.
2. **IdoSell pobiera też opłatę od każdego zamówienia.** Jej wysokość zależy od planu.
3. **Sklep jest u dostawcy.** Wygląd i funkcje zależą od tego, na co pozwala platforma, a przeniesienie sklepu gdzie indziej to osobny projekt. Shoper pozwala wyeksportować do plików CSV zamówienia, klientów, produkty i kupony rabatowe.

Nasza praca przy platformie to wdrożenie i konfiguracja. Abonament platformy płacisz jej dostawcy, osobno.

## Czy sklep musi stać na gotowej platformie?

Nie. Gdy produktów jest kilkanaście lub kilkadziesiąt, sklep może być częścią szybkiej strony w Astro. Strona trafia do przeglądarki jako gotowe pliki, więc katalog otwiera się szybko także na telefonie.

Mamy tu dwie drogi:

- **Katalog w Astro z płatnością przez Stripe Checkout.** Pasuje, gdy nie potrzebujesz panelu sklepu, a płatność może obsłużyć zewnętrzna strona operatora.
- **Katalog w Astro z własnym koszykiem w PHP.** Pasuje, gdy chcesz polskiego operatora płatności z BLIK-iem bez WordPressa. Koszyk działa na hostingu lh.pl, a zamówienia zapisuje baza danych.

Mechanizm koszyka zbudowaliśmy już na stronie Pan Stolarz, choć tam nie służy do sprzedaży. Klient liczy w kalkulatorach cenę kuchni, szafy albo garderoby i zbiera wyliczenia w jednym koszyku wycen. Potem wysyła je jednym zapytaniem z formularza. Ta sama zasada działa w kalkulatorze na naszej stronie.

![Strona główna szafy-na-wymiar-warszawa.pl: granatowy pas z tytułem „Szafy na wymiar w Warszawie”, mosiężny guzik pomiaru, guzik ceny szafy i zdjęcie sypialni](../../assets/serwisy/zrzut-szafy-na-wymiar-warszawa.jpg "Szafy na wymiar Warszawa: drugi przycisk prowadzi do kalkulatora ceny szafy, a nie do koszyka sklepu.")

## Za co płacisz co miesiąc, poza wdrożeniem?

Wdrożenie to opłata jednorazowa. Po starcie każdy sklep ma koszty stałe, a ich rodzaj zależy od drogi:

- **Sklep na własnym silniku (WooCommerce, PrestaShop):** hosting z domeną i certyfikatem. Większy ruch to droższy serwer. Do tego opieka techniczna sklepu.
- **Platforma abonamentowa (Shoper, IdoSell):** abonament platformy, w którym jest hosting. W IdoSell także opłata od zamówienia.
- **Każdy sklep z płatnością online:** prowizja operatora płatności od każdej opłaconej transakcji. Pobiera ją operator, nie my.
- **Każdy sklep:** regulamin i polityka prywatności, aktualizowane przy zmianie przepisów.

Integracje, na przykład z Allegro albo z hurtownią, liczymy osobno. Wszystkie pozycje z cenami netto są w [cenniku stron i sklepów](/cena/), a w [kalkulatorze policzysz koszt swojego sklepu](/cena/#kalkulator): jednorazowo, co miesiąc i w pierwszym roku.

![Strona główna kuchnia-na-wymiar-warszawa.pl: tytuł „Kuchnie na wymiar w Warszawie”, terakotowy guzik „Umów pomiar i wycenę” i zdjęcie białej kuchni z drewnianym blatem](../../assets/serwisy/zrzut-kuchnia-na-wymiar-warszawa.jpg "Kuchnia na wymiar Warszawa: strona w Astro z kalkulatorem ceny netto i z VAT, bez koszyka i bez płatności.")

## Jak wybieramy platformę z klientem?

Drogę wybieramy po rozmowie, a nie z góry. Pytamy o pięć rzeczy:

1. Ile masz produktów dziś i ile planujesz za rok.
2. Kto będzie dodawał produkty i obsługiwał zamówienia.
3. Czy masz już sklep, a jeśli tak, na jakiej platformie.
4. Jakie płatności i dostawy chcesz dać klientom: BLIK, karta, przelew, paczkomaty, kurier.
5. Czy sklep ma się łączyć z magazynem, systemem księgowym albo Allegro.

Przy dużym sklepie, z tysiącami produktów i integracją z magazynem lub systemem ERP, gotowa platforma może nie wystarczyć. Wtedy przygotowujemy wycenę sklepu pisanego na zamówienie.

Jeśli dopiero zastanawiasz się, czy potrzebujesz sklepu, przeczytaj wpis [strona czy sklep internetowy](/blog/strona-czy-sklep-internetowy-od-czego-zaczac/). Jak budujemy sklep krok po kroku, opisujemy na stronie [tworzenie sklepów internetowych](/sklepy-internetowe-warszawa/). Gdy znasz odpowiedzi na pytania z listy, [zapytaj o wycenę sklepu](/kontakt/).
