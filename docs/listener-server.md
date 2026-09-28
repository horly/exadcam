# Serveur GPS et vidéo EXADCAM

> Nomenclature, 28 septembre : le modèle commercial en base et dans l’interface
> est ESTON ES500-603 JK114 ou 4G SmartVision JT808/1078. Le résolveur interne
> continue de transmettre respectivement les clés de profil JK114 et ES500-603,
> pour conserver les politiques des connexions actives. Le renommage ne change
> aucune version JT808, identité terminal, politique TCP ni configuration audio.


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

## 24 septembre 2026 — Fragments vidéo courts et attente de confirmation caméra

Demande : réduire encore le chargement du direct. ES500 fiche 4 CH2, mesure
serveur comparable : réserve nécessaire prête à 19,567 s avant, 12,823 s après
(gain 6,744 s, environ 34 %). Premiers paquets à 8,900 puis 8,386 s : le délai
propre à l'appareil reste présent. Ce n'est pas une mesure clic → lecture garantie.

Profil HLS rapide JK114/ES500 : fragments indépendants de 1 s, fenêtre d'environ
40 s, recompression libx264 veryfast/zerolatency CRF 20 avec dimensions conservées,
un thread decode/encode et analyse initiale réduite. Coût CPU supérieur à la copie.
Le serveur annonce une réserve initiale de 4 s uniquement pour ce profil ;
fallback copie, modèles inconnus et anciens serveurs restent à 8 s. Reprise
après épuisement à 15 s conservée, lecture commune aux trois interfaces.

Un timeout/503 de confirmation de départ n'envoie plus immédiatement Stop et
ne détruit plus la demande déjà autorisée ; réception possible pendant le délai
d'inactivité existant de 30 s, contrôlé toutes les 5 s. Identités, registre,
révocations et refus explicites restent appliqués. Pas de statut prêt artificiel.

Validation fraîche : 33 tests Linux avec FFmpeg, puis 7 ciblés après métadonnées,
puis 8 ciblés après correction ACK ; 64 tests JS. Test additionnel de cadence
SPS différente : 2 réussis. Pas de suite Laravel complète rejouée. Déploiements
12:24:04 puis 12:32:24 UTC, sauvegardes /var/backups/exadcam-live-pipeline-20260924-122401
et /var/backups/exadcam-live-pipeline-20260924-123223. Dix fichiers plus copies
runtime vérifiés par empreintes ; cache des vues reconstruit. Seul service vidéo
relancé, PID final 161604 ; GPS 156157 et audio 151193 inchangés.

Véhicule 2 : deux canaux lus en production, mais interruption source CH2 et
remplissage CH1 observés ; reprise automatique confirmée, puis arrêt volontaire.
JK114 fiche 3 : timeouts et arrivées média intermittentes, lecture continue
non validée. Capture bornée de 12 s, aucun paquet perdu par le noyau, seulement
deux images complètes reconstituées et un flux partiel inexploitable ; ne pas
attribuer une cause précise ni présenter la JK114 comme rétablie. Aucun micro
ni écoute lancé par l'agent. Aucun paramètre matériel changé, aucune coupure GPS.

Fichiers : listener/src/video.js et video-profile.js, tests vidéo associés,
live-player.mjs, map-video.mjs, leurs trois intégrations et versions Blade,
deux tests JS. Détails et limites dans docs/live-stream-startup.md ; preuves
et sources sauvegardées dans workspace analysis/live-pipeline-20260924/.


## 25 septembre 2026 — Connexions GPS préservées pendant une indisponibilité du service web

Signalement : ES500 accessibles sur CarAssist, mais hors ligne sur EXADCAM.
Cause vérifiée côté EXADCAM : une réponse « Service returned 503 » pendant
un redémarrage des services web ferme le transport d'un appareil authentifié.
L'origine du redémarrage web n'est pas établie ici. Une indisponibilité
temporaire de l'API était assimilée à une révocation. Ce comportement est
reproduit et vérifié avec des appareils synthétiques dans les nouveaux tests.

