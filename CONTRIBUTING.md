# Contribuer

1. Créer une branche depuis `main`.
2. Installer les dépendances avec `composer install`.
3. Copier `.env.example` vers `.env`, configurer PostgreSQL puis exécuter `php artisan migrate --seed`.
4. Ajouter des tests pour toute nouvelle fonctionnalité.
5. Vérifier le code avec `vendor/bin/pint` et `php artisan test`.
6. Utiliser les commits Conventional Commits : `feat:`, `fix:`, `test:`, `docs:`, `ci:`.
7. Ouvrir une pull request en décrivant les changements et les tests exécutés.

Ne jamais committer `.env`, des secrets ou des fichiers générés.
