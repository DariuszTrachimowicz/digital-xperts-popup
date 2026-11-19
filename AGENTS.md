# Digital Xperts Popup: instrukcja dla agentów (Codex, Claude)

Stan na 19 listopada 2026. Popup wydzielony z Menedżera Strony AI 2.8.0 i nowy pasek informacyjny.

## Gdzie co jest

- Repozytorium: https://github.com/DariuszTrachimowicz/digital-xperts-popup (publiczne, gałąź main). Bez sekretów i danych klientów.
- `includes/Popup.php`, `assets/popup.js`, `assets/popup-v3.css` (styl klasyczny), `assets/popup-theme.css`: popup.
- `includes/Bar.php`, `assets/bar.js`, `assets/bar.css`: pasek. `includes/Schedule.php`: daty od i do.
- `includes/Admin.php`, `assets/admin.*`: panel „Popup i pasek”. `includes/Updater.php`: aktualizacje i okno „Zobacz szczegóły”.
- Testy: `php -d zend.assertions=1 tests/unit.php` (Docker, lokalnego PHP nie ma) i `tests/compose.yml` z WordPressem.

## Ustalenia, których nie wolno zgubić

- Popup czyta opcje `asm_popup` i `asm_popup_library` i używa klas `.asm-popup__*`. Nie zmieniaj nazw: strony mają tam zapisane popupy i własny CSS.
- Zapisane ustawienia bez pola `style` zostają przy wyglądzie klasycznym. Domyślne `classic` zostaje.
- Popup pokazuje się od razu, przed banerem cookies (decyzja Darka 16 XI 2026). Skrypty popupu i paska mają atrybuty data-no-optimize i data-no-defer.
- Gdy działa Menedżer Strony AI 2.8.0 lub starszy (klasa `ASM\Popup\Popup`), ta wtyczka nie wyświetla popupu, tylko pasek.
- Pasek na górze jest wypisany po `<body>` i widoczny bez skryptu (bez przesunięcia treści). Skrypt w nagłówku chowa go klasą `dxp-bar-off`, gdy odwiedzający go zamknął albo daty go wykluczają.
- Przyklejony pasek na górze: `position: fixed` (klasa dxp-bar--pinned) i puste pole `#dxp-bar-space`, bo `sticky` nie działa przy overflow: hidden na html/body (d-x.pl). Nagłówki fixed/absolute zachodzące na pasek dostają margin-top (bar.js), nakładka na cały ekran (menu) chowa pasek (dxp-bar--covered). Test: tests/mu/header-fixture.php z ?fx=dx|abs|fixed.
- LiteSpeed na d-x.pl łączy CSS w jeden plik: test podmiany plików na żywej stronie musi wstrzyknąć CSS (addStyleTag), sama podmiana bar.css nie działa.
- Wydanie ma dwa załączniki: `digital-xperts-popup-X.Y.Z.zip` (aktualizacje) i `digital-xperts-popup.zip` (stały adres do instalacji przez konektor). Oba robi `python build.py`.

## Wydanie

1. Podnieś wersję w nagłówku i stałej `VERSION` w `digital-xperts-popup.php` oraz w `readme.txt` (Stable tag, Changelog).
2. Testy, `python build.py`, commit i push.
3. `gh release create vX.Y.Z dist/digital-xperts-popup-X.Y.Z.zip dist/digital-xperts-popup.zip --title X.Y.Z --notes "..."`.

Wydanie trafia na strony klientów. Nie publikuj bez testów i bez zgody Darka. Commity bez dopisków o narzędziach AI.
