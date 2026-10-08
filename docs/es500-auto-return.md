# ES500 — retour automatique, 24 septembre 2026

> Résultat du week-end, vérifié le 28 septembre : Véhicule 2 reste connecté
> depuis environ 64 heures sans fermeture de sa session GPS, avec EXADCAM seul
> et le Backup retiré. Le Hilux, dont le Backup reste configuré, présente encore
> des renouvellements de session et des expirations réseau, malgré son état
> connecté au contrôle. Résultat positif limité à Véhicule 2 sur cette période ;
> voir l’entrée du 28 septembre en fin de document pour les limites du constat.


> Mise à jour du 25 septembre : le manuel T2 est déjà accepté par l'utilisateur.
> FX-4PVK sert uniquement à la recherche Internet ; connexion Wi-Fi interdite.
> La démarche locale mentionnée plus bas est annulée. Voir l'entrée finale.

> Identification corrigée : Véhicule 2 et Hilux 9863BV01 sont des SmartVision /
> CarAssist. L'utilisateur confirme le manuel SmartVision T2 comme référence.
> Les anciens diagnostics « ES500 » portent sur ces appareils. Ne plus demander
> de confirmation du manuel. La compatibilité d'un nouveau firmware et l'API
> de réveil restent à vérifier. Voir [identification](smartvision-identification.md).


Diagnostic en cours, pas une déclaration de résolution.

## Paramètres lus sur les appareils réels

À 11:11 UTC, lecture JT808 `0x8106` sur les deux sockets existants, sans
redémarrage. Les appareils 2 et 4 répondent tous les deux :

| Paramètre | Valeur |
| --- | --- |
| 0x0001, intervalle heartbeat | 20 s |
| 0x0002, attente réponse TCP | 10 s |
| 0x0003, retransmissions messages TCP | 0 |
| 0x0013, serveur principal | 62.171.190.15 |
| 0x0018, port TCP | 7808 |
| 0x0017, serveur de secours | chaîne vide |
| 0x0027, intervalle de positions en veille | 10 s |
| 0x0029, intervalle de positions normal | 10 s |

La valeur vide de 0x0017 ne correspond pas au champ Backup de la capture
CarAssist. Ne pas supposer que le firmware expose sa configuration propriétaire
complète dans cette réponse standard. La valeur zéro de retransmissions ne
mesure pas directement le nombre de reconnexions TCP ni l'état de veille.

## Essai de paramètres de retransmission

À 11:14:08.044 UTC, seul appareil 2 : `0x8103`, paramètre 0x0002 à 30 s,
0x0003 à 3. ACK succès à 11:14:08.229. Relecture à 11:14:08.408 : 30 et 3,
autres paramètres inchangés. Les réglages de l'appareil 4 restent inchangés.
Ce résultat prouve l'application immédiate, pas la persistance après reboot,
ni la correction de la reconnexion automatique.

## Essai de fermeture ciblée

Session 2 présente avant essai. À 11:16:01.270 UTC, une unique fermeture via
l'API interne `/disconnect`, puis surveillance bornée trois minutes. Les autres
appareils ne sont pas déconnectés et aucun service n'est redémarré.
Les résultats bruts sont conservés côté serveur dans
`/home/exad-cam/es500-auto-return-20260924/reconnect-test.json`.

Le contrôle Windows a encore échoué avant toute interaction (native pipe,
OS error 2). Aucun compte, secret ou jeton CarAssist extrait. L'utilisateur
répond « eston ne pose pas problème » à la question sur l'accès API/SDK ; cette
réponse ne confirme ni un contrat API ni un accès technique utilisable.

L'utilisateur applique temporairement, sur Véhicule 2 uniquement, Backup IP
62.171.190.15 et Backup Port 7808, autres champs conservés, puis Submit.
Retour à 11:19:40.990 UTC. Deuxième fermeture ciblée à 11:20:30.902 UTC : 37
relevés sans session pendant 180 s ; toujours absent à 11:25:22 UTC.
Le réglage de secours identique ne suffit donc pas dans cette fenêtre.
L'utilisateur restaure ensuite 119.23.78.106:6608 et Submit rétablit l'appareil.
À 11:28:00 UTC, contact et GPS 11:27:55. Aucun basculement effectif sur le
secours tiers n'a été capturé. Le retour de veille reste distinct de ce test.

