# Réveil CarAssist — identification du 24 septembre 2026

> Identification corrigée : Véhicule 2 et Hilux 9863BV01 sont des SmartVision /
> CarAssist. L'utilisateur confirme le manuel SmartVision T2 comme référence.
> Les anciens diagnostics « ES500 » portent sur ces appareils. Ne plus demander
> de confirmation du manuel. La compatibilité d'un nouveau firmware et l'API
> de réveil restent à vérifier. Voir [identification](smartvision-identification.md).


La requête envoyée par l'application au cloud est identifiée dans l'APK distribué
par le site officiel CarAssist. Le transport utilisé ensuite entre ce cloud et
le module basse consommation de l'ES500 n'est pas déterminable depuis ce seul APK.
Le réveil matériel n'a pas été essayé par l'agent et n'est pas intégré à EXADCAM.

## Source examinée

- Page éditeur : http://www.carassist.cn/ ; même lien Android sur http://www.dvrassist.com/.
- APK directement lié : http://dl.carassist.cn/upgrade/CarControl.apk.
- Package constaté dans AndroidManifest.xml : com.car.control, version 3.4.8 (348).
- SHA-256 de l'APK : 28e6ab8de76757e528f6d1af3a41b0e76671f343782b8d027ccf3913c94edcc7.
- Certificat contenu dans l'archive, empreinte SHA-256 :
  3d4d66a757eca7acf480a0bebe1b798087ac03a8798e435841eb8076667146d5.
  Lecture du certificat seulement ; ne pas assimiler à une vérification cryptographique complète.
- Le manifeste de mise à jour CarControl.txt annonce encore 3.4.6 et pointe vers
  le même APK, dont le manifeste interne indique 3.4.8. Ne pas déclarer cette
  distribution comme la dernière version Play Store.
- Google Play publie également CarAssist Go! sous cn.carassist.control :
  https://play.google.com/store/apps/details?id=cn.carassist.control&hl=en.
  L'utilisateur confirme Android, sans préciser le package ni la version installée.

Analyse statique locale avec JADX 1.5.6 téléchargé depuis son dépôt officiel,
empreinte de l'archive vérifiée contre le digest de la release GitHub.
L'application n'a pas été installée ni exécutée. Neuf erreurs de décompilation
signalées dans des méthodes de bibliothèques Baidu, Google et ZXing ; les méthodes
CarAssist citées ci-dessous sont lisibles et ne figurent pas dans cette liste.
La décompilation ne remplace pas une capture de requête sur le téléphone réel.

## Demande de direct et réveil

Le service CarCloudService.java, ligne 30, utilise par défaut
`ws://ws.carassist.cn:8000`. Le serveur est surchargeable par la configuration
de l'application. Ce n'est pas l'adresse du listener JT808 EXADCAM.

Dans LiveVideoFragment.java, ligne 615, le démarrage du direct appelle la méthode
de prévisualisation avec action=1, le numéro de série sélectionné, peerurl=" "
et l'identifiant de caméra. WebSocketUtil.java, lignes 1705–1715, construit le
message contenant peer, cmd="preview", wakeup=1, action, peerurl et camid.

Le sérialiseur WebSocketUtil.java, ligne 1975, ajoute un compteur de requête et
éventuellement un relayid de réponse. Exemple de structure, jamais envoyé :

```text
relay:<numéro>{"peer":"<SN_CARASSIST>","cmd":"preview","wakeup":1,"action":1,"peerurl":" ","camid":70,"relayid":<numéro_réponse>}
```

`camid=70` est la valeur initiale de ce lecteur (caractère F). Les caméras
disponibles peuvent être modifiées par la réponse du device. Ne pas recopier ce
numéro comme s'il s'agissait du canal JT1078 1 ou 2.

Le destinataire peer est le numéro de série CarAssist, distinct du champ IMEI :
le modèle de caméra com/car/cloud/d.java lit séparément sn et imei. Le lecteur
récupère ce numéro par key_living_sn. L'IMEI seul ne suffit donc pas à construire
correctement la requête pour les équipements du compte.

