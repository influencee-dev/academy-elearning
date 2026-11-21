// Immagini neutre (nessuna persona né marchio) per i contenuti di esempio del tutorial.
import puppeteer from 'puppeteer-core';
import path from 'node:path';
import fs from 'node:fs';
const out = path.resolve(process.argv[2]);
fs.mkdirSync(out, { recursive: true });
const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: 'new' });
const page = await browser.newPage();
const items = [
  ['demo-docente.png', 800, 1000, `<div style="width:800px;height:1000px;background:linear-gradient(160deg,#E6007E,#0A0A0A);position:relative;overflow:hidden"><div style="position:absolute;left:250px;top:250px;width:300px;height:300px;border-radius:50%;background:rgba(255,255,255,.9)"></div><div style="position:absolute;left:130px;top:600px;width:540px;height:540px;border-radius:50%;background:rgba(255,255,255,.9)"></div></div>`],
  ['demo-copertina-docente.png', 1600, 600, `<div style="width:1600px;height:600px;background:linear-gradient(100deg,#0A0A0A,#E6007E 70%,#C7F000)"></div>`],
  ['demo-corso.png', 1200, 1500, `<div style="width:1200px;height:1500px;background:linear-gradient(140deg,#0A0A0A 10%,#E6007E 80%);position:relative;overflow:hidden"><div style="position:absolute;left:380px;top:560px;width:440px;height:440px;border-radius:50%;border:90px solid #C7F000;border-right-color:transparent"></div></div>`],
];
for (const [name, w, h, html] of items) {
  await page.setViewport({ width: w, height: h, deviceScaleFactor: 1 });
  await page.setContent('<body style="margin:0">' + html + '</body>');
  await page.screenshot({ path: path.join(out, name) });
}
await browser.close();
console.log('ok');
