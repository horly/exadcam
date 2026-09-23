# EXADCAM

Plateforme web de suivi GPS, de vidéo embarquée et d'alertes pour les dashcams.
Projet indépendant d'EXAD Tracking, développé dans `D:\App\Codex\exadcam`.

## Priorité actuelle

Développer la plateforme **web en premier**. L'application mobile Android/iPhone
viendra après. La connexion web est fonctionnelle avec Laravel Fortify et protège
le tableau de bord interactif de démonstration. La gestion des utilisateurs est
fonctionnelle sur la base EXADCAM ; les autres modules métier et la communication
avec les dashcams restent à développer.

## Interface et organisation

- Laravel 12, rendu serveur Blade, contrôleurs et modèles Eloquent.
- Bootstrap 5.3.8 installé via Composer (`twbs/bootstrap`) et servi localement.
- ApexCharts local via `akaunting/laravel-apexcharts` 4.0.0, comme dans EXAD Tracking ;
  [graphiques de démonstration](docs/dashboard-charts.md).
- Aucun CDN, aucune police distante et aucune dépendance Tailwind pour l'interface.
- [Google Maps](docs/google-maps.md) charge son API cartographique officielle depuis
  Google ; les positions affichées restent fictives pour le moment.
- Layout dans `resources/views/layouts`, fragments dans `resources/views/partials`.
- Styles dans `public/css`, comportements JavaScript dans `public/js`.
- Bibliothèques tierces dans `public/vendor`, sans modification de leur code.
- Vite reste disponible pour les futurs bundles spécifiques. La page d'accueil
  utilise directement les fichiers locaux via `asset()` et ne dépend pas de Vite.

L'installation via Composer suit la [documentation officielle de Bootstrap](https://getbootstrap.com/docs/5.3/getting-started/download/#composer).

## Installation

Prérequis : PHP 8.2 ou ultérieur avec PDO MySQL, MySQL/MariaDB, Composer, Node.js et npm compatibles avec Vite 7.

```sh
composer install
npm install
```

Pour une nouvelle installation, créer `.env` à partir de `.env.example`, renseigner
les paramètres propres à EXADCAM, puis exécuter :

```sh
php artisan key:generate
php artisan migrate
```

La base locale utilisée est `exadcam`. Configurer son accès dans `.env` et créer
cette base avant les migrations. Conserver la clé d’une installation existante.
EXADCAM garde sa propre clé, ses comptes, ses données et ses fichiers utilisateurs.
Voir [le schéma et les autorisations](docs/database-and-access.md).

Bootstrap et ApexCharts sont republiés automatiquement après `composer install` /
`composer update`. Pour republier manuellement leurs scripts, styles et licences :

```sh
composer assets:publish
```

## Développement et vérification

```sh
php artisan serve
php artisan test
vendor/bin/pint --test
npm run build
```

Ouvrir l'URL locale indiquée par Artisan. `composer dev` reste disponible pour
lancer les services de développement ensemble ; sous Windows, `php artisan serve`
et, au besoin, `npm run dev` peuvent être lancés séparément.

Lire [les conventions et le périmètre](docs/project-context.md) avant d'ajouter un module.
L’[historique du projet](docs/project-history.md) consigne les décisions, les étapes
livrées, les vérifications et les travaux restants. Le compléter après chaque lot
significatif, conformément aux consignes de [AGENTS.md](AGENTS.md).


## Aperçu du tableau de bord

L’accueil est un prototype de supervision avec six véhicules fictifs, des filtres,
un fond Google Maps et des fenêtres de prévisualisation. Les positions sont fictives et créées
uniquement pour le rendu et ne sont jamais enregistrées. Aucun flux GPS ou vidéo
réel n’est connecté. Les menus donnent accès aux vues de démonstration et aux
rubriques métier encore en préparation.

## Connexion et création du premier compte

Ouvrir `/login`. La connexion par e-mail et mot de passe, la mémorisation de session
et la déconnexion par POST sont gérées par Laravel Fortify. Après cinq échecs pour
une adresse e-mail et une IP, les tentatives sont bloquées pendant une minute.
Les formulaires utilisent CSRF et les pages de connexion/tableau de bord ne sont
pas mises en cache. Le cookie `exadcam_session` sépare la session des autres projets locaux.

Créer un compte dans le terminal du projet :

```sh
php artisan app:create-user --role=superadmin
```

Le nom et l'e-mail sont demandés, puis le mot de passe est saisi deux fois de manière
masquée (12 caractères minimum, 72 octets maximum avec bcrypt). Ne pas transmettre
le mot de passe en argument de commande. Les options `--name` et `--email` sont
disponibles. Le rôle vaut `user` par défaut ; `--role` accepte aussi `admin` ou `superadmin`. Un compte existant n'est jamais écrasé. Aucun compte ni mot de passe
par défaut n'est ajouté par le seeder. Les comptes de test sont créés uniquement
dans la base SQLite en mémoire des tests.

L'inscription publique est désactivée. Le bouton « Besoin d’aide ? » explique la
procédure de contact de l’administrateur. Les trois rôles, le statut des comptes,
les droits JSON, les rattachements aux flottes et l’historique des connexions sont
en place. La [gestion des utilisateurs](docs/user-management.md) reprend EXAD
Tracking : comptes, rôles, permissions, affectation à une flotte, historique et
suppression confirmée. Une flotte active est obligatoire ; les écrans de gestion
des flottes et la récupération par e-mail restent à réaliser.

Sur un hébergement public, configurer HTTPS et `SESSION_SECURE_COOKIE=true`.
La page locale est accessible en HTTP uniquement pour le développement.

Ressources de la connexion : `resources/views/auth`, `layouts/auth.blade.php`,
`public/css/auth-login.css`, `public/js/auth-login.js` et photographies générées par IA, optimisées en WebP et servies localement.
Le logo officiel EXAD et le visuel de la dashcam au premier plan sont documentés
dans [exad-identity.md](docs/exad-identity.md), avec les sources et le prompt final.
Les seules routes Fortify exposées sont celles déjà réalisées : login et logout.

Le formulaire de connexion propose le français et l'anglais, avec un choix de langue
mémorisé dans le navigateur. Voir [la localisation et la présentation](docs/localization.md)
pour les catalogues Laravel Lang, les fichiers du formulaire et les tests.
