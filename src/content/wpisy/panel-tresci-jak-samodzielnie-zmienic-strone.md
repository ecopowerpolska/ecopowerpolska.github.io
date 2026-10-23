---
tytul: "Panel treści: jak sam zmienisz tekst i zdjęcia na stronie"
tytulSeo: "Panel treści strony bez programisty — EcoPower Polska"
opisSeo: "Jak działa panel treści pod adresem /admin/: co w nim zmienisz, czym różni się panel Sveltia od panelu w PHP i jak cofnąć zmianę."
dataPublikacji: "2026-10-23T15:00:00+02:00"
zdjecie: "../../assets/serwisy/zrzut-nadihome.jpg"
zdjecieAlt: "Strona główna nadihome.pl: nagłówek „Remonty mieszkań w Warszawie i wykończenia pod klucz” obok zdjęcia układania płytek"
szkic: false
---

Każda strona, którą budujemy, dostaje panel treści pod adresem /admin/. Zmieniasz w nim teksty, zdjęcia, wpisy na blogu i dane firmy bez pisania kodu i bez dzwonienia do programisty. Mamy dwa rodzaje panelu: Sveltia, który zapisuje zmiany w repozytorium strony, oraz panel w PHP na hostingu, do którego logujesz się hasłem. Niżej opisujemy, jak działa każdy z nich.

## Co zmienisz w panelu?

W panelu zmieniasz to, co klienci czytają i oglądają na stronie. Są to przede wszystkim:

- teksty na stronie głównej i podstronach,
- zdjęcia, na przykład w galerii realizacji,
- wpisy na blogu,
- dane firmy.

Jeśli wyczyścisz pole, strona wraca do napisu domyślnego. Dzięki temu strona nie zostanie bez nagłówka, nawet gdy coś przypadkiem skasujesz.

Dostęp do panelu dotyczy tylko Twojej strony. Strony klientów nie dzielą kodu ani dostępów, więc z Twojego panelu nie da się wejść do cudzej strony.

## Jak działa panel Sveltia?

Sveltia to panel, który zapisuje każdą zmianę w repozytorium strony na GitHubie. Po zapisie strona przebudowuje się sama w ciągu kilku minut i nowa treść pojawia się w sieci.

Taki układ ma ważną zaletę. Każda zmiana, także z panelu, jest w repozytorium osobnym wpisem z pełną historią. Jeśli coś zmienisz przez pomyłkę, ten wpis da się cofnąć. Całą stronę można też odbudować z repozytorium.

Tak działa na przykład strona Nadi Home. Zastąpiliśmy tam WordPressa stroną w Astro z panelem treści Sveltia, cennikiem robocizny, galerią, FAQ i blogiem. Więcej takich stron jest w sekcji [nasze realizacje](/#realizacje).

## Kiedy lepszy jest panel w PHP na hostingu?

Panel Sveltia wymaga konta na GitHubie. Nie każdy chce je zakładać. Wtedy robimy panel w PHP, który działa na hostingu lh.pl obok strony. Logujesz się do niego hasłem.

Taki panel ma na przykład strona Studio Agat. Przenieśliśmy ją z WordPressa do Astro, a treść zmienia się w panelu w PHP na hostingu.

Oba panele służą temu samemu: Ty zmieniasz treść, a strona zostaje szybka, bo do przeglądarki nadal trafia gotowy HTML. Różnią się sposobem logowania i miejscem, w którym zapisują zmiany.

## Czy zmiany w panelu spowolnią stronę?

Nie, bo panel nie zmienia sposobu, w jaki strona trafia do przeglądarki. Strony budujemy w Astro: do przeglądarki trafia gotowy HTML, bez składania strony z bazy danych przy każdym wejściu i bez zbędnego JavaScriptu. Zdjęcia są przygotowane w kilku rozmiarach, więc telefon pobiera mniejszy plik niż monitor.

Szybkość sprawdzamy pomiarem PageSpeed Insights po publikacji. Strona studioagat.pl, która ma panel w PHP, po przepisaniu na Astro ma w nim 98–100 punktów. Szczegóły są w odpowiedzi na pytanie [czy strona będzie szybka na telefonie](/faq/#szybkosc).

## Jak dodać wpis na blogu?

Blog to część panelu. Każdy wpis jest osobną podstroną z datą publikacji, a strona ma listę wpisów i kanał RSS.

Wpis dodajesz w panelu tak samo jak inne treści: wpisujesz tytuł i tekst, dodajesz zdjęcie, ustawiasz datę publikacji i zapisujesz. Strona sama dopisze nowy wpis do listy i do kanału RSS.

Wpis z datą w przyszłości pojawi się sam przy pierwszej przebudowie strony po tej dacie. Możesz więc przygotować kilka wpisów naraz i rozłożyć je w czasie.

Jeśli nie masz czasu pisać, wpisy możesz zlecić nam. Temat dobieramy z danych Search Console i Planera słów kluczowych, tekst piszemy z faktów Twojej firmy, a zdjęcia są prawdziwe.

## Który panel wybrać?

Wybór zależy od tego, jak chcesz się logować. Jeśli masz konto na GitHubie albo nie przeszkadza Ci jego założenie, Sveltia daje pełną historię zmian i cofanie każdego zapisu. Jeśli wolisz zwykłe hasło, panel w PHP na hostingu zrobi to samo bez GitHuba.

Rodzaj panelu ustalamy na początku, razem z wyborem hostingu. Krótki opis obu jest w sekcji [panel do edycji treści w naszej ofercie](/#oferta), a szczegóły w odpowiedzi na pytanie [czy sam zmienię treść na stronie](/faq/#edycja-tresci). Jeśli chcesz zobaczyć, jak panel wygląda w praktyce, [napisz do nas](/#kontakt).
