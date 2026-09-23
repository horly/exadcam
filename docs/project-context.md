# Contexte de développement EXADCAM

Décisions confirmées au 23 septembre 2026.

La flèche cartographique est ancrée par son centre sur la même coordonnée que
l'extrémité de la trace. Les actualisations pendant l'animation conservent les
virages reçus et n'affichent pas la ligne en avance sur le marqueur.
Le son et le microphone sont intégrés aux trois lecteurs. L’écoute partage le
flux vidéo 1 ; l’interphone réserve ce canal, suspend sa vidéo puis la libère à
l’arrêt du micro. Permission audio.talk et arrêt automatique de la capture.
La réception AAC de la JK114 et G.711 de l’ES500 est confirmée avec la vidéo.
L’écoute reprend automatiquement après coupure ; le micro reste arrêté. Les
coupures de source ES500 persistent. Le retour haut-parleur sera testé plus
tard par l’utilisateur. L’audio peut devancer l’image
qui conserve sa réserve de quinze secondes. Voir docs/live-audio.md.

Le tableau de bord utilise les données réelles et les alertes enregistrées,
avec périmètre de flotte et permissions. Carte d’aperçu non interactive et deux
canaux vidéo précèdent l’activité GPS. Le centre vidéo redondant est retiré.
Voir la dernière entrée du journal et dashboard-video.md pour les vérifications.

Le journal daté des réalisations et des vérifications est disponible dans
[project-history.md](project-history.md). Le compléter après chaque lot significatif ;
ce document décrit le contexte courant.

## Navigation et suivi cartographique — 22 septembre 2026

Les comptes de flotte accèdent directement aux rubriques Véhicules, Dashcams et
Départements autorisées, sans menu Flottes. Le superadmin conserve le regroupement.
La carte privilégie à l'ouverture un véhicule en ligne en déplacement et active
le suivi de la sélection. Le choix reste stable aux actualisations ; un glissement
manuel suspend le suivi et « Afficher tous les véhicules » conserve la vue globale.
Validation ciblée : 18 tests Laravel / 153 assertions et 11 tests Node réussis.

## Gestion par flotte — 22 septembre 2026

Les admins affectés à une flotte active gèrent ses véhicules, départements,
dashcams et utilisateurs simples. Les comptes clients ne reçoivent aucun IMEI
ni paramètre de communication. Des permissions séparées permettent de déléguer
véhicules, départements, dashcams et vidéos aux utilisateurs. La création matérielle
des dashcams et leur configuration restent au superadmin. Le tableau de bord client
affiche les données de sa flotte. Voir [fleet-administration.md](fleet-administration.md)
pour les règles serveur et les limites de révocation vidéo.

## Fuseau des nouvelles dashcams — 22 septembre 2026

La création via le registre persiste désormais le fuseau GPS de l’installation :
listener.default_gps_timezone_minutes, variable DASHCAM_GPS_TIMEZONE_MINUTES,
60 minutes par défaut pour les horloges UTC+1 observées sur JK114 et ES500-603
à Kinshasa. Les calibrages existants restent inchangés à l’édition. Ce réglage
est celui de l’horloge du terminal, pas du navigateur ni de l’année JT808.
Les autres déploiements doivent l’accorder à leurs terminaux ; un changement
de défaut ne reconfigure pas les appareils et ne corrige pas les anciennes fiches.

Les deux Toyota Hilux VODA FLEET recevaient déjà des positions : le repli UTC+8
créait sept heures de retard et les plaçait avant leurs bornes d’affectation.
Fiches 3 et 4 corrigées à 60, 335 relevés recalés de +7 h après sauvegarde,
avec prédicat borné sur les heures de réception et le décalage observé.
Les positions réapparaissent sur la carte ; les bornes d’accès restent intactes.
44 tests Laravel ciblés / 331 assertions passent. Aucun écouteur redémarré.
Sauvegarde : /var/backups/exadcam-gps-timezone-20260922-153119.
Contrôle matériel complémentaire : JK114 Toyota Hilux reconnectée, 100 nouveaux
relevés post-correction vérifiés avec une seconde d’écart GPS/réception.
Le direct sur cette caméra renvoie un refus de commande ; l’utilisateur indique
EXADCAM en serveur secondaire et ne peut pas arrêter l’autre direct pour un test
isolé. ES500 Toyota Hilux : pas de nouveau contact depuis 15:30:23 UTC malgré
EXADCAM déclaré principal. Diagnostic et limites dans device-commissioning.md.
La cause du refus vidéo et de l’absence de reconnexion ES500 reste à confirmer.

