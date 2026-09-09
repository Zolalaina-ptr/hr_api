# HR API — Gestion des Ressources Humaines

API REST de gestion des ressources humaines, construite avec **Laravel 13** et **PHP 8.3**.
Elle fournit les modules métier complets (employés, départements, postes, congés,
remplacements, présences, contrats, paie, évaluations, tableaux de bord, notifications)
avec authentification par jeton (Sanctum) et contrôle d'accès par rôles/permissions (Spatie).

---

## Stack technique

| Couche | Technologie |
|---|---|
| Langage | PHP 8.3+ |
| Framework | Laravel 13 |
| Auth (API) | Laravel Sanctum |
| RBAC | spatie/laravel-permission |
| Base de données | PostgreSQL (production) / SQLite (tests) |
| Tests | PHPUnit (suite `tests/Feature`) |
| Qualité | Laravel Pint, IDE Helper |
| CI | GitHub Actions (`tests.yml`) |
| Déploiement | Docker (`Dockerfile`, `docker-compose.yml`) |

---

## Modules fonctionnels

- **Authentification** — connexion, profils, jetons Sanctum.
- **Employés** — CRUD, historique, compétences, langues.
- **Départements & postes** — structures hiérarchiques, exigences de poste, budgets.
- **Congés** — demandes, soldes, approbations, statistiques, exports CSV.
- **Remplacements de congé** — machine à états `pending → accepted/declined/cancelled`,
  notifications, synchronisation avec le congé parent.
- **Présences** — plannings, jours fériés, pointages et exceptions.
- **Contrats & paie** — génération mensuelle en file d'attente, statuts, export CSV.
- **Évaluations** — objectifs, périodes, notation.
- **Tableau de bord & statistiques** — indicateurs mis en cache.
- **Notifications** — gestion des notifications utilisateur.

---

## Installation

Prérequis : PHP 8.3+, Composer, et une base de données PostgreSQL (SQLite pour les tests).

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

L'API est exposée sous le préfixe `/api` (voir [`routes/api.php`](routes/api.php)).
Le contrat des endpoints est documenté dans [`docs/API.md`](docs/API.md).

---

## Tests

```bash
composer test          # ou : php artisan test
vendor/bin/pint        # formatage et analyse de style
```

---

## Contribuer

Voir [`CONTRIBUTING.md`](CONTRIBUTING.md) — branche depuis `main`, commits en
Conventional Commits, `pint` et tests requis avant toute pull request.

---

## Licence

Propriétaire — usage interne.
