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


## 24 septembre 2026 — Retour matériel et format ES500

L’utilisateur confirme que Parler fonctionne physiquement sur JK114. Sur ES500,
son navigateur reçoit du son, mais le haut-parleur de la caméra reste muet.
Cette précision remplace l’attente de test mentionnée dans les entrées précédentes.

Capacités ES500 fiche 4 reçues à 07:45:06.297 UTC : A-law 6, mono, 8000 Hz,
sample_bits=16, frame_length=80, output=true. Le serveur formait tous les retours
G.711 en 160 octets. g711FrameSize sélectionne maintenant la longueur annoncée
entre 5 et 120 ms, sinon 20 ms par défaut. Les timestamps progressent selon les
échantillons effectivement envoyés ; 80 octets = 10 ms pour ce codec 8 kHz.
AAC reste assemblé selon ses en-têtes ADTS ; aucun changement du microphone JK114.

Régressions TCP/WebSocket : pairs annonçant 80 et 320 octets, contrôle du nombre
de paquets, identités 6/10 octets, séquences et timestamps, refus d’un second
interlocuteur, contrôle des droits et révocation. Suite Linux 30/30 avec FFmpeg.
Déployé à 08:18:41 UTC, sauvegarde camera-fixes-20260924-081841.
L’audibilité ES500 n’a pas été vérifiée physiquement après correction. L’agent
n’a lancé aucun microphone, aucune écoute ni session audio matérielle ce jour.

## 24 septembre 2026 — Validation utilisateur après correction

L’utilisateur confirme maintenant que le microphone / haut-parleur fonctionne
physiquement sur l’ES500, comme sur la JK114. Ce retour remplace l’attente de test
du paragraphe précédent. Aucun nouvel essai de microphone effectué par l’agent.
Le problème restant concerne la connexion pendant la veille ES500 ; voir
device-commissioning.md et listener-server.md.


## 24 septembre 2026 — Droits clients et interphone ES500

## Audio

- Réservation des deux canaux pendant Parler sur ES500, au lieu du seul canal 1.
  Les requêtes vidéo déjà en cours sont drainées avant le démarrage de l’audio.
  JK114 conserve le second canal. La reprise vidéo suit l’arrêt de l’interphone.
- Attente initiale jusqu’à 30 s après la commande, séparée du délai d’inactivité
  de 15 s après réception ; confirmation tardive 503/timeout ne détruit plus la
  session. Les permissions, refus explicites et durée du bail restent appliqués.
- Gain du microphone ES500 multiplié par deux (+6 dB), limité à 0,95 pleine
  échelle avant encodage G.711. JK114 et le volume d’écoute restent inchangés.
  Ce réglage amplifie le signal transmis, pas le réglage matériel du haut-parleur.
- Aucune reprise automatique du microphone ; pas d’écoute/micro navigateur lancé
  par l’agent. Les sondes matérielles reçoivent et comptent le PCM sans le stocker,
  puis transmettent uniquement du silence. Les baux et grants sont supprimés.

## Contrôles et limites

- 81 tests PHP ciblés / 720 assertions, puis 24 / 260 après confidentialité des
  alertes. SQLite en mémoire et cache de test séparés de la base métier.
- 64 tests JavaScript réussis.
- Linux : 4 tests codecs avec FFmpeg (dont mesure de gain et limiteur), 8 tests
  vidéo/coordination, 7 tests audio incluant premier paquet retardé de 17 s et
  ACK absent, droits/révocation, paquet G.711 80 octets. Une erreur de syntaxe
  dans un nouveau test a été corrigée avant le passage réussi des 7 tests audio.
- ES500 Toyota Hilux 9863BV01 : prêt en 1,284 s, 133760 octets PCM reçus,
  129280 octets silencieux transmis ; journal 422 trames reçues / 403 envoyées,
  fermeture volontaire. Cela valide le transport, pas l’audibilité humaine.
- Véhicule 2 : commande acceptée, zéro trame, expiration autour de 31–33 s.
  Capture bornée à 40 s : zéro paquet entrant TCP 1080. Utilisateur confirme
  Parler fonctionnel dans CarAssist. Nouvel essai après fermeture confirmée
  de CarAssist : résultat identique. Ne pas attribuer la cause à une concurrence
  exclusive, au volume, au navigateur ou à une panne matérielle sans preuve.

## Déploiements