## Image intérieure JK114 — 22 septembre 2026

Correction après capture utilisateur : élargir l’image intérieure, pas le panneau.
Le panneau JK114 retrouve sa taille compacte ; ses deux lecteurs affichent
l’image sur toute la surface 16:9 via object-fit: fill, sans recadrage. Le flux
720 × 576 reste intact côté serveur. ES500-603 reste en contain. Contrôle réel
du Canal 2 et fermeture de la lecture effectués. Sauvegarde :
/var/backups/exadcam-jk-image-20260922-145827. Voir la dernière entrée du journal.

La même adaptation pleine largeur s’applique maintenant à Voir en direct dans
la liste Dashcams, avec le modèle réappliqué à chaque ouverture. JK114 en fill,
ES500-603 en contain ; taille de la modale conservée. Contrôle réel du Canal 2
JK114 et changement de modèle vérifiés. Sauvegarde de ce complément :
/var/backups/exadcam-dashcam-live-image-20260922-151023.

## Historique GPS actuel — 22 septembre 2026

Pagination de 10 relevés par page (remplace les 25 du lot précédent), boutons
numérotés, précédent/suivant et compteur de plage/total. Les plus récents en
premier ; nouvelle date = page 1. Total limité au même périmètre autorisé que
les lignes. Validation : 16 tests ciblés / 133 assertions, Pint, syntaxe JS,
navigateur local et production. Sauvegarde :
/var/backups/exadcam-map4-20260922-143735. Voir la dernière entrée du journal.

## Présentation actuelle de la carte — 22 septembre 2026, révision visuelle

Filtres plus compacts (320 px, compteurs 17 px), symboles de résultats réduits,
fiche ancrée structurée avec voyant de connexion et dernier contact relatif.
La modale de détails est limitée à 800 px : Synthèse à l’ouverture (bandeau,
cartes Véhicule et dashcam / Emplacement), puis Historique GPS par date dans
un onglet distinct. Aucun champ fictif ajouté ; permissions inchangées.

Le volet vidéo remplace la largeur 50/50 du lot précédent par une largeur adaptée
à l’écran, au maximum 470 px sur ordinateur ; les deux zones restent au format
16:9 avec commandes compactes. Lecture à la demande, canaux indépendants.

Contrôles propres à cette révision : 16 tests Laravel ciblés / 111 assertions,
syntaxe JS/PHP, navigateur local SQLite et production, historique paginé et
essai bref du Canal 2 ES500-603 en 960 × 540 puis libération de la lecture.
Les chiffres de suite complète ci-dessous appartiennent au lot précédent.
Déploiement sauvegardé dans `/var/backups/exadcam-map3-20260922-141816`, sans
migration ni redémarrage d’écouteur. Voir l’entrée datée de project-history.md.


## Correction prioritaire — Parcours Carte et vidéo livré le 22 septembre 2026

La carte suit désormais le parcours EXAD Tracking demandé : compteurs avec icônes,
case Afficher tous les véhicules, résultats uniquement lors d'une recherche, puis
sélection du véhicule. Sans sélection ni case cochée, la rubrique Carte n'impose pas
de marqueurs. Flèche orientée d'après les déplacements GPS et trace animée ; carré
à l'arrêt/contact allumé ; P au contact coupé. Marqueurs anciens/hors ligne distincts.

Clic sur un marqueur → fiche avec Historique et détails / Vidéos. La modale affiche
les données actuelles et les relevés GPS par date locale, pagination de 25 points,
avec validation des flottes, des caméras et des bornes d'affectation. IMEI et détails
techniques visibles au superadmin uniquement. La lecture vidéo reste superadmin.

Le volet vidéo partage l'écran avec la carte et propose CH1/CH2 indépendants, chacun
avec Lecture/Arrêter. Aucun flux lancé à l'ouverture. Baux libérés à l'arrêt/fermeture,
y compris démarrage tardif. Deux flux ES500-603 lus simultanément en 960 × 540 en
production ; arrêt indépendant et fermeture confirmés dans les journaux. Pas de
nouveau test physique JK114 ni d'endurance. Cap déduit du GPS ; relecture de trajet
et archives vidéo encore à développer.

