# TMS — Ticket Management System

Plateforme web de gestion de demandes de support (tickets), conçue pour plusieurs organisations clientes. Le projet associe une API Laravel, une interface React et une infrastructure Docker Compose. L’isolation des organisations est appliquée dans PostgreSQL au moyen du Row-Level Security (RLS), en complément des contrôles d’autorisation applicatifs.

## Objectif

Centraliser la création et le traitement des demandes de support tout en permettant à plusieurs organisations (« tenants » ou « entités ») de partager la même plateforme sans accéder aux données des autres. Les demandes non attribuées sont placées dans une réserve commune, puis proposées selon les compétences des agents.

## Profils utilisateurs

| Profil | Responsabilités principales |
| --- | --- |
| Super Admin | Administration de la plateforme, des entités et des rapports inter-entités |
| Tenant Admin | Gestion des utilisateurs, rôles et opérations de son entité |
| Manager | Supervision des tickets, des agents et des rapports de son entité |
| Support Agent | Prise en charge des tickets pour lesquels il possède les compétences requises |
| Client | Création et suivi de ses demandes, commentaires et pièces jointes autorisées |

Les rôles sont définis dans `backend/tms-backend/app/Enums/RoleName.php`. Les permissions sont gérées par Spatie Laravel Permission en mode « teams » et les règles par des Policies Laravel.

## Fonctionnalités

- Gestion multi-entités et comptes utilisateurs avec rôles et compétences d’agent.
- Création, recherche, filtrage, affectation et suivi des tickets.
- Cycle de vie : `Pending`, `In Progress`, `On Hold`, `Resolved`, `Closed`.
- Réserve de tickets non affectés; la prise concurrente d’un ticket est protégée par transaction et verrouillage de ligne.
- Commentaires publics ou internes, pièces jointes privées et analyse antivirus configurable.
- Notifications applicatives, courriels de développement interceptés par Mailpit et événements temps réel via Laravel Reverb.
- Indicateurs de volume, délais de réponse/résolution, activité par agent et tendances.
- Journal d’audit append-only pour les actions sensibles.
- Interface et messages API disponibles en anglais et en français.

## Architecture et sécurité

Le frontend React est une application monopage distincte de l’API Laravel. Il communique avec l’API versionnée par HTTP JSON et des jetons Sanctum. PostgreSQL stocke les données relationnelles, Redis sert au cache, aux files et à Reverb, et MinIO fournit un stockage S3-compatible pour le développement.

Les données des entités partagent les tables PostgreSQL. Les politiques RLS s’appuient sur le contexte de session `app.tenant_id`; une requête sans contexte est fermée par défaut. Les opérations inter-entités autorisées utilisent un bypass explicite et audité. Les autorisations applicatives (permissions et Policies) s’ajoutent à la protection en base.

Les transitions de ticket sont vérifiées par le service métier et les contraintes d’immutabilité importantes sont également protégées au niveau PostgreSQL. Les pièces jointes sont contrôlées par type de contenu, taille et analyse antivirus configurable; les téléchargements utilisent des liens signés de courte durée.

Documentation technique complémentaire :

- [Architecture](docs/architecture.md)
- [Diagramme entité-association](docs/er-diagram.md)
- [Contrat OpenAPI](docs/openapi.yaml)
- [Déploiement et configuration](docs/deployment.md)

## Technologies

| Domaine | Technologies |
| --- | --- |
| Backend | PHP 8.4, Laravel 12, Laravel Sanctum, Spatie Permission, Pest |
| Frontend | React 19, TypeScript, Vite, Tailwind CSS, TanStack Query, React Router |
| Données et services | PostgreSQL 16, Redis 7, MinIO, Mailpit, ClamAV, Laravel Reverb |
| Conteneurs et serveur web | Docker Compose, Nginx |

## Structure du dépôt

```text
backend/tms-backend/   API Laravel, migrations, seeders et tests Pest
frontend/               Interface React, traductions, tests Vitest et E2E Playwright
infra/                  Docker Compose, images, configuration Nginx et scripts
docs/                   Architecture, modèle de données, OpenAPI et déploiement
```

## Démarrage local avec Docker

Prérequis : Docker et le plugin Docker Compose v2. À partir de la racine du dépôt :

```bash
cp backend/tms-backend/.env.example backend/tms-backend/.env
cp infra/.env.example infra/.env   # configurez APP_HOST et les ports ici
docker compose -f infra/docker-compose.yml up -d --build
docker compose -f infra/docker-compose.yml exec backend php artisan key:generate
docker compose -f infra/docker-compose.yml exec backend php artisan migrate
docker compose -f infra/docker-compose.yml exec backend php artisan db:seed
```

`db:seed` installe les rôles, permissions, types de tickets globaux et entité principale. En environnement non-production, il crée également les comptes de démonstration. Ne pas exécuter `migrate:fresh` sur une base à conserver : cette commande détruit les tables avant de les recréer.

## Adresses et paramètres locaux

Les ports sont ceux publiés par `infra/docker-compose.yml` pour une machine de développement locale. Depuis un conteneur Compose, employer les noms de services ci-dessous plutôt que `localhost`.

