# Sophishticated

Plateforme de simulation de phishing et de sensibilisation a la securite. Elle permet aux organisations de lancer des campagnes de phishing controlees pour former les employes a identifier les menaces, suivre leurs reactions en temps reel et mesurer la progression de la vigilance.

## Stack technique

| Couche | Technologies |
|---|---|
| **Frontend** | Vue.js 3, Vue Router, Pinia, Tailwind CSS, Vite, Chart.js, CKEditor 5, Lucide Icons |
| **Backend** | PHP 8.2, Apache |
| **Base de donnees** | MariaDB 10.6 |
| **WebSocket** | Ratchet (suivi temps reel des campagnes) |
| **Queue** | Worker PHP avec retry et backoff exponentiel |
| **Infra** | Docker, Docker Compose |

## Architecture

```
                  ┌──────────────────────┐
                  │   Frontend (Vue SPA) │
                  │   servi par Apache   │
                  └──────────┬───────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
              ▼              ▼              ▼
        ┌──────────┐  ┌──────────┐  ┌────────────┐
        │   App    │  │  Worker  │  │ WebSocket  │
        │ (Apache) │  │ (Queue)  │  │ (Ratchet)  │
        └────┬─────┘  └────┬─────┘  └─────┬──────┘
             │              │              │
             └──────────────┼──────────────┘
                            ▼
                      ┌──────────┐
                      │ MariaDB  │
                      └──────────┘
```

**4 services Docker :**

- **app** — Apache + PHP : sert le SPA, expose l'API REST et execute les taches cron
- **worker** — Traitement asynchrone des jobs (envoi d'emails, synchronisation destinataires)
- **websocket** — Serveur Ratchet pour le monitoring temps reel des campagnes
- **db** — MariaDB

## Fonctionnalites

- **Gestion de campagnes** — Creation, planification, lancement, pause, reprise et annulation de campagnes de phishing
- **Editeur de templates** — Editeur riche (CKEditor) pour les emails et constructeur de landing pages avec champs de capture
- **Suivi multi-methode** — Detection d'ouverture (pixel, CSS `@import`, `@font-face`), suivi des clics et des soumissions
- **Monitoring temps reel** — Compteurs live via WebSocket pendant l'execution des campagnes
- **Gestion des destinataires** — Import, organisation par groupes, synchronisation depuis Microsoft Graph (Azure AD)
- **Statistiques** — Dashboard global, analytiques par campagne et par groupe, graphiques (funnel, jauge, barres, lignes)
- **Import/Export** — Packs de templates au format JSON
- **Page de sensibilisation** — Contenu pedagogique affiche apres une interaction avec le phishing
- **Administration** — Gestion des utilisateurs (admin, manager, viewer), journal d'audit, rate limiting

## Prerequis

- [Docker](https://docs.docker.com/get-docker/) et [Docker Compose](https://docs.docker.com/compose/install/)

## Installation

1. **Cloner le depot**

   ```bash
   git clone https://github.com/elalitte/sophishticated.git
   cd sophishticated
   ```

2. **Configurer l'environnement**

   ```bash
   cp .env.example .env
   ```

   Editer `.env` avec vos parametres (base de donnees, SMTP, Microsoft Graph, etc.).

3. **Lancer les conteneurs**

   ```bash
   docker compose up -d --build
   ```

4. **Acceder a l'application**

   Ouvrir `http://localhost:8443` (ou le port defini dans `APP_PORT`).

## Structure du projet

```
.
├── backend/            # Code PHP (controllers, models, services, middleware, jobs)
├── cron/               # Taches planifiees (verification statut de lecture)
├── database/           # Schema SQL et donnees de seed
├── docker/             # Config Apache, crontab, entrypoint, wait-for-db
├── frontend/           # Application Vue.js (src/, composables, stores, views)
├── public/             # Point d'entree HTTP et assets compiles
├── scripts/            # Scripts utilitaires
├── storage/            # Logs et fichiers temporaires (monte en volume)
├── templates/          # Templates email et landing pages (monte en volume)
├── websocket/          # Serveur WebSocket Ratchet
├── worker/             # Worker de traitement de la file de jobs
├── docker-compose.yml
├── Dockerfile          # Build multi-stage (frontend → app / worker / websocket)
└── vite.config.js
```

## Developpement

Lancer le serveur de developpement frontend (avec proxy vers le backend) :

```bash
npm install
npm run dev
```

Le serveur Vite demarre et redirige les appels `/api`, `/track`, `/phish` et `/awareness` vers le backend PHP.

## Licence

Projet prive.
