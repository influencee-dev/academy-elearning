// Copia le schermate nella cartella pubblica di Remotion e scrive src/dims.json con le misure di ogni immagine.
const fs = require('fs');
const path = require('path');
const SRC = process.env.IMG_SRC || 'img-src';
fs.rmSync('public/img', { recursive: true, force: true });
fs.mkdirSync('public/img', { recursive: true });
const dims = {};
for (const f of fs.readdirSync(SRC)) {
  if (!f.endsWith('.png')) continue;
  fs.copyFileSync(path.join(SRC, f), path.join('public/img', f));
  const b = fs.readFileSync(path.join(SRC, f));
  const DSF = Number(process.env.DSF || 2);
  dims[f] = { w: Math.round(b.readUInt32BE(16) / DSF), h: Math.round(b.readUInt32BE(20) / DSF) };
}
fs.writeFileSync('src/dims.json', JSON.stringify(dims, null, 1));
console.log('Schermate copiate:', Object.keys(dims).length);