Correction : les erreurs temporaires du registre et de l'ingestion sont
identifiées à leur frontière HTTP. Une session déjà authentifiée reste ouverte
pendant un 408/429/500/502/503/504, timeout ou défaut réseau temporaire reconnu.
Le contrôle reprend périodiquement ; les positions suivantes utilisent la même
connexion après rétablissement. Un 403/404, changement d'identité ou jeton,
message invalide et une connexion non authentifiée restent refusés. Les
commandes sont bloquées si leur autorisation ne peut pas être revalidée.
Les erreurs répétées sont journalisées au plus toutes les 30 secondes par session.

Aucun faux contact/GPS n'est créé par le maintien de la socket. Les trames non
persistées ne reçoivent pas d'ACK de succès ; il n'y a pas de file de reprise
durable et un intervalle manquant reste possible si la caméra ne retransmet pas.
Les réponses valides 0x0001 et 0x1003 actualisent désormais la présence réelle,
avec une écriture espacée d'au moins dix secondes. Une commande média déjà
acceptée est confirmée avant l'écriture du contact pour éviter un nouveau délai.

Fichiers : listener/src/gps.js, nouveau backend-recovery.js et nouveau test
gps-backend-recovery.test.js ; copies runtime des deux sources. Aucun réglage
caméra, délai TCP/veille, code vidéo/audio ou règle de visibilité modifié.

Tests fraîchement exécutés : quatre nouveaux tests locaux sur la première
version, puis 21 GPS/protocole Linux ; après la protection contre l'attente
d'écriture de présence, 22 tests GPS/protocole Linux passent (dont cinq nouveaux).
Vraies sockets TCP locales et API synthétique : outage 503, reprise sans
reconnexion, révocation, absence de faux statut, traitement du reste d'une
rafale, réponses réelles et commande acceptée pendant une écriture bloquée.
Test de veille réelle de 185 secondes inclus. Aucune panne injectée sur le
service métier ni les caméras. Pas de suite Laravel, vidéo/audio ou UI rejouée.

Déploiement : 2026-09-25T12:03:47.300138+00:00. Sauvegarde /var/backups/exadcam-es500-stability-20260925-120347.
Empreintes contrôlées avant/après. Récepteur GPS activé par redémarrage ciblé :
PID 156157 → 184064.
Vidéo PID 165314 et audio PID 167293 inchangés.
Pas de redémarrage matériel, migration, purge du cache ou reconfiguration serveur.

Cette correction supprime une cause prouvée côté EXADCAM ; elle ne constitue
pas une validation de stabilité longue durée. Les fermetures distantes,
remplacements de connexion et expirations TCP sont des causes distinctes.
Le réveil cloud CarAssist lorsque JT808 est fermé reste non intégré. Ne pas
attribuer toutes les absences à la veille, ni promettre un retour instantané.
Le signalement distinct de Parler ES500 reste ouvert. Sources et scripts : workspace
analysis/es500-stability-20260925. Preuves conservées uniquement sur le serveur :
es500-stability-tests-20260925
(tests.tap, deployment.json, status-latest.json) et es500-stability-20260925/gps-evidence.json.
Le contrôle automatique a refusé l'export local des preuves détaillées (états
et identifiants d'appareils). Aucun autre moyen d'export n'a été utilisé.

## 25 septembre 2026 — Liaison directe SmartVision et délais des réponses JT808

L'utilisateur corrige explicitement le périmètre : résoudre la connexion directe
caméra → EXADCAM. Aucune demande fournisseur n'est souhaitée ou envoyée.
Le manuel SmartVision T2 est déjà confirmé ; ne plus demander sa confirmation.
Ne pas assimiler toute absence à une veille ni remplacer une investigation
de transport par une demande d'API de réveil.

Diagnostic passif : les ACK observés sur une liaison SmartVision active sont
positifs, entre environ 0,4 et 15 ms pendant une fenêtre de 45 secondes, sans
perte de capture ni fermeture observée. Cette fenêtre ne prouve pas une
stabilité de longue durée et n'explique pas l'appareil absent. Les preuves
détaillées restent sur le serveur ; aucun export de télémétrie ou d'identifiants.

