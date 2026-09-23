# Serveur GPS et vidéo EXADCAM

Socle déployé le 21 septembre 2026. Les modèles JK114 et ES500-603 ont
transmis des positions et des images réelles ; leur stabilité de connexion
est suivie dans [device-commissioning.md](device-commissioning.md).

## Architecture installée

```text
Dashcam -- JT808 TCP 7808 --> Node GPS <-- API locale --> Laravel / MariaDB
   ^                           |
   +---- commande 0x9101 ------+  destination vidéo + canal + sous-flux
   |
   +------ JT1078 TCP 1078 --> Node vidéo --> FFmpeg H.264 vers HLS
                                                |
Navigateur <-- HTTPS /live-media/ <-- Apache <----+
```

Le port principal du configurateur est **7808** sur **62.171.190.15**.
L'écouteur prend en charge les en-têtes **JT808 2013 et 2019**. Le matériel
précédemment essayé sur GPS51 avait finalement été configuré en 2019 ; vérifier
la version du nouvel appareil. Ne pas confondre année du protocole et port.

Le port **1078/TCP** est une destination vidéo distincte, transmise par la commande
JT808 0x9101 lorsque l'utilisateur demande un direct. Il n'est donc pas nécessaire
que le configurateur propose deux champs de port principal. Pas d'écoute UDP
dans cette première implémentation. L'acceptation de la commande et le port
effectivement utilisé devront être confirmés avec le firmware.

## Inscription et autorisation

1. Le superadmin inscrit le nouvel IMEI dans **Flottes → Dashcams**.
2. Laravel crée les alias exacts : 12 derniers chiffres pour JT808 2013 et JT1078,
   IMEI complété à gauche sur 20 chiffres pour l'en-tête JT808 2019.
3. Lors de 0x0100, Node consulte Laravel. Un terminal absent ou désactivé est refusé.
   Pour un appareil autorisé, Laravel fournit le code d'authentification provisionné.
4. Node accepte 0x0102 uniquement avec le code attendu ; en 2019, le champ IMEI
   du message d'authentification doit aussi correspondre à celui du registre.
5. Seule une connexion GPS authentifiée peut déclencher une session vidéo autorisée.

Les alias sont une convention initiale à vérifier sur le matériel, jamais une
recherche approximative dans les IMEI. Le PATCH administrateur permet de les
corriger après analyse des trames ; cette édition avancée n'a pas encore de formulaire.
Un appareil qui conserve un ancien code d'un autre serveur peut devoir refaire son
enregistrement protocolaire. Le comportement réel sera observé avant toute action.

L'inscription par IMEI ne prouve pas cryptographiquement l'identité physique d'un
appareil. Les protocoles natifs utilisés ici ne sont pas chiffrés par TLS. Le HTTPS
protège la consultation web ; un APN privé/VPN constitue une évolution distincte.

## Services et configuration

| Service | Écoute publique | Coordination privée |
| --- | --- | --- |
| `exadcam-gps` | 0.0.0.0:7808/TCP | 127.0.0.1:3001 |
| `exadcam-video` | 0.0.0.0:1078/TCP | 127.0.0.1:3002 |
| Laravel interne | Aucune | 127.0.0.1:8081 |

Code exécuté : `/opt/exadcam-listener/src`. Source applicative :
`/var/www/exadcam/listener`. Une modification de la source Laravel ne met donc
pas à jour automatiquement le code Node exécuté : recopier et redémarrer les services.

Configuration : `/etc/exadcam/listener.env`, lisible uniquement par root et le
groupe du service. Jeton commun au `.env` Laravel de production ; aucune valeur
secrète n'est présente dans le dépôt. `deployment/listener.env.example` contient
les noms des variables. Ne pas régénérer APP_KEY lors des prochains déploiements.

Les unités systemd installées proviennent de `deployment/systemd/`. Elles utilisent
un compte non interactif, un système de fichiers protégé, des plafonds mémoire et
un redémarrage automatique. Aucun processus Node lancé manuellement en arrière-plan.

```sh
sudo systemctl status exadcam-gps exadcam-video --no-pager
sudo journalctl -u exadcam-gps -u exadcam-video --since '10 minutes ago' --no-pager
sudo ss -lntp
```

Apache sert l'application et relaie seulement les médias vers Node. Les API privées
ne sont pas exposées via le vhost public. Les liens vidéo temporaires ne sont pas
écrits dans le journal d'accès HTTPS. Les journaux techniques restent sur le VPS.

## GPS et messages

