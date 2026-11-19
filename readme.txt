=== Digital Xperts Popup ===
Contributors: digitalxperts
Requires at least: 6.2
Tested up to: 7.2
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Popup i pasek informacyjny dla odwiedzających: gotowe style i okazje, biblioteka popupów, daty wyświetlania i podgląd przed włączeniem.

== Description ==

Digital Xperts Popup pokazuje odwiedzającym okno z komunikatem albo pasek informacyjny na górze lub na dole strony. Oba ustawiasz w menu „Popup i pasek”.

= Popup =

* Okno pokazuje się od razu po wejściu na stronę, także przed zamknięciem banera cookies.
* 20 stylów: „Jak strona” (czcionki, kolory i przycisk z Twojej strony), Własny, Klasyczny, 10 gotowych stylów i 7 okazji, na przykład Black Friday i Boże Narodzenie.
* Treść piszesz w edytorze. Przycisk może zamykać okno albo prowadzić pod wybrany adres.
* Biblioteka: zapisz popup pod nazwą i wczytaj go za rok. Siedem gotowych popupów okazjonalnych z treścią.
* Po zamknięciu popup nie wraca przez wybraną liczbę dni.

= Pasek informacyjny =

* Jedno zdanie i opcjonalny przycisk z adresem, na przykład „Darmowa dostawa od 200 zł”.
* Na górze strony albo na dole ekranu, przyklejony przy przewijaniu albo nie.
* Te same gotowe style i okazje co popup oraz styl Własny z wyborem kolorów.
* Krzyżyk zamyka pasek. Strona pamięta zamknięcie przez wybraną liczbę dni, a nowa treść pokazuje pasek ponownie.
* Pasek na górze jest widoczny od pierwszej chwili i nie przesuwa treści po wczytaniu strony.

= Daty =

Popup i pasek mają pola „Pokazuj od” i „Pokazuj do”. Kampania na Black Friday włączy się i wyłączy sama. Daty sprawdza też przeglądarka, więc działają mimo pamięci podręcznej stron.

= Podgląd =

Przycisk podglądu pokazuje zapisany popup albo pasek tylko zalogowanemu administratorowi, także gdy jest wyłączony albo poza datami.

= Połączenie z AI =

Menedżer Strony AI od wersji 2.9.0 zmienia popup i pasek przez operację `popup.update`: zasób `main` to popup, `bar` to pasek.

= Aktualizacje =

Wtyczka aktualizuje się z wydań na GitHubie: https://github.com/DariuszTrachimowicz/digital-xperts-popup. Nowa wersja pojawia się na liście wtyczek jak każda inna aktualizacja.

= Dane techniczne =

* Popup: opcje `asm_popup` i `asm_popup_library`, te same co w Menedżerze Strony AI do wersji 2.8.0. Klasy CSS `.asm-popup__*` też zostały, więc własny CSS działa dalej.
* Pasek: opcja `dxp_bar`, klasy CSS `.dxp-bar__*`.
* Zapis popupu albo paska czyści pamięć podręczną LiteSpeed Cache, W3 Total Cache i WP Super Cache.

== Installation ==

1. Pobierz paczkę `digital-xperts-popup-X.Y.Z.zip` z wydań na GitHubie.
2. W WordPressie wybierz Wtyczki, Dodaj wtyczkę, Wyślij wtyczkę na serwer i włącz ją.
3. Otwórz menu „Popup i pasek”, wpisz treść, zapisz i obejrzyj podgląd.
4. Włącz przełącznik „Pokazuj odwiedzającym”.

Strona z Menedżerem Strony AI 2.8.0 lub starszym: najpierw zaktualizuj Menedżera do 2.9.0. Do tego czasu popup zostaje w Menedżerze, a ta wtyczka pokazuje tylko pasek.

== Changelog ==

= 1.0.0 =
* Popup przeniesiony z Menedżera Strony AI 2.8.0 z ustawieniami, biblioteką i własnym CSS.
* Nowy pasek informacyjny: góra albo dół, przyklejony albo nie, gotowe style i okazje, przycisk z adresem.
* Daty „od” i „do” dla popupu i paska.
* Okno „Zobacz szczegóły” i aktualizacje z wydań na GitHubie.