Dernier état GPS avant deuxième fermeture : ACC rapporté vrai, fix GPS vrai.
Cela ne mesure pas le mode électrique réel. Capture SYN bornée 90 s : huit
paquets venant d'autres adresses, aucune perte de capture, aucun SYN de la
dernière IP de Véhicule 2. Les rejets « Invalid frame start » de cette fenêtre
n'identifient aucun terminal : ne pas les attribuer à Véhicule 2.

Lecture de propriétés 0x8107 à 11:28 UTC, mêmes réponses pour les deux ES500 :
manufacturer 12345, model FX, hardware T1, firmware
PL_TW1-V1.1_4G_NCS_EN_2.2.31_2.3.7. Aucune commande de mise à jour envoyée.

Troisième essai, 11:29:17.201 UTC : resetAndDestroy sur la seule session 2
(réinitialisation TCP, comparée à la fermeture normale des deux premiers essais).
Capture réelle : RST émis à 11:29:17.201832 UTC ; aucun SYN de retour capturé
dans la fenêtre de 180 secondes. Le compteur indique 1 paquet capturé,
3 reçus par le filtre, 0 perte noyau ; ne pas présenter ceci comme une capture
exhaustive de tout le trafic. 37 relevés sans session entre 11:29:32 et
11:32:33 (181 secondes). Toujours absent à 11:34:38 et 11:36:14 UTC.

L'utilisateur ne peut pas réappuyer sur Submit maintenant. Aucun nouvel essai
de coupure ne doit être lancé. Véhicule 2 reste hors ligne après cet essai,
dernier contact 11:29:07 et GPS 11:29:06 UTC. Le Backup original a été restauré
par l'utilisateur avant cet essai. Les paramètres TCP d'essai 30 s / 3 restent
appliqués : restauration initiale 10 s / 0 préparée mais NON exécutée, faute
de session. Ne pas présenter ces paramètres comme une correction validée.
Commande de retour arrière, uniquement après retour de la session 2 :
`sudo /usr/local/bin/node /home/exad-cam/es500-auto-return-20260924/query-runtime.mjs restore-retries`.
Elle relit d'abord et n'écrit que si les valeurs restent exactement 30 / 3,
puis vérifie 10 / 0. Ne pas écraser une modification intervenue entre-temps.

Observation complémentaire : l'appareil 4 conserve une session à 11:34:38,
mais son dernier contact applicatif reste 11:31:12. Une simple requête de
capacités audio (aucun média ni micro ouvert) reçoit une vraie réponse 0x1003
à 11:35:49.919 UTC. À 11:36:14, last_seen_at reste 11:31:12. Le listener ne
comptabilise donc pas cette réponse authentifiée comme présence ; cela peut
produire un statut applicatif périmé malgré une caméra joignable. Ce défaut
distinct est identifié, pas corrigé/déployé dans ce lot. Il n'explique pas
l'absence de socket de Véhicule 2. Ne pas confondre /status 200 avec un contact
récent ni prétendre que les deux caméras sont totalement fonctionnelles.

## Méthode et limites

Sonde de maintenance Node sur les sockets existants, inspecteur activé
temporairement sur 127.0.0.1, fermé et contrôlé après chaque exécution. Aucun
secret renvoyé, aucun endpoint public ajouté, aucun redémarrage. Les observateurs
de données sont retirés à l'expiration, aucune tâche de maintien en éveil laissée.
Les commandes de lecture sont limitées aux paramètres non secrets cités ci-dessus.

Références primaires consultées :
- https://github.com/QuecPython/jtt808/blob/master/docs/en/API_Reference.md
  (signification des paramètres et commandes JT808 standard).
- https://www.estontech.cn/detail-1532.html
  (annonce veille avec réveil distant et double IP, pas de contrat de réveil fourni).

Premier essai complet : 37 relevés, tous sans session pendant 180 secondes.

Quatre tests unitaires locaux de la sonde passent : portée appareil 2, encodage
exact de la commande, relecture, refus du retour arrière si paramètres modifiés
entre-temps, réinitialisation ciblée et retrait des observateurs. 0 échec.
Pas de suite applicative complète rejouée : aucun code de production modifié.

Les cinq services ont été vérifiés actifs. Aucun service redémarré, aucun cache
vidé, aucun changement de pare-feu, aucune vidéo/micro lancés. Les tâches de
mesure sont terminées et le port temporaire d'inspection 9229 est fermé.
Pas de surveillance ni de restauration en arrière-plan laissée active.

