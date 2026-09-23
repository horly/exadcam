# Historique du projet EXADCAM

Dernière mise à jour : **21 septembre 2026** — fuseau Africa/Kinshasa.

Ce journal suit les décisions, les réalisations et les vérifications du projet.
Il a été créé le 16 septembre 2026. Les premières entrées sont une reconstitution
des échanges, des documents techniques, des fichiers livrés et des résultats de
vérification disponibles ; elles ne constituent pas un journal saisi au jour le jour.
Les travaux préparatoires sans date précise sont regroupés comme tels.

## Règles de suivi

- Ajouter une entrée après chaque lot significatif, dans l’ordre chronologique.
- Indiquer la date, la demande, le résultat, les fichiers ou documents concernés,
  les contrôles réellement exécutés et les limites restantes.
- Distinguer une décision, un prototype, une fonctionnalité livrée et un essai
  effectué sur un service tiers. Une fonction envisagée n’est pas une livraison.
- Conserver les anciennes étapes ; documenter les corrections dans une nouvelle
  entrée et actualiser la synthèse de l’état courant.
- Ne consigner aucun mot de passe, jeton, clé d’application ou contenu de `.env`.
- Mettre à jour les documents spécialisés lorsque leur état change. Ce journal
  conserve l’historique ; [project-context.md](project-context.md) décrit le cadre courant.

## État courant au 21 septembre 2026

| Domaine | État vérifié |
| --- | --- |
| Socle web | Laravel 12, Blade, Bootstrap local ; projet indépendant d’EXAD Tracking |
| Identité | Thème bleu nuit, logo EXAD, visuel dashcam, police Manrope locale, favicon dédié |
| Connexion | Laravel Fortify, sessions, mémorisation, limitation des tentatives, déconnexion |
| Validation du login | Messages dynamiques sous les champs et envoi asynchrone ; erreurs FR/EN |
| Base | MySQL/MariaDB `exadcam` ; schéma utilisateurs et accès aligné sur la référence |
| Superadmin | Compte demandé provisionné ; connexion réelle vérifiée |
| Autorisations | Rôles, droits JSON, statut actif, rattachements aux flottes et middleware disponibles |
| Navigation | Barre corporate, menu latéral masquable, plein écran, préférences, notifications de démonstration, langue et compte |
| Tableau de bord | Prototype interactif avec données fictives ; aucun flux GPS/vidéo EXADCAM branché |
| Administration métier | Gestion des utilisateurs livrée le 16 septembre ; clients, flottes et équipements à développer |
| Mobile | Prévu après les parcours web ; aucune application mobile EXADCAM livrée |
| Déploiement | Serveur Apache/MariaDB prêt, exadcam.app en HTTPS avec page statique ; application Laravel non déployée |

## Préparation du projet — avant le socle web

**Objectif :** comprendre la dashcam ESTON ES500-603 / AT603D, ses interfaces de
configuration et les conditions de raccordement à une plateforme propre.

- Étude du manuel et inspection des interfaces locales : état système, GPS,
  plateforme, réseau mobile/APN, alimentation, WLAN, paramètres JT808, caméras,
  enregistrement, flux principal/secondaire et fonctions d’aide à la conduite.
- Accès administrateur local obtenu après clarification de la différence entre
  le mot de passe Wi-Fi et celui de l’administration. Les secrets ne sont pas
  reproduits dans ce journal.
- Viidure essayé ; les réglages nécessaires au raccordement à un serveur propre
  ont été recherchés dans le configurateur avancé.
- Rapports PDF, dont une version illustrée de captures, et analyse de GPS51 produits.
- Distinction établie entre direct vidéo, enregistrement sur carte mémoire,
  relecture distante et stockage cloud. Un lecteur vidéo cloud ne prouve pas
  l’existence d’un archivage permanent côté serveur.
- Cadrage d’une plateforme séparée d’EXAD Tracking, avec une future application
  commune Android/iPhone. Objectif discuté : trois mois de développement et plus
  de 300 dashcams au lancement, à confirmer par des essais de charge.
- Préparation d’un budget annuel d’outillage et d’infrastructure dans un document
  Word, avec totaux et Codemagic explicitement optionnel. Ces enveloppes ne
  constituent ni des ressources déjà achetées ni des services déjà déployés.

**Pièces préparatoires :** `Rapport_analyse_Dashcam_ES500-603_illustre.pdf`,
`Analyse_GPS51_ESTON.pdf`, `Justification_budget_Dashcam.docx` et notes techniques,
conservés hors du dépôt dans le dossier de travail `DASHCAM`.

## 14 septembre 2026 — Connexion mobile et vidéo sur GPS51

**Demande :** raccorder l’équipement au réseau Africell, vérifier sa présence en
ligne et accéder aux caméras depuis la plateforme du fournisseur.

- APN local réglé sur `africellnet`, PDP IPv4, avec relecture de la configuration.
- Protocole du serveur principal passé de 2013 à 2019 ; paramètres observés :
  `d.gps51.com:808`. L’équipement est ensuite apparu en ligne sur GPS51.
- Profil cloud passé de `JT808` à `JH114 — Active safety using jt808 1078`, puis
  vérifié après réouverture de la fiche.
- Réception distante des deux canaux CH1 et CH2 constatée. Le canal CH1 présentait
  une image sombre et un débit intermittent.

**Preuves :** notes `diagnostic-africell-gps51.md`, `gps51-demande-profil-video.md`
et captures associées dans le dossier de recherche `DASHCAM/analysis`.

**Limites :** cet essai concerne GPS51, pas le futur serveur EXADCAM. Il ne valide
ni la stabilité de longue durée, ni l’audio, la relecture des archives, les alertes
ADAS/DMS ou les pièces jointes. La correspondance exacte du profil JH114 avec
le matériel reste distincte du constat de fonctionnement du direct.

## 15 septembre 2026 — Socle Laravel et méthode de développement

**Demande :** travailler dans `D:\App\Codex\exadcam`, sur le web en premier,
avec Bootstrap sans CDN, en reprenant la méthode d’EXAD Tracking.

- Inspection en lecture seule de `D:\App\Codex\exad-tracking`.
- Adoption de Blade, layouts et fragments partagés, contrôleurs Laravel,
  modèles Eloquent et fichiers CSS/JavaScript séparés.
- Bootstrap 5.3.8 installé via Composer et publié dans `public/vendor/bootstrap`.
  Publication reproductible via `composer assets:publish` et les scripts Composer.
- Tailwind exclu de l’interface et de la compilation ; Vite conservé pour les bundles.
- Répartition cible : Laravel pour les parcours métier et les accès ; Node.js
  pour l’écoute GPS/commandes ; adaptateur JT1078 et service média pour la vidéo.
  Cette répartition est une orientation d’architecture, pas une intégration livrée.

**Références :** [README](../README.md), [contexte du projet](project-context.md),
`composer.json`, `scripts/publish-assets.php`, `resources/views/layouts`.

## 15 septembre 2026 — Tableau de bord de démonstration

**Demande :** améliorer la présentation avant de développer les parcours métier.

- Création puis amélioration du tableau de bord : navigation, indicateurs,
  liste de véhicules, recherche/filtres, événements et aperçus caméra.
- Six véhicules et trois événements fictifs ; plan illustratif et vues de démonstration.
- Signalement explicite du caractère simulé de l’interface.

**Fichiers :** `app/Http/Controllers/DashboardPreviewController.php`, vues et assets
du tableau de bord. Les données présentées ne sont pas enregistrées comme données métier.

## 15 septembre 2026 — Connexion Fortify et identité corporate

**Demande :** donner la priorité au login, obtenir un rendu corporate et mettre
la dashcam en avant tout en conservant la palette approuvée.

- Laravel Fortify avec formulaire Blade personnalisé, connexion/déconnexion,
  mémorisation de session et limitation des tentatives ; tableau de bord protégé.
- Routes Fortify limitées aux parcours implémentés. Inscription publique désactivée.
- Protection CSRF, absence de mise en cache des pages sensibles et cookie de session propre.
- Commande interactive `app:create-user`, sans compte de démonstration ajouté aux seeders.
- Création de photographies illustratives par IA, conversion WebP et distribution locale.
  La version retenue place une dashcam au premier plan.
- Intégration du logo officiel fourni par EXAD, déclinaisons claire/bleu nuit
  conservant le dessin original. Suppression du logo en bas du panneau blanc.
- Refonte du panneau de connexion, de la hiérarchie visuelle et de l’aide à l’accès.
- Adoption de Manrope variable, fichiers WOFF2 et licence servis localement.

**Références :** [visuels initiaux](design-assets.md), [identité EXAD](exad-identity.md),
[typographie](typography.md), `resources/views/auth/login.blade.php`,
`resources/views/layouts/auth.blade.php`, `public/css/auth-login.css`,
`public/css/auth-panel.css` et `public/js/auth-login.js`.

**Limites :** les photographies sont illustratives, pas une reproduction certifiée
de l’ES500-603. La récupération du mot de passe par e-mail reste à implémenter.

## 15 septembre 2026 — Français et anglais

- Sélecteur de langue fonctionnel sur la connexion ; route POST avec protection CSRF.
- Langues autorisées FR/EN, préférence en session et cookie chiffré persistant.
- Publication des catalogues Laravel Lang et traduction du formulaire, de l’aide,
  des erreurs et du parcours de session expirée.
- Maintien des messages personnalisés et distribution locale des traductions.

**Vérifications disponibles à cette étape :** tests des langues et de la connexion,
contrôles visuels responsive, formatage PHP et compilation des assets.

**Référence :** [localisation](localization.md). Le tableau de bord de démonstration
reste en français ; les futurs modules devront être internationalisés.

## 16 septembre 2026 — Favicon

**Demande :** proposer puis appliquer un favicon adapté au thème.

- Proposition approuvée : pictogramme de dashcam blanc et bleu sur fond bleu nuit.
- Installation des versions SVG, ICO 16/32/48 px et icône Apple 180 px.
- Inclusion commune dans les layouts de connexion et de tableau de bord.

**Fichiers :** `public/images/favicon.svg`, `public/favicon.ico`,
`public/apple-touch-icon.png`, `resources/views/partials/favicons.blade.php`.

**Vérifications :** ressources accessibles en HTTP 200 et contrôles de rendu des vues.

## 16 septembre 2026 — Base exadcam, utilisateurs et autorisations

**Demande :** utiliser la base créée `exadcam`, reprendre les éléments utilisateurs
et autorisations d’`exad_tracking`, puis créer le Superadmin demandé.

- Inspection du schéma réel de la base de référence en lecture seule.
- Bascule de la configuration locale vers MySQL/MariaDB `exadcam`, sans changer
  la clé d’application. La base cible était vide et l’ancienne SQLite sans utilisateur.
- Création des migrations pour `subscriptions`, `fleets`, `fleet_user`,
  `user_login_histories` et l’extension de `users`.
- Alignement de `users` sur 21 colonnes ; types, ordre, valeurs par défaut,
  index et clés étrangères comparés aux structures de référence.
- Reprise des rôles `superadmin`, `admin`, `user` et des droits JSON.
  La référence ne possède pas de tables de rôles/permissions séparées.
- Mise en place des relations, contrôles de statut actif, portée de visibilité
  des flottes et middleware d’autorisation. Un droit inconnu est refusé.
- Enregistrement des connexions réussies et exclusion des secrets des données sérialisées.
- Provisionnement explicite de `superadmin@erp.loc`, actif et global, avec mot de
  passe haché. Aucun secret ni compte initial dans les seeders.
- Ajout de l’option de rôle à `app:create-user` et mise à jour des documents techniques.

**Vérifications :** migrations appliquées, comparaison des schémas, connexion
réelle du Superadmin, **80 tests réussis / 308 assertions** et contrôle Pint réussi
à la fin de ce lot. Les tests utilisent SQLite en mémoire, pas la base métier.

**Référence :** [base et accès](database-and-access.md).

**Différence volontaire :** `logged_in_at` conserve la date de connexion et ne
devient pas automatiquement la date d’une modification ultérieure de la ligne.
Les écrans d’administration, passkeys, sessions mobiles et parcours 2FA ne sont
pas encore livrés, même si les colonnes 2FA existent pour compatibilité du schéma.

## 16 septembre 2026 — Confirmation de l’utilisation de Fortify

**Demande :** utiliser Fortify pour le login.

- Vérification des routes et du contrôleur : le formulaire utilisait déjà Fortify.
- Confirmation de la chaîne d’authentification, de la session et de la limitation
  des tentatives ; aucune réinstallation ou réécriture nécessaire.

**Vérification :** **23 tests réussis / 109 assertions** sur le contrôleur de connexion.

## 16 septembre 2026 — Validation dynamique du formulaire

**Demande :** remplacer les bulles de validation natives par une validation dynamique.

- Messages sous les champs à leur sortie puis actualisés pendant la correction.
- Vérification locale des champs obligatoires, du format d’e-mail et des longueurs ;
  Fortify conserve la validation définitive et l’authentification côté serveur.
- Envoi asynchrone avec `fetch` et CSRF vers le contrôleur Fortify existant.
  Aucune vérification du compte n’est envoyée pendant la saisie.
- Erreurs d’identifiants affichées sans rechargement, indicateur de chargement
  et protection contre les doubles soumissions.
- Réponse JSON de succès enrichie avec la destination prévue en session ;
  redirection du navigateur limitée à la même origine.
- Gestion des erreurs 422, 429, 419, réseau et serveur, avec lien d’actualisation
  pour une session expirée. Aucun renvoi automatique du mot de passe.
- Messages FR/EN, attributs d’accessibilité et fonctionnement POST classique
  conservé lorsque JavaScript est indisponible.

**Fichiers principaux :** `public/js/auth-login.js`, `public/css/auth-panel.css`,
`resources/views/auth/login.blade.php`, `resources/views/layouts/auth.blade.php`,
`app/Http/Responses/LoginResponse.php`, `lang/en.json`, tests de connexion.

**Vérifications :** **53 tests ciblés réussis / 248 assertions** sur la connexion,
les accès et les langues ; syntaxe JavaScript et Pint validés. Contrôles réels
dans le navigateur : champs vides, e-mail incorrect, correction immédiate,
mauvais identifiants, FR/EN, affichage mobile sans débordement horizontal,
connexion Superadmin réussie et session expirée après déconnexion dans un autre onglet.
Les messages réseau/serveur sont implémentés, sans simulation navigateur de panne
réseau ou d’erreur 500 lors de ce lot.

Les **53 tests** correspondent au périmètre rejoué après cette modification,
pas à une nouvelle exécution de la suite complète de **80 tests** du lot précédent.

**Référence :** [validation et localisation](localization.md).

## 16 septembre 2026 — Centralisation de l’historique

**Demande :** disposer de `docs/project-history.md` pour suivre tout le travail.

- Création de ce journal et reconstitution des étapes documentées.
- Liens ajoutés depuis le README et le contexte du projet.
- Consigne de mise à jour après chaque lot ajoutée au fichier `AGENTS.md` du projet.
- Actualisation du contexte pour mentionner la validation dynamique et les
  informations réellement persistées.

**Vérifications :** relecture de cohérence et contrôle des liens locaux.
Modification documentaire uniquement ; aucun nouveau test applicatif exécuté pour ce lot.

## 16 septembre 2026 — Navbar conforme à la référence visuelle

**Demande :** reprendre la disposition de la navbar fournie en image.

- Titre de page et fil d’Ariane à gauche, puis boutons ronds plein écran,
  préférences et notifications ; sélecteur de langue et compte en forme de pilule.
- Fond clair, bordure fine, ombres discrètes, palette EXAD et police Manrope conservés.
- Flèche pour masquer/rétablir le menu latéral sur ordinateur ; menu Bootstrap
  offcanvas sur mobile. Disposition des actions sur une seconde ligne sur petit écran.
- Plein écran fonctionnel avec libellé et icône adaptés à son état ; message de
  retour si le navigateur refuse cette fonction.
- Bouton paramètres relié aux préférences d’affichage existantes ; cloche reliée
  à la vue des événements. Le badge montre les **3 événements fictifs** de l’aperçu,
  et non une quantité artificielle de notifications réelles.
- Composant de langues réutilisé avec une variante compacte FR/EN ; choix
  enregistré par la route POST/CSRF existante. Barre et menus traduits ; le contenu
  métier du tableau de bord reste encore majoritairement en français.
- Initiale et nom issus du compte connecté, menu d’identité et déconnexion Fortify conservés.
- Titre et fil d’Ariane actualisés lors du passage entre les vues de démonstration.
- Champ de recherche retiré de la navbar pour respecter la composition demandée ;
  recherche conservée dans la liste des véhicules, avec raccourci `/` adapté.

**Fichiers :** `resources/views/partials/topbar.blade.php`,
`resources/views/components/language-switcher.blade.php`, `resources/views/layouts/app.blade.php`,
`public/css/topbar.css`, `public/js/topbar.js`, `public/js/app.js`,
`public/images/icons.svg` et `lang/en.json`.

**Vérifications :** **42 tests ciblés réussis / 215 assertions** sur la connexion
et les langues ; syntaxe des deux fichiers JavaScript vérifiée. Essais navigateur
aux largeurs 1680, 1024, 390 et 320 px : absence de débordement horizontal,
contrôles visibles, masquage/rétablissement du menu, entrée/sortie du plein écran,
préférences et affichage compact, notifications et fil d’Ariane, sélection FR/EN,
menu du compte, menu mobile et recherche d’un véhicule. Aucun avertissement ou
erreur JavaScript relevé. Les essais PHP ont utilisé la base de test isolée.

