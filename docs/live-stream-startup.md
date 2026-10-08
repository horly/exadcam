# Démarrage du direct — 24 septembre 2026

Demande : l'ouverture du direct sur les deux modèles est lente par rapport à
CarAssist. L'utilisateur distingue le retour normal au démarrage du véhicule
et les absences EXADCAM de l'autre caméra encore accessible via CarAssist.
Ne pas déduire une correspondance marque/modèle de son terme « ESTON ».

## Modification

Le lecteur commun imposait 15 secondes de réserve avant toute lecture. Il
démarre maintenant avec 8 secondes continues et garde la réserve de 15 secondes
après un véritable épuisement. L'image initiale reste visible pendant la
préparation, et la dernière image pendant la reprise. Les pauses manuelles,
contrôles d'accès, audio, microphone et leases indépendants sont conservés.
La vérification de disponibilité passe de 5 à 1 seconde avant le premier
manifest, puis revient à 5 secondes. Le délai total inclut encore la réponse
caméra, la première image clé, le multiplexage et le réseau : ce n'est pas une
promesse de démarrage en 8 secondes ni une correction du réveil cloud.

Les imports et trois entrées (carte, liste dashcams, dashboard) sont versionnés
live-startup-20260924b. Aucun changement au listener/FFmpeg/firmware.

## Validation et déploiement

62 tests JavaScript locaux réussis sur la version finale. Couverture ajoutée :
démarrage à 8 secondes sans compter les trous du tampon, HLS natif, remplissage
de 15 secondes après manque de données et transition du polling 1 à 5 secondes.
Pas de nouvelle suite Laravel complète : seulement le cache des vues compilé
en production pour trois modifications de version de ressources.

Une première version à 4 secondes a été déployée à 11:47:38 UTC puis ajustée
après essai réel : ES500 fiche 4, deux canaux en lecture au relevé à environ
19 secondes du clic. Au relevé à 41 secondes, CH2 attendait sa réserve ; à
63 secondes les deux lisaient de nouveau. Ce constat motive 8 secondes.
Flux d'essai fermés par les boutons Arrêter, sans déconnexion GPS.

Version finale installée à 11:51:09 UTC. Sauvegarde :
/var/backups/exadcam-live-startup-20260924-115108.
Version antérieure initiale (réserve 15 secondes) conservée dans
/var/backups/exadcam-live-startup-20260924-114738.
Huit fichiers déployés avec contrôle des SHA-256 et sauvegarde préalable.
Cache des vues reconstruit, aucun cache de leases vidé, aucun service redémarré.
PID inchangés : GPS 156157, vidéo 151192, audio 151193.
Les scripts versionnés finaux sont visibles dans le DOM de production.

Les relevés complémentaires des essais finaux sont consignés ci-dessous.

ES500 fiche 4 : après remplacement volontaire de la première sélection, les
sessions de contrôle finales commencent à 11:51:43.981 et 11:51:44.160 UTC.
Au relevé à environ 48 secondes du clic : CH1 34,00 s, CH2 33,79 s, les deux
paused=false, readyState=4, 960×540, tampon jusqu'à 42,50 s. À environ 68 secondes :
CH1 54,38 s / tampon 62,50 s ; CH2 54,17 s / tampon 60,00 s, paused=false.
Progression régulière entre ces deux relevés ; pas de coupure média dans le
journal de cette session avant l'arrêt volontaire à 11:52:51/52 UTC. Cela ne
constitue pas un test d'endurance ni une mesure précise du tout premier démarrage.

JK114 fiche 3 : au relevé à environ 43 secondes du clic, les deux canaux sont
en lecture, 720×576, CH1 28,56 s / tampon 38,10 s, CH2 30,04 s / tampon 38,30 s.
À environ 72 secondes, ils sont encore en lecture : 57,67 s et 59,15 s,
tampons 68,10 et 68,30 s. Arrêt explicite des deux lecteurs après ce relevé.
Aucun microphone ni écoute ouverts pendant ces essais.

Vérification HTTP finale des cinq fichiers JS publics : 200 et SHA-256 conforme
pour chaque ressource à 11:54 UTC. Ces essais valident les deux appareils
présents, pas Véhicule 2 absent. Les résultats ne garantissent pas l'absence de
futures coupures 4G et ne valident pas encore un retour autonome après veille.

## Reconnexion toujours non résolue

Véhicule 2 reste sans session au contrôle final 11:54:03 UTC ; dernier contact
11:29:07, dernière position 11:29:06. Son absence suit l'essai RST précédent.
Pas de nouvelle coupure ni modification caméra dans ce lot vidéo. L'utilisateur
ne peut pas réappliquer Submit maintenant. Timeout TCP 30 s/retransmissions 3
encore appliqués ; restauration gardée vers 10 s/0 en attente de retour.
Voir docs/es500-auto-return.md pour la commande et les preuves.

La réponse 0x1003 ignorée par le calcul de présence reste un défaut distinct
identifié, non corrigé dans le listener par ce lot. Le réveil CarAssist utilise
son cloud ; aucun accès API/SDK ni session d'intégration autorisée disponible.
Ne pas annoncer un retour automatique livré ni une surveillance en arrière-plan.