Blocage : pas de commande de réveil/reconnexion documentée pour ce firmware
hors session JT808, ni d'accès d'intégration CarAssist utilisable. Brouillon
technique `vendor-request.md` préparé mais non envoyé. Aucun réveil cloud
automatique intégré ; le problème demandé n'est pas résolu.

## Complément à 11:54 UTC — vitesse du direct

L'utilisateur précise le retour normal au démarrage du véhicule et les absences
de l'autre caméra encore accessible sur CarAssist, puis la lenteur des deux
lecteurs EXADCAM. Cela ne fournit pas d'accès API/SDK de réveil. Correction web
indépendante livrée : voir docs/live-stream-startup.md. Véhicule 2 encore 409
à 11:54:03 UTC, dernier contact 11:29:07 ; rollback TCP 10 s/0 toujours en attente.
Aucune nouvelle coupure imposée ni changement aux réglages caméra pendant ce lot.

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


## 25 septembre 2026 — Diagnostic complémentaire ES500 sans coupure

L'utilisateur précise que le retour de Véhicule 2 suit son réenregistrement
du formulaire serveur CarAssist, sans changer les valeurs. Il confirme ensuite
que sa vidéo fonctionne. Ne pas attribuer ce retour au correctif serveur ni
le présenter comme une reconnexion autonome. Le réenregistrement demandé pour
l'autre ES500 n'est pas possible maintenant ; ne pas répéter cette demande.

Diagnostic limité à la lecture : contrôle du port public, des sessions et de
la télémétrie, observation réseau bornée et interrogation 0x8106 sur les
connexions existantes. La réponse ne contient pas le paramètre demandé 0x007c ;
cette omission ne prouve pas l'absence de capacité matérielle de réveil. Une
fermeture peer_closed ne permet pas de départager caméra et réseau distant.
La présence dans CarAssist ne garantit pas une liaison JT808 avec EXADCAM.

Aucune modification des paramètres caméra ou du code de production, aucun
redémarrage ni coupure volontaire pendant ce diagnostic. L'inspecteur temporaire
est fermé, ses observateurs retirés et l'onglet de diagnostic fermé. Pas de
surveillance persistante ou de restauration en attente. Aucun essai micro,
écoute ou lancement vidéo par l'agent dans ce lot.

Nouveaux fichiers workspace : analysis/es500-reliability-20260925/
observe-readonly.js, prepare-probe.cjs, query-readonly.mjs et probe.test.mjs.
Contrôles exécutés : syntaxe de la sonde et deux tests de sa portée en lecture
seule, du filtrage des paramètres et du retrait des observateurs. Tous réussis.
Les suites applicatives antérieures ne sont pas rejouées ici.

Les preuves détaillées restent sur le serveur dans les répertoires de diagnostic
es500-reliability-20260925 et es500-stability-tests-20260925. Aucun export local
de journaux ou d'états identifiants. Cette note consigne le résultat technique
et les confirmations de l'utilisateur, sans recopier ces preuves.

Limites : le réveil à la demande depuis une liaison JT808 fermée n'est toujours
pas intégré ; le retour automatique rapide et la stabilité à long terme ne
sont pas validés. L'accès à CarAssist via Windows reste indisponible pour
l'agent. Ne pas changer une étiquette 2013 en 2019, des réglages de veille ou
des serveurs de secours pour simuler une résolution.


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

## 25 septembre 2026 — FX : recherche Internet uniquement, accès Wi-Fi annulé

### Périmètre corrigé par l'utilisateur

FX-4PVK est uniquement un indice pour rechercher des informations sur Internet.
L'utilisateur interdit expressément la connexion au Wi-Fi de la caméra. Aucun
accès Wi-Fi n'a eu lieu. La demande antérieure de connecter le PC est annulée ;
ne pas la réitérer. La sonde locale préparée n'a jamais interrogé la caméra et
est maintenant désactivée explicitement avant toute opération réseau.
La priorité reste la liaison directe caméra → EXADCAM, sans démarche fournisseur.

### Résultats et portée

1. Le manuel T2, déjà confirmé par l'utilisateur, décrit le préfixe FX-xxxx
   (page 9 du PDF) et les fonctions cloud CarAssist. Il confirme JT808/JT1078,
   sans identifier une année de protocole ni donner de correctif de reconnexion.
   https://www.oranic-tech.com/uploads/43575/files/%5BUser-Manual%5DT2.pdf

