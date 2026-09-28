# Raccordement des deux dashcams — 22 septembre 2026

> Configuration actuelle de Véhicule 2, confirmée le 28 septembre : seul le
> serveur EXADCAM est conservé, Backup vide. Cette configuration remplace les
> anciennes valeurs de raccordement de cet appareil dans les tableaux historiques
> ci-dessous. Session GPS maintenue environ 64 heures sans fermeture enregistrée.
> Le Toyota Hilux 9863BV01 conserve son Backup ; ne pas lui attribuer ce résultat.


> Identification corrigée : Véhicule 2 et Hilux 9863BV01 sont des SmartVision /
> CarAssist. L'utilisateur confirme le manuel SmartVision T2 comme référence.
> Les anciens diagnostics « ES500 » portent sur ces appareils. Ne plus demander
> de confirmation du manuel. La compatibilité d'un nouveau firmware et l'API
> de réveil restent à vérifier. Voir [identification](smartvision-identification.md).


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

## ES500 — limite de tampon corrigée le 23 septembre 2026

Le défaut `GPS buffer limit` est reproduit avec une trame partielle puis une
lecture TCP de 65 536 octets contenant plusieurs messages valides. L’ancienne
limite portait sur le fragment et le nouveau bloc réunis, avant extraction.
Il entraînait une fermeture de session lors de rafales multimédias 0x0801 :
Véhicule 2 à 14:43:27 et 14:51:37 UTC, autre ES500 à 15:21:59 et 16:18:30 UTC.

Frames808 limite désormais séparément le bloc entrant (65 536) et chaque trame
échappée (2 092). Le fragment conservé reste borné ; checksum, identité,
authentification, révocation, cadence de traitement et contre-pression TCP restent
contrôlés. Aucun support d’archivage des messages multimédias n’est simulé.

Correction déployée à 17:08:18 UTC, reprise GPS uniquement. Sauvegarde :
/var/backups/exadcam-es500-framing-20260923-170816, avec manifest.json,
receipt.json et résultats des 26 tests Linux du listener (aucun ignoré).
Code local, copie applicative et runtime vérifiés par SHA-256.

Limite du diagnostic : Véhicule 2 avait pour dernier contact 15:44:10 UTC et
pour dernière fermeture le redémarrage du service à 15:44:15 UTC. Aucune nouvelle
authentification n’est observée avant installation de ce correctif. Le bug du
tampon est corrigé, mais il ne suffit pas à expliquer l’absence de reconnexion
depuis ce redémarrage. CarAssist et EXADCAM utilisent des connexions distinctes.
L’utilisateur confirme sa relance vers 17:10 UTC ; le contrôle de 17:12 UTC
ne montre toujours aucune nouvelle authentification de Véhicule 2. L’autre
ES500 ne s’est pas encore reconnectée après le redémarrage de ce lot ; la JK114
est revenue à 17:08:27 UTC. Ne pas annoncer le rétablissement matériel avant un
nouveau contact observé.

Contrôle après déploiement : l’autre ES500 (Toyota Hilux) se reconnecte à
17:13:40 UTC, puis remplace sa session à 17:14:42. Au contrôle applicatif réel
de 17:17:15 UTC, elle est en ligne, en parking, contact 17:17:07 et position
17:17:06 UTC. Aucun nouveau `GPS buffer limit` observé depuis installation.
La fenêtre est courte et ne constitue pas un test d’endurance.

Véhicule 2 demeure hors ligne au même contrôle, dernier contact 15:44:10 UTC.
L’utilisateur précise que sa première relance était une commande CarAssist,
puis confirme un redémarrage physique vers 17:17 UTC. Au contrôle de 17:19:41 UTC,
aucune nouvelle authentification de Véhicule 2, aucun nouveau rejet GPS et deux
connexions établies seulement (JK114 et autre ES500). Une capture TCP/UDP 7808
de 45 secondes après le redémarrage physique voit uniquement ces deux appareils.
La stabilité longue durée et le rétablissement de Véhicule 2 ne sont pas validés.
L’étape suivante, si l’absence persiste, est de relire les paramètres serveur
effectivement appliqués sur cette caméra dans CarAssist. Ne pas répéter les
redémarrages du service EXADCAM ni afficher artificiellement l’appareil en ligne.

### Retour matériel confirmé — 23 septembre 2026, après 17:20 UTC

Véhicule 2 s’authentifie de nouveau à 17:20:22.697 UTC, après le redémarrage
physique confirmé par l’utilisateur. Au contrôle applicatif de 17:21:40 UTC :
en ligne, contact 17:21:33 et position 17:21:32 UTC. Au contrôle de 17:24:06 UTC :
contact 17:24:04 et position 17:24:03 UTC. Aucun nouveau rejet ni fermeture de
sa session GPS dans cette fenêtre. Les points restent réels et ne sont pas
remplacés par un statut forcé.