Référence technique consultée :
https://raw.githubusercontent.com/video-dev/hls.js/master/docs/API.md
Les réglages liveSyncDuration 18, liveMaxLatencyDuration 40 et taille maximale
de réserve sont conservés ; le seuil initial est une politique du lecteur EXADCAM.

# Optimisation de la préparation vidéo — 24 septembre 2026

L'utilisateur signale encore une ouverture trop lente après le premier ajustement
du tampon. Mesure séparée de la commande, des premiers paquets vidéo, du premier
manifest HLS et de la réserve disponible. Aucun changement de firmware ou de
configuration matérielle ; le service GPS reste actif.

## Mesure avant / après, ES500 Hilux fiche 4, canal 2

| Étape depuis la demande serveur | Avant | Après |
| --- | ---: | ---: |
| Réponse à la commande | 1,885 s | 1,613 s |
| Premières données reçues | 8,900 s | 8,386 s |
| Premier fragment prêt | 12,020 s | 9,950 s |
| Réserve nécessaire au démarrage prête | 19,567 s (10 s disponibles, seuil 8 s) | 12,823 s (4 s disponibles, seuil 4 s) |

Le gain mesuré de préparation est 6,744 s (environ 34 % sur ces deux essais).
Ce n'est pas une mesure exacte clic → lecture navigateur, ni une garantie de
délai : l'appareil met ici près de huit secondes à commencer l'envoi.
Les mesures JK114 ont réutilisé un flux existant et/ou reçu des données par
intermittence ; elles ne fournissent pas de comparaison fiable de démarrage à froid.

## Implémentation

- Nouveau profil serveur pour les modèles JK114 et ES500-603 : décodage H.264,
  encodage libx264 veryfast/zerolatency, CRF 20, dimensions conservées, YUV420P,
  une image clé par seconde, sans B-frames, un thread de décodage et d'encodage.
- Analyse initiale réduite, cadence configurée conservée, correction des timestamps
  ES500 conservée. HLS : fragments indépendants de 1 s, fenêtre de 40 fragments
  (environ 40 s), suppression progressive ; limite existante de stockage 128 Mio.
- Le serveur annonce une réserve initiale de 4 s uniquement avec ce profil.
  Ancien serveur, modèle inconnu, profil `VIDEO_HLS_PROFILE=copy` ou valeur invalide :
  réserve de 8 s conservée. Reprise après épuisement à 15 s inchangée.
- Un timeout/503 de la commande de départ ne détruit plus immédiatement la demande
  déjà autorisée ni ne lui envoie Stop : la connexion vidéo peut encore arriver
  dans la limite existante d'inactivité de 30 s, contrôlée toutes les 5 s.
  Identité, registre, présence GPS avant démarrage et révocations sont vérifiés.
  Un refus matériel explicite continue à échouer sans envoyer Stop sur un flux
  potentiellement utilisé ailleurs. Aucun flux déclaré prêt sans manifest réel.
- Carte, dashboard et liste dashcams partagent les options et les versions
  `live-pipeline-20260924`. Aucun changement au microphone ou à l'écoute.

Compromis : le profil effectue désormais une recompression du direct ; mêmes
dimensions, mais pas de conservation bit à bit des images comprimées. Il consomme
plus de CPU que la copie du H.264. Serveur constaté : 12 CPU, environ 48 Go RAM.
La fluidité reste dépendante des données réellement fournies par l'appareil et
du réseau ; une réserve plus courte ne masque pas une longue interruption source.

## Tests réellement exécutés

- Suite Linux isolée : 33 tests réussis avec FFmpeg réel, aucun ignoré, avant
  le dernier ajustement de métadonnées/ACK ; inclut le test GPS silencieux de 190 s.
- Après métadonnées de réserve : 7 tests vidéo ciblés réussis.
- Après correctif ACK : 8 tests vidéo ciblés réussis, dont flux réel arrivant
  après un 503 de commande, respect du refus explicite, droits et révocations.
- Suite JavaScript locale finale : 64 tests réussis, dont profil 4/8 s, tampon
  contigu, HLS natif, pause manuelle, deux canaux et reprises.
- Test complémentaire isolé : en-tête H.264 60 fps alimenté à 12 fps, décodage
  des fragments indépendants et première publication avant 2,5 s : 2 tests passés.
  Cette vérification n'a pas modifié la production.
- Pas de suite Laravel complète rejouée : changements Blade limités aux versions
  des scripts, cache des vues reconstruit.

## Production et essais

Premier déploiement à 12:24:04 UTC, sauvegarde
/var/backups/exadcam-live-pipeline-20260924-122401.
Complément ACK à 12:32:24 UTC, sauvegarde
/var/backups/exadcam-live-pipeline-20260924-123223.
Dix fichiers applicatifs contrôlés par empreinte, dont deux fichiers également
copiés dans le runtime listener. Seul exadcam-video est relancé (PID final 161604).
GPS reste PID 156157, audio PID 151193 ; aucun cache de leases vidé.
Le redémarrage vidéo peut provoquer une reprise des lecteurs existants ; cela
a été annoncé avant l'installation. Aucun reset de connexion GPS ni réglage
des caméras effectué. Véhicule 2 est revenu avant ces changements.