Validation actuelle : **179 tests Laravel / 909 assertions et 8 tests JavaScript**
réussis. Navigateur local et production contrôlés. Déploiement sans migration ni
redémarrage des écouteurs, sauvegarde `/var/backups/exadcam-map2-20260922-134311`.
Les descriptions/tests des lots antérieurs ci-dessous sont historiques ; consulter
la dernière entrée de project-history.md et le guide google-maps.md.


## État prioritaire — Carte réelle livrée le 22 septembre 2026

La rubrique Carte et la carte partagée du tableau de bord utilisent les positions
réelles enregistrées par les écouteurs. Les anciennes mentions de carte entièrement
en démonstration ci-dessous sont historiques. Les autres indicateurs/aperçus du
dashboard restent partiellement démonstratifs et explicitement signalés.

Carte inspirée d'EXAD Tracking : liste/recherche des véhicules, filtres flotte,
département/site/région et état, fiche GPS, suivi, courte trace récente, satellite et
plein écran. Actualisation environ toutes les dix secondes ; animation entre relevés
confirmés. Une seule position par véhicule, issue de sa caméra activée la plus récente.
Accès `map.view` et périmètre des flottes contrôlés côté serveur, aucun secret exposé.

Bornes d'affectation ajoutées aux véhicules/dashcams pour exclure les traces d'une
ancienne flotte ou d'un ancien véhicule après réaffectation. Migration appliquée
localement et en production le 22 septembre ; début des bornes existantes en production
à 12:58:33 UTC. Historique brut conservé. Deux véhicules EXAD CARS localisés en
production, tous deux à l'arrêt lors du contrôle. Écouteurs et identités inchangés.

Suite finale actuelle : **175 tests Laravel / 873 assertions** et **3 tests Node du
déplacement**, réussis. Navigateur local et production vérifiés. Sauvegarde :
`/var/backups/exadcam-map-20260922-125833`. Les tests plus anciens mentionnés ensuite
décrivent leurs lots respectifs. Essai routier réel, charge de 300+ appareils,
historique complet/relecture par date et validation mobile détaillée restent à faire.
Voir [google-maps.md](google-maps.md) et la dernière entrée de l'historique.


## Périmètre

- Projet : `D:\App\Codex\exadcam`.
- Référence de méthode : `D:\App\Codex\exad-tracking`, consulté en lecture seule.
- Plateforme indépendante : base, comptes, configuration et infrastructure propres.
- Développement web en premier ; application Flutter Android/iPhone ultérieure.
- Bootstrap local sans CDN ; Tailwind exclu de l'interface et de la compilation.
- Horizon de développement discuté : trois mois, avec plus de 300 dashcams visées
  au lancement. La capacité réelle devra être mesurée avec la charge GPS/vidéo.

## Conventions observées dans EXAD Tracking

| Sujet | Référence observée | Application à EXADCAM |
| --- | --- | --- |
| Rendu web | Pages Blade et fragments dans `resources/views/partials` | Blade, layout commun et fragments réutilisables |
| Bootstrap | `twbs/bootstrap` dans `composer.json`, fichiers sous `public/vendor/bootstrap` | Même mode d'installation et de distribution locale |
| Styles et interactions | `public/css/dashboard.css`, `public/js/dashboard-sidebar.js`, fichiers par module | CSS/JS séparés des vues ; fichiers par module lorsque nécessaire |
| Navigation | Barre latérale et barre supérieure partagées | Navigation de démonstration protégée par authentification |
| Formulaires | Modales Bootstrap, `@csrf`, erreurs `@error`, attributs `data-*` | Réutiliser cette approche pour les opérations métier |
| Traductions | Textes passés à `__()` et fichiers de langue | Prévoir les traductions dès les premiers modules |
| Authentification | Laravel Fortify et vues Blade personnalisées | Fortify installé ; connexion/déconnexion et vues Blade dédiées |

Le projet de référence conserve aussi des fichiers et dépendances Tailwind issus du
squelette Laravel. Cette partie n'est pas reprise, conformément au choix explicite
de Bootstrap uniquement. Le nouveau layout commun évite de répéter l'en-tête HTML
et les imports d'assets sur chaque page.

Les fichiers `resources/js/bootstrap.js` servent à initialiser Axios : leur nom ne
désigne pas la bibliothèque d'interface Bootstrap.

## État livré pour commencer

Bootstrap local, publication reproductible des ressources, page d'accueil Blade,
CSS/JS séparés et pipeline Vite sans Tailwind. L’accueil présente désormais un tableau de bord de démonstration, explicitement
étiqueté : six véhicules fictifs, trois événements simulés, fond Google Maps, filtres
et aperçus caméra sans flux réel. Les données affichées de flotte restent fictives ; les comptes, les sessions et les historiques de connexion sont persistés. Les tables structurelles d’abonnements et de flottes sont en place, sans module métier connecté au tableau de bord.
Le DashboardPreviewController sera remplacé par les services métier authentifiés
lors de l’intégration réelle.

