# Messa online di un'academy: passo dopo passo

Vale per ogni academy (Influencee, poi Specialty e le altre). Ogni academy è un **WordPress separato** su un sottodominio
(per esempio `academy.influencee.it`), con il suo database, i suoi utenti, il suo tema. Il plugin è lo stesso per tutte.
Gli utenti di un'academy non esistono nelle altre né sul sito principale: non c'è un login unico.

Prima di iniziare tieni a portata di mano:

- lo zip del plugin (`dist/academy-elearning-<versione>.zip`, si crea con `bin\build-zip.ps1`);
- la cartella del tema dell'academy (per Influencee: `academy-influencee`, dal repository
  `influencee-dev/influencee-academy-influencer`), compressa in uno zip;
- i dati del cliente: dominio, logo, testi, prezzi dei corsi, dati dell'azienda per il fondo pagina;
- le chiavi del metodo di pagamento reale (si scrivono **solo** nelle impostazioni del plugin di pagamento, mai in chat né nei file).

## 1. Sottodominio e WordPress

1. Da WePanel crea il sottodominio (es. `academy.influencee.it`) e fai puntare il dominio alla nuova cartella.
2. Installa WordPress pulito sul sottodominio (lingua italiano).
3. Attiva il certificato HTTPS e controlla che il sito si apra con il lucchetto.
4. Imposta **Impostazioni → Permalink → Nome articolo**.
5. Crea il tuo utente amministratore personale (il tuo nome, la tua email) e usa quello da qui in avanti. Chi attiva il plugin
   diventa "l'agenzia" (vede la pagina Configurazione): quindi **attiva il plugin con il tuo utente**, non con quello del cliente.

## 2. Plugin e tema

1. **Plugin → Aggiungi nuovo → Carica plugin**: scegli lo zip del plugin e attivalo. Le pagine Catalogo, Accedi e I miei corsi si
   creano da sole.