**Limites :** le badge reste lié à la démonstration ; aucun service de notifications
réelles n’a été ajouté. Les préférences d’affichage et le menu replié concernent
l’aperçu ouvert et ne sont pas encore enregistrés dans le profil utilisateur.

## 16 septembre 2026 — Réduction de la navbar

**Retour utilisateur :** la navbar est trop grande.

- Hauteur sur ordinateur réduite de 126 à 76 px ; boutons d’action de 54 à 38 px.
- Titre réduit de 25 à 20 px, avatar de 40 à 30 px, espacements et ombres allégés.
- Menus de langue et de compte ramenés à 40 px ; contrôles mobiles conservés à
  42 px, avec moins d’espace vertical entre les deux lignes.
- Même disposition, thème et comportements. Version de l’asset CSS actualisée.

**Fichiers :** `public/css/topbar.css`, `resources/views/layouts/app.blade.php`.

**Vérifications :** contrôle visuel dans le navigateur à 1440 et contrôle des
dimensions à 320 px ; hauteur de 76 px confirmée sur ordinateur, absence de
débordement horizontal et boutons mobiles entièrement dans la zone visible.
Modification CSS uniquement hors version d’asset ; aucun test PHP supplémentaire
exécuté pour ce lot.

## 16 septembre 2026 — Refonte du menu latéral

**Demande :** améliorer le menu montré dans la capture utilisateur.

- Présentation bleu nuit harmonisée avec le login ; état actif bleu avec repère
  latéral clair, icônes homogènes et compteurs discrets.
- Largeur de 232 px sur ordinateur, en-tête aligné sur la navbar de 76 px et
  logo officiel conservé. Espace de démonstration présenté dans un bloc plus compact.
- Navigation organisée sous « Supervision » et « Préférences » ; intitulé
  « Tableau de bord » cohérent avec la navbar, sans ajout de modules fictivement opérationnels.
- Bloc promotionnel inférieur remplacé par un accès compact au guide existant.
- Logo et pied de menu restent visibles ; seule la liste défile lorsque la
  hauteur disponible est insuffisante. Espacements ajustés pour éviter un défilement
  inutile à 320 × 568 px. Menu mobile Bootstrap conservé et refermé après navigation.
- Libellés du menu traduits en FR/EN. Le contenu métier de démonstration reste
  majoritairement en français.

**Fichiers :** `resources/views/partials/sidebar.blade.php`,
`public/css/sidebar.css`, `resources/views/layouts/app.blade.php`, `lang/en.json`.

**Vérifications :** **42 tests ciblés réussis / 215 assertions** sur la connexion
et les langues. Essais navigateur à 1440 × 900, 1024 × 600, 320 × 568 et 1024 × 400 :
rendu, hauteur du menu, pied visible, défilement limité à la liste, navigation des
véhicules et du centre vidéo, état actif/fil d’Ariane, repli du menu sur ordinateur,
fermeture après sélection sur mobile, guide et préférences accessibles. Libellés
anglais vérifiés sans débordement du bloc de contexte. Aucun avertissement ou
erreur JavaScript relevé. Aucun nouveau test dédié à l’apparence ajouté.

## 16 septembre 2026 — Suppression du bloc Espace EXAD dans le menu

**Demande :** retirer le bloc « Espace EXAD / Démonstration » montré en capture.

- Bloc et styles dédiés supprimés ; la rubrique Supervision suit directement
  l’en-tête du logo, avec un espacement adapté.
- Le reste de la navigation et les indications de démonstration du tableau de
  bord sont conservés.

**Fichiers :** `resources/views/partials/sidebar.blade.php`, `public/css/sidebar.css`,
`resources/views/layouts/app.blade.php` (version du CSS), documents de contexte/langues.
**Vérification :** contrôle du menu rendu après actualisation dans le navigateur.
Aucun test applicatif supplémentaire pour cette suppression de présentation.

## 16 septembre 2026 — Arborescence du menu avec Dashcams

**Demande :** reprendre l’organisation du menu de référence, remplacer Traceurs
par Dashcams et exclure les rubriques barrées de la capture.

- Entrées Tableau de bord, Utilisateurs, Carte, Centre vidéo, Alertes et Rapports.
- Groupe Flottes repliable : Flottes, Véhicules, Dashcams, Départements.
- Conducteurs, Garages, Entretien et Événements véhicules ne sont pas ajoutés.
- Thème bleu nuit, largeur et navbar compactes conservés ; icônes dédiées,
  indentation et ligne de liaison pour les sous-rubriques. Le bloc Espace EXAD
  précédemment retiré reste absent.
- Ouverture automatique du groupe pour une sous-rubrique active, titre et fil
  d’Ariane synchronisés ; fermeture du menu mobile après sélection.
- Carte utilise le plan illustratif existant ; Véhicules, Centre vidéo et Alertes
  conservent les aperçus de démonstration. Utilisateurs, Flottes, Dashcams,
  Départements et Rapports ouvrent des pages explicitement « En préparation ».
- Navigation et pages de préparation traduites en français/anglais.

**Fichiers :** fragments sidebar/topbar/navigation-modules, layout app, vue welcome,
`public/css/sidebar.css`, `public/css/navigation.css`, `public/js/app.js`,
`public/images/icons.svg`, `lang/en.json`, documents de contexte et de langues.

**Vérifications :** syntaxe JavaScript vérifiée ; **42 tests ciblés réussis /
215 assertions** (connexion et langues, base isolée). Vérification navigateur de
chaque rubrique à 1440 × 900, repli du groupe et accès direct à Dashcams,
traductions anglaises puis retour au français. Navigation mobile à 390 × 844 et
320 × 568 : fermeture après sélection, absence de débordement horizontal,
défilement interne et pied de menu accessible. Aucun avertissement ou erreur
JavaScript relevé. Aucun nouveau test miroir du menu ajouté.

**Limites :** cette livraison prépare la navigation, sans livrer les CRUD ou des
rapports réels. La carte reste illustrative et les aperçus métier restent simulés.
Aucune modification de données métier ni de la base EXAD Tracking.

## 16 septembre 2026 — Sous-menu Flottes replié au chargement

**Demande :** réduire le sous-menu Flottes au chargement de la page.

- Suppression de l’ouverture initiale du groupe, y compris après actualisation
  d’une sous-rubrique ; ouverture au clic conservée.
- État actif et ouverture lors de la navigation préservés ; version du JS actualisée.

**Fichiers :** `resources/views/partials/sidebar.blade.php`, `public/js/app.js`,
`resources/views/layouts/app.blade.php`, document de contexte.
**Vérifications :** syntaxe JavaScript, contrôle navigateur après rechargement du
Tableau de bord, ouverture au clic et actualisation de Dashcams. Aucun test PHP
supplémentaire pour cet ajustement de présentation.

## 16 septembre 2026 — Dashboard bleu et graphiques ApexCharts

**Demande :** améliorer le dashboard, harmoniser les boutons avec le thème et
reprendre la bibliothèque de graphiques utilisée dans EXAD Tracking.

- Référence consultée en lecture seule : `akaunting/laravel-apexcharts` 4.0.0,
  bibliothèque JavaScript ApexCharts 3.35.1, rendu par un fichier JS dédié.
- Même package installé et verrouillé dans Composer ; script ApexCharts et
  licence publiés dans `public/vendor/apexcharts`, sans CDN. Le fichier JavaScript
  publié est identique à celui du projet de référence (SHA-256 comparé).
- Publication reproductible de Bootstrap et ApexCharts via `composer assets:publish`
  et les scripts Composer existants. Pas de copie de données EXAD Tracking.
- Boutons principaux bleu nuit `#203d65`, états survol/focus/actif assortis ; filtres,
  actions secondaires, marqueurs, illustration de trajet et bandeau de démo harmonisés.
- Cartes d’indicateurs affinées et accès « Voir la carte » ajouté.
- Courbe d’activité avec deux séries (en ligne/en mouvement), périodes 24 h et
  7 jours, tableau de valeurs consultable et traduction FR/EN.
- Anneau d’état des dashcams calculé depuis la même collection fictive que les
  indicateurs : deux en mouvement, deux à l’arrêt/en ligne, deux hors ligne.
- Graphiques initialisés uniquement sur la vue Tableau de bord, adaptés aux
  redimensionnements et aux changements de rubrique ; animations réduites selon
  la préférence système. Résumé textuel et tableau accessibles sans lire le SVG.
- Sous-menu Flottes conservé replié au chargement.

**Fichiers :** `composer.json`, `composer.lock`, `scripts/publish-assets.php`,
`app/Http/Controllers/DashboardPreviewController.php`, fragment `dashboard-charts`,
vue welcome/layout app, `public/css/dashboard.css`, `public/js/dashboard-charts.js`,
`public/js/app.js`, ressources ApexCharts, illustration de carte et `lang/en.json`.

**Vérifications :** syntaxe PHP/JS, Pint réussi sur les deux fichiers PHP modifiés,
publication des assets et découverte du provider. **Suite complète : 84 tests
réussis / 340 assertions**, base de test isolée. Composer valide les fichiers avec
avertissements informatifs sur les versions exactes de Bootstrap et ApexCharts ;
l’installation ne signale aucun avis de vulnérabilité.
Contrôles navigateur à 1440, 1024, 390 et 320 px : rendu de deux graphiques, absence
de débordement horizontal, sélection 24 h/7 jours et tableau associé, navigation
Carte/Vidéo/Tableau de bord, chargement différé après accès direct à Vidéo et
redimensionnement après masquage du menu. Graphiques et libellés anglais contrôlés,
puis retour au français. Aucun avertissement ou erreur JavaScript relevé.

**Limites :** les courbes représentent une journée/semaine type simulée ; aucun
historique réel ni flux dashcam n’est raccordé. Les modules métier restent à livrer.

## 16 septembre 2026 — Configuration Google Maps et carte interactive

**Demande :** utiliser la nouvelle clé Google Maps fournie pour EXADCAM, en local
puis en production lorsque le domaine sera défini.

- Clé enregistrée dans le `.env` local exclu de Git, jamais dans les documents,
  tests ou fichiers source. Configuration Laravel via `services.google_maps` ;
  `.env.example` contient uniquement les variables vides et le Map ID de test.
- Fond Google Maps réel à la place du plan illustratif ; six coordonnées fictives
  à Kinshasa, clairement signalées comme démonstration. Pas de données GPS reçues.
- Marqueurs avancés sélectionnables et accessibles, couleurs bleu du thème/vert/
  gris selon sélection et état ; sélecteur de véhicule, zoom, dézoom, cadrage de
  flotte et accès à l’aperçu caméra existant pour le véhicule sélectionné.
- Chargement différé lorsque la carte devient visible, instance unique réutilisée
  entre Tableau de bord et Carte. Aucun appel Geocoding, Places, Routes ou Roads.
  Aucun rafraîchissement périodique des positions ni rechargement à leur sélection.
- Messages en absence de clé ou en cas d’échec ; contrôles et statuts traduits
  en français/anglais, langue transmise à l’API. Attribution Google conservée.
- API chargée officiellement depuis Google ; bibliothèques d’interface et polices
  locales conservées. Aucun package Laravel supplémentaire requis.
- Événement des marqueurs adapté à `gmp-click` après un avertissement de Google
  sur l’ancien événement `click` durant la première vérification.

**Fichiers :** `config/services.php`, `.env.example`, contrôleur DashboardPreview,
fragment `dashboard-map`, vues welcome/layout app/preview-modals, fichiers
`public/js/google-map.js`, `public/css/google-map.css`, `public/js/app.js`,
`lang/en.json`, `phpunit.xml`, `DashboardMapTest`, README et documentation associée.

**Vérifications :** syntaxe des scripts JavaScript et contrôle Pint réussis.
**Suite complète : 87 tests réussis / 412 assertions**, base SQLite isolée et clé
Maps réelle exclue des tests. Tests ajoutés pour l’accès invité, l’absence de clé
et le contenu de la configuration avec des valeurs factices.
Chargement Google réel validé sur `http://127.0.0.1:8011/` ; sélection de Toyota
RAV4 depuis son marqueur, ouverture de son aperçu caméra, zoom/dézoom, cadrage de
flotte et navigation Tableau de bord/Centre vidéo/Carte vérifiés. Une seule balise
de chargement de l’API et un seul conteneur conservés, sélection préservée.
Affichage contrôlé sur ordinateur et à 390/320 px, sans débordement horizontal.

**Limites :** positions et statuts restent fictifs, aucun flux caméra raccordé.
Le chargement local réussi ne valide pas les restrictions, quotas ni paramètres
de facturation du compte Google Cloud. Domaine de production à ajouter aux sites
autorisés et Map ID propre au projet à configurer avant déploiement. La même clé
est utilisable selon les restrictions définies ; elle est nécessairement visible
dans le navigateur avec Maps JavaScript. Voir `docs/google-maps.md`.

## 16 septembre 2026 — Gestion des utilisateurs comme dans EXAD Tracking

**Demande :** remplacer la rubrique en préparation par une page disposant des
mêmes fonctions que la gestion des utilisateurs d’EXAD Tracking.

- Lecture seule du contrôleur, des vues, scripts, catalogues et tests de référence.
  Reprise de la liste, recherche, tri, pagination, modales de création/édition,
  suppression confirmée, mot de passe et confirmation, rôle, flotte et permissions.
- Écran corporate bleu avec indicateurs calculés sur le périmètre autorisé,
  tableau des comptes réels, avatars, rôles, statuts et accès à l’historique.
- Actualisation AJAX, cinq lignes par défaut et choix 5/10/25/50. Historique
  appareil/IP/date avec recherche, tri et pagination serveur, sans charger toutes
  les connexions en mémoire. Traductions FR/EN et validation sous les champs.
- `UserPolicy` : superadmin global, admin limité aux utilisateurs simples de sa
  flotte, aucun accès pour un utilisateur simple. Admin sans flotte isolé des
  comptes non affectés. Comptes superadmin protégés contre création/édition/suppression.
- `SaveUserRequest` : validations serveur, rôle non extensible à superadmin,
  flotte active obligatoire, permissions autorisées, e-mail normalisé et unique,
  mot de passe de 12 caractères minimum et 72 octets maximum avec confirmation.
- Transactions et synchronisation du pivot viewer/manager ; mots de passe hachés.
  Modification des accès : jeton renouvelé et sessions de base de données révoquées.
  Protection CSRF, champs sensibles non assignables et échappement du HTML.
- Aucun package ajouté, aucune migration ni copie de données EXAD Tracking.
  Les utilisateurs s’affichent désormais depuis EXADCAM ; les autres aperçus
  métier du dashboard restent des démonstrations.

**Fichiers :** contrôleurs User/UserLoginHistory, requête SaveUserRequest, policy
UserPolicy, routes web, vues `users/*`, sidebar/navigation-modules/welcome,
`public/css/users.css`, `public/js/users.js`, icônes locales, catalogues FR/EN,
tests de contrôleurs, README et documentation spécialisée.

**Vérifications :** 41 premiers tests ciblés réussis (190 assertions), puis ajout
de cas de permissions, rôle admin et CSRF. **Suite complète finale : 131 tests
réussis / 615 assertions**, dont 44 cas ajoutés, sous SQLite isolé. Pint réussi et
syntaxe JavaScript vérifiée. Affichage navigateur réel du tableau, protection du
superadmin, formulaire de création, absence de flotte active, validation dynamique
d’e-mail et historique de connexion vérifiés ; aucune erreur JavaScript relevée.

**Limites :** la base réelle n’a encore aucune flotte active. La création de
comptes exige cette affectation comme dans la référence ; le formulaire explique
le prérequis. Les permissions reprennent le catalogue existant, sans livrer les
modules matériels ni les futurs droits vidéo. L’accès navigateur à une instance
de test séparée (8012, SQLite dédiée) a été refusé par l’approbation automatique
après expiration de son délai ; aucun contournement tenté, serveur arrêté.
Le cycle CRUD est couvert par les tests HTTP ; sa vérification navigateur complète
reste à effectuer. Aucun compte de test ajouté dans la base EXADCAM réelle.

## 21 septembre 2026 — Compte SSH d’administration du serveur

**Demande :** se connecter au serveur communiqué et créer le compte Linux
`exad-cam`, qui servira aux prochaines connexions d’administration.

- Connexion SSH au serveur `62.171.190.15`, port 22, avec l’accès root fourni.
- Système observé : Ubuntu 24.04.5 LTS, hôte `vmi3599783`. La bannière du
  serveur indique Contabo ; les offres Netcup discutées auparavant ne prouvent
  pas l’hébergeur de ce serveur effectivement communiqué.
- Création de `exad-cam`, UID/GID 1001, répertoire `/home/exad-cam`, ajout au
  groupe `sudo`. Mot de passe demandé défini via une saisie masquée, sans copie
  dans les fichiers du projet ni dans la documentation.
- Connexion SSH indépendante réussie sous `exad-cam` et validation réelle de
  `sudo` avec son mot de passe ; `sudo -n id -un` retourne `root` après validation.