Après une réponse de prévisualisation acceptée, LiveVideoFragment.l() envoie
`livekeep` puis programme le prochain envoi dix secondes plus tard. La méthode
WebSocketUtil.java, ligne 885, contient également wakeup=1 :

```text
relay:<numéro>{"peer":"<SN_CARASSIST>","cmd":"livekeep","wakeup":1}
```

Références locales complètes dans decompiled/sources/ :

- com/car/control/cloud/CarCloudService.java : 30, 87, 333.
- com/car/cloud/WebSocketUtil.java : 885, 915, 1705, 1975.
- com/car/control/browser/LiveVideoFragment.java : 615, 1128, 1585, 1675.
- com/car/cloud/d.java : 29.

## Accès et limites de ce qui est établi

La connexion passe par `userlogin`, avec uid et un champ h produit par
nativeGetHash(uid), puis la liste des équipements associés au compte. Les champs
de login sont visibles ; le contrat d'authentification tiers n'est pas documenté
par cette analyse. Aucun secret embarqué n'a été extrait ni réutilisé, aucune
session utilisateur n'a été récupérée et aucune commande cloud n'a été envoyée.

Le drapeau wakeup=1 prouve que l'application demande au relais CarAssist de
réveiller le destinataire. Le témoignage utilisateur relie l'ouverture du direct
au retour de son ES500 sur EXADCAM. Cela ne prouve pas si le serveur utilise un
canal TCP persistant, MQTT, un SMS, un appel opérateur ou une extension de firmware
pour effectuer le réveil matériel. Ne pas nommer arbitrairement ce dernier transport.

Le code utilise aussi wakeup=1 pour des lectures de paramètres. Une requête de
réveil sans ouverture de média pourrait donc être envisagée, mais son effet seul
sur l'ES500 doit être validé avant intégration. Envoyer preview ouvre le direct
CarAssist et peut affecter les ressources média ; ce n'est pas encore une API de
réveil seul validée pour EXADCAM.

## Suite concrète pour une intégration

1. Confirmer le package/version Android réellement utilisé et les SN des deux
   ES500 depuis le compte autorisé ; ne pas supposer que SN=IMEI.
2. Obtenir un accès d'intégration CarAssist/Prolink ou le protocole de réveil
   auprès du fournisseur SmartVision/CarAssist pour le firmware exact (correction d’identification du 25 septembre). L'accès SDK/API permettra
   de garder l'authentification dans un service serveur, jamais dans le navigateur.
3. Avec un compte et un appareil autorisés, observer une ouverture de direct en
   veille et corréler la requête relay/wakeup avec la reconnexion JT808 EXADCAM.
4. Tester une commande de réveil sans média avant de relancer le direct JT1078,
   avec un délai borné et une seule demande en cours par caméra. Préserver les
   permissions et le périmètre de flotte ; un délai expiré ne signifie pas en ligne.

Contact éditeur publié sur Google Play : plwl@for-fun.com.cn. Aucun message envoyé.
Le diagnostic identifie le message application → cloud. Il ne constitue pas
encore une validation cloud → ES500 ni un déploiement de réveil automatique.

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

## 24 septembre 2026 — Véhicule 2 immobilisé durablement

L'utilisateur précise que Véhicule 2 reste sur place et ne devrait pas bouger
dans les prochains temps. Aucun déplacement n'est requis pour le diagnostic.
Évaluer la session de communication, les derniers messages réellement reçus et
la disponibilité du direct ; des coordonnées identiques ne prouvent pas une
déconnexion. Lors du précédent essai, le constat hors ligne reposait sur l'absence
de session et de nouveau contact, pas sur l'absence de déplacement.
Cette précision ne confirme pas à elle seule la veille prolongée. Aucun nouvel
essai matériel, changement de code ou déploiement effectué dans ce complément.

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


## 25 septembre 2026 — Manuel SmartVision T2 confirmé par l’utilisateur