- Contrôle de la délimitation 0x7e, de l'échappement, du XOR, des longueurs et BCD.
- En-têtes 2013/2019, enregistrement 0x0100/0x8100, authentification 0x0102.
- Battements 0x0002, positions 0x0200, lots 0x0704 limités à 32 positions.
- Réponses générales 0x8001 et accusés des commandes 0x0001.
- Commandes direct 0x9101 et arrêt 0x9102 ; sous-flux vidéo seul demandé.
- Messages inconnus et messages JT808 multiparties renvoient « non pris en charge ».

`GPS_TIMEZONE_MINUTES=480` interprète les heures BCD en UTC+8. Cette convention
doit être vérifiée avec l'appareil ; le réglage PHP/Africa-Kinshasa ne change pas
automatiquement le fuseau de la trame. Positions normalisées avant stockage.
Les positions sans fix valide ne remplacent pas la dernière position valide.

La réévaluation des droits est périodique (5 s hors durée des appels internes).
En cas d'indisponibilité du registre, les connexions sont refusées/fermées. Les
délais d'appel internes peuvent retarder une révocation ; ne pas promettre une
révocation instantanée ni un SLA de disponibilité.

## Vidéo livrée et limites

Le serveur reçoit JT1078 avec payload H.264 (98), reconstitue les images et les
transmet à FFmpeg. FFmpeg remuxe sans transcodage en segments MPEG-TS/HLS,
lisibles par hls.js livré localement ou par un navigateur compatible HLS natif.

Un flux par appareil/canal est partagé entre les lecteurs. Chaque lecteur dispose
d'un lien temporaire aléatoire et doit renouveler son droit de lecture via Laravel.
Ces liens sont des accès porteurs : ne pas les diffuser. Seul le superadmin peut
ouvrir ou renouveler une lecture dans cette première version. La possession d'une
autre session Laravel ne permet pas d'en renouveler le lien.

Le tampon est conservé dans `/var/lib/exadcam-media`, puis supprimé à l'arrêt.
Cible de segmentation 2 s et cinq segments de playlist, dépendant des images clés
du matériel ; ce n'est ni un archivage cloud ni une garantie de latence de deux secondes.
Une cadence de 15 images/s est préremplie et devra correspondre au sous-flux matériel.

Limite de départ : **8 canaux vidéo simultanés**, 512 connexions GPS admises,
64 connexions TCP vidéo. Ces plafonds protègent le serveur ; ils ne constituent
pas un résultat de benchmark ou une validation pour 300 dashcams.

Pas encore pris en charge : audio, H.265, UDP, lecture de carte SD à distance,
enregistrement cloud durable, pièces jointes ADAS/DMS, alertes constructeur,
transcodage ou distribution multi-serveurs. Les écrans généraux de carte,
dashboard et Centre vidéo restent de démonstration. Le parcours initial de
lecture réelle est dans **Flottes → Dashcams**, après raccordement matériel.

## Vérifications effectuées

- Suite locale Laravel : **137 tests et 643 assertions** ; base de test isolée.
- Build Vite et publication locale hls.js réussis.
- Linux : **7 tests Node réussis**, dont serveur TCP réel, rejet des inconnus,
  authentification 2013/2019, parser de fragments, commande vidéo, génération HLS
  et suppression de l'accès lors d'une révocation.
- Tests synthétiques sur ports éphémères locaux et répertoire temporaire, avec
  faux registre ; aucune insertion d'IMEI dans la base de production.
- HTTPS, services automatiques, ports publics accessibles, API privées protégées,
  registre vide et écran web vérifiés sur le VPS déployé.

Le test FFmpeg est ignoré sur Windows si `/usr/bin/ffmpeg` n'existe pas ; il a bien
été exécuté sous Linux. Reproduction dans un répertoire de validation isolé :

```sh
node --test listener/test/*.test.js
```

## Essais matériels à effectuer au prochain créneau

1. Recevoir le nouvel IMEI et l'inscrire explicitement dans EXADCAM.
2. Vérifier APN/data, puis configurer le serveur principal 62.171.190.15, TCP 7808,
   et la version JT808 adaptée au firmware. Aucun changement matériel fait aujourd'hui.
3. Observer enregistrement, authentification, battements et dernier contact réel.
4. Vérifier le fuseau et les coordonnées GPS ; distinguer absence de fix et absence de data.
5. Demander le canal 1 en H.264 sous-flux depuis Dashcams, contrôler l'accusé 0x9101
   et l'arrivée des paquets TCP 1078, puis la lecture et l'arrêt. Répéter pour le canal 2.
6. Vérifier qu'une désactivation coupe la connexion et la vidéo ; noter les écarts
   du firmware avant d'étendre le protocole. Ne jamais ouvrir l'admission à tous les IMEI.