Contrôle navigateur sur https://exadcam.app : Véhicule 2 est visible en ligne
dans le tableau et le sélecteur. Les deux vidéos sont affichées et réellement
lues en 960 × 540, readyState 4, paused=false, sans erreur média. Temps observés
à 17:24 UTC : 56,12 s et 109,81 s. L’inspection visuelle confirme deux images
de caméra. Aucun microphone ni session d’écoute n’a été activé pour cet essai.

Autre ES500 : une nouvelle rafale 0x0801 déclenche la temporisation normale à
17:22:06 UTC sans `GPS buffer limit`. La session a traité 410 messages, dont
des positions après la rafale, avant d’être remplacée par une nouvelle connexion
du même appareil à 17:22:24 UTC. Ce remplacement n’est pas une preuve de stabilité
absolue. Au contrôle de 17:24 UTC, contact 17:23:58 et position 17:23:57 UTC.

Le retour réseau et la vidéo de Véhicule 2 sont confirmés ; le défaut de tampon
est corrigé et testé. La cause du long délai de reconnexion du firmware après
fermeture n’est pas établie. La fenêtre de contrôle reste courte : ne pas promettre
la disparition de toutes les coupures cellulaires ou caméra. Les 26 tests Linux
réussis portent sur le listener ; aucune suite Laravel rejouée dans ce lot.

### Essai prolongé non concluant et second correctif — 23 septembre 2026

Le retour décrit ci-dessus n’a pas tenu : à 17:25:14.897 UTC, Véhicule 2 ferme
sa session côté distant (`peer_closed`), après 293 secondes et 58 messages,
sans temporisation de rafale ni rejet de tampon. Dernier contact 17:25:10 et
position 17:25:09 UTC. L’utilisateur confirme une alimentation continue.
La fermeture distante ne permet pas à elle seule de distinguer firmware,
politique de connexion de l’appareil et événement du réseau. Elle reste inexpliquée.

Le direct canal 1 avait été arrêté à 17:24:47 pour un interphone lancé depuis
un autre client, hors onglet de diagnostic. Le son de cet interphone a continué
après la coupure GPS, jusqu’à un arrêt explicite à 17:25:32, avec 4 163 trames
reçues et 2 221 envoyées. Cela ne prouve pas l’audibilité physique du microphone.
Le canal vidéo 2 a été arrêté par EXADCAM à 17:25:16 pour
`authorization_or_source_lost` : le contrôle périodique exigeait encore la
session GPS, même sur un socket média déjà authentifié et établi.

Second correctif : pour un flux JT1078 déjà établi, la perte de JT808 seule
ne ferme plus la vidéo. Les vérifications du registre, des canaux, de la
configuration, des baux et de la révocation continuent toutes les 5 secondes.
L’absence de données média reste limitée à 30 secondes. Les nouveaux flux
et la première admission du socket média exigent toujours une liaison GPS
authentifiée ; aucune ouverture anonyme et aucun statut GPS forcé.

Fichiers : listener/src/video.js et listener/test/video.test.js. La nouvelle
régression maintient une source vidéo TCP active, rend l’API GPS indisponible,
franchit le contrôle périodique, vérifie HLS et renouvellement toujours actifs,
refuse un nouveau canal, puis désactive le matériel et vérifie l’arrêt effectif.
Suite complète du listener Linux rejouée après ce changement : 26 tests réussis,
0 échec, 0 ignoré, 17,7 secondes. Aucune suite Laravel rejouée. Les essais physiques
de continuité pendant une nouvelle coupure GPS restent à faire.

Déployé à 17:38:27 UTC, redémarrage du service vidéo uniquement. Sauvegarde :
/var/backups/exadcam-es500-media-20260923-173823. SHA-256 du code vidéo local,
applicatif et runtime : 537c95a1d67869bce057048d3e835dde3784eac4c1f3b8b3796c43f7604cbdb3.

Au contrôle applicatif final de 17:39:44 UTC, Véhicule 2 est hors ligne, sans
nouvelle authentification depuis sa fermeture. L’autre ES500 est en ligne avec
contact 17:39:35 et position 17:39:34 UTC. Les cinq services sont actifs. La
capture GPS bornée de 10 minutes est terminée (1 239 paquets, aucune perte de
capture noyau) ; données réservées à root dans la sauvegarde du premier correctif.
Le résumé PCAP simplifié n’assemble pas les segments réordonnés : ses erreurs
de décodage de capture ne constituent pas des rejets du listener en production.
Les deux lecteurs de diagnostic ont été arrêtés explicitement et l’onglet fermé.