- Identité, groupes et répertoire personnel vérifiés. Configuration sudoers
  existante valide (`visudo -c`). Sessions de vérification fermées ensuite.

**Documents :** `docs/project-history.md` et `docs/project-context.md`.

**Limites :** préparation du compte d’administration uniquement. Aucun déploiement
Laravel/Node.js/vidéo, migration de base, configuration DNS/TLS, sauvegarde,
réplication, changement de pare-feu ni redémarrage réalisé dans cette intervention.
Le domaine annoncé est `exadcam.app` ; sa résolution et son HTTPS ne sont pas
vérifiés. Connexion SSH par mot de passe ; aucun accès par clé ajouté. Accès root
et configuration SSH existante conservés. Aucun test applicatif requis ou exécuté.

## 21 septembre 2026 — Préparation Apache, MariaDB et HTTPS

**Demande :** installer Apache 2 et MariaDB, configurer `exadcam.app` et HTTPS,
avec OpenSSL, en administrant le serveur via le compte `exad-cam`.

- Résolution A vérifiée vers `62.171.190.15` ; aucun AAAA retourné.
- Installation depuis Ubuntu d'Apache 2.4.58, MariaDB 10.11.14, Certbot 2.9.0
  et son plugin Apache. OpenSSL 3.0.13 déjà présent et vérifié.
- Services Apache et MariaDB actifs et activés au démarrage. MariaDB limité
  à `127.0.0.1:3306`, administration par Unix socket, aucun compte anonyme.
- Hôte virtuel pour `exadcam.app`, racine `/var/www/exadcam/public`, modules
  rewrite/ssl/headers et page d'attente statique EXADCAM. Configuration Apache
  contrôlée, site par défaut désactivé et journaux dédiés.
- Certificat public Let's Encrypt émis et installé ; HTTPS actif, redirection
  HTTP 301 vers HTTPS, validité initiale jusqu'au 20 décembre 2026.
- Renouvellement automatique via `certbot.timer` et simulation de renouvellement
  réussie. Compte ACME sans adresse e-mail ; aucun secret enregistré dans le projet.
- Configurations Apache et MariaDB initiales conservées sur le serveur sous
  `/var/backups/exadcam-initial-*` pour un retour arrière local.

**Vérifications réellement exécutées :** `apache2ctl configtest` et `-S`, état
et activation des services, écoute des ports, `mariadb-admin ping`, version et
comptes MariaDB, lecture des propriétés publiques du certificat avec OpenSSL,
requêtes HTTP/HTTPS depuis le poste externe avec validation normale du certificat,
contenu de la page d'attente, `certbot renew --dry-run` réussi. Aucun test Laravel
rejoué, car le code applicatif n'a pas été modifié.

**Documents :** `docs/server-infrastructure.md`, `docs/project-history.md`,
`docs/project-context.md`. Configurations serveur détaillées dans le document dédié.

**Limites :** page d'attente uniquement, application Laravel non déployée.
PHP/Composer, base applicative et migrations, Node.js/GPS/vidéo, sauvegardes externes
et supervision restent à préparer. UFW et SSH existants non modifiés ; aucune
réplication ajoutée. Un redémarrage système déjà signalé au début de la session
n'a pas été exécuté. Le certificat concerne `exadcam.app`, sans sous-domaine www.

## 21 septembre 2026 — PHP 8.2, Composer et phpMyAdmin

**Demande :** installer PHP 8.2, les extensions nécessaires au projet, phpMyAdmin
et les comptes MariaDB applicatif et d’administration demandés.

- Vérification préalable du `composer.lock` de production : compatible PHP 8.2.
  Ajout du dépôt signé `ppa:ondrej/php` pour Ubuntu 24.04 et installation de PHP
  **8.2.33**, CLI et FPM. Apache utilise `proxy_fcgi`, sans module mod_php.
- Extensions installées : MySQL/PDO, mbstring, XML/DOM, cURL, ZIP, BCMath, Intl,
  GD, OPcache, SQLite/PDO SQLite et BZip2, en complément des modules PHP de base.
- Paramètres FPM : mémoire 256 Mo, envoi de fichiers 64 Mo, corps POST 80 Mo,
  délai 120 secondes, erreurs journalisées et non affichées, OPcache actif,
  fuseau Africa/Kinshasa et cookies de session sécurisés. CLI sans limite mémoire.
- Installation de **Composer 2.10.3** et de **phpMyAdmin 5.2.3** depuis leurs
  distributions officielles, après vérification des sommes de contrôle.
- phpMyAdmin disponible à `https://exadcam.app/phpmyadmin/`, alias limité au
  vhost HTTPS. Authentification par cookie, connexion root interdite dans
  l’interface, protection HTTP des répertoires internes et fichiers de configuration.
- Création de la base de production `exadcam`, UTF-8 `utf8mb4_unicode_ci`, vide.
  Comptes `'exad_cam_user'@'localhost'` et `'phpmyadmin'@'localhost'` avec les
  mots de passe demandés, saisis sans écho et absents des fichiers du projet.
  Chacun possède les droits sur `exadcam.*`, sans droits globaux ni GRANT OPTION.
  Le nom de connexion dans phpMyAdmin est `phpmyadmin`, sans suffixe `@localhost`.
- Stockage des préférences phpMyAdmin dans sa base dédiée, compte technique
  `exad_pma_control@localhost` séparé, limité à SELECT/INSERT/UPDATE/DELETE sur
  cette base. Secret technique généré et conservé uniquement dans la configuration
  serveur protégée `root:www-data`, mode 640.

**Contrôles exécutés :** syntaxe Apache, PHP-FPM et configuration phpMyAdmin ;
services Apache/MariaDB/FPM actifs et activés ; contrôle réel du `composer.lock`
avec `composer check-platform-reqs --lock --no-dev` réussi dans un dossier isolé ;
requête HTTPS exécutant PHP-FPM 8.2.33 et confirmant 30 extensions ; suppression
du script de contrôle dans un bloc `finally`. Connexions PDO, lectures et écritures
dans des tables temporaires réussies pour les deux comptes, dont TCP 127.0.0.1
pour le compte Laravel. Connexion HTTP authentifiée à phpMyAdmin réussie, base
`exadcam` visible, aucune alerte de stockage détectée et session de test déconnectée.
HTTP 200 externe avec validation TLS normale ; HTTP 403 pour config.inc.php,
setup, SQL interne et composer.json. Écoute MariaDB toujours locale. Base
applicative confirmée vide. Aucun test Laravel rejoué ni code applicatif modifié.

**Documents :** `docs/server-infrastructure.md`, `docs/database-and-access.md`,
`docs/project-context.md`, `docs/project-history.md`.

**Limites :** Laravel, ses dépendances applicatives et migrations ne sont pas
encore déployés. Aucun compte applicatif superadmin n’a été copié en production.
Node.js/GPS/vidéo, sauvegardes externes et supervision restent à préparer.
phpMyAdmin et Composer sont installés hors paquets Ubuntu et nécessitent un suivi
de mise à jour distinct. Aucun redémarrage système effectué.

## 21 septembre 2026 — Déploiement web et préparation GPS/vidéo

**Demande :** envoyer le projet local sur exadcam.app puis préparer le serveur
d'écoute. Refuser les appareils absents du registre, comme EXAD Tracking. Le
dernier cadrage reporte les essais matériels au lendemain ; un nouvel IMEI sera
fourni. L'ancien IMEI n'est pas enregistré et aucun configurateur n'est modifié.

**Réalisé :**

- Laravel déployé dans `/var/www/exadcam`, dépendances de production installées,
  migrations exécutées, caches reconstruits, environnement sécurisé distinct et
  compte superadmin demandé provisionné. Aucun export de base locale importé.
- Node.js 24.21.0 LTS officiel et FFmpeg Ubuntu installés. Services systemd GPS
  et vidéo sous un compte dédié, activés au démarrage avec reprise automatique.
- GPS/commandes JT808 2013 et 2019 sur TCP 7808 ; réception JT1078 H.264 sur
  TCP 1078. L'adresse et le port vidéo sont transmis via 0x9101, tandis que le
  configurateur utilise le port principal. API de coordination limitées au localhost.
- Registre Laravel des dashcams actives, alias protocolaires exacts, authentification
  JT808, contrôles des trames et persistance des positions. Aucun auto-enregistrement.
- Direct à la demande, conversion HLS sans transcodage, lecteur hls.js local,
  autorisation web, URL temporaire, arrêt/révocation et nettoyage du tampon vidéo.
- Écran Flottes → Dashcams en français/anglais, inscription et activation réservées
  au superadmin, validation dynamique, consultation vidéo préparée pour les essais.
- Sauvegardes locales avant déploiement et fichiers de configuration reproductibles.

**Fichiers principaux :** `listener/src`, `listener/test`, `deployment`,
`app/Models/Dashcam.php`, contrôleurs Dashcam/Listener, middleware AuthenticateListener,
`config/listener.php`, migration dashcam/positions, routes API/web/bootstrap,
vues/traductions Dashcams, `public/js/dashcams.js`, hls.js et publication npm,
`tests/Feature/ListenerAccessTest.php`, documents de contexte/infrastructure/base.

**Contrôles réellement exécutés :** suite Laravel locale complète : 137 tests,
643 assertions ; build Vite et publication HLS réussis ; Pint sur les fichiers PHP
ajoutés. Sept tests Node réussis sous Linux, dont sockets TCP, refus des identifiants
inconnus, authentification 2013/2019, trames fragmentées, génération HLS d'un flux
synthétique et révocation. Test vidéo isolé des services de production et de la base.
Syntaxes Apache/systemd valides ; services actifs/activés ; accès externe HTTPS et
TCP 7808/1078 confirmés. API privées refusant les requêtes non autorisées. Connexion
web et écran Dashcams vide vérifiés dans le navigateur ; carte Google affichée.
Contrôle SQL final : un utilisateur, zéro dashcam. Aucun essai matériel exécuté.

**Limites explicites :** firmware, canaux, cadence, fuseau GPS et raccordement réel
à valider demain. H.264 vidéo seule ; audio/H.265, relecture carte SD, archives cloud,
ADAS/DMS, droits par flotte et mesures pour 300 appareils non livrés. Dashboard et
Centre vidéo général encore de démonstration ; le test direct est dans Dashcams.
Plafond initial de huit flux ; aucune capacité de charge promise. Sauvegarde externe,
supervision et réplication non installées. Aucun redémarrage du VPS.
## 22 septembre 2026 — Raccordement JK114 et préparation ES500-603

**Demande :** préparer le serveur pour les deux IMEI communiqués. JK114 configurée
en JT808 2019 vers EXADCAM en plus de GPS51 ; ES500-603 via CarAssist, EXADCAM en
backup. L'utilisateur précise qu'aucun choix de protocole n'existe dans CarAssist.

**Réalisé :** deux inscriptions explicites dans le registre de production ; admission
des appareils inconnus toujours refusée. JK114 immédiatement authentifiée, GPS
reçu. Correction de deux écarts matériels : fuseau GPS UTC+1 par équipement et
en-tête vidéo avec identifiant de dix octets BCD. Migration d'identité vidéo étendue
et configuration indépendante du fuseau. Après sauvegarde SQL, 145 premières
positions JK114 corrigées de sept heures selon une sélection bornée.

**Résultat réel :** les deux canaux JK114 acceptent la commande de direct, transmettent
du H.264 en 720 × 576 et produisent des segments HLS. ffprobe analyse chaque flux
et FFmpeg décode une image de chacun ; arrêt explicite après essai, tampon nettoyé.
Cadence HLS configurée à 15 images/s ; cadence native non mesurée indépendamment.
Les positions ont retrouvé une heure cohérente (âge de trois secondes au contrôle).

**Validation :** suite Laravel complète, 139 tests / 656 assertions ; neuf tests
Node réussis sous Linux, dont HLS avec identités courtes et longues ; Pint réussi.
Migrations et code déployés, services actifs, reconnexion JK114 observée. Aucun
essai destructif, changement de firmware ou modification du serveur fournisseur.

**Fichiers :** modèle Dashcam, contrôleurs Dashcam/Listener, migrations du 22 septembre,
listeners GPS/vidéo et parser, tests associés. Documentation : contexte, historique,
infrastructure, base, listener-server et nouveau `docs/device-commissioning.md`.

**En attente :** ES500-603 inscrite mais aucune connexion identifiable reçue ; test
avec EXADCAM temporairement en serveur principal demandé à l'utilisateur. Aucun
support matériel ES500-603 annoncé avant observation de ses trames. Lecture web
réelle dans le navigateur non testée dans ce lot ; dashboard/centre vidéo général
restent de démonstration. Audio, SD, cloud permanent et ADAS/DMS non validés.

## 22 septembre 2026 — Direct web confirmé et identifiant ES500-603 corrigé

**Retour utilisateur :** direct JK114 visible dans le navigateur ; serveur EXADCAM
désormais configuré dans CarAssist pour le second appareil.

**Constat et action :** réception ES500-603 à 09:35:21 UTC sous l'identifiant court
053810725721, absent du registre initial. Après sauvegarde ciblée, remplacement
de l'alias JT808 court de la fiche déjà autorisée, sans changer l'IMEI métier,
le secret d'authentification ou les services. Aucun appareil inconnu admis.

**Contrôles exécutés :** résolution interne du nouvel alias vers la fiche 2, HTTP 200 ;
ancien alias refusé, HTTP 404. JK114 toujours connectée et transmettant des positions.
Pas encore de nouvelle session authentifiée de l'ES500-603 après correction ;
reconnexion par CarAssist ou redémarrage demandé à l'utilisateur. Capture ciblée
sans exposition des coordonnées, secrets ou contenus vidéo dans les résultats.

**Documents :** contexte et `device-commissioning.md` actualisés. Aucune modification
du code et aucune suite de tests rejouée. Le direct JK114 est confirmé par l'utilisateur ;
le GPS et la vidéo ES500-603 restent à valider après reconnexion.

**Précision utilisateur :** redémarrage ES500-603 impossible pour le moment.
La validation reste en attente de reconnexion, sans modification supplémentaire
des services ou de la JK114.

## 22 septembre 2026 — Connexion ES500-603 confirmée après redémarrage

**Demande :** vérifier la connexion après le redémarrage annoncé par l'utilisateur.

**Résultat :** ES500-603 authentifiée sous son alias explicite 053810725721,
positions GPS et battements reçus. Décodeur ancien « 2013 », fix GPS valide.
API privée : deux appareils online=true, JK114 maintenue en fonctionnement.
Une reconnexion ES500-603 observée à 09:55:21 UTC ; données reçues ensuite.

**Correction :** fuseau GPS propre à l'ES500-603 passé de la valeur par défaut
UTC+8 à UTC+1 sur la base des trames réelles. Sauvegardes SQL ciblées ; 42 positions
initiales corrigées de sept heures avec filtre borné, sans toucher la JK114.
À 10:00:01 UTC : 44 positions ES500-603, dernier point âgé de neuf secondes.

**Contrôles exécutés :** statuts des services, API privée, journaux, capture TCP
bornée à 40 secondes et cohérence des dates SQL. Aucun changement de code,
aucune suite de tests rejouée, aucun redémarrage de service ou commande matérielle.
Documents de contexte, historique et commissioning actualisés localement et sur VPS.

**Limites :** direct vidéo ES500-603, identité vidéo et numérotation des canaux
restent à tester. Dashboard/cartographie généraux toujours en démonstration.

## 22 septembre 2026 — Correction du direct ES500-603, essai éveillé en attente

**Demande :** résoudre le lecteur restant sur « Connexion à la caméra » malgré
la réception GPS ; expliquer la différence JT808 2013/2019.

**Diagnostic réel :** commandes de direct acceptées et arrivée du flux JT1078.
Le terminal vidéo transmet 053810725721, tandis que la fiche contenait encore
538107257217. Correction exacte du video_terminal_id de la fiche 2 après sauvegarde.
Le flux H.264 devient admis, puis FFmpeg échoue avec « first pts and dts value
must be set ». Les deux versions JT808 ne constituent pas le blocage observé.

**Correctif livré :** option normalize_video_timestamps par équipement, désactivée
par défaut, activée uniquement pour l'ES500-603. Le filtre FFmpeg setts génère les
horodatages à la cadence configurée (15 images/s) sans réencoder. Mode réservé
aux images I/P observées ; une trame JT1078 de type B est refusée explicitement.
La JK114 conserve son traitement natif. Le serveur GPS n'est pas redémarré ; seul
le service vidéo est relancé à 10:17 UTC après sauvegarde et migration production.

**Validation exécutée :** échantillon réel ES500-603 H.264 Baseline 960 × 540,
14 images : conversion initiale en échec, conversion corrigée et décodage réussis.
Neuf tests Laravel ciblés / 48 assertions (SQLite isolée), Pint réussi ; dix tests
Node Linux réussis, dont identités courtes/longues, HLS, dates des images, décodage,
révocation et refus des images B pour le mode normalisé. Pas de suite Laravel
complète exécutée dans ce lot.

**Blocage de l'essai matériel final :** ES500-603 déconnectée à 10:14:22 UTC, avant
le déploiement. API /status = 409 à 10:18 ; l'utilisateur indique des mises en
veille et confirme que la caméra est alimentée. Essai après réveil/contact demandé.
Le direct web ES500-603 après correction n'est donc pas encore validé. Contrôle
JK114 : GPS actif, mais l'appareil refuse deux commandes de direct (accusé négatif),
ce qui ne permet pas de revalider sa lecture vidéo dans ce lot.

