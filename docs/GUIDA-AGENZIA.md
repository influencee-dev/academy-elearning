# Academy E-learning: guida per l'agenzia

Come installare, configurare e consegnare un'academy. Il plugin è uno solo per tutte le academy: ogni
cliente si distingue per i moduli che accendiamo, le impostazioni e il tema. Il codice non si tocca mai per
un singolo cliente.

## 1. Cosa serve

- WordPress 6.4 o superiore, PHP 8.1 o superiore, su un sito dedicato (per esempio `academy.[dominio]`).
- Un tema dell'academy (repository separato): la grafica non sta nel plugin.
- Per la vendita dei singoli corsi: WooCommerce attivo.
- Per l'abbonamento: un account Stripe del cliente (chiavi, prezzo mensile, portale clienti, webhook).

## 2. Installazione

1. Genera lo zip: `powershell -ExecutionPolicy Bypass -File bin\build-zip.ps1` (crea `dist/academy-elearning-<versione>.zip`).
2. Dal File Manager di WePanel carica lo zip in `wp-content/plugins/` e scompattalo (la cartella si chiama `academy-elearning`).
3. In WordPress, **Plugin → attiva "Academy E-learning"**. L'utente che attiva diventa l'account dell'agenzia
   (capacità `ael_manage_modules`).
4. Le pagine **Catalogo**, **Accedi** e **I miei corsi** (e **Abbonati** se serve) si creano da sole. Aggiungile al
   menu del sito dal tema.

**Aggiornamenti:** si carica il nuovo zip nello stesso modo, sopra la cartella esistente. Le migrazioni dei dati
partono da sole al primo caricamento. Nessun dato si cancella.

**Chi è "l'agenzia":** chi ha la capacità `ael_manage_modules`. Il cliente riceve il ruolo Amministratore, che ha
`ael_manage_academy` (contenuti, studenti, vendite) ma NON vede la pagina Configurazione né la pagina Abbonamento.
Per recuperare l'accesso dell'agenzia si può aggiungere in `wp-config.php`:
`define( 'AEL_AGENCY_USER_IDS', array( 1 ) );`

## 3. Configurazione (solo agenzia): Academy → Configurazione

### Moduli (ognuno nasce spento)

| Modulo | Cosa fa | Richiede |
|---|---|---|
| Acquisto singolo | Vende ogni corso con WooCommerce, account obbligatorio e consenso al recesso | WooCommerce |
| Abbonamento | Abbonamento mensile su Stripe: accesso a tutto il catalogo | Account Stripe |
| Ricavi docente | Registro vendite e quote docenti sul netto, CSV | (nessuno) |
| Pannello docente | Il docente vede le sue vendite in sola lettura | Ricavi docente |
| Attribuzione | UTM, provenienza e "Come ci hai conosciuto?" per ogni acquisto | (nessuno) |
| Magic link | Accesso con link via email (mai al posto della password) | (nessuno) |
| Docenti autori | I docenti creano i propri corsi, senza vedere quelli altrui | (nessuno) |

### Opzioni del core

- **Pagine docente**: ogni docente ha una pagina pubblica con i suoi corsi.
- **Schede docente personalizzabili**: frase, copertina, colore e link per ogni docente; il corso mostra il riquadro "Il docente".
- **Anteprime gratuite**: le lezioni segnate come anteprima si vedono senza acquisto.
- **Sorgenti video**: YouTube e/o Vimeo.
- **Cancella tutti i dati alla disinstallazione**: lasciare spento salvo richiesta esplicita.

Il cliente non vede mai questa pagina: la configurazione è decisa prima della consegna.

## 4. Pacchetti tipici

- **Solo corsi a pagamento (acquisto singolo):** Acquisto singolo + Ricavi docente (+ Pannello docente).
- **Solo abbonamento:** Abbonamento.
- **Entrambi:** Acquisto singolo + Abbonamento. La scheda del corso mostra "Acquista a …" e "Abbonati a … e accedi a tutto".
  I corsi con "Escludi dall'abbonamento" si comprano solo a parte.
- **Più docenti che caricano da soli:** aggiungere Docenti autori (il titolare pubblica).
- **Marketing:** aggiungere Attribuzione.

## 5. WooCommerce (acquisto singolo)

1. Valuta, paese e IVA come da cliente (WooCommerce → Impostazioni). I prezzi si mostrano con l'IVA se
   "Visualizzazione prezzi" è impostata su "inclusa".