État final : deux défauts serveur corrigés et sauvegardés, mais la stabilité
de la connexion de Véhicule 2 n’est pas résolue. Ne pas annoncer une connexion
durable ni demander des redémarrages successifs sans nouvelle hypothèse vérifiable.
Les paramètres appliqués sur la caméra et son firmware restent à examiner pour
expliquer la fermeture de sa liaison GPS et son absence de reconnexion autonome.


## 24 septembre 2026 — JK114 Hilux 0210BW01 et refus de direct

Appareil fiche 3, terminal JK114 déjà identifié. Refus des démarrages canaux 1/2
dans les journaux vidéo/GPS entre 07:42 et 07:44 UTC, puis peer_closed à
07:59:35.589 UTC après 14 610 secondes de session, 2 390 messages, aucun pacing.
Le détail du résultat n’était pas journalisé. La capture bornée de commandes
effectuée plus tard n’a pas permis de retrouver un ACK de cet appareil : au
test interne, /status et /sessions renvoient 409, absence de session GPS.
Ne pas interpréter le PCAP simplifié sans réassemblage TCP comme preuve exhaustive.

Utilisateur : EXADCAM fait partie de deux serveurs simultanés ; ce n’est pas un
Backup de secours. Le fonctionnement sur l’autre plateforme ne prouve pas la
disponibilité de la connexion EXADCAM ni de deux sorties vidéo simultanées.
Aucune limitation matérielle de concurrence n’a encore été démontrée ici.

Correction serveur : command_rejected conserve device_id, commande, séquence et
résultat ; code device_rejected transmis uniquement dans l’API interne, message
FR/EN public sans identifiants. Un refus explicite est HTTP 422 et ne provoque
plus de commande d’arrêt 0x9102 ni de boucle automatique de démarrage. Les erreurs
réseau restent récupérables, avec nettoyage après résultat de démarrage incertain.
Le code 1 seul signifierait un échec, pas une preuve de canal occupé ; ne pas
attribuer cette cause sans trace constructeur ou essai de concurrence.

Déploiement le 24 septembre 2026 à 08:18:41 UTC : 17 fichiers applicatifs/tests,
dont cinq modules également installés dans /opt/exadcam-listener/src. Empreintes
local/app/runtime vérifiées, originaux protégés sous
/var/backups/exadcam-camera-fixes-20260924-081841. Vues recompilées, PHP-FPM
rechargé, trois listeners redémarrés ; cinq services actifs. Aucun cache de
baux vidé, aucune migration ni modification des données métier. ES500 fiche 4
réauthentifiée à 08:19:06 UTC après le redémarrage. La JK114 fiche 3 reste absente.

Prochaine étape : observer sa prochaine authentification et un nouveau refus
avec son code, puis tester le canal demandé. Pas de rétablissement vidéo constaté
et aucun changement imposé à l’autre plateforme ou au réglage de la caméra.


## 24 septembre 2026 — Veille ES500 et conservation du transport

Informations confirmées par l'utilisateur : le microphone fonctionne maintenant
physiquement sur ES500, comme sur JK114. Sur son installation, la JK114 s'éteint
normalement avec le moteur. L'ES500 reste alimentée mais peut entrer en veille
prolongée. Ouvrir son direct dans CarAssist la fait revenir sur EXADCAM sans
redémarrage. C'est une observation utilisateur, pas un essai instrumenté réalisé
par l'agent ; le mécanisme exact de réveil CarAssist n'est pas encore identifié.

Diagnostic des journaux depuis le 23 septembre 17:08:30 UTC jusqu'au contrôle
du 24 septembre vers 08:44 UTC : fiche ES500 4, 14 fermetures idle_timeout,
22 ETIMEDOUT réseau, 54 remplacements de session et un arrêt de service.
Fiche 2 : une fermeture distante peer_closed. Ces causes sont distinctes et
ne prouvent pas toutes un passage en veille. Le serveur imposait 180 secondes
sans données applicatives à toute connexion, y compris une ES500 authentifiée.

Le registre interne fournit désormais le modèle enregistré. Après authentification
uniquement, ES500-603 reçoit socket.setTimeout(0) : plus de fermeture applicative
au bout de trois minutes. JK114 et modèles non identifiés conservent 180 secondes.
Délais avant authentification, TCP keepalive, clôtures distantes, révalidation
du registre toutes les cinq secondes, remplacement de session et limites de
ressources restent inchangés. Aucun faux heartbeat ni nouvelle position généré ;
la présence affichée reste fondée sur le dernier contact réellement reçu.

