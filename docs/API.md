# HR API

Toutes les routes `/api` protégées nécessitent un token Sanctum :

```http
Authorization: Bearer <token>
Accept: application/json
```

## Authentification

| Méthode | Route | Description |
|---|---|---|
| POST | `/api/register` | Créer un compte |
| POST | `/api/login` | Obtenir un token |
| POST | `/api/logout` | Révoquer le token courant |
| GET | `/api/me` | Profil courant |

## Ressources

Les modules suivants exposent des endpoints CRUD paginés :

- `/api/departments` et `/api/positions`
- `/api/employees`
- `/api/attendances`
- `/api/leaves`
- `/api/evaluations`
- `/api/contracts`
- `/api/payrolls`
- `/api/notifications`
- `/api/dashboard`

Les listes acceptent généralement `page`, `per_page` et les filtres propres au module.

## Filtres `GET /api/employees`

| Paramètre | Description |
|---|---|
| `status` | `active`, `inactive`, `on_leave`, `terminated` |
| `department_id` | Filtrer par département |
| `position_id` | Filtrer par poste |
| `contract_type` | `cdi`, `cdd`, `stage`, `alternance` |
| `search` | Recherche insensible à la casse (nom, prénom, email, matricule) |

`GET /api/employees/{id}/history` renvoie l'historique des changements de poste et de salaire (paginé). `DELETE /api/employees/{id}` effectue une suppression douce (soft delete).

## Remplacements de congé (`/api/leaves/{id}/replacements`)

Un remplacement désigne un collègue qui couvre un congé donné. Les permissions
sont celles du module `leaves` (`view|create|update|delete|manage leaves`).

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/leaves/{leave}/replacements` | Liste des remplacements du congé (filtre `status`) |
| POST | `/api/leaves/{leave}/replacements` | Demander un remplacement |
| GET | `/api/replacements/{replacement}` | Détail d'un remplacement |
| PATCH | `/api/replacements/{replacement}/accept` | Accepter (depuis `pending` uniquement) |
| PATCH | `/api/replacements/{replacement}/decline` | Refuser, `reason` optionnel (depuis `pending` uniquement) |
| DELETE | `/api/replacements/{replacement}` | Annuler (`pending` ou `accepted`) |

Corps de la création : `replacement_employee_id` (obligatoire, différent de
l'employé du congé), `start_date`/`end_date` (optionnels, période du congé par
défaut), `responsibilities` (optionnel, max 2000). `original_employee_id` est
déduit du congé, `requested_by` vaut l'utilisateur courant et le statut initial
est `pending`. Une erreur métier (HTTP 422) est renvoyée si un remplacement
`accepted` existe déjà pour le congé.

Machine à états : `pending → accepted | declined | cancelled`,
`accepted → cancelled` ; `declined` et `cancelled` sont terminaux. Les
transitions invalides renvoient une erreur métier HTTP 422
(`BusinessRuleException`).

Cohérence avec le congé : à l'acceptation, `leaves.replacement_employee_id`
est synchronisé avec le remplaçant accepté (et remis à `null` si ce
remplacement est ensuite annulé). Une seule acceptation est possible par
congé — accepter un second remplacement tant qu'un autre est `accepted`
renvoie également une erreur métier 422. `GET /api/leaves/{leave}` expose la
collection `replacements` du congé.

Notifications automatiques (module `notifications`) : demande → utilisateur
lié à l'employé remplaçant ; acceptation/refus → demandeur ; annulation →
remplaçant. Aucune notification n'est émise si l'employé n'a pas d'utilisateur
lié.

Les réponses utilisent le format standard :

```json
{"success": true, "message": "...", "data": {}}
```

Les erreurs de validation renvoient HTTP 422 avec un objet `errors`. Les erreurs d'authentification et d'autorisation renvoient respectivement HTTP 401 et 403.

## Exemple

```bash
TOKEN=$(curl -s -X POST http://localhost/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"password"}' | jq -r '.data.token')

curl http://localhost/api/dashboard/statistics \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

## Documentation OpenAPI

Cette documentation constitue le guide de démarrage. Les annotations OpenAPI peuvent être ajoutées progressivement aux contrôleurs lorsque le package Swagger sera activé.
