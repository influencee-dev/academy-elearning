// Contenuto del video: capitoli, schermate e testi. Le misure "focus" sono in pixel dell'immagine: [x, y, larghezza, altezza].
export type Focus = [number, number, number, number];

export type Shot = {
  img: string;
  seconds: number;
  focus?: Focus;
  caption?: string;
};

export type Scene = {
  kind: 'intro' | 'steps' | 'outro';
  title: string;
  caption: string;
  shots: Shot[];
};

export const FPS = 30;

export const scenes: Scene[] = [
  {
    kind: 'intro',
    title: 'Compila la tua scheda e crea i tuoi corsi',
    caption: 'Guida per i docenti · circa due minuti',
    shots: [{ img: '', seconds: 4 }],
  },
  {
    kind: 'steps',
    title: 'Entra nella tua area',
    caption: 'Apri il link che ti ha mandato il titolare e accedi con nome utente e password.',
    shots: [{ img: '01-accesso.png', seconds: 8 }],
  },
  {
    kind: 'steps',
    title: 'Il menu a sinistra',
    caption: 'Il lavoro si fa dal menu: Academy per i contenuti, Le mie vendite per i tuoi numeri.',
    shots: [{ img: '03-elenco-corsi.png', seconds: 8, focus: [0, 90, 175, 335] }],
  },
  {
    kind: 'steps',
    title: 'Apri la tua scheda',
    caption: 'Vai su Academy, poi Docenti, e clicca sul tuo nome.',
    shots: [
      { img: '04-scheda-docente.png', seconds: 6, focus: [0, 165, 170, 140] },
      {
        img: '04-scheda-docente.png',
        seconds: 6,
        focus: [182, 270, 940, 370],
        caption: 'Nella casella di testo scrivi la tua presentazione: chi sei e cosa fai.',
      },
    ],
  },
  {
    kind: 'steps',
    title: 'Metti la tua foto',
    caption: 'In Immagine in evidenza carica la tua foto, meglio se verticale.',
    shots: [{ img: '05-immagine-docente.png', seconds: 7 }],
  },
  {
    kind: 'steps',
    title: 'Frase e copertina',
    caption: 'Scrivi una frase breve che ti presenta.',
    shots: [
      { img: '06a-frase.png', seconds: 6, focus: [240, 12, 690, 52] },
      { img: '06b-copertina.png', seconds: 6, focus: [240, 128, 210, 52], caption: 'Scegli una copertina orizzontale per la tua pagina.' },
    ],
  },
  {
    kind: 'steps',
    title: 'Canali e numeri',
    caption: 'Aggiungi i tuoi canali con follower ed engagement rate. Scrivi la data in cui hai letto i numeri.',
    shots: [
      { img: '06d-canali.png', seconds: 9, focus: [118, 24, 830, 100] },
      {
        img: '18-docente-pubblico.png',
        seconds: 7,
        focus: [372, 590, 520, 105],
        caption: 'I numeri danno autorevolezza: gli studenti li vedono nella tua pagina.',
      },
    ],
  },
  {
    kind: 'steps',
    title: 'Crea un corso',
    caption: 'Vai su Academy, Corsi, Aggiungi Corso. Scrivi il titolo e la descrizione.',
    shots: [{ img: '07a-nuovo-corso.png', seconds: 8, focus: [180, 90, 945, 50] }],
  },
  {
    kind: 'steps',
    title: 'Dettagli e copertina',
    caption: 'Compila sottotitolo, livello, cosa si impara e a chi è rivolto.',
    shots: [
      { img: '08-dettagli-corso.png', seconds: 7 },
      { img: '10-copertina-corso.png', seconds: 6, caption: "L'Immagine in evidenza è la copertina del corso, quella che si vede nel catalogo." },
    ],
  },
  {
    kind: 'steps',
    title: 'Moduli e lezioni',
    caption: 'Aggiungi moduli e lezioni scrivendo il titolo. Trascina per cambiare l\'ordine.',
    shots: [{ img: '09-programma.png', seconds: 10 }],
  },
  {
    kind: 'steps',
    title: 'Il video della lezione',
    caption: 'Incolla il link del video: YouTube non in elenco oppure Vimeo. Vanno bene anche i video verticali.',
    shots: [
      { img: '13-video-lezione.png', seconds: 9, focus: [20, 130, 915, 66] },
      { img: '14-materiali.png', seconds: 5, caption: 'Se serve, aggiungi i materiali da scaricare.' },
    ],
  },
  {
    kind: 'steps',
    title: 'Invia per la revisione',
    caption: 'Quando è pronto premi Invia per la revisione. Il titolare imposta il prezzo e pubblica.',
    shots: [{ img: '11a-invia-revisione.png', seconds: 7, focus: [122, 183, 162, 44] }],
  },
  {
    kind: 'steps',
    title: 'Come lo vede lo studente',
    caption: 'Questo è il corso pubblicato, con il tuo riquadro e i tuoi numeri.',
    shots: [{ img: '17-corso-pubblico.png', seconds: 6 }],
  },
  {
    kind: 'steps',
    title: 'Le tue vendite',
    caption: 'In Le mie vendite vedi gli acquisti e la tua quota, in sola lettura.',
    shots: [{ img: '15-vendite.png', seconds: 6 }],
  },
  {
    kind: 'outro',
    title: 'Hai dubbi?',
    caption: "Rileggi la guida scritta (PDF) o scrivi al titolare dell'academy.",
    shots: [{ img: '', seconds: 5 }],
  },
];

export const sceneFrames = (s: Scene) => s.shots.reduce((a, x) => a + Math.round(x.seconds * FPS), 0);
export const totalFrames = scenes.reduce((a, s) => a + sceneFrames(s), 0);