Tests frais de ce lot : Windows, test TCP réel de 185 secondes réussi, reprise
du heartbeat et commande live acceptée après le silence, révocation vérifiée.
22 tests Laravel ciblés (ListenerAccessTest, FleetMapTest), 170 assertions,
SQLite de test en mémoire. Suite listener Linux isolée : 31 tests réussis,
aucun ignoré, FFmpeg réel inclus. Pas de suite Laravel complète ni nouveau
test physique de veille exécuté par l'agent.

Déployé à 2026-09-24T08:55:12.762848+00:00, sauvegarde /var/backups/exadcam-es500-sleep-20260924-085512.
Quatre fichiers app/tests, GPS également installé dans /opt/exadcam-listener/src.
Seul exadcam-gps est redémarré ; PHP-FPM rechargé. Services vidéo et audio
conservés, aucun cache de bail vidé, aucune migration ni réglage caméra modifié.

Limite : conserver un transport silencieux ne réveille pas une caméra qui a fermé
sa connexion JT808. Le réveil propriétaire reste à intégrer avec une documentation
officielle adaptée au firmware. Le manuel ES500-603 (notes workspace,
analysis/manual-notes.md, page 8) annonce ACC OFF / veille / réveil distant sans
format de commande. Le relevé local Power / 808 Param du 14 septembre est ancien
et ne décrit pas nécessairement la configuration actuelle. Aucun SMS ni commande
vendeur supposée n'est envoyé. Une demande technique non envoyée est préparée dans
le workspace : analysis/es500-sleep-20260924/demande-protocole-reveil.md.
La stabilité physique en veille prolongée n'est pas déclarée résolue.


## 24 septembre 2026 — Requête de réveil CarAssist identifiée côté Android

Demande : identifier la commande qui fait sortir l'ES500 de veille à l'ouverture
du direct CarAssist. L'utilisateur confirme Android. Les sites officiels CarAssist
et DvrAssist lient directement CarControl.apk. Analyse statique de ce fichier,
package com.car.control 3.4.8 (348), SHA-256
28e6ab8de76757e528f6d1af3a41b0e76671f343782b8d027ccf3913c94edcc7.

La demande est relay / cmd=preview / wakeup=1 / action=1, avec peer (SN CarAssist),
peerurl et camid. Transport applicatif : WebSocket, défaut ws://ws.carassist.cn:8000,
après userlogin et récupération des équipements associés. Pendant le direct,
livekeep avec wakeup=1 est envoyé toutes les dix secondes. Le SN est distinct du
champ IMEI dans le modèle de données de l'application. Références exactes et
format : docs/carassist-wake.md ; preuves locales dans le workspace
analysis/carassist-wake-20260924/ (APK, pages, décompilation et empreintes).

Cette preuve identifie le message application vers cloud. Le transport réel
cloud vers module ES500 en veille reste inconnu : ne pas déclarer SMS, MQTT ou
JT808 comme cause établie. La version installée par l'utilisateur n'est pas
encore connue ; le manifeste de téléchargement officiel est lui-même ancien.
Il faut une session d'intégration autorisée et les SN associés, ou un protocole
fabricant, pour valider le réveil réel sans ouvrir un média CarAssist concurrent.

Contrôles : chaîne d'appel du lecteur vers le sérialiseur et minuterie livekeep
lues ; APK non exécuté, aucun compte consulté, aucune commande ni message envoyé.
JADX signale neuf erreurs dans des bibliothèques tierces, hors méthodes citées.
Pas de modification du code d'exploitation, pas de nouveau déploiement ni de
tests applicatifs nécessaires pour ce lot de recherche et documentation.

## 24 septembre 2026 — Essai accompagné CarAssist, retour EXADCAM non observé

Demande utilisateur : tester le réveil ES500. Le point d'entrée officiel
ws://ws.carassist.cn:8000 accepte HTTP 101 à 09:52:29 UTC. Aucun message
applicatif, login ou ordre caméra envoyé par ce contrôle de transport.

L'utilisateur indique Android relié à Windows. Le contrôle Windows échoue
deux fois avant toute interaction avec le téléphone (native pipe indisponible,
erreur OS 2). Aucun compte, token ou écran CarAssist n'a été inspecté.
L'utilisateur ouvre donc lui-même la vidéo de Véhicule 2, puis confirme
« La vidéo est ouverte » avant le relevé de 09:58:51 UTC.