| Composant | Depuis l’hôte | Depuis le réseau Docker | Paramètres locaux usuels |
| --- | --- | --- | --- |
| Interface web | `http://localhost:8081` | `http://frontend` | Vite dev direct : `http://localhost:5173` |
| API | `http://localhost:8080/api/v1` | `http://nginx/api/v1` | Health check : `http://localhost:8080/up` |
| Documentation API | `http://localhost:8080/docs` | `http://nginx/docs` | OpenAPI source : `docs/openapi.yaml` |
| PostgreSQL | `localhost:5432` | `postgres:5432` | Base `tms`, utilisateur applicatif `tms`, port `5432` |
| Base de tests | `localhost:5432` | `postgres:5432` | Base `tms_testing`, configurée dans `backend/tms-backend/phpunit.xml` |
| Redis | `localhost:6379` | `redis:6379` | Client `predis`; aucun mot de passe par défaut dans Compose |
| Reverb / WebSocket | `ws://localhost:8085` | `reverb:8080` | Même clé publique Reverb que celle injectée dans le frontend |
| MinIO S3 | `http://localhost:9000` | `http://minio:9000` | Console : `http://localhost:9001`; bucket `tms-storage` |
| Mailpit | UI `http://localhost:8025` | `mailpit:1025` | Interface SMTP locale, sans authentification |
| ClamAV | `localhost:3310` | `clamav:3310` | Analyse antivirus des pièces jointes, activable par configuration |

Les variables de configuration à renseigner sont documentées dans [`backend/tms-backend/.env.example`](backend/tms-backend/.env.example), [`frontend/.env.example`](frontend/.env.example) et [`infra/.env.example`](infra/.env.example). Les noms les plus importants sont `APP_URL`, `FRONTEND_URL`, `APP_KEY`, `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `REDIS_HOST`, `AWS_ENDPOINT`, `AWS_BUCKET`, `REVERB_HOST`, `REVERB_PORT`, `VITE_API_URL`, `VITE_APP_URL` et les variables `VITE_REVERB_*`.

Pour accéder à l'application depuis un autre appareil du même réseau local, définissez `APP_HOST` à l'adresse IP de votre machine dans `infra/.env` (voir [`docs/deployment.md`](docs/deployment.md#accessing-from-another-device) pour la procédure détaillée).

Valeurs d’exemple locales présentes dans le Compose et le seeder : utilisateur MinIO `tms-minio` et mot de passe de développement `tms-minio-secret`; la configuration locale PostgreSQL utilise le mot de passe `secret`. Ces valeurs sont réservées à une machine de développement jetable, ne doivent pas être réutilisées sur un serveur exposé et ne remplacent pas une configuration sécurisée par variables d’environnement ou gestionnaire de secrets. Ne jamais publier un vrai fichier `.env`, une `APP_KEY`, un secret Reverb de production, un jeton ou des identifiants réels.

## Comptes de démonstration locaux

Après `db:seed` dans un environnement non-production, les identifiants de démonstration ont le mot de passe partagé `password` :

| Rôle | Entité / espace de travail | Adresse |
| --- | --- | --- |
| Super Admin | Aucun espace à saisir | `super-admin@tms.local` |
| Tenant Admin | `acme` | `admin@acme.tms.local` |
| Support Agent | `acme` | `agent@acme.tms.local` |
| Client | `acme` | `client@acme.tms.local` |
| Tenant Admin | `globex` | `admin@globex.tms.local` |
| Client | `globex` | `client@globex.tms.local` |

Ces comptes et ce mot de passe sont uniquement destinés à la démonstration et aux tests locaux. Le seeder ne crée pas ces comptes en production; le mot de passe doit être changé si des données de démonstration sont utilisées hors d’un environnement jetable.

## Développement et tests

Frontend, depuis `frontend/` :

```bash
npm ci
npm run dev
npm run test:unit
npm run lint
npm run build
```

Backend, depuis la racine du dépôt avec les services Compose démarrés :

```bash
docker compose -f infra/docker-compose.yml exec backend vendor/bin/pest
```

La suite backend exige PostgreSQL réel pour couvrir les UUID, fonctions SQL et politiques RLS. Le script [`infra/scripts/run-backend-tests.sh`](infra/scripts/run-backend-tests.sh) démarre les dépendances et lance Pest dans le conteneur PHP. Les E2E utilisent Playwright et les comptes seedés; vérifier leurs prérequis avant exécution, car certains scénarios de préparation peuvent nécessiter une base dédiée.

Lors des validations de développement du 29 septembre 2026 : 169 tests backend et 509 assertions ont réussi; 28 tests unitaires frontend ont réussi; le build TypeScript/Vite a réussi. Un smoke test navigateur a couvert les pages principales sous Super Admin et Tenant Admin. Oxlint signale deux avertissements Fast Refresh existants dans `badge.tsx` et `button.tsx`; ils ne bloquent pas le lint.

## Limites et suites possibles

La plateforme fournit des métriques de support, mais pas de moteur SLA avec objectifs et alertes de dépassement. Une instance de démonstration peut également ne contenir aucun ticket/client pour le contexte choisi; les écrans doivent alors afficher leur état vide. Les secrets et données de production nécessitent une configuration propre à l’environnement, un stockage sécurisé et un proxy HTTPS.

## Licence

Projet de stage à usage privé/propriétaire. Toute redistribution doit respecter les autorisations de l’établissement et de l’organisation d’accueil.