**Fichiers :** modèle Dashcam, contrôleurs Dashcam/Listener, migration du 22 septembre
102000, listener/src/video.js, tests ListenerAccessTest et listener/test/video.test.js.
Documents de contexte, historique, commissioning et listener actualisés.
Migration locale non appliquée : MariaDB local 127.0.0.1:3306 arrêté/injoignable ;
la migration de production est appliquée avec succès. Aucun secret ajouté aux fichiers.


### Précisions de diagnostic — 22 septembre 2026, 10:27 UTC

L'utilisateur confirme que son téléphone accède à Internet via le hotspot de
l'ES500-603 : absence de data non retenue. Le direct CarAssist est regardé en Wi-Fi.
Main IP 62.171.190.15 et Main Port 7808 sont confirmés inchangés. Sur la capture
bornée de 10:26 à 10:27 UTC, seule la JK114 transmet à EXADCAM ; dernière télémétrie
ES500 à 10:14:14 UTC. Aucun basculement effectif vers le serveur backup n'est prouvé.

L'utilisateur demande un essai en 2019. Le listener accepte déjà les deux formats
et choisit la réponse d'après l'en-tête reçu : last_protocol est un constat, pas
une sélection de protocole. Aucun changement de cette valeur n'a été effectué.
Pas de commande constructeur ni réglage CarAssist vérifié permettant de changer
la version émise par ce firmware. Le manuel original sur G: est indisponible dans
la session ; les notes conservées décrivent l'interface AT603D, distincte de
l'écran CarAssist fourni. Ne pas appliquer des commandes supposées à cet appareil.



## 22 septembre 2026 — Direct ES500-603 confirmé après redémarrage

**Retour utilisateur :** après redémarrage de la caméra, le direct fonctionne.
**Preuve serveur :** authentification à 10:30:00 UTC, toujours au format JT808
ancien affiché « 2013 » ; demandes de direct canal 1 à 10:30:30 et canal 2 à
10:30:45. Aucun passage en 2019 n'a été appliqué ni nécessaire.
**Preuve navigateur :** lecteur actif en 960 × 540, temps de lecture supérieur
à 75 secondes, sans erreur vidéo. Après l'interruption décrite ci-dessous,
canal 2 relancé à 10:34:12, lecture observée en progression en 960 × 540.

**Limite observée :** rejet « Message rate limit » à 10:32:25 UTC, puis reconnexion
à 10:32:36 ; la perte de session GPS arrête la vidéo par contrôle d'autorisation.
Une capture bornée de suivi montre positions, battements et réponses normales,
sans reproduire immédiatement la rafale. Ne pas présenter une correction de
limiteur comme livrée : aucun seuil modifié. Stabilité longue durée à suivre.

Documents de contexte, historique et commissioning actualisés. Aucun changement
de code, configuration ou protocole dans ce lot ; aucune suite de tests rejouée.

## Travaux restant à planifier et livrer

1. Écrans d’administration restants : clients/abonnements et flottes. Gestion des utilisateurs livrée.
2. Véhicules, dashcams, affectations et paramètres de raccordement.
3. Valider le service Node.js GPS/commandes installé avec les trames du matériel réel.
4. Valider le direct JT1078/HLS installé avec le matériel, puis compléter les canaux/audio.
5. Historique sur carte mémoire, extraits, politique de conservation cloud et alertes ADAS/DMS.
6. Rapports, tests de charge, sauvegardes externes et supervision du déploiement installé.
7. Application Android/iPhone après stabilisation du web et des interfaces serveur.

Cet ordre exprime le cadre de travail actuel et ne vaut pas engagement de
livraison ni validation du support de toutes ces fonctions par le firmware.

Contrôle complémentaire : le canal 2 est resté ouvert de 10:34:12 à 10:36:16 UTC
(plus de deux minutes), sans nouvelle déconnexion GPS ni erreur vidéo dans les
journaux ; arrêt par fermeture de lecture, puis nouvelles demandes CH1 et CH2.
La rafale ayant provoqué la coupure précédente n'a pas été reproduite dans cette
fenêtre. Aucun seuil modifié. Les résultats ne constituent pas un test d'endurance.


## 22 septembre 2026 — Profils dashcams, modales et affectations véhicule/flotte

Demande : choisir ES500-603 ou JK114 avant le nom, saisir l’identifiant CarAssist pour ES500, imposer TCP et 2013 pour ES500, proposer 2013/2019 pour JK114, ajouter/modifier en modale et reprendre les tableaux EXAD Tracking. Chaque dashcam doit être affectée à un véhicule rattaché à une flotte.

Livré : migration additive `vehicles` et colonnes de profil/affectation, relations Eloquent, validations serveur et dynamiques, préservation des alias existants, modales Bootstrap locales FR/EN, tableau AJAX avec recherche/tri/pages de 5/10/25/50, filtre de modèle, compteurs, commandes de direct conservées. Registres Flottes et Véhicules ajoutés (création, modification, liste). Gestion réservée au superadmin ; aucune nouvelle autorisation client. Modifier un nom ou un véhicule ne révoque plus le direct.

Affectations demandées créées en production : **EXAD CARS → Véhicule test 1 → JK114** et **EXAD CARS → Véhicule 2 → ES500-603**. Immatriculations laissées vides. Identités, secrets et réglages vidéo préservés ; deux appareils encore en contact après livraison. MariaDB local relancé, migrations locales en attente appliquées. Données réelles en production ; essais sur SQLite distinct.

Contrôles réellement exécutés : suite Laravel finale **158 tests / 792 assertions réussis** ; `node --check` des deux JS ; Pint ; essais navigateur local de création/édition/recherche et de création flotte/véhicule ; vérification production du tableau et des valeurs de la modale ES500. Migration de caméras préexistantes testée sans altération des attributs initiaux. Aucun test Node fonctionnel rejoué : code écouteur inchangé. Aucun nouvel essai d’endurance vidéo dans ce lot.

Déploiement : archive des fichiers ciblés vérifiée par SHA-256 ; sauvegarde fichiers + SQL dans `/var/backups/exadcam-registry-20260922-111550`. Migration appliquée avant le reste des fichiers. Services Apache/PHP-FPM/GPS/vidéo actifs, écouteurs non redémarrés et baux vidéo conservés. Routes registre et page de connexion vérifiées, HTTP 200.

Fichiers : `DashcamController`, `FleetRegistryController`, `DashcamProfile`, modèles `Dashcam`, `Vehicle`, `Fleet`, migration `2026_09_22_120000`, routes web, vues dashcams/registry/sidebar/welcome/navigation, traductions FR/EN, JS dashcams/fleet-registry et CSS dashcams, tests `DashcamRegistryTest` et `ListenerAccessTest`. Guide complet : [dashcam-registry.md](dashcam-registry.md).

Limites : l’année configurée ne change pas le firmware à distance ; `last_protocol` reste observé. Le dashboard et la carte restent en démo. Registres de base livrés, autres fonctions métier flottes/véhicules à développer. Audio, archives, conservation cloud et applications mobiles restent hors de ce lot.

## 22 septembre 2026 — Simplification des affectations et sélecteurs recherchables

Correction demandée : le véhicule connaît déjà sa flotte, supprimer ce choix redondant dans la dashcam ; reprendre les listes avec recherche d’EXAD Tracking et enlever les titres/descriptions doublons des panneaux Flottes/Véhicules.

Livré : seul `vehicle_id` est demandé et validé pour l’affectation d’une dashcam ; la flotte vient de `vehicle.fleet`. Une ancienne valeur `fleet_id` ne peut pas modifier cette relation. Options véhicules enrichies avec leur flotte pour chercher par véhicule, immatriculation, nom/code de flotte. Composant local de recherche adapté d’EXAD Tracking pour les véhicules des dashcams et les flottes des véhicules, avec clavier, recherche insensible aux accents, message sans résultat et validation dynamique. Barres d’outils Flottes/Véhicules compactées, titres/descriptions des panneaux supprimés. Schéma, données, identifiants et paramètres vidéo inchangés.

Validation réellement exécutée : **27 tests Laravel ciblés / 182 assertions réussis**, syntaxe des trois JS vérifiée, Pint passé. Navigateur local sur SQLite séparé : affectation sans flotte et sauvegarde, recherche, flèches/Entrée/Échap, résultat vide, validation et sélection au clic puis création de véhicule. Défaut de fermeture au focus corrigé et rejoué avant publication. Aucune suite Laravel complète ni tests fonctionnels Node rejoués pour cette correction.

Déploiement de 12 fichiers après contrôle SHA-256 et sauvegarde dans `/var/backups/exadcam-registry-ux-20260922-115425`. Cache des vues renouvelé ; PHP-FPM rechargé. Aucune migration, écriture métier ni coupure des écouteurs GPS/vidéo. Apache/PHP-FPM/GPS/vidéo actifs. Guide `docs/dashcam-registry.md` corrigé en conservant l’historique de la décision précédente.

## 22 septembre 2026 — Départements, sites et régions des flottes

Demande : un véhicule peut avoir un département/site/région, sans obligation ; chaque
département appartient obligatoirement à une flotte. La flotte du véhicule reste requise.

Livré :
- Modèle Department et table departments, structure reprise d’EXAD Tracking (référence
  consultée en lecture seule) : flotte obligatoire, nom, code facultatif, description,
  statut actif par défaut. Le nom permet de représenter un département, site ou région ;
  aucun type supplémentaire ni hiérarchie récursive imposés.
- vehicles.department_id nullable. Clé étrangère composée department_id + fleet_id :
  la base refuse une affectation entre flottes différentes, y compris hors formulaire.
- Menu Départements opérationnel : création/modification en modale, recherche, tri,
  pagination, validation dynamique et contrôle serveur ; accès superadmin existant conservé.
- Formulaire véhicule : flotte recherchable, département facultatif recherchable filtré
  sur cette flotte, choix « Aucun département ». Changer de flotte retire le département
  incompatible du formulaire. À l’édition, une propriété department_id omise préserve
  l’affectation ; null explicite la retire. Le serveur rejette une affectation incompatible.
- Changer la flotte d’un département contenant des véhicules est refusé avec un message
  explicite. Il faut réaffecter ces véhicules avant le changement.
- Colonne département dans le tableau véhicules et recherche par nom/code de département.
  La dashcam reste liée uniquement au véhicule et hérite de son organisation.
- Composant searchable-select local : choix vide autorisé pour les champs facultatifs,
  préremplissage/effacement conservés, versions des assets actualisées. FR et EN disponibles.

Validation réellement exécutée : 35 tests ciblés / 253 assertions, puis suite complète
Laravel 166 tests / 868 assertions, tous réussis (SQLite isolé). Pint et node --check
réussis. Migration aller/retour testée sur SQLite, application additive réussie sur MariaDB
locale et production. Navigateur sur base SQLite séparée : création Site Nord, validation
des champs requis, recherche et choix clavier, création véhicule avec site, préremplissage,
choix Aucun département, changement de flotte et enregistrement sans département.

Déploiement : sauvegarde SQL et fichiers dans
/var/backups/exadcam-departments-20260922-121839 ; migration appliquée avant le code
utilisant la nouvelle relation. Identités/secrets/paramètres des dashcams et affectations
véhicules comparés avant/après et inchangés. Les 2 véhicules EXAD CARS restent sans
département, aucun département réel inventé. Derniers contacts des 2 caméras récents
après déploiement. Apache, PHP-FPM, exadcam-gps et exadcam-video actifs. Écouteurs non
redémarrés et cache des baux vidéo conservé. Vérification des pages et modales production
en lecture seule ; aucun nouveau test vidéo prolongé pour ce lot.

Limites : registre départements sans suppression, sans cascade de réaffectation ni
hiérarchie département/sous-département. Aucun élargissement des accès admin/user.
Les limites vidéo/audio/archives et les éléments de démonstration antérieurs restent valables.

## 22 septembre 2026 — Carte réelle et suivi des déplacements

Demande : rapprocher la carte d'EXAD Tracking et afficher les positions et déplacements
réels. La référence a été consultée en lecture seule. Les six positions de démonstration
ont été remplacées par les relevés reçus dans `dashcam_positions`.

Livré : carte occupant l'espace disponible, panneau de véhicules, compteurs, recherche,
sélecteurs recherchables flotte et département/site/région, filtre d'état, fiche de
position, cadrage, suivi du véhicule sélectionné, zoom, satellite et plein écran.
Thème Bootstrap local et textes FR/EN conservés. La carte est partagée avec le tableau
de bord ; les autres indicateurs de démonstration restent explicitement signalés.

`GET /map/vehicles` exige un compte actif, `map.view` et le périmètre de flotte côté
serveur ; réponse privée non stockable. Une position par véhicule, choisie parmi ses
caméras activées d'après le relevé GPS admissible le plus récent. Vérification du fix,
coordonnées et dates ; états distincts pour arrêt, déplacement, hors ligne, position
ancienne ou absente. Aucun secret, IMEI ou alias de communication dans la réponse.

Actualisation environ toutes les dix secondes sans recréer la carte Google. Animation
entre relevés confirmés, pas d'extrapolation ; courte trace filtrée et bornée à dix
points/850 mètres dans les dix dernières minutes, coupée après interruption ou saut GPS.
Pause des requêtes hors de la vue/onglet, délais limites et erreurs visibles. Aucun
appel de calcul d'itinéraire ni géocodage ajouté.

Migration additive `2026_09_22_150000_add_map_assignment_boundaries` : bornes temporelles
des affectations flotte/véhicule, maintenues par les modèles. Les anciennes traces ne
sont pas révélées après une réaffectation. Affectations existantes initialisées à
l'exécution (production 12:58:33 UTC) ; historique GPS brut conservé. Migrations
effectuées après vérification de la base réellement utilisée : SQLite de test séparé,
MariaDB locale exadcam, puis MariaDB exadcam du serveur attendu.

Validation finale réellement exécutée : **175 tests Laravel / 873 assertions** et
**3 tests Node du module d'animation**, tous réussis ; Pint et syntaxes JS réussis.
Couverture GPS valide/invalide/ancien/futur, source multiple par véhicule, limites de
trace, arrêt, authentification, cloisonnement et réaffectations. Navigateur local
sur SQLite isolé : recherche, sélection, filtres, fiches et changement de position
synthétique observé ; une seule instance Google dans la navigation interne.

Déployé après contrôle SHA-256 et sauvegarde SQL/fichiers dans
`/var/backups/exadcam-map-20260922-125833`. Migration avant le code, caches de vues/routes
renouvelés et PHP-FPM rechargé. Aucun redémarrage GPS/vidéo ni vidage des baux vidéo.
Identités/secrets/paramètres des caméras et affectations véhicules préservés. Quatre
services actifs après déploiement. Production : les deux véhicules EXAD CARS sont
localisés près de Binza avec des coordonnées reçues récemment ; la fiche Véhicule 2
affiche la source ES500-603 et se met à jour automatiquement. Les deux appareils
étaient à l'arrêt pendant le contrôle.

Fichiers principaux : `FleetMapService`, `MapController`, modèles Vehicle/Dashcam,
migration 150000, routes web, contrôleur/vue du dashboard, `google-map.js`,
`map-motion.mjs`, `google-map.css`, traductions et tests de la carte. Guide technique :
[google-maps.md](google-maps.md).

Limites : trace récente uniquement, historique complet/relecture par date à développer.
Déplacements validés sur relevés synthétiques ; essai routier et charge réelle à plus
de 300 appareils non réalisés. Aucun nouvel essai vidéo prolongé ni changement des
protocoles. Les autres fonctions encore démonstratives du tableau de bord le restent.


## 22 septembre 2026 — Carte EXAD Tracking : recherche, symboles, détails et deux canaux vidéo

Correction demandée après comparaison visuelle : reproduire le parcours réel d'EXAD
Tracking, ne montrer la liste que pendant une recherche, ajouter les icônes des
compteurs, flèche et ligne en mouvement, carré à l'arrêt, P au parking, fiche au clic
sur la carte, modale Historique et détails et volet vidéo à droite avec les deux canaux.

Livré :
- Panneau compact à quatre compteurs illustrés sur deux colonnes, case Afficher tous
  les véhicules, filtres d'état/flotte et de département si des véhicules ont un site,
  recherche par véhicule, immatriculation, flotte et, pour le superadmin, IMEI/modèle.
  Au chargement de la rubrique Carte, ni liste ni marqueurs imposés : rechercher puis
  sélectionner un véhicule, ou cocher Afficher tous les véhicules. La liste de résultats
  reste cachée sans recherche. La carte du tableau de bord conserve son aperçu global.
- Marqueur flèche bleue et trace courte en déplacement ; carré bleu à l'arrêt avec
  contact ACC allumé ; P rond si le contact est coupé. Connexion/GPS anciens gardent
  des symboles distincts. Règle ACC reprise du service EXAD Tracking ; seuil GPS de
  déplacement EXADCAM conservé à 3 km/h. La direction de flèche est calculée depuis
  les points GPS confirmés, pas depuis un cap JT808 nouvellement persisté. La trace
  suit le marqueur pendant l'animation et disparaît à l'arrêt/parking. Les coupures
  et sauts GPS ne sont pas interpolés.
