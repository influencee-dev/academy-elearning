# Academy E-learning

Plugin WordPress unico per le academy online (la nostra e quelle dei clienti). Le regole del progetto sono in CLAUDE.md, il flusso completo e le decisioni in docs/academy-elearning-flusso.md.

## Contenuto di questa cartella

- `CLAUDE.md` — regole fisse che Claude Code rilegge a ogni sessione
- `docs/academy-elearning-flusso.md` — documento master (fonte di verità)
- `docs/Academy-elearning-documento-master.pdf` — lo stesso documento in PDF
- `docs/trascrizione-call.txt` — la call in cui è nato il progetto
- `docs/academy-specialty/` — documento di progetto inviato a Specialty, prima academy da installare
- `CHANGELOG.md`, `.gitignore`

La cartella `docs/` serve solo come riferimento e non va inclusa nello zip del plugin.

## Come partire

1. Crea su GitHub il repository `academy-elearning` (privato) e carica il contenuto di questa cartella.
2. Installa LocalWP su Windows e crea un sito di test con WordPress e WooCommerce. Attiva Stripe in modalità test: le chiavi di test si inseriscono più avanti nelle impostazioni del plugin, mai nel codice o in chat.
3. Apri il repository con Claude Code e incolla il prompt qui sotto come prima richiesta.

## Prompt per la prima sessione

```
Leggi CLAUDE.md e docs/academy-elearning-flusso.md per intero.
Poi, prima di scrivere codice, proponimi:
1. il piano di sviluppo della prima versione completa, diviso in passi piccoli e verificabili;
2. lo schema delle tabelle e dei custom post type;
3. l'elenco degli hook che la base esporrà ai moduli;
4. i punti del documento master che ti sembrano poco chiari o da confermare.
Non scrivere codice finché non ti confermo il piano. Lavora sul ramo feature/core-base.
```