2. Le manuel P9000/T88 distingue l'application Jt808service utilisée pour CMSV6
   des fonctions de CarAssist. Sa capture de configuration affiche exactement
   119.23.78.106:6608, adresse présente dans le champ Backup fourni par
   l'utilisateur, ainsi que Manufacturer ID 12345 et Terminal Model FX.
   Il s'agit d'un autre modèle de la même famille logicielle, pas d'une preuve
   d'identité matérielle. Le moteur de recherche restitue le texte et les champs
   de la capture ; l'ouverture intégrale du PDF a échoué par expiration de délai.
   https://www.oranic-tech.com/uploads/43575/files/%5BUser-Manual%5D4G-Dash-Cam-P9000.pdf

3. Le manuel original LiveEye de TopDawg/Falcon, conservé sur device.report,
   prescrit un serveur principal personnalisé et un Backup IP vide. Il décrit
   également Jt808service et un identifiant FX-xxxx. Cette consigne concerne ce
   modèle LiveEye ; elle ne prouve pas le comportement du firmware SmartVision.
   https://device.report/m/780e14d5af32e471ff4b110f4e31c6ae2190dad0872b215402b00da6d34427b2
   La page officielle Falcon répertorie aussi le manuel LiveEye :
   https://falconelectronics.com/pages/product-quick-start-guides-manuals

Le rapprochement avec le code CarAssist déjà acquis appuie l'existence de deux
liaisons distinctes : le cloud CarAssist et le client JT808. Le statut CarAssist
ne suffit donc pas à établir une connexion JT808 à EXADCAM. Le retour observé
par l'utilisateur après Submit est compatible avec une réinitialisation de la
configuration/connexion JT808 ; l'implémentation du client embarqué n'a pas été
inspectée, et ce mécanisme ne doit pas être annoncé comme démontré.

Hypothèse à vérifier : la sélection du serveur principal/de secours ou les délais
de reconnexion du client JT808 pourraient expliquer certaines absences. Aucune
preuve de connexion de la caméra au serveur de secours n'a été obtenue. L'adresse
publique trouvée n'est pas démontrée comme indispensable au cloud CarAssist.
Le précédent essai Backup = EXADCAM n'avait pas obtenu de retour rapide pendant
sa courte fenêtre ; il ne faut ni le présenter comme concluant, ni le répéter
automatiquement. La réponse standard 0x0017 précédemment vide ne coïncide pas
avec le champ propriétaire Backup affiché : une écriture 0x8103 aveugle n'est
donc pas un correctif fiable de ce champ.

Aucun correctif public confirmé pour PL_TW1-V1.1_4G_NCS_EN_2.2.31_2.3.7 trouvé
dans les recherches effectuées. Ne pas forcer JT808 2019 ni installer un firmware
d'un autre modèle à partir du seul nom FX ou d'une ressemblance de manuel.

### État livré

Recherche publique et suivi documentaire uniquement. Aucun réglage de caméra,
code applicatif actif ou service de production modifié. Aucun test applicatif
relancé. Le correctif de délais des ACK déjà testé reste préparé, non activé.
Le retour automatique fiable n'est pas encore résolu. La prochaine observation
utile doit départager absence de tentative TCP et tentative reçue puis refusée
ou interrompue, sans provoquer une coupure d'une session saine.


## 25 septembre — Maintien TCP et activation sans coupure

> Maintien SmartVision, 25 septembre : correctifs GPS/ACK et nettoyage vidéo
> désormais ACTIFS après activation dans leurs fenêtres naturellement vides.
> Aucune session établie n'a été coupée pour charger ces correctifs.
> Politique TCP intégrée au GPS : sonde après 60 s de silence, intervalle 30 s,
> 10 essais, sans présence fictive ni commande caméra périodique.
> Le pont temporaire TCP s'est arrêté avec l'ancien processus GPS.
> Des fermetures distantes et retours spontanés ont été observés avant cette
> activation. La stabilité complète n'est PAS démontrée ; vérifier les nouvelles
> sessions et leur durée. Détails dans la dernière entrée de project-history.md.

Le profil transport est dans listener/src/transport-policy.js. Il ne simule
jamais une position ni une présence. Une fermeture initiée par la caméra peut
toujours survenir : ce réglage ne force pas son client embarqué à se reconnecter.
La piste d'interrogation périodique des capacités a été abandonnée et retirée.
Consulter le journal de projet et idle-activation.json sur le serveur pour
distinguer réglage TCP actif, source installé et processus réellement rechargé.

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