## Ordre de travail

1. Authentification web et autorisations ; espace administrateur et navigation.
2. Gestion des clients/flottes, véhicules, utilisateurs et dashcams.
3. Réception GPS depuis le service Node.js et affichage des positions/historiques.
4. Commandes vidéo, réception des flux et lecture en direct dans le navigateur.
5. Archives, extraits, alertes et rapports après validation sur l'appareil.
6. Application mobile une fois les parcours web et les interfaces serveur stabilisés.

La limitation des accès aux données d'une flotte doit être appliquée côté serveur
sur les listes, les détails et les actions, puis vérifiée avec plusieurs comptes.

## Intégration dashcam : confirmé et à vérifier

Le matériel de test est une ESTON ES500-603 / AT603D. Le paramétrage observé utilise
JT808-2019. Une vidéo distante de deux canaux a été obtenue sur GPS51 ; cela ne
valide pas encore notre propre réception des messages et des flux.

Les services Node.js GPS/commandes et vidéo JT1078 sont maintenant installés.
Le direct H.264 est converti en HLS avec FFmpeg, puis lu par hls.js local.
Laravel contrôle le registre des équipements et les demandes de lecture.
La chaîne a été vérifiée avec des données synthétiques ; les essais matériels
restent à effectuer avec le nouvel IMEI que l'utilisateur fournira.

Restent à valider sur notre serveur : trames reçues, transport vidéo, numérotation
des canaux, audio, relecture de la carte mémoire et extensions d'alertes ADAS/DMS.
Ne pas présenter ces fonctions comme opérationnelles avant leurs essais.

Aucun accès au cloud GPS51 ni changement du configurateur matériel n'est nécessaire
pour préparer le socle web. Les secrets sont exclus de cette documentation.

## Connexion livrée

Page Blade responsive avec Bootstrap local et Laravel Fortify. La validation est
dynamique, avec messages sous les champs et connexion asynchrone en français et
en anglais. Mémorisation de session, limitation des échecs et déconnexion dans le
menu du compte restent prises en charge côté serveur. Voir [localization.md](localization.md).
Le tableau de bord exige une authentification. Création explicite des comptes
avec `php artisan app:create-user --role=user`. Aucun compte par défaut ;
la gestion des utilisateurs est livrée ; la récupération du mot de passe par e-mail reste à implémenter.

## Direction visuelle corporate

Le login utilise une composition photographique, une palette bleu nuit,
des angles discrets et une typographie sobre. Les visuels de flotte et de dashcam
sont générés par IA, optimisés en WebP et servis localement, sans CDN.
La dashcam occupe le premier plan sur ordinateur et sur mobile, avec un cadrage adapté.
Le logo officiel EXAD est réutilisé depuis les PNG fournis, sans redessiner les lettres.
Deux présentations SVG appliquent les tons bleu nuit et clair tout en conservant la transparence.
Les visuels initiaux sont documentés dans `docs/design-assets.md`.
La version actuelle et les logos sont documentés dans `docs/exad-identity.md`.


## Base et autorisations — 16 septembre 2026

La connexion utilise désormais MySQL/MariaDB, base locale `exadcam`. La structure
de `users` (21 colonnes), les rôles et les droits JSON reprennent EXAD Tracking,
avec les tables `subscriptions`, `fleets`, `fleet_user` et `user_login_histories`.
Un compte Superadmin a été provisionné à la demande de l’utilisateur. Aucun secret
ni compte n’est ajouté aux seeders. La base de référence reste indépendante.

Voir [database-and-access.md](database-and-access.md) pour le comparatif réel,
les garanties d’accès, le provisionnement et les modules différés.

## Navigation corporate — 16 septembre 2026

La barre supérieure reprend la référence visuelle fournie : titre, fil d’Ariane,
plein écran, préférences, notifications de démonstration, langue et compte.
Le menu latéral se masque sur ordinateur et s’ouvre en offcanvas sur mobile.
Les styles/interactions propres à la barre sont dans `public/css/topbar.css`
et `public/js/topbar.js`. Le composant de langues partage la même route et les
mêmes protections que la connexion. Le badge représente trois événements fictifs.
La recherche reste accessible dans la liste des véhicules et par le raccourci `/`.

