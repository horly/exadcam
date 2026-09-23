# Graphiques et thème du dashboard

## Bibliothèque

Même bibliothèque que le projet de référence EXAD Tracking, consulté en lecture
seule : [Akaunting Laravel ApexCharts](https://github.com/akaunting/laravel-apexcharts)
4.0.0. Le package inclut ApexCharts JavaScript 3.35.1. Les versions sont conservées
explicitement pour reproduire le socle observé. Les notices originales restent
dans le fichier JavaScript et `public/vendor/apexcharts/LICENSE.md`.

Installation/republication :

```sh
composer install
composer assets:publish
```

Le publisher copie les fichiers depuis `vendor/akaunting/laravel-apexcharts`.
Les vues chargent uniquement `asset('vendor/apexcharts/apexcharts.js')` et nos
ressources locales. Aucun CDN ni intégration supplémentaire NPM n’est nécessaire.

## Organisation

- `DashboardPreviewController` : séries fictives, libellés traduits, répartition
  déduite de la collection de véhicules fictifs.
- `partials/dashboard-charts.blade.php` : panneaux, tableau alternatif et JSON
  encodé avec `Illuminate\Support\Js::encode`.
- `public/js/dashboard-charts.js` : instanciation des graphiques, périodes,
  redimensionnement et écoute de l’événement `exadcam:view-changed`.
- `public/css/dashboard.css` : bleu principal `#203d65`, bleu secondaire `#7799bd`,
  contrôles, indicateurs et présentation responsive.

La [méthode updateOptions](https://apexcharts.com/docs/methods/) actualise ensemble
les catégories et les séries lorsque la période change. Les graphiques ne sont
créés que lorsque la vue Tableau de bord est visible. Les animations respectent
`prefers-reduced-motion` ; les valeurs restent disponibles dans un tableau HTML
et une légende textuelle. Si le script de la bibliothèque est indisponible, un
message ouvre le tableau de valeurs.

## Données actuelles

L’activité illustre une journée ou une semaine type, avec un maximum de six
véhicules. L’état reprend les six équipements fictifs : deux en mouvement, deux
à l’arrêt mais connectés, deux hors ligne. Le bandeau, les légendes et les
mentions de simulation distinguent ces valeurs des futures données réelles.
Les compteurs ne prouvent pas la disponibilité d’un flux vidéo.

L’intégration future devra remplacer ces séries par des agrégats limités aux
flottes autorisées côté serveur, avec période et fuseau horaire définis.