Deux blocages reproduits avec de vraies sockets et une API synthétique :
l'authentification attendait l'écriture de présence, et une lecture lente du
registre bloquait les heartbeats derrière le contrôle périodique. Les tests
échouent avant correction, puis passent après. Ces reproductions démontrent
des défauts du serveur ; elles ne prouvent pas qu'ils causent chaque coupure
terrain. Les temps de réponse actuels observés restent bons.

Correction de listener/src/gps.js :
- ACK d'authentification après vérification réelle des identifiants, sans
  attendre l'écriture de présence ; même séparation pour les heartbeats.
- Une seule écriture de présence en vol par connexion, aucune file de reprises
  ni contact créé par un timer. Seule une trame authentifiée déclenche l'écriture.
- Le contrôle périodique du registre s'exécute hors de la file des trames ;
  ses appels concurrents partagent une seule requête. Les erreurs temporaires
  ne rejettent pas le heartbeat d'une session déjà authentifiée.
- Les révocations, changements d'identité et trames invalides restent refusés.
  Les commandes média nécessitent toujours la vérification du registre.
  Les positions ne reçoivent toujours aucun succès avant leur persistance.
- Le profil historique ES500-603 est conservé pour les SmartVision, sans
  changer les paramètres des caméras, l'année protocolaire, la vidéo ou l'audio.

Tests : 25 tests GPS/protocole réussis sur Linux, zéro échec, environ 190 secondes,
y compris le silence réel de plus de trois minutes, les deux formats JT808,
les rafales, la révocation et les pannes/ralentissements de l'API synthétique.
En local, 24 tests de la première version passent, puis quatre tests ciblés de
la version finale ; ces résultats intermédiaires ne remplacent pas le lot Linux.
Nouvelle couverture gps-contact.test.js ; gps-backend-recovery.test.js étendu.
Les tests de présence et de silence attendent désormais explicitement la
persistance asynchrone, sans supprimer leurs assertions. Le script facultatif
gps-network-outage.mjs est adapté à cette même sémantique ; il n'est pas
exécuté dans ce lot. Pas de suite PHP, audio ou vidéo complète rejouée.

Activation : correction enregistrée dans les sources locales et copie de
validation isolée sur le serveur. PAS ACTIVE dans le récepteur de production.
Le processus GPS actuellement en service conserve la version précédente.
Aucun redémarrage, fermeture volontaire, reconfiguration de caméra, inspection
dynamique du processus ni changement de pare-feu réalisé dans ce lot.
Ne pas annoncer un déploiement actif ou une reconnexion terrain résolue.

Limites : l'acceptation initiale exige toujours un registre disponible ; une
écriture GPS lente peut encore retarder les trames suivantes (pas de file
durable ajoutée). L'absence de nouvelles tentatives du second appareil et le
retour automatique rapide restent à expliquer. Le serveur ne peut accepter
une nouvelle connexion TCP qu'après une tentative entrante de l'appareil.
Conserver les sessions saines, plutôt que les interrompre pour un essai.


## 25 septembre — Politique transport SmartVision

> Maintien SmartVision, 25 septembre : correctifs GPS/ACK et nettoyage vidéo
> désormais ACTIFS après activation dans leurs fenêtres naturellement vides.
> Aucune session établie n'a été coupée pour charger ces correctifs.
> Politique TCP intégrée au GPS : sonde après 60 s de silence, intervalle 30 s,
> 10 essais, sans présence fictive ni commande caméra périodique.
> Le pont temporaire TCP s'est arrêté avec l'ancien processus GPS.
> Des fermetures distantes et retours spontanés ont été observés avant cette
> activation. La stabilité complète n'est PAS démontrée ; vérifier les nouvelles
> sessions et leur durée. Détails dans la dernière entrée de project-history.md.

