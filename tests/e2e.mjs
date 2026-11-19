// Test w przeglądarce na lokalnym WordPressie z tests/compose.yml (port 8098):
//   node tests/e2e.mjs <katalog z node_modules/playwright>
// Ustawia pasek i popup przez wp-cli, sprawdza wyświetlanie, zamykanie, daty i przesunięcie treści, robi zrzuty do tests/shots.
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const { chromium } = createRequire(path.join(process.argv[2], 'package.json'))('playwright');
const base = 'http://127.0.0.1:8098/';
const shots = path.join(here, 'shots');
fs.mkdirSync(shots, { recursive: true });
let failures = 0;
const check = (ok, name) => { console.log((ok ? 'ok   ' : 'FAIL ') + name); if (!ok) failures += 1; };
const wp = php => execFileSync('docker', ['compose', '-f', path.join(here, 'compose.yml'), '-p', 'dxp', 'run', '--rm', '-T', 'cli', 'eval', php], { stdio: ['ignore', 'pipe', 'ignore'] }).toString();
// Ustawienia idą jako base64, żeby cudzysłowy i polskie znaki nie psuły kodu PHP.
const save = (cls, settings) => wp(`$s = json_decode(base64_decode('${Buffer.from(JSON.stringify(settings)).toString('base64')}'), true); DigitalXperts\\Popup\\${cls}::save($s + DigitalXperts\\Popup\\${cls}::defaults());`);
const bar = settings => save('Bar', settings);
const popup = settings => save('Popup', settings);
const local = date => { const p = n => String(n).padStart(2, '0'); return `${date.getFullYear()}-${p(date.getMonth() + 1)}-${p(date.getDate())}T${p(date.getHours())}:${p(date.getMinutes())}`; };

const browser = await chromium.launch({ channel: 'chrome' });
const open = async (viewport, context) => {
  context = context || await browser.newContext({ viewport, timezoneId: 'Europe/Warsaw' });
  const page = await context.newPage();
  // Suma przesunięć układu (CLS) od początku wczytywania.
  await page.addInitScript(() => { window.__cls = 0; new PerformanceObserver(list => { for (const e of list.getEntries()) if (!e.hadRecentInput) window.__cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); });
  await page.goto(base, { waitUntil: 'load' });
  await page.waitForTimeout(300);
  return { context, page };
};
const rect = page => page.evaluate(() => { const b = document.getElementById('dxp-bar'); if (!b) return null; const r = b.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, height: r.height, display: getComputedStyle(b).display, first: document.body.firstElementChild === b }; });

popup({ enabled: false });

// 1. Pasek na górze, przyklejony, z przyciskiem: widoczny od razu, pierwszy w body, bez przesunięcia treści.
bar({ enabled: true, message: 'Darmowa dostawa od 200 zł do końca listopada.', button_text: 'Zobacz ofertę', button_url: 'https://example.com/oferta', position: 'top', sticky: true, style: 'dark', dismiss_days: 7 });
let { context, page } = await open({ width: 1440, height: 900 });
let r = await rect(page);
check(r && r.display !== 'none' && r.first && r.top === 0, 'pasek na górze: widoczny i pierwszy w body');
check(await page.evaluate(() => window.__cls) < 0.01, 'pasek na górze nie przesuwa treści (CLS < 0,01)');
check(await page.locator('#dxp-bar .dxp-bar__btn').getAttribute('href') === 'https://example.com/oferta', 'przycisk prowadzi pod adres');
await page.screenshot({ path: path.join(shots, '1-gora-desktop.png') });
await page.mouse.wheel(0, 1500);
await page.waitForTimeout(300);
check((await rect(page)).top === 0, 'przyklejony pasek zostaje na górze po przewinięciu');
await page.screenshot({ path: path.join(shots, '2-gora-przewiniete.png') });

// 2. Zamknięcie: pasek znika, po odświeżeniu go nie ma i nie mignie (klasa na <html> przed narysowaniem).
await page.click('#dxp-bar .dxp-bar__x');
check(await page.locator('#dxp-bar').count() === 0, 'krzyżyk zamyka pasek');
await page.goto(base, { waitUntil: 'commit' });
await page.waitForSelector('body');
check(await page.evaluate(() => document.documentElement.classList.contains('dxp-bar-off')), 'po odświeżeniu: pasek schowany już w nagłówku strony');
await page.waitForLoadState('load');
check(await page.locator('#dxp-bar').count() === 0, 'po odświeżeniu: zamknięty pasek nie wraca');
await context.close();

