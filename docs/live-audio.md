# Son et interphone — 23 septembre 2026

Les trois surfaces (tableau de bord, panneau vidéo de la carte et modal de la
liste Dashcams) proposent Écouter, Parler, un volume et Arrêter l’audio.
Parler demande le microphone au navigateur ; Écouter ne le demande jamais.
HTTPS est nécessaire en production. Les refus de permission, périphériques
absents, équipements hors ligne et formats incompatibles sont affichés.

## Réception et conversation

Le canal vidéo 1 est demandé en audio/vidéo JT1078 (0x9101, type 0). Le canal 2
reste vidéo seule. Le service vidéo transmet les trames audio au service audio
uniquement pendant une écoute autorisée. HLS reste H.264 sans piste audio ;
FFmpeg convertit l’audio AAC/ADTS ou G.711 A-law/µ-law mono en PCM 16 kHz.
Le navigateur reçoit ce PCM par WebSocket et le joue avec AudioWorklet.
Une réserve de 200 ms et une file bornée évitent d’accumuler de vieilles paroles.

La JK114 observée remplace la connexion vidéo 1 lorsqu’une commande d’écoute
séparée est envoyée. Le premier essai a montré une coupure audio après 42 trames,
au moment de la reprise vidéo. L’écoute partage donc désormais le flux vidéo,
sans commande concurrente vers la caméra.

L’interphone réserve le canal 1 avant la commande type 2 et empêche les reprises
vidéo de le remplacer. La vidéo 1 est suspendue pendant la conversation ; le
canal 2 reste disponible. À l’arrêt du micro, la réservation est libérée et
la reprise automatique vidéo existante retrouve le canal 1 (avec son délai de
reconnexion et de remplissage). Cette limite est indiquée dans l’interface.
Le retour micro PCM est encodé au codec/rate reçu puis envoyé en JT1078 sur
la même connexion TCP que l’audio montant. Aucun microphone n’est redémarré
automatiquement après une erreur.

L’audio est proche du temps réel et peut devancer l’image, qui conserve sa
réserve vidéo de quinze secondes. Ce lot ne réalise pas une synchronisation
audio/vidéo et ne modifie pas cette réserve. L’audio n’est pas enregistré.

## Accès

L’écoute exige video.view ; parler exige aussi audio.talk. Admin et superadmin
disposent de leurs droits dans leur périmètre ; un utilisateur normal doit
recevoir la nouvelle permission de son admin. La caméra, sa flotte, ses bornes
d’affectation et l’activité du compte sont revalidées en cours de session.
Les réponses navigateur ne révèlent pas d’IMEI.

Chaque bail appartient à un compte ET à sa session web. Une seule session audio
est admise par caméra. Le WebSocket exige une origine autorisée et un jeton
opaque à usage unique transmis comme sous-protocole, jamais dans l’URL.
Fermeture, changement de véhicule, onglet masqué, révocation ou perte de réseau
coupent la capture et libèrent la session. L’expiration serveur couvre un client
qui disparaît. Les capacités internes sont contrôlées périodiquement (3 s,
hors délai de requête) et le bail expire après 20 s sans renouvellement.

## Exploitation

- exadcam-audio : TCP public 1080 pour l’interphone, API 127.0.0.1:3003.
- Apache : /audio-live/ vers 3003, upgrade WebSocket ; aucune API interne publique.
- Coordination entre services : VIDEO_API_URL (3002), AUDIO_API_URL (3003).
- AUDIO_ALLOWED_ORIGINS vaut https://exadcam.app par défaut, liste séparée par virgules.
- Dépendance ws 8.21.3 verrouillée, FFmpeg existant, Node 24.
- Installation : deployment/systemd/exadcam-audio.service et npm ci --ignore-scripts
  dans le répertoire du listener. Ne pas vider le cache Laravel des baux vidéo.

Les capacités JT1078 0x9003/0x1003 sont interrogées avant ouverture. Seuls AAC
(type 19), G.711 A-law (6) et µ-law (7), mono, sont pris en charge. La réponse
matérielle doit annoncer une sortie audio pour autoriser l’interphone.

## Vérifications exécutées

- 45 tests Laravel ciblés, 358 assertions : droits, flotte, propriétaire du bail,
  révocation, affectation, expiration, présence des contrôles.
- 49 tests JavaScript : lecteurs existants et cycle micro/écoute ; fermeture
  pendant la permission, réponse HTTP tardive, révocation, débranchement,
  congestion et appels des minuteurs compatibles avec le navigateur.
- 21 tests listener sur Linux, aucun ignoré : FFmpeg réel aller/retour pour les
  trois codecs, TCP/WebSocket, origine et jeton, source AV partagée, arrêt audio
  préservant les spectateurs vidéo et réservation du canal pendant l’interphone.
- Prévisualisation locale isolée : sélection, demande d’écoute sans micro,
  message d’indisponibilité et libération des commandes. Un blocage de minuteur
  du navigateur a été détecté et corrigé avant le déploiement initial.
- Son réel JK114 reçu lors du diagnostic : AAC LC mono 8 kHz, 93 trames décodées
  en 380928 octets PCM lors du test d’écoute seule. Aucun son n’a été envoyé au
  haut-parleur d’un véhicule par l’agent. Le retour audible nécessite un essai
  humain via Parler. La réception G.711 de l’ES500 est également confirmée.

Déploiements sauvegardés : /var/backups/exadcam-audio-gps-20260923-154415,
/var/backups/exadcam-audio-20260923-162103 (service/API/interface), puis
/var/backups/exadcam-audio-20260923-163209 (coordination AV/interphone).
Le dernier lot redémarre les trois listeners ; aucune migration ni modification
des données métier et aucun cache de bail vidé.


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