Après retour utilisateur, la navbar utilise une hauteur compacte de 76 px sur
ordinateur, des boutons de 38 px et un titre de 20 px. Sur mobile, les contrôles
restent à 42 px avec une disposition sur deux lignes moins espacées.

Le menu latéral utilise une largeur de 232 px sur ordinateur et un thème bleu nuit,
avec un état actif bleu discret. Ses styles sont isolés dans `public/css/sidebar.css`.
Le logo et l’aide restent visibles ; seule la liste défile si nécessaire. Le bloc
promotionnel a été remplacé par un accès au guide. Les libellés du menu sont traduits
en français et en anglais, et l’ouverture mobile reste gérée par Bootstrap.

Le bloc « Espace EXAD / Démonstration » a été retiré à la demande de l’utilisateur.
La rubrique Supervision suit directement le logo dans le menu.

La navigation reprend désormais l’arborescence approuvée : Tableau de bord,
Utilisateurs, Flottes (Flottes, Véhicules, Dashcams, Départements), Carte, Centre
vidéo, Alertes et Rapports. Le groupe Flottes est replié à chaque chargement, même sur une sous-rubrique.
Il se déplie au clic ou lors de la navigation vers une sous-rubrique. Les nouvelles rubriques sans module métier ouvrent une page
« En préparation » ; Carte affiche Google Maps avec des positions fictives à Kinshasa.
La gestion des utilisateurs et le registre initial des dashcams utilisent les données réelles ; les autres CRUD
et les rapports réels restent à développer. Les icônes sont servies localement.

## Dashboard et graphiques — 16 septembre 2026

Les boutons et actions du dashboard utilisent désormais le bleu nuit `#203d65`
du login. Les styles spécifiques sont dans `public/css/dashboard.css`.
ApexCharts est repris d’EXAD Tracking : package Laravel `akaunting/laravel-apexcharts`
4.0.0, script JavaScript 3.35.1 livré par le package, servi localement. La publication
des assets Composer inclut Bootstrap et ApexCharts.
Le dashboard présente une courbe d’activité 24 h/7 jours et un anneau d’état des
dashcams, tous deux alimentés par la démonstration. Les séries sont préparées par
DashboardPreviewController, traduites et encodées avec `Js::encode`, puis rendues
par `public/js/dashboard-charts.js`. Les données métier réelles ne sont pas encore
connectées. Voir [dashboard-charts.md](dashboard-charts.md).

## Carte Google Maps — 16 septembre 2026

La clé navigateur fournie est configurée dans les `.env` local et de production, exclus de
Git. Le contrôleur transmet la configuration nécessaire à la page authentifiée ;
une clé Maps JavaScript est visible côté navigateur et doit être restreinte aux
sites autorisés et à cette API dans Google Cloud.
Une seule carte est créée lorsqu’elle devient visible, puis réutilisée entre les
vues Tableau de bord et Carte. Les marqueurs sont sélectionnables, avec commandes
de zoom, cadrage de la flotte et accès à l’aperçu caméra du véhicule choisi.
Le fond cartographique est réel, les six positions et leurs statuts sont fictifs.
Le service Node.js n’alimente pas encore la carte. Aucun géocodage ni calcul
d’itinéraire n’est appelé. Bootstrap, ApexCharts, polices et icônes restent locaux ;
l’API cartographique et ses ressources sont chargées depuis Google.
Le domaine `exadcam.app` pointe vers le serveur et répond en HTTPS ; les restrictions de clé Google Maps restent à adapter et vérifier. Le Map ID de test doit être remplacé
avant la production. Voir [google-maps.md](google-maps.md).

## Gestion des utilisateurs — 16 septembre 2026

La rubrique Utilisateurs reprend les fonctions d’EXAD Tracking : liste avec
recherche/tri/pagination, création, modification, suppression confirmée, permissions,
affectation à une flotte et historique des connexions. Données réelles EXADCAM,
formulaires dynamiques FR/EN, Bootstrap local et thème bleu conservés.
Le superadmin gère les comptes non superadmin. Un admin ne gère que les utilisateurs
simples de sa flotte ; aucun accès aux comptes d’une autre flotte ou non affectés.
Les comptes superadmin sont protégés. Le menu n’est pas exposé aux utilisateurs simples.
Une flotte active est nécessaire avant de créer un utilisateur. Aucune n’est
encore présente dans la base réelle ; le prochain module Flottes devra l’offrir.
Voir [user-management.md](user-management.md) pour les règles, routes et vérifications.