Les nouveaux sources GPS importent transport-policy.js ; déployer ce module
avant gps.js. Serveur Node >=24.19 requis pour les trois délais explicites.
Les anciens Node 24 conservent le délai de 300 secondes pour éviter de raccourcir
accidentellement la fenêtre d'abandon avec des arguments ignorés. Les réglages
JK114 et les règles d'autorisation restent inchangés.
Le correctif vidéo importe media-cleanup.js ; le module doit précéder video.js.
Les nouveaux tests ont été exécutés sur Linux, hors environnement de production.

## Complément — Première connexion retardée sur réseau mobile

Une observation passive a aussi montré un premier message d'inscription du
Hilux arrivant après la fermeture du socket par le serveur, autour de l'ancien
délai initial de 15 secondes. Cette observation révèle un obstacle à cette
tentative de reconnexion ; elle n'explique pas toutes les fermetures distantes.
Les preuves et métadonnées restent dans le dossier de diagnostic du serveur.

Le délai initial et la limite absolue d'authentification passent à 60 secondes.
L'identité doit toujours être autorisée et authentifiée ; répéter l'inscription
ne prolonge pas cette limite. Les délais des connexions déjà authentifiées,
la politique TCP, les ACK et la persistance des positions restent inchangés.

Validation Linux : 27 tests ciblés réussis sur cette version, dont un nouvel
essai réel de 60 secondes. Le premier message arrive après 22 secondes et la
connexion s'authentifie ; une seconde connexion qui répète seulement son
inscription est fermée à l'échéance absolue, sans présence fictive. Les autres
tests couvrent les deux protocoles, les autorisations, les rafales, les erreurs
backend, la présence lente et les valeurs TCP effectives. Le test de silence
authentifié de 185 secondes avait été réussi sur la version précédente de ce
lot ; il n'a pas été relancé pour ce seul changement d'admission.

Sources et test enregistrés localement et sur le serveur, sauvegarde préalable
dans /var/backups/exadcam-admission-*. Le correctif supplémentaire d'admission
attend son activation sans connexion active ; il n'est pas encore annoncé
actif. Les correctifs ACK, TCP et nettoyage vidéo sont déjà actifs. Les deux
SmartVision ont repris contact sous cette version et les valeurs TCP ont été
vérifiées directement sur leurs deux sockets établis.

L'unité temporaire exadcam-smartvision-admission-activation attend une fenêtre
vide, au maximum six heures. Lire admission-activation.json dans le dossier
de diagnostic serveur pour connaître l'activation effective. Les premières
unités de transition ont terminé ; aucun pont TCP permanent n'est nécessaire.
La stabilité sur une longue durée et après une vraie panne réseau reste à
confirmer : ces retours ne permettent pas d'annoncer une résolution complète.



## 25 septembre 2026 — Activation explicite du délai de connexion

À la demande explicite « deploie », activation immédiate du dernier correctif
d'admission, auparavant installé mais en attente d'une fenêtre vide. Le service
GPS a été redémarré une fois après arrêt de l'observateur d'activation différée.
Le délai initial et la limite absolue d'authentification de 60 secondes sont
désormais ACTIFS ; aucune activation supplémentaire de ce lot n'est en attente.
L'inscription répétée ne dispense pas d'authentification et ne prolonge pas
la limite absolue. Les corrections ACK/TCP et nettoyage vidéo restent actives.

Contrôles de ce déploiement : sources GPS et dépendances comparés par SHA-256
avec la version testée, syntaxe Node vérifiée, nouveau processus GPS confirmé,
API de santé authentifiée en succès, services GPS/vidéo/audio actifs. Les
processus vidéo et audio ont été conservés. Les 27 tests Linux ciblés avaient
réussi avant ce déploiement sur ces mêmes sources ; ils n'ont pas été rejoués.
La sauvegarde précédente et le retour arrière conditionnel ont été vérifiés ;
aucun retour arrière n'a été nécessaire. Aucun changement de configuration
caméra, de protocole ou d'accès Wi-Fi.

Après activation, l'API de session confirme Véhicule 2 connecté. Le Hilux
reste sans session GPS au contrôle suivant. Déploiement terminé ne signifie
donc PAS disparition des coupures ni reconnexion durable du Hilux.

