# Stato del progetto (aggiornato il 17/11/2026)

Scadenza: **30/11/2026** (incluso). Tutto ciò che è previsto va consegnato entro quella data: niente scale di
priorità, si fa tutto. Giorni lavorativi esclusi: 18, 19, 20 novembre e i weekend.

## Dove siamo

**Il plugin è completo e provato.** Ogni parte è stata provata dall'utente nel browser su LocalWP.

| Parte | Stato |
|---|---|
| Core: contenuti, accessi (`AEL_Access`), catalogo, scheda corso, lezione con video protetto, account, progressi, studenti, panoramica | Fatto e provato |
| Schede docente personalizzabili (frase, copertina, colore, link, riquadro "Il docente") | Fatto e provato |
| Modulo `single-purchase` (WooCommerce, checkout classico, consenso al recesso, rimborsi) | Fatto e provato |
| Modulo `subscription` (Stripe: Checkout, portale clienti, webhook firmato) | Fatto e provato (sandbox + Stripe CLI) |
| Modulo `teacher-revenue` (registro vendite e quote) | Fatto e provato |
| Modulo `teacher-panel` ("Le mie vendite") | Fatto e provato |
| Modulo `attribution` (UTM, cookie con consenso, "Come ci hai conosciuto?") | Fatto e provato |
| Modulo `magic-link` | Fatto e provato (email via Mailpit di LocalWP) |
| Modulo `teacher-authoring` ("Docenti autori") | Fatto e provato |
| Disinstallazione completa, traduzioni (.pot), script dello zip, guide | Fatto |

Codice: ramo **`feature/core-base`** su `https://github.com/influencee-dev/academy-elearning` (pubblicato; `main`
non è stato toccato). Versione nel codice: 0.1.0 (si porta a 1.0.0 alla chiusura).

## Cosa resta da fare

1. **Parte estetica: prima l'academy di Influencee (entro il 30/11)**: tema dell'academy in un repository separato
   (vedi sotto). Decisione del 21/11/2026: Specialty è rimandata, si parte da Influencee.
