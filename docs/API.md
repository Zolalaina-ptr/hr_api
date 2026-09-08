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