// 3. Nowa treść pokazuje pasek ponownie także temu, kto zamknął poprzedni.
({ context, page } = await open({ width: 390, height: 844 }));
await page.click('#dxp-bar .dxp-bar__x');
bar({ enabled: true, message: 'Sklep nieczynny 24 grudnia. Zamówienia wysyłamy od 27 grudnia.', position: 'top', sticky: true, style: 'boze-narodzenie' });
await page.goto(base, { waitUntil: 'load' });
r = await rect(page);
check(r && r.display !== 'none', 'nowa treść: pasek wraca po zamknięciu poprzedniego');
await page.screenshot({ path: path.join(shots, '3-gora-telefon-swieta.png') });
await context.close();

// 4. Pasek na dole, przyklejony: przy dolnej krawędzi, strona dostaje odstęp jego wysokości.
bar({ enabled: true, message: 'Black Friday: rabaty do 30 % na całą kolekcję.', button_text: 'Kupuję', button_url: 'https://example.com/bf', position: 'bottom', sticky: true, style: 'black-friday' });
({ context, page } = await open({ width: 1440, height: 900 }));
r = await rect(page);
check(r && Math.round(r.bottom) === 900, 'pasek na dole: przy dolnej krawędzi ekranu');
const pad = await page.evaluate(() => parseFloat(document.body.style.paddingBottom) || 0);
check(pad >= r.height, 'pasek na dole: strona ma odstęp na pasek');
await page.screenshot({ path: path.join(shots, '4-dol-desktop.png') });
await context.close();
({ context, page } = await open({ width: 390, height: 844 }));
await page.screenshot({ path: path.join(shots, '5-dol-telefon.png') });
await context.close();

// 5. Daty: przed „od” i po „do” paska nie ma; podgląd administratora pomija daty (tu bez logowania: brak podglądu).
const now = new Date();
bar({ enabled: true, message: 'Jutrzejsza promocja', position: 'top', start: local(new Date(now.getTime() + 86400000)) });
({ context, page } = await open({ width: 1440, height: 900 }));
check(await page.locator('#dxp-bar').count() === 0, 'data „od” w przyszłości: pasku nie ma (skrypt go usuwa)');
await context.close();
bar({ enabled: true, message: 'Wczorajsza promocja', position: 'top', end: local(new Date(now.getTime() - 86400000)) });
({ context, page } = await open({ width: 1440, height: 900 }));
check(!(await page.content()).includes('Wczorajsza promocja'), 'data „do” minęła: serwer nie wypisuje paska');
await context.close();

// 6. Popup i pasek razem; popup bez paska z datą w przyszłości się nie otwiera.
bar({ enabled: true, message: 'Darmowa dostawa od 200 zł.', position: 'top', sticky: true, style: 'business' });
popup({ enabled: true, title: 'Wesołych Świąt!', message: 'Życzymy spokojnych Świąt.\n\nW czasie świąt odpowiadamy z opóźnieniem.', button_text: 'Dziękuję', style: 'boze-narodzenie' });
({ context, page } = await open({ width: 1440, height: 900 }));
check(await page.locator('#asm-popup').isVisible() && (await rect(page)).display !== 'none', 'popup i pasek widoczne razem');
await page.screenshot({ path: path.join(shots, '6-popup-i-pasek.png') });
await page.keyboard.press('Escape');
check(!(await page.locator('#asm-popup').isVisible()), 'Escape zamyka popup');
await context.close();
popup({ enabled: true, title: 'Za tydzień', message: 'x', start: local(new Date(now.getTime() + 7 * 86400000)) });
({ context, page } = await open({ width: 1440, height: 900 }));
check(!(await page.locator('#asm-popup').isVisible()), 'popup z datą „od” w przyszłości się nie otwiera');
await context.close();

