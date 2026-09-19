# Eways Next

Parallel rewrite of Eways Video Commerce. The current production code remains untouched while this tree is built and verified.

## Stack

- Backend: Laravel 13 / PHP 8.3+
- App frontend: React + Vite
- Admin frontend: React + Vite
- Database: existing MySQL schema, migrated gradually to Laravel migrations
- Deployment: GitHub Actions builds Composer/Vite artifacts before FTP deployment

## Migration rules

1. Do not remove or alter the current production PHP app until feature parity is verified.
2. Preserve existing database data and API behaviour during migration.
3. Keep Eways integration behind one service layer.
4. Add automated smoke checks before any production cutover.
5. Cut over route-by-route, not as a single big-bang replacement.

## Planned phases

1. Foundation and health API
2. Eways authentication/integration service
3. Catalog/categories/videos
4. Admin CRUD and media upload
5. React video app
6. React admin
7. Cart/order flow
8. PWA/update flow
9. Production cutover and legacy cleanup
