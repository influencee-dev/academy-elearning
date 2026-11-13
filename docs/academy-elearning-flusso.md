# Academy e-learning — il flusso completo della piattaforma

Documento di progetto · novembre 2026 · base comune per tutte le academy che installeremo

## In sintesi

Costruiamo un solo plugin WordPress che trasforma un sito in una piattaforma di corsi online, installata su un sottodominio del tipo academy.[dominio]. Lo sviluppiamo una volta su Claude Code e lo installiamo dove serve: cambiano la grafica e le impostazioni, il motore resta lo stesso.

Il plugin fa quattro cose: gestisce i contenuti (docenti, corsi, moduli, lezioni video), li mostra in un catalogo pubblico, gestisce l’accesso a pagamento (acquisto del singolo corso e abbonamento mensile) e calcola in automatico la quota che spetta a ogni docente sulle vendite dei suoi corsi.

La prima versione nasce completa di tutte le funzioni già decise, abbonamento compreso. Ogni academy accende solo quelle che le servono attraverso degli interruttori. Le funzioni future (quiz, webinar, community, chatbot) si aggiungeranno come moduli separati, senza toccare ciò che è già online. Per i pagamenti ci appoggiamo a WooCommerce per l’acquisto dei singoli corsi e direttamente a Stripe per l’abbonamento.

## 1. Il principio: un motore, tante academy

L’idea nasce da un’esigenza ricorrente: più realtà (la nostra academy e quelle dei clienti) hanno bisogno della stessa cosa, cioè un posto dove caricare corsi, venderli e farli seguire. Invece di costruire un sito ogni volta, costruiamo un prodotto che si installa e si configura.

Ogni installazione decide dalle impostazioni, senza toccare il codice, quali funzioni accendere, chi sono i docenti e con quale percentuale, e che aspetto ha la piattaforma. Quando correggiamo un errore o aggiungiamo una funzione, la novità arriva a tutte le academy con un semplice aggiornamento.

## 2. Architettura tecnica

**Sottodominio con WordPress dedicato.** Ogni academy vive su un’installazione WordPress separata dal sito principale. Così il sito istituzionale resta leggero, gli aggiornamenti non si intralciano e un problema su uno non tocca l’altro.

**Plugin per la logica, tema per la grafica.** Tutto ciò che riguarda contenuti, accessi, pagamenti e quote sta nel plugin. La veste grafica sta in un tema leggero (o child theme) personalizzato per ogni academy. Aggiornare il plugin non cambia mai l’aspetto di nessuno.

**Acquisto singolo con WooCommerce.** È la scelta consigliata rispetto a un e-commerce costruito da zero: offre già i collegamenti con Stripe e PayPal, la gestione di ordini, rimborsi ed email di conferma, ed è compatibile con i plugin italiani di fatturazione elettronica. Costruirlo da zero vorrebbe dire rifare tutto questo e prenderci in carico la sicurezza dei pagamenti. Il nostro plugin usa WooCommerce come cassa e si occupa di tutto il resto: chi ha comprato cosa, cosa si sblocca, a chi va la quota.

**Abbonamento gestito direttamente su Stripe.** Per l’abbonamento non usiamo estensioni di WooCommerce: lo gestisce Stripe, che è nato per i pagamenti ricorrenti. Il flusso è questo: l’utente sceglie l’abbonamento e paga su una pagina di pagamento di Stripe; Stripe addebita ogni mese in automatico e avvisa il nostro plugin di ogni evento (pagamento riuscito, rinnovo, pagamento non andato a buon fine, disdetta); il plugin apre o chiude l’accesso al catalogo in base a questi avvisi. Per disdire o cambiare carta l’utente usa il portale clienti di Stripe, raggiungibile dalla sua area account, così non dobbiamo costruire quella parte.

I vantaggi sono nessun costo di licenza aggiuntivo, rinnovi e tentativi di riaddebito gestiti da Stripe e una dipendenza in meno da WooCommerce. Ogni academy collega il proprio account Stripe dalle impostazioni del plugin: le chiavi si inseriscono lì, direttamente nell’amministrazione di WordPress, e non stanno mai nel codice né nel repository. L’unico punto da preparare è la fatturazione dei rinnovi, perché questi pagamenti non passano da WooCommerce (vedi decisioni confermate).