// 7. Nagłówki motywów (tests/mu/header-fixture.php): pasek nie zasłania nagłówka, przyklejony zostaje na górze,
// menu na telefonie (nakładka z-index 9999) jest nad paskiem.
popup({ enabled: false });
const layout = page => page.evaluate(() => {
  const b = document.getElementById('dxp-bar').getBoundingClientRect();
  const h = document.getElementById('fx-header').getBoundingClientRect();
  return { barTop: Math.round(b.top), barBottom: Math.round(b.bottom), headerTop: Math.round(h.top) };
});
for (const sticky of [true, false]) {
  bar({ enabled: true, message: 'Darmowa dostawa od 200 zł do końca listopada.', button_text: 'Zobacz', button_url: 'https://example.com/', position: 'top', sticky, style: 'dark' });
  for (const fx of ['dx', 'abs', 'fixed']) {
    const label = `${fx}, ${sticky ? 'przyklejony' : 'nieprzyklejony'}`;
    ({ context, page } = await open({ width: 1440, height: 900 }));
    await page.goto(base + '?fx=' + fx, { waitUntil: 'load' });
    await page.waitForTimeout(300);
    let l = await layout(page);
    check(l.barTop === 0 && l.headerTop >= l.barBottom - 1, `${label}: nagłówek pod paskiem (pasek ${l.barTop}-${l.barBottom}, nagłówek od ${l.headerTop})`);
    if (fx === 'dx' && sticky) await page.screenshot({ path: path.join(shots, '7-naglowek-dx-gora.png') });
    await page.mouse.wheel(0, 1200);
    await page.waitForTimeout(400);
    l = await layout(page);
    if (sticky) {
      check(l.barTop === 0, `${label}: po przewinięciu pasek na górze ekranu`);
      if (fx === 'fixed') check(l.headerTop >= l.barBottom - 1, `${label}: po przewinięciu przyklejony nagłówek pod paskiem`);
    } else {
      check(l.barBottom <= 0, `${label}: po przewinięciu pasek odjechał ze stroną`);
      if (fx === 'fixed') check(l.headerTop === 0, `${label}: po przewinięciu przyklejony nagłówek wraca na górę ekranu`);
    }
    if (fx === 'dx' && sticky) await page.screenshot({ path: path.join(shots, '8-naglowek-dx-przewiniete.png') });
    await context.close();
  }
}
// Menu na telefonie zakrywa pasek, a zamknięcie paska przywraca nagłówek na miejsce.
bar({ enabled: true, message: 'Darmowa dostawa od 200 zł.', position: 'top', sticky: true, style: 'dark' });
({ context, page } = await open({ width: 390, height: 844 }));
await page.goto(base + '?fx=fixed', { waitUntil: 'load' });
await page.waitForTimeout(300);
// Menu zamknięte w warstwie nagłówka (z-index 10), jak na d-x.pl: pasek chowa się, gdy menu jest otwarte.
await page.evaluate(() => { const m = document.getElementById('fx-menu'); document.getElementById('fx-header').appendChild(m); m.classList.add('open'); });
await page.waitForTimeout(700);
check(await page.evaluate(() => { const r = document.getElementById('dxp-bar').getBoundingClientRect(); return document.elementFromPoint(r.left + 20, r.top + r.height / 2).closest('#fx-menu') !== null; }), 'otwarte menu w warstwie nagłówka jest nad paskiem');
await page.evaluate(() => document.getElementById('fx-menu').classList.remove('open'));
await page.waitForTimeout(700);
check(await page.evaluate(() => getComputedStyle(document.getElementById('dxp-bar')).visibility === 'visible'), 'po zamknięciu menu pasek wraca');
await page.click('#dxp-bar .dxp-bar__x');
check(await page.evaluate(() => Math.round(document.getElementById('fx-header').getBoundingClientRect().top) === 0 && !document.getElementById('dxp-bar-space')), 'po zamknięciu paska nagłówek wraca na górę');
await context.close();