- Fiche Google Maps au clic sur le marqueur : véhicule, équipement, immatriculation,
  flotte, vitesse, contact récent ; boutons Historique et détails et Vidéos.
- Modale Bootstrap avec informations de la dashcam et historique GPS par date locale,
  paginé à 25 relevés, heures, état, vitesse, contact et coordonnées. Nouvel endpoint
  `/map/vehicles/{vehicle}/details`, authentification/compte actif/map.view, périmètre
  de flotte et caméra appartenant au véhicule vérifiés en transaction. Bornes
  d'affectation conservées, invalidités/futures positions exclues. Ce tableau ne
  constitue pas une relecture animée d'un trajet ni une archive vidéo.
- Les informations techniques (IMEI, modèle, protocole, canaux, cadence) sont réservées
  au superadmin, suivant le parcours demandé et EXAD Tracking. Aucune clé, adresse IP,
  identifiant de communication ou donnée d'authentification transmise à la carte.
- Volet vidéo occupant la moitié droite de la carte sur ordinateur, deux zones visibles
  Canal 1 / Canal 2, démarrage par Lecture uniquement. HLS local et endpoints vidéo
  existants, toujours superadmin ; aucune nouvelle autorisation client introduite.
  Chaque canal possède son lecteur et son bail. Arrêt, fermeture, changement de véhicule,
  sortie de la rubrique/onglet et fermeture de page libèrent les lectures concernées.
  Une réponse tardive de démarrage est arrêtée sans ouvrir de lecteur. Erreurs et
  attente de connexion affichées ; fermeture sans flux lancé n'ouvre aucune connexion.

Validation réellement effectuée : **179 tests Laravel / 909 assertions** réussis et
**8 tests JavaScript** réussis (5 déplacement/symboles, 3 cycle des baux vidéo). Pint et
syntaxe JS vérifiés. Tests de date/fuseau/pagination, confidentialité, permission et
affectation ; tests de bail tardif, indépendance CH1/CH2 et libération après erreur.
Le test navigateur a détecté une invocation incorrecte des temporisations natives du
lecteur ; enveloppes corrigées, puis parcours rejoué avec succès. Les tests unitaires
seuls n'avaient pas détecté cette particularité du navigateur.

Navigateur local sur SQLite séparé : résultats absents avant saisie, recherche IMEI,
sélection, flèche/trace et carré, fiche ancrée, modale et relevés, volet 50/50 sans
démarrage automatique, erreur de flux simulé limitée au canal demandé. Production :
deux équipements toujours en GPS, fiche réelle ES500-603 en TCP/JT808 2013, 25 relevés
sur la page 1 et pagination page 2 fonctionnelle. CH1 puis CH2 ES500-603 lus simultanément
en 960 × 540, horloges vidéo progressant indépendamment. Image CH1 sombre avec horodatage
incrusté, CH2 montrant la scène filmée ; aucun changement optique ou matériel effectué.
Arrêt CH1 sans interrompre CH2, puis fermeture du volet : lecteurs sans source, journaux
`video_stopped` pour les deux canaux (13:46:11 et 13:46:21 UTC). Essai court, pas un test
d'endurance. Aucun nouveau test physique de la JK114 dans ce lot.

Déploiement de 15 fichiers après vérification SHA-256 et sauvegarde SQL/fichiers dans
`/var/backups/exadcam-map2-20260922-134311`. Aucun changement de schéma ni code écouteur,
aucun redémarrage GPS/vidéo ni purge des baux. PHP-FPM rechargé, vues/routes renouvelées.
Identités/secrets/paramètres des dashcams et affectations véhicules comparés et préservés.
Apache/PHP-FPM/GPS/vidéo actifs. Documentation synchronisée après vérifications.

Fichiers : FleetMapService, MapDetailsService, MapController, DashboardPreviewController,
routes web, partial dashboard-map, google-map.js, map-motion.mjs, map-video.mjs,
google-map.css, traductions FR/EN, FleetMapTest et tests JS. Voir google-maps.md.

Limites : cap de flèche déduit des déplacements reçus, essai routier réel et charge
300+ équipements non effectués. Historique GPS tabulaire livré, relecture complète
de trajet/audio/archives vidéo non ajoutés. Mise en page mobile prévue avec carte
au-dessus des vidéos mais non validée sur téléphone physique dans ce lot.

## 22 septembre 2026 — Carte : proportions et détails affinés

Demande : réduire les compteurs et les symboles des résultats, reprendre la structure
compacte des fiches/modales EXAD Tracking, améliorer les proportions du volet vidéo.

Réalisations : panneau de filtres ramené de 365 à 320 px, chiffres de 21 à 17 px,
actions et champs plus compacts. Dans les résultats, flèche de 20 × 23 px, carré
de 16 × 16 px ; les marqueurs de la carte conservent leur lisibilité. Fiche ancrée
compacte : voyant vert/gris associé à la connexion, titre/IMEI, lignes alignées,
dernier contact relatif avec date exacte au survol, deux actions. Suppression de
la ligne d’en-tête vide de Google InfoWindow via headerDisabled et ajout d’une
fermeture accessible dans la fiche.

Modale limitée à 800 px, en-tête avec icône et fermeture à droite, onglet Synthèse
à l’ouverture : bandeau aux couleurs EXADCAM, état de connexion et état GPS,
cartes Véhicule et dashcam / Emplacement, protocole et date de création en bas.
La dernière position connue provient du relevé sélectionné de la carte ; ses
coordonnées et son horodatage sont affichés. Les informations absentes restent
indiquées par un tiret. Aucun pourcentage GPS, adresse ou durée de parking inventé.
Onglet Historique GPS distinct conservant date, relevés réels et pagination.

Volet vidéo plus étroit, largeur adaptée à la hauteur disponible, plafonnée à
470 px sur ordinateur, plutôt qu’une moitié fixe de l’écran. Deux lecteurs au
format 16:9, image contenue sans déformation, titres et commandes réduits. Pas
de démarrage automatique ; gestion existante des baux, permissions et arrêt
des canaux conservée. Styles mobiles empilés conservés.

Vérifications de ce lot : syntaxe JavaScript et traductions PHP FR/EN valides ;
**16 tests Laravel ciblés / 111 assertions** réussis (FleetMapTest et
DashboardMapTest). Aperçu local avec base SQLite séparée : recherche, petit
symbole flèche, fiche avec voyant, synthèse et onglet historique, lecteurs inactifs
à l’ouverture. Production : données ES500-603 TCP/JT808 2013, modale 800 px,
historique pages 1 et 2 de 25 relevés, deux surfaces 16:9 visibles sans débordement
vertical dans le viewport contrôlé. Essai bref du Canal 2 ES500-603 : vidéo
960 × 540 décodée, temps de lecture progressant de 8 à 15 secondes, Canal 1 non
lancé. Fermeture du volet libérant la lecture. Aucun nouvel essai d’endurance,
de roulage ou sur téléphone physique. La suite complète et les tests Node du
lot précédent ne sont pas présentés comme réexécutés dans ce lot visuel.

Déploiement ciblé de cinq fichiers d’interface après vérification SHA-256,
sauvegarde SQL/fichiers dans `/var/backups/exadcam-map3-20260922-141816`.
Vues/routes renouvelées et PHP-FPM rechargé ; services Apache/PHP-FPM/GPS/vidéo
actifs. Aucun changement de schéma, d’écouteur ou de protocole. Identités et
paramètres des caméras, affectations des véhicules comparés et préservés.
Version publique des ressources : `map-refined-3`.

Fichiers : `public/css/google-map.css`, `public/js/google-map.js`,
`resources/views/partials/dashboard-map.blade.php`, `lang/fr/map.php`,
`lang/en/map.php`, documentation projet/contexte et `google-maps.md`.

## 22 septembre 2026 — Historique GPS : 10 relevés et pagination numérotée

Demande : limiter l’historique aux dix premiers événements et paginer comme les
autres tableaux. L’onglet Historique GPS affiche désormais au maximum 10 relevés
par page, les plus récents en premier. Boutons de pages numérotées, page active
dans la couleur du thème, précédent/suivant, première/dernière page et points de
suspension pour les longues listes. Compteur « Affichage de 1 à 10 sur … » ;
sur une journée vide, « Affichage de 0 à 0 sur 0 ». Changer la date repart en page 1.

MapDetailsService utilise la pagination Laravel avec total : per_page, total,
last_page, from et to ajoutés à la réponse. Le total est calculé dans le même
périmètre autorisé (véhicule, caméra, journée locale, bornes d’affectation et
validité GPS). Aucune dépendance ajoutée. Le total du jour peut évoluer entre
deux consultations quand de nouvelles positions arrivent ; les pages sont triées
par date et identifiant décroissants, sans nouvelle fonction de gel d’historique.

Contrôles : 16 tests Laravel ciblés / 133 assertions réussis, Pint et syntaxe JS.
Le test de pagination vérifie 23 positions autorisées : 10, 10 puis 3, leurs
horodatages et métadonnées ; positions hors date/invalides exclues du total,
total nul après borne d’affectation et permissions préservées. Aperçu SQLite
isolé vérifié. Production : pages 1 et 2 de dix lignes, compteur et page active,
accès direct à la dernière page et bouton suivant désactivé, journée sans relevé.
Pas de nouvelle suite complète ni d’essai vidéo dans ce lot.

Sept fichiers déployés et sauvegardés dans
/var/backups/exadcam-map4-20260922-143735 : MapDetailsService, google-map.js,
google-map.css, partial dashboard-map, traductions FR/EN, FleetMapTest.
Ressources versionnées map-history-10. Aucun changement de schéma ni redémarrage
des écouteurs. Vues/routes renouvelées, PHP-FPM rechargé ; les quatre services
restent actifs et les identités/paramètres/affectations ont été préservés.
Documentation de contexte et guide Google Maps actualisés et synchronisés.

## 22 septembre 2026 — Lecteur JK114 élargi horizontalement

Demande confirmée : augmenter la largeur horizontale pour le modèle JK114.
Sur ordinateur, son volet vidéo peut occuper 50 % de l’espace disponible,
avec un maximum de 620 px ; la limite calculée à partir de la hauteur de l’écran
ne réduit plus ce modèle. Défilement vertical conservé pour accéder aux deux
canaux. Image entière conservée via object-fit: contain, sans étirement ni
recadrage. L’ES500-603 garde le panneau compact précédent. Présentation mobile
sur toute la largeur conservée. Le modèle est réappliqué à chaque ouverture.

Vérifications : syntaxe JS valide, comparaison navigateur en production au même
viewport 1536 × 730 : surface vidéo JK114 passée de 335 à 555 px de largeur,
retour à 335 px pour l’ES500-603 après changement de véhicule. Flux réel JK114
Canal 1 décodé en 720 × 576, lecture progressant de 17 à 31 secondes ; fermeture
de la lecture de contrôle puis lecteurs sans source. Aucun nouveau test
d’endurance, sur téléphone physique ou de suite complète dans ce lot de style.

Trois fichiers déployés : google-map.css, google-map.js et partial dashboard-map,
ressources versionnées map-jk114-wide. Sauvegarde SQL/fichiers :
/var/backups/exadcam-jk-frame-20260922-144903. Vues/routes renouvelées et PHP-FPM
rechargé ; services actifs, identités/paramètres/affectations préservés. Aucun
changement de protocole, de schéma ou redémarrage d’écouteur. Documentation
de contexte et guide Google Maps actualisés.

## 22 septembre 2026 — Correction : largeur de l’image intérieure JK114

La capture utilisateur précise que la demande concerne l’image à l’intérieur
du lecteur. L’élargissement du panneau à 620 px du lot précédent était une
mauvaise interprétation et est retiré. Le panneau retrouve sa largeur compacte.
Pour les deux canaux JK114, l’image utilise maintenant object-fit: fill dans
la surface 16:9 : adaptation horizontale et suppression des marges latérales
ajoutées par le lecteur, tout le contenu de l’image conservé sans recadrage.
Le flux source 720 × 576 n’est pas réencodé ni modifié ; ses proportions sont
adaptées à l’affichage demandé. L’ES500-603 conserve object-fit: contain.

Contrôle navigateur en production sur le Canal 2 montré dans la capture :
flux 720 × 576 en lecture, temps progressant de 3,8 à 10,1 secondes, image
occupant toute la surface avec les informations incrustées encore visibles.
Règle fill vérifiée sur les deux lecteurs JK114, panneau compact rétabli,
lecture de contrôle fermée. Aucun changement JavaScript, d’écouteur, de schéma,
de protocole ou de permissions ; pas de nouvelle suite de tests automatisés
pour ces deux fichiers de présentation.

Déploiement CSS et partial dashboard-map, version map-jk114-image. Sauvegarde :
/var/backups/exadcam-jk-image-20260922-145827. Vues/routes renouvelées, PHP-FPM
rechargé, quatre services actifs ; identités et affectations préservées.
Le contexte courant et le guide Google Maps remplacent la consigne de panneau
élargi par celle d’image intérieure élargie.

## 22 septembre 2026 — Image JK114 pleine largeur depuis la liste Dashcams

Demande : appliquer à la modale Voir en direct de la liste Dashcams le même
remplissage horizontal que sur la carte. Le modèle de la caméra choisie est
désormais réappliqué à chaque ouverture. Pour JK114, object-fit: fill remplit
la surface du lecteur sans couper les bords ou les informations incrustées.
Les dimensions de la modale et sa limite de hauteur existantes sont conservées.
Le flux source reste intact ; seul son affichage est adapté. ES500-603 garde
son rendu contain, y compris après une lecture JK114.

Vérifications : syntaxe JS valide ; contrôle navigateur en production depuis
la liste sur le Canal 2 de la JK114 du Toyota Hilux. Flux réel 720 × 576, lecture
progressant de 3,8 à 9,1 secondes, image pleine largeur vérifiée visuellement,
puis fermeture de la lecture. Ouverture ES500-603 ensuite : modèle actualisé
et object-fit: contain vérifiés, puis fermeture. Aucun test d’endurance ni
nouvelle suite automatisée pour ce réglage de présentation.

Trois fichiers déployés : public/js/dashcams.js, public/css/dashcams.css et
resources/views/dashcams/module.blade.php. Ressources registry-live-image.
Sauvegarde : /var/backups/exadcam-dashcam-live-image-20260922-151023.
Vues/routes renouvelées, PHP-FPM rechargé, services actifs. Identités et
affectations préservées ; aucun changement de protocole, de base ou d’écouteur.
Journal, contexte et guide dashcam-registry actualisés.

## 22 septembre 2026 — Positions absentes des deux nouveaux Toyota Hilux

Demande : diagnostiquer une nouvelle ES500 initialement en attente et deux
équipements désormais en ligne sans position sur la carte.

Preuve serveur à 15:27 UTC : IMEI 867934087966430 (JK114, fiche 3) et
352538106693487 (ES500-603, fiche 4) authentifiés, fixes GPS valides reçus,
gps_timezone_minutes null. Délais GPS/réception : 25 200 à 25 204 secondes
pour JK114 ; 25 200 à 25 299 pour ES500. Les deux anciennes fiches sont à 60.
Le repli UTC+8 enlève sept heures de trop ; les points passent avant les dates
d’affectation et la carte les écarte normalement. Le protocole et le fix GPS
ne sont pas la cause. ES500 a bien rejoint le registre en 2013 à 15:22:45 UTC.

Correction : DashcamProfile persiste à la création le défaut d’installation
listener.default_gps_timezone_minutes (DASHCAM_GPS_TIMEZONE_MINUTES, 60).
Le calibrage existant n’est jamais écrasé lors d’une édition. Configuration
documentée dans .env.example ; aucun changement de secret ou d’environnement réel.
Tests de régression des trois profils (JK 2013/2019, ES 2013), de leur ingestion
et de leur visibilité après affectation ; contrôle de la valeur 0 et conservation
d’un réglage personnalisé -180. Pint valide ; 44 tests Laravel ciblés
DashcamRegistryTest / ListenerAccessTest / FleetMapTest, 331 assertions, SQLite
isolé. La suite complète n’a pas été rejouée pour cette correction.

Déployés : app/Support/DashcamProfile.php, config/listener.php et test associé.
Configuration Laravel reconstruite, PHP-FPM rechargé, sans vider les baux vidéo.
Sauvegarde SQL et fichiers : /var/backups/exadcam-gps-timezone-20260922-153119.
Fiches 3/4 mises à 60 ; 335 points recalés de +7 h, uniquement pour ces fiches,
avec réception antérieure à la bascule +15 s et écart 25 200–25 320 s.
La deuxième passe idempotente a corrigé 0 point. Position courante également
recalée. Identités, secrets, coordonnées et bornes d’affectation préservés ;
autres fiches inchangées. Services Apache, PHP-FPM, GPS et vidéo actifs.

Contrôle navigateur production : recherche Toyota, 2 véhicules localisés,
positions retrouvées et marqueurs visibles. Les derniers relevés reçus sont
à 15:30:25 UTC (JK114) et 15:30:03 (ES500). Les connexions ont été fermées
à 15:31:09 et 15:31:04, avant la sauvegarde/déploiement de 15:31:19.
La reprise matérielle après correction reste à observer à ce stade ; ne pas
présenter les points recalés comme des trames reçues après la correction.
Journal, contexte et guides de registre/listener actualisés.

## 22 septembre 2026 — Direct des deux Toyota Hilux et présence multi-plateforme

