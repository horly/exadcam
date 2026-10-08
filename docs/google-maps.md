# Google Maps et suivi GPS dans EXADCAM

État livré le 22 septembre 2026. La rubrique Carte affiche désormais les positions
réelles reçues des dashcams et affectées aux véhicules. Elle remplace les six marqueurs
de démonstration de la première version. Les autres indicateurs et aperçus du tableau
de bord restent partiellement démonstratifs et portent un avertissement explicite.

## Image JK114 sur toute la largeur — 22 septembre 2026

Le panneau garde la largeur adaptative compacte commune aux modèles. Pour la
JK114, les vidéos intérieures utilisent object-fit: fill dans la surface 16:9.
L’image est adaptée horizontalement à la largeur demandée, sans couper les
bords ni les informations incrustées. Le flux natif 720 × 576 reste inchangé.
L’ES500-603 garde object-fit: contain. Le modèle est réappliqué à chaque ouverture.

Cette correction remplace l’élargissement du panneau à 620 px : la capture
utilisateur désignait les marges à l’intérieur du lecteur. Vérification réelle
sur le Canal 2 JK114 : lecture et rendu plein cadre, puis fermeture. Les règles
de démarrage explicite et de libération des baux restent identiques.

## Pagination de l’historique — 22 septembre 2026

10 lignes par page par défaut et au maximum. Pagination numérotée, première et
dernière page, précédent/suivant et ellipses ; page active dans le thème EXADCAM.
Compteur « Affichage de :from à :to sur :total », 0 à 0 sur 0 si la date est vide.
Une nouvelle date réinitialise la page à 1. Les boutons sont désactivés ou retirés
pendant le chargement pour éviter les actions sur une ancienne pagination.

La réponse details contient page, has_more, per_page, total, last_page, from et
to. Le total utilise exactement les filtres et autorisations de la liste. Les
nouvelles positions du jour peuvent faire évoluer ce total entre deux pages.
Validation propre à ce lot : 16 tests / 133 assertions, Pint, syntaxe JS et
contrôles navigateur local/production. Les résultats plus anciens ci-dessous
restent associés à leurs lots respectifs.

## Proportions et fiche détaillée — révision du 22 septembre 2026

Le panneau mesure 320 px sur ordinateur et utilise des chiffres de 17 px.
Les symboles des résultats sont distincts des marqueurs sur la carte : flèche
20 × 23 px, carré 16 × 16 px, P de 19 px. Les règles GPS restent inchangées.

La fiche de marqueur affiche un voyant vert/gris pour la connexion, séparé du
symbole de déplacement. Son en-tête personnalisé évite la ligne vide de Google
InfoWindow (`headerDisabled: true`). Fermeture accessible, informations alignées,
date de dernier contact relative et date exacte au survol.

La modale est limitée à 800 px avec défilement interne. Synthèse présente le
véhicule, sa connexion, son état GPS, deux cartes d’informations et les paramètres
techniques autorisés. Les coordonnées proviennent de la dernière position connue
du véhicule au moment d’ouvrir la fiche ; l’heure du relevé est explicite.
Historique GPS est un onglet distinct avec date et pagination de 10 positions.
L’endpoint, l’isolation des flottes et la restriction des détails techniques au
superadmin sont conservés. Ni adresse géocodée ni qualité GPS estimée ajoutée.

Le volet vidéo a une largeur adaptative, plafonnée à 470 px et réduite selon la
hauteur disponible pour présenter les deux canaux. Chaque surface garde un ratio
16:9 ; le lecteur utilise object-fit: contain. Sur mobile, carte et vidéos restent
empilées. Les flux ne démarrent qu’au clic sur Lecture.

Validation de cette révision : 16 tests ciblés Laravel / 111 assertions, syntaxe
JS/PHP, parcours navigateur local et production, pages 1/2 d’historique ; essai
bref du Canal 2 ES500-603 en 960 × 540 puis fermeture de sa lecture. Les suites
complètes et le test simultané CH1/CH2 mentionnés plus bas concernent le lot précédent.
Pas de nouvelle validation sur téléphone physique ni d’endurance.


## Révision du parcours — 22 septembre 2026, après comparaison EXAD Tracking

Cette révision remplace la liste permanente et la fiche dans le panneau de gauche.
La rubrique Carte démarre avec la case « Afficher tous les véhicules » décochée.
La recherche ouvre la liste de résultats ; sélectionner un résultat affiche son
marqueur. La case permet d'afficher tous les véhicules correspondant aux filtres,
sans imposer la liste quand la recherche est vide. Le tableau de bord conserve
son aperçu global. Compteurs 2 × 2 avec icônes, filtre département visible seulement
si des véhicules ont un département dans le périmètre.

Symboles conformes au principe EXAD Tracking : flèche et trace en mouvement,
carré à l'arrêt avec ACC allumé, P rond avec ACC coupé. L'état parking a priorité
si ACC est coupé, conformément à la référence. Seuil de déplacement conservé à
3 km/h. La flèche suit le cap calculé entre points GPS confirmés ; aucun nouveau
champ de cap JT808 ni modification d'écouteur. La trace avance jusqu'au marqueur
animé, sans anticiper le point suivant, et est cachée en arrêt/parking.

Un clic ouvre une InfoWindow avec résumé de la dashcam et deux actions :

- **Historique et détails** : modale Bootstrap, projection explicite des champs
  de l'équipement pour le superadmin (IMEI/modèle/protocoles/canaux/cadence), détails
  véhicule et relevés GPS du jour choisi. `GET /map/vehicles/{vehicle}/details`
  exige `source_id`, `date`, `timezone`, accepte `page`. Validation Laravel,
  `map.view`, flotte visible active pour les clients, caméra activée appartenant
  au véhicule. Les bornes d'affectation s'appliquent aussi à cet historique.
  Pagination avec total de 10 relevés valides par page, décroissants, horodatés en UTC puis
  présentés selon le navigateur. Dates locales traduites en fenêtre UTC avant requête.