Avant ouverture : à 09:57:27 et 09:58:08 UTC, fiche 2 sans session GPS (409),
dernier contact 08:55:07 UTC ; fiche 4 active, contacts récents.
Après confirmation : toujours 409 à 09:58:51 UTC, puis 20 relevés bornés
entre 2026-09-24T09:59:04.437181+00:00 et 2026-09-24T10:03:52.671077+00:00, tous sans session pour
Véhicule 2. Dernier contact et position inchangés : 2026-09-24T08:55:07.000000Z.
La fiche 4 continue de transmettre ; dernier contact final 2026-09-24T10:03:51.000000Z.

Journal GPS depuis 09:58:00 UTC consulté après le dernier relevé : seule entrée,
authentification de la fiche JK114 3 à 09:58:16.461 UTC. Aucun rejet ni
authentification de Véhicule 2 enregistré dans cette fenêtre. Ce n'est pas une
capture réseau. GPS, vidéo, audio, Apache et PHP-FPM sont tous actifs au contrôle.

Résultat : cet essai ne reproduit pas le retour EXADCAM précédemment signalé
par l'utilisateur à l'ouverture de CarAssist. Il ne prouve pas le mode de veille
exact du matériel, ni une panne du modem, ni un refus du serveur EXADCAM.
La vidéo CarAssist est confirmée par l'utilisateur ; elle n'a pas été visionnée
par l'agent. Aucune capture du message application vers cloud ou cloud vers
caméra, aucune intégration de réveil autonome validée.

Preuves workspace : analysis/carassist-wake-20260924/transport-check.json,
server-observations.jsonl, observations-summary.json, test-observations.md.
Lecture seule en production : aucun service redémarré, aucune configuration
caméra ou métier modifiée, aucun microphone activé. Pas de suite applicative
rejouée pour ce lot de diagnostic. La stabilité/réveil ES500 reste non résolue.

## 24 septembre 2026 — Tolérance réseau ES500 corrigée, réveil encore bloqué

Demande : résoudre durablement la perte de connexion de Véhicule 2, immobilisé.
Diagnostic réel : sa dernière fermeture est service_shutdown à 08:55:12.735 UTC
lors du déploiement précédent ; absence de réauthentification aux contrôles
suivants. Cela ne prouve pas à elle seule une veille prolongée ni une panne modem.

Défaut distinct reproduit : socket.setKeepAlive(true,30000), avec Node 24.21.0,
déclenche dix sondes espacées d'une seconde après 30 secondes. Dans un namespace
réseau Linux isolé, un filtre bloque uniquement les paquets de la caméra simulée
vers son port de test. Le listener courant perd sa session après 41 secondes
(ETIMEDOUT), malgré socket.setTimeout(0). Aucun réseau de production modifié.

Correction : uniquement après authentification d'une ES500-603, le premier
keepalive est différé à 300 secondes. Les autres modèles et les connexions
non authentifiées conservent leurs délais. Pas de faux heartbeat/position,
pas de statut en ligne forcé, contrôles de registre et révocation conservés.
Cette tolérance aux pertes courtes ne réveille pas une caméra déconnectée.

Contrôles frais : essai réseau avant/après réel, 65 secondes de perte. Avant,
session perdue ; après, reprise du heartbeat sur le même socket puis révocation
effective. Deux événements seulement : authentification et vrai heartbeat.
Suite listener Linux complète isolée : 31 réussites, 0 échec, 0 ignoré,
190157 ms, FFmpeg réel compris. Pas de suite Laravel rejouée, aucun PHP modifié.
Test de régression autonome : listener/test/gps-network-outage.mjs, à lancer
sur Linux via sudo unshare --net -- node test/gps-network-outage.mjs. Le garde
refuse le namespace réseau hôte. Reproduction et résultats complets conservés
dans analysis/es500-reconnect-20260924/ du workspace.

Déployé à 2026-09-24T10:25:49.211864+00:00. Sauvegarde :
/var/backups/exadcam-es500-network-20260924-102549. Deux fichiers app (GPS et test), GPS également installé
dans /opt/exadcam-listener/src ; SHA-256 local/app/runtime vérifiés.
Seul exadcam-gps redémarré. Aucun cache vidé ni migration ; audio/vidéo inchangés.
Les cinq services sont actifs. JK114 fiche 3 reconnectée à 10:25:50.098 UTC,
ES500 fiche 4 à 10:25:54.045 UTC.

Capture CarAssist de l'utilisateur : réveil sur collision actif ; aucun délai
de veille affiché. L'APK analysé définit autosleep_layout avec visibility=gone :
le réglage generic.autosleeptime (15/30/60/0) existe dans le code mais est masqué
dans ce layout. Ce n'est pas la preuve du réglage effectif du firmware installé.
Ne pas demander de trouver ce menu masqué ni changer le réveil sur collision.

