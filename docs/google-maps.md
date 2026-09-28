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