// Przypadki z oceny Codexa: nagłówek ustawiony od dołu nie dostaje rosnącego marginesu, własny margines nagłówka
// wraca po zamknięciu paska, wąski panel boczny i niewidoczna nakładka nie chowają paska.
({ context, page } = await open({ width: 1440, height: 900 }));
await page.goto(base + '?fx=bottom', { waitUntil: 'load' });
await page.waitForTimeout(500);
const margin = () => page.evaluate(() => { const h = document.getElementById('fx-header').style; return h.marginTop + '|' + h.translate; });
const first = await margin();
for (let i = 0; i < 4; i += 1) { await page.evaluate(() => document.body.classList.toggle('fx-tick')); await page.waitForTimeout(300); }
await page.mouse.wheel(0, 400); await page.waitForTimeout(300);
check(await margin() === first, `nagłówek ustawiony od dołu: przesunięcie stałe (${first} i ${await margin()})`);
let l2 = await layout(page);
check(l2.headerTop === 0, `nagłówek ustawiony od dołu zostaje tam, gdzie postawił go motyw (${l2.headerTop})`);
await context.close();
// Nagłówek od dołu z animacją marginesu, wyśrodkowany, z menu fixed w środku: wtyczka go nie psuje,
// zostaje wyśrodkowany, a jego menu nadal zajmuje cały ekran i zakrywa pasek.
({ context, page } = await open({ width: 1440, height: 900 }));
await page.goto(base + '?fx=center', { waitUntil: 'load' });
await page.waitForTimeout(2500);
l2 = await layout(page);
// shortcut w bar.js: nagłówek od dołu nie schodzi pod pasek; sprawdzamy, że niczego nie psuje.
check(await page.evaluate(() => { const r = document.getElementById('fx-header').getBoundingClientRect(); return Math.abs(r.left + r.width / 2 - innerWidth / 2) < 2; }), 'wyśrodkowany nagłówek zostaje wyśrodkowany');
await page.evaluate(() => document.getElementById('fx-menu').classList.add('open'));
await page.waitForTimeout(700);
check(await page.evaluate(() => { const r = document.getElementById('fx-menu').getBoundingClientRect(); return r.top === 0 && Math.round(r.height) === innerHeight; }), 'menu fixed w nagłówku zajmuje cały ekran');
check(await page.evaluate(() => getComputedStyle(document.getElementById('dxp-bar')).visibility === 'hidden'), 'menu w nagłówku chowa pasek');
await context.close();
// Motyw zmienia top przypiętego nagłówka z 30 na 0 bez zmiany position: nagłówek dalej pod paskiem.
({ context, page } = await open({ width: 1440, height: 900 }));
await page.goto(base + '?fx=fixed', { waitUntil: 'load' });
await page.evaluate(() => document.getElementById('fx-header').style.setProperty('top', '30px'));
await page.waitForTimeout(500);
l2 = await layout(page);
check(l2.headerTop >= l2.barBottom - 1, `nagłówek z top 30px pod paskiem (${l2.barBottom}, ${l2.headerTop})`);
await page.evaluate(() => document.getElementById('fx-header').style.setProperty('top', '0px'));
await page.waitForTimeout(500);
l2 = await layout(page);
check(l2.headerTop >= l2.barBottom - 1, `po zmianie top na 0 nagłówek dalej pod paskiem (${l2.barBottom}, ${l2.headerTop})`);
// Motyw przestawia nagłówek niżej (top 80px): wtyczka nie cofa tego przy zdejmowaniu marginesu.
await page.evaluate(() => document.getElementById('fx-header').style.setProperty('top', '80px'));
await page.waitForTimeout(500);
l2 = await layout(page);
check(l2.headerTop === 80, `nagłówek przestawiony przez motyw na 80px zostaje na 80px (${l2.headerTop})`);
await context.close();
({ context, page } = await open({ width: 1440, height: 900 }));
await page.goto(base + '?fx=inline', { waitUntil: 'load' });
await page.waitForTimeout(400);
await page.click('#dxp-bar .dxp-bar__x');
check(await page.evaluate(() => document.getElementById('fx-header').style.marginTop) === '20px', 'po zamknięciu paska nagłówek ma z powrotem własny margines 20px');
await context.close();
({ context, page } = await open({ width: 1440, height: 900 }));
await page.goto(base + '?fx=fixed', { waitUntil: 'load' });
await page.evaluate(() => {
  const side = document.createElement('div');
  side.style.cssText = 'position:fixed;top:0;left:0;width:100px;height:100vh;background:#fde68a;z-index:20';
  const ghost = document.createElement('div');
  ghost.style.cssText = 'opacity:0';
  ghost.innerHTML = '<div style="position:fixed;inset:0;z-index:30"></div>';
  document.body.append(side, ghost);
});
await page.waitForTimeout(700);
check(await page.evaluate(() => getComputedStyle(document.getElementById('dxp-bar')).visibility === 'visible'), 'wąski panel boczny i niewidoczna nakładka nie chowają paska');
await context.close();

// Zalogowany administrator: pasek pod paskiem administratora, nagłówek pod paskiem.
({ context, page } = await open({ width: 1440, height: 900 }));
await page.goto(base + 'wp-login.php');
await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'local-fixture-only');
await page.click('#wp-submit');
await page.waitForLoadState('load');
await page.goto(base + '?fx=abs', { waitUntil: 'load' });
await page.waitForTimeout(300);
const admin = await page.evaluate(() => ({ admin: Math.round(document.getElementById('wpadminbar').getBoundingClientRect().bottom), ...(() => { const b = document.getElementById('dxp-bar').getBoundingClientRect(); const h = document.getElementById('fx-header').getBoundingClientRect(); return { barTop: Math.round(b.top), barBottom: Math.round(b.bottom), headerTop: Math.round(h.top) }; })() }));
check(admin.barTop === admin.admin && admin.headerTop >= admin.barBottom - 1, `administrator: pasek pod paskiem administratora, nagłówek pod paskiem (${JSON.stringify(admin)})`);
await page.screenshot({ path: path.join(shots, '9-administrator.png') });
await context.close();

await browser.close();
popup({ enabled: false });
bar({ enabled: false });
console.log(failures ? `BŁĘDY: ${failures}` : 'WSZYSTKO OK');
process.exit(failures ? 1 : 0);