L'utilisateur réenregistre les paramètres JT808 avant 10:28:13 UTC. À 10:30:32 UTC,
fiche 2 toujours sans session (409), dernier contact 08:55:07 UTC ; fiche 4 en
ligne, contact et position 10:30:27 UTC. Capture bornée de 90 secondes après
réenregistrement : aucun SYN/FIN/RST sur TCP 7808, aucune perte de capture.
Ce filtre porte sur les tentatives/fermetures, pas sur toute la télémétrie.
L'appareil ne reprend donc pas sa liaison durant cette fenêtre ; aucun rejet
de connexion le concernant n'est observé.

Limite et suite : défaut serveur corrigé, mais Véhicule 2 non rétabli. Il faut
relire l'écran JT808 effectif et les destinations Main/IP2/Backup, ou disposer
d'un accès de maintenance matériel/cloud autorisé. Contrôle Windows indisponible
(native pipe) ; adresse locale 192.168.1.1 actuellement Starlink, pas la caméra.
Aucune configuration caméra modifiée par l'agent, aucune session cloud extraite,
aucun réveil cloud autonome implémenté. Ne pas annoncer le problème résolu.

## 24 septembre 2026 — Retour de Véhicule 2 après Submit confirmé

L'utilisateur indique que Submit sur le formulaire JT808 a fait revenir
Véhicule 2 en ligne. Capture fournie : Main IP 62.171.190.15, Main Port 7808 ;
Backup IP 119.23.78.106, Backup Port 6608 ; IP2 et Backup IP2 vides, ports 0.
SIM Number 053810725721, Terminal ID 0725721, modèle FX. L'adresse EXADCAM et
l'identité courte correspondent au registre. Un secours est configuré ; aucun
second groupe de serveur indépendant n'est renseigné dans cette capture.

Preuve serveur : device_authenticated, fiche 2, protocole 2013, à
2026-09-24T10:48:21.086Z (11:48:21 Kinshasa). À 10:57:36 UTC, /status renvoie
200, dernier contact et dernière position 10:57:28 UTC. À 10:58:21 UTC, toujours
200, contact et position 10:58:16 UTC. Aucune déconnexion de la fiche 2 enregistrée
dans cette fenêtre de dix minutes. Une rafale 0x0801 a été temporisée normalement
à 10:50:46 sans fermer sa session. L'autre ES500 est également authentifiée au
contrôle ; ces constats ne valident pas toute commande vidéo en cours.

La remise en ligne après réapplication JT808 est corroborée par le retour
utilisateur et l'authentification réelle. Le passage effectif sur le serveur
backup, sa politique de retour au principal et le mode de veille précis ne sont
pas observés. Ne pas présenter le basculement de secours comme la cause établie.
L'essai ne valide pas encore un réveil autonome EXADCAM après une nouvelle veille.

Aucun service redémarré, aucun réglage caméra modifié, aucun nouveau flux vidéo
ou microphone lancé dans ce contrôle. Aucun nouveau test automatisé nécessaire
pour cette vérification en lecture seule. Les 31 tests cités au lot précédent
restent ceux exécutés pour le correctif réseau de 10:25 UTC. Retour réseau/GPS
confirmé ; stabilité prolongée et reconnexion automatique encore à observer.

## 24 septembre 2026 — Essais de reconnexion automatique ES500, non résolue

Demande : automatiser le retour après veille/coupure, au-delà du Submit manuel.
Lecture réelle des deux ES500 par 0x8106 et 0x8107 sans redémarrer le listener.
Firmware commun : PL_TW1-V1.1_4G_NCS_EN_2.2.31_2.3.7, hardware T1, modèle FX.
Heartbeat 20 s, timeout TCP 10 s, retransmissions 0, GPS normal/veille 10 s.
Le champ standard 0x0017 est vide malgré le Backup visible dans CarAssist :
ne pas en déduire le fonctionnement du secours propriétaire.

Véhicule 2 seulement : essai 0x8103, timeout 30 s/retransmissions 3, ACK et
relecture confirmés à 11:14:08 UTC. Trois essais ciblés, sans redémarrage de
service : fermeture normale 11:16:01 ; même fermeture après Backup temporaire
EXADCAM appliqué par l'utilisateur à 11:20:30 ; RST réel 11:29:17 après retour
au Backup original. Aucun retour automatique dans les fenêtres observées.
Submit utilisateur rétablit l'appareil entre les essais. Pas de rejet identifié
de Véhicule 2 pendant les absences ; ne pas attribuer les scans inconnus à lui.

