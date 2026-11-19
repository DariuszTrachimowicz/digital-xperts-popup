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

await browser.close();
popup({ enabled: false });
bar({ enabled: false });
console.log(failures ? `BŁĘDY: ${failures}` : 'WSZYSTKO OK');
process.exit(failures ? 1 : 0);
