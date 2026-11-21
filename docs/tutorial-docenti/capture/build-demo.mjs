// Costruisce una DEMO in un solo file HTML a partire dalle pagine vere del sito di prova (LocalWP acceso).
// Il file si apre da qualsiasi browser, senza internet e senza WordPress: le pagine sono collegate tra loro,
// i moduli sono simulati e nessun dato viene inviato.
// Uso: COOKIES_STUDENT=cookie-studente.json node build-demo.mjs <file-di-uscita.html>
import puppeteer from 'puppeteer-core';
import fs from 'node:fs';

const BASE = 'http://academy-test.local';
const OUT = process.argv[2] || 'demo.html';
const student = JSON.parse(fs.readFileSync(process.env.COOKIES_STUDENT, 'utf8'));

// Pagine della demo: chiave, indirizzo vero, chi le vede.
const PAGES = [
  { key: 'home', url: '/', who: 'guest' },
  { key: 'catalogo', url: '/catalogo/', who: 'guest' },
  { key: 'corso', url: '/corsi/reel-verticali/', who: 'guest' },
  { key: 'docente', url: '/docenti/elena-esempio/', who: 'guest' },
  { key: 'iscriviti', url: '/iscriviti/', who: 'guest' },
  { key: 'accedi', url: '/accedi/', who: 'guest' },
  { key: 'carrello', url: '/carrello/', who: 'guest', addToCart: true },
  { key: 'pagamento', url: '/pagamento/', who: 'guest' },
  { key: 'miei-corsi', url: '/i-miei-corsi/', who: 'student' },
  { key: 'account', url: '/mio-account/', who: 'student' },
];

const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: 'new' });
const dataUrl = async (url) => {
  const r = await fetch(url);
  if (!r.ok) throw new Error(url + ' ' + r.status);
  const type = (r.headers.get('content-type') || 'application/octet-stream').split(';')[0];
  return `data:${type};base64,${Buffer.from(await r.arrayBuffer()).toString('base64')}`;
};

const pages = {};
const sheets = new Set();