2. Uscire dalla modalità "in arrivo" di WooCommerce.
3. Attivare un metodo di pagamento reale (carte, PayPal...). Il bonifico serve solo per le prove.
4. Il modulo porta Carrello e Pagamento al checkout "classico" (shortcode) alla prima attivazione e salva il
   contenuto precedente nell'opzione `ael_sp_pages_backup`.
5. Il prezzo di ogni corso si scrive nel riquadro "Vendita del corso" della scheda corso. Il prodotto WooCommerce
   collegato si crea e si aggiorna da solo.
6. La commissione del gateway non è nota al plugin: vale 0 e si corregge dal registro vendite (o si fornisce con il
   filtro `ael_wc_order_fee`).

## 6. Stripe (abbonamento)

In Stripe (modalità test in sviluppo, live in produzione):

1. **Catalogo prodotti**: prodotto "Abbonamento mensile" con prezzo ricorrente mensile. Copiare l'ID del prezzo (`price_...`).
2. **Impostazioni → Billing → Portale clienti**: attivarlo (annullamento a fine periodo, aggiornamento carta, fatture).
3. **Sviluppatori → Webhook**: aggiungere l'endpoint `https://[dominio]/wp-json/ael/v1/stripe-webhook` con gli eventi
   `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.created`,
   `customer.subscription.updated`, `customer.subscription.deleted`, `charge.refunded`. Copiare il segreto (`whsec_...`).
4. **Chiavi API**: la chiave segreta (`sk_...`).

In WordPress, **Academy → Abbonamento**: incollare chiave segreta, segreto del webhook e ID del prezzo, poi
"Verifica il collegamento". Le chiavi non si mostrano mai per intero e non si scrivono mai nel codice.

- Una chiave **live** non viene usata su un sito di sviluppo (localhost, .local, .test): è una protezione voluta.
- In sviluppo, su un sito locale, Stripe non raggiunge il webhook: si usa la Stripe CLI
  (`stripe listen --events <elenco> --forward-to <indirizzo del webhook>`) e il segreto che stampa.

## 7. Cookie e consenso (Attribuzione)

Il cookie di provenienza si salva **solo con il consenso**. Di base il consenso è negato: va collegato al banner
cookie del sito con il filtro `ael_has_tracking_consent` (nel tema o in un piccolo plugin del sito):

```php
add_filter( 'ael_has_tracking_consent', function () {
    return /* il banner ha registrato il consenso agli analytics? */ true;
} );
```

Senza banner (e solo se la normativa lo permette per quel sito) si può usare `__return_true`.

## 8. Tema dell'academy

Il plugin porta solo uno stile neutro con variabili CSS (`--ael-accent`, `--ael-border`, ...). Il tema:

- ridefinisce le variabili CSS (colori, raggi, spazi);
- può sostituire ogni template copiando il file da `templates/` (o da `modules/<nome>/templates/`) in
  `academy-elearning/` dentro il tema o il tema figlio: `course.php`, `lesson.php`, `catalog.php`,
  `course-card.php`, `teacher.php`, `teacher-box.php`, `program.php`, `actions.php`, `login.php`, `my-courses.php`,
  `subscribe.php`, `subscription-box.php`...

I template dei video e dei materiali ricevono già i dati protetti: non costruire mai il video a mano nel tema.

## 9. Sicurezza: cosa è già garantito

- Il video e i materiali si inseriscono nella pagina **solo** dopo il controllo di `AEL_Access`: chi non ha accesso
  non trova l'ID del video nel sorgente, né nelle risposte REST.
- Ogni azione dell'amministrazione ha controllo dei permessi e nonce; ogni input è sanificato e ogni output ha escape.
- Il webhook di Stripe rifiuta tutto ciò che non ha una firma valida ed è idempotente.
- Chiavi e segreti solo nelle opzioni del sito, mai nel codice o nei log.

## 10. Disinstallazione

Se in Configurazione è spuntato "Cancella tutti i dati", all'eliminazione del plugin si rimuovono tabelle, corsi,
moduli, lezioni, docenti, raccolte, categorie, pagine create dal plugin, prodotti WooCommerce collegati ai corsi,
meta utente e opzioni (comprese le chiavi di Stripe). Altrimenti nulla viene toccato.

## 11. Per chi sviluppa

Regole del progetto in `CLAUDE.md`. Il modulo `esempio-di-prova` e la pagina "Prova accessi" sono solo per lo
sviluppo e non entrano nello zip. `bin/make-pot.php` rigenera `languages/academy-elearning.pot`.
