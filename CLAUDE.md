# CLAUDE.md — Academy E-learning

Questo file contiene le regole fisse del progetto. Rileggilo all'inizio di ogni sessione e rispettalo sempre. Se una richiesta va contro una di queste regole, fermati e segnalalo prima di scrivere codice.

## 1. Cos'è il progetto

Un plugin WordPress che trasforma un sito in una piattaforma di corsi online (academy). Si installa su un WordPress dedicato, su un sottodominio del tipo `academy.[dominio]`.

Il plugin è **uno solo** e serve più academy (la nostra e quelle dei clienti, la prima è Specialty). Noi (l'agenzia) lo configuriamo in WordPress prima della consegna, accendendo le funzioni previste dal pacchetto di quel cliente, e lo vestiamo con un tema proprio dell'academy. Il cliente riceve l'academy finita: non decide quali funzioni attivare. Il codice del plugin non contiene mai nulla di specifico di una singola academy.

Documenti di riferimento, nella cartella `docs/`:

- `docs/academy-elearning-flusso.md` — documento master: flusso completo e decisioni confermate. È la fonte di verità funzionale.
- `docs/trascrizione-call.txt` — la call in cui è nato il progetto, per il contesto.

Se qualcosa non è scritto nel documento master, **non inventarlo**: proponi un'opzione e chiedi conferma.

## 2. Decisioni confermate (non rimetterle in discussione)

| Tema | Decisione |
|---|---|
| Struttura | Un solo plugin completo fin dalla prima versione, con interruttori per ogni funzione |
| Chi decide le funzioni | Lo decidiamo noi (agenzia) prima della consegna, da una pagina di WordPress riservata all'agenzia (capacità dedicata `ael_manage_modules`, non concessa al ruolo amministratore del cliente). Il cliente non vede né cambia gli interruttori |
| Acquisto del singolo corso | WooCommerce |
| Abbonamento mensile | Gestito direttamente su Stripe (Checkout + Customer Portal + webhook), senza estensioni WooCommerce |
| Accesso utenti | Utenti WordPress con email e password; accesso con link via email solo come opzione aggiuntiva, con interruttore |
| Video | YouTube non in elenco, incorporato; la sorgente si sceglie per lezione (YouTube, Vimeo, caricamento) per poter cambiare in futuro |
| Quote docenti | Calcolate sull'importo netto (senza IVA e commissioni); il plugin conta, il pagamento ai docenti avviene fuori |
| Venditore | Il titolare di ciascuna academy |
| Grafica | In un tema separato per ogni academy, in un repository a parte |
| Rilascio | File zip caricato dal File Manager di WePanel in `wp-content/plugins/` e attivato dall'amministrazione |

## 3. Le cinque regole per far crescere il plugin

Sono le più importanti del progetto. Valgono per ogni riga di codice.

1. **Ogni funzione ha la sua cartella.** Ogni modulo vive in `modules/<nome-modulo>/` con i suoi file. Nessun modulo modifica i file della base o di un altro modulo.
2. **I moduli si agganciano alla base, non la modificano.** La base espone hook (`do_action` / `apply_filters`) nei punti chiave; i moduli li usano. Se a un modulo serve un aggancio che non c'è, si aggiunge l'hook alla base, non la logica del modulo.
3. **Ogni modulo ha il suo interruttore.** Un modulo spento non carica nulla (niente hook, niente asset, niente voci di menu). Un modulo nuovo arriva sempre spento. Gli interruttori li gestisce solo l'agenzia, con la capacità `ael_manage_modules`: la pagina di configurazione e le sue voci di menu non compaiono a chi non ce l'ha. Le opzioni del core (pagine docente, anteprime gratuite, sorgenti video) seguono la stessa regola.
4. **I dati si aggiungono, non si cancellano.** Le migrazioni creano tabelle e colonne nuove; non eliminano, non rinominano e non cambiano il tipo di quelle esistenti. Spegnere un modulo non cancella i suoi dati. La disinstallazione cancella i dati solo se l'amministratore lo sceglie esplicitamente nelle impostazioni.
5. **Ogni modifica passa da una prova.** Si lavora su un ramo dedicato, si prova sul WordPress locale, si aggiorna la versione e il changelog, poi si unisce al ramo principale.

## 4. Struttura del repository

```
academy-elearning/
├── academy-elearning.php        ← bootstrap: header, costanti, caricamento
├── uninstall.php                ← rispetta l'impostazione "cancella i dati"
├── includes/                    ← la base (core)
│   ├── class-plugin.php         ← avvio, caricamento moduli attivi
│   ├── class-modules.php        ← registro moduli e interruttori
│   ├── class-installer.php      ← tabelle e migrazioni versionate
│   ├── class-access.php         ← UNICA logica che decide chi vede cosa
│   ├── class-content.php        ← CPT e tassonomie: corsi, lezioni, docenti
│   ├── class-catalog.php        ← catalogo, scheda corso, pulsante di azione
│   ├── class-teacher-profile.php ← scheda pubblica personalizzabile del docente
│   ├── class-payments.php       ← contratto dei pagamenti (eventi per i moduli)
│   ├── class-stats.php          ← numeri per studenti e panoramica
│   ├── class-progress.php       ← progressi e tempo di visione
│   ├── class-pages.php          ← pagine di sistema (Catalogo, Accedi, I miei corsi)
│   ├── class-lesson-view.php    ← pagina lezione, video protetto, download materiali
│   ├── class-video.php          ← codice di incorporamento YouTube/Vimeo
│   ├── class-templates.php      ← caricamento template (tema prima del plugin)
│   ├── class-account.php        ← area "I miei corsi", progressi
│   ├── class-settings.php       ← impostazioni del cliente; pagina interruttori riservata all'agenzia
│   └── class-rest.php           ← endpoint REST del core
├── modules/
│   ├── single-purchase/         ← acquisto singolo con WooCommerce
│   ├── subscription/            ← abbonamento con Stripe
│   ├── teacher-revenue/         ← quote docenti e registro vendite
│   ├── teacher-panel/           ← pannello docente in sola lettura
│   ├── attribution/             ← provenienza degli acquisti
│   ├── magic-link/              ← accesso con link via email (opzionale)
│   └── teacher-authoring/       ← i docenti creano i propri corsi, senza vedere quelli altrui
├── admin/                       ← pagine di amministrazione del core
├── templates/                   ← template sovrascrivibili dal tema
├── assets/
├── languages/
├── docs/
├── CHANGELOG.md
└── CLAUDE.md
```

Ogni modulo ha un file principale `modules/<nome>/module.php` che dichiara nome, descrizione, versione, interruttore predefinito (spento) e dipendenze.

## 5. Convenzioni di codice

- PHP 8.1 o superiore, WordPress 6.4 o superiore. Seguire i WordPress Coding Standards.
- Prefisso per tutto: funzioni, classi, option, meta, hook, tabelle. Usare `ael_` / `AEL_` (tabelle: `{$wpdb->prefix}ael_...`).
- Text domain `academy-elearning`. Tutti i testi visibili passano da `__()` / `esc_html__()`; lingua di base italiano.
- Template in `templates/` caricati con una funzione che controlla prima il tema attivo (`academy-elearning/` nella cartella del tema), così ogni academy personalizza la grafica senza toccare il plugin.
- Niente CSS o colori specifici di un'academy nel plugin: solo stili di base neutri e variabili CSS che il tema può sovrascrivere.
- Dipendenze esterne ridotte al minimo. Per Stripe preferire chiamate dirette all'API con `wp_remote_post` / `wp_remote_get` invece di includere l'SDK, salvo motivi concreti da proporre prima.

## 6. Sicurezza (obbligatoria)

- Nessuna chiave, password o token nel codice, nel repository o nei log. Le chiavi Stripe si inseriscono nelle impostazioni del plugin dall'amministrazione di WordPress e si salvano come option.
- Ogni azione da amministrazione: controllo dei permessi (`current_user_can`) e nonce.
- Ogni input sanificato, ogni output con escape. Query sempre con `$wpdb->prepare`.
- Endpoint REST sempre con `permission_callback` reale (mai `__return_true` su dati privati).
- Webhook Stripe: verificare sempre la firma (`Stripe-Signature`) con il segreto del webhook, rifiutare tutto ciò che non è verificato, gestire gli eventi in modo idempotente (lo stesso evento ricevuto due volte non produce effetti doppi).
- Il codice di incorporamento di un video viene inserito nella pagina **solo** dopo che `AEL_Access` ha confermato l'accesso. Chi non ha accesso non deve trovare l'ID del video nemmeno nel codice sorgente o nelle risposte REST.

## 7. Il cuore: la logica di accesso

Tutta la decisione "questo utente può vedere questa lezione?" sta in `includes/class-access.php`. Nessun altro file prende questa decisione da solo.

- L'accesso a un corso deriva da una o più "concessioni" salvate in una tabella dedicata, con: utente, corso (o "tutto il catalogo"), origine (`purchase`, `subscription`, `manual`), data di inizio, data di fine eventuale, stato.
- Il modulo acquisto singolo crea concessioni a vita quando un ordine WooCommerce passa a completato, e le revoca in caso di rimborso.
- Il modulo abbonamento crea o aggiorna una concessione sul catalogo in base agli eventi Stripe; alla disdetta la concessione resta valida fino alla fine del periodo pagato.
- Un corso può essere escluso dall'abbonamento: in quel caso la concessione `subscription` non lo copre.
- Le anteprime gratuite sono visibili a tutti, se l'interruttore è acceso.
- La base espone filtri (per esempio `ael_user_can_access_lesson`) così i moduli futuri possono aggiungere regole senza modificare la classe.

## 8. Specifiche dei moduli della prima versione

**single-purchase (WooCommerce).** Ogni corso in vendita è collegato a un prodotto WooCommerce virtuale. Hook su ordine completato e rimborsato. Checkout con creazione account obbligatoria (email e password) e casella di consenso all'accesso immediato ai contenuti digitali (rinuncia al recesso), salvata con l'ordine.

**subscription (Stripe).** Stripe Checkout in modalità subscription per l'iscrizione, Customer Portal per disdetta e cambio carta, webhook almeno per: `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`. Collegamento utente WordPress ↔ customer Stripe salvato nei meta utente. Stessa casella di consenso al recesso prima del pagamento.

**teacher-revenue.** Registro vendite in tabella dedicata: data, ordine, corso, docente, importo lordo, IVA, commissione, netto, percentuale applicata, quota, stato (`da_liquidare` / `liquidata` / `stornata`). Percentuale per docente con eccezione per corso. Storno automatico sui rimborsi. Riepilogo per docente e periodo, esportazione CSV. Nessun pagamento automatico.

**teacher-panel.** Il docente vede solo le proprie vendite e quote, in sola lettura.

**attribution.** Al primo arrivo salva UTM e referrer in un cookie con durata 90 giorni (primo e ultimo contatto). Al pagamento, sia WooCommerce sia Stripe, registra con l'acquisto: UTM primo e ultimo contatto, codice promozionale usato, risposta al campo obbligatorio "Come ci hai conosciuto?". Dati esportabili in CSV. Rispettare il consenso cookie dell'academy.

**magic-link.** Link di accesso monouso con scadenza breve, richiedibile dalla pagina di accesso. Mai sostitutivo della password.

**Funzioni del core** (sempre attive): corsi, moduli, lezioni con sorgente video per lezione, docenti con pagina pubblica, raccolte, catalogo con filtri, scheda corso con pulsante che cambia in base agli interruttori e allo stato dell'utente, area "I miei corsi" con progressi e ripresa dall'ultima lezione, registrazione dei minuti visti per lezione, cruscotto vendite e abbonamenti per l'amministratore.

## 9. Moduli futuri (non svilupparli ora)

Quiz, webinar, community/chat, chatbot sui contenuti, attestati, codici sconto, link referral per docente. Quando arriverà il momento, ognuno sarà un modulo in `modules/` secondo le regole della sezione 3. Nella prima versione prevedi gli hook che serviranno (per esempio `ael_lesson_completed`, `ael_course_completed`, `ael_after_lesson_content`), senza implementare i moduli.

## 10. Come lavorare in ogni sessione

1. Rileggi questo file e la parte del documento master che riguarda il lavoro richiesto.
2. Prima di scrivere codice, proponi un piano breve: file che toccherai, tabelle o hook nuovi, cosa resta invariato. Aspetta conferma per le modifiche che toccano il core, le tabelle o i pagamenti.
3. Lavora su un ramo dedicato (`feature/<nome>`), mai direttamente su `main`.
4. Prova sul WordPress locale (LocalWP su Windows) con WooCommerce e Stripe in modalità test. Mai chiavi live in sviluppo.
5. A fine lavoro: aggiorna la versione (semantic versioning), scrivi il `CHANGELOG.md`, spiega in parole semplici cosa è cambiato e come provarlo.
6. Per il rilascio prepara lo zip della cartella del plugin (senza `docs/`, file di sviluppo e `.git`), pronto per il caricamento da WePanel.

## 11. Cosa non fare mai

- Inserire nel plugin nomi, colori, testi o logiche di una specifica academy.
- Duplicare il plugin per adattarlo a un'academy.
- Cancellare o modificare tabelle e colonne esistenti in una migrazione.
- Mettere chiavi o segreti nel codice, nei commenti, nei test o nei log.
- Mostrare l'ID o il link di un video a chi non ha accesso.
- Usare estensioni a pagamento (per esempio WooCommerce Subscriptions) senza una decisione esplicita.
- Cambiare una decisione della sezione 2 senza conferma.