## Accès au serveur — 21 septembre 2026

- Serveur communiqué : `62.171.190.15`, SSH sur le port 22.
- Ubuntu 24.04.5 LTS, hôte `vmi3599783` ; bannière d’accueil Contabo observée.
- Domaine : `exadcam.app`, DNS et HTTPS vérifiés lors de la préparation serveur du 21 septembre 2026.
- Compte Linux d’administration créé : `exad-cam`, répertoire `/home/exad-cam`,
  membre de `sudo`. Connexion SSH et élévation sudo avec mot de passe vérifiées.
- Utiliser ce compte pour les prochaines connexions : `ssh exad-cam@62.171.190.15`.
  Les secrets restent hors documentation et hors dépôt. Aucune clé SSH ajoutée.
- Cette étape prépare l’accès système ; elle ne déploie pas l’application, les
  services GPS/vidéo, les sauvegardes ni une réplication. L’accès root reste inchangé.


## Infrastructure web et écoute — 21 septembre 2026

La plateforme Laravel est déployée dans `/var/www/exadcam` et disponible sur
`https://exadcam.app`. Apache, MariaDB, PHP-FPM et le HTTPS sont actifs. Composer
a installé 99 dépendances de production depuis le lockfile. Configuration
production séparée, débogage désactivé, clé applicative propre et sessions sécurisées.
Les migrations sont exécutées ; le compte superadmin demandé a été provisionné.
Aucun export de la base métier locale. Les premières dashcams réelles ont ensuite été inscrites le 22 septembre, voir ci-dessous.

Node.js 24.21.0 LTS et FFmpeg 6.1.1 sont installés. Deux services systemd,
`exadcam-gps` et `exadcam-video`, démarrent automatiquement sous un compte dédié.
Ports publics TCP : 7808 pour JT808 2013/2019, 1078 pour JT1078. La commande 0x9101
transmet la destination vidéo au matériel : le configurateur utilise le port
principal 7808. Les API de coordination 3001/3002 et Laravel 8081 restent locales.

La rubrique Flottes → Dashcams permet au superadmin de créer le registre autorisé,
d'activer/désactiver un équipement et de demander un direct H.264. Seuls les
équipements inscrits et actifs sont admis. La préparation du 21 septembre ne
contenait aucun IMEI ; les inscriptions et essais du 22 septembre sont décrits ci-dessous.

Les GPS reçus sont persistés mais ne remplacent pas encore les données de
démonstration de la carte/dashboard. La rubrique Centre vidéo générale reste
un aperçu ; le parcours de test réel est dans Dashcams. Audio, H.265, archives
sur carte/cloud, alertes ADAS/DMS et droits vidéo par flotte restent à développer.
La limite initiale de 8 flux simultanés est une protection, pas une capacité mesurée.

phpMyAdmin reste disponible à `https://exadcam.app/phpmyadmin/`. Le fond Google
Maps a été vu en production, sans audit des restrictions de clé. Les sauvegardes
externes et la supervision métier restent à installer. Les sauvegardes réalisées
pour ce déploiement sont locales au VPS, sans réplication.
Voir [server-infrastructure.md](server-infrastructure.md),
[listener-server.md](listener-server.md) et [database-and-access.md](database-and-access.md).

## Deux équipements et première vidéo réelle — 22 septembre 2026

JK114 et ES500-603 inscrites/activées en production avec les deux IMEI fournis.
JK114 : connexion JT808 2019 authentifiée, positions réelles reçues et deux canaux
H.264 720 × 576 décodés et servis en HLS lors des tests serveur. L'utilisateur
a ensuite confirmé voir le direct dans son navigateur. La correspondance
route/habitacle reste à identifier explicitement.

Le serveur supporte désormais les identifiants vidéo courts et étendus, sélectionnés
par correspondance exacte avec un flux autorisé. La JK114 utilise 20 chiffres.
Fuseau GPS configurable par équipement : JK114 observée en UTC+1, premières
positions corrigées après sauvegarde ; aucun changement global imposé à l'ES500-603.

