# Tutorial per i docenti: guida scritta e video

Uno solo per tutte le academy: gli schermi sono quelli dell'amministrazione, uguali ovunque. I dati nelle immagini sono inventati
(docente "Elena Esempio", corso "Reel verticali"). Questa cartella è solo per lo sviluppo: non va nello zip del plugin.

| Cosa | Dove |
|---|---|
| Guida scritta | `../GUIDA-DOCENTI.md` e il PDF `Guida-docenti.pdf` (da mandare ai docenti) |
| Schermate | `img/` |
| Testo per la voce | `VOCE.md` |
| Video (sorgente) | `video/` (Remotion). Il file finito, `tutorial-docenti.mp4`, si genera e non è nel repository |
| Come rifare le schermate | `capture/` |

## Quando il plugin cambia

Se una schermata cambia (un nome, un pulsante), si rifanno schermate, PDF e video così:

1. **Schermate.** Sul sito di prova (LocalWP acceso) servono i dati di esempio e un accesso valido:
   - `php setup-demo.php <cartella-immagini> <cookie.json>` crea il docente e il corso di esempio (una volta sola; le immagini neutre
     si fanno con `node demo-assets.mjs <cartella>`);
   - `COOKIES=<cookie.json> node capture.mjs` rifà tutte le schermate in `img/`.
   (Dentro `capture/` prima si fa `npm install`.)
2. **PDF della guida.** Da `capture/`: `node build-guide.mjs`.
3. **Video.** Windows non regge i percorsi lunghi di questa cartella: copia `video/` in una cartella corta (per esempio
   `C:\ael-video`), copia `img/` in `C:\ael-video\img-src`, poi:
   ```
   npm install
   npm run prepare-img
   npm run render
   ```
   Il video esce in `out/tutorial-docenti.mp4`. I testi e i tempi sono in `src/scenes.ts`.

## Prima di mandare ai docenti

- Controlla che il testo del video e della guida corrisponda a quello che il plugin fa in quel momento (per esempio: il docente non
  imposta il prezzo e non pubblica da solo).
- Il video non ha la voce: ha le didascalie. Se si aggiunge la voce, il testo è in `VOCE.md`.

## Demo in un solo file HTML (per mostrare l'academy a chi non ha accesso al sito)

`capture/build-demo.mjs` salva le pagine vere del sito di prova (home, catalogo, corso, docente, iscrizione, accesso, carrello,
pagamento, I miei corsi, account) in un unico file HTML che si apre da qualsiasi browser, anche da telefono e senza internet.
Le pagine sono collegate, il menu a tre righe funziona, l'iscrizione, l'accesso e l'acquisto sono simulati e nessun dato viene inviato.

```
COOKIES_STUDENT=<cookie-studente.json> node build-demo.mjs ../../../Academy-Influencee-demo.html
```
(LocalWP acceso, con il docente e il corso di esempio e almeno un corso nel carrello.)