## 2026-09-28 — Stabilité du week-end avec EXADCAM seul sur Véhicule 2

- Retour utilisateur : Véhicule 2 ne se déconnecte plus depuis qu’il a conservé uniquement le serveur EXADCAM, avec le Backup retiré. Il confirme que le Backup reste configuré sur le Toyota Hilux 9863BV01. La suppression du Backup avait déjà été signalée le 25 septembre, avec accès distant CarAssist toujours fonctionnel.
- Vérification du 28 septembre en lecture seule : Véhicule 2 conserve la même session GPS authentifiée depuis vendredi 25 septembre à 16 h 31 min 52 s, heure de Kinshasa, soit environ 64 heures. Aucune fermeture de cette session n’est enregistrée sur la période ; le contact reçu était âgé d’environ 5 secondes lors du contrôle. Ce constat repose sur la session réelle du listener et les journaux, pas uniquement sur le statut affiché dans l’interface.
- Comparaison sur la même période : le Hilux est également en ligne au contrôle, mais les journaux enregistrent 165 authentifications et 164 fermetures de sessions, dont 105 remplacements par une nouvelle connexion, 54 expirations réseau, 4 fermetures par le pair et un retour de service 422. Ces nombres ne représentent pas 164 pannes utilisateur : un remplacement peut intervenir alors qu’une nouvelle connexion est déjà authentifiée. Aucun contenu détaillé des journaux ni position géographique n’est copié ici.
- Configuration de référence validée en exploitation pour Véhicule 2 sur ce week-end : EXADCAM seul, Backup vide. Ne pas restaurer automatiquement l’ancien Backup. Pour le Hilux, un essai de cette même configuration est pertinent ; il n’a pas été effectué pendant ce contrôle et ne doit pas être présenté comme appliqué.
- Interprétation : les observations renforcent la piste d’un effet de la configuration secondaire, sans isoler à elles seules sa causalité. Les correctifs du listener ont aussi été activés le vendredi et l’heure exacte du retrait du Backup n’est pas connue. La stabilité observée ne valide pas encore un retour après une nouvelle panne d’alimentation ou de réseau, ni la stabilité future de tous les appareils.
- Aucun changement de code, déploiement, redémarrage, commande caméra, modification de paramètres ou nouvel essai de rupture. Aucun test automatisé relancé ; documentation locale mise à jour. Les anciens diagnostics restent conservés comme historique.

## 29 septembre 2026 — Nissan Patrol et demande de retrait du Backup du Hilux

L’utilisateur précise que Véhicule 2 est désormais Nissan Patrol et autorise le retrait du seul serveur Backup sur le Hilux SmartVision, en conservant EXADCAM. Lecture de la base de production : dashcam 2 affectée à Nissan Patrol 1387AA10 ; dashcam 4 à Toyota Hilux 9863BV01. Aucun renommage supplémentaire effectué. La demande ne concerne ni le Nissan ni le Hilux ESTON.

Contrôle direct le 29 septembre, 08 h 10–08 h 12 Kinshasa, sur la session du Hilux SmartVision uniquement : requête JT808 0x8106 puis inventaire 0x8104. Le Hilux répond en environ 0,37 seconde dans les deux cas. Serveur principal 0x0013 = 62.171.190.15 et port TCP 0x0018 = 7808 ; port UDP 0. Le paramètre standard 0x0017 est renvoyé vide lorsqu’il est demandé explicitement, mais absent de l’inventaire complet de 19 paramètres. Aucun de ces paramètres ne contient l’ancien Backup 119.23.78.106. Ce résultat ne prouve pas que le champ Backup de CarAssist est vide et ne permet pas de le supprimer avec une correspondance vérifiée.

Le code Android CarAssist acquis précédemment confirme des champs distincts jt808.ipbak et jt808.portbak dans Jt808ConfigActivity et leur envoi par WebSocketUtil via settings. Aucune correspondance de ces champs avec une commande JT808 de ce firmware n’a été trouvée. La référence primaire QuecPython définit 0x0017 comme l’adresse de secours standard, pas comme un contrat propre au firmware SmartVision : https://github.com/QuecPython/jtt808/blob/master/docs/en/API_Reference.md . Ne pas inventer un identifiant de port Backup ni mettre 0x0018 à zéro : c’est le port principal partagé standard.