- 13:16:19 UTC : 26 fichiers, copies runtime de trois modules listener.
  Sauvegarde `/var/backups/exadcam-client-audio-20260924-131615`.
  GPS PID 156157 conservé ; vidéo 164448, audio 164451.
- 13:21:51 UTC : masquage supplémentaire du modèle dans les alertes,
  sauvegarde `/var/backups/exadcam-client-alert-20260924-132151`.
- Pas de migration, modification de paramètres caméra ou nettoyage du cache
  applicatif. Vues reconstruites et PHP-FPM rechargé.

Référence du traitement audio : https://ffmpeg.org/ffmpeg-filters.html#alimiter.

## Transition finale et résultat matériel

13:30:05 UTC : transition ES500 directe vers l’interphone, sans 0x9102 d’arrêt
AV juste avant 0x9101. Réservation et fermeture des lecteurs locaux conservées ;
arrêts utilisateur, expirations et JK114 inchangés. Huit tests vidéo Linux
repassent après cette modification. Sauvegarde
`/var/backups/exadcam-intercom-transition-20260924-133002` ; seul service vidéo
redémarré, GPS 156157 inchangé.

Véhicule 2, essai 13:30:19 UTC : toujours zéro octet audio, expiration à 32,625 s.
Ne pas annoncer son interphone réparé. La suppression de l’arrêt AV ne prouve
donc pas la cause de son absence de connexion. ES500 Hilux, contre-essai final
13:31:23 UTC : prêt en 1,400 s, 88960 octets PCM reçus, 129280 octets silencieux
transmis ; arrêt volontaire après quatre secondes d’échange. La liaison de
cette seconde ES500 reste opérationnelle après la transition.

Tous les essais matériels sont terminés, sans microphone navigateur ni stockage
du son. L’audibilité et le niveau réel du haut-parleur n’ont pas été mesurés sur
place. Le cas Véhicule 2 demeure une différence de comportement de la liaison
JT1078 d’interphone ; pas de cause firmware/réseau attribuée faute de preuve.


# 24 septembre 2026 — Sens de Parler / Écouter

L'utilisateur veut parler dans EXADCAM et être entendu sur le haut-parleur de
Véhicule 2. Le mode précédent était duplex : il restituait aussi le microphone
de la caméra dans le navigateur. L'état « conversation » dépendait du décodage
entrant et ne confirmait pas l'envoi du microphone. Le code n'inversait pas les
boutons, mais ce comportement ne correspondait pas au sens demandé.

## Modification livrée

- Parler : microphone navigateur → socket JT1078 identifié de la caméra.
  Aucun son de caméra n'est joué dans le navigateur dans ce mode, ni envoyé
  en PCM par le serveur. Le décodage entrant n'est plus requis pour ouvrir
  l'encodeur retour ; le premier paquet valide identifie toujours la caméra
  et son codec avant tout envoi.
- Écouter : microphone caméra → navigateur, sans demander le microphone local.
- Le worklet de capture produit une sortie locale silencieuse, avec un gain
  nul supplémentaire sur son branchement au graphe audio. Aucun monitoring
  local du microphone n'est utilisé.
- Niveau de microphone visible pendant la capture. Curseur du volume réservé
  à l'écoute. Libellés FR/EN explicitent la destination du son.
- L'état « envoi vers le véhicule » apparaît après écriture effective du
  premier paquet sortant sur la socket identifiée, pas à la simple réception
  de son entrant. Il ne constitue pas un acquittement acoustique du matériel.
  Un micro sans retour effectif reste soumis au délai initial de 45 secondes.
- Gain ES500 +6 dB et limiteur conservés, cadres G.711 de 80 octets conservés.
  Commandes JT808, arrêt volontaire, autorisations, baux et absence de reprise
  automatique du microphone conservés.

## Preuves nouvelles, distinctes du lot précédent

- Journaux avant ce lot : Véhicule 2 a ouvert l'audio à 13:41:41 UTC, puis
  fermé à 13:42:05 avec 2432 trames entrantes / 2351 sortantes. Cela corrige
  l'observation antérieure « aucune connexion » ; aucune cause du retour
  ni redémarrage physique confirmé par l'utilisateur à ce stade.
- 66 tests JavaScript passés, dont séparation des directions, état sans envoi
  et absence de restitution locale du microphone dans le worklet.
- 17 tests PHP ciblés / 126 assertions : audio, aperçu vidéo du tableau de
  bord et carte. SQLite mémoire et cache séparé ; pas de suite PHP complète.
