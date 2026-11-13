# Changelog

Tutte le modifiche rilevanti del plugin, dalla più recente. Versioni secondo semantic versioning.

## [0.1.0] - in sviluppo (ramo feature/core-base)
- Passo 0: scheletro del plugin. File di avvio con controllo dei requisiti (PHP 8.1, WordPress 6.4), caricamento automatico delle classi, classe `AEL_Plugin`, caricamento delle traduzioni e primo hook `ael_loaded`. Ancora nessuna funzione visibile.

- Passo 1: registro dei moduli (`AEL_Modules`) e pagina Configurazione riservata all'agenzia (`AEL_Settings`, capacità `ael_manage_modules`). I moduli si accendono dalla pagina e un modulo spento non carica nulla. Opzioni del core: pagine docente, anteprime gratuite, sorgenti video, cancellazione dati alla disinstallazione. Hook: `ael_modules_loaded`, `ael_module_activated`, `ael_module_deactivated`, `ael_settings_saved`, `ael_default_settings`. Aggiunto un modulo di prova visibile solo con WP_DEBUG acceso (da escludere dallo zip).
- Passo 2: installer con migrazioni versionate (`AEL_Installer`) e tabelle del core `ael_grants`, `ael_progress`, `ael_watch_time`. Le migrazioni in sospeso partono a ogni caricamento, quindi anche dopo un aggiornamento via zip. I moduli aggiungono le loro con il filtro `ael_migrations`. `uninstall.php` cancella i dati solo se l'agenzia ha spuntato "Cancella tutti i dati". La pagina Configurazione mostra lo stato delle tabelle.
- Documentazione: gli interruttori delle funzioni sono decisi dall'agenzia prima della consegna, da una pagina riservata (capacità `ael_manage_modules`). Il cliente non li vede. Aggiornati CLAUDE.md e documento master.

## [Non rilasciato]
- Avvio del progetto: CLAUDE.md, documento master e materiali di riferimento in docs/.
