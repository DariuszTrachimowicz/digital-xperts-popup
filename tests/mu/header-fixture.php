<?php
// Tylko lokalny WordPress z tests/compose.yml. Nagłówki jak w prawdziwych motywach, wybierane parametrem ?fx=:
// dx    nagłówek absolute bez top i overflow: hidden na html i body (jak d-x.pl),
// abs   nagłówek absolute z top: 0,
// fixed nagłówek przyklejony do ekranu (position: fixed; top: 0).
add_action('wp_body_open', static function (): void {
    $fx = isset($_GET['fx']) ? (string) $_GET['fx'] : '';
    if (! in_array($fx, array('dx', 'abs', 'fixed'), true)) {
        return;
    }
    $css = array(
        'dx' => 'html{overflow:hidden scroll}body{overflow:hidden}#fx-header{position:absolute;left:0;right:0}',
        'abs' => '#fx-header{position:absolute;top:0;left:0;right:0}',
        'fixed' => '#fx-header{position:fixed;top:0;left:0;right:0}',
    )[$fx];
    echo '<style>' . $css . '#fx-header{z-index:10;height:90px;background:#dbeafe;display:flex;align-items:center;gap:24px;padding:0 24px;font:600 18px sans-serif}#fx-menu{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.8);color:#fff;display:none}#fx-menu.open{display:block}</style>';
    echo '<header id="fx-header"><span>LOGO</span><nav>Start O nas Usługi Kontakt</nav><button type="button" id="fx-burger" onclick="document.getElementById(\'fx-menu\').classList.add(\'open\')">Menu</button></header>';
    echo '<div id="fx-menu"><p style="margin:0;padding:16px">Menu mobilne</p></div>';
    echo '<div style="height:3000px"></div>';
}, 20);