ES500-603 : après correction de son alias JT808 court (053810725721), puis
redémarrage confirmé par l'utilisateur, authentification réelle reçue à 09:52:08 UTC.
Une reconnexion automatique a été observée à 09:55:21 UTC. Le listener utilise
son décodeur JT808 ancien, nommé « 2013 » ; cette observation ne détermine pas
à elle seule l'édition exacte du firmware. Positions 0x0200 et battements 0x0002
reçus, fix GPS valide. Les deux appareils sont connectés au contrôle du 22 septembre
vers 10:00 UTC. Le fuseau propre à l'ES500-603 a été réglé sur UTC+1 après
observation des horodatages réels ; 42 positions initiales corrigées de sept heures
après sauvegarde. Les nouvelles dates GPS sont cohérentes.

La connexion GPS de l'ES500-603 a été validée. Son flux JT1078 utilise aussi
l'identifiant 053810725721, désormais corrigé dans le registre vidéo. Le H.264
réel 960 × 540 nécessitait une génération explicite des horodatages. L'option
normalize_video_timestamps est livrée et activée pour cette fiche uniquement,
avec cadence configurée à 15 images/s, copie du flux compressé et refus des images B.
La JK114 conserve le mode natif. Les dix tests Node et neuf tests Laravel ciblés
(48 assertions) du correctif passent ; décodage de l'échantillon ES500 corrigé réussi.

L'ES500-603 s'est déconnectée à 10:14:22 UTC, avant installation du correctif à
10:17 UTC. L'utilisateur signale la mise en veille : essai final de lecture en direct
en attente de réveil/reconnexion. La JK114 reste en GPS mais a refusé la commande
vidéo lors du contrôle de ce lot ; sa validation vidéo antérieure reste historique.
MariaDB local est arrêté/injoignable, migration locale de compatibilité en attente ;
migration appliquée en production. Les autres modules restent dans l'état décrit
précédemment. Voir [device-commissioning.md](device-commissioning.md).

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


## État confirmé après redémarrage — 22 septembre 2026

Le direct ES500-603 est confirmé par l'utilisateur après redémarrage, puis
observé dans le navigateur en 960 × 540. Reconnexion à 10:30 UTC toujours au format
JT808 ancien (« 2013 ») ; aucune conversion vers 2019 nécessaire. Canal 2 observé
en lecture. Une coupure liée au limiteur de messages à 10:32:25 a été suivie d'une
reconnexion automatique ; la lecture a été relancée. La stabilité longue durée
et la cause précise de cette rafale restent à suivre, aucun seuil n'a été modifié.

La JK114 conserve sa validation vidéo antérieure ; lors du dernier essai du lot
précédent elle restait GPS active mais refusait la commande de direct.
MariaDB local est arrêté/injoignable, migration locale de compatibilité en attente ;
migration appliquée en production. Voir device-commissioning.md pour les essais
et limites. La connexion Internet via le hotspot ES500 est confirmée par l'utilisateur.


Contrôle complémentaire : le canal 2 est resté ouvert de 10:34:12 à 10:36:16 UTC
(plus de deux minutes), sans nouvelle déconnexion GPS ni erreur vidéo dans les
journaux ; arrêt par fermeture de lecture, puis nouvelles demandes CH1 et CH2.
La rafale ayant provoqué la coupure précédente n'a pas été reproduite dans cette
fenêtre. Aucun seuil modifié. Les résultats ne constituent pas un test d'endurance.


## Mise à jour prioritaire — Registre et affectations livrés le 22 septembre 2026

La gestion des dashcams utilise désormais des modales, un choix de modèle préalable,
TCP / JT808 2013 fixe pour ES500-603 avec identifiant CarAssist explicite à 12 chiffres,
et le choix 2013/2019 pour JK114. Registres Flottes/Véhicules de base opérationnels,
relation dashcam → véhicule → flotte, tableaux avec recherche/tri/pagination.
Seul le superadmin gère actuellement ces modules. Le changement d’année dans la fiche
ne modifie pas le firmware ; les trames continuent de déterminer `last_protocol`.

Production : flotte EXAD CARS, JK114 affectée à Véhicule test 1, ES500-603 affectée
à Véhicule 2 ; immatriculations à compléter. Les deux contacts sont récents après
déploiement, identités/secrets et paramètres vidéo préservés. Aucun redémarrage
des écouteurs. L’affectation et le nom n’interrompent plus la vidéo.

MariaDB local fonctionne de nouveau et toutes les migrations locales en attente
ont été appliquées : les anciennes mentions « migration locale en attente » sont
désormais historiques. Essais navigateur réalisés sur SQLite séparé ; appareils
réels et affectations en production. Suite finale Laravel : 158 tests / 792 assertions.
Le dashboard/carte restent en démo ; fonctions avancées flottes, archives/audio et
endurance vidéo restent à compléter. Voir [dashcam-registry.md](dashcam-registry.md).