2. **Aspetto → Temi**: installa Twenty Twenty-Five (se non c'è già), poi carica lo zip del tema dell'academy e **attivalo**.
3. Controlla che nel menu a sinistra compaia **Academy**.

## 3. WooCommerce (acquisto dei corsi singoli)

1. Installa e attiva WooCommerce. Nella procedura guidata scegli: negozio in Italia, euro, prodotti digitali.
2. **WooCommerce → Impostazioni → Fiscalità**: IVA 22% inclusa nei prezzi (come in prova).
3. Disattiva la modalità "in arrivo": **WooCommerce → Impostazioni → Sito → Modalità "coming soon"** su "No", altrimenti il pagamento
   non si apre.
4. **WooCommerce → Impostazioni → Pagamenti**: attiva il metodo reale (carta con Stripe, PayPal o altro). Inserisci le chiavi del
   cliente, **in modalità live**, solo nelle impostazioni di quel metodo di pagamento. Togli il bonifico di prova.
5. Fai un acquisto vero di prova con un importo basso (e rimborsalo) per vedere che l'accesso al corso arrivi.

## 4. Configurazione dell'academy (solo agenzia)

Da **Academy → Configurazione**:

1. Accendi solo i moduli del pacchetto del cliente. Per Influencee: Acquisto singolo, Quote docenti, Pannello docente, Docenti
   autori, Provenienza, Link via email. **Abbonamento spento.**
2. In "Opzioni del core": **Formato dei contenuti = Verticale (9:16)** per Influencee (corsi in video verticale).
3. Lascia accese le pagine docente e le schede docente personalizzabili.

## 5. Contenuti

1. **Academy → Docenti**: crea i docenti con foto (immagine in evidenza), frase di presentazione, copertina, **canali e numeri**
   (follower ed engagement rate, scritti a mano, con la data di aggiornamento) e, per ognuno, l'utente WordPress collegato.
2. **Academy → Corsi**: titolo, descrizione, copertina (immagine in evidenza), **prezzo** (per Influencee: 60 €), docente, moduli
   e lezioni con il video (YouTube non in elenco o Vimeo; per i video verticali va bene anche uno Short).
3. Se il modulo Quote docenti è acceso: imposta la percentuale di ogni docente e le eventuali eccezioni per corso.
4. Pubblica i corsi e guarda il catalogo da visitatore (finestra in incognito).

## 6. Home e menu

1. Crea una pagina **Home** con il modello "Home Academy Influencee" (nell'inseritore dei modelli) e impostala come pagina
   iniziale: **Impostazioni → Lettura → Una pagina statica**.
2. Il menu in alto è già nel tema (Catalogo, Come funziona, I miei corsi). Controlla che i collegamenti si aprano.

## 7. Cookie, privacy ed email

1. **Banner dei cookie**: collega il consenso del banner del cliente al filtro `ael_has_tracking_consent` (finché non è collegato, la
   provenienza degli acquisti non registra nulla). Togli il file di prova `ael-test-consent.php` da `wp-content/mu-plugins/` se c'è.
2. **Privacy e condizioni**: pagina privacy (da indicare in **Impostazioni → Privacy**, così compare nel modulo di iscrizione), termini di vendita e informativa sul recesso (il pagamento chiede già il consenso
   all'accesso immediato ai contenuti digitali).
3. **Email di amministrazione**: **Impostazioni → Generali → Indirizzo email di amministrazione**, scrivi un indirizzo reale
   dell'academy (per Influencee `info@influencee.it`). Serve davvero: le email di WooCommerce (ordine ricevuto, nuovo account)
   scrivono al cliente "se hai bisogno di aiuto contattaci qui: <quell'indirizzo>", e le notifiche di nuovo ordine arrivano lì.
   Un'installazione nuova ha un indirizzo provvisorio (in prova `dev-email@wpengine.local`): se resta, il cliente vede un indirizzo
   finto nella mail. WordPress chiede di confermare il cambio con un link mandato al nuovo indirizzo: aprilo.
4. **Email di invio**: installa un plugin di invio email (SMTP) con il mittente dell'academy, poi prova un "password dimenticata" e un
   link di accesso via email, e controlla che la mail d'ordine mostri logo Influencee e l'indirizzo giusto. In locale le email
   passano da Mailpit e non partono davvero.

## 8. Accessi: chi può fare cosa

| Chi | Come entra | Cosa vede |
|---|---|---|
| Agenzia (tu) | il tuo utente, creato su **ogni** academy | tutto, compresa Configurazione e i moduli |
| Cliente (titolare) | utente con ruolo Amministratore | contenuti, studenti, vendite; **non** Configurazione |
| Docente | utente collegato alla sua scheda docente | solo i propri corsi e le proprie vendite |
| Studente | si registra dal pagamento o dall'invito | corsi acquistati e I miei corsi |

- Per entrare in un'altra academy vai al suo `/wp-admin` con le tue credenziali: non c'è un pannello unico per tutte.
- Se perdi l'accesso agenzia, aggiungi in `wp-config.php`: `define( 'AEL_AGENCY_USER_IDS', array( 1 ) );` (1 = ID del tuo utente).
- Usa password lunghe e, se puoi, l'accesso a due fattori per gli amministratori.

## 9. Collaudo prima di annunciare

1. Segui `docs/PROVE-MANUALI.md` su questo sito (acquisto, accesso al corso, ripresa del video, progressi, rimborso, docente).
2. Prova da telefono: menu, catalogo, lezione in verticale, pagamento.
3. Fai una copia di sicurezza (file e database) prima di aprire al pubblico.

## 10. Dopo la messa online

- **Aggiornare il plugin**: carica il nuovo zip sopra la cartella esistente. Le migrazioni partono da sole e nessun dato si cancella.
- **Aggiornare il tema**: stessa cosa con lo zip del tema.
- Tieni WordPress, WooCommerce e gli altri plugin aggiornati, con una copia di sicurezza prima di ogni aggiornamento.