- 11 tests Linux passés : 7 transport/protocole, 4 FFmpeg réels (G.711 A/µ-law,
  AAC, amplification et limitation). Les trames du simulateur de caméra et
  celles du navigateur sont distinctes ; le retour contient bien celles du
  navigateur, et Parler ne renvoie aucun PCM de caméra au navigateur.
- Sonde Véhicule 2 à 13:55:39.676 UTC : prêt en 0,799 s, premier envoi confirmé
  à 0,842 s ; 129280 octets PCM silencieux injectés, zéro PCM renvoyé au
  navigateur, arrêt volontaire à 4,872 s. Aucun son ambiant enregistré ni
  microphone réel activé par l'agent. Un premier lancement du script a échoué
  avant toute création de session (chemin relatif d'un fichier auxiliaire),
  puis le chemin a été corrigé.
- Après le déploiement, l'utilisateur confirme : « ça sors mais le volume est
  bas ». La restitution physique de sa voix est donc confirmée par lui sur
  Véhicule 2, au-delà du test silencieux. Le niveau demande un réglage additionnel.

## Déploiement et sauvegarde

14 fichiers de production et copie runtime du listener audio, déployés à
13:54:08 UTC. Sauvegarde :
`/var/backups/exadcam-talk-direction-20260924-135406`.
Audio redémarré, PID 166384. GPS 156157 et vidéo 165314 conservés. Vues
reconstruites, PHP-FPM rechargé. Aucun changement de paramètres caméra,
migration ou purge du cache applicatif. Les trois tests modifiés/ajoutés
sont conservés dans le projet local. Version navigateur :
`talk-direction-20260924`.

## Ajustement après confirmation de l'utilisateur

L'utilisateur confirme la restitution, mais juge le volume faible. Le gain
microphone ES500 passe de 2 à 4 : +6 dB supplémentaires, soit environ +12 dB
par rapport au signal d'origine. Limiteur à 0,95 conservé. Cette amplification
porte uniquement sur le signal envoyé au véhicule ; l'écoute et la JK114
restent inchangées. Ce n'est pas une modification du volume matériel CarAssist.

Les 11 tests Linux ont été exécutés à nouveau avec succès sur ce réglage :
le test FFmpeg/G.711 mesure une amplitude environ quadruplée pour le signal
faible et des pics limités pour le signal fort. Aucune nouvelle session
matérielle ni aucun son audible n'a été injecté pour ce réglage de gain.

Déploiement à 14:00:22 UTC : deux sources et leurs copies runtime ; sauvegarde
`/var/backups/exadcam-talk-volume-20260924-140021`. Audio PID 166845 ; GPS
156157 et vidéo 165314 inchangés. L'utilisateur doit relancer Parler pour
apprécier le nouveau niveau sonore. Le volume perçu n'est pas déduit du gain
électrique ni des tests de codec.

Une tentative audio distincte a encore journalisé un échec de service 503 à
13:56:17 UTC. La séparation des sens et la confirmation utilisateur du son
ne prouvent pas la résolution de toute intermittence de connexion ES500.


# 24 septembre 2026 — Volume Parler JK114

L'utilisateur demande le même ajustement sur « l'autre device » et précise
ensuite « La JK114 ». Gain microphone de cette famille porté de 1 à 2
(environ +6 dB) avant encodage vers le véhicule, limiteur à 0,95 conservé.
Les ES500 gardent leur gain de 4 ; le volume d'écoute navigateur est inchangé.

Douze tests audio/Linux réussis sur ce lot : transport et révocation, codecs
G.711/AAC réels, gain ES500 et nouveau contrôle du gain JK114 après encodage
puis décodage AAC. Ce dernier mesure environ deux fois l'amplitude du signal
faible. Aucun nouveau test matériel audible, aucun microphone navigateur
activé par l'agent pour ce réglage. La voix sur Véhicule 2 avait été confirmée
par l'utilisateur avant ces augmentations ; l'appréciation des niveaux finaux
ES500 et JK114 reste à l'utilisateur.

Déployé le 24 septembre à 14:05:06 UTC, source audio et copie runtime.
Sauvegarde : `/var/backups/exadcam-jk-talk-volume-20260924-140505`.
Audio PID 167293 ; GPS 156157 et vidéo 165314 conservés. Aucun redémarrage
caméra, changement de réglage embarqué, migration ou purge du cache métier.
Le nouveau gain s'applique à la prochaine ouverture de Parler.