Essai Computer Use pour accéder à CarAssist Windows : native pipe indisponible, os error 2 avant toute interaction. Aucun accès Wi-Fi, extraction de session ou contact fournisseur. L’utilisateur a reçu les valeurs précises à appliquer dans le formulaire distant du Hilux : Main IP conservée, Main Port 7808 conservé, Backup IP vide, Backup Port 0, autres champs inchangés, puis Submit. Il répond ne pas avoir accès au formulaire à distance. Retrait du Backup NON effectué par l’assistant et NON confirmé ; ne pas attendre une manipulation que l’utilisateur a signalée inaccessible.

Vérification complémentaire du code Android : l’entrée « JT808/1078 config » (ll808 / test_808_conf) se situe vers le bas des paramètres, juste avant la section de mise à jour et après l’éventuelle entrée Redémarrer. Elle est cachée initialement et montrée à réception de la configuration jt808. Le chargement distant des paramètres demande bien jt808 au cloud ; Jt808ConfigActivity sait envoyer les modifications par ce cloud lorsque getNetType() > 0. Cette voie distante existe donc dans la version examinée, mais sa disponibilité pour le compte, la version et la caméra de l’utilisateur n’est pas confirmée. Si le menu n’apparaît pas, ne pas en déduire qu’un accès Wi-Fi est autorisé. Aucune session CarAssist exploitable à disposition.

Aucune écriture 0x8103, commande de redémarrage, déconnexion forcée ou modification applicative déployée. Le processus GPS 263273 est conservé et reçoit encore le Hilux après les lectures. Observateurs temporaires retirés, inspecteur loopback fermé et absence d’écoute sur 9229 contrôlée. Premier essai de lecture bornée sans réponse ; deuxième observation avec resynchronisation des trames réussie. Deux tests Node ciblés du lecteur réussis : requêtes en lecture seule réservées au Hilux, retrait des observateurs et masquage des paramètres non nécessaires. Aucun test applicatif complet. Résultats filtrés conservés dans /home/exad-cam/smartvision-backup-20260929 ; aucune valeur de mot de passe ou jeton dans les comptes rendus.

## 29 septembre 2026 — Commande de lecture des Backups CarAssist identifiée

Demande utilisateur : trouver la commande qui identifie les Backups du Hilux SmartVision. Analyse statique complète du trajet lecture/sérialisation/réponse dans CarAssist Android 3.4.8 déjà acquis : relay:<compteur> avec cmd=settings, get.what incluant jt808 et wakeup=1. La réponse get.jt808 alimente les champs ipbak/portbak/ipbak2/portbak2 du formulaire. Le destinataire peer est le SN CarAssist, distinct de l’IMEI et du terminal JT808. Une version ciblée sur la section jt808 est documentée dans docs/smartvision-server-parameters.md, avec références de code, format du relais et limites. Requête cloud identifiée dans le code, NON envoyée ni validée sur la caméra : session CarAssist autorisée et SN exact non disponibles. Aucun secret embarqué ou compte extrait.

Nouvel essai réel en lecture seule sur le Hilux 9863BV01 : 0x8106 pour 0x0013, 0x0018, 0x0017 et 0x0026, sur la session 2013 existante. Réponse à 08 h 20 min 27 s Kinshasa en 175 ms : principal 62.171.190.15, port 7808 ; 0x0017 vide ; 0x0026 = quatre octets nuls, sans liste de serveurs exploitable. Le champ 0x0026 est documenté dans la famille 2019, sans support démontré sur ce firmware. Ces valeurs ne prouvent pas que les Backups CarAssist sont absents. Aucun équivalent constructeur direct à envoyer sur JT808 n’est établi ; ne pas encapsuler arbitrairement le JSON dans 0x8900.

Validation du lecteur : deux tests Node ciblés réussis, cible Hilux exclusivement, aucune écriture, masquage et nettoyage des observateurs. GPS PID 263273 conservé ; inspecteur temporaire refermé. Aucun déploiement applicatif, changement de serveur caméra, redémarrage, Wi-Fi ou démarche fournisseur. Recherche publique additionnelle sans contrat constructeur confirmant la commande directe. La suppression de tous les Backups du Hilux reste autorisée mais non appliquée ; Main IP 62.171.190.15 et Main Port 7808 à préserver. Le Nissan Patrol n’a reçu aucune commande.