**Video.** Il plugin accetta tre sorgenti, e conviene usarle con criterio:

| Sorgente | Punto di forza | A cosa fare attenzione |
|---|---|---|
| Caricamento diretto su WordPress | Tutto resta in casa | Appesantisce l’hosting, lo streaming può essere lento e il file è facile da scaricare |
| YouTube (video non in elenco) | Gratuito e veloce | Chiunque abbia il link lo vede: non protegge un corso a pagamento |
| Vimeo o servizio di streaming dedicato | Video visibili solo dal dominio dell’academy, player pulito | Ha un costo mensile |

**La scelta per partire: YouTube non in elenco, incorporato nelle lezioni.** È il metodo già usato per l’academy di Socialee: gratuito, veloce e già conosciuto dal team. I video vanno caricati come «non in elenco», perché quelli privati non si possono mostrare su un sito esterno.

Per proteggere i corsi a pagamento il plugin inserisce il video nella pagina solo dopo aver verificato che l’utente abbia comprato il corso o sia abbonato: chi non ha pagato non trova il video nemmeno guardando il codice della pagina. Il player viene impostato per mostrare a fine video solo suggerimenti dello stesso canale. Resta un limite da conoscere: chi ha pagato può copiare il link e girarlo ad altri. Su corsi di prezzo contenuto è un rischio accettabile.

Prima del lancio vanno lette con attenzione le regole di YouTube sull’uso del player incorporato in servizi a pagamento, per non avere sorprese.

**Il passaggio successivo, quando servirà.** La sorgente si sceglie lezione per lezione, quindi passare a Vimeo (o a un servizio simile, che limita la visione al solo dominio dell’academy) vuol dire cambiare un link, senza toccare il resto. Il momento giusto è quando i corsi a pagamento cominciano a generare incassi importanti o quando un’academy vende contenuti di valore alto.

**Sviluppo con Claude Code.** Il plugin si sviluppa interamente su Claude Code, in un unico repository GitHub con le versioni numerate. Nella cartella del progetto c’è un file CLAUDE.md con le regole fisse che Claude Code rilegge a ogni sessione: struttura del plugin, convenzioni di codice, regole per far crescere il plugin a moduli (sezione 13) e soprattutto il principio che nessuna personalizzazione di una singola academy entra nel codice del plugin. Lo sviluppo si prova su un WordPress locale di test con WooCommerce e i pagamenti in modalità prova, prima di toccare qualsiasi sito vero.

**Rilascio.** Ogni versione pronta diventa un file zip che si installa con il metodo già collaudato: caricamento dal File Manager di WePanel, estrazione in wp-content/plugins e attivazione dall’amministrazione di WordPress. Lo stesso zip va su tutte le academy.

## 3. Chi usa la piattaforma

| Ruolo | Cosa fa |
|---|---|
| Amministratore | Carica e organizza i corsi, gestisce docenti, prezzi, percentuali, interruttori e impostazioni, consulta vendite e quote |
| Docente | È l’autore dei corsi (un esperto o un creator). Può avere una pagina pubblica con tutti i suoi corsi e, se lo attiviamo, un pannello in sola lettura con le sue vendite e la sua quota |
| Studente | Si registra, acquista un corso o si abbona, segue le lezioni e ritrova i suoi corsi nell’area personale |

## 4. Come sono organizzati i contenuti

La struttura è a quattro livelli: docente, corso, modulo, lezione. Un docente ha più corsi, un corso è diviso in moduli, ogni modulo contiene le lezioni.

Ogni **corso** ha titolo, sottotitolo, docente, immagine di copertina, descrizione, cosa si impara, a chi è rivolto, livello, categoria, durata complessiva e prezzo. Ogni **lezione** ha titolo, video (da caricamento, link YouTube o link Vimeo), un testo di accompagnamento, eventuali materiali da scaricare e un’opzione per renderla anteprima gratuita.

Oltre all’ordine naturale corso per corso, l’amministratore può creare **raccolte** a mano: per esempio la raccolta con tutti i corsi di un certo docente, o una raccolta tematica. L’ordine dei moduli e delle lezioni si cambia trascinando gli elementi.