L'utilisateur ne peut pas réappuyer sur Submit après le dernier essai. À
11:36:14 UTC, fiche 2 sans session ; contact 11:29:07, GPS 11:29:06. Ne plus
provoquer de coupure. Backup original restauré par l'utilisateur ; timeout
30 s/retransmissions 3 encore appliqués. Retour à 10 s/0 préparé mais non exécuté,
à faire au prochain retour après relecture et avec garde contre modifications.

Défaut distinct observé sur fiche 4 : réponse matérielle 0x1003 à 11:35:49.919
mais last_seen_at resté 11:31:12 à 11:36:14. Les réponses authentifiées aux
commandes ne rafraîchissent pas la présence. Aucun correctif livré pour ce point
dans ce lot ; cela ne rétablit pas un socket absent comme celui de fiche 2.

Quatre tests locaux ciblés de la sonde réussis ; aucune suite complète rejouée.
Aucun code applicatif de production modifié ni service redémarré. Captures
bornées terminées, inspecteur temporaire loopback fermé. Aucun média/micro ouvert,
aucun mot de passe/token cloud extrait. Réveil automatique non intégré.
Accès API/SDK ou commande de reconnexion/réveil du firmware encore nécessaires ;
question de documentation précisée à l'utilisateur. Brouillon fournisseur non envoyé.

Preuves, paramètres, chronologie et retour arrière : docs/es500-auto-return.md,
docs/es500-integration-request.md et workspace analysis/es500-auto-return-20260924/.
Le code de diagnostic et les reçus bruts sont conservés côté serveur dans
/home/exad-cam/es500-auto-return-20260924/. Aucun observateur laissé en tâche de fond.

## 24 septembre 2026 — Retour autonome de Véhicule 2 confirmé après environ 30 minutes

L'utilisateur indique que Véhicule 2 est revenu seul, sans aucune action de sa
part. Vérification serveur : fermeture issue de l'essai à 11:29:17.202 UTC,
puis authentification JT808 réelle à 11:59:14.198 UTC (12:59:14 Kinshasa).
Écart : 29 min 56,996 s. Aucune autre fermeture de la fiche 2 dans le journal
entre ce retour et le contrôle de 12:05:37 UTC.

La session est présente et les positions GPS sont récentes à trois contrôles :
12:03:21 (contact/GPS 12:03:17), 12:05:06 (12:04:57) et 12:05:37 (12:05:37).
Ce sont des messages réellement reçus, pas seulement un statut conservé en cache.
Le retour précède notre ouverture SSH et toute lecture de paramètres de ce tour.

Cela révise le diagnostic précédent : absence de retour pendant les fenêtres
courtes et jusqu'à 11:54, mais retour autonome constaté ensuite. Ne plus écrire
que Véhicule 2 reste actuellement absent ou qu'il ne revient jamais seul.
Ce résultat n'établit pas encore un retour rapide, un délai reproductible de
30 minutes, la disparition des pertes réseau, ni un réveil EXADCAM à la demande.
Une temporisation du firmware est une hypothèse, pas une cause démontrée ; un
redémarrage interne ou un autre mécanisme ne sont pas exclus par ces seuls journaux.

Relecture gardée pour terminer le rollback d'essai : commande 0x8106 à
12:03:55.283, réponse 12:03:55.495 UTC. Les paramètres sont DÉJÀ revenus à leurs
valeurs initiales : 0x0002 = 10 secondes et 0x0003 = 0. Les valeurs d'essai
30 secondes / 3 ne sont donc plus appliquées. La sonde restore-retries constate
ce changement et n'envoie aucune écriture 0x8103. Aucun réglage modifié pendant
ce tour, aucune restauration supplémentaire en attente. Ne pas attribuer le
retour à la sonde ni prétendre qu'elle a rétabli ces paramètres.

Autres valeurs relues : heartbeat 20 s, GPS normal/veille 10 s, serveur principal
EXADCAM :7808, champ backup standard vide comme précédemment. La cause du retour
aux valeurs initiales n'est pas mesurée : ne pas conclure à un reset matériel
ou à un effet des réglages d'essai. Pas de nouvelle coupure, redémarrage de
service, test vidéo, écoute ou microphone. PID GPS inchangé (156157).
Inspecteur temporaire fermé et port 9229 sans écoute ; observateurs retirés.
Pas de surveillance ni de restauration en tâche de fond.