- **Vidéos** : volet à droite, de largeur adaptative et lecteurs 16:9 depuis la
  révision visuelle (initialement un partage 50/50). Deux zones indépendantes CH1/CH2, départ explicite via
  Lecture. Endpoints et restrictions superadmin existants conservés ; les autres
  comptes voient l'action indisponible. Chaque lecteur HLS possède son bail.
  Le module `map-video.mjs` gère demandes tardives, polling sans chevauchement,
  arrêt et libération à la fermeture/navigation/onglet masqué. Aucun flux automatique.

Le snapshot GPS expose maintenant `equipment` au superadmin pour la recherche IMEI
et la fiche, ainsi que `details_url`. Les autres comptes reçoivent `equipment: null`.
Les affirmations de l'ancienne version « aucun IMEI dans la réponse » sont ainsi
remplacées par une visibilité technique réservée au superadmin. Les secrets, alias
de communication et adresses IP restent exclus de toutes ces projections.

Validation actuelle : 179 tests Laravel / 909 assertions et 8 tests JS réussis.
La première vérification navigateur a détecté puis corrigé le contexte d'appel
des temporisations natives du lecteur. Les deux canaux ES500-603 ont ensuite été
lus simultanément en production en 960 × 540 ; arrêt de CH1 sans interrompre CH2,
puis fermeture avec deux événements video_stopped et lecteurs vidés. L'image CH1
était sombre avec incrustation horaire, CH2 montrait la scène filmée. Deux contacts
GPS récents ; protocole ES500 2013, JK114 2019 préservés. Historique et pagination
vérifiés sur les vrais relevés ES500. Aucun nouvel essai physique JK114.

Sauvegarde du lot : `/var/backups/exadcam-map2-20260922-134311`. Pas de migration,
de modification des équipements ou des écouteurs. Audio, archives vidéo et relecture
animée de trajet complet restent hors de ce lot. Les paragraphes décrivant le
premier lot ci-dessous donnent son contexte technique ; cette révision prévaut
pour le parcours, les symboles, les projections et les résultats de validation.


## Configuration

La configuration utilise `GOOGLE_MAPS_API_KEY` et `GOOGLE_MAPS_MAP_ID` dans `.env`,
puis `config/services.php`. La clé navigateur est transmise à la page authentifiée ;
elle est nécessairement visible dans les requêtes Google. Ne pas la copier dans les
sources, les exemples ou la documentation. Le Map ID est distinct de la clé API.

La clé doit être restreinte à Maps JavaScript API et aux origines de développement
utilisées ainsi qu'à `https://exadcam.app/*`. Un chargement réussi ne prouve pas les
restrictions, quotas ou paramètres de facturation du compte Google Cloud. La carte
a été observée sur le serveur local de validation et en production. Les paramètres
Google Cloud n'ont pas été modifiés dans ce lot.

Bootstrap, Manrope, les icônes et les scripts métier restent locaux. Google fournit
le fond de carte et son API officielle. Aucun package Laravel supplémentaire ajouté.

## Données et autorisations

`GET /map/vehicles`, dans les routes authentifiées et actives, exige `map.view` et
est limité à 30 requêtes par minute. `MapController` appelle `FleetMapService`.
La réponse est privée et non stockable (`private, no-store`).

- Les véhicules sont limités aux flottes visibles par l'utilisateur ; les flottes
  inactives sont exclues pour les comptes clients. Un filtre envoyé par le navigateur
  ne peut pas élargir ce périmètre.
- Une seule position est retenue par véhicule, issue de sa dashcam activée ayant le
  relevé GPS admissible le plus récent. Plusieurs caméras ne créent pas de doublons.
- Les positions proviennent de `dashcam_positions`, enregistrées par la chaîne GPS
  existante. Le service ne prend pas une coordonnée de démonstration comme remplacement.
- Un relevé admissible exige le bit de localisation JT808, des coordonnées dans les
  limites géographiques, différentes du couple (0, 0), et un horodatage au maximum
  30 secondes dans le futur. Le tri utilise l'heure GPS puis l'identifiant du relevé.
- `vehicles.fleet_assigned_at` et `dashcams.vehicle_assigned_at` excluent les relevés
  antérieurs à l'affectation courante. Une réaffectation ne révèle donc pas une trace
  de l'ancien véhicule ou de l'ancienne flotte. Les modifications de nom ou de
  télémétrie ne réinitialisent pas ces bornes.
- La migration `2026_09_22_150000_add_map_assignment_boundaries` initialise les bornes
  des affectations existantes à son exécution. En production : 12:58:33 UTC le
  22 septembre 2026. Les anciens relevés bruts sont conservés en base ; leur exclusion
  de cette carte est volontaire, faute d'historique fiable des affectations passées.
- Aucune adresse IP, aucun IMEI, alias de communication ou secret n'est transmis.
  Le nom de la dashcam source est réservé au superadmin.

Le JSON contient `generated_at`, `refresh_seconds`, `trail_minutes` et `vehicles`.
Chaque véhicule expose son nom, immatriculation, flotte, département facultatif,
état, dernier contact, source interne, dernière position et courte trace récente.
La position inclut latitude, longitude, heure UTC, vitesse et état du contact ACC.

## États et déplacements

Le contact et la position sont considérés récents pendant trois minutes. Les états
distinguent absence de caméra, absence de position, hors ligne, GPS ancien malgré
un contact récent, déplacement et arrêt. Le seuil de déplacement est 3 km/h.
Une dernière position ancienne reste identifiable comme telle ; elle n'est pas
présentée comme une localisation fraîche.

La trace récente utilise au maximum les 60 derniers points par caméra des dix dernières
minutes. Elle est interrompue lors d'un écart supérieur à 120 secondes, d'un saut de
plus de 600 mètres ou d'une vitesse calculée supérieure à 70 m/s. Les déplacements
inférieurs à trois mètres sont filtrés ; la trace finale garde au maximum dix points
et 850 mètres. Elle est masquée si la position ou la connexion devient ancienne.
Ce filtrage réduit les artefacts GPS, sans garantir l'absence de dérive à l'arrêt.

`map-motion.mjs` anime les marqueurs entre les relevés confirmés, avec les points
intermédiaires disponibles. Il n'extrapole pas une position future. Un changement
de caméra source, une interruption ou un saut incohérent entraîne un repositionnement
direct. Les préférences de réduction des animations sont respectées.