## 5. Il catalogo pubblico

Il modello di riferimento è Udemy: una vetrina con tutti i corsi, filtrabili per categoria e per docente, come un catalogo di prodotti in cui al posto delle scarpe ci sono i corsi.

La **scheda del corso** mostra titolo, sottotitolo, docente, descrizione, un video di anteprima e il programma completo, con moduli e lezioni visibili ma bloccati finché non si ha accesso. Sotto c’è il pulsante di azione.

Il punto che tiene insieme le due modalità di accesso è proprio questo pulsante: **il catalogo è sempre lo stesso, cambia solo cosa dice il pulsante** in base agli interruttori accesi sull’academy e a chi sta guardando.

| Situazione | Pulsante |
|---|---|
| Solo acquisto singolo acceso, utente senza accesso | Acquista a [prezzo] |
| Solo abbonamento acceso, utente non abbonato | Incluso nell’abbonamento · Abbonati |
| Entrambe le modalità accese | Acquista a [prezzo] oppure Abbonati e accedi a tutto |
| Utente che ha già comprato il corso o è abbonato | Inizia / Continua il corso |

## 6. Le modalità di accesso

| Modalità | Come funziona | Cosa ottiene l’utente |
|---|---|---|
| Acquisto del singolo corso | Pagamento una tantum per un corso | Accesso a vita a quel corso |
| Abbonamento mensile | Pagamento ricorrente, stile Netflix | Accesso a tutto il catalogo finché l’abbonamento è attivo |
| Entrambe | Le due formule convivono | Sceglie l’utente |

Entrambe le modalità sono presenti nel plugin fin dalla prima versione; ogni academy accende quella o quelle che le servono. In più, un singolo corso può essere escluso dall’abbonamento (per esempio un corso premium venduto solo a parte).

## 7. Gli interruttori

Ogni funzione del plugin ha un interruttore nelle impostazioni. Il plugin è uno solo e completo; ogni academy lo modella accendendo e spegnendo.

| Interruttore | Cosa fa quando è acceso |
|---|---|
| Acquisto del singolo corso | I corsi si comprano uno per uno, con il loro prezzo |
| Abbonamento mensile | Un pagamento ricorrente sblocca tutto il catalogo |
| Quote docenti | Ogni vendita calcola la quota del docente autore del corso |
| Pannello docenti | Ogni docente vede in sola lettura le proprie vendite e la propria quota |
| Pagine docente | Ogni docente ha una pagina pubblica con tutti i suoi corsi |
| Anteprime gratuite | Le lezioni segnate come anteprima si vedono senza acquisto |
| Accesso con link via email | In aggiunta alla password, l’utente può entrare anche con un link mandato per email |
| Sorgenti video | Sceglie quali sorgenti si possono usare: caricamento, YouTube, Vimeo |
| Moduli futuri | Quiz, webinar, community, chatbot, attestati: ognuno arriverà con il proprio interruttore, spento di default |

Spegnere un interruttore nasconde la funzione ma non cancella i dati: se un domani la si riaccende, tutto è ancora lì.

## 8. Il percorso dell’utente, passo per passo

| Passo | Cosa succede | Cosa fa il sistema |
|---|---|---|
| Scoperta | L’utente arriva dai social, dal link di un docente o dal sito | Mostra il catalogo o direttamente la scheda del corso |
| Scelta | Guarda l’anteprima e il programma, decide se comprare il corso o abbonarsi | Mostra il pulsante giusto per la sua situazione |
| Account | Crea l’account dentro lo stesso passaggio del pagamento | Registra email e password; nessun modulo separato |
| Pagamento | Paga con carta o PayPal | WooCommerce incassa e conferma; registra la vendita e la quota del docente |
| Sblocco | Riceve l’email di conferma | Sblocca il corso (o tutto il catalogo) e porta l’utente alla pagina del corso |
| Fruizione | Segue le lezioni dall’area «I miei corsi» | Salva i progressi e riparte dall’ultima lezione vista |
| Completamento | Finisce il corso | Segna il corso come completato (attestato con un modulo futuro) |