### Correction de parcours — 22 septembre 2026

L’affectation dashcam demande uniquement le véhicule, avec recherche intégrée par
nom/immatriculation/flotte. Sa flotte est déduite en base ; le choix préalable de flotte
décrit dans le lot précédent est supprimé. Le formulaire véhicule garde son choix de
flotte, désormais recherchable. Titres/descriptions répétés des panneaux Flottes et
Véhicules retirés. Composant local adapté d’EXAD Tracking, sans nouvelle dépendance.
Correction testée (27 tests ciblés / 182 assertions et contrôles navigateur) et déployée
sans modification des affectations ni des écouteurs. Voir `dashcam-registry.md`.

### Organisation des véhicules — 22 septembre 2026

Départements/sites/régions opérationnels dans #departments, superadmin uniquement.
Flotte obligatoire pour chaque département et véhicule ; département facultatif pour
le véhicule, de la même flotte (validation transactionnelle + FK composée). Modales avec
sélecteurs recherchables, département filtré sur la flotte, choix Aucun département.
Déplacer un département occupé vers une autre flotte est bloqué. Dashcam → véhicule,
puis flotte et département éventuel du véhicule ; pas de saisie doublonnée sur la dashcam.
Migration appliquée localement et en production ; aucun département réel créé ni affecté.
Suite complète actuelle : 166 tests / 868 assertions, réussis. Sauvegarde déploiement :
/var/backups/exadcam-departments-20260922-121839. Identités et connexions conservées.
Voir docs/dashcam-registry.md et l’entrée correspondante de project-history.md.

## Diagnostic GPS de Véhicule 2 — 23 septembre 2026

ES500-603 connectée mais sans nouvelle position valide depuis 06:44:25 UTC
(07:44:25 Kinshasa). Capture de 40 secondes : quatre télémétries JT808 2013 avec
bit de validité GPS désactivé, toutes acquittées. La carte conserve le dernier GPS
avec l'état stale ; fuseau et affectations corrects. Utilisateur : caméra sous
un toit / dans un bâtiment. Essai extérieur demandé, reprise non encore vérifiée.
Aucun changement logiciel ou matériel appliqué. Détails : device-commissioning.md.


## Fluidité vidéo déployée — 23 septembre 2026

Le socle live-buffer-2, complété par live-reconnect-1 ci-dessous, est en production :
lecteur partagé Carte/Dashcams, réserve continue de 15 s avant lecture, cible HLS
à 18 s du bord et fenêtre serveur de 20 segments (environ 40 s). Première image reçue
affichée pendant la préparation ; dernière image conservée lors du remplissage suivant, sans animation
de chargement. Une coupure de réception dépassant la réserve reste perceptible.

La cadence réelle mesurée sur les deux canaux des appareils de test était inférieure
aux 15 images/s enregistrées. Calibration du remuxage pour la fiche 1 JK114 à 10
images/s et la fiche 2 ES500-603 à 12 images/s ; aucun changement du firmware,
protocole, identifiant ou affectation. La JK114 fiche 3 a ensuite été mesurée sur les deux canaux (environ 10,01 images/s)
et calibrée à 10 images/s. La fiche 4 a ensuite été mesurée et calibrée à 12 images/s (voir reprise automatique ci-dessous).

Validation : 40 tests Laravel / 317 assertions et 11 tests Node locaux avant
déploiement ; 10 tests listener Linux avec FFmpeg réel réussis pendant cette mise
en ligne. Essais matériels et limites détaillés dans device-commissioning.md.
Des déconnexions source ont aussi été observées pendant les essais ES500 CH2
et JK114 CH1 ; leur cause reste à diagnostiquer si elles persistent.
Sauvegarde : /var/backups/exadcam-live-buffer-20260923-084409.
Le service vidéo a été redémarré, le service GPS est resté actif sans redémarrage.


### Reprise du démarrage vidéo — 23 septembre 2026

Après signalement utilisateur, téléchargement dès le premier segment, aperçu
du premier vrai frame et correction des chronologies commençant après zéro.
La réserve de 15 secondes reste active. 13 tests Node lecteur/baux réussis.
Version live-buffer-2 déployée ; PHP-FPM rechargé, écouteurs non redémarrés.
L’état réel des essais et connexions figure dans la dernière entrée du journal :
ne pas assimiler cette correction du lecteur à un rétablissement des caméras
qui se sont déconnectées pendant le diagnostic.


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