Véhicule 2, deux canaux réellement lus à environ 19 s du clic : 960×540,
readyState=4, temps 9,55 s et 3,03 s. Canal 2 cesse ensuite d'alimenter son tampon,
connexion média fermée à 12:25:33 UTC, remplacée automatiquement à 12:25:38.
Canal 1 a aussi rempli à nouveau son tampon. Au relevé à 163 s, les deux lisent
de nouveau, temps 133,16 s et 83,75 s ; réserve environ 19 s et 17 s.
Ces constats ne valident pas une disparition des coupures source ES500.
Les deux lecteurs d'essai ont été arrêtés explicitement à 12:27:58/12:28:01.

JK114 fiche 3 : timeouts de commande et données vidéo intermittentes pendant
la fenêtre ; lecture continue non confirmée. Contrôle de cadence final à suivre
dans les preuves et la conclusion ajoutée ci-dessous. Ne pas la présenter
comme rétablie sans validation réelle.

Après le complément ACK, la JK114 reste très intermittente au contrôle navigateur
(deux secondes seulement dans le tampon CH1 au relevé à 139 s, CH2 sans image).
Capture réseau bornée de 12 s : 33 paquets capturés, 36 reçus par le filtre,
0 perte noyau. Reconstitution : seulement deux images complètes identifiées
sur CH2, une erreur de fragmentation ; autre flux inexploitable avec un trou
TCP dans cette courte capture. La cadence calculée sur 79 ms d'horodatages source
n'est PAS une mesure de cadence régulière sur douze secondes. Ne pas inférer
une cause matérielle exacte de cet échantillon ni garantir la lecture JK114.
Les flux d'essai JK114 sont arrêtés explicitement à la fin du contrôle.
La capture brute reste réservée à root sur le serveur, pas copiée dans le projet.

Sources techniques primaires consultées :
https://ffmpeg.org/ffmpeg-formats.html#hls-2
https://ffmpeg.org/ffmpeg-codecs.html#libx264_002c-libx264rgb

Preuves, sources avant modification, manifestes, scripts de déploiement et mesures :
workspace analysis/live-pipeline-20260924/, copie serveur du même nom sous
/home/exad-cam/. Les identifiants d'authentification ne sont pas documentés.

## 25 septembre — Nettoyage média résistant aux fichiers encore occupés

Suppression asynchrone et reprises bornées dans media-cleanup.js, pour éviter
qu'une erreur ENOTEMPTY à la fermeture d'un flux termine tout le service vidéo.
Treize tests vidéo/profil/nettoyage réussis sur Linux. Sources installés avec
sauvegarde ; activation conditionnée à une fenêtre vide du service. Lire
/home/exad-cam/smartvision-maintain-20260925/idle-activation.json avant d'affirmer
le correctif actif. Ce lot ne modifie ni l'interphone ni les paramètres caméra.

Complément de contrôle : le correctif vidéo a été activé automatiquement dans
une fenêtre sans flux ; le service a redémarré sainement et le PID GPS est
resté inchangé. Le correctif GPS/ACK attend encore sa propre fenêtre vide.
Le pont TCP reste actif pour les reconnexions sous l'ancien processus.

Complément final d'activation : les deux correctifs GPS et vidéo sont désormais
ACTIFS. Le GPS a été rechargé dans une fenêtre sans connexion, après fermeture
spontanée des sessions. Le résultat idle-activation.json confirme les deux
activations. Le pont TCP temporaire s'arrête avec l'ancien GPS ; le nouveau
processus applique directement transport-policy.js. Véhicule 2 avait également
repris contact pendant les contrôles précédents. Les retours ponctuels et les
tests ne démontrent pas une stabilité prolongée. Les affirmations « en attente »
plus haut décrivent uniquement les étapes précédentes de ce lot.

## 8 octobre 2026 — Ouverture directe depuis la carte

À la demande de l'utilisateur, Vidéos sur la fiche carte démarre les deux canaux disponibles automatiquement ; le bouton Lecture sert à reprendre après arrêt/pause. Le panneau montre immédiatement la connexion, puis le chargement jusqu'à la lecture effective. Une première image fixe ne masque plus l'indicateur de préparation. Reconnexions signalées, sessions indépendantes, arrêt pendant chargement et fermetures protégés par générations. Repli Démarrer si le navigateur exige une interaction ; Réessayer sur erreur définitive. Audio et réserves HLS existants conservés. Nouveau contrôleur d'interface map-video-player.mjs, commun aux deux lecteurs de la carte, sans changement du lecteur HLS partagé.

Déployé le 2026-10-08T09:08:02.957312+00:00. Vérifications ciblées du lot : 64 tests Node, 16 tests PHP / 134 assertions, ressources publiques HTTP 200 et empreintes conformes. Aucun essai sur caméra réelle ni navigateur connecté ; aucune nouvelle mesure de latence matérielle. Voir la dernière entrée de project-history.md et le reçu DASHCAM/analysis/cam-map-live-20261008/cam-receipt.json.