Utilisateur : les deux appareils sont visibles avec vidéo sur une autre plateforme.
Il confirme EXADCAM en serveur principal pour ES500-603 / 9863BV01 et en serveur
secondaire pour JK114 / 0210BW01. Il ne peut pas fermer le direct de l’autre
plateforme pour effectuer un essai sans lecture concurrente. Aucun changement
des destinations ni du protocole matériel n’a été effectué.

JK114, fiche 3 : reconnexion à 15:36:31 UTC après la correction de fuseau,
positions valides et actualisées. À 15:56:17 UTC, 100 points post-correction
ont un écart réception/GPS d’une seconde : le correctif GPS est vérifié sur
de nouveaux relevés réels. Coupure à 15:52:37, reprises à 15:54:25 puis
15:55:35 ; contact récent au dernier contrôle. Les journaux 15:47–15:48
montrent plusieurs refus de commandes de démarrage des canaux 1 et 2.
Essai navigateur CH1 à 15:51:43 : Device rejected command côté GPS,
video_stopped / start_failed côté vidéo, réponse HTTP 503 dans le parcours.
Essai CH2 à 15:52:38 : appareil entre-temps déconnecté, HTTP 409.
Le refus est émis dans le traitement d’une réponse 0x0001 associée à la
commande envoyée ; le journal actuel ne conserve pas la valeur numérique du
refus. La capture commencée ensuite n’a pas capturé ce refus, il ne faut donc
pas prétendre avoir lu son code exact. Une restriction du serveur secondaire
ou une concurrence des directs est une hypothèse, pas une cause démontrée.
Pas de nouvelles commandes vidéo après la réponse de l’utilisateur.

ES500-603, fiche 4 : dernier contact 15:30:23 UTC, dernier GPS 15:30:03,
déconnexion journalisée à 15:31:04. Aucune nouvelle authentification depuis.
Essai navigateur : appareil non connecté au serveur EXADCAM. Pendant la
capture bornée de 45 secondes vers 15:55 UTC, aucune trame de son identifiant
053810669348 n’est observée sur le port 7808. Cela ne prouve ni une caméra
éteinte ni un défaut de carte SIM ; l’état sur une autre plateforme ne décrit
pas celui de sa session EXADCAM. Réenregistrement du serveur principal et
relance de connexion demandés à l’utilisateur pour observer sa prochaine
tentative. Aucun redémarrage distant ni modification automatique d’identité.

Contrôles : liste et essais dans le navigateur authentifié, journaux GPS/vidéo,
positions et horodatages en base, sockets et capture TCP limitée au port 7808.
UFW inactif. Captures dans /var/backups/exadcam-diagnostic-20260922, répertoire
protégé ; résumés expurgés des secrets et des corps média. Scripts d’analyse
temporaires uniquement, pas de modification du code ou des réglages de
production, pas de redémarrage de service et pas de nouvelle suite de tests.
Les lecteurs d’essai ont été fermés ; aucune lecture créée ne reste active.

## 22 septembre 2026 — Administration cloisonnée par flotte

Demande : reprendre la logique EXAD Tracking, permettre à l’admin de gérer ses
véhicules, départements, dashcams avec visibilité réduite, et de choisir les
accès des utilisateurs simples de sa flotte. Référence consultée en lecture seule.

Réalisation : contrôle central FleetAccess, quatre permissions, filtre serveur des
listes/compteurs/options, validation des affectations, projection client sans IMEI
ni données de connexion, formulaires adaptés, tableau de bord client réel. Direct
limité à la flotte, renouvellement lié à la session et permission revérifiée,
révocation sur transfert interflotte. app:create-user exige --fleet pour admin/user.
Le compte Admin Voda était déjà associé à VODA FLEET ; aucun compte métier modifié.

Contrôles locaux : suite complète Laravel, 204 tests / 1130 assertions, passée
avant le dernier complément de révocation. Après ce complément, 37 tests ciblés
FleetAdministrationTest et DashcamRegistryTest / 303 assertions passés. Pint des
fichiers PHP modifiés ; node --check sur les trois scripts modifiés réussi.
Navigateur avec SQLite isolé : liste limitée à la flotte, création effective d’un
véhicule dans sa flotte avec son département, liste dashcams sans identifiant,
modale d’édition limitée au nom/véhicule, cases de permissions et rôle utilisateur
unique pour l’admin. Quelques commandes navigateur ont expiré ; les résultats
ont été contrôlés par snapshots et capture, sans modifier la base de production.

Fichiers : contrôleurs DashboardPreview, Dashcam et FleetRegistry, support
FleetAccess, VideoLeaseRevoker, modèle User, commande et routes ; services carte,
vues/JS des registres et comptes, traductions FR/EN, tests et documentation.
Pas de migration de schéma ni de modification des identités des caméras.
Limites : provisionnement matériel superadmin, aucune suppression ajoutée aux
registres, bail média expirant après 60 s sans renouvellement ; détails dans
fleet-administration.md. Validation du flux réel indépendante des tests d’accès.

Déploiement validé à 19:15 UTC : 37 fichiers installés, sauvegarde SQL et code
dans /var/backups/exadcam-admin-fleet-20260922-191547. Vues recompilées, routes
rafraîchies et PHP-FPM rechargé ; cache applicatif des baux conservé, aucun
redémarrage des écouteurs. Comparaison avant/après : comptes, mots de passe,
permissions enregistrées, véhicules, affectations et identités caméras inchangés.
Apache, PHP-FPM, GPS et vidéo actifs.

Contrôles production en lecture seule avec le contexte Admin Voda : une flotte
(VODA FLEET), deux véhicules, deux dashcams, aucun département ; aucune donnée
de l’autre flotte malgré un filtre forgé. JSON et HTML sans IMEI/alias techniques,
carte limitée aux deux véhicules avec permission vidéo. Page Utilisateurs du
superadmin vérifiée dans le navigateur après déploiement, comptes existants intacts.
Contrôle navigateur local supplémentaire : utilisateur autorisé seulement aux
véhicules et vidéos, sans menus utilisateurs/départements/carte ni actions
modifier/désactiver dans la liste dashcams ; aucune erreur console relevée.

## 2026-09-22 — Menu de flotte simplifié et centrage cartographique

Demande : supprimer le groupe Flottes côté admin, remonter ses rubriques au premier
niveau et centrer la carte sur le véhicule en déplacement comme dans EXAD Tracking.

Réalisation : sidebar conditionnelle conservant le groupe pour le superadmin ;
entrées directes filtrées par droits pour les comptes de flotte. Suivi coché par
défaut, priorité initiale au véhicule mobile en ligne avec le GPS le plus récent,
maintien de la sélection, suivi pendant et hors animation, suspension au glissement
manuel et préservation de la vue d'ensemble. Version des ressources actualisée.

Fichiers : partials/sidebar.blade.php, partials/dashboard-map.blade.php,
public/js/google-map.js, public/js/map-motion.mjs, tests/js/map-motion.test.mjs
et documentation de la carte / administration de flotte.

Contrôles ciblés : 18 tests Laravel / 153 assertions ; 11 tests Node et syntaxe JS
réussis. Navigateur local : menu direct vérifié pour admin et utilisateur restreint,
carte centrée sur le véhicule synthétique mobile et suivi coché. Aucun essai routier
réel réalisé dans ce lot. Aucun changement de base métier ou de protocole caméra.

Déploiement effectué et vérifié le 22 septembre 2026 : 9 fichiers comparés
à l'archive installée, vues recompilées, PHP-FPM rechargé. Sauvegarde :
/var/backups/exadcam-nav-focus-20260922-194346. Les quatre services sont actifs ;
les comptes, affectations et identités caméra sont inchangés. Aucun redémarrage
des écouteurs. Navigateur de production : groupe Flottes du superadmin conservé.
Navigateur local : déplacement du centrage à réception d'un nouveau relevé,
sélection manuelle conservée, vue collective et suspension du suivi au glissement
vérifiés. Aucune mesure routière réelle dans ce lot.

## 23 septembre 2026 — Véhicule 2 connecté, GPS ancien

Signalement utilisateur : la caméra de Véhicule 2 est connectée mais sa position
ne se met plus à jour. Diagnostic en lecture seule, caméra ES500-603, fiche 2.
Dernier GPS valide : 06:44:25 UTC (07:44:25 à Kinshasa), reçu à la même seconde.
Les bornes d'affectation et le fuseau de 60 minutes sont corrects.

Capture bornée du port 7808 pendant 40 secondes, entre 08:21:48 et 08:22:28 UTC :
4 messages 0x0200 JT808 2013, tous avec statut 5 et bit de validité GPS désactivé ;
2 battements 0x0002. Le serveur acquitte les 6 messages avec succès (résultat 0).
Aucune position récente valide n'est fournie par l'appareil pendant cette fenêtre.
Pas de rejet GPS journalisé pour cet appareil depuis 06:40 UTC au contrôle.
À 08:23:08 UTC, FleetMapService confirme online=true, state=stale et la présence
de la dernière position connue de 06:44:25 UTC. Ce n'est pas une disparition du
véhicule liée aux droits ou au filtre d'affectation.

L'utilisateur confirme que la caméra est sous un toit ou dans un bâtiment.
Cette situation est compatible avec une perte de réception satellite, sans
perte d'Internet ; la cause matérielle précise n'est pas démontrée à distance.
Prochain essai : caméra alimentée à l'extérieur avec ciel dégagé pendant quelques
minutes, puis vérifier la reprise de positions valides. Reprise non encore observée.

Capture protégée : /var/backups/exadcam-diagnostic-20260923/vehicle2-082148.pcap
avec résumé expurgé, sans secrets ni corps média dans la documentation.
Aucun code, réglage, protocole, compte ou donnée métier modifié ; aucun service
redémarré, aucune commande de redémarrage envoyée. Pas de tests applicatifs rejoués
pour ce diagnostic sans changement de code.


## 23 septembre 2026 — Fluidité du direct, correctif local en attente de déploiement

Demande : éviter les rechargements répétés ; accepter une réserve de 15 secondes.
Précision utilisateur : ne pas afficher de chargement dans le parcours vidéo.
Avant modification, l'essai ES500-603 / Véhicule 2 montre seulement 1,3 à 1,7 s
d'images d'avance dans le navigateur ; le serveur conserve cinq segments, soit 10 s.
Le lecteur utilise liveSyncDurationCount=2, trop proche du bord pour ce besoin.
L'essai a été fermé et aucune session d'essai n'est laissée active.

Correctif LOCAL : lecteur partagé live-player.mjs pour carte et registre, réserve
continue de 15 s avant lecture, cible HLS à 18 s du bord, fenêtre serveur de vingt
segments de deux secondes (environ 40 s) et cinq segments supplémentaires avant
suppression. Si la réserve s'épuise, pause sur la dernière image puis reprise après
reconstitution ; la pause manuelle reste respectée. Aucun texte de mémoire tampon,
compteur ou animation de chargement ajouté : aperçu neutre avant la première image,
image conservée pendant le remplissage suivant. Les erreurs réelles restent visibles.
Le flux reste H.264 sans réencodage ; les réglages JT808 et les droits sont conservés.

Tests réellement exécutés sur le code modifié : 40 tests Laravel / 317 assertions,
11 tests Node du lecteur et des baux, syntaxe JS et PHP. Navigateur local : page
authentifiée chargée sans erreur console. Les tests de tampon couvrent démarrage,
trous entre segments, reprise, pause manuelle, fermeture tardive, indépendance des
canaux, erreur fatale, attente bornée, lecture native simulée et autoplay refusé.
Le test FFmpeg a été adapté à une fenêtre d'au moins 40 s mais N'A PAS été exécuté
sur cette version : FFmpeg et WSL sont indisponibles dans l'environnement local.

État : NON DÉPLOYÉ. Le contrôle automatique d'approbation a refusé deux fois l'envoi
de l'archive de tests vers le serveur utilisateur, demandant une autorisation explicite
de cet export précis malgré les autorisations antérieures rappelées. Aucun transfert
du correctif, aucun redémarrage et aucun changement de production dans ce lot.
À faire après confirmation : tests FFmpeg isolés, sauvegarde et déploiement du web
et du service vidéo, puis essais réels prolongés des deux modèles et des deux vues.
Une coupure réseau dépassant la réserve peut encore figer l'image ; aucune promesse
de continuité absolue ou de délai initial invisible. Lecteur Safari réel non validé.

Fichiers : public/js/live-player.mjs, map-video.mjs, google-map.js, dashcams.js,
public/css/dashcams.css, deux vues Blade, traductions FR/EN, listener/src/video.js,
tests du lecteur et test FFmpeg. Ressources versionnées live-buffer-1.


## 23 septembre 2026 — Déploiement autorisé et calibration du direct

L'utilisateur a explicitement autorisé le déploiement après le blocage du
transfert noté dans l'entrée précédente. Cette entrée remplace son état « non
déployé » ; elle ne supprime pas la trace du contrôle initial.

Sauvegarde complète de la base et des fichiers concernés sous
/var/backups/exadcam-live-buffer-20260923-084409, puis installation atomique des
17 fichiers du lot et du miroir listener/src/video.js dans /opt/exadcam-listener.
Hashes des 18 cibles vérifiés, vues recompilées, PHP-FPM rechargé et service vidéo
redémarré. Apache, PHP-FPM, GPS et vidéo actifs. Aucun redémarrage du service GPS.
Comptes, secrets, affectations et paramètres des appareils conservés lors de
l'installation ; calibration ciblée de frame_rate effectuée ensuite, avec une
sauvegarde dédiée des deux fiches avant modification.

Avant production, 10 tests listener ont réussi sur Linux dans un dossier isolé,
sans connexion aux appareils réels, dont le test FFmpeg avec 20 segments et
au moins 39 secondes conservées. Les 40 tests Laravel / 317 assertions et les
11 tests du lecteur/baux avaient déjà réussi localement au lot précédent ; ils
n'ont pas été rejoués comme une suite complète de production.

Le tampon seul ne suffisait pas : captures bornées du port média, réassemblage
TCP ordonné et horodatages JT1078 démontrent une cadence inférieure à la valeur
enregistrée de 15 images/s. Les deux canaux ont été mesurés. Seules la JK114
fiche 1 (10 images/s) et l'ES500-603 fiche 2 (12 images/s) ont été calibrées.
Pas de changement de protocole, de commande de redémarrage matériel ni de
réécriture globale des autres caméras. Captures et sauvegardes protégées dans
le dossier du déploiement ; aucun contenu média ni secret dans la documentation.

Essais après calibration, dans le navigateur de production authentifié :
- JK114 canal 1, panneau Carte : progression de 8,71 à 198,65 secondes pour
  189,96 secondes écoulées entre relevés ; lecture active à chaque contrôle,
  réserve de 17,29 à 27,35 secondes. Aucun rechargement constaté dans cette fenêtre.
- ES500 canal 1, modale Dashcams : lecture démarrée, progression de 77,07 à
  171,54 secondes pour 94,45 secondes écoulées entre relevés ; réserve stable
  de 17,93 à 18,49 secondes. Décodage réel 960x540 et horodatage superposé visible.
- ES500 canal 2 : première lecture calibrée active, réserve de 18,87 à 21,22 s
  sur les relevés initiaux, puis interruption de réception ; journal serveur
  source_disconnected à 09:01:20 UTC. Cette déconnexion n'est pas corrigée par
  un tampon et sa cause réseau/appareil n'a pas été déterminée. Le second essai
  sur canal 1 a ensuite démarré et fonctionné normalement.
- Contrôle final du journal : la JK114 canal 1 a également perdu son flux
  source à 09:05:29 UTC, après la fenêtre de lecture continue mesurée ci-dessus.
  La cause de ces déconnexions source reste à déterminer ; ne pas les présenter
  comme résolues par la calibration ou la réserve de lecture.
- Les deux playlists actives contiennent 20 segments : 40 s pour JK114 et 50 s
  pour ES500 (segments sur les images clés). Lecteur partagé testé dans les deux
  vues ; fermeture des lecteurs d'essai à la fin de la vérification.

Ces mesures valident une amélioration de la réserve et de la progression du
direct sur les appareils testés, pas une endurance de plusieurs heures ni la
continuité lors d'une coupure dépassant la réserve. La cadence variable n'est
pas encore pilotée par les horodatages JT1078 dans le muxer. Safari réel non testé.
Pas de nouvelle validation du GPS dans ce lot ; la vidéo et la validité GPS sont
deux observations distinctes.


Documentation courante mise à jour : project-context.md, listener-server.md,
device-commissioning.md et ce journal. Correctif partagé sur Carte et Dashcams,
ressources versionnées live-buffer-1. Une coupure longue peut figer l'image ;
pas de promesse de continuité absolue ni d'endurance validée sur plusieurs heures.


## 23 septembre 2026 — Correction du démarrage vidéo après signalement

L'utilisateur signale que les vidéos ne démarrent plus après live-buffer-1.
Diagnostic : Apache, PHP-FPM et les écouteurs fonctionnent. L'ancien lecteur
attend huit segments avant même de télécharger/décoder les premières images,
puis conserve l'aperçu neutre jusqu'à 15 secondes de réserve : un flux lent
donne donc l'impression de ne rien charger. La JK114 de Véhicule test 1 a bien
démarré pendant l'essai initial ; ce n'est pas une panne globale du service.