**Perché l’account serve sempre**, anche per chi compra un solo corso: senza un accesso personale l’utente non potrebbe tornare a vedere ciò che ha pagato. L’account si crea nel checkout, nello stesso passaggio del pagamento, con email e password scelta dall’utente. Da quel momento si entra dalla pagina di accesso dell’academy, come su qualsiasi piattaforma.

Chi dimentica la password la reimposta da solo con il classico «password dimenticata», che manda un link nuovo ogni volta che lo si chiede. Come comodità in più, ogni academy può accendere anche l’accesso con un link mandato per email: è un’alternativa alla password, non un sostituto, e anche in questo caso il link si richiede di nuovo quando serve, quindi perdere una vecchia email non blocca mai l’accesso.

**Per l’abbonamento** il rinnovo è automatico ogni mese. Se l’utente disdice o il pagamento non va a buon fine, l’accesso resta attivo fino alla fine del mese già pagato e poi si chiude. La disdetta si fa in autonomia dall’area account, che porta al portale clienti di Stripe.

## 9. Le quote dei docenti

Quando un corso viene venduto, una parte dell’incasso spetta al docente che l’ha creato (indicativamente tra il 40 e il 50%). Il plugin si occupa del conteggio; il pagamento al docente lo facciamo noi, fuori dalla piattaforma.

La percentuale si imposta per docente, con la possibilità di un’eccezione su un singolo corso. Ogni vendita viene registrata con data, corso, docente, importo e quota calcolata. Il consiglio è calcolare la quota sull’importo netto, cioè senza IVA e senza le commissioni del sistema di pagamento, così si divide ciò che entra davvero; va scritto chiaro nel contratto con il docente. Se un acquisto viene rimborsato, la quota viene stornata in automatico.

L’amministratore vede un riepilogo per docente e per periodo, lo esporta in CSV e segna a mano le quote come «da liquidare» o «liquidate». Il docente, se il suo pannello è acceso, vede solo le proprie vendite.

Sull’abbonamento oggi non è prevista nessuna ripartizione. Se in futuro dentro un abbonamento entrassero docenti esterni, la soluzione più equa è dividere in base al tempo di visione dei loro corsi: per questo la prima versione registra già i minuti visti, costa poco e lascia la strada aperta.

## 10. Il pannello di amministrazione

Il plugin aggiunge a WordPress una sezione con cinque aree. **Corsi**: creazione, moduli, lezioni, prezzi, raccolte. **Docenti**: anagrafica, pagina pubblica, percentuale. **Studenti**: elenco iscritti, corsi acquistati, stato dell’abbonamento, progressi. **Vendite e quote**: registro vendite, riepilogo per docente, esportazione. **Impostazioni**: interruttori, percentuale predefinita, testi ed email automatiche.

## 11. Personalizzazione per ogni academy

La parte visiva si affronta dopo il motore. Per ogni installazione si definiscono logo, colori, caratteri, testi del catalogo, email e dominio, attraverso le impostazioni e il tema dedicato. Il codice del plugin non si modifica mai per una singola academy: è questo che ci permette di aggiornarle tutte insieme.

## 12. Aspetti legali e amministrativi da preparare

Servono termini e condizioni di vendita, informativa privacy e gestione dei cookie. Per i contenuti digitali il diritto di recesso di 14 giorni decade solo se l’utente chiede espressamente di accedere subito e dichiara di saperlo: va previsto un consenso dedicato nel checkout.

Per ogni academy va chiarito chi è il venditore e chi emette fattura o ricevuta (di norma il titolare dell’academy). I corsi online sono servizi elettronici e la vendita a privati di altri Paesi UE ha regole IVA proprie: è un punto da verificare con il commercialista prima del lancio.

Con ogni docente serve un accordo scritto su percentuale, base di calcolo, tempi di liquidazione, diritti d’uso dei video ed eventuale esclusiva.

## 13. Come cresce il plugin

Le funzioni nuove si aggiungono senza toccare ciò che è già online. Il plugin è costruito a moduli, come una presa multipla: la base resta fissa e ogni nuova funzione si collega come una spina in più. Queste regole vanno scritte nel CLAUDE.md, così Claude Code le rispetta in ogni sessione.

