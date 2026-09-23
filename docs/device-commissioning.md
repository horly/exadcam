# Raccordement des deux dashcams — 22 septembre 2026

État vérifié à 09:24 UTC, soit 10:24 Africa/Kinshasa. Les modèles sont les noms
communiqués par l'utilisateur ; aucune identification commerciale supplémentaire
n'est déduite du protocole.

| Modèle | IMEI autorisé | Configuration communiquée | Résultat côté EXADCAM |
| --- | --- | --- | --- |
| JK114 | 867934087965630 | d.gps51.com:808 + exadcam.app:7808, protocole 2019 | Authentifiée en JT808 2019 ; GPS et deux canaux vidéo validés côté serveur |
| ES500-603 | 352538107257217 | CarAssist, principal 119.23.78.106:6608, backup 62.171.190.15:7808 | Inscrite et activée ; aucune connexion identifiable reçue au moment du contrôle |

Le configurateur de l'ES500-603 ne propose pas de choix de protocole, selon
l'utilisateur. Un statut « en ligne » sur son cloud d'origine ne suffit pas à
prouver une connexion simultanée à EXADCAM. Le mode backup peut être un secours
exclusif ; ce comportement reste à confirmer. Un essai avec EXADCAM comme serveur
principal a été demandé à l'utilisateur, sans modifier nous-mêmes l'appareil.

## Inscriptions réelles

Les deux fiches ont été créées dans la base de production uniquement, avec leurs
noms et IMEI exacts, via le modèle Laravel et sa génération de code d'authentification
chiffré. Deux canaux et une cadence HLS de 15 images/s sont les valeurs initiales.
Les identifiants de l'ancien appareil d'essai restent absents du registre.

| Paramètre | JK114 | ES500-603 |
| --- | --- | --- |
| ID interne | 1 | 2 |
| Identifiant JT808 court | 934087965630 | 538107257217 |
| Identifiant JT808 2019 | 00000867934087965630 | 00000352538107257217 |
| Identifiant vidéo autorisé | 00000867934087965630, observé | 538107257217, valeur initiale non validée |
| Fuseau de la trame GPS | UTC+1, observé | Non observé, repli global UTC+8 |

Les aliases initiaux de l'ES500-603 devront être confrontés à ses trames. Il n'y a
ni auto-enregistrement ni correspondance approximative des identifiants.

## Correctifs issus du matériel JK114

**Heure GPS.** Une trame reçue à 09:12:31 UTC contenait l'heure BCD 10:12:29.
Le décalage réel observé est UTC+1 ; la valeur globale UTC+8 du socle créait un
retard artificiel de sept heures. Ajout d'un `gps_timezone_minutes` nullable par
dashcam, communiqué par l'API interne et utilisé pour les positions et les lots.
Valeur JK114 : 60. Les autres appareils conservent leur propre valeur ou le repli.

Après sauvegarde SQL, 145 positions initiales de la JK114 ont été corrigées de
sept heures. La sélection était limitée à cette dashcam, au jour du raccordement
et aux enregistrements dont l'heure corrigée était à moins de deux minutes de
la réception. Le dernier horodatage a été recalé. Au contrôle suivant, l'âge
de la dernière position était de trois secondes.

**Identité vidéo.** Les premiers essais ont été refusés avec « Invalid media type ».
L'en-tête reçu contient en réalité dix octets BCD pour l'IMEI étendu, contre six
dans le décodeur initial. Les champs canal/type/horodatage/longueur étaient donc
décalés de quatre octets. Le décodage accepte désormais les deux longueurs.
Le choix est limité aux identités exactes des flux autorisés en attente ; aucune
heuristique basée seulement sur les zéros de tête et aucun assouplissement du registre.

`video_terminal_id` passe de 12 à 20 caractères de capacité. La validation accepte
exactement 12 ou 20 chiffres. La JK114 utilise son identifiant étendu observé ;
l'autre fiche conserve sa valeur initiale jusqu'à observation de son protocole.