2. **Collaudo finale**: giro completo con `docs/PROVE-MANUALI.md`, meglio su un sito pulito con lo zip.
3. **Chiusura**: versione 1.0.0 (`academy-elearning.php`, `AEL_VERSION`, intestazione del CHANGELOG), rifare lo zip
   con `bin\build-zip.ps1`, togliere il consenso cookie di prova dal sito locale, unire `feature/core-base` a `main`
   (solo dopo il via dell'utente).
4. **Installazione reale** su Specialty: guida in `docs/GUIDA-AGENZIA.md` (chiavi Stripe del cliente, WooCommerce,
   consenso cookie collegato al loro banner, webhook vero).

## Tema dell'academy di Influencee (da fare per primo; Specialty dopo)

Brand di Influencee: fondo bianco, nero `#0A0A0A`, magenta `#E6007E`, verde acido `#C7F000` (solo su nero), grigi
`#F6F6F6` / `#E4E4E4` / `#6B6B6B`, font Manrope, angoli arrotondati, etichette a pillola, una parola d'accento
per titolo. Logo: solo i file ufficiali (mai su magenta, mai ricolorato). Tema figlio di Twenty Twenty-Five, nome
`academy-influencee`.

Le indicazioni qui sotto valgono per qualsiasi academy (anche Specialty, più avanti).

## Tema di un'academy (regole generali)

Regola di progetto: la grafica sta in un **tema separato per ogni academy**, in un repository a parte; il plugin non
contiene mai nulla di una singola academy. Il plugin offre:

- variabili CSS da ridefinire (`--ael-accent`, `--ael-accent-contrast`, `--ael-border`, `--ael-surface`,
  `--ael-surface-alt`, `--ael-muted`, `--ael-radius`, `--ael-gap`);
- template sostituibili copiandoli in `academy-elearning/` nel tema: `catalog.php`, `course-card.php`, `course.php`,
  `lesson.php`, `program.php`, `actions.php`, `teacher.php`, `teacher-box.php`, `collection.php`, `login.php`,
  `my-courses.php`, `subscribe.php`, `subscription-box.php`;
- il video e i materiali arrivano già protetti dal controllo di accesso: nel tema non si costruiscono mai a mano.

Per realizzarlo servono da Specialty: logo (SVG/PNG), colori, font, tono e immagini, esempi o riferimenti grafici,
struttura delle pagine (home, menu, footer), testi di catalogo/abbonamento/email, dominio. Materiali di contesto già
presenti: `docs/academy-specialty/Specialty-Academy-online-progetto.pdf` e la skill `coaching-aziendale-esperto`.
Da decidere: tema a blocchi (come Twenty Twenty-Five) o classico, e nome del repository (proposta:
`academy-theme-specialty`).

## Ambiente di prova (LocalWP)

- Sito `academy-test`: `http://academy-test.local`; cartella `C:\Users\maril\Local Sites\academy-test\app\public`.
- Il plugin sul sito è una copia della cartella del repository: si aggiorna con
  `robocopy <repo> <sito>\wp-content\plugins\academy-elearning /E /XD .git docs dist`.
- Moduli accesi per le prove: tutti e 7. WooCommerce attivo (euro, Italia, IVA 22% inclusa, bonifico di prova).
- Stripe in sandbox: prodotto mensile creato, portale clienti attivo; chiavi nelle impostazioni del plugin
  (mai nel repository né in chat). Per ricevere gli eventi serve `stripe listen --events <elenco> --forward-to
  http://academy-test.local/wp-json/ael/v1/stripe-webhook` (il segreto che stampa va nella pagina Abbonamento).
- File di prova da togliere a fine collaudo: `wp-content/mu-plugins/ael-test-consent.php` (consenso cookie di prova).
- Utenti di prova: `admin`, `studente.prova`, `Mario Rossi` (docente collegato ad Anna Bianchi), clienti di prova.
- PHP di LocalWP per i controlli: `C:\Users\maril\AppData\Roaming\Local\lightning-services\php-8.2.29+0\bin\win64\php.exe`.

## Regole da ricordare

- Mai chiavi o segreti nel codice, nei commenti, nei test o nei log. Mai chiavi live in sviluppo.
- Mai lavorare su `main`: si lavora su rami `feature/...` e si unisce a fine prova.
- Gli interruttori dei moduli e delle opzioni del core li decide solo l'agenzia (capacità `ael_manage_modules`).
- I docenti si chiamano "docenti" (anche quelli di Specialty), non "creator".

## Configurazione dell'academy di Influencee (decisione del 21/11/2026)

Solo **corsi singoli**, con docenti singoli che ricevono una quota. Moduli accesi: `single-purchase`,
`teacher-revenue`, `teacher-panel`, `teacher-authoring`, `attribution`, `magic-link`. Modulo `subscription` **spento**
(il tema non ha menu, pulsanti né sezioni sull'abbonamento). Lo spegnimento sul sito di prova è del 21/11.

## Novità del 21/11/2026 (valgono per tutte le academy)

- **Formato dei contenuti** (Configurazione → Opzioni del core): orizzontale 16:9 oppure verticale 9:16. Influencee usa il
  verticale (lo ha impostato l'agenzia sul sito di prova).
- **Schede docente con i numeri**: canali social con follower ed engagement rate, data di aggiornamento, foto grande.
  I numeri si scrivono a mano nella scheda del docente (Academy → Docenti → Canali e numeri).
- Il lettore video riprende dall'ultima posizione anche a lezione completata; la scheda corso mostra la barra dei progressi.
- Tema Influencee: verde acido su numeri, prezzi, badge e riquadro docente; intestazione con pulsante "Accedi" come
  influencee.it. Repository del tema da collegare (`influencee-dev/academy-theme-influencee`).

## Tutorial per i docenti (21/11/2026)

Uno solo per tutte le academy: guida scritta `docs/GUIDA-DOCENTI.md` (con PDF `docs/tutorial-docenti/Guida-docenti.pdf`) e video
(sorgente in `docs/tutorial-docenti/video/`, testo per la voce in `VOCE.md`). Come rifarli se il plugin cambia: `docs/tutorial-docenti/README.md`.
Sul sito di prova esiste un docente di esempio ("Elena Esempio", utente `docente.esempio`) usato per le schermate.
