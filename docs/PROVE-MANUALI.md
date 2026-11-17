# Prove manuali: collaudo prima della consegna

Da fare su un sito di prova pulito (LocalWP) con lo zip di rilascio, non con la copia di sviluppo. Segna ogni voce
`[x]` quando funziona. Dove serve, prima accendi il modulo in **Academy → Configurazione**.

**Preparazione:** WooCommerce attivo (euro, Italia, IVA 22% inclusa, "in arrivo" spento, bonifico di prova), Stripe in
sandbox con prodotto mensile, portale clienti e Stripe CLI avviata. Un utente "studente.prova" (Sottoscrittore).

## A. Contenuti e programma (core)

- [ ] Creo un corso con sottotitolo, docente, livello, "cosa si impara", "a chi è rivolto", immagine.
- [ ] Nel riquadro **Programma** aggiungo 2 moduli e 3 lezioni scrivendo il titolo; le riordino trascinando e sposto una lezione tra moduli.
- [ ] Una lezione con video YouTube e una con Vimeo (incollo l'indirizzo, il sistema riconosce l'ID).
- [ ] Una lezione con un file in "Materiali della lezione".
- [ ] Segno una lezione come "Anteprima gratuita".
- [ ] Elimino una lezione dal Programma: finisce nel cestino.

## B. Pagine pubbliche (da non collegato, in incognito)

- [ ] **Catalogo**: vedo il corso; filtro per categoria, docente e ricerca; la paginazione funziona.
- [ ] **Scheda corso**: docente, livello, durata, programma con lezioni "Riservata" e lucchetto; pulsante coerente con i moduli accesi.
- [ ] **Lezione riservata**: mostra "Questa lezione è riservata"; nel sorgente della pagina NON compare l'ID del video (cerco `youtube`).
- [ ] **Anteprima gratuita**: la lezione si apre con il video.
- [ ] Un indirizzo di download di un materiale (`?ael_download=...`) da non collegato dà "Non hai accesso a questo file".
- [ ] Pagina del docente e raccolta (se usate) con i corsi.

## C. Accesso e area studente

- [ ] **Accedi**: password sbagliata → messaggio rosso sulla stessa pagina; password giusta → "I miei corsi".
- [ ] Dalla scheda corso, "Accedi" riporta al corso dopo l'accesso.
- [ ] Lo studente non vede la barra nera di WordPress.
- [ ] **I miei corsi** mostra solo i corsi a cui ha accesso, con "Inizia/Continua il corso" e barra di avanzamento.
- [ ] Guardo un video per 40 secondi e ricarico: riparte da lì; "Segna come completata" funziona e si può annullare; al 90% del video la lezione si completa da sola; "Continua il corso" porta alla lezione giusta.

## D. Amministrazione (titolare)

- [ ] **Panoramica**: numeri e tabelle senza errori.
- [ ] **Studenti**: elenco, ricerca, CSV; nel dettaglio vedo accessi, progressi e tempo guardato.
- [ ] Concedo un accesso manuale (con durata) e lo revoco; un accesso da pagamento non si revoca da qui.
- [ ] Un corso con "Escludi dall'abbonamento" non si apre a un abbonato.
- [ ] Il cliente (Amministratore) NON vede Configurazione né Abbonamento; l'agenzia sì.

## E. Schede docente

- [ ] Nel docente compilo frase, copertina, colore e 2 link: la sua pagina e la scheda corso li mostrano; un link `javascript:` non viene salvato.
- [ ] Spengo "Schede docente personalizzabili": tutto torna a mostrare solo il nome.

## F. Acquisto singolo (WooCommerce)

- [ ] Nel corso imposto un prezzo: nasce il prodotto collegato; la pagina del prodotto porta al corso.
- [ ] Da non collegato: "Acquista a …" → checkout: senza password o senza casella del recesso l'ordine NON parte.
- [ ] Ordine con bonifico → "In attesa"; lo metto su **Completato**: il corso compare in "I miei corsi". Nell'ordine vedo il consenso (data).
- [ ] Rimborso totale: il corso sparisce. Rimborso parziale: il corso resta.
- [ ] Un utente che ha già il corso non può ricomprarlo.

## G. Abbonamento (Stripe, sandbox)

- [ ] "Verifica il collegamento" in Academy → Abbonamento dice "Collegamento riuscito".
- [ ] Pagina Abbonati: registrazione, consenso obbligatorio, pagamento con la carta 4242 4242 4242 4242.
- [ ] Ritorno su "I miei corsi" con "Il tuo abbonamento è attivo" e tutto il catalogo accessibile.
- [ ] "Gestisci abbonamento" apre il portale; disdetta → "disdetto, accesso fino al …" e l'accesso resta.
- [ ] Carta che fallisce al rinnovo (4000 0000 0000 0341): arriva l'email e lo stato mostra "pagamento non riuscito".
- [ ] Rimborso totale dal Dashboard Stripe: l'accesso viene tolto.
- [ ] Gli eventi nella finestra della Stripe CLI rispondono 200.

## H. Ricavi docente e Pannello docente

- [ ] Imposto percentuale predefinita, per docente e per un corso (eccezione).
- [ ] Un acquisto compare in **Vendite e quote** con lordo, IVA, netto, percentuale e quota giusti; un abbonamento NON genera quota.
- [ ] Correggo la commissione: netto e quota si ricalcolano. "Segna come liquidate" e CSV funzionano.
- [ ] Rimborso: la vendita risulta stornata (o la quota ridotta se parziale).
- [ ] Il docente collegato vede "Le mie vendite": solo le sue, senza IVA, commissioni né dati dell'acquirente.

## I. Attribuzione

- [ ] Senza consenso: nessun cookie `ael_attr`. Con il consenso collegato: arrivando con `?utm_source=...` il cookie c'è.
- [ ] Nel checkout compare "Come ci hai conosciuto?" (obbligatoria) in WooCommerce e in Stripe.
- [ ] Academy → Provenienza mostra primo/ultimo contatto, codice promo e risposta; il CSV si scarica.

## J. Magic link

- [ ] Da Accedi: "Inviami il link" con l'email di uno studente → arriva l'email (serve invio email configurato).
- [ ] Il link apre una pagina con il pulsante "Accedi"; aprirlo due volte (anteprima) non lo consuma; dopo l'uso non funziona più.
- [ ] Un amministratore NON può entrare con il link.

## K. Docenti autori

- [ ] Accendo il modulo; creo un utente con ruolo Docente collegato alla sua scheda.
- [ ] Il docente entra e vede solo Academy → Corsi/Moduli/Lezioni/Docenti (la sua scheda); crea un corso: "Invia in revisione", non "Pubblica".
- [ ] Non vede i corsi degli altri, gli studenti, le vendite né la configurazione.
- [ ] Il titolare pubblica il corso: moduli e lezioni in bozza si pubblicano insieme.

## L. Chiusura

- [ ] Nessun errore in `wp-content/debug.log` durante tutte le prove.
- [ ] Spengo tutti i moduli e riaccendo: nessun dato perso.
- [ ] Zip installato su un sito pulito (non quello di sviluppo): niente "Prova accessi", niente modulo di prova.
- [ ] Disinstallazione con "Cancella tutti i dati" acceso su una copia: tabelle, pagine e prodotti collegati spariscono.
