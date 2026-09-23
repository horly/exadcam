# Connexion et langues

Le sélecteur de `/login` propose le français et l’anglais. Le changement se fait
par POST avec CSRF sur `/language/{locale}`. Seules les langues déclarées dans
`config/localization.php` sont acceptées. La redirection rejoint une route interne
connue : connexion pour un visiteur, tableau de bord pour un utilisateur connecté.

Le middleware `SetLocale` s’exécute après le démarrage de session, avant les erreurs
de formulaire et CSRF. Il lit d’abord la préférence en session, puis le cookie
chiffré `exadcam_locale` (un an, HttpOnly, SameSite=Lax). Cette préférence reste
disponible après la déconnexion. À défaut, `APP_LOCALE=fr` s’applique.

## Catalogues

Laravel Lang (`laravel-lang/lang`, dépendance de développement) fournit les
catalogues Laravel/Fortify, publiés dans `lang/fr` et `lang/en`. Les traductions
publiées sont incluses dans le projet et fonctionnent en production sans le paquet.
L’authentification reste assurée par Laravel Fortify. Bootstrap et les ressources
CSS/JavaScript sont servis localement, sans CDN.

Les textes spécifiques à EXADCAM sont rédigés en français dans les vues Blade,
avec leurs traductions anglaises dans `lang/en.json`. La connexion, les messages
de validation, l’aide, la visibilité du mot de passe et la page de session expirée
sont traduits. La navbar du tableau de bord propose également FR/EN, avec ses propres titres
et menus traduits. Le contenu métier de démonstration demeure majoritairement
en français ; ses modules seront internationalisés lors de leur développement.

La publication initiale a utilisé `php artisan lang:add fr en`. Les messages
d’authentification et de validation personnalisés du projet ont été conservés,
et les noms des champs ont été définis dans les deux langues. Une future commande
`lang:add` / `lang:update` doit faire l’objet d’une revue des changements : elle
peut remplacer ces messages personnalisés. Aucune publication automatique des
langues n’est ajoutée aux scripts Composer.

## Présentation

La photo et le logo EXAD de la partie gauche sont conservés. Le logo du pied de
page droit est supprimé. `public/css/auth-panel.css` définit la présentation du
formulaire et du sélecteur, tandis que `auth-login.css` conserve la composition
photographique et les styles de base. Le composant `language-switcher` utilise
un menu HTML natif et des formulaires qui fonctionnent sans JavaScript.

## Vérification

Les tests HTTP couvrent la sélection FR/EN, la persistance en session/cookie,
le retour après déconnexion, les valeurs invalides, la protection CSRF, les
redirections internes, les erreurs de validation et la récupération d’une
session expirée. Les tests existants de connexion et de création de compte
doivent continuer de réussir.

```sh
php artisan test --compact
vendor/bin/pint --test
npm run build
```

Références : [Laravel Localization](https://laravel.com/docs/12.x/localization),
[Laravel Lang](https://laravel-lang.com/packages-lang.html).

## Validation dynamique de la connexion

`auth-login.js` active la validation sous les champs à leur sortie, puis pendant
la correction. Les messages proviennent des catalogues Laravel FR/EN. Aucune
vérification d’existence du compte ni requête d’authentification n’est envoyée
pendant la saisie. Le mot de passe est uniquement contrôlé comme champ requis,
sans appliquer de nouvelles règles de création de mot de passe à la connexion.

L’envoi utilise `fetch` vers le contrôleur Fortify existant, avec le jeton CSRF
du formulaire et une réponse JSON. La réponse réussie fournit la destination
prévue en session. Les erreurs 422 restent sous les champs ; les erreurs 429,
419, réseau et serveur apparaissent dans un message accessible. Une session
expirée propose un lien pour actualiser le formulaire, sans renvoi automatique
du mot de passe. Sans JavaScript, le formulaire POST classique reste utilisable.

## Langues dans la navbar

La variante `compact` du composant `language-switcher` affiche le code FR/EN
dans le tableau de bord, sans changer la présentation du login. Les formulaires
de changement de langue restent les mêmes. Les styles de la navbar sont isolés
dans `topbar.css` et sa gestion du menu dans `topbar.js`.

## Langues dans le menu latéral

Le menu latéral reprend la langue de la session pour ses rubriques, la navigation,
l’aide et les libellés d’accessibilité. Le bloc d’espace de démonstration a été
retiré du menu ; les indications de démonstration des écrans restent présentes. Ces traductions ne constituent pas une traduction
complète des écrans métier simulés.

La navigation hiérarchique (Utilisateurs, Flottes, Dashcams, Départements, Carte,
Alertes, Rapports) et ses pages de préparation disposent de traductions FR/EN.
Leurs titres et descriptions sont transmis au JavaScript par les attributs de la
vue Blade. Les descriptions des aperçus historiques restent majoritairement en
français ; la traduction complète du contenu métier reste à poursuivre.

Les graphiques du dashboard (titres, séries, périodes, légendes et tableau
alternatif) sont traduits en FR/EN. Le contrôleur fournit les libellés traduits
dans un JSON encodé pour son insertion dans Blade. Les valeurs numériques des
infobulles utilisent `Intl.NumberFormat` avec la langue de la session.

La carte et ses contrôles, le sélecteur de véhicule, les statuts fictifs et les
messages de chargement/indisponibilité sont traduits en FR/EN. Le script Google
reçoit la langue de la session ; changer de langue recharge la page et initialise
donc une nouvelle carte. L’aperçu caméra conserve ses limites de démonstration.

## Gestion des utilisateurs

Les catalogues `lang/fr/users.php` et `lang/en/users.php` traduisent le tableau,
les formulaires, l’historique, la pagination et les retours des opérations. La
validation Laravel utilise les attributs traduits du formulaire. Les messages
dynamiques sont fournis par le catalogue de la session dans un JSON `Js::encode`.
Les libellés de compte et de flotte saisis ne sont pas traduits automatiquement.