async function grab(page, def) {
  await page.goto(BASE + def.url, { waitUntil: 'networkidle2' });
  await new Promise((r) => setTimeout(r, 500));
  const res = await page.evaluate(async (def) => {
    const MAP = [
      [/logout/, '#/'],
      [/add-to-cart=/, '#/carrello'],
      [/^\/carrello\/?/, '#/carrello'],
      [/^\/pagamento\/?/, '#/pagamento'],
      [/^\/catalogo\/?/, '#/catalogo'],
      [/^\/corsi\//, '#/corso'],
      [/^\/lezioni\//, '#/corso'],
      [/^\/docenti\//, '#/docente'],
      [/^\/iscriviti\/?/, '#/iscriviti'],
      [/^\/accedi\/?/, '#/accedi'],
      [/^\/i-miei-corsi\/?/, '#/miei-corsi'],
      [/^\/mio-account\/?/, '#/account'],
      [/wp-login\.php/, '#/'],
      [/^\/?$/, '#/'],
    ];
    const here = location.origin;
    document.querySelectorAll('a[href]').forEach((a) => {
      const raw = a.getAttribute('href');
      if (!raw || raw.startsWith('mailto:') || raw.startsWith('tel:')) return;
      let u;
      try { u = new URL(a.href, location.href); } catch { return; }
      if (u.origin !== here) { a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener'); return; }
      const full = u.pathname + u.search;
      if (u.hash === '#come-funziona' && /^\/?$/.test(u.pathname)) { a.setAttribute('href', '#/?s=come-funziona'); return; }
      let to = '#/nd';
      for (const [re, h] of MAP) { if (re.test(full)) { to = h; break; } }
      a.setAttribute('href', to);
    });
    // Immagini: in dati incorporati.
    for (const img of document.querySelectorAll('img')) {
      const src = img.currentSrc || img.src;
      if (!src || src.startsWith('data:')) continue;
      try {
        const blob = await (await fetch(src)).blob();
        const data = await new Promise((ok) => { const f = new FileReader(); f.onload = () => ok(f.result); f.readAsDataURL(blob); });
        img.setAttribute('src', data);
        img.removeAttribute('srcset'); img.removeAttribute('sizes'); img.removeAttribute('loading');
      } catch (e) { /* resta com'è */ }
    }
    // Fogli di stile letti dal browser.
    const css = [];
    for (const s of document.styleSheets) {
      try {
        const t = [...s.cssRules].map((r) => r.cssText).join('\n');
        // Alcuni fogli (per esempio quello di WooCommerce per gli schermi piccoli) hanno la condizione sul collegamento, non nelle regole.
        const m = s.media && s.media.mediaText;
        css.push(m && m !== 'all' ? '@media ' + m + ' {\n' + t + '\n}' : t);
      } catch (e) { /* esterno */ }
    }
    document.querySelectorAll('script, noscript, #wpadminbar, link[rel="stylesheet"], style').forEach((n) => n.remove());
    return { html: document.body.innerHTML, cls: document.body.className, title: document.title, css };
  }, def);
  pages[def.key] = { html: res.html, cls: res.cls, title: res.title };
  res.css.forEach((c) => sheets.add(c));
  console.log('ok', def.key, Math.round(res.html.length / 1024) + ' KB');
}

// Visitatore (con il corso nel carrello)
{
  const ctx = await browser.createBrowserContext();
  const page = await ctx.newPage();
  await page.setViewport({ width: 1440, height: 900 });
  for (const def of PAGES.filter((p) => p.who === 'guest')) {
    if (def.addToCart) await page.goto(BASE + '/carrello/?add-to-cart=73', { waitUntil: 'networkidle2' });
    await grab(page, def);
  }
  await ctx.close();
}
// Studente collegato
{
  const ctx = await browser.createBrowserContext();
  const page = await ctx.newPage();
  await page.setViewport({ width: 1440, height: 900 });
  for (const c of student) await page.setCookie({ ...c, domain: 'academy-test.local' });
  for (const def of PAGES.filter((p) => p.who === 'student')) await grab(page, def);
  await ctx.close();
}
await browser.close();

// Stili: tutti i fogli visti, senza doppioni, con font e immagini incorporati.
let css = [...sheets].join('\n');
const urls = [...new Set([...css.matchAll(/url\(\s*["']?([^"')]+)["']?\s*\)/g)].map((m) => m[1]))].filter((u) => !u.startsWith('data:') && !u.startsWith('#'));
for (const u of urls) {
  try {
    const abs = new URL(u, BASE + '/').href;
    const d = await dataUrl(abs);
    css = css.split(u).join(d);
  } catch (e) { console.log('risorsa non incorporata:', u.slice(0, 80)); }
}
// Il font del sito di prova arriva dal tema: se un'immagine di sfondo manca, la pagina resta leggibile.

const tpl = Object.entries(pages).map(([k, p]) => `<template id="pg-${k}" data-cls="${p.cls.replace(/"/g, '&quot;')}" data-title="${p.title.replace(/ – academy-test$/, ' · Demo Academy Influencee').replace(/"/g, '&quot;')}">${p.html}</template>`).join('\n');

const html = `<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Academy Influencee: demo</title>
<style>${css}
.wp-block-button.is-style-outline .wp-block-button__link{border:1.5px solid currentColor}
.demo-badge{position:fixed;left:12px;bottom:12px;z-index:99999;display:flex;gap:8px;align-items:center;padding:8px 14px;border-radius:999px;background:#0A0A0A;color:#fff;font:600 12px/1.2 Manrope,Segoe UI,Arial,sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.25)}
.demo-badge b{color:#C7F000}
.demo-toast{position:fixed;left:50%;bottom:64px;transform:translateX(-50%) translateY(20px);z-index:99999;max-width:min(92vw,520px);padding:12px 18px;border-radius:16px;background:#0A0A0A;color:#fff;font:600 14px/1.4 Manrope,Segoe UI,Arial,sans-serif;opacity:0;pointer-events:none;transition:opacity .2s,transform .2s;text-align:center}
.demo-toast.on{opacity:1;transform:translateX(-50%) translateY(0)}
</style>
</head>
<body>
<div id="app"></div>
<div class="demo-badge"><b>DEMO</b> dati e immagini inventati · nulla viene inviato</div>
<div class="demo-toast" id="toast" role="status"></div>
${tpl}
<script>
(function () {
  var app = document.getElementById('app');
  var toastEl = document.getElementById('toast');
  var timer;
  function toast(msg) { toastEl.textContent = msg; toastEl.classList.add('on'); clearTimeout(timer); timer = setTimeout(function () { toastEl.classList.remove('on'); }, 3200); }
  var CLS0 = '';
  function route() {
    var raw = location.hash.replace(/^#\\/?/, '');
    var parts = raw.split('?');
    var key = parts[0] || 'home';
    var scroll = (parts[1] || '').replace(/^s=/, '');
    if (key === 'nd') { toast('Questa parte non è nella demo.'); history.replaceState(null, '', '#/'); key = 'home'; }
    var t = document.getElementById('pg-' + key) || document.getElementById('pg-home');
    app.innerHTML = '';
    app.appendChild(t.content.cloneNode(true));
    document.body.className = t.getAttribute('data-cls');
    document.title = t.getAttribute('data-title');
    var target = scroll && document.getElementById(scroll);
    if (target) { target.scrollIntoView(); } else { window.scrollTo(0, 0); }
  }
  window.addEventListener('hashchange', route);
  document.addEventListener('submit', function (e) {
    e.preventDefault();
    var f = e.target;
    var inCheckout = !!f.closest('.woocommerce-checkout, form.checkout');
    if (f.closest('.ael-register-box')) { toast('Demo: iscrizione simulata. Ora sei dentro come studente.'); location.hash = '#/miei-corsi'; return; }
    if (f.closest('.ael-login-box') || f.id === 'loginform') { toast('Demo: accesso simulato come studente.'); location.hash = '#/miei-corsi'; return; }
    if (inCheckout) { toast('Demo: ordine simulato, nessun pagamento. Ora il corso è nei tuoi corsi.'); location.hash = '#/miei-corsi'; return; }
    toast('Demo: i moduli non inviano nulla.');
  }, true);
  document.addEventListener('click', function (e) {
    var el = e.target.closest ? e.target.closest('button, input[type=submit]') : null;
    if (el && el.closest('form')) return;
    if (el && el.type !== 'submit' && !el.closest('details')) toast('Demo: questo pulsante non fa nulla.');
  });
  document.addEventListener('click', function (e) {
    var d = document.querySelectorAll('details.ai-user[open]');
    d.forEach(function (x) { if (!x.contains(e.target)) x.removeAttribute('open'); });
  });
  // Menu a tre righe: in WordPress lo apre uno script che nella demo non c'è, quindi lo apriamo qui.
  function setMenu(open) {
    document.querySelectorAll('.wp-block-navigation__responsive-container').forEach(function (c) {
      c.classList.toggle('is-menu-open', open);
      c.classList.toggle('has-modal-open', open);
      c.setAttribute('aria-hidden', open ? 'false' : 'true');
    });
    document.documentElement.classList.toggle('has-modal-open', open);
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest('.wp-block-navigation__responsive-container-open')) { e.preventDefault(); setMenu(true); return; }
    if (e.target.closest('.wp-block-navigation__responsive-container-close')) { e.preventDefault(); setMenu(false); return; }
    if (e.target.closest('.wp-block-navigation__responsive-container.is-menu-open a')) { setMenu(false); }
  });
  window.addEventListener('hashchange', function () { setMenu(false); });
  route();
})();
</script>
</body>
</html>`;
fs.writeFileSync(OUT, html);
console.log('Demo scritta:', OUT, Math.round(html.length / 1024) + ' KB');