Confirmation explicite : « c'est le meme manuel que tu as trouvé tu peux utiliser ».
Le manuel SmartVision T2 publié par Oranic est donc accepté comme référence pour
les caméras CarAssist de Véhicule 2 et du Hilux 9863BV01. Cette confirmation lève
la demande de nouvelles photos/pages du manuel ; ne pas la réitérer. Les mentions
antérieures d'identification documentaire en attente sont dépassées par cette
confirmation. Une compatibilité de firmware précis demeure à vérifier avant
toute mise à jour matérielle.

Manuel : https://www.oranic-tech.com/uploads/43575/files/%5BUser-Manual%5DT2.pdf
Lecture des parties 1, 3 et 5 : JT808/JT1078, veille parking, fonctions CarAssist.
Il ne contient pas de contrat API pour réveiller un appareil dont JT808 est fermé,
ni de commande garantissant une reconnexion à un serveur tiers. La mention de
veille n'établit pas à elle seule la cause de chaque coupure observée.

Recoupement du code Android précédemment acquis : WebSocketUtil envoie wakeup=1
avec preview, livekeep et settings ; la lecture settings inclut jt808.
Les réglages génériques incluent autosleeptime, mais le support exact du firmware
et l'effet sur JT808 restent non validés. Aucune écriture de veille tentée.
Le canal cloud et son authentification restent nécessaires pour ces requêtes ;
leur identification dans le code n'est pas une intégration livrée.

Nouvelle recherche publique : pas de contrat API de réveil exploitable trouvé.
Nouvel essai d'accès Windows via Computer Use : native pipe indisponible,
os error 2 avant toute interaction. Aucune session ou donnée de compte extraite.
Demande technique ciblée préparée : docs/demande-integration-smartvision.md,
destinée au fournisseur SmartVision/CarAssist approprié, non envoyée.

Contexte, identification, diagnostic de retour et réveil actualisés. Aucun code
applicatif, profil stocké, configuration caméra ou service de production modifié.
Aucun test applicatif exécuté. Réveil et reconnexion rapide toujours non résolus.

## 25 septembre 2026 — Backup retiré par l’utilisateur, CarAssist toujours accessible

L'utilisateur indique avoir supprimé l'IP Backup 119.23.78.106 et son port sur
Véhicule 2, en conservant uniquement EXADCAM comme serveur principal. Il indique
que l'accès à distance dans CarAssist fonctionne toujours. Ce changement a été
effectué par l'utilisateur ; aucune écriture de configuration caméra n'a été
envoyée par l'assistant. Ne pas restaurer automatiquement l'ancien Backup ni
appliquer ce changement au Hilux sur la seule base de cette observation.

Contrôle en lecture seule : Véhicule 2 a une session GPS authentifiée active
dans EXADCAM et un contact récent. La session observée est restée ouverte pendant
environ dix-huit minutes sans coupure enregistrée. L'heure exacte de retrait du
Backup n'est pas connue : cette durée n'est pas une mesure depuis le changement
et ne démontre pas une amélioration qui lui serait due. Aucun redémarrage,
commande de réveil, lecture vidéo ou microphone déclenché pendant le contrôle.

L'observation renforce l'hypothèse de deux liaisons : client JT808 vers le serveur
principal/de secours configuré, et fonctions cloud CarAssist. Le manuel T2
accepté par l'utilisateur décrit explicitement les fonctions cloud sur réseaux
distincts (partie 5, pages PDF 11-12), dont vidéo et interphone à distance :
https://www.oranic-tech.com/uploads/43575/files/%5BUser-Manual%5DT2.pdf

La coexistence observée ne suffit pas à démontrer que l'ancien Backup causait
les coupures. La persistance du réglage et du fonctionnement CarAssist après
une future reconnexion normale restent à confirmer. Aucune reconnexion forcée
n'est demandée pour ce seul constat. Le champ standard JT808 0x0017 précédemment
vide n'est toujours pas une lecture fiable du champ Backup propriétaire.

Suivi local actualisé ; détails de production conservés sur le serveur. Aucun
code applicatif modifié, aucun déploiement ni test applicatif rejoué dans ce lot.
