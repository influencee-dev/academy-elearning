import puppeteer from 'puppeteer-core';
import fs from 'node:fs';
const OUT = process.argv[2];
const stud = JSON.parse(fs.readFileSync(process.argv[3], 'utf8'));
fs.mkdirSync(OUT, { recursive: true });
const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: 'new' });
const B = 'http://academy-test.local';
async function go(page, url, name, opts = {}) {
  await page.goto(B + url, { waitUntil: 'networkidle2' });
  await new Promise((r) => setTimeout(r, 400));
  await page.screenshot({ path: `${OUT}/${name}.jpg`, type: 'jpeg', quality: 82, ...opts });
  console.log('ok', name);
}
// Visitatore, desktop
let ctx = await browser.createBrowserContext();
let p = await ctx.newPage();
await p.setViewport({ width: 1440, height: 900, deviceScaleFactor: 1 });
await go(p, '/', 'home-1');
await go(p, '/', 'home-2', { clip: { x: 0, y: 880, width: 1440, height: 1000 }, captureBeyondViewport: true });
await go(p, '/catalogo/', 'catalogo', { clip: { x: 0, y: 0, width: 1440, height: 760 } });
await go(p, '/corsi/reel-verticali/', 'corso', { clip: { x: 0, y: 0, width: 1440, height: 1000 }, captureBeyondViewport: true });
await go(p, '/docenti/elena-esempio/', 'docente', { clip: { x: 0, y: 0, width: 1440, height: 1000 }, captureBeyondViewport: true });
await go(p, '/iscriviti/', 'iscrizione', { clip: { x: 0, y: 0, width: 1440, height: 860 } });
// Telefono
await p.setViewport({ width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
await go(p, '/', 'tel-home');
await go(p, '/corsi/reel-verticali/', 'tel-corso');
await ctx.close();
// Studente collegato
ctx = await browser.createBrowserContext();
p = await ctx.newPage();
await p.setViewport({ width: 1440, height: 900, deviceScaleFactor: 1 });
for (const c of stud) await p.setCookie({ ...c, domain: 'academy-test.local' });
await go(p, '/i-miei-corsi/', 'miei-corsi', { clip: { x: 0, y: 0, width: 1440, height: 760 } });
await go(p, '/mio-account/', 'account', { clip: { x: 0, y: 0, width: 1440, height: 700 } });
await ctx.close();
await browser.close();