**Ogni funzione ha la sua cartella.** Un modulo nuovo, per esempio il quiz, vive nei suoi file. Il resto del plugin non dipende da lui: aggiungerlo vuol dire creare una cartella nuova, non riscrivere quelle vecchie.

**I moduli si agganciano alla base senza modificarla.** La base offre dei punti di aggancio (in WordPress si chiamano hook), come «fine lezione» o «corso completato». Il quiz si aggancia a «fine lezione» e mostra le sue domande; la lezione non viene toccata. Se il quiz si spegne, tutto torna com’era.

**Ogni modulo ha il suo interruttore.** La funzione nuova arriva spenta e si accende solo sulle academy dove serve.

**I dati si aggiungono, non si cancellano.** Quando un modulo ha bisogno di nuove informazioni, per esempio le risposte ai quiz, crea tabelle o campi nuovi senza modificare o eliminare quelli esistenti. Corsi, acquisti, abbonamenti e quote restano intatti a ogni aggiornamento.

**Ogni aggiornamento passa da una prova.** La funzione si sviluppa su un ramo separato del repository, si prova sul WordPress di test, si fa un backup del sito vero e solo allora si carica la nuova versione numerata. Se qualcosa non va, si torna alla versione precedente in pochi minuti.

Le evoluzioni già emerse da sviluppare così sono quiz e verifiche, webinar dal vivo, chat o community tra iscritti, un chatbot che risponde sui contenuti dei corsi e attestati di completamento. Utili anche codici sconto e un link personale per ogni docente, per sapere quante vendite porta con la propria promozione.

## 14. Piano di lavoro

| Fase | Contenuto | Risultato |
|---|---|---|
| 1 · Prima versione completa | Contenuti, catalogo, account, acquisto del singolo corso con WooCommerce, abbonamento con Stripe, quote docenti, area «I miei corsi», interruttori, registrazione del tempo di visione | Il plugin fa tutto ciò che è già deciso |
| 2 · Installazione | Per ogni academy: interruttori, grafica dedicata, caricamento corsi, test dei pagamenti in modalità prova, messa online | Academy pronta per ciascun sito |
| 3 · Nuovi moduli | Quiz, webinar, community, chatbot, attestati, uno alla volta secondo le regole della sezione 13 | La piattaforma cresce senza fermarsi |

**Come lavoriamo.** Il motore nasce su Claude Code, nel repository GitHub «e-learning», che diventa il punto di riferimento per tutto il team: chiunque lavori sul progetto parte dallo stesso codice e dallo stesso CLAUDE.md, e lo storico delle modifiche resta visibile a tutti. Ogni nuovo modulo si sviluppa su un ramo dedicato che, una volta provato, confluisce nel ramo principale.

La grafica e la configurazione di ogni academy stanno in un repository separato e leggero, con il tema dedicato e le impostazioni: il plugin resta uno solo, e non si duplica mai per adattarlo a un’academy.

La trascrizione della call e questo documento vanno nella cartella del progetto, così Claude Code ha subito il contesto completo dalla prima sessione.

## 15. Decisioni confermate

Queste sono le scelte già prese e valide per tutte le academy.

| Tema | Decisione |
|---|---|
| Struttura del plugin | Un solo plugin, completo fin dalla prima versione, con interruttori per accendere le funzioni in ogni academy |
| Sviluppo | Su Claude Code, in un unico repository GitHub con CLAUDE.md |
| Acquisto del singolo corso | WooCommerce |
| Abbonamento | Gestito direttamente su Stripe |
| Accesso degli utenti | Email e password; il link via email è un’opzione aggiuntiva |
| Video | YouTube non in elenco, incorporato nelle lezioni; passaggio a Vimeo o simile quando gli incassi lo giustificano |
| Base di calcolo delle quote dei docenti | Importo netto, senza IVA e commissioni |
| Chi vende ed emette fattura | Il titolare di ciascuna academy |
| Fatturazione dei rinnovi dell’abbonamento | Collegamento tra Stripe e il sistema di fatturazione elettronica di ciascuna academy, con verifica del commercialista |
| Prezzi dei corsi e dell’abbonamento | Fissati da ciascuna academy |
| Moduli futuri | Ordine definito dopo la messa online della prima versione |
