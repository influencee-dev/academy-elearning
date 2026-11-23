# Collegare Claude all'academy (modulo "Accesso per assistenti AI")

Con questo collegamento Claude può leggere e modificare **corsi, moduli, lezioni e schede docente** dell'academy, e leggere alcuni
numeri. Non può toccare pagamenti, rimborsi, chiavi, utenti né gli interruttori dei moduli. Quando elimina, manda solo nel cestino
moduli e lezioni (si recuperano dall'amministrazione).

**Cosa serve:** WordPress 6.9 o successivo, il sito in **HTTPS**, il plugin dell'academy dalla versione 1.1.0, e Claude Desktop o
Claude Code sul tuo computer (**non funziona da claude.ai nel browser**: il plugin ufficiale non ha l'accesso con login).

## 1. Sul sito

1. Aggiorna il plugin dell'academy alla 1.1.0 (zip caricato sopra quello esistente).
2. **Academy → Configurazione → Moduli**: accendi **Accesso per assistenti AI (MCP)** e salva. (Lo fa solo l'agenzia.)
3. **Plugin → Aggiungi nuovo**: cerca **MCP Adapter** (plugin ufficiale di WordPress), installa e attiva.
   Se non lo trovi, scaricalo da https://github.com/WordPress/mcp-adapter/releases/latest (file `mcp-adapter.zip`) e caricalo da
   **Plugin → Aggiungi nuovo → Carica plugin**.
4. **Utenti → Aggiungi**: crea un utente solo per Claude (per esempio `claude-academy`), ruolo **Amministratore**. Claude farà solo
   quello che può fare quell'utente: se un giorno vuoi smettere, elimini la password o l'utente.
5. Entra con quell'utente, vai in **Utenti → Profilo**, in fondo c'è **Password applicazione**: scrivi un nome (per esempio
   "Claude sul mio computer") e premi **Aggiungi**. WordPress mostra una password lunga con gli spazi: **copiala subito, si vede una
   sola volta**. Non scriverla in chat né in un file del progetto.

L'indirizzo che serve dopo è questo (cambia solo il dominio):
`https://academy.influencee.it/wp-json/mcp/mcp-adapter-default-server`
Se i permalink non sono su "Nome articolo": `https://academy.influencee.it/?rest_route=/mcp/mcp-adapter-default-server`.

## 2. Sul tuo computer: Claude Desktop

Serve Node.js installato (da https://nodejs.org). Poi in Claude Desktop: **Settings → Developer → Edit Config** e aggiungi:

```json
{
  "mcpServers": {
    "academy-influencee": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://academy.influencee.it/wp-json/mcp/mcp-adapter-default-server",
        "WP_API_USERNAME": "claude-academy",
        "WP_API_PASSWORD": "incolla qui la password applicazione"
      }
    }
  }
}
```

Salva e riavvia Claude Desktop. La password sta solo in questo file sul tuo computer.

## 2 bis. Sul tuo computer: Claude Code

Crea la stringa di accesso (in PowerShell, con la tua password applicazione tra gli apici):

```powershell
[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes('claude-academy:incolla qui la password applicazione'))
```

Poi, con quel risultato al posto di `INCOLLA_QUI`:

```powershell
claude mcp add --transport http academy-influencee https://academy.influencee.it/wp-json/mcp/mcp-adapter-default-server --header "Authorization: Basic INCOLLA_QUI"
```

## 3. Come si usa

Claude vede tre strumenti generici dell'adapter (scoprire, leggere i dettagli ed eseguire un comando) e, tramite quelli, i comandi
dell'academy: `list-courses`, `get-course`, `save-course`, `save-module`, `save-lesson`, `trash-program-item`, `list-teachers`,
`save-teacher`, `academy-summary`. Basta chiedere: "elenca i corsi", "crea il modulo 2 nel corso Reel verticali con tre lezioni",
"cambia il prezzo a 60 euro", "aggiorna follower e engagement di Elena".

Regole che il modulo applica da solo:
- chi non può pubblicare (per esempio un docente) salva sempre in bozza;
- prezzo, docente del corso ed esclusione dall'abbonamento li cambia solo l'agenzia o il titolare;
- ogni comando controlla i permessi dell'utente collegato, gli stessi dell'amministrazione.

## 4. Sicurezza

- Fai una copia di sicurezza prima di lavori grandi: le modifiche vanno sul sito vero.
- La password applicazione si revoca in qualsiasi momento da **Utenti → Profilo** dell'utente `claude-academy`.
- Se il sito risponde "401" a Claude, il server sta togliendo l'intestazione di accesso: chiedi all'assistenza dell'hosting di lasciarla
  passare a PHP.
- Non spegnere HTTPS: senza, WordPress non offre le password applicazione.
- Per scollegare tutto: spegni il modulo in **Academy → Configurazione**, oppure disattiva MCP Adapter.
