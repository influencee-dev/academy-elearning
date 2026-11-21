// Fa le schermate della guida per i docenti dal sito di prova (utente docente).
// Uso: COOKIES=cookies.json node capture.mjs
import puppeteer from 'puppeteer-core';
import fs from 'node:fs';
import path from 'node:path';

const BASE = 'http://academy-test.local';
const OUT = path.resolve('..', 'img');
const cookies = JSON.parse(fs.readFileSync(process.env.COOKIES, 'utf8'));
fs.mkdirSync(OUT, { recursive: true });

const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: 'new' });
const HIDE = '.update-nag,.notice,#wpfooter,.wp-pointer,#screen-meta-links,#wp-admin-bar-new-content{display:none!important}';

// Ritagli delle schermate intere (per togliere gli spazi vuoti)
const CLIPS = { '01-accesso': { x: 420, y: 60, width: 600, height: 560 }, '03-elenco-corsi': { x: 0, y: 0, width: 1440, height: 430 } };

async function open(page, url) {
  await page.goto(BASE + url, { waitUntil: 'networkidle2' });
  await page.addStyleTag({ content: HIDE });
}
async function shot(page, name, selector, pad = 12) {
  const file = path.join(OUT, name + '.png');
  if (!selector) { const clip = CLIPS[name]; await page.screenshot(clip ? { path: file, clip } : { path: file }); console.log('ok', name); return; }
  const rect = await page.evaluate((sel) => {
    const e = document.querySelector(sel);
    if (!e) return null;
    const bar = document.getElementById('wpadminbar'); if (bar) bar.style.display = 'none';
    const r = e.getBoundingClientRect();
    return { x: r.left + window.scrollX, y: r.top + window.scrollY, width: r.width, height: r.height };
  }, selector);
  if (!rect) { console.log('MANCA', name, selector); return; }
  const clip = { x: Math.max(0, rect.x - pad), y: Math.max(0, rect.y - pad), width: rect.width + pad * 2, height: rect.height + pad * 2 };
  await page.screenshot({ path: file, clip, captureBeyondViewport: true });
  console.log('ok', name, Math.round(clip.width) + 'x' + Math.round(clip.height));
}

// 1. Accesso (senza essere collegati)
{
  const ctx = await browser.createBrowserContext();
  const page = await ctx.newPage();
  await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 2 });
  await page.goto(BASE + '/wp-login.php', { waitUntil: 'networkidle2' });
  await shot(page, '01-accesso');
  await ctx.close();
}

const page = await browser.newPage();
await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 2 });
for (const c of cookies) await page.setCookie({ ...c, domain: 'academy-test.local' });

await open(page, '/wp-admin/index.php');            await shot(page, '02-bacheca');
await open(page, '/wp-admin/edit.php?post_type=ael_course'); await shot(page, '03-elenco-corsi');
await open(page, '/wp-admin/post.php?post=61&action=edit');
await shot(page, '04-scheda-docente');
await shot(page, '05-immagine-docente', '#postimagediv');
await shot(page, '06-scheda-pubblica', '#ael_teacher_profile');
await shot(page, '06a-frase', '#ael_teacher_profile tbody > tr:nth-of-type(1)');
await shot(page, '06b-copertina', '#ael_teacher_profile tbody > tr:nth-of-type(2)');
await shot(page, '06c-colore', '#ael_teacher_profile tbody > tr:nth-of-type(3)');
await shot(page, '06d-canali', '#ael_teacher_profile tbody > tr:nth-of-type(4)');
await shot(page, '06e-link', '#ael_teacher_profile tbody > tr:nth-of-type(5)');
await open(page, '/wp-admin/post-new.php?post_type=ael_course');
await shot(page, '07a-nuovo-corso');
await shot(page, '11a-invia-revisione', '#submitdiv');
await open(page, '/wp-admin/post.php?post=62&action=edit');
await shot(page, '07-corso');
await shot(page, '08-dettagli-corso', '#ael_course_details');
await shot(page, '09-programma', '#ael_program');
await shot(page, '10-copertina-corso', '#postimagediv');
await shot(page, '11-pubblica', '#submitdiv');
await open(page, '/wp-admin/post.php?post=67&action=edit');
await shot(page, '12-lezione');
await shot(page, '13-video-lezione', '#ael_lesson_details');
await shot(page, '14-materiali', '#ael_materials');
await open(page, '/wp-admin/admin.php?page=ael-my-sales'); await shot(page, '15-vendite');
await open(page, '/wp-admin/profile.php'); await shot(page, '16-profilo');
// Come lo vedono gli studenti (senza essere collegati)
{
  const ctx = await browser.createBrowserContext();
  const pub = await ctx.newPage();
  await pub.setViewport({ width: 1440, height: 900, deviceScaleFactor: 2 });
  await pub.goto(BASE + '/corsi/reel-verticali/', { waitUntil: 'networkidle2' });
  await shot(pub, '17-corso-pubblico');
  await pub.goto(BASE + '/docenti/elena-esempio/', { waitUntil: 'networkidle2' });
  await shot(pub, '18-docente-pubblico');
  await ctx.close();
}
await browser.close();