Toyota Hilux ES500 fiche 4 : début de média reçu (3 segments / 6 secondes),
puis absence de nouveaux paquets et perte de la connexion GPS à 09:19:24 UTC.
Le flux est arrêté par le contrôle de présence source à 09:19:26 UTC. Capture
ultérieure de 30 secondes sans paquet média. Les fiches 2 et 1 se déconnectent
ensuite à 09:19:30 et 09:20:05 UTC. Leur cause de déconnexion n'est pas connue ;
question envoyée à l'utilisateur sur l'alimentation et l'accès via l'autre app.

Correction live-buffer-2 du lecteur partagé : chargement des fragments dès le
premier segment, première image réellement décodée visible pendant la constitution
de la réserve, puis lecture automatique à 15 secondes. Aucun indicateur animé
de chargement. Traitement du début de chronologie MPEG-TS supérieur à zéro pour
éviter d'attendre une plage qui précède les premières images disponibles.
Les pauses manuelles, les contrôles d'accès et la limite d'attente restent actifs.

Fichiers : live-player.mjs, dashcams.js, google-map.js, versions des deux vues
Blade et tests/js/live-player.test.mjs. 13 tests Node lecteur/baux réussis et
contrôles de syntaxe. Pas de suite Laravel complète rejouée pour ce correctif JS.
Six fichiers déployés et hashes vérifiés, vues recompilées, PHP-FPM rechargé.
Aucun redémarrage GPS/vidéo, aucun changement d'identité ou d'affectation.
Sauvegarde : /var/backups/exadcam-live-recovery-20260923/reader.

Navigateur de production : ressources live-buffer-2 confirmées dans une nouvelle
page. JK114 Toyota Hilux 0210BW01 : première image visible au relevé à 16 secondes,
720x576, readyState=4, réserve 8,47 secondes, lecture encore en pause volontaire
jusqu'au seuil. Mesures de lecture et de cadence complémentaires ci-dessous.

