// Crea il PDF della guida per i docenti a partire da docs/GUIDA-DOCENTI.md (uso: node build-guide.mjs)
import { marked } from 'marked';
import puppeteer from 'puppeteer-core';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const docs = path.resolve('..', '..');
let md = fs.readFileSync(path.join(docs, 'GUIDA-DOCENTI.md'), 'utf8');
md = md.replace(/\(tutorial-docenti\/img\//g, '(' + pathToFileURL(path.join(docs, 'tutorial-docenti', 'img')).href + '/');
md = md.replace(/Esiste anche il video[^\n]*\n/, '');
const body = marked.parse(md);
const html = `<!doctype html><html lang="it"><meta charset="utf-8"><style>
@page { size: A4; margin: 18mm 16mm; }
body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11.5pt; line-height: 1.5; color: #0A0A0A; }
h1 { font-size: 24pt; letter-spacing: -.02em; margin: 0 0 8pt; }
h2 { font-size: 15pt; margin: 22pt 0 8pt; padding: 3pt 8pt; background: linear-gradient(transparent 60%, #C7F000 60%); display: inline-block; break-after: avoid; }
p, li { orphans: 3; widows: 3; }
img { max-width: 100%; border: 1px solid #E4E4E4; border-radius: 8px; margin: 6pt 0; break-inside: avoid; }
li > p { margin: 4pt 0; }
code { background: #F6F6F6; padding: 1pt 4pt; border-radius: 4px; }
strong { color: #0A0A0A; }
hr { border: 0; border-top: 1px solid #E4E4E4; }
</style><body>${body}</body></html>`;
const tmp = path.join(docs, 'tutorial-docenti', '.guida.html');
fs.writeFileSync(tmp, html);
const browser = await puppeteer.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: 'new' });
const page = await browser.newPage();
await page.goto(pathToFileURL(tmp).href, { waitUntil: 'networkidle0' });
await page.pdf({ path: path.join(docs, 'tutorial-docenti', 'Guida-docenti.pdf'), format: 'A4', printBackground: true, margin: { top: '18mm', bottom: '18mm', left: '16mm', right: '16mm' } });
await browser.close();
fs.unlinkSync(tmp);
console.log('PDF creato');