Les reçus d'activation et de santé restent sur le serveur dans le dossier de
diagnostic du lot. admission-activation.json indique maintenant l'activation
explicite réussie ; admission-explicit-deployment.json enregistre le contrôle.
Ce tour actualise le suivi local ; aucune nouvelle tentative de copie de la
note documentaire précédemment refusée n'a été effectuée.

## 28 septembre 2026 — Nomenclature des dashcams corrigée et déployée

Demande utilisateur : corriger partout dans l’application les appellations et les données déjà enregistrées :

| Ancienne appellation | Appellation désormais enregistrée et affichée |
| --- | --- |
| JK114 | ESTON ES500-603 JK114 |
| ES500-603 | 4G SmartVision JT808/1078 |

Les deux fiches de chaque famille ont été migrées en production : champs model et noms par défaut corrigés, aucun ancien model restant. Les éventuels noms personnalisés sont conservés. Migration également appliquée à la base MySQL locale EXADCAM. Les données historiques GPS et les associations véhicule/flotte restent inchangées. L’utilisateur reporte à plus tard son intervention sur le Backup du Hilux distant ; aucune configuration serveur de caméra n’a été modifiée dans ce lot.

Réalisation : constantes et normalisation centralisées dans DashcamProfile, conversion des anciennes valeurs à la lecture/écriture du modèle Dashcam, validation compatible avec les formulaires déjà ouverts, filtres/recherche et affichage dans les listes, la carte, les détails, le tableau de bord et les alertes. Les traductions françaises/anglaises et les noms proposés à la création sont corrigés. Le filtre de modèle a été élargi et les versions des fichiers JS/CSS incrémentées.

Compatibilité : les clés internes envoyées exclusivement aux listeners restent JK114 pour ESTON et ES500-603 pour SmartVision. Ce sont désormais des identifiants de profil historiques, pas les modèles affichés ni stockés en base. Cette correspondance explicite dans ListenerController préserve les politiques TCP, l’inactivité GPS, le cadrage, les paramètres vidéo et le gain microphone, sans redémarrer les listeners. Ne pas remplacer globalement ces clés internes par les appellations commerciales : toute évolution de ce contrat doit conserver le comportement des connexions actives.

Migration 2026_09_28_090000_correct_dashcam_model_names.php : mise à jour ciblée et transactionnelle des noms/modèles avec query builder, sans événements Eloquent, sans modification des horodatages ni des identifiants de communication. Elle préserve les noms personnalisés, accepte une réexécution et dispose d’un retour arrière. Les sources et noms/modèles précédents ont été sauvegardés avant application ; preuves détaillées de production conservées sur le serveur.

Validation réellement exécutée sur le code final : 65 tests ciblés réussis, 566 assertions, sur SQLite en mémoire isolée. Sont couverts le renommage/idempotence/retour arrière, la conservation de toutes les colonnes hors nom/modèle, les profils internes, les filtres, la carte, les détails, le tableau de bord et les permissions des comptes flotte. Suites : DashcamModelNamesTest, DashcamRegistryTest, ListenerAccessTest, FleetAdministrationTest, DashboardVideoTest et RealDashboardTest. Contrôle syntaxique JavaScript et syntaxe PHP des fichiers du lot réussi. Il ne s’agit pas d’une suite complète ni d’un nouvel essai physique du haut-parleur.

Déploiement du 28 septembre réussi : 15 fichiers vérifiés/installés et migration ciblée appliquée. Quatre fiches vérifiées par comparaison des empreintes de leurs paramètres de communication et d’affectation : inchangés. Réponse HTTP réelle du résolveur interne : profils préservés pour les quatre équipements. Véhicule 2 et Hilux SmartVision connectés après migration. Les trois processus GPS/vidéo/audio sont conservés, sans redémarrage. Connexion web et ressource JS répondent HTTP 200. Seules les vues compilées ont été purgées ; aucun vidage du cache des baux vidéo. Recharger la page permet aux onglets déjà ouverts de récupérer les nouveaux formulaires et ressources.