Référence primaire : [configuration initialLiveManifestSize de HLS.js](https://github.com/video-dev/hls.js/blob/master/docs/API.md#initiallivemanifestsize).

Complément de diagnostic sur la fiche 3 JK114 du Toyota Hilux 0210BW01 : les
refus/timeouts de démarrage observés à 09:13-09:14 UTC ne se reproduisent pas
sur les essais suivants. Les deux canaux fournissent une vidéo décodable. Les
captures séparées CH1/CH2 mesurent chacune 244 images pour 24,269 secondes de
chronologie JT1078, soit environ 10,01 images/s. À 15 images/s configurées,
la réserve décroît rapidement et le lecteur revient en pause de remplissage.
Sauvegarde de la fiche puis changement ciblé frame_rate=10 pour l'identifiant
3 ; aucun réglage de firmware, de protocole, d'identité ni d'affectation modifié.
La fiche 4 reste à 15 faute de flux permettant de mesurer sa cadence réelle.
Les captures restent dans /var/backups/exadcam-live-recovery-20260923, protégé.

Essai matériel après calibration, JK114 fiche 3 canal 2, nouveau lecteur en
production : à 54,06 secondes de l'ouverture, lecture active à 27,96 secondes,
réserve 16,34 secondes ; à 90,30 secondes, lecture active à 64,21 secondes,
réserve 18,09 secondes. La progression suit le temps écoulé sur ces relevés,
sans nouvelle pause observée ; image vidéo vérifiée visuellement. Ceci ne
constitue pas une validation d'endurance de plusieurs heures.

Au contrôle de 09:30 UTC, les fiches 1, 2 et 4 n'ont toujours pas repris leur
connexion. Leurs derniers contacts sont respectivement 09:19:24, 09:18:49 et
09:19:24 UTC. Leurs vidéos ne peuvent pas être déclarées rétablies. La fiche 3
reste connectée. La réponse utilisateur sur l'état physique des trois autres
équipements n'est pas encore reçue. Aucun redémarrage distant de caméra tenté.


### Contrôle complémentaire — 23 septembre 2026, 09:37 UTC

JK114 Hilux 0210BW01, après calibration : à 208,06 s de l'ouverture, lecture
active à 181,96 s, réserve 18,34 s. Progression continue d'environ 154 secondes
entre les relevés à 54 et 208 s, sans nouvelle pause observée. Lecteur fermé
volontairement ensuite. La caméra s'est toutefois déconnectée du serveur GPS
à 09:34:40 UTC, après ce test ; la cause reste inconnue.

L'ES500 de Véhicule 2 s'est reconnectée à 09:31:34 UTC. Nouvelle lecture CH1
vérifiée avec live-buffer-2 : image et lecture actives, réserve 6,93 s au relevé
à 31 s, 21,70 s à 63 s, puis 20,34 s à 89,68 s. La progression entre les deux
premiers relevés indique encore une pause intermédiaire d'environ 7 s ; la
fin de l'essai progresse normalement. Ne pas annoncer une fluidité absolue.
Lecteur d'essai fermé. Aucun flux de diagnostic laissé ouvert.

Au dernier contrôle, Véhicule 2 reçoit des contacts récents. Les fiches 1 et 4
restent sans reconnexion, et la fiche 3 s'est déconnectée après son essai réussi.
La correction du lecteur est livrée et testée ; le rétablissement durable de
tous les appareils dépend encore du diagnostic de leurs pertes de connexion.
Question utilisateur sur leur alimentation et accès via l'autre application
toujours sans réponse à ce stade. PHP-FPM ne journalise pas de saturation dans
la période inspectée ; aucune cause réseau/matérielle précise n'est démontrée.

Le premier transfert de la documentation a été refusé par le contrôle automatique.
Après vérification des quatre fichiers, recherche de secrets et rappel de la
destination EXADCAM explicitement autorisée, la nouvelle demande a été acceptée.
Documentation synchronisée ; le blocage d'export est résolu.


## Reconnexion automatique du direct — 23 septembre 2026

Demande : les ES500 s'arrêtent et obligent à recliquer sur Lecture. Les logs
montrent notamment deux fermetures source des canaux 1 et 2 à 09:52:04 et
09:52:15 UTC. L'ancien client libérait la session puis abandonnait la lecture.
La cause de ces coupures source n'est pas déterminée par ce correctif.

Version live-reconnect-1 déployée dans Carte et Dashcams. Une interruption
temporaire renouvelle automatiquement le bail du canal demandé, après 3, 5, 10,
20 puis 30 secondes maximum entre tentatives. Chaque nouveau démarrage repasse
par les autorisations Laravel. Une révocation d'accès ou une session utilisateur
expirée arrête les tentatives. Les réponses tardives et erreurs simultanées ne
créent pas plusieurs boucles. Arrêter, fermer ou quitter annule la reprise ; une
pause manuelle reste respectée. Chaque canal est indépendant.

Une seule image fixe peut être conservée en mémoire du navigateur (largeur
maximale 960 px) pendant la reprise ; elle est effacée à la fermeture et jamais
envoyée ni enregistrée. Réserve de lecture 15 s maintenue. Requêtes client bornées
à 15 s. Une coupure prolongée peut donc figer l'image ; la reprise automatique
ne garantit pas l'absence de toute interruption et ne répare pas la liaison mobile.

Validation réellement exécutée dans ce lot : 24 tests Node ciblés du lecteur
et des baux, réussis localement puis sur Linux avant installation ; contrôle de
syntaxe des quatre scripts et des quatre traductions. Pas de nouvelle suite
Laravel complète ni de suite listener : leur code n'a pas changé.

Douze fichiers déployés avec vérification SHA-256 et sauvegarde préalable dans
/var/backups/exadcam-live-reconnect-20260923/reader. Vues Blade recompilées et
PHP-FPM rechargé ; aucun redémarrage des écouteurs GPS/vidéo. Modifications :
public/js/{live-player.mjs,map-video.mjs,dashcams.js,google-map.js}, deux vues
Blade, traductions map/dashcams FR/EN et les deux tests JavaScript associés.

Toyota Hilux ES500 fiche 4 (9863BV01) : captures JT1078 des deux canaux.
CH1 : 322 images, intervalle médian 88 ms, une lacune de 14 735 ms ; CH2 :
172 images, médiane 84 ms, une lacune de 14 534 ms. Les moyennes globales
7,41 et 5,90 images/s incluent ces lacunes : elles ne sont pas utilisées comme
cadences nominales. La cadence courante est proche de 12 images/s, inférieure
aux 15 enregistrées. Sauvegarde de la fiche puis calibration ciblée à 12 images/s.
Aucun changement de firmware, d'identité, de protocole ou d'affectation.
Captures et sauvegarde SQL conservées dans le répertoire protégé
/var/backups/exadcam-live-reconnect-20260923.

Essai réel du renouvellement, liste Dashcams, ES500 fiche 4 canal 2 : le changement
de cadence invalide le flux à 10:14:59 UTC. Le lecteur redemande automatiquement
le canal à 10:15:04 UTC. À 10:15:08, la dernière image reste en poster, état
« reprise automatique ». À 10:15:42, lecture active (readyState=4, temps 4,14 s,
réserve 10,94 s), sans aucun nouveau clic sur Lecture. Cette reprise est consécutive
à la calibration, pas à une coupure réseau provoquée. La page neuve charge bien
live-reconnect-1. Canal 2 également actif sur la Carte à 10:15:59 UTC.

Contrôle complémentaire réel : déconnexion spontanée des deux canaux à 10:17:01
UTC, nouveaux démarrages automatiques CH2 à 10:17:04 et CH1 à 10:17:09.
Dernières images conservées et aucune demande de recliquer. Arrêter CH1 ferme
uniquement son bail à 10:17:14 ; sa lecture ne redémarre pas. CH2 reste suivi
mais une nouvelle coupure survient à 10:17:56, suivie d'une autre tentative à
10:18:02. La liaison est donc encore intermittente : ne pas présenter les
coupures source comme résolues. Le dernier essai de reconnexion naturelle n'a
pas été observé jusqu'à une nouvelle lecture active ; le renouvellement après
calibration, lui, a bien repris la lecture. Tous les lecteurs d'essai ont été
fermés et l'actualisation Auto de la carte a été rétablie. Safari réel et
endurance de plusieurs heures non testés.


## 23 septembre 2026 — Deux canaux réels sur le tableau de bord

Demande : afficher les deux canaux en direct dans la zone caméra du tableau de bord, avec choix de véhicule recherchable.

L'ancien aperçu fictif du tableau de bord est remplacé par « Caméras en direct ».
Un sélecteur recherchable par nom de véhicule, immatriculation ou flotte propose
uniquement les véhicules réellement affectés à une dashcam activée et autorisée.
Choisir un véhicule démarre automatiquement les canaux 1 et 2 côte à côte sur
ordinateur ; les lecteurs passent l'un sous l'autre sur petit écran. Un appareil
à un seul canal affiche le second comme non configuré, sans requête inutile.

Le lecteur réutilise live-reconnect-1 : réserve de 15 s, reconnexion automatique,
arrêt indépendant et pause manuelle. Changer de véhicule libère les anciens baux ;
une sélection remplacée ou une navigation ne peut lancer un flux tardif. Quitter
le tableau de bord ferme les deux lecteurs et vide la sélection. Aucun flux n'est
ouvert à l'arrivée sur le tableau de bord avant un choix explicite.

Visible au superadmin et aux comptes ayant video.view dans une flotte active,
même sans accès à la carte. Les choix sont limités à la flotte du compte et ne
contiennent aucun IMEI, identifiant de communication ou secret. Si un véhicule
possède plusieurs dashcams activées, celle ayant le contact le plus récent est
choisie (id croissant en cas d'égalité). Les autorisations des routes vidéo
existantes continuent de s'appliquer à chaque démarrage et renouvellement.

Contrôles réellement exécutés : 20 tests Laravel ciblés, 162 assertions
(DashboardVideoTest et FleetAdministrationTest), SQLite en mémoire ; 28 tests
Node du lecteur, des baux et de la sélection à deux canaux. Quatre nouveaux
tests de coordination également réussis sur Linux avant installation. Syntaxe
PHP/JS vérifiée. Une collision d'identifiants dans les premières fixtures de
test a été corrigée avant leur réussite ; aucun changement des identités réelles.
Pas de suite complète Laravel/listener ni d'endurance vidéo rejouée dans ce lot.

Onze fichiers installés et hashes vérifiés. Sauvegarde :
/var/backups/exadcam-dashboard-live-20260923/before. Vues recompilées, PHP-FPM
rechargé, Apache/GPS/vidéo actifs ; aucun redémarrage des écouteurs, migration,
changement du registre des caméras ou du serveur Node.

Navigateur de production : recherche « EXAD » puis « 9863 », sélection des bons
véhicules, deux zones côte à côte d'environ 278 × 157 px en 16:9, démarrage des
deux demandes, changement de véhicule et remise à zéro en quittant le tableau
de bord vérifiés. Inspection visuelle effectuée. Les appareils étaient hors
connexion au test vers 13:18 UTC : les demandes retournaient Service 409 côté
écouteur. La reprise automatique restait active ; aucune image vidéo réelle
n'a été validée depuis ce nouveau composant pendant cet essai. Les tests ont
été fermés. La disponibilité physique des caméras est distincte de cette livraison.

Fichiers : DashboardPreviewController, welcome/client-dashboard, nouvelle vue
partials/dashboard-video, dashboard-video.css, dashboard-video.mjs,
dashboard-video-session.mjs, traductions FR/EN et deux fichiers de tests.

## 23 septembre 2026 — Connexions ES500 et limitation des rafales

L'utilisateur confirme que les deux ES500 sont joignables à distance dans
CarAssist. Leur liaison vers cette plateforme est indépendante de la session
JT808 vers EXADCAM : l'absence de connexion mobile n'est pas retenue comme cause.

Les journaux EXADCAM du jour montrent neuf fermetures « Message rate limit » :
huit pour la fiche 4 (Toyota Hilux 9863BV01), dont la dernière à 12:08:45 UTC,
et une pour la fiche 2 (Véhicule 2) à 13:15:46 UTC. Véhicule 2 se reconnecte à
13:19:29 puis se déconnecte à 13:23:41 sans motif enregistré par l'ancien code.
Les neuf rejets n'expliquent donc pas toutes les déconnexions observées. Aucun
paquet reçu pendant une capture préalable de 50 secondes ; la nature des
messages des rafales réelles reste à identifier. Les anciennes captures
consultées montrent seulement des échanges ordinaires, pas la rafale fautive.

Le défaut est reproduit par un test TCP isolé : 120 battements suivis d'une
position valides déconnectent une caméra authentifiée avec l'ancien code.
Correction dans listener/src/gps.js : traitement plafonné à 50 messages par
fenêtre d'une seconde, avec attente et contre-pression TCP pour les sessions
authentifiées, au lieu de fermer au 51e message. File applicative bornée à
64 Kio et parseur borné conservés. Le plafond reste bloquant avant
authentification ; les autorisations sont revérifiées pendant le traitement
des rafales. L'arrêt du service annule l'attente et les messages restants.

Les journaux indiquent désormais le motif de fermeture (pair distant,
inactivité, nouvelle connexion du même appareil, déconnexion demandée,
révocation, erreur socket ou arrêt du service), le nombre de messages et
de fenêtres ralenties. Le résumé gps_paced inclut les types de messages
(au plus un résumé toutes les 30 secondes par connexion), sans corps de
message ni jeton. Cette instrumentation doit permettre de distinguer les
autres causes ; elle ne prouve pas encore l'origine des rafales ES500.

Validation exécutée pour ce lot : test de régression d'abord en échec sur
l'ancien code ; 11 tests GPS/protocole ciblés réussis localement ; suite
listener complète sur Linux isolé, 14 tests réussis, aucun ignoré, avec FFmpeg.
Les nouveaux tests vérifient les acquittements et la position après une rafale,
le refus des rafales non authentifiées, la révocation pendant une file active
et l'annulation à l'arrêt. Aucune suite Laravel ou navigateur du lecteur
rejouée comme substitut à une validation matérielle.

Déployé à 13:43:02 UTC : copie applicative et runtime /opt/exadcam-listener
vérifiées par SHA-256, nouveau test conservé dans le projet. Sauvegarde :
/var/backups/exadcam-es500-connections-20260923/before. Seul exadcam-gps est
redémarré. Services GPS/vidéo et web actifs, contrôles /health internes 200 ;
port public TCP 7808 accessible depuis l'extérieur. Aucune migration,
modification des identifiants, changement de protocole 2013 ou réglage CarAssist.

Au premier contrôle après déploiement, les deux ES500 n'ont pas encore de
session TCP (statut interne 409). Une reconnexion des deux appareils a été
demandée et l'utilisateur répond « les 2 ». Le suivi matériel qui suit doit
être consigné séparément : ne pas annoncer leur retour en ligne ni leur
stabilité vidéo avant observation effective.


### Suivi matériel après le correctif — 23 septembre 2026

L'utilisateur confirme avoir redémarré les deux appareils et que les valeurs
CarAssist n'ont pas changé. Le Toyota Hilux 9863BV01 (fiche 4) s'authentifie à
13:47:23 UTC, toujours en JT808 2013. Ses positions GPS redeviennent récentes.
Le pare-feu nftables ne présente aucune règle ; le port 7808 est ouvert depuis
l'extérieur. La capture des ports 7808/6608/808/1078 confirme ensuite les
échanges TCP 7808 du Hilux. Aucun nouvel essai de connexion de Véhicule 2 n'est
observé dans cette fenêtre ; son dernier contact reste 13:23:41 UTC.

Essai réel depuis le nouveau tableau de bord : sélection du Hilux, demandes
CH1 et CH2 à 13:49:03 UTC. Deux images réelles côte à côte, 960 × 540, lecture
active et progression observées dans le navigateur ; inspection visuelle
effectuée. CH1 perd ensuite sa source à 13:50:57 puis est relancé à 13:51:03.
Les deux sources se déconnectent à 13:51:18 ; nouveaux démarrages automatiques
à 13:51:24. Reprise effective des deux lectures observée ensuite à plus de
20 secondes, sans nouveau clic. La session GPS demeure active durant ces
coupures vidéo. Ne pas assimiler le correctif des rafales GPS à une résolution
complète de l'instabilité média ; la cause de ces fermetures vidéo reste à
isoler. La reconnexion de Véhicule 2 reste également non confirmée.

Dernier contrôle à 13:53:47 UTC : Hilux toujours GPS actif, aucune nouvelle
fermeture GPS ni fenêtre ralentie journalisée depuis sa reconnexion ;
Véhicule 2 toujours sans nouvelle session. Les deux vidéos ont de nouveau
progressé jusqu'à environ 87 secondes après leur reprise. Une capture de
50 secondes des en-têtes TCP vidéo ne reproduit aucune fermeture FIN/RST :
elle ne permet pas d'attribuer les coupures antérieures. Les deux canaux
d'essai ont été arrêtés explicitement, puis l'onglet de test fermé.


## 23 septembre 2026 — Reprise du diagnostic de Véhicule 2

Véhicule 2 / ES500-603, fiche 2, s'authentifie à 13:57:49 UTC (14:57:49
Kinshasa), toujours avec l'identifiant 053810725721 et le protocole 2013.
Statut interne /status 200, online=true ; positions reçues en base.

Nouvelle coupure à 13:59:00.275 UTC : journal peer_closed, 13 messages traités,
aucune fenêtre ralentie, durée 71 secondes, dernier message 0x0002. La capture
réseau montre un FIN entrant avant le FIN du serveur. La caméra n'a pas
acquitté les 40 derniers octets de réponses 0x8001 (position et battement),
retransmis ensuite par le serveur. Les premières demandes vidéo du navigateur
arrivent après cette fermeture et échouent en 409 : elles n'ont donc pas
déclenché la coupure observée. Cette preuve localise l'initiative de fermeture
du côté distant ; elle ne permet pas de trancher entre firmware, réglage de
l'appareil et perturbation du chemin réseau. Le manque d'acquittements ne
permet pas d'accuser précisément l'opérateur. Référence de lecture des drapeaux
TCP : [RFC 9293, fermeture](https://www.rfc-editor.org/rfc/rfc9293.html#section-3.6).

Nouvelle authentification automatique à 14:00:51.812 UTC. La capture de suivi
contient six positions avec fix GPS valide, trois battements et leurs réponses
de succès. Commande de direct 0x9101 acquittée avec résultat 0. Canal 1 demandé
à 14:01:35 et canal 2 à 14:02:18. Les deux vidéos sont ensuite réellement lues
sur le tableau de bord : 960 × 540, readyState=4, paused=false, aucune erreur
média et temps en progression (64 s / 21 s au premier contrôle commun).
Inspection visuelle réalisée avec deux images réelles côte à côte.

À 14:03:35 UTC, le contact et la position sont récents, sans nouvelle fermeture
GPS ni vidéo journalisée depuis cette reconnexion. Cette fenêtre courte ne
constitue pas un test d'endurance et n'établit pas la disparition de toutes
les coupures. Aucun nouveau code, réglage, identifiant, protocole, délai ou
service n'a été modifié ou redémarré dans cette reprise. Aucune suite de tests
automatisés rejouée : diagnostic en production et essais matériels uniquement.
Captures réservées à root : /var/backups/exadcam-vehicle2-resume-20260923.

Contrôle complémentaire : CH1 se ferme à 14:04:04 UTC (source_disconnected),
puis redémarre automatiquement à 14:04:12. Sa lecture a repris à plus de
16 secondes au dernier contrôle, tandis que CH2 atteint environ 130 secondes.
Le GPS reste connecté, dernier contact/position 14:04:45 UTC. Les deux flux
ouverts pour cet essai ont été arrêtés explicitement via leurs boutons.
La cause exacte de la fermeture média reste inconnue : ne pas annoncer
une lecture continue garantie. Aucun changement serveur supplémentaire.


## Tableau de bord réel — 23 septembre 2026

Demande : remplacer les indicateurs, tableaux et alertes de démonstration, montrer
le statut dans la recherche de véhicule, afficher carte et caméras avant l'activité,
supprimer l'espace vide et le centre vidéo redondant.

Implémentation : DashboardService fournit les compteurs et véhicules autorisés,
les choix vidéo avec état de connexion, les agrégats GPS et les alertes. Les routes
authentifiées /dashboard/data et /dashboard/alerts actualisent les données et
paginent les alertes par 10. La liste des véhicules est filtrable et paginée par 10.
Le dashboard partage désormais ces composants entre superadmin et comptes clients.
Les deux panneaux carte/caméras sont de même hauteur ; le dashboard désactive les
gestes, contrôles et clics sur marqueurs, tandis que la page Carte reste interactive.
Les deux lecteurs gardent leurs sessions lors d'une actualisation des statuts.
Le menu et les boutons Centre vidéo et les anciens exemples ont été retirés.

Données : connexion récente = dernier contact de moins de 3 minutes. Le graphique
compte les véhicules distincts avec GPS valide par heure (24 tranches) ou jour
(7 jours), et ceux ayant un relevé en déplacement (ACC allumé, vitesse >= 3 km/h).
Il ne reconstruit pas un historique de connexion à partir du dernier contact.
Heures affichées : Africa/Kinshasa. Les compteurs concernent les caméras activées.

Alertes : nouveaux bits d'alarme JT808 enregistrés sur 7 jours, comparés au relevé
précédent, y compris avant le début de la période pour éviter les répétitions.
Les pertes de contact en cours apparaissent avec la date réelle du dernier signal,
et disparaissent à la reconnexion. Aucun événement artificiel de déconnexion passée.
Les bornes d'affectation dashcam/véhicule/flotte et les permissions sont appliquées.
Sans map.view, pas d'alertes, de vitesse, de mouvement ou d'historique GPS exposé.
Les réponses du dashboard ne contiennent aucun IMEI ni paramètre de communication.

Limites : seules les alarmes déjà conservées dans dashcam_positions.alarm sont
exploitables. Les extensions ADAS/DMS propriétaires et les alarmes sans position
GPS rejetées par le décodeur actuel ne sont pas ajoutées par ce changement.
Les libellés indiquent des signalements de l'équipement, pas des incidents vérifiés.
Référence consultée pour les bits standard :
https://raw.githubusercontent.com/traccar/traccar/master/src/main/java/org/traccar/protocol/Jt808ProtocolDecoder.java

Vérification locale : suite Laravel complète, 219 tests / 1219 assertions réussis,
36 tests JavaScript réussis ; contrôles de syntaxe PHP/JS. Tests ajoutés pour
compteurs réels, permissions, réaffectation, GPS invalide, répétitions d'alarme,
frontière de période, déconnexion/reconnexion et pagination.
Prévisualisation navigateur sur base SQLite isolée : statuts visibles/recherchables,
navigation Alertes, ordre des sections, hauteur identique des deux panneaux (421,7 px
sur la fenêtre de validation). Déploiement et contrôle production consignés ensuite.


### Validation sur le serveur — 23 septembre 2026, 14:53 UTC

La vérification MariaDB initiale a révélé un coût de 15,228 s pour plusieurs
instantanés et la pagination. La recherche du précédent signal par paquet a été
remplacée par LAG sur la période et un seul prédécesseur par équipement.
Les bornes d'affectation restent appliquées aux deux branches. Le même contrôle
complet s'exécute ensuite en 0,583 s, puis 0,588 s après installation.
Après cette optimisation : 33 tests ciblés Dashboard/FleetAdministration et
247 assertions réussis ; 4 tests de sélection vidéo réussis. Le complément
« En ligne · état GPS » dans la recherche est couvert par 12 tests / 82 assertions.

Déploiement initial : 25 fichiers sauvegardés/installés et empreintes vérifiées,
cache des vues reconstruit, PHP-FPM rechargé. Sauvegarde :
/var/backups/exadcam-dashboard-real-20260923-145302.
Aucune migration/écriture métier, aucun vidage du cache des leases et aucun
redémarrage des listeners GPS/vidéo. Les quatre services contrôlés sont actifs.

Validation navigateur en production : 4 véhicules équipés, 4 dashcams activées,
8 canaux configurés, 2 connexions récentes au contrôle, concordant avec SQL.
29 alertes dans la période à cet instant ; pages 1–10 puis 11–20 vérifiées.
Les alertes utilisent les bits transmis par les appareils, et ne certifient pas
qu'une collision ou fatigue ait effectivement eu lieu.
La carte d'aperçu n'affiche aucun bouton de contrôle et ses marqueurs ont un rôle
d'image. La page Carte rétablit les contrôles et le rôle de bouton du marqueur.

Essai vidéo de validation à 14:56 UTC : deux canaux de Véhicule 2 réellement
affichés, 960 × 540, readyState=4, paused=false ; progression initiale CH1=13,34 s
et CH2=10,69 s, puis 54,72 s / 52,23 s après plusieurs actualisations du dashboard.
Aucun nouveau video_requested entre l'ouverture à 14:56:00 et la fermeture des
deux sources à 14:57:39 (source_disconnected). La reprise automatique se déclenche.
Cet essai confirme la conservation des sessions pendant l'actualisation des choix,
pas la disparition des coupures propres aux flux ES500 déjà suivies dans le journal.
Les deux flux d'essai sont arrêtés explicitement avant la fin de la validation.

Complément final : état GPS ajouté au statut de recherche pour les comptes avec accès carte ; sauvegarde /var/backups/exadcam-dashboard-real-20260923-145930. Pastille de statut corrigée et feuille de style versionnée dashboard-real-2. Empreintes CSS/Blade identiques en local et en production. Les quatre services sont actifs au contrôle final. Les deux lecteurs de test sont arrêtés et leurs reprises annulées. Les pertes de contact ES500 observées restent à diagnostiquer ; elles ne sont pas corrigées par ce lot dashboard.


## Compteur hors ligne et ordre du menu — 23 septembre 2026

Le widget « Canaux configurés » est remplacé par « Dashcams hors ligne »,
lié à metrics.offline et à son actualisation existante. Il compte les dashcams
activées sans contact récent dans le périmètre autorisé. Le menu Carte suit
immédiatement Tableau de bord ; Utilisateurs suit Rapports. Les conditions
d'autorisation des liens et les regroupements propres au superadmin sont conservés.
Validation ciblée : 27 tests existants RealDashboard, DashboardVideo et
FleetAdministration réussis, 221 assertions. Aucun test supplémentaire créé
pour ces ajustements de vues ; aucune modification du calcul ou des flux vidéo.

Déploiement vérifié sur EXADCAM : quatre fichiers sauvegardés et installés ; sauvegarde /var/backups/exadcam-dashboard-menu-20260923-151401. Contrôle navigateur du menu administrateur en prévisualisation et du menu superadmin en production : Carte en deuxième position, Utilisateurs après Rapports. Le widget Dashcams hors ligne affiche le compteur réel (2 au contrôle production). Les quatre services sont actifs ; aucun listener ni flux vidéo n'a été redémarré.


## Flèche et trace GPS ; diagnostic audio — 23 septembre 2026

Demande : ligne décalée de la flèche, absence de son en direct et possibilité
de parler au véhicule. L'ancrage du marqueur passe du bas au centre (-50 % sur
les deux axes). La trace se termine à la position animée affichée, même lors
d'un rendu intermédiaire. La reprise d'une animation conserve les virages GPS
restants et la trace précédente ; une suspension ou un changement d'état
réaligne ligne et marqueur. Les horodatages interpolés ne quittent pas le navigateur.
Fichiers : google-map.js, map-motion.mjs, dashboard-map.blade.php, tests de
déplacement et documentation. Assets versionnés map-anchor-1.

Validation exécutée : 39 tests JavaScript (11 du déplacement), 16 tests Laravel
FleetMap/DashboardMap, 134 assertions et syntaxe JavaScript réussis. Prévisualisation
sur SQLite isolé : trace reliée au centre de la flèche verticale et diagonale,
attributs d'ancrage confirmés dans le navigateur. Pas de nouvel essai routier.

Diagnostic audio confirmé dans les sources déployées : demande vidéo seule,
paquets audio ignorés, multiplexage FFmpeg avec -an. Le matériel sait parler
via CarAssist sur les deux modèles selon l'utilisateur, mais l'audio et son
retour microphone ne sont pas encore intégrés dans EXADCAM. Aucun changement
du listener, des réglages matériels ou du lecteur vidéo dans ce lot.


Déploiement du correctif cartographique vérifié le 23 septembre 2026 : huit fichiers sauvegardés et installés, empreintes contrôlées ; sauvegarde /var/backups/exadcam-map-anchor-20260923-153454. Vues reconstruites et PHP-FPM rechargé, quatre services actifs, aucun redémarrage GPS/vidéo ni vidage du cache des sessions. Navigateur en production : asset map-anchor-1 chargé et ancrage -50 % sur les deux axes confirmé sur le Toyota Hilux en déplacement. Les essais de géométrie verticale/diagonale utilisent la base locale isolée ; aucun son ni interphone ajouté par ce déploiement.


## Son et interphone — 23 septembre 2026

Demande explicite : intégrer le son et le microphone, déploiement déjà autorisé.
Écouter/Parler/Arrêter et volume ajoutés au dashboard, à la carte et à la liste
Dashcams. Autorisation audio.talk ajoutée aux permissions de gestion de flotte ;
API Laravel liée au compte, à la session web et aux affectations de la caméra.
Service exadcam-audio, passerelle WebSocket, AudioWorklet et conversion FFmpeg
AAC/G.711 ; microphone arrêté sur fermeture, révocation et perte de connexion.

Le premier essai matériel a révélé qu’une commande d’écoute séparée remplace
la vidéo 1 de la JK114. La version finale partage le flux AV pour Écouter et
réserve le canal 1 pour Parler, au lieu de laisser les deux lecteurs se relancer
en concurrence. Le canal 2 reste disponible pendant l’interphone. L’audio a sa
latence propre et peut devancer la vidéo ; aucun enregistrement sonore ajouté.

Contrôles de ce lot : 45 tests PHP ciblés / 358 assertions, 49 tests JavaScript,
21 tests Node sous Linux (FFmpeg réel et HLS compris), tous réussis. Prévisualisation
isolée effectuée ; minuterie navigateur corrigée après détection d’un blocage.
Les essais matériels ont confirmé la réception et le décodage AAC de la JK114.
Aucun micro physique ni parole de test envoyé par l’agent à un véhicule ; retour
haut-parleur à confirmer. Réception ES500 également validée, avec des coupures
de source qui persistent. Détails et limites dans live-audio.md.

Sauvegardes : exadcam-audio-gps-20260923-154415,
exadcam-audio-20260923-162103 et exadcam-audio-20260923-163209 sous /var/backups.
API/code local et serveur enregistrés, services contrôlés actifs à l’installation.
La coordination nécessite le redémarrage GPS/vidéo/audio ; aucune donnée métier
modifiée et aucun vidage du cache Laravel des sessions vidéo.


Complément de validation et version finale audio-3 :

Les limites de requêtes sont séparées par opération (dashboard, carte, vidéo,
début/renouvellement/arrêt audio), après observation d’un 429 partagé. Le test
de saturation confirme que l’arrêt audio reste possible après saturation du
renouvellement. Le correctif et les assets audio-2 ont été sauvegardés sous
/var/backups/exadcam-audio-20260923-164054 sans redémarrage des listeners.

JK114 Toyota Hilux 0210BW01 : écoute continue de 16:39:18 à 16:41:11 UTC,
883 trames AAC reçues, aucun paquet micro transmis. Les deux vidéos 720 × 576
sont en lecture (readyState 4, paused false), à 28,42 s et 41,34 s au contrôle
simultané. Arrêt explicite de l’audio confirmé côté serveur.

ES500 Toyota Hilux 9863BV01 : capacités G.711 A-law mono 8 kHz et sortie audio.
Écoute ouverte à 16:41:40 UTC, 2886 trames reçues, aucun retour micro transmis.
Les deux vidéos 960 × 540 ont été constatées en lecture simultanée au son.
Une interruption de la source audio/canal 1 a ensuite déclenché l’expiration
à 16:42:26, tandis que le canal 2 continuait. La stabilité ES500 n’est donc pas
déclarée résolue. Les deux sessions vidéo d’essai ont été arrêtées explicitement.

Audio-3 ajoute la reprise automatique de l’écoute après 5 secondes, sans jamais
redémarrer le microphone. Un arrêt utilisateur ou un retrait de droits annule
cette reprise ; deux tests supplémentaires vérifient ce comportement.
L’utilisateur indique qu’il testera le retour haut-parleur plus tard.