Références de travail : documentation fournisseur et implémentation protocolaire,
[manuel JT808/JT1078 Meitrack](https://www.meitrack.com/wp-content/uploads/h2025/MEITRACK_JTT808_JTT1078_protocol_V1.0--20250401.pdf),
[sérialiseur JT1078](https://github.com/SmallChi/JT1078/blob/master/src/JT1078.Protocol/JT1078Serializer.cs).
Elles aident à préparer le socle mais ne remplacent pas la validation ESTON.

## Mise à jour de compatibilité — 22 septembre 2026

Les sections de tests synthétiques ci-dessus décrivent le socle initial. Après
essai réel JK114, le registre autorise un `video_terminal_id` de 12 ou 20 chiffres
et le parser lit les en-têtes JT1078 à six ou dix octets BCD. La sélection dépend
des flux explicitement autorisés et refuse une identité ambiguë.

`gps_timezone_minutes` par appareil prend priorité sur `GPS_TIMEZONE_MINUTES` ;
la valeur nulle conserve le repli global. JK114 = 60 minutes (UTC+1), confirmé
en comparant l'heure reçue et l'heure de réception. Ne pas appliquer ce constat
aux autres modèles sans observation. Les tests actuels comptent 139 tests Laravel
et neuf tests Node, détaillés dans [device-commissioning.md](device-commissioning.md).

## Normalisation des horodatages vidéo — 22 septembre 2026

normalize_video_timestamps est une option de compatibilité par appareil, false par
défaut. Pour un flux I/P sans horodatages H.264 utilisables, elle applique
setts=ts=N/(frame_rate*TB) avant le remuxage HLS, sans transcodage. Les images B sont
explicitement refusées dans ce mode pour éviter une mauvaise chronologie d'affichage.
Un changement de mode ou de cadence invalide également un flux actif au prochain
contrôle du registre. ES500-603 : mode activé, JK114 : mode natif conservé.
Référence : [filtre setts officiel FFmpeg](https://ffmpeg.org/ffmpeg-bitstream-filters.html#setts).
Tests de ce lot : dix tests Node Linux et neuf tests Laravel ciblés, 48 assertions.
État des essais matériels et limites : voir device-commissioning.md.

## Provisionnement du fuseau — correction du 22 septembre 2026

Le registre Laravel persiste désormais un décalage explicite lors de chaque
création : listener.default_gps_timezone_minutes / DASHCAM_GPS_TIMEZONE_MINUTES,
60 par défaut pour cette installation. Les deux nouvelles caméras, comme les
deux premières, transmettent une horloge UTC+1. Sans cette valeur, le repli Node
UTC+8 vieillissait les points de sept heures. Le défaut n’est pas généralisable
à tous les firmwares ou lieux ; le réglage doit correspondre à l’horloge réelle.

Les fiches existantes ne sont pas réécrites par les éditions de métadonnées.
Le listener garde son repli global pour les fiches nulles et recharge chaque
fiche au plus toutes les cinq secondes ; sa logique, ses ports et ses protocoles
restent inchangés. Aucun redémarrage GPS/vidéo pour ce lot.


## Optimisation du tampon vidéo — déployée le 23 septembre 2026

HLS : hls_time=2 conservé, hls_list_size=20 et hls_delete_threshold=5. Segments
supprimés progressivement ; plafond existant de 128 Mio par flux inchangé.
Lecteur commun : 15 secondes continues préchargées, liveSyncDuration=18,
liveMaxLatencyDuration=40, initialLiveManifestSize=1, maxBufferLength=30,
maxMaxBufferLength=60, backBufferLength=10, lowLatencyMode=false. Aucun mélange
avec liveSyncDurationCount. Les canaux conservent leurs baux indépendants.
Le remplissage est masqué par un aperçu neutre au démarrage et la dernière image
en cas d'interruption. Attente bornée à 90 s ; erreurs et contrôles d'accès maintenus.
La lecture impose donc un décalage volontaire avec le temps réel et une attente
initiale pour constituer la réserve ; aucun flux fictif ne remplace la caméra.

Le remuxage H.264 utilise encore frame_rate pour construire la chronologie.
Une valeur supérieure à la cadence réelle vide progressivement le tampon même
si le réseau fonctionne. Mesures JT1078 sur les deux canaux : JK114 fiche 1
environ 10,2-10,3 images/s ; ES500 fiche 2 environ 11,8-12 images/s. Les valeurs
de remuxage ont été calibrées respectivement à 10 et 12, au lieu de 15. Il ne
s'agit pas d'une commande de modification de cadence envoyée aux appareils.
Les horodatages JT1078 ont servi à mesurer, mais ne pilotent pas encore le muxer.
Une caméra à cadence très variable nécessite donc une validation spécifique.
Les autres appareils et les valeurs par défaut n'ont pas été réécrits.

Références primaires consultées :
- [HLS.js, configuration du délai et du tampon](https://github.com/video-dev/hls.js/blob/master/docs/API.md#livesyncduration).
- [FFmpeg, options du multiplexeur HLS](https://ffmpeg.org/ffmpeg-formats.html#hls-2).

Validation locale avant déploiement : 40 tests Laravel / 317 assertions et 11
tests Node. Validation sur Linux isolé : 10 tests listener réussis, aucun ignoré,
dont FFmpeg réel (fenêtre de 20 segments, décodage et chronologie, refus B,
révocation et identités 6/10 octets). Les essais réels sont consignés dans
device-commissioning.md. Safari matériel n'a pas été validé.


### Correctif de démarrage live-buffer-2 — 23 septembre 2026

Le seuil de huit segments retardait le téléchargement initial et masquait
toutes les images sur les flux lents. Le lecteur charge maintenant dès le
premier segment et montre le premier frame décodé, sans lancer la lecture
avant les 15 secondes de réserve. Si la plage média commence après zéro,
la position initiale rejoint son début. Deux tests de régression ajoutés ;
13 tests Node lecteur/baux passent. Aucun changement du listener dans ce lot.
Déconnexions d’équipements et cadence des autres appareils : se référer à
device-commissioning.md, sans généraliser le calibrage des premières caméras.


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

Le listener et les routes Laravel restent inchangés. Le client ferme son ancien
bail avant de demander le suivant ; il ne réutilise pas une URL de session expirée.

Validation réellement exécutée dans ce lot : 24 tests Node ciblés du lecteur
et des baux, réussis localement puis sur Linux avant installation ; contrôle de
syntaxe des quatre scripts et des quatre traductions. Pas de nouvelle suite
Laravel complète ni de suite listener : leur code n'a pas changé.

Douze fichiers déployés avec vérification SHA-256 et sauvegarde préalable dans
/var/backups/exadcam-live-reconnect-20260923/reader. Vues Blade recompilées et
PHP-FPM rechargé ; aucun redémarrage des écouteurs GPS/vidéo. Modifications :
public/js/{live-player.mjs,map-video.mjs,dashcams.js,google-map.js}, deux vues
Blade, traductions map/dashcams FR/EN et les deux tests JavaScript associés.


## 23 septembre 2026 — Deux canaux réels sur le tableau de bord

Le tableau de bord consomme désormais les mêmes routes Laravel /dashcams/{id}/live
et les mêmes baux indépendants que Carte et Dashcams. Aucun nouveau port ni
service. Les flux sont ouverts uniquement après sélection explicite du véhicule
et libérés lors d'une navigation ou du changement de sélection. Voir dashboard-video.md
pour le périmètre, les tests et les limites de validation matérielle.

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


## Diagnostic du son et de l'interphone — 23 septembre 2026

Absence de son confirmée dans le code local ET dans les fichiers du listener en
production : liveRequest (0x9101) demande le type 1, vidéo seule ; MediaFrames
ignore les paquets de type > 2 (donc audio type 3) ; FFmpeg ne reçoit que le H.264,
sélectionne 0:v:0 et utilise -an. Activer le volume du lecteur ne crée donc aucune
piste audio. Aucune modification audio n'est livrée dans ce lot cartographique.

L'utilisateur confirme que Parler/Intercom fonctionne via CarAssist sur ES500-603
et JK114. Cela établit une capacité matérielle observée, pas la compatibilité
de leur codec et de leur canal de retour avec notre serveur. L'intégration exige
la validation des capacités et paquets audio de chaque firmware, leur conversion
pour le navigateur et une session de retour microphone avec autorisations de
flotte, geste utilisateur, capture explicitement accordée, arrêt et libération.
L'interphone doit avoir une latence propre à la conversation, indépendante de
la réserve vidéo actuelle de 15 secondes. Aucun microphone n'a été activé et
aucun son n'a été envoyé à un véhicule pendant ce diagnostic.


## Audio livré — 23 septembre 2026

Le diagnostic vidéo seule ci-dessus est désormais suivi d’une intégration audio.
Voir [live-audio.md](live-audio.md) pour les accès, essais et limites matérielles.
Le canal 1 fournit un flux JT1078 AV type 0. Le service vidéo relaie ses trames
audio vers le service audio pendant une écoute autorisée. Un interphone type 2
réserve ce canal et suspend sa vidéo, avec reprise après libération.
exadcam-audio écoute 1080/TCP et 127.0.0.1:3003 ; Apache expose uniquement
/audio-live/ en WebSocket authentifié. ws 8.21.3 est verrouillé dans package-lock.
Les APIs entre services et Laravel conservent le jeton interne existant.
