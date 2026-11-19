(function () {
  'use strict';

  // Skrypt jest w nagłówku i ma dane paska we własnych atrybutach. Gdy optymalizator przeniesie go niżej
  // albo połączy z innymi, dane bierzemy z samego paska.
  var script = document.currentScript && document.currentScript.hasAttribute('data-revision') ? document.currentScript : null;
  var hide = script ? excluded(script) : null;
  if (hide) document.documentElement.classList.add('dxp-bar-off');

  function excluded(source) {
    var preview = source.getAttribute('data-preview') === '1';
    if (preview) return false;
    var now = Date.now() / 1000;
    var start = Number(source.getAttribute('data-start') || 0);
    var end = Number(source.getAttribute('data-end') || 0);
    return (start && now < start) || (end && now >= end) || dismissed(source);
  }

  function storageKey(source) {
    return 'dxp_bar_dismissed_' + String(source.getAttribute('data-revision') || 'default');
  }

  function days(source) {
    return Math.max(0, Number(source.getAttribute('data-dismiss-days') || 0));
  }

  function dismissed(source) {
    try {
      // 0 dni = „przy każdej wizycie”: zamknięcie pamiętamy do końca wizyty (karty).
      if (days(source) === 0) return window.sessionStorage.getItem(storageKey(source)) === '1';
      return Number(window.localStorage.getItem(storageKey(source)) || 0) > Date.now();
    } catch (error) {
      return false;
    }
  }

  function run() {
    var bar = document.getElementById('dxp-bar');
    if (!bar) return;
    if (hide === null ? excluded(bar) : hide) {
      bar.remove();
      return;
    }
    var body = document.body;
    // Pasek ze stopki (motyw bez wp_body_open) trafia na początek strony.
    if (bar.classList.contains('dxp-bar--top')) {
      if (body.firstElementChild !== bar) body.insertBefore(bar, body.firstChild);
    } else if (body.lastElementChild !== bar) {
      body.appendChild(bar);
    }
    // Przyklejony pasek na dole zasłaniałby koniec strony, więc strona dostaje odstęp jego wysokości.
    var fixed = bar.classList.contains('dxp-bar--bottom') && bar.classList.contains('dxp-bar--sticky');
    var base = parseFloat(window.getComputedStyle(body).paddingBottom) || 0;
    var pad = function () { if (fixed) body.style.paddingBottom = (base + bar.offsetHeight) + 'px'; };
    pad();
    window.addEventListener('resize', pad);
    bar.querySelector('.dxp-bar__x').addEventListener('click', function () {
      window.removeEventListener('resize', pad);
      if (fixed) body.style.paddingBottom = '';
      bar.remove();
      if (bar.getAttribute('data-preview') === '1') return;
      try {
        if (days(bar) > 0) window.localStorage.setItem(storageKey(bar), String(Date.now() + days(bar) * 86400000));
        else window.sessionStorage.setItem(storageKey(bar), '1');
      } catch (error) {}
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
}());