La recherche et les filtres flotte, département/site/région et état concernent uniquement
les véhicules autorisés. La fiche sélectionnée montre coordonnées, dates, vitesse et
contact ACC. Le suivi peut recentrer la carte sur ce véhicule. Des commandes permettent
le cadrage des véhicules filtrés, le zoom, le satellite, le plein écran et le masquage
du panneau. Les sélecteurs flotte et département proposent une recherche locale.

## Actualisation et carte partagée

`partials/dashboard-map.blade.php` contient une seule carte, réutilisée entre Tableau
de bord et Carte. `google-map.js` charge l'API Google quand l'une de ces vues est active,
puis conserve son instance et ses marqueurs. Les attributions Google restent affichées.
Les styles et textes FR/EN suivent le thème EXADCAM et le parcours observé dans
EXAD Tracking, consulté en lecture seule.

L'actualisation interroge notre endpoint Laravel environ toutes les dix secondes,
sans requêtes simultanées. Elle s'arrête quand l'onglet est masqué ou qu'une autre
rubrique est affichée. Une requête est abandonnée après quinze secondes ; les échecs
espacent les tentatives jusqu'à trente secondes et affichent un avertissement.
La perte d'autorisation retire les données et les marqueurs. Le centrage initial
à Kinshasa n'est qu'un cadrage de secours, sans marqueur fictif.

Le déplacement des marqueurs ne recrée pas l'instance Google. Aucun appel Directions,
Geocoding, Places, Routes ou Roads n'est utilisé pour ces positions. Un rechargement
complet de page ou un changement de langue peut créer une nouvelle instance Google.
Les seuils gratuits et la facturation restent ceux du compte Google Cloud ; cette
intégration ne constitue pas une garantie de gratuité.

## Validation et limites actuelles

Suite finale : **175 tests Laravel / 873 assertions**, **3 tests Node du module de
déplacement**, Pint et vérifications de syntaxe JS réussis. `FleetMapTest` couvre les
coordonnées admissibles, les états, les traces bornées, les sources multiples,
les droits et l'isolation des flottes, ainsi que les changements d'affectation.
`DashboardMapTest` couvre l'accès invité et la configuration authentifiée.

Navigateur local sur SQLite isolé : chargement Google, recherche et sélection,
filtres recherchables, fiche véhicule, changement de coordonnées à partir de relevés
synthétiques, commandes du panneau et instance Google unique. Production : deux
positions réelles visibles pour EXAD CARS, fiche ES500-603 actualisée automatiquement,
un seul script Google chargé. Les deux véhicules étaient à l'arrêt pendant ce contrôle.

Déploiement sauvegardé dans `/var/backups/exadcam-map-20260922-125833`. Migration
additive appliquée localement et en production ; identités, paramètres vidéo et
affectations préexistantes comparés et préservés. Apache, PHP-FPM et les services
GPS/vidéo sont actifs. Les écouteurs n'ont pas été redémarrés.

La trace récente ne remplace pas un module d'historique avec choix de date et relecture
d'un trajet complet. Un essai routier réel, une mesure de charge avec plus de 300 appareils
et une validation mobile détaillée restent à réaliser. Aucun nouveau test d'endurance
vidéo dans ce lot ; aucun changement des protocoles ou réglages des caméras.

## Références officielles