Aucun nouveau code applicatif, donc aucune suite automatisée rejouée pour cette
vérification opérationnelle. Les 62 tests vidéo du lot précédent et les 4 tests
de la sonde restent les résultats de leurs exécutions antérieures.
Preuves : workspace analysis/es500-autonomous-return-20260924/evidence.json,
copie serveur /home/exad-cam/es500-auto-return-20260924/autonomous-return-evidence.json.
Le défaut distinct de présence sur réponse de commande reste non corrigé.
Réveil cloud CarAssist non intégré ; ce retour autonome ne valide pas ce réveil.


## 25 septembre 2026 — Demande de passage ES500 en JT808 2019, non appliquée

L'utilisateur indique que les appareils sont en ligne et demande de remplacer
JT808 2013 par 2019, soupçonnant la version de contribuer à leur instabilité.
Contrôle du code : decode808 choisit le format par le bit 0x4000 reçu ; les
réponses utilisent cette version. ListenerController résout les identifiants
2013 et 2019 sans imposer la valeur protocol_version de la fiche. Le serveur
accepte donc déjà les deux formats. Le profil de provisionnement ES500 reste
fixé à 2013 ; changer seulement ce champ ne commande pas le firmware et une
édition de profil par le contrôleur peut en outre révoquer la session existante.

Inspection ciblée du Jt808ConfigActivity de la version CarAssist disponible dans
le workspace : le formulaire transmet adresses, ports, identités et paramètres
de plaque ; aucune sélection de version n'est identifiée. Le champ version reçu
est affiché en lecture. Cela ne prouve pas une impossibilité matérielle, mais
ne fournit pas de commande vérifiée pour passer ces caméras en 2019.
Recherche documentaire sans procédure constructeur applicable confirmée.

Aucun changement de protocole, d'identifiant, de firmware ou de paramètres
caméra exécuté. Aucune nouvelle coupure ou relance de service dans cette
vérification. Pas de nouveaux tests : les 22 tests GPS/protocole du correctif
précédent restent les résultats de ce lot. Une procédure ou un firmware officiel
compatible a été demandé à l'utilisateur. Brouillon fournisseur non envoyé :
workspace analysis/es500-stability-20260925/demande-jt808-2019.md.
Le correctif 503 déjà déployé reste actif ; le passage matériel en 2019 et le
réveil/reconnexion depuis une liaison fermée ne sont pas déclarés résolus.

## 2026-09-28 — Stabilité du week-end avec EXADCAM seul sur Véhicule 2

- Retour utilisateur : Véhicule 2 ne se déconnecte plus depuis qu’il a conservé uniquement le serveur EXADCAM, avec le Backup retiré. Il confirme que le Backup reste configuré sur le Toyota Hilux 9863BV01. La suppression du Backup avait déjà été signalée le 25 septembre, avec accès distant CarAssist toujours fonctionnel.
- Vérification du 28 septembre en lecture seule : Véhicule 2 conserve la même session GPS authentifiée depuis vendredi 25 septembre à 16 h 31 min 52 s, heure de Kinshasa, soit environ 64 heures. Aucune fermeture de cette session n’est enregistrée sur la période ; le contact reçu était âgé d’environ 5 secondes lors du contrôle. Ce constat repose sur la session réelle du listener et les journaux, pas uniquement sur le statut affiché dans l’interface.
- Comparaison sur la même période : le Hilux est également en ligne au contrôle, mais les journaux enregistrent 165 authentifications et 164 fermetures de sessions, dont 105 remplacements par une nouvelle connexion, 54 expirations réseau, 4 fermetures par le pair et un retour de service 422. Ces nombres ne représentent pas 164 pannes utilisateur : un remplacement peut intervenir alors qu’une nouvelle connexion est déjà authentifiée. Aucun contenu détaillé des journaux ni position géographique n’est copié ici.
- Configuration de référence validée en exploitation pour Véhicule 2 sur ce week-end : EXADCAM seul, Backup vide. Ne pas restaurer automatiquement l’ancien Backup. Pour le Hilux, un essai de cette même configuration est pertinent ; il n’a pas été effectué pendant ce contrôle et ne doit pas être présenté comme appliqué.
- Interprétation : les observations renforcent la piste d’un effet de la configuration secondaire, sans isoler à elles seules sa causalité. Les correctifs du listener ont aussi été activés le vendredi et l’heure exacte du retrait du Backup n’est pas connue. La stabilité observée ne valide pas encore un retour après une nouvelle panne d’alimentation ou de réseau, ni la stabilité future de tous les appareils.
- Aucun changement de code, déploiement, redémarrage, commande caméra, modification de paramètres ou nouvel essai de rupture. Aucun test automatisé relancé ; documentation locale mise à jour. Les anciens diagnostics restent conservés comme historique.