Ce format étendu est également traité dans le
[décodeur JT1078 de Traccar](https://github.com/traccar/traccar/blob/master/src/main/java/org/traccar/protocol/Jt1078ProtocolDecoder.java),
consulté pour comparaison ; notre sélection repose sur les flux autorisés.

## Essais réellement exécutés

- JK114 authentifiée à 09:12:01 UTC, puis reconnectée automatiquement après mise
  à jour à 09:23:05 UTC. Positions 0x0200 et battement 0x0002 observés.
- Canal 1 : commande de direct acceptée, 591 124 octets reçus au contrôle,
  HLS analysé par ffprobe et une image décodée par FFmpeg.
- Canal 2 : même parcours, 392 947 octets reçus au contrôle et une image décodée.
- Deux sorties H.264 en 720 × 576. Le HLS produit indique 15 images/s selon la
  configuration de remuxage ; ce nombre ne constitue pas une mesure indépendante
  de la cadence du capteur. L'association route/habitacle reste à vérifier visuellement.
- Arrêt explicite de chaque flux après essai. Tampon média vide au contrôle final ;
  aucune archive vidéo permanente créée, aucun envoi vers un service tiers.
- Services GPS/vidéo actifs ; JK114 présente sur TCP 7808. Aucune connexion ou
  position de l'ES500-603 constatée pendant cette intervention.
- Suite Laravel complète : **139 tests, 656 assertions**, base SQLite isolée.
- Node sous Linux : **9 tests réussis**, y compris HLS avec identités de six et dix
  octets, fragmentation, révocation, refus des inconnus et fuseau propre à l'appareil.
- Pint réussi sur les fichiers PHP modifiés. Migrations appliquées en production.

La réception, le décodage et le HLS ont été vérifiés côté serveur. La lecture de
ces flux dans le navigateur utilisateur n'a pas été testée dans cette intervention.
Le parcours web préparé reste **Flottes → Dashcams → Vidéo en direct**.

## Fichiers et exploitation

Modèle Dashcam, contrôleurs Dashcam/Listener, deux migrations du 22 septembre,
`listener/src/gps.js`, `protocol.js`, `video.js`, tests Laravel/Node associés.
Source locale et copie serveur des fichiers modifiés alignées. Aucun secret ni
IMEI réel n'est introduit dans un seeder ou dans les fixtures des tests.

Sauvegarde avant inscriptions et avant correctifs :
`/var/backups/exadcam-devices-20260922`, accès root uniquement. Les captures de
diagnostic limitées aux ports de notre application sont conservées dans ce dossier
protégé, sans diffusion des trames ou de leurs contenus dans la documentation.

## Prochaine vérification ES500-603

Attendre la confirmation du changement temporaire du serveur principal demandé
dans CarAssist : 62.171.190.15:7808, en gardant la configuration d'origine notée.
Vérifier alors TCP, en-tête réel, identifiant, authentification et variante de
protocole avant de tester les canaux vidéo. Si le firmware utilise une variante
incompatible, demander sa spécification ou un réglage JT808 au fournisseur.
Ne pas contourner l'autorisation par IMEI pour obtenir un statut « en ligne ».

Le dashboard/cartographie et le Centre vidéo général restent de démonstration.
Audio, relecture SD, archivage cloud et alertes ADAS/DMS ne sont pas validés par
ces essais GPS/direct vidéo. Aucun paramètre de conduite, firmware, APN ou mot
de passe matériel n'a été modifié.

## Suivi après changement CarAssist — 22 septembre 2026

L'utilisateur confirme voir le direct JK114 dans son navigateur : la lecture web
est désormais validée par son retour, en complément des essais serveur précédents.
Il confirme aussi avoir remplacé le serveur dans CarAssist pour l'ES500-603.

Le journal EXADCAM montre une tentative le 22 septembre à **09:35:21 UTC**, avec
l'identifiant terminal **053810725721**. Cet alias ne correspondait pas à celui
provisionné initialement (538107257217), d'où le refus « Service returned 404 ».
L'utilisateur confirme ensuite que CarAssist affiche exactement cette valeur dans
le champ « SIM number » (12 chiffres, complétés par des zéros à gauche).
La capture fournie confirme Main IP 62.171.190.15, Main Port 7808 et le fournisseur
119.23.78.106:6608 en backup. Elle indique aussi Terminal ID 0725721 et Terminal
Model FX. Ces paramètres n'ont pas été modifiés par l'agent.
L'alias court JT808 de la fiche ES500-603 a été corrigé après sauvegarde, en conservant
son IMEI métier **352538107257217** et son secret d'authentification existant.

Le rapprochement est explicite, limité à l'appareil fourni par l'utilisateur et
à son changement de destination. L'identifiant observé correspond aux onze derniers
chiffres de l'IMEI privé de son chiffre final, complétés par un zéro à gauche ;
cette observation n'est pas ajoutée comme règle de recherche approximative.
Aucun appareil supplémentaire ni alias global n'est autorisé automatiquement.

Contrôle de l'API interne : 053810725721 résout la fiche 2 / IMEI attendu, HTTP 200 ;
l'ancien alias 538107257217 renvoie HTTP 404. L'identifiant vidéo reste inchangé
jusqu'à observation de la vidéo réelle. Aucun redémarrage des services ni interruption
de la JK114 pour cette correction de registre.

Après correction, aucune session authentifiée ES500-603 n'a encore été observée.
Une réapplication des paramètres CarAssist ou un redémarrage de cette caméra a été
demandé pour relancer sa connexion. La capture de diagnostic après correction montre
la JK114 mais aucune nouvelle trame ES500-603 au contrôle ; ne pas confondre cette
absence de nouvelle tentative avec une nouvelle erreur d'authentification.

Sauvegarde ciblée : `/var/backups/exadcam-devices-20260922/before-es500-alias.sql`,
répertoire privé root. Aucun changement du code applicatif ; aucune suite de tests
rejouée pour cette mise à jour de données. Les tests cités plus haut restent ceux
du lot de compatibilité précédent. Authentification, GPS, fuseau et vidéo ES500-603
restent à valider lors de la reconnexion.

L'utilisateur a précisé qu'il ne peut pas redémarrer la caméra maintenant. Le
contrôle ES500-603 est donc en attente de sa prochaine reconnexion ; le serveur
reste prêt et la JK114 reste en fonctionnement.

## ES500-603 reconnectée et GPS validé — 22 septembre 2026

L'utilisateur a ensuite pu redémarrer la caméra et a demandé la vérification de
sa connexion. Première authentification observée à 09:52:08 UTC ; déconnexion à
09:55:10, puis reconnexion à 09:55:21 UTC. À 09:58, l'API privée /status retourne
HTTP 200 et online=true pour chacun des deux appareils. Aucun redémarrage serveur.

ES500-603 : IMEI 352538107257217, alias court 053810725721. Trames 0x0200 et 0x0002
reçues ; réponses serveur 0x8001 de succès. Décodeur ancien « 2013 » sélectionné,
sans prétendre déterminer l'édition exacte de JT808 à partir de cet en-tête seul.
Le bit de fix GPS est actif ; les positions arrivent environ toutes les dix secondes
sur la fenêtre observée. La JK114 reste en fonctionnement.

Un décalage de sept heures était causé par le fuseau par défaut UTC+8. Exemple
réel : horloge BCD 2026-09-22 10:59:02 reçue à 09:59:03 UTC. La configuration
gps_timezone_minutes de la fiche ES500-603 passe à 60 (UTC+1), sans changement
global ni redémarrage du listener. Après sauvegarde, 42 positions ont été avancées
de sept heures : uniquement la fiche 2, dates de réception du 22 septembre entre
09:52:00 et 10:00:01 UTC, écart réception/GPS compris entre 25 080 et 25 320 secondes.
La correction ne modifie ni les coordonnées ni les données JK114.

À 10:00:01 UTC : 44 positions ES500-603 en base ; dernière date GPS 09:59:52 UTC,
réception 09:59:53 UTC. Les nouveaux points utilisent déjà le bon fuseau.
Sauvegardes root privées dans /var/backups/exadcam-devices-20260922 :
before-es500-timezone-dashcam.sql et before-es500-timezone-positions.sql.
Capture de diagnostic ciblée limitée à 40 secondes, sans publication de coordonnées
ni de secrets dans les résultats.

Aucun changement de code ni migration dans ce lot ; aucune suite de tests rejouée.
Contrôles réels : journaux d'authentification, statuts privés, trames réseau et dates
persistées. La vidéo ES500-603 reste à valider : pas de commande de direct envoyée,
pas de changement de video_terminal_id tant que le flux n'a pas été observé.

## Diagnostic et correctif vidéo ES500-603 — 22 septembre 2026

Le direct transmet réellement du JT1078 court : identifiant 053810725721, canal 1,
payload type 98 (H.264). Après sauvegarde before-es500-video-alias.sql, remplacement
de l'ancien video_terminal_id sur la seule fiche autorisée. Les commandes 0x9101
sont acceptées : le format GPS « 2013 » ne bloque donc pas la demande de vidéo.

Après correction de l'identité, les deux canaux échouent au remuxage FFmpeg :
« first pts and dts value must be set ». Un prélèvement borné à dix secondes,
reconstitué dans l'ordre TCP, donne 14 images H.264 Baseline 960 × 540. L'entête
élémentaire ne fournit pas une cadence exploitable (ffprobe annonce 1200000/1).
Les timestamps JT1078 commencent par plusieurs zéros ; ils ne sont pas réutilisés
comme une horloge fiable. Échantillon initial : échec de remuxage ; avec setts,
14 paquets convertis et décodage FFmpeg complet réussi sans erreur.

Option booléenne normalize_video_timestamps, false par défaut, configurée à true
pour l'ES500-603 uniquement. Filtre setts=ts=N/(frame_rate*TB), avec cadence de fiche
15 images/s. Il produit une chronologie régulière sans réencoder l'image. Ce n'est
pas une mesure de la cadence native ni une synchronisation audio/GPS absolue.
La normalisation est limitée aux flux I/P ; trames déclarées B rejetées explicitement.
Identité et autorisation continuent d'être vérifiées, sans admission automatique.

Migration de production appliquée, sauvegardes before-es500-normalization.sql et
before-es500-video-timing-code.tar.gz, service vidéo relancé à 10:17 UTC. GPS inchangé.
Tests ciblés Laravel : 9 / 48 assertions ; Pint ; suite Node Linux : 10 réussis.
Vérification des timestamps HLS, décodage synthétique et refus des B ajoutés aux tests.
Migration locale en attente car MariaDB local est injoignable ; tests SQLite isolés.

À ce stade l'essai en direct après correctif reste bloqué : ES500-603 hors connexion
depuis 10:14:22 UTC, avant le redémarrage vidéo. L'utilisateur précise que l'appareil
est alimenté mais se met parfois en veille. Réveil/contact véhicule demandé, aucun
paramètre d'alimentation modifié à distance. La JK114 reste GPS active, mais refuse
deux commandes de direct durant le contrôle ; lecture JK114 non revalidée dans ce lot.
Ne pas annoncer la lecture web ES500-603 comme réussie avant la prochaine reconnexion.

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


Contrôle complémentaire : le canal 2 est resté ouvert de 10:34:12 à 10:36:16 UTC
(plus de deux minutes), sans nouvelle déconnexion GPS ni erreur vidéo dans les
journaux ; arrêt par fermeture de lecture, puis nouvelles demandes CH1 et CH2.
La rafale ayant provoqué la coupure précédente n'a pas été reproduite dans cette
fenêtre. Aucun seuil modifié. Les résultats ne constituent pas un test d'endurance.


## Provisionnement par modèle — 22 septembre 2026

La fiche dashcam gère maintenant le modèle, TCP, l’année configurée et l’affectation
véhicule/flotte dans une modale. Pour toute nouvelle ES500-603, recopier exactement
son propre « SIM Number » CarAssist à 12 chiffres : aucune règle de dérivation
depuis l’IMEI. Profil fixe JT808 2013, alias GPS et vidéo identiques, normalisation
vidéo activée. JK114 : choix 2013/2019 et identité vidéo au format correspondant.
Les alias personnalisés existants restent conservés sur une simple édition de nom
ou d’affectation. Le protocole observé n’est pas modifié artificiellement.

Flotte EXAD CARS créée et appareils affectés à Véhicule test 1 (JK114) et Véhicule 2
(ES500). Aucun paramètre matériel changé. Contacts des deux caméras vérifiés après
déploiement, sans nouveau test d’endurance. Voir [dashcam-registry.md](dashcam-registry.md).
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


## 23 septembre 2026 — Cadence et fluidité du direct en production

Mesure des flux JT1078 après réassemblage TCP ordonné, suppression des doublons
et vérification des trous de capture. Sur les deux canaux des appareils de test :
JK114 fiche 1, environ 10,2-10,3 images/s ; ES500-603 fiche 2, environ 11,8-12
images/s. Les valeurs des métadonnées H.264 ne correspondent pas à la cadence
réelle reçue. La configuration était à 15 pour les deux appareils, ce qui
consommait les images plus vite qu'elles arrivaient.

Après sauvegarde, calibration serveur de frame_rate : fiche 1 à 10, fiche 2 à
12 images/s. Ce réglage concerne la chronologie du remuxage ; aucune commande
de changement de cadence, de protocole ou de redémarrage envoyée aux caméras.
Fiches 3 et 4 non modifiées, aucune valeur généralisée automatiquement par modèle.

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