- [Marqueurs avancés](https://developers.google.com/maps/documentation/javascript/advanced-markers/overview)
- [Personnalisation des marqueurs](https://developers.google.com/maps/documentation/javascript/advanced-markers/basic-customization)
- [Chargement de Maps JavaScript API](https://developers.google.com/maps/documentation/javascript/load-maps-js-api)

## Centrage et suivi par défaut — 22 septembre 2026

La carte partagée privilégie à l'ouverture le véhicule en ligne et en déplacement
dont le relevé GPS est le plus récent. Seules les positions admissibles fournies
par Laravel sont utilisées. Une sélection explicite est conservée même lorsque
le véhicule s'arrête : les actualisations ne basculent pas vers un autre véhicule.
Le suivi est coché par défaut et recentre le véhicule pendant les animations ainsi
que lors des mises à jour sans animation. Un glissement manuel suspend ce suivi ;
« Centrer », une nouvelle sélection ou la case de suivi permettent de le reprendre.
« Afficher tous les véhicules » conserve le cadrage collectif sans suivi individuel.
La liste des résultats reste conditionnée à la recherche.

Validation ciblée : 18 tests Laravel / 153 assertions (FleetAdministrationTest et
DashboardMapTest), 11 tests Node (déplacements et vidéo), syntaxe JavaScript.
La priorité mobile, le maintien du choix et l'absence de coordonnées sont couverts
par des tests du sélecteur. La validation navigateur utilise uniquement une base
SQLite isolée et des relevés synthétiques ; elle ne remplace pas un essai routier.

## Alignement flèche et trace — 23 septembre 2026

Le décalage signalé venait notamment de l'ancrage Google par défaut en bas du
marqueur : le centre du symbole de 36 px se trouvait 18 px au-dessus du GPS.
AdvancedMarkerElement utilise désormais anchorLeft/anchorTop à -50 %, alignant
la coordonnée de la ligne avec le centre de rotation de la flèche.
Référence : [ancrage des marqueurs Google](https://developers.google.com/maps/documentation/javascript/reference/advanced-markers#AdvancedMarkerElementOptions.anchorTop).

La ligne se termine à la position effectivement affichée, aussi pendant un
nouveau rendu des filtres. L'interpolation conserve un horodatage de présentation
pour reprendre une animation interrompue sans sauter les virages reçus ni perdre
la trace antérieure. Les changements d'état interrompent l'ancienne animation ;
la suspension automatique aligne ensemble la ligne, la flèche et la fiche ouverte.
Ces horodatages restent côté navigateur ; aucun relevé GPS n'est créé ni modifié.
Les ressources JavaScript sont versionnées map-anchor-1.

Contrôles de ce lot : 39 tests Node (dont 11 déplacements), 16 tests Laravel
FleetMap/DashboardMap et 134 assertions, syntaxe JavaScript réussis. Navigateur
local : ancrage -50 %/-50 % confirmé dans le DOM, trace reliée à la flèche sur
un segment vertical puis diagonal avec des positions de test SQLite isolées.
La validation locale ne constitue pas un nouvel essai routier réel.

## Carte mobile/tablette et changement d’onglet — 23 septembre 2026

Demande : conserver le véhicule sélectionné au centre sur mobile/tablette et
éviter la disparition du panneau vidéo lors du passage à un autre onglet du
navigateur. L’utilisateur reporte explicitement le diagnostic réseau ES500.

Causes corrigées : le suivi utilisait panTo au centre du canevas entier, alors
que le cadrage initial réservait une marge pour les filtres. Sur petit écran,
les marges pouvaient même dépasser la largeur de la carte. Le redimensionnement
ne réappliquait pas le suivi. En parallèle, visibilitychange appelait closeVideo
dès que document.hidden devenait vrai.

Le cadrage et le suivi partagent désormais des marges calculées sur la surface
réelle de la carte. La caméra suit la même position animée que le marqueur,
sans seconde animation de déplacement qui prend du retard. ResizeObserver,
resize, visualViewport et plein écran recalculent le cadrage sans remettre le
zoom à zéro. Sur un écran trop étroit, sélectionner un véhicule ou réduire la
largeur replie les filtres pour dégager la carte. Le glissement manuel conserve
la suspension du suivi et la vue de tous les véhicules reste collective.
Le panneau vidéo mobile dispose d’une hauteur de carte effective de 38 % ;
la hauteur minimale excessive en paysage est supprimée.

Changer d’onglet suspend seulement les actualisations GPS. Les lecteurs et
leurs sessions restent actifs. Au retour, renouvellement immédiat du bail sans
requête concurrente ; reprise si le navigateur a suspendu le lecteur, respect
d’une pause manuelle, et reconnexion automatique si le bail a expiré. Les
contrôles serveur et la réserve vidéo de quinze secondes restent inchangés.
Fermer le panneau, changer de véhicule, quitter la rubrique Carte ou fermer
la page libère toujours les sessions. Le microphone ne redémarre pas en arrière-plan.

Fichiers : public/js/google-map.js, map-view.mjs (nouveau), map-video.mjs,
live-player.mjs, public/css/google-map.css, partials/dashboard-map.blade.php
et tests JavaScript correspondants. Ressources versionnées map-responsive-1.

Validation : suite JavaScript locale de 57 tests réussis ; tests Laravel ciblés
FleetMapTest et DashboardMapTest : 16 tests, 134 assertions. Après le dernier
ajustement de repli à la rotation : syntaxe google-map.js et 15 tests de suivi/
géométrie rejoués avec succès. En production, 32 tests JavaScript ciblés passent.
Aucune suite PHP complète ni suite du listener exécutée pour ce lot.

Aperçu local SQLite isolé : à 390 × 844, marqueur centré à moins de 0,01 px
avec et sans volet vidéo ; à 768 × 1024, même centrage dans le volet carte
réduit ; filtres ouverts, centre attendu et mesuré à x=550, y=550.
Production : même vérification à 390 × 844, repli automatique des filtres et
écart nul horizontalement. Une caméra réelle a été utilisée pour le contrôle
vidéo ; aucun microphone ni écoute audio activé pour cet essai.

Déployé le 23 septembre à 18:03:01 UTC, neuf fichiers avec vérification des
empreintes et sauvegarde /var/backups/exadcam-map-responsive-20260923-180301.
Vues Blade recompilées et PHP-FPM rechargé. Aucun redémarrage GPS, vidéo ou
audio, aucune migration, aucune suppression du cache applicatif.

Limite : le système mobile peut suspendre entièrement un onglet en arrière-plan ;
le correctif conserve le panneau et permet la reprise au retour, mais ne peut
garantir une lecture pendant la suspension du navigateur par le système.
La tenue de connexion ES500 reste un diagnostic séparé, reporté par l’utilisateur.

Référence : [projection et cadrage Google Maps](https://developers.google.com/maps/documentation/javascript/reference/map).

Contrôle final du navigateur Chromium : à 768 × 1024 avec vidéo, carte de
445,45 × 948 px et écart du marqueur au centre de 0 px sur les deux axes.
Canal 2 ES500 réel en lecture, readyState=4, paused=false, panneau toujours
visible, aucune nouvelle action Lecture. Une première observation a comporté
une reconnexion automatique : le journal serveur confirme source_disconnected,
puis start_failed et une nouvelle demande vidéo, indépendamment du correctif
d’onglet. Lors du second contrôle, la source média est conservée et le temps
de lecture progresse jusqu’à 119,6 secondes. Ne pas confondre cela avec un
test d’endurance de connexion ES500.

Limite de l’automatisation navigateur : les commandes de sélection d’onglet
ne donnent pas un état document.hidden=true observable avec cet outil ; le
cas visibilitychange est donc vérifié par les tests d’événements, sans prétendre
avoir validé la suspension physique d’un téléphone. Aucun réglage permanent du
navigateur modifié ; l’override de dimensions et les onglets de contrôle sont
supprimés en fin d’essai.


## 24 septembre 2026 — Ligne solidaire de l’ancre du marqueur

map-trail-2 remplace la Polyline Google distincte par un SVG positionné à 50 % / 50 %
dans le contenu du marqueur 34×36. Flèche, étiquette et ligne partagent ainsi le même
déplacement du moteur Google. La trace est projetée en pixels Mercator relatifs au
GPS affiché, se termine exactement à (0,0), et reste derrière le symbole. Aucun
point anticipé ou inventé : map-motion conserve ses règles de trajets reçus.
Zoom et changement de position recalculent les points ; pointer-events:none,
aria-hidden, suppression avec le marqueur. Fond raster et caméra sans rotation.

Fichiers : map-marker-trail.mjs (nouveau), google-map.js, google-map.css et partial
dashboard-map.blade.php. Trois nouveaux tests géométrie/DOM ; suite JS 60/60.
Vérification navigateur avec trajet fictif, dimensions 390×844 et 768×1024 :
écart origine de ligne / centre flèche 0 px sur les deux axes. Contrôle des virages.
Déployé à 08:18:41 UTC, sauvegarde camera-fixes-20260924-081841.



## 25 septembre 2026 — Carte responsive et deux canaux en plein écran

La page Carte occupe maintenant la hauteur restante sous la barre supérieure,
y compris lorsque celle-ci passe sur deux lignes. Le panneau vidéo s'adapte à
la largeur réelle disponible après le menu latéral. Sur téléphone portrait et
tablette étroite, la carte garde 36 % de la hauteur et les vidéos défilent dans
leur propre panneau ; en paysage court, les deux zones restent côte à côte.
Les filtres peuvent recouvrir le volet vidéo sur les petits écrans sans être
limités à la petite hauteur de la carte. Le redimensionnement conserve le
suivi existant du véhicule sélectionné.

Bouton « 2 écrans » dans l'en-tête des vidéos : les deux lecteurs existants
occupent une seule surface, deux colonnes en paysage, deux lignes en portrait.
« Réduire » ou Échap revient au panneau ; la croix ferme toujours les vidéos.
L'API Fullscreen est demandée au clic, avec repli plein navigateur si elle est
indisponible/refusée. Aucun déplacement ni remplacement des éléments vidéo,
aucune nouvelle session ou demande caméra lors de cette bascule. Un seul canal
configuré utilise toute la grille. Navigation clavier, focus initial/restauré,
arrière-plan inert et restitution des attributs sont gérés. Une autorisation
plein écran tardive ne peut pas rouvrir un panneau déjà fermé.

Le format horizontal JK114 reste corrigé dans une zone 16:9 centrée, même
quand la cellule plein écran possède une autre proportion. ES500 garde son
format contain. Les contrôles audio existants restent présents ; leur moteur,
leurs réglages et leurs services ne sont pas modifiés par ce lot.

Fichiers : public/css/google-map.css, public/js/google-map.js,
public/js/map-video-fullscreen.mjs (nouveau), partial dashboard-map.blade.php,
lang/fr/map.php et lang/en/map.php. Version navigateur map-layout-20260925.
Nouveau test local : tests/js/map-video-fullscreen.test.mjs.

Contrôles effectivement exécutés sur la version finale : 74 tests JavaScript
réussis, dont huit sur la nouvelle présentation et le dimensionnement ; 17 tests
PHP ciblés / 126 assertions (DashboardMapTest, DashboardVideoTest, LiveAudioTest)
avec SQLite en mémoire et caches de test séparés. Syntaxe JS et traductions PHP
vérifiées. Pas de suite PHP complète ni de test listener pour ce lot d'interface.

Aperçu local rendu depuis les vrais partials et styles, avec deux flux canvas
de test : 320×568, 390×844, 844×390, 768×1024, 1024×768, 1366×768.
Aucun dépassement de la page aux dimensions contrôlées ; défilement interne
du volet préservé. Les deux flux gardent leurs sources et leurs temps de lecture
continuent après entrée/sortie. Le cas 320×568 initialement trop étroit a été
corrigé puis revérifié, ainsi que les filtres et le paysage 844×390. Ce sont
des dimensions de navigateur, pas des essais sur six appareils physiques.

Déploiement de six fichiers le 25 septembre à 11:17:48 UTC (12:17:48 Kinshasa),
empreintes avant/après contrôlées, sauvegarde :
/var/backups/exadcam-map-layout-20260925-111748.
Vues Blade recompilées et PHP-FPM rechargé. GPS PID 156157, vidéo PID 165314,
audio PID 167293 inchangés. Aucune migration ni purge des baux/cache métier.
Les trois ressources JS/CSS publiques répondent HTTP 200 avec les empreintes
attendues. Une page déjà ouverte nécessite un vrai rechargement, pas seulement
une nouvelle navigation vers le même fragment #map.

Contrôle en production après rechargement : les deux canaux réels JK114 du
Hilux 0210BW01 sont en lecture dans le panneau agrandi (1536×730, deux cellules
de 751×552,6). readyState=4 et paused=false sur les deux. Après « Réduire »,
sources média identiques et temps de lecture progressant de 112,48 à 132,40 s
et de 89,50 à 109,42 s. Aucune écoute ni microphone activé. Le panneau de test
est ensuite fermé pour libérer ses deux sessions. Ce contrôle ne prétend pas
valider la nouvelle intermittence audio ES500 ni la suspension d'un mobile.


Complément du 25 septembre 2026 : à la demande explicite « deploie tout »,
les documents de ce lot et tests/js/map-video-fullscreen.test.mjs sont aussi
synchronisés sur le serveur EXADCAM. Les huit tests ciblés passent sur Linux.
Les six fichiers applicatifs sont vérifiés identiques, sans nouveau déploiement
fonctionnel ni redémarrage des services caméra.


## 5 octobre 2026 — Historique global et détaillé sur la carte

Demande : appliquer à EXADCAM et EXAD Tracking Mobile le panneau d’historique inspiré des captures Navixy déjà livré sur EXAD Tracking web.

EXADCAM : nouvelle action « Historique et trajets » dans la fiche sur la carte. Panneau blanc compact, périodes prédéfinies et dates personnalisées, résumé global puis chronologie des trajets, stationnements et arrêts moteur allumé. Sélection exclusive ou multiple, tout sélectionner, lignes colorées avec flèches, repères départ/arrivée, parking P, lecture visuelle du parcours, réduction/fermeture. La fiche technique et l’historique GPS paginé existants restent accessibles. Le suivi automatique ne déplace pas la carte pendant la consultation de l’historique.

Calcul : MapTripHistoryService utilise une fenêtre unique de FleetReportBuilder sans période comparative. Les règles des rapports sont conservées ; pour la carte un arrêt observé d’au moins 60 secondes peut être affiché. Un trajet requiert 60 secondes, 100 m parcourus et une emprise de 50 m ; coupures de plus de 300 secondes et sauts GPS ne sont pas reliés. Les trajets peuvent donc différer du découpage d’un autre fournisseur. Les stationnements ne sont pas étendus jusqu’à la fin de la période. Les repères utilisent les coordonnées GPS des extrémités. Les lieux sont présentés en coordonnées exactes : aucune adresse de rue inventée, aucun nouvel appel de géocodage externe. La lecture est une animation du tracé, pas une reconstitution horodatée de la vitesse.

Accès : GET /map/vehicles/{vehicle}/trips, permission map.view, flotte visible/active, caméra active actuellement affectée au véhicule, bornes des affectations caméra/véhicule et véhicule/flotte. Dates locales interprétées dans le fuseau demandé, stockage UTC, période de 32 jours maximum, limitation de débit et taille/temps du calcul, Cache-Control privé sans stockage. Réponses tardives ou annulées ignorées. Aucun changement de paramètres caméra ni commande envoyée.

Fichiers : MapController, MapTripHistoryService, FleetMapService, FleetReportBuilder, routes/web.php, dashboard-map.blade.php, google-map.js, map-trip-history.mjs, map-trip-history.css et traductions map FR/EN. Tests : MapTripHistoryTest et map-trip-history.test.mjs.

Contrôles effectués : 31 tests PHP ciblés (273 assertions) couvrant carte, historique et rapports ; 7 tests ciblés supplémentaires (40 assertions, dont des cas déjà inclus dans les 31) pour historique et rendu Blade. 18 tests JavaScript avec carte/DOM simulés, dont sélection, repères et fermeture. Syntaxes PHP/JS, compilation des vues de production et diff --check vérifiés. Aucun essai visuel dans un navigateur connecté ni essai caméra physique pendant ce lot.

Déploiement EXADCAM réussi le 2026-10-05T12:57:15.553531+00:00 : 11 fichiers installés et empreintes contrôlées, route disponible, vues reconstruites, PHP-FPM rechargé. /login, /up et les deux ressources d’historique répondent HTTP 200. Sauvegarde : /var/backups/cam-history-20261005-125714.tar.gz. GPS, vidéo et audio actifs, PID conservés. Aucun redémarrage des listeners, aucune migration de base.

EXAD Tracking Mobile : API de trajets étendue de façon additive avec history.items, durées de parking et extrémités exactes, déployée le 2026-10-05T12:56:46.627123+00:00. Panneau intégré à la carte (en bas sur téléphone, à gauche sur écran large), vue globale, détail, sélection multiple, parking, départ/arrivée et lecture. Les anciennes réponses sans history restent lisibles. Build 1.0.0+40 signé produit ; il n’est pas publié dans Google Play par ce déploiement web.


## 6 octobre 2026 — Design EXADCAM, Trajets/Détails et vitesse de lecture

Demande : conserver le design EXADCAM pour le panneau, nommer les actions simplement « Trajets » et « Détails », ajouter la vitesse manquante.

Livraison : libellés FR/EN raccourcis dans la fiche et le titre du panneau, icônes route/information distinctes, composants locaux arrondis, couleurs de marque, dates et commandes plus lisibles. Panneau de 420 px à gauche ; sur petit écran, départ à 30 % de la carte. Sélecteur ×1/×2/×4/×8/×16/×32/×64, modifiable pendant la lecture, désactivé lorsque plusieurs trajets ou un parking sont sélectionnés. Le temps total de lecture correspond désormais à la durée du trajet divisée par la vitesse choisie. La progression conserve les fractions (range step=any), y compris sur les longs trajets. L’animation reste une interpolation du tracé, sans reconstitution des horodatages de chaque point.

Fichiers : public/js/map-trip-history.mjs, google-map.js, public/css/map-trip-history.css, dashboard-map.blade.php, traductions map FR/EN ; tests/js/map-trip-history.test.mjs. Versions des assets modifiées pour invalider le cache navigateur.

Contrôles exécutés pendant ce lot : 5 tests Node du panneau (dont vitesses, durée, fin, disponibilité du sélecteur), syntaxe JS, 3 tests DashboardMapTest / 14 assertions sur SQLite isolé, diff --check ciblé. Aucun navigateur connecté pour une recette visuelle web. Pas de nouvelle validation caméra physique.

Déployé le 2026-10-06T07:23:26.968072+00:00, six fichiers avec empreintes avant/après contrôlées. Sauvegarde /var/backups/cam-history-20261006-072325.tar.gz (SHA-256 7b359e559bd88e1e396866c131ca3013f749404703358953618aa1e325d86be6). Vues recompilées, PHP-FPM rechargé ; login/up et ressources CSS/JS HTTP 200 avec empreintes conformes. GPS, vidéo et audio actifs avec PID conservés. Aucune migration, aucun changement de listener ni de configuration d’équipement.


## 6 octobre 2026 — Widgets cliquables et navigation client

Demande : widgets du tableau de bord cliquables, véhicules équipés vers Véhicules, Dashcams vers le registre pour superadmin et vers Véhicules pour les clients, suppression du menu Dashcams client, libellés Véhicules en ligne/hors ligne et accès à la carte filtrée ; alertes vers Alertes.

Livraison : les indicateurs sont des liens natifs accessibles au clavier, avec survol et focus visibles. La carte s’ouvre via #map?connection=online ou offline ; ces liens supportent le rechargement et le retour navigateur. Le raccourci réinitialise flotte/département/recherche, ferme les anciens trajets/vidéos, désactive le suivi individuel et active Afficher tous avant cadrage. Le filtre utilise online (pas uniquement les états GPS moving/offline) : un véhicule stationné ou sans position GPS peut être connecté ; une caméra jamais connectée appartient au filtre hors ligne. Les véhicules sans caméra active sont exclus du filtre hors ligne. Les véhicules sans position restent consultables dans la liste, sans point inventé sur la carte.

Client : menu Dashcams retiré, anciens liens #dashcams redirigés vers Véhicules. Indicateurs et graphique utilisent les libellés véhicules et les compteurs online_vehicles/offline_vehicles, une fois par véhicule équipé, cohérents avec la source choisie par la carte. Le superadmin conserve les compteurs et le registre des caméras. Le widget de disponibilité ouvre le registre adapté au rôle ; ses liens En ligne/Hors ligne ouvrent la carte filtrée. Les permissions existantes restent appliquées : sans droit de gestion des véhicules, le raccourci ouvre la liste de véhicules en lecture seule ; sans map.view, pas de lien vers carte/alertes. Les accès vidéo existants sont conservés.

Fichiers : DashboardService, welcome, nouveau partial dashboard-metrics, sidebar, layout app, dashboard-charts, dashboard-map, app.js, google-map.js, nouveau map-dashboard-filter.mjs, dashboard-real.css et traductions dashboard FR/EN. Tests : RealDashboardTest et dashboard-map-filter.test.mjs.

Contrôles effectués : 26 tests PHP ciblés / 234 assertions (tableau de bord, carte et rendu), base SQLite en mémoire, et 5 tests Node (filtres, remise à zéro, navigation, liens clients/superadmin). Syntaxes PHP/JS et diff --check ciblé conformes. Premier essai PHP interrompu par un conflit d’identifiants dans une caméra fictive du nouveau test, corrigé avant le passage final. Aucun navigateur connecté pour une recette visuelle interactive web.

Déployé le 2026-10-06T08:30:37.336247+00:00 : 13 fichiers, empreintes avant/après vérifiées, vues recompilées et PHP-FPM rechargé. Sauvegarde /var/backups/cam-dashboard-widgets-20261006-083034.tar.gz, SHA-256 a04718a68aac8fd94b5ef02834d9adec22ca0aed9bea66d1428fb7dc85e7dcd5. Login/up et quatre ressources publiques HTTP 200, ressources conformes aux empreintes. GPS, vidéo et audio actifs avec PID inchangés. Aucune migration, aucune modification des équipements. Reçus et sources avant/après : DASHCAM/analysis/dashboard-widgets-20261006/.

## 7 octobre 2026 — Simplification du panneau de carte

Demande : conserver trois compteurs sur une ligne (Véhicules, En ligne, Hors ligne), retirer Localisées/Positionnés et le sélecteur État/Tous les états, réserver le filtre Flotte au superadmin. Même présentation simplifiée côté client, dans le design propre à chaque plateforme.

Les compteurs sont accessibles au clavier et permettent de sélectionner tous les véhicules, ceux en ligne ou ceux hors ligne ; le compteur Véhicules permet de revenir à tous les états après un raccourci du tableau de bord. L'état actif reste visible. Le champ d'état interne est caché, les liens filtrés existants sont conservés. Trois colonnes sans défilement horizontal sur petit écran ; icônes sous les libellés. Permissions serveur inchangées.

EXADCAM : le sélecteur Flotte n'est plus rendu pour les clients ; le code accepte son absence, y compris dans applyDashboardConnection. Le filtre Département reste disponible lorsqu'il existe des départements. Fichiers : dashboard-map.blade.php, google-map.css, google-map.js, map-dashboard-filter.mjs ; test existant dashboard-map-filter.test.mjs complété pour l'absence du sélecteur client. Assets versionnés map-panel-20261007.

Contrôles de ce lot : 26 tests PHP ciblés / 234 assertions (DashboardMapTest, FleetMapTest, RealDashboardTest), base SQLite isolée ; 5 tests Node de filtres/navigation réussis. Syntaxe JS et diff --check ciblé conformes. Aucun navigateur connecté pour une recette visuelle interactive. Pas de validation matérielle nécessaire pour ce changement d'interface.

Déployé le 2026-10-07T07:53:50.826106+00:00 : quatre fichiers applicatifs, empreintes avant/après et ressources HTTP contrôlées, vues recompilées, PHP-FPM rechargé. Sauvegarde /var/backups/cam-map-panel-20261007-075349.tar.gz, SHA-256 2d7f8e920a64591b51da2c9d306c61b2ae0cbd3352a8d80f65860654896fcb9a. Login, santé et ressources modifiées HTTP 200. Services actifs, PID des listeners inchangés. Aucune migration ni commande aux équipements.

Modification également livrée sur EXAD Tracking web, à la demande explicite de l'utilisateur. Sources, sauvegardes locales et reçus : DASHCAM/analysis/map-panel-20261007/.

## 7 octobre 2026 — Sélecteur flottant et résultats de tous les véhicules

Demande : faire flotter le sélecteur Flotte sans déplacer les éléments du dessous et afficher les véhicules dans les résultats lorsque « Afficher tous les véhicules » est coché, même sans recherche.

Menus de sélection de la carte en position absolue au-dessus du contenu, ouverts vers le haut si nécessaire, hauteur bornée à l'espace visible et défilement limité aux options. La mise au point ne fait plus défiler le panneau. Fermeture lors du défilement du panneau parent ; fermeture extérieure, clavier et sélection existants conservés. Portée CSS/JS limitée aux panneaux de carte, comportement des formulaires/modales préservé.

Les résultats apparaissent lorsque la case est cochée ou qu'une recherche est saisie. Suppression des plafonds d'affichage (50 EXADCAM, 12 EXAD Tracking) : toutes les entrées du jeu de données filtré sont accessibles dans la liste défilante. Les filtres de flotte, d'état et de recherche restent appliqués. Pas de modification de l'API ni des autorisations ; EXAD Tracking conserve son flux cartographique de véhicules positionnés. EXADCAM conserve également l'affichage des résultats via les raccourcis En ligne/Hors ligne.

Version des ressources modifiées : map-overlay-20261007. Aucun navigateur connecté pour une recette visuelle interactive ; syntaxe JS et diff --check ciblé conformes.

Fichiers EXADCAM : public/js/searchable-select.js, public/js/google-map.js, public/css/google-map.css, resources/views/partials/dashboard-map.blade.php, resources/views/dashcams/module.blade.php (version du sélecteur partagé).

Contrôles exécutés : 16 tests PHP / 134 assertions (DashboardMapTest et FleetMapTest) sur SQLite isolé ; 5 tests Node de filtres/navigation réussis. Il s'agit de tests ciblés existants, pas d'une validation visuelle de la géométrie du menu.

Déployé le 2026-10-07T08:12:27.996261+00:00 : cinq fichiers applicatifs avec empreintes avant/après vérifiées. Sauvegarde /var/backups/cam-map-overlay-20261007-081225.tar.gz, SHA-256 34e66341cbe7aa2a09076e8c0ac0769f2ff069e9b43ae8674e11d57edda0cb12. Vues recompilées, PHP-FPM rechargé. Login, santé et ressources CSS/JS HTTP 200, empreintes des ressources conformes. Services actifs et PID des listeners conservés. Aucune migration ni commande aux équipements.

Reçus et sources avant/après : DASHCAM/analysis/map-overlay-20261007/. Même correction livrée sur EXAD Tracking web.

## 8 octobre 2026 — Sélection visible et démarrage vidéo depuis la carte

Demande : conserver la liste après sélection pour lire les vitesses, ouvrir automatiquement la fiche et lancer les vidéos au clic sur Vidéos. Afficher clairement le chargement ; réserver Lecture à la reprise après arrêt.

La sélection d'une ligne garde tous les résultats filtrés et le panneau visibles, centre le véhicule et ouvre sa fiche. Liste et fiche continuent d'actualiser les vitesses. Le panneau ne se replie plus automatiquement lors de cette sélection ou d'un redimensionnement. Une sélection pendant le chargement du SDK ouvre la fiche lorsque la carte devient prête. L'ouverture du panneau vidéo conserve son organisation responsive existante (panneau de filtres replié pendant la vidéo, restauré à sa fermeture).

Vidéos prépare immédiatement un indicateur par canal, puis démarre automatiquement les canaux configurés, dans la limite des deux lecteurs existants, après libération des anciennes sessions. Cette demande remplace le démarrage manuel antérieur sur la carte. Lecture apparaît après arrêt ou pause ; en cas de refus de lecture automatique par le navigateur, Démarrer la vidéo reprend la même session ; une erreur définitive propose Réessayer. Les contrôles audio restent manuels.

Un cercle animé et des textes français/anglais distinguent Connexion à la caméra, Chargement de la vidéo et Reconnexion. Le chargement reste visible pendant la préparation et la première image en attente du tampon, puis disparaît seulement à la lecture effective. Les lecteurs exposent aria-busy et un statut accessible ; animation neutralisée si réduction du mouvement demandée. Les profils de tampon, transports HLS, autorisations et temporisations serveur existants ne changent pas.

Chaque canal conserve son bail et son arrêt indépendants. Un arrêt pendant la préparation annule le démarrage différé ; fermeture, changement de véhicule et événements média tardifs ne relancent pas la vidéo. Les canaux absents et comptes sans autorisation vidéo ne lancent aucune requête. Un défaut d'autorisation conserve l'état d'erreur après nettoyage du lecteur.

Fichiers : google-map.js, nouveau map-video-player.mjs, google-map.css, partial dashboard-map, traductions map FR/EN ; nouveaux tests map-video-player.test.mjs et map-selection.test.mjs.

Contrôles : 64 tests Node ciblés réussis (15 nouveaux, plus 49 existants : sessions, tampons, reprise, plein écran, sélection, filtres et navigation), et 16 tests PHP existants / 134 assertions (DashboardMapTest, FleetMapTest), SQLite en mémoire. Syntaxes PHP/JS et diff --check conformes. Le premier passage du test d'intégration a nécessité d'ajouter ResizeObserver au navigateur simulé ; passage final réussi. Aucun navigateur connecté (liste vide), donc pas de recette visuelle interactive ni de lecture sur caméra réelle dans ce lot. Les tests utilisent des flux et SDK simulés.

Déployé le 2026-10-08T09:08:02.957312+00:00 : six fichiers applicatifs, empreintes vérifiées, vues recompilées et PHP-FPM rechargé. Sauvegarde /var/backups/cam-cam-map-live-20261008-090800.tar.gz, SHA-256 5e8c9f4877d313156fddd78506460f6a21f303aec39196d416db8b7497ed3962. Login/up et trois ressources CSS/JS HTTP 200, ressources conformes aux empreintes. Services GPS, vidéo et audio actifs, PID conservés. Aucun changement de configuration matérielle, migration ou redémarrage des listeners.

Sources avant/après, tests et reçu : DASHCAM/analysis/cam-map-live-20261008/.

## 8 octobre 2026 — Filtres carte visibles pendant la vidéo

Précision utilisateur : le panneau Filtres carte doit rester ouvert également au lancement et pendant la vidéo, pour garder les véhicules et vitesses accessibles. Cette règle remplace le repli vidéo décrit dans le lot précédent.

L'ouverture Vidéos maintient ou ouvre le panneau de filtres, sans lui transférer le focus. La fermeture vidéo ne restaure plus un ancien état replié. Le bouton de repli manuel reste utilisable. Sélection, vitesses actualisées, démarrage automatique et chargement des canaux conservés. Fichiers applicatifs : google-map.js et version du script dans dashboard-map.blade.php (cam-video-filters-20261008).

Cinq tests d'intégration existants map-selection.test.mjs réussis ; le scénario vidéo vérifie désormais le maintien des filtres et des vitesses pendant le direct, ainsi que l'ouverture depuis un panneau préalablement replié et la fermeture vidéo. Syntaxe JS et diff --check conformes. Pas de nouvelle recette visuelle interactive ou matérielle ; les tests emploient un navigateur et des lecteurs simulés.

Déployé le 2026-10-08T09:16:45.805768+00:00, deux fichiers vérifiés, vues recompilées. Sauvegarde /var/backups/cam-cam-video-filters-20261008-091642.tar.gz, SHA-256 f0e1d8ee121d95b30ccb6281c4b5543d12160068ef7c7dfaa0854c8603816085. Login/up et script public HTTP 200 avec empreinte conforme. GPS, vidéo et audio actifs avec PID conservés. Reçu et sources : DASHCAM/analysis/cam-video-filters-20261008/.


## Mise à jour du 8 octobre 2026 — Détails et états

Déployé : la fiche Détails ouvre directement la synthèse. L'onglet Historique GPS, sa date, sa table et sa pagination ne sont plus affichés. Le navigateur demande /map/vehicles/{vehicle}/details?source_id=…&summary_only=1 ; ce mode omet history et les métadonnées de pagination et n'interroge pas dashcam_positions. Les contrôles de flotte/source et la projection technique réservée au superadmin restent appliqués. Le contrat historique sans summary_only reste compatible ; Trajets n'est pas retiré.

Icônes carte, résultats et légende : moving #10b981 (flèche), offline #ef4444 (cercle), parking bleu existant #229bd8. Les autres états restent distincts. Les étiquettes de marqueur suivent le vert/rouge de déplacement/connexion ; les points de connexion hors ligne sont rouges. Versions des ressources : cam-alerts-map-20261008. Sélection, filtres, vidéos automatiques et chronologie des trajets conservés. Voir project-history.md pour les tests et le reçu de déploiement.
