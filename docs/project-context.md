# Contexte de développement EXADCAM

État au 7 octobre 2026 : sélecteurs carte flottants déployés ; la case Afficher tous les véhicules ouvre les résultats sans recherche, sans plafond de 50 entrées. panneau carte simplifié déployé, trois compteurs Véhicules/En ligne/Hors ligne sur une ligne, Localisées et menu État retirés, Flotte réservé au superadmin. Compteurs utilisables pour filtrer et revenir à tous les véhicules ;  widgets du tableau de bord cliquables, navigation vers la carte filtrée en ligne/hors ligne et libellés/compteurs de véhicules côté client déployés ; menu Dashcams client retiré. Panneau carte adapté au design EXADCAM, actions « Trajets » et « Détails », sélecteur de lecture ×1 à ×64 déployés. Historique global/détaillé et repères départ/arrivée conservés. Les lieux restent des coordonnées GPS exactes. Voir les dernières entrées de project-history.md et google-maps.md pour les contrôles et limites.

> 30 septembre, 16 h 37 Kinshasa : essai distant effectué sur le Hilux
> SmartVision 352538106693487. Écriture 0x8103 du seul Backup standard 0x0017
> vide acceptée (ACK 0) ; relecture : principal 62.171.190.15:7808 inchangé,
> Backup standard vide, déjà vide avant essai. Cela ne prouve pas l’effacement
> des backups propriétaires CarAssist. Huit tests ciblés réussis, inspecteur
> refermé, aucun redémarrage. Pas de passage par Windows/Wi-Fi. Tous les
> backups ne sont pas encore vérifiés supprimés ; voir la dernière entrée de
> docs/smartvision-server-parameters.md. Aucun formulaire d’envoi réintroduit.
> Une nouvelle coupure par le pair et une reconnexion spontanée ont eu lieu.
> Relecture après reconnexion à 16 h 40 min 51 s : principal inchangé, Backup
> standard vide. La stabilité durable n’est pas démontrée.


> 30 septembre, 16 h 17 Kinshasa : connexion directe confirmée au Hilux
> SmartVision 352538106693487. La caméra répond à la lecture serveur : principal
> 62.171.190.15:7808, déjà correct et conservé. Backup standard vide, autres
> destinations CarAssist non vérifiables par cette lecture ; aucune suppression
> de Backup ni commande d’écriture. Deux tests du lecteur réussis, inspecteur
> refermé, PID GPS conservé. Contrôle à 16 h 20 : session de nouveau absente
> (HTTP 409), dernier contact 16 h 19 ; cause non établie, stabilité non résolue.
> Voir docs/smartvision-server-parameters.md.

> 30 septembre : retrait du formulaire SmartVision **déployé** à 16 h 11
> (Kinshasa), faute d’envoi opérationnel. Bouton et modale absents du registre ;
> un brouillon conservé, contenu vérifié identique avant/après. Seules deux
> vues remplacées, cache des vues purgé ; six services actifs, PID inchangés.
> Aucune configuration expédiée aux caméras. Le lot local a passé 48 tests
> ciblés / 357 assertions avant déploiement. Les descriptions du formulaire
> livré le 29 septembre ci-dessous constituent l’historique.

> 29 septembre : **Formulaire SmartVision complet livré et déployé**, suivant le
> choix explicite « formulaire complet avec envoi en attente ». Superadmin :
> Flottes → Dashcams → Configuration SmartVision. Principal, trois serveurs
> secondaires et identifiants ; sauvegarde par caméra, révision/auteur/date,
> protection des onglets périmés. Les valeurs actuelles ne sont pas lues dans
> ce formulaire. Envoi désactivé, aucun traitement automatique, aucune commande
> matérielle. Le Backup du Hilux reste inchangé. ESTON hors périmètre. Tests
> ciblés 48 / 350 assertions et navigateur local/production vérifiés ; aucun
> redémarrage de service. Voir docs/smartvision-server-parameters.md.


> Commande générale CarAssist identifiée : settings/get/what avec 11 sections explicites (dont jt808). Elle lit les réglages ; la visibilité des formulaires reste conditionnelle. Aucune commande cloud exécutée ni suppression de Backup réalisée. Détails dans docs/smartvision-server-parameters.md.

> Lecture des Backups identifiée dans CarAssist : relais settings/get/what=[jt808], champs ipbak/portbak/ipbak2/portbak2. Requête cloud non exécutée faute de session et SN CarAssist. Lecture directe complémentaire du Hilux : 0x0017 vide, 0x0026 quatre octets nuls. Aucune suppression appliquée. Voir docs/smartvision-server-parameters.md.

> 29 septembre : Véhicule 2 est désormais Nissan Patrol 1387AA10 (confirmé en production). Retrait du Backup du Hilux SmartVision 9863BV01 autorisé mais pas encore appliqué : le Backup CarAssist n’est pas identifié dans les paramètres JT808 renvoyés. Principal 62.171.190.15:7808 vérifié. Accès Windows indisponible ; utilisateur sans accès au formulaire distant. Le code CarAssist prévoit une entrée conditionnelle « JT808/1078 config » en bas des paramètres, avant les mises à jour, et son envoi cloud ; disponibilité réelle non confirmée. Voir la dernière entrée du journal.

> 28 septembre : module Rapports livré : Synthèse de flotte, Trajets et arrêts,
> Utilisation et Sécurité, filtres, modèles personnels, comparaison de périodes,
> datatable cinq lignes, export PDF/XLSX et contexte carte/enregistrements.
> Calcul asynchrone sur les relevés existants ; distances estimées, confirmation
> minimale des trajets, périodes manquantes/incertaines explicites. Résultats
> privés 24 h, aucun changement des caméras. Voir [Rapports](reports.md).


> Préférence utilisateur : toujours proposer un bouton **Restaurer par défaut**
> dans les écrans de personnalisation. Bouton permanent livré pour les deux rôles ;
> aperçu puis Enregistrer pour appliquer. L’admin client peut aussi renommer sa
> propre flotte ; le retour au logo par défaut conserve le nom enregistré.
> Menu Enregistrements désormais placé immédiatement après Carte.


> 28 septembre : Personnalisation livrée avec deux périmètres. Superadmin :
> identité, logos/favicon, couleurs, support et fond Google Maps. Admin client :
> nom et logo de sa flotte ; EXADCAM / VIDÉO & GPS conservés à côté.
> Images privées, droits serveur et isolation des flottes ; aperçus et restauration.
> Voir [Personnalisation](customization.md). Aucun redémarrage des listeners.


> Monitoring : boutons Pause/Rafraîchir retirés à la demande de l’utilisateur ;
> actualisation automatique 5 s conservée. Stockage affiché en Go/To décimaux,
> libellé Volume EXADCAM. Le serveur expose un seul disque virtuel de 1 200 Gio
> (1,288 To bruts), volume applicatif 1,247 To. Les 600 Go de base + extension
> 1,2 To annoncés ne sont pas visibles comme un total de 1,8 To ; affectation
> de l’extension à vérifier côté hébergeur. Aucun changement de partition.

> 28 septembre : menu Monitoring livré après Logs serveur, superadmin uniquement.
> Mesures réelles CPU, RAM, disque, charge, réseau et système ; collecte 5 s,
> historique récent jusqu’à 15 min et présentation responsive.
> Collecteur indépendant ; aucun redémarrage des quatre listeners des caméras.
> Voir [Monitoring serveur](server-monitoring.md).

> 28 septembre : menu Logs serveur livré au superadmin uniquement, sans console.
> Journaux réels GPS TCP, vidéo, audio, enregistrements et Laravel ; filtres,
> 100/300/600/1000 lignes, rafraîchissement 5 s, Pause/Reprendre. Collecteur
> dédié en lecture seule, aucun redémarrage des listeners des caméras.
> Voir [Logs serveur](server-logs.md) pour les limites et contrôles.

> Enregistrements : tableau harmonisé avec Véhicules/Dashcams, recherche dans
> tous les résultats, tri des colonnes, tailles 5/10/25/50 (5 par défaut),
> pagination numérotée et compteur. Déployé le 28 septembre, testé sur les
> 634 entrées réelles de Véhicule 2 ; aucun redémarrage des listeners.

> 28 septembre : présentation Enregistrements harmonisée avec EXADCAM.
> Champs et boutons compacts ; recherche des véhicules en superposition, sans
> déplacement des filtres. Vérifié sur ordinateur, tablette et mobile.
> Correctif CSS/Blade déployé sans redémarrage des services des caméras.

> 28 septembre : menu Enregistrements SD livré pour ESTON et SmartVision,
> recherche par véhicule/date/canal, dix résultats par page, relecture et MP4.
> Essais matériels réussis sur Suzuki Horly ESTON et Véhicule 2 SmartVision.
> Accès limité à la flotte et à l’affectation courante ; fichiers temporaires 6 h,
> extraits limités à 30 min/512 Mio. Voir [Enregistrements](recordings.md).
> Le service GPS a été rechargé pour ce lot : la session de 64 h mentionnée
> ci-dessous est une observation antérieure, pas la durée de la nouvelle session.

> Nomenclature corrigée et déployée le 28 septembre, données existantes migrées :
> JK114 → ESTON ES500-603 JK114 ; ES500-603 → 4G SmartVision JT808/1078.
> Les anciennes clés subsistent uniquement dans le contrat interne des profils
> des listeners, afin de préserver GPS, vidéo et interphone. Quatre fiches
> corrigées sans changement de leurs réglages ni redémarrage des listeners.
> Le changement de Backup du Hilux est reporté par l’utilisateur.


> État vérifié le 28 septembre : Véhicule 2 conserve la même session GPS depuis
> environ 64 heures, sans fermeture enregistrée et avec un contact reçu récemment.
> Configuration de référence pour cet appareil : EXADCAM seul, Backup vide.
> Le Hilux conserve son Backup selon l’utilisateur ; il est en ligne au contrôle,
> mais présente encore des expirations réseau et des remplacements de session.
> Un essai du Hilux sans Backup reste à effectuer. La causalité du Backup seul
> n’est pas démontrée : correctifs serveur également actifs depuis vendredi.
> Les anciens contrôles ci-dessous sont historiques ; voir project-history.md.


> Nouvelle configuration déclarée par l'utilisateur pour Véhicule 2 :
> serveur principal EXADCAM conservé, IP et port Backup retirés. CarAssist
> reste accessible à distance selon l'utilisateur ; session EXADCAM et contact
> récent confirmés. Conserver ce nouveau contexte, sans restauration automatique
> de l'ancien Backup. La cause des coupures et la stabilité durable restent
> à établir ; ne pas attribuer la durée de session au changement sans son heure.

> Dernier contrôle du lot : Véhicule 2 connecté ; le Hilux a encore fermé
> sa liaison puis reste sans session GPS. Ne pas déclarer la stabilité résolue.
> Les observations passives sont terminées. Voir la fin de project-history.md.

> Déploiement demandé par « deploie » : délai initial et limite d'authentification
> de 60 secondes désormais ACTIFS après redémarrage du service GPS.
> Contrôle de santé réussi ; vidéo/audio actifs avec leurs processus conservés.
> Véhicule 2 reconnecté ; Hilux toujours sans session au contrôle suivant.
> Observateur différé arrêté, aucune activation de ce lot encore en attente.
> La stabilité durable reste à confirmer. Voir la dernière entrée du journal.

> Maintien SmartVision, 25 septembre : correctifs GPS/ACK et nettoyage vidéo
> désormais ACTIFS après activation dans leurs fenêtres naturellement vides.
> Aucune session établie n'a été coupée pour charger ces correctifs.
> Politique TCP intégrée au GPS : sonde après 60 s de silence, intervalle 30 s,
> 10 essais, sans présence fictive ni commande caméra périodique.
> Le pont temporaire TCP s'est arrêté avec l'ancien processus GPS.
> Des fermetures distantes et retours spontanés ont été observés avant cette
> activation. La stabilité complète n'est PAS démontrée ; vérifier les nouvelles
> sessions et leur durée. Détails dans la dernière entrée de project-history.md.

> Consigne utilisateur : FX-4PVK est UNIQUEMENT un indice pour la recherche
> Internet. Ne pas connecter le PC au Wi-Fi de la caméra et ne pas demander
> cette connexion. Aucun accès Wi-Fi effectué ; sonde locale annulée/désactivée.
> Recherche : CarAssist et Jt808service sont distincts ; l'adresse Backup de la
> capture apparaît dans un manuel public. Basculement effectif et correction
> de reconnexion NON prouvés. Voir la dernière entrée du journal.

> Priorité confirmée : fiabiliser la liaison directe SmartVision → EXADCAM.
> Aucune démarche fournisseur demandée ou envoyée. Le manuel T2 est confirmé.
> Correctif des attentes de présence/autorisation désormais ACTIF.
> La stabilité et le retour automatique durable restent à confirmer.
> Voir les dernières entrées de project-history.md ; les indications de réveil
> ci-dessous sont historiques.

> Identification corrigée : Véhicule 2 et Hilux 9863BV01 sont des SmartVision /
> CarAssist. L'utilisateur confirme le manuel SmartVision T2 comme référence.
> Les anciens diagnostics « ES500 » portent sur ces appareils. Ne plus demander
> de confirmation du manuel. La compatibilité d'un nouveau firmware et l'API
> de réveil restent à vérifier. Voir [identification](smartvision-identification.md).


Décisions confirmées au 25 septembre 2026.

Lot GPS précédent : les erreurs temporaires du service web ne ferment plus
les sessions déjà authentifiées ; les vrais accusés de réception actualisent
aussi la présence. Une cause de coupure côté EXADCAM est identifiée et reproduite
(503 pendant une indisponibilité web). Déploiement 2026-09-25T12:03:47.300138+00:00,
22 tests GPS/protocole Linux réussis ; vidéo/audio inchangés. Les autres
fermetures ES500 et le réveil cloud restent distincts et non résolus par ce lot.
Voir docs/es500-auto-return.md et la dernière entrée du journal.

Complément demandé par « deploie tout » : les trois documents de suivi et le
nouveau test plein écran sont synchronisés sur le serveur EXADCAM ; les six
fichiers applicatifs sont vérifiés identiques. Huit tests ciblés passent sur
Linux. Aucun redémarrage des services caméra. Voir la dernière entrée du journal.

Dernier lot interface : carte responsive et bouton « 2 écrans », déployé le
25 septembre à 11:17:48 UTC. Plein écran des deux lecteurs sans recréer leurs
sessions : côte à côte en paysage, superposés en portrait, repli plein navigateur.
Hauteur sous la barre supérieure et largeur réellement disponible prises en
compte. 74 tests JS et 17 tests PHP ciblés / 126 assertions réussis ; six tailles
de navigateur vérifiées avec des flux de test. Sauvegarde map-layout-20260925-111748.
Services GPS/vidéo/audio inchangés. Voir docs/google-maps.md et le journal.

Attention : l’utilisateur a signalé une nouvelle panne de Parler ES500 après
le lot audio ci-dessous. Son diagnostic a été interrompu par la demande CSS ;
il reste ouvert. Le lot d'interface ne modifie pas l'audio. Les anciennes
confirmations de restitution et de volume restent des observations datées.


Dernier ajustement audio : JK114 à gain 2 (+6 dB), ES500 à gain 4 (+12 dB),
limiteur conservé. Déployé le 24 septembre 2026 à 14:05:06 UTC ; douze tests
Linux réussis, dont amplification AAC réelle. Audio PID 167293 ; GPS et vidéo
inchangés. Relancer Parler pour appliquer. Voix sur Véhicule 2 confirmée par
l'utilisateur ; appréciation des niveaux après amplification encore attendue.
Voir la dernière entrée du journal. Les étapes précédentes suivent.


Dernier lot audio : 24 septembre 2026 à 13:54 UTC, talk-direction-20260924.
Parler envoie uniquement le microphone navigateur vers la caméra ; Écouter
restitue le son de la caméra dans le navigateur. Niveau de microphone visible,
gain ES500 porté à +12 dB avec limiteur. L'état d'envoi attend le premier paquet sortant.
Véhicule 2 a repris l'audio : échange réel journalisé à 13:41 UTC, puis sonde
silencieuse à 13:55:39 UTC avec envoi confirmé à 0,842 seconde. Zéro son de
caméra renvoyé au navigateur en mode Parler. L'utilisateur confirme ensuite
que sa voix sort bien de Véhicule 2, mais avec un niveau bas. Gain porté de
2 à 4 à sa demande ; appréciation du nouveau niveau encore à confirmer.
66 tests JS, 17 PHP ciblés / 126 assertions, 11 tests Linux/FFmpeg réussis.
GPS 156157 et vidéo 165314 conservés. Aucun réglage caméra
modifié. Voir la dernière entrée du journal et docs/live-audio.md.
Les observations antérieures d'absence de connexion ci-dessous restent datées.


Dernier lot client/audio : voir docs/project-history.md, entrée « Droits clients
et interphone ES500 ». Création de véhicule limitée au superadmin côté serveur
et interface ; clients sans nom/modèle/IMEI du matériel, y compris alertes.
Popup client : Contact ACC Allumé/Éteint selon le dernier relevé.
Interphone ES500 : réservation des deux canaux, attente initiale séparée de
l’inactivité, gain micro +6 dB avec limiteur. Transport validé par silence sur
l’ES500 Hilux 9863BV01 ; l’audibilité réelle nécessite un contrôle humain.
Véhicule 2 : CarAssist Parler confirmé fonctionnel, mais EXADCAM ne reçoit pas
de connexion audio après acceptation de la commande, même CarAssist fermé.
Ne pas le déclarer réparé sur la seule base des tests logiciels ou de l’autre
ES500. Le résultat de l’ultime transition est détaillé dans le journal.
GPS conservé PID 156157, aucun réglage caméra modifié, aucun cache métier vidé.
Les observations plus anciennes ci-dessous restent datées.


Véhicule 2 EST REVENU AUTONOMEMENT : confirmation utilisateur sans action, et
authentification réelle le 24 septembre à 11:59:14.198 UTC (12:59:14 Kinshasa).
Cela survient 29 min 56,996 s après la coupure de test à 11:29:17.202 UTC.
Session et nouvelles positions reçues aux contrôles 12:03:21, 12:05:06 et
12:05:37 UTC ; dernier contact/GPS 12:05:37 au contrôle final. Aucune nouvelle
fermeture de sa session dans la fenêtre observée. Ne plus le déclarer absent.

Relecture des paramètres le 24 septembre à 12:03:55 UTC : les valeurs TCP
initiales sont DÉJÀ rétablies dans la caméra, timeout 10 s/retransmissions 0.
La sonde gardée de restauration n'a rien écrit, puisque l'état d'essai 30 s/3
n'était plus présent. Aucun rollback supplémentaire en attente. La cause de
ce retour des paramètres n'est pas établie ; ne pas attribuer la reconnexion
à notre lecture ni aux paramètres expérimentaux. Pas de nouvelle coupure,
aucun service redémarré, aucun média/micro ouvert ; inspecteur fermé.

Diagnostic révisé : retour autonome désormais confirmé dans ce cas, mais long.
Un mécanisme de temporisation du firmware est une piste, pas une cause démontrée.
Ni délai garanti de 30 minutes, ni réveil cloud/retour rapide à la demande livré.
Conserver la consigne de ne plus provoquer de coupures. Aucun monitoring en fond.
Voir la dernière entrée du journal, docs/es500-auto-return.md et les preuves
workspace analysis/es500-autonomous-return-20260924/. Aucun nouveau test automatisé
dans ce contrôle opérationnel ; les résultats des lots précédents restent datés.

Dernier lot livré : live-pipeline-20260924, 12:32:24 UTC le 24 septembre.
Fragments HLS indépendants de 1 seconde pour JK114/ES500, avec recompression
libx264 aux dimensions d'origine. Réserve initiale 4 s annoncée par le serveur,
fallback ancien/copie 8 s, reprise après épuisement 15 s. Commande de départ
sans ACK : demande conservée dans la limite d'inactivité 30 s, sans envoyer
Stop prématurément ; contrôle des identités et des droits conservé.
Mesure ES500 fiche 4 : réserve prête vers 12,823 s au lieu de 19,567 s ; caméra
envoie seulement vers 8 s. Ce n'est pas une garantie de lecture immédiate.
Véhicule 2 effectivement lu sur deux canaux avec reprise après interruptions.
JK114 : envoi intermittent / commandes sans réponse pendant le test, lecture
continue non validée. Pas de cause matérielle exacte attribuée.
33 tests serveur réussis, puis 8 vidéo ciblés sur la dernière correction et
64 tests JS. Seul exadcam-video relancé, PID 161604 ; GPS 156157 et audio
151193 conservés. Aucun réglage caméra, aucun micro/écoute démarré par l'agent.
Sauvegarde finale /var/backups/exadcam-live-pipeline-20260924-123223 ; état avant
le lot dans /var/backups/exadcam-live-pipeline-20260924-122401.
Voir docs/live-stream-startup.md et la dernière entrée du journal.

Lot GPS précédent : TCP keepalive ES500 différé à cinq minutes, déployé le
24 septembre à 10:25:49 UTC. Les observations courtes des essais 11:16–11:36
et le contrôle 11:54 n'avaient pas montré de retour ; elles sont complétées par
le retour autonome de 11:59. Backup original 119.23.78.106:6608 restauré par
l'utilisateur avant la dernière coupure. Firmware des deux ES500 :
PL_TW1-V1.1_4G_NCS_EN_2.2.31_2.3.7 (FX/T1).

Défaut de présence distinct encore identifié sur fiche 4 : réponse 0x1003 réelle
à 11:35:49 mais last_seen_at resté 11:31:12. Ne pas confondre socket existant,
réponse authentifiée, contact en cache et retour d'une connexion absente.
Réveil cloud CarAssist toujours non intégré, accréditation d'intégration ou
protocole documenté manquant. Brouillon fournisseur conservé, non envoyé.

Correctif précédent : conservation de la connexion ES500 authentifiée pendant le silence
(suppression du délai applicatif de trois minutes), déployé le 2026-09-24T08:55:12.762848+00:00.
La JK114 s’éteint normalement avec le moteur sur l’installation de l’utilisateur.
Pour l’ES500 en veille prolongée, ouvrir CarAssist restaure aussi EXADCAM selon
l’utilisateur. Requête de réveil côté application Android identifiée : relay,
preview, wakeup=1, via le cloud CarAssist ; livekeep toutes les dix secondes.
L'accès d'intégration et le transport cloud vers caméra restent à confirmer.
Réveil automatique EXADCAM non intégré. Voir docs/carassist-wake.md.
Ne pas assimiler absence de contact, veille et coupure réseau. Aucun statut en
ligne fabriqué. Essai accompagné du 24 septembre : l'utilisateur ouvre le direct
CarAssist de Véhicule 2, mais aucun retour EXADCAM observé pendant les cinq
minutes suivantes (20 relevés). L'autre ES500 transmet normalement. Le statut
de veille exact n'est pas mesuré ; réveil EXADCAM non validé. Voir le journal.

Véhicule 2 reste immobilisé pour les prochains temps, selon l’utilisateur.
Aucun déplacement requis pour tester la connexion ou la vidéo ; distinguer
une position inchangée de l’absence de nouveaux messages et de session.

Lot précédent déployé à 08:18:41 UTC : trace SVG contenue dans le marqueur,
paquets microphone G.711 dimensionnés selon les capacités ES500, distinction
des refus vidéo matériels et suppression du Stop envoyé après un refus explicite.
Sauvegarde /var/backups/exadcam-camera-fixes-20260924-081841.
L’utilisateur confirme désormais le retour haut-parleur sur JK114 et ES500.
JK114 Hilux 0210BW01 : refus vidéo puis fermeture distante à 07:59:35 UTC ;
toujours sans session EXADCAM au contrôle après déploiement. Deux serveurs
simultanés confirmés par l’utilisateur, pas un serveur de secours.
Ne pas présenter la vidéo de cette JK114 comme rétablie. Voir live-audio.md,
google-maps.md et device-commissioning.md pour les preuves et limites.


La carte conserve le véhicule sélectionné au centre de sa surface visible sur
mobile/tablette, y compris lors des changements de dimensions et du volet vidéo.
Changer d’onglet du navigateur conserve le panneau et la session vidéo ; au retour,
le lecteur reprend ou renouvelle sa session après suspension du navigateur.
Déploiement map-responsive-1 du 23 septembre à 18:03 UTC. Diagnostic ES500 repris
le 24 septembre à partir des précisions sur la veille. Voir le journal.

Correction ES500 du 23 septembre à 17:08 UTC : une rafale TCP valide pouvait
dépasser à tort le tampon JT808 et fermer la connexion. Les limites portent
désormais séparément sur la lecture TCP et la trame incomplète. Les 26 tests
Linux du listener passent et le correctif est déployé, avec sauvegarde.
Véhicule 2 est revenu à 17:20:22 UTC avec GPS et vidéos, puis sa liaison
GPS s’est refermée côté distant à 17:25:14 UTC. Le rétablissement durable
reste non résolu. À 17:38 UTC, un second correctif préserve une vidéo déjà
établie si seule sa liaison GPS tombe ; accès et révocation restent contrôlés.
Voir device-commissioning.md pour les preuves et les limites.
L’utilisateur confirme l’écoute et le retour haut-parleur sur les deux modèles
après le correctif de trames ES500 du 24 septembre.


La flèche cartographique est ancrée par son centre sur la même coordonnée que
l'extrémité de la trace. Les actualisations pendant l'animation conservent les
virages reçus et n'affichent pas la ligne en avance sur le marqueur.
Le son et le microphone sont intégrés aux trois lecteurs. L’écoute partage le
flux vidéo 1 ; l’interphone réserve ce canal, suspend sa vidéo puis la libère à
l’arrêt du micro. Permission audio.talk et arrêt automatique de la capture.
La réception AAC de la JK114 et G.711 de l’ES500 est confirmée avec la vidéo.
L’écoute reprend automatiquement après coupure ; le micro reste arrêté. Les
coupures de source ES500 restent étudiées. Le retour haut-parleur est confirmé
par l’utilisateur sur les deux modèles. L’audio peut devancer l’image
qui conserve sa réserve de quinze secondes. Voir docs/live-audio.md.

Le tableau de bord utilise les données réelles et les alertes enregistrées,
avec périmètre de flotte et permissions. Carte d’aperçu non interactive et deux
canaux vidéo précèdent l’activité GPS. Le centre vidéo redondant est retiré.
Voir la dernière entrée du journal et dashboard-video.md pour les vérifications.

Le journal daté des réalisations et des vérifications est disponible dans
[project-history.md](project-history.md). Le compléter après chaque lot significatif ;
ce document décrit le contexte courant.

## Navigation et suivi cartographique — 22 septembre 2026

Les comptes de flotte accèdent directement aux rubriques Véhicules, Dashcams et
Départements autorisées, sans menu Flottes. Le superadmin conserve le regroupement.
La carte privilégie à l'ouverture un véhicule en ligne en déplacement et active
le suivi de la sélection. Le choix reste stable aux actualisations ; un glissement
manuel suspend le suivi et « Afficher tous les véhicules » conserve la vue globale.
Validation ciblée : 18 tests Laravel / 153 assertions et 11 tests Node réussis.

## Gestion par flotte — 22 septembre 2026

Les admins affectés à une flotte active gèrent ses véhicules, départements,
dashcams et utilisateurs simples. Les comptes clients ne reçoivent aucun IMEI
ni paramètre de communication. Des permissions séparées permettent de déléguer
véhicules, départements, dashcams et vidéos aux utilisateurs. La création matérielle
des dashcams et leur configuration restent au superadmin. Le tableau de bord client
affiche les données de sa flotte. Voir [fleet-administration.md](fleet-administration.md)
pour les règles serveur et les limites de révocation vidéo.

## Fuseau des nouvelles dashcams — 22 septembre 2026

La création via le registre persiste désormais le fuseau GPS de l’installation :
listener.default_gps_timezone_minutes, variable DASHCAM_GPS_TIMEZONE_MINUTES,
60 minutes par défaut pour les horloges UTC+1 observées sur JK114 et ES500-603
à Kinshasa. Les calibrages existants restent inchangés à l’édition. Ce réglage
est celui de l’horloge du terminal, pas du navigateur ni de l’année JT808.
Les autres déploiements doivent l’accorder à leurs terminaux ; un changement
de défaut ne reconfigure pas les appareils et ne corrige pas les anciennes fiches.

Les deux Toyota Hilux VODA FLEET recevaient déjà des positions : le repli UTC+8
créait sept heures de retard et les plaçait avant leurs bornes d’affectation.
Fiches 3 et 4 corrigées à 60, 335 relevés recalés de +7 h après sauvegarde,
avec prédicat borné sur les heures de réception et le décalage observé.
Les positions réapparaissent sur la carte ; les bornes d’accès restent intactes.
44 tests Laravel ciblés / 331 assertions passent. Aucun écouteur redémarré.
Sauvegarde : /var/backups/exadcam-gps-timezone-20260922-153119.
Contrôle matériel complémentaire : JK114 Toyota Hilux reconnectée, 100 nouveaux
relevés post-correction vérifiés avec une seconde d’écart GPS/réception.
Le direct sur cette caméra renvoie un refus de commande ; l’utilisateur indique
EXADCAM en serveur secondaire et ne peut pas arrêter l’autre direct pour un test
isolé. ES500 Toyota Hilux : pas de nouveau contact depuis 15:30:23 UTC malgré
EXADCAM déclaré principal. Diagnostic et limites dans device-commissioning.md.
La cause du refus vidéo et de l’absence de reconnexion ES500 reste à confirmer.

## Image intérieure JK114 — 22 septembre 2026

Correction après capture utilisateur : élargir l’image intérieure, pas le panneau.
Le panneau JK114 retrouve sa taille compacte ; ses deux lecteurs affichent
l’image sur toute la surface 16:9 via object-fit: fill, sans recadrage. Le flux
720 × 576 reste intact côté serveur. ES500-603 reste en contain. Contrôle réel
du Canal 2 et fermeture de la lecture effectués. Sauvegarde :
/var/backups/exadcam-jk-image-20260922-145827. Voir la dernière entrée du journal.

La même adaptation pleine largeur s’applique maintenant à Voir en direct dans
la liste Dashcams, avec le modèle réappliqué à chaque ouverture. JK114 en fill,
ES500-603 en contain ; taille de la modale conservée. Contrôle réel du Canal 2
JK114 et changement de modèle vérifiés. Sauvegarde de ce complément :
/var/backups/exadcam-dashcam-live-image-20260922-151023.

## Historique GPS actuel — 22 septembre 2026

Pagination de 10 relevés par page (remplace les 25 du lot précédent), boutons
numérotés, précédent/suivant et compteur de plage/total. Les plus récents en
premier ; nouvelle date = page 1. Total limité au même périmètre autorisé que
les lignes. Validation : 16 tests ciblés / 133 assertions, Pint, syntaxe JS,
navigateur local et production. Sauvegarde :
/var/backups/exadcam-map4-20260922-143735. Voir la dernière entrée du journal.

## Présentation actuelle de la carte — 22 septembre 2026, révision visuelle

Filtres plus compacts (320 px, compteurs 17 px), symboles de résultats réduits,
fiche ancrée structurée avec voyant de connexion et dernier contact relatif.
La modale de détails est limitée à 800 px : Synthèse à l’ouverture (bandeau,
cartes Véhicule et dashcam / Emplacement), puis Historique GPS par date dans
un onglet distinct. Aucun champ fictif ajouté ; permissions inchangées.

Le volet vidéo remplace la largeur 50/50 du lot précédent par une largeur adaptée
à l’écran, au maximum 470 px sur ordinateur ; les deux zones restent au format
16:9 avec commandes compactes. Lecture à la demande, canaux indépendants.

Contrôles propres à cette révision : 16 tests Laravel ciblés / 111 assertions,
syntaxe JS/PHP, navigateur local SQLite et production, historique paginé et
essai bref du Canal 2 ES500-603 en 960 × 540 puis libération de la lecture.
Les chiffres de suite complète ci-dessous appartiennent au lot précédent.
Déploiement sauvegardé dans `/var/backups/exadcam-map3-20260922-141816`, sans
migration ni redémarrage d’écouteur. Voir l’entrée datée de project-history.md.


## Correction prioritaire — Parcours Carte et vidéo livré le 22 septembre 2026

La carte suit désormais le parcours EXAD Tracking demandé : compteurs avec icônes,
case Afficher tous les véhicules, résultats uniquement lors d'une recherche, puis
sélection du véhicule. Sans sélection ni case cochée, la rubrique Carte n'impose pas
de marqueurs. Flèche orientée d'après les déplacements GPS et trace animée ; carré
à l'arrêt/contact allumé ; P au contact coupé. Marqueurs anciens/hors ligne distincts.

Clic sur un marqueur → fiche avec Historique et détails / Vidéos. La modale affiche
les données actuelles et les relevés GPS par date locale, pagination de 25 points,
avec validation des flottes, des caméras et des bornes d'affectation. IMEI et détails
techniques visibles au superadmin uniquement. La lecture vidéo reste superadmin.

Le volet vidéo partage l'écran avec la carte et propose CH1/CH2 indépendants, chacun
avec Lecture/Arrêter. Aucun flux lancé à l'ouverture. Baux libérés à l'arrêt/fermeture,
y compris démarrage tardif. Deux flux ES500-603 lus simultanément en 960 × 540 en
production ; arrêt indépendant et fermeture confirmés dans les journaux. Pas de
nouveau test physique JK114 ni d'endurance. Cap déduit du GPS ; relecture de trajet
et archives vidéo encore à développer.

Validation actuelle : **179 tests Laravel / 909 assertions et 8 tests JavaScript**
réussis. Navigateur local et production contrôlés. Déploiement sans migration ni
redémarrage des écouteurs, sauvegarde `/var/backups/exadcam-map2-20260922-134311`.
Les descriptions/tests des lots antérieurs ci-dessous sont historiques ; consulter
la dernière entrée de project-history.md et le guide google-maps.md.


## État prioritaire — Carte réelle livrée le 22 septembre 2026

La rubrique Carte et la carte partagée du tableau de bord utilisent les positions
réelles enregistrées par les écouteurs. Les anciennes mentions de carte entièrement
en démonstration ci-dessous sont historiques. Les autres indicateurs/aperçus du
dashboard restent partiellement démonstratifs et explicitement signalés.

Carte inspirée d'EXAD Tracking : liste/recherche des véhicules, filtres flotte,
département/site/région et état, fiche GPS, suivi, courte trace récente, satellite et
plein écran. Actualisation environ toutes les dix secondes ; animation entre relevés
confirmés. Une seule position par véhicule, issue de sa caméra activée la plus récente.
Accès `map.view` et périmètre des flottes contrôlés côté serveur, aucun secret exposé.

Bornes d'affectation ajoutées aux véhicules/dashcams pour exclure les traces d'une
ancienne flotte ou d'un ancien véhicule après réaffectation. Migration appliquée
localement et en production le 22 septembre ; début des bornes existantes en production
à 12:58:33 UTC. Historique brut conservé. Deux véhicules EXAD CARS localisés en
production, tous deux à l'arrêt lors du contrôle. Écouteurs et identités inchangés.

Suite finale actuelle : **175 tests Laravel / 873 assertions** et **3 tests Node du
déplacement**, réussis. Navigateur local et production vérifiés. Sauvegarde :
`/var/backups/exadcam-map-20260922-125833`. Les tests plus anciens mentionnés ensuite
décrivent leurs lots respectifs. Essai routier réel, charge de 300+ appareils,
historique complet/relecture par date et validation mobile détaillée restent à faire.
Voir [google-maps.md](google-maps.md) et la dernière entrée de l'historique.


## Périmètre

- Projet : `D:\App\Codex\exadcam`.
- Référence de méthode : `D:\App\Codex\exad-tracking`, consulté en lecture seule.
- Plateforme indépendante : base, comptes, configuration et infrastructure propres.
- Développement web en premier ; application Flutter Android/iPhone ultérieure.
- Bootstrap local sans CDN ; Tailwind exclu de l'interface et de la compilation.
- Horizon de développement discuté : trois mois, avec plus de 300 dashcams visées
  au lancement. La capacité réelle devra être mesurée avec la charge GPS/vidéo.

## Conventions observées dans EXAD Tracking

| Sujet | Référence observée | Application à EXADCAM |
| --- | --- | --- |
| Rendu web | Pages Blade et fragments dans `resources/views/partials` | Blade, layout commun et fragments réutilisables |
| Bootstrap | `twbs/bootstrap` dans `composer.json`, fichiers sous `public/vendor/bootstrap` | Même mode d'installation et de distribution locale |
| Styles et interactions | `public/css/dashboard.css`, `public/js/dashboard-sidebar.js`, fichiers par module | CSS/JS séparés des vues ; fichiers par module lorsque nécessaire |
| Navigation | Barre latérale et barre supérieure partagées | Navigation de démonstration protégée par authentification |
| Formulaires | Modales Bootstrap, `@csrf`, erreurs `@error`, attributs `data-*` | Réutiliser cette approche pour les opérations métier |
| Traductions | Textes passés à `__()` et fichiers de langue | Prévoir les traductions dès les premiers modules |
| Authentification | Laravel Fortify et vues Blade personnalisées | Fortify installé ; connexion/déconnexion et vues Blade dédiées |

Le projet de référence conserve aussi des fichiers et dépendances Tailwind issus du
squelette Laravel. Cette partie n'est pas reprise, conformément au choix explicite
de Bootstrap uniquement. Le nouveau layout commun évite de répéter l'en-tête HTML
et les imports d'assets sur chaque page.

Les fichiers `resources/js/bootstrap.js` servent à initialiser Axios : leur nom ne
désigne pas la bibliothèque d'interface Bootstrap.

## État livré pour commencer

Bootstrap local, publication reproductible des ressources, page d'accueil Blade,
CSS/JS séparés et pipeline Vite sans Tailwind. L’accueil présente désormais un tableau de bord de démonstration, explicitement
étiqueté : six véhicules fictifs, trois événements simulés, fond Google Maps, filtres
et aperçus caméra sans flux réel. Les données affichées de flotte restent fictives ; les comptes, les sessions et les historiques de connexion sont persistés. Les tables structurelles d’abonnements et de flottes sont en place, sans module métier connecté au tableau de bord.
Le DashboardPreviewController sera remplacé par les services métier authentifiés
lors de l’intégration réelle.

## Ordre de travail

1. Authentification web et autorisations ; espace administrateur et navigation.
2. Gestion des clients/flottes, véhicules, utilisateurs et dashcams.
3. Réception GPS depuis le service Node.js et affichage des positions/historiques.
4. Commandes vidéo, réception des flux et lecture en direct dans le navigateur.
5. Archives, extraits, alertes et rapports après validation sur l'appareil.
6. Application mobile une fois les parcours web et les interfaces serveur stabilisés.

La limitation des accès aux données d'une flotte doit être appliquée côté serveur
sur les listes, les détails et les actions, puis vérifiée avec plusieurs comptes.

## Intégration dashcam : confirmé et à vérifier

Le matériel de test est une ESTON ES500-603 / AT603D. Le paramétrage observé utilise
JT808-2019. Une vidéo distante de deux canaux a été obtenue sur GPS51 ; cela ne
valide pas encore notre propre réception des messages et des flux.

Les services Node.js GPS/commandes et vidéo JT1078 sont maintenant installés.
Le direct H.264 est converti en HLS avec FFmpeg, puis lu par hls.js local.
Laravel contrôle le registre des équipements et les demandes de lecture.
La chaîne a été vérifiée avec des données synthétiques ; les essais matériels
restent à effectuer avec le nouvel IMEI que l'utilisateur fournira.

Restent à valider sur notre serveur : trames reçues, transport vidéo, numérotation
des canaux, audio, relecture de la carte mémoire et extensions d'alertes ADAS/DMS.
Ne pas présenter ces fonctions comme opérationnelles avant leurs essais.

Aucun accès au cloud GPS51 ni changement du configurateur matériel n'est nécessaire
pour préparer le socle web. Les secrets sont exclus de cette documentation.

## Connexion livrée

Page Blade responsive avec Bootstrap local et Laravel Fortify. La validation est
dynamique, avec messages sous les champs et connexion asynchrone en français et
en anglais. Mémorisation de session, limitation des échecs et déconnexion dans le
menu du compte restent prises en charge côté serveur. Voir [localization.md](localization.md).
Le tableau de bord exige une authentification. Création explicite des comptes
avec `php artisan app:create-user --role=user`. Aucun compte par défaut ;
la gestion des utilisateurs est livrée ; la récupération du mot de passe par e-mail reste à implémenter.

## Direction visuelle corporate

Le login utilise une composition photographique, une palette bleu nuit,
des angles discrets et une typographie sobre. Les visuels de flotte et de dashcam
sont générés par IA, optimisés en WebP et servis localement, sans CDN.
La dashcam occupe le premier plan sur ordinateur et sur mobile, avec un cadrage adapté.
Le logo officiel EXAD est réutilisé depuis les PNG fournis, sans redessiner les lettres.
Deux présentations SVG appliquent les tons bleu nuit et clair tout en conservant la transparence.
Les visuels initiaux sont documentés dans `docs/design-assets.md`.
La version actuelle et les logos sont documentés dans `docs/exad-identity.md`.


## Base et autorisations — 16 septembre 2026

La connexion utilise désormais MySQL/MariaDB, base locale `exadcam`. La structure
de `users` (21 colonnes), les rôles et les droits JSON reprennent EXAD Tracking,
avec les tables `subscriptions`, `fleets`, `fleet_user` et `user_login_histories`.
Un compte Superadmin a été provisionné à la demande de l’utilisateur. Aucun secret
ni compte n’est ajouté aux seeders. La base de référence reste indépendante.

Voir [database-and-access.md](database-and-access.md) pour le comparatif réel,
les garanties d’accès, le provisionnement et les modules différés.

## Navigation corporate — 16 septembre 2026

La barre supérieure reprend la référence visuelle fournie : titre, fil d’Ariane,
plein écran, préférences, notifications de démonstration, langue et compte.
Le menu latéral se masque sur ordinateur et s’ouvre en offcanvas sur mobile.
Les styles/interactions propres à la barre sont dans `public/css/topbar.css`
et `public/js/topbar.js`. Le composant de langues partage la même route et les
mêmes protections que la connexion. Le badge représente trois événements fictifs.
La recherche reste accessible dans la liste des véhicules et par le raccourci `/`.

Après retour utilisateur, la navbar utilise une hauteur compacte de 76 px sur
ordinateur, des boutons de 38 px et un titre de 20 px. Sur mobile, les contrôles
restent à 42 px avec une disposition sur deux lignes moins espacées.

Le menu latéral utilise une largeur de 232 px sur ordinateur et un thème bleu nuit,
avec un état actif bleu discret. Ses styles sont isolés dans `public/css/sidebar.css`.
Le logo et l’aide restent visibles ; seule la liste défile si nécessaire. Le bloc
promotionnel a été remplacé par un accès au guide. Les libellés du menu sont traduits
en français et en anglais, et l’ouverture mobile reste gérée par Bootstrap.

Le bloc « Espace EXAD / Démonstration » a été retiré à la demande de l’utilisateur.
La rubrique Supervision suit directement le logo dans le menu.

La navigation reprend désormais l’arborescence approuvée : Tableau de bord,
Utilisateurs, Flottes (Flottes, Véhicules, Dashcams, Départements), Carte, Centre
vidéo, Alertes et Rapports. Le groupe Flottes est replié à chaque chargement, même sur une sous-rubrique.
Il se déplie au clic ou lors de la navigation vers une sous-rubrique. Les nouvelles rubriques sans module métier ouvrent une page
« En préparation » ; Carte affiche Google Maps avec des positions fictives à Kinshasa.
La gestion des utilisateurs et le registre initial des dashcams utilisent les données réelles ; les autres CRUD
et les rapports réels restent à développer. Les icônes sont servies localement.

## Dashboard et graphiques — 16 septembre 2026

Les boutons et actions du dashboard utilisent désormais le bleu nuit `#203d65`
du login. Les styles spécifiques sont dans `public/css/dashboard.css`.
ApexCharts est repris d’EXAD Tracking : package Laravel `akaunting/laravel-apexcharts`
4.0.0, script JavaScript 3.35.1 livré par le package, servi localement. La publication
des assets Composer inclut Bootstrap et ApexCharts.
Le dashboard présente une courbe d’activité 24 h/7 jours et un anneau d’état des
dashcams, tous deux alimentés par la démonstration. Les séries sont préparées par
DashboardPreviewController, traduites et encodées avec `Js::encode`, puis rendues
par `public/js/dashboard-charts.js`. Les données métier réelles ne sont pas encore
connectées. Voir [dashboard-charts.md](dashboard-charts.md).

## Carte Google Maps — 16 septembre 2026

La clé navigateur fournie est configurée dans les `.env` local et de production, exclus de
Git. Le contrôleur transmet la configuration nécessaire à la page authentifiée ;
une clé Maps JavaScript est visible côté navigateur et doit être restreinte aux
sites autorisés et à cette API dans Google Cloud.
Une seule carte est créée lorsqu’elle devient visible, puis réutilisée entre les
vues Tableau de bord et Carte. Les marqueurs sont sélectionnables, avec commandes
de zoom, cadrage de la flotte et accès à l’aperçu caméra du véhicule choisi.
Le fond cartographique est réel, les six positions et leurs statuts sont fictifs.
Le service Node.js n’alimente pas encore la carte. Aucun géocodage ni calcul
d’itinéraire n’est appelé. Bootstrap, ApexCharts, polices et icônes restent locaux ;
l’API cartographique et ses ressources sont chargées depuis Google.
Le domaine `exadcam.app` pointe vers le serveur et répond en HTTPS ; les restrictions de clé Google Maps restent à adapter et vérifier. Le Map ID de test doit être remplacé
avant la production. Voir [google-maps.md](google-maps.md).

## Gestion des utilisateurs — 16 septembre 2026

La rubrique Utilisateurs reprend les fonctions d’EXAD Tracking : liste avec
recherche/tri/pagination, création, modification, suppression confirmée, permissions,
affectation à une flotte et historique des connexions. Données réelles EXADCAM,
formulaires dynamiques FR/EN, Bootstrap local et thème bleu conservés.
Le superadmin gère les comptes non superadmin. Un admin ne gère que les utilisateurs
simples de sa flotte ; aucun accès aux comptes d’une autre flotte ou non affectés.
Les comptes superadmin sont protégés. Le menu n’est pas exposé aux utilisateurs simples.
Une flotte active est nécessaire avant de créer un utilisateur. Aucune n’est
encore présente dans la base réelle ; le prochain module Flottes devra l’offrir.
Voir [user-management.md](user-management.md) pour les règles, routes et vérifications.


## Accès au serveur — 21 septembre 2026

- Serveur communiqué : `62.171.190.15`, SSH sur le port 22.
- Ubuntu 24.04.5 LTS, hôte `vmi3599783` ; bannière d’accueil Contabo observée.
- Domaine : `exadcam.app`, DNS et HTTPS vérifiés lors de la préparation serveur du 21 septembre 2026.
- Compte Linux d’administration créé : `exad-cam`, répertoire `/home/exad-cam`,
  membre de `sudo`. Connexion SSH et élévation sudo avec mot de passe vérifiées.
- Utiliser ce compte pour les prochaines connexions : `ssh exad-cam@62.171.190.15`.
  Les secrets restent hors documentation et hors dépôt. Aucune clé SSH ajoutée.
- Cette étape prépare l’accès système ; elle ne déploie pas l’application, les
  services GPS/vidéo, les sauvegardes ni une réplication. L’accès root reste inchangé.


## Infrastructure web et écoute — 21 septembre 2026

La plateforme Laravel est déployée dans `/var/www/exadcam` et disponible sur
`https://exadcam.app`. Apache, MariaDB, PHP-FPM et le HTTPS sont actifs. Composer
a installé 99 dépendances de production depuis le lockfile. Configuration
production séparée, débogage désactivé, clé applicative propre et sessions sécurisées.
Les migrations sont exécutées ; le compte superadmin demandé a été provisionné.
Aucun export de la base métier locale. Les premières dashcams réelles ont ensuite été inscrites le 22 septembre, voir ci-dessous.

Node.js 24.21.0 LTS et FFmpeg 6.1.1 sont installés. Deux services systemd,
`exadcam-gps` et `exadcam-video`, démarrent automatiquement sous un compte dédié.
Ports publics TCP : 7808 pour JT808 2013/2019, 1078 pour JT1078. La commande 0x9101
transmet la destination vidéo au matériel : le configurateur utilise le port
principal 7808. Les API de coordination 3001/3002 et Laravel 8081 restent locales.

La rubrique Flottes → Dashcams permet au superadmin de créer le registre autorisé,
d'activer/désactiver un équipement et de demander un direct H.264. Seuls les
équipements inscrits et actifs sont admis. La préparation du 21 septembre ne
contenait aucun IMEI ; les inscriptions et essais du 22 septembre sont décrits ci-dessous.

Les GPS reçus sont persistés mais ne remplacent pas encore les données de
démonstration de la carte/dashboard. La rubrique Centre vidéo générale reste
un aperçu ; le parcours de test réel est dans Dashcams. Audio, H.265, archives
sur carte/cloud, alertes ADAS/DMS et droits vidéo par flotte restent à développer.
La limite initiale de 8 flux simultanés est une protection, pas une capacité mesurée.

phpMyAdmin reste disponible à `https://exadcam.app/phpmyadmin/`. Le fond Google
Maps a été vu en production, sans audit des restrictions de clé. Les sauvegardes
externes et la supervision métier restent à installer. Les sauvegardes réalisées
pour ce déploiement sont locales au VPS, sans réplication.
Voir [server-infrastructure.md](server-infrastructure.md),
[listener-server.md](listener-server.md) et [database-and-access.md](database-and-access.md).

## Deux équipements et première vidéo réelle — 22 septembre 2026

JK114 et ES500-603 inscrites/activées en production avec les deux IMEI fournis.
JK114 : connexion JT808 2019 authentifiée, positions réelles reçues et deux canaux
H.264 720 × 576 décodés et servis en HLS lors des tests serveur. L'utilisateur
a ensuite confirmé voir le direct dans son navigateur. La correspondance
route/habitacle reste à identifier explicitement.

Le serveur supporte désormais les identifiants vidéo courts et étendus, sélectionnés
par correspondance exacte avec un flux autorisé. La JK114 utilise 20 chiffres.
Fuseau GPS configurable par équipement : JK114 observée en UTC+1, premières
positions corrigées après sauvegarde ; aucun changement global imposé à l'ES500-603.

ES500-603 : après correction de son alias JT808 court (053810725721), puis
redémarrage confirmé par l'utilisateur, authentification réelle reçue à 09:52:08 UTC.
Une reconnexion automatique a été observée à 09:55:21 UTC. Le listener utilise
son décodeur JT808 ancien, nommé « 2013 » ; cette observation ne détermine pas
à elle seule l'édition exacte du firmware. Positions 0x0200 et battements 0x0002
reçus, fix GPS valide. Les deux appareils sont connectés au contrôle du 22 septembre
vers 10:00 UTC. Le fuseau propre à l'ES500-603 a été réglé sur UTC+1 après
observation des horodatages réels ; 42 positions initiales corrigées de sept heures
après sauvegarde. Les nouvelles dates GPS sont cohérentes.

La connexion GPS de l'ES500-603 a été validée. Son flux JT1078 utilise aussi
l'identifiant 053810725721, désormais corrigé dans le registre vidéo. Le H.264
réel 960 × 540 nécessitait une génération explicite des horodatages. L'option
normalize_video_timestamps est livrée et activée pour cette fiche uniquement,
avec cadence configurée à 15 images/s, copie du flux compressé et refus des images B.
La JK114 conserve le mode natif. Les dix tests Node et neuf tests Laravel ciblés
(48 assertions) du correctif passent ; décodage de l'échantillon ES500 corrigé réussi.

L'ES500-603 s'est déconnectée à 10:14:22 UTC, avant installation du correctif à
10:17 UTC. L'utilisateur signale la mise en veille : essai final de lecture en direct
en attente de réveil/reconnexion. La JK114 reste en GPS mais a refusé la commande
vidéo lors du contrôle de ce lot ; sa validation vidéo antérieure reste historique.
MariaDB local est arrêté/injoignable, migration locale de compatibilité en attente ;
migration appliquée en production. Les autres modules restent dans l'état décrit
précédemment. Voir [device-commissioning.md](device-commissioning.md).

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


## État confirmé après redémarrage — 22 septembre 2026

Le direct ES500-603 est confirmé par l'utilisateur après redémarrage, puis
observé dans le navigateur en 960 × 540. Reconnexion à 10:30 UTC toujours au format
JT808 ancien (« 2013 ») ; aucune conversion vers 2019 nécessaire. Canal 2 observé
en lecture. Une coupure liée au limiteur de messages à 10:32:25 a été suivie d'une
reconnexion automatique ; la lecture a été relancée. La stabilité longue durée
et la cause précise de cette rafale restent à suivre, aucun seuil n'a été modifié.

La JK114 conserve sa validation vidéo antérieure ; lors du dernier essai du lot
précédent elle restait GPS active mais refusait la commande de direct.
MariaDB local est arrêté/injoignable, migration locale de compatibilité en attente ;
migration appliquée en production. Voir device-commissioning.md pour les essais
et limites. La connexion Internet via le hotspot ES500 est confirmée par l'utilisateur.


Contrôle complémentaire : le canal 2 est resté ouvert de 10:34:12 à 10:36:16 UTC
(plus de deux minutes), sans nouvelle déconnexion GPS ni erreur vidéo dans les
journaux ; arrêt par fermeture de lecture, puis nouvelles demandes CH1 et CH2.
La rafale ayant provoqué la coupure précédente n'a pas été reproduite dans cette
fenêtre. Aucun seuil modifié. Les résultats ne constituent pas un test d'endurance.


## Mise à jour prioritaire — Registre et affectations livrés le 22 septembre 2026

La gestion des dashcams utilise désormais des modales, un choix de modèle préalable,
TCP / JT808 2013 fixe pour ES500-603 avec identifiant CarAssist explicite à 12 chiffres,
et le choix 2013/2019 pour JK114. Registres Flottes/Véhicules de base opérationnels,
relation dashcam → véhicule → flotte, tableaux avec recherche/tri/pagination.
Seul le superadmin gère actuellement ces modules. Le changement d’année dans la fiche
ne modifie pas le firmware ; les trames continuent de déterminer `last_protocol`.

Production : flotte EXAD CARS, JK114 affectée à Véhicule test 1, ES500-603 affectée
à Véhicule 2 ; immatriculations à compléter. Les deux contacts sont récents après
déploiement, identités/secrets et paramètres vidéo préservés. Aucun redémarrage
des écouteurs. L’affectation et le nom n’interrompent plus la vidéo.

MariaDB local fonctionne de nouveau et toutes les migrations locales en attente
ont été appliquées : les anciennes mentions « migration locale en attente » sont
désormais historiques. Essais navigateur réalisés sur SQLite séparé ; appareils
réels et affectations en production. Suite finale Laravel : 158 tests / 792 assertions.
Le dashboard/carte restent en démo ; fonctions avancées flottes, archives/audio et
endurance vidéo restent à compléter. Voir [dashcam-registry.md](dashcam-registry.md).

### Correction de parcours — 22 septembre 2026

L’affectation dashcam demande uniquement le véhicule, avec recherche intégrée par
nom/immatriculation/flotte. Sa flotte est déduite en base ; le choix préalable de flotte
décrit dans le lot précédent est supprimé. Le formulaire véhicule garde son choix de
flotte, désormais recherchable. Titres/descriptions répétés des panneaux Flottes et
Véhicules retirés. Composant local adapté d’EXAD Tracking, sans nouvelle dépendance.
Correction testée (27 tests ciblés / 182 assertions et contrôles navigateur) et déployée
sans modification des affectations ni des écouteurs. Voir `dashcam-registry.md`.

### Organisation des véhicules — 22 septembre 2026

Départements/sites/régions opérationnels dans #departments, superadmin uniquement.
Flotte obligatoire pour chaque département et véhicule ; département facultatif pour
le véhicule, de la même flotte (validation transactionnelle + FK composée). Modales avec
sélecteurs recherchables, département filtré sur la flotte, choix Aucun département.
Déplacer un département occupé vers une autre flotte est bloqué. Dashcam → véhicule,
puis flotte et département éventuel du véhicule ; pas de saisie doublonnée sur la dashcam.
Migration appliquée localement et en production ; aucun département réel créé ni affecté.
Suite complète actuelle : 166 tests / 868 assertions, réussis. Sauvegarde déploiement :
/var/backups/exadcam-departments-20260922-121839. Identités et connexions conservées.
Voir docs/dashcam-registry.md et l’entrée correspondante de project-history.md.

## Diagnostic GPS de Véhicule 2 — 23 septembre 2026

ES500-603 connectée mais sans nouvelle position valide depuis 06:44:25 UTC
(07:44:25 Kinshasa). Capture de 40 secondes : quatre télémétries JT808 2013 avec
bit de validité GPS désactivé, toutes acquittées. La carte conserve le dernier GPS
avec l'état stale ; fuseau et affectations corrects. Utilisateur : caméra sous
un toit / dans un bâtiment. Essai extérieur demandé, reprise non encore vérifiée.
Aucun changement logiciel ou matériel appliqué. Détails : device-commissioning.md.


## Fluidité vidéo déployée — 23 septembre 2026

Le socle live-buffer-2, complété par live-reconnect-1 ci-dessous, est en production :
lecteur partagé Carte/Dashcams, réserve continue de 15 s avant lecture, cible HLS
à 18 s du bord et fenêtre serveur de 20 segments (environ 40 s). Première image reçue
affichée pendant la préparation ; dernière image conservée lors du remplissage suivant, sans animation
de chargement. Une coupure de réception dépassant la réserve reste perceptible.

La cadence réelle mesurée sur les deux canaux des appareils de test était inférieure
aux 15 images/s enregistrées. Calibration du remuxage pour la fiche 1 JK114 à 10
images/s et la fiche 2 ES500-603 à 12 images/s ; aucun changement du firmware,
protocole, identifiant ou affectation. La JK114 fiche 3 a ensuite été mesurée sur les deux canaux (environ 10,01 images/s)
et calibrée à 10 images/s. La fiche 4 a ensuite été mesurée et calibrée à 12 images/s (voir reprise automatique ci-dessous).

Validation : 40 tests Laravel / 317 assertions et 11 tests Node locaux avant
déploiement ; 10 tests listener Linux avec FFmpeg réel réussis pendant cette mise
en ligne. Essais matériels et limites détaillés dans device-commissioning.md.
Des déconnexions source ont aussi été observées pendant les essais ES500 CH2
et JK114 CH1 ; leur cause reste à diagnostiquer si elles persistent.
Sauvegarde : /var/backups/exadcam-live-buffer-20260923-084409.
Le service vidéo a été redémarré, le service GPS est resté actif sans redémarrage.


### Reprise du démarrage vidéo — 23 septembre 2026

Après signalement utilisateur, téléchargement dès le premier segment, aperçu
du premier vrai frame et correction des chronologies commençant après zéro.
La réserve de 15 secondes reste active. 13 tests Node lecteur/baux réussis.
Version live-buffer-2 déployée ; PHP-FPM rechargé, écouteurs non redémarrés.
L’état réel des essais et connexions figure dans la dernière entrée du journal :
ne pas assimiler cette correction du lecteur à un rétablissement des caméras
qui se sont déconnectées pendant le diagnostic.


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


## 23 septembre 2026 — Deux canaux réels sur le tableau de bord

L'ancien aperçu fictif du tableau de bord est remplacé par « Caméras en direct ».
Un sélecteur recherchable par nom de véhicule, immatriculation ou flotte propose
uniquement les véhicules réellement affectés à une dashcam activée et autorisée.
Choisir un véhicule démarre automatiquement les canaux 1 et 2 côte à côte sur
ordinateur ; les lecteurs passent l'un sous l'autre sur petit écran. Un appareil
à un seul canal affiche le second comme non configuré, sans requête inutile.

Le lecteur réutilise live-reconnect-1 : réserve de 15 s, reconnexion automatique,
arrêt indépendant et pause manuelle. Changer de véhicule libère les anciens baux ;
une sélection remplacée ou une navigation ne peut lancer un flux tardif. Quitter
le tableau de bord ferme les deux lecteurs et vide la sélection. Aucun flux n'est
ouvert à l'arrivée sur le tableau de bord avant un choix explicite.

Visible au superadmin et aux comptes ayant video.view dans une flotte active,
même sans accès à la carte. Les choix sont limités à la flotte du compte et ne
contiennent aucun IMEI, identifiant de communication ou secret. Si un véhicule
possède plusieurs dashcams activées, celle ayant le contact le plus récent est
choisie (id croissant en cas d'égalité). Les autorisations des routes vidéo
existantes continuent de s'appliquer à chaque démarrage et renouvellement.

Contrôles réellement exécutés : 20 tests Laravel ciblés, 162 assertions
(DashboardVideoTest et FleetAdministrationTest), SQLite en mémoire ; 28 tests
Node du lecteur, des baux et de la sélection à deux canaux. Quatre nouveaux
tests de coordination également réussis sur Linux avant installation. Syntaxe
PHP/JS vérifiée. Une collision d'identifiants dans les premières fixtures de
test a été corrigée avant leur réussite ; aucun changement des identités réelles.
Pas de suite complète Laravel/listener ni d'endurance vidéo rejouée dans ce lot.

Onze fichiers installés et hashes vérifiés. Sauvegarde :
/var/backups/exadcam-dashboard-live-20260923/before. Vues recompilées, PHP-FPM
rechargé, Apache/GPS/vidéo actifs ; aucun redémarrage des écouteurs, migration,
changement du registre des caméras ou du serveur Node.

Navigateur de production : recherche « EXAD » puis « 9863 », sélection des bons
véhicules, deux zones côte à côte d'environ 278 × 157 px en 16:9, démarrage des
deux demandes, changement de véhicule et remise à zéro en quittant le tableau
de bord vérifiés. Inspection visuelle effectuée. Les appareils étaient hors
connexion au test vers 13:18 UTC : les demandes retournaient Service 409 côté
écouteur. La reprise automatique restait active ; aucune image vidéo réelle
n'a été validée depuis ce nouveau composant pendant cet essai. Les tests ont
été fermés. La disponibilité physique des caméras est distincte de cette livraison.

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


## Tableau de bord réel — 23 septembre 2026

Demande : remplacer les indicateurs, tableaux et alertes de démonstration, montrer
le statut dans la recherche de véhicule, afficher carte et caméras avant l'activité,
supprimer l'espace vide et le centre vidéo redondant.

Implémentation : DashboardService fournit les compteurs et véhicules autorisés,
les choix vidéo avec état de connexion, les agrégats GPS et les alertes. Les routes
authentifiées /dashboard/data et /dashboard/alerts actualisent les données et
paginent les alertes par 10. La liste des véhicules est filtrable et paginée par 10.
Le dashboard partage désormais ces composants entre superadmin et comptes clients.
Les deux panneaux carte/caméras sont de même hauteur ; le dashboard désactive les
gestes, contrôles et clics sur marqueurs, tandis que la page Carte reste interactive.
Les deux lecteurs gardent leurs sessions lors d'une actualisation des statuts.
Le menu et les boutons Centre vidéo et les anciens exemples ont été retirés.

Données : connexion récente = dernier contact de moins de 3 minutes. Le graphique
compte les véhicules distincts avec GPS valide par heure (24 tranches) ou jour
(7 jours), et ceux ayant un relevé en déplacement (ACC allumé, vitesse >= 3 km/h).
Il ne reconstruit pas un historique de connexion à partir du dernier contact.
Heures affichées : Africa/Kinshasa. Les compteurs concernent les caméras activées.

Alertes : nouveaux bits d'alarme JT808 enregistrés sur 7 jours, comparés au relevé
précédent, y compris avant le début de la période pour éviter les répétitions.
Les pertes de contact en cours apparaissent avec la date réelle du dernier signal,
et disparaissent à la reconnexion. Aucun événement artificiel de déconnexion passée.
Les bornes d'affectation dashcam/véhicule/flotte et les permissions sont appliquées.
Sans map.view, pas d'alertes, de vitesse, de mouvement ou d'historique GPS exposé.
Les réponses du dashboard ne contiennent aucun IMEI ni paramètre de communication.

Limites : seules les alarmes déjà conservées dans dashcam_positions.alarm sont
exploitables. Les extensions ADAS/DMS propriétaires et les alarmes sans position
GPS rejetées par le décodeur actuel ne sont pas ajoutées par ce changement.
Les libellés indiquent des signalements de l'équipement, pas des incidents vérifiés.
Référence consultée pour les bits standard :
https://raw.githubusercontent.com/traccar/traccar/master/src/main/java/org/traccar/protocol/Jt808ProtocolDecoder.java

Vérification locale : suite Laravel complète, 219 tests / 1219 assertions réussis,
36 tests JavaScript réussis ; contrôles de syntaxe PHP/JS. Tests ajoutés pour
compteurs réels, permissions, réaffectation, GPS invalide, répétitions d'alarme,
frontière de période, déconnexion/reconnexion et pagination.
Prévisualisation navigateur sur base SQLite isolée : statuts visibles/recherchables,
navigation Alertes, ordre des sections, hauteur identique des deux panneaux (421,7 px
sur la fenêtre de validation). Déploiement et contrôle production consignés ensuite.


### Validation sur le serveur — 23 septembre 2026, 14:53 UTC

La vérification MariaDB initiale a révélé un coût de 15,228 s pour plusieurs
instantanés et la pagination. La recherche du précédent signal par paquet a été
remplacée par LAG sur la période et un seul prédécesseur par équipement.
Les bornes d'affectation restent appliquées aux deux branches. Le même contrôle
complet s'exécute ensuite en 0,583 s, puis 0,588 s après installation.
Après cette optimisation : 33 tests ciblés Dashboard/FleetAdministration et
247 assertions réussis ; 4 tests de sélection vidéo réussis. Le complément
« En ligne · état GPS » dans la recherche est couvert par 12 tests / 82 assertions.

Déploiement initial : 25 fichiers sauvegardés/installés et empreintes vérifiées,
cache des vues reconstruit, PHP-FPM rechargé. Sauvegarde :
/var/backups/exadcam-dashboard-real-20260923-145302.
Aucune migration/écriture métier, aucun vidage du cache des leases et aucun
redémarrage des listeners GPS/vidéo. Les quatre services contrôlés sont actifs.

Validation navigateur en production : 4 véhicules équipés, 4 dashcams activées,
8 canaux configurés, 2 connexions récentes au contrôle, concordant avec SQL.
29 alertes dans la période à cet instant ; pages 1–10 puis 11–20 vérifiées.
Les alertes utilisent les bits transmis par les appareils, et ne certifient pas
qu'une collision ou fatigue ait effectivement eu lieu.
La carte d'aperçu n'affiche aucun bouton de contrôle et ses marqueurs ont un rôle
d'image. La page Carte rétablit les contrôles et le rôle de bouton du marqueur.

Essai vidéo de validation à 14:56 UTC : deux canaux de Véhicule 2 réellement
affichés, 960 × 540, readyState=4, paused=false ; progression initiale CH1=13,34 s
et CH2=10,69 s, puis 54,72 s / 52,23 s après plusieurs actualisations du dashboard.
Aucun nouveau video_requested entre l'ouverture à 14:56:00 et la fermeture des
deux sources à 14:57:39 (source_disconnected). La reprise automatique se déclenche.
Cet essai confirme la conservation des sessions pendant l'actualisation des choix,
pas la disparition des coupures propres aux flux ES500 déjà suivies dans le journal.
Les deux flux d'essai sont arrêtés explicitement avant la fin de la validation.

Complément final : état GPS ajouté au statut de recherche pour les comptes avec accès carte ; sauvegarde /var/backups/exadcam-dashboard-real-20260923-145930. Pastille de statut corrigée et feuille de style versionnée dashboard-real-2. Empreintes CSS/Blade identiques en local et en production. Les quatre services sont actifs au contrôle final. Les deux lecteurs de test sont arrêtés et leurs reprises annulées. Les pertes de contact ES500 observées restent à diagnostiquer ; elles ne sont pas corrigées par ce lot dashboard.


## Compteur hors ligne et ordre du menu — 23 septembre 2026

Le widget « Canaux configurés » est remplacé par « Dashcams hors ligne »,
lié à metrics.offline et à son actualisation existante. Il compte les dashcams
activées sans contact récent dans le périmètre autorisé. Le menu Carte suit
immédiatement Tableau de bord ; Utilisateurs suit Rapports. Les conditions
d'autorisation des liens et les regroupements propres au superadmin sont conservés.
Validation ciblée : 27 tests existants RealDashboard, DashboardVideo et
FleetAdministration réussis, 221 assertions. Aucun test supplémentaire créé
pour ces ajustements de vues ; aucune modification du calcul ou des flux vidéo.

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


## 25 septembre 2026 — Complément de déploiement demandé

Après « deploie », contrôle des empreintes du correctif GPS déjà installé dans
le projet et dans le runtime. Les trois services sont actifs, les sources GPS
passent le contrôle de syntaxe et le service de santé GPS répond HTTP 200.
Complément installé : note de compatibilité JT808 2019 et brouillon technique
fournisseur non envoyé (docs/demande-jt808-2019.md). Aucun nouveau redémarrage,
changement de firmware/protocole caméra, migration ni purge de cache.
Les 22 tests GPS/protocole sont ceux du lot précédent, non rejoués ici.
Le passage matériel en 2019 reste non appliqué faute de procédure compatible.
Les journaux d'appareils restent sur le serveur ; aucun nouvel export local.


## 25 septembre 2026 — Diagnostic complémentaire ES500 sans coupure

L'utilisateur confirme le retour de Véhicule 2 après son réenregistrement du
formulaire CarAssist, puis le fonctionnement de la vidéo. Il ne peut pas
effectuer la même manipulation pour l'autre ES500 maintenant. Aucun changement
de production, paramètre caméra ou redémarrage dans ce diagnostic. Deux tests
de la nouvelle sonde de lecture seule passent ; preuves détaillées conservées
sur le serveur. Le réveil d'une liaison JT808 fermée, le retour automatique
rapide et la stabilité prolongée restent non résolus. Voir la dernière entrée
de docs/es500-auto-return.md ; ne pas attribuer le retour au correctif 503.


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

## 2026-09-28 — Stabilité du week-end avec EXADCAM seul sur Véhicule 2

- Retour utilisateur : Véhicule 2 ne se déconnecte plus depuis qu’il a conservé uniquement le serveur EXADCAM, avec le Backup retiré. Il confirme que le Backup reste configuré sur le Toyota Hilux 9863BV01. La suppression du Backup avait déjà été signalée le 25 septembre, avec accès distant CarAssist toujours fonctionnel.
- Vérification du 28 septembre en lecture seule : Véhicule 2 conserve la même session GPS authentifiée depuis vendredi 25 septembre à 16 h 31 min 52 s, heure de Kinshasa, soit environ 64 heures. Aucune fermeture de cette session n’est enregistrée sur la période ; le contact reçu était âgé d’environ 5 secondes lors du contrôle. Ce constat repose sur la session réelle du listener et les journaux, pas uniquement sur le statut affiché dans l’interface.
- Comparaison sur la même période : le Hilux est également en ligne au contrôle, mais les journaux enregistrent 165 authentifications et 164 fermetures de sessions, dont 105 remplacements par une nouvelle connexion, 54 expirations réseau, 4 fermetures par le pair et un retour de service 422. Ces nombres ne représentent pas 164 pannes utilisateur : un remplacement peut intervenir alors qu’une nouvelle connexion est déjà authentifiée. Aucun contenu détaillé des journaux ni position géographique n’est copié ici.
- Configuration de référence validée en exploitation pour Véhicule 2 sur ce week-end : EXADCAM seul, Backup vide. Ne pas restaurer automatiquement l’ancien Backup. Pour le Hilux, un essai de cette même configuration est pertinent ; il n’a pas été effectué pendant ce contrôle et ne doit pas être présenté comme appliqué.
- Interprétation : les observations renforcent la piste d’un effet de la configuration secondaire, sans isoler à elles seules sa causalité. Les correctifs du listener ont aussi été activés le vendredi et l’heure exacte du retrait du Backup n’est pas connue. La stabilité observée ne valide pas encore un retour après une nouvelle panne d’alimentation ou de réseau, ni la stabilité future de tous les appareils.
- Aucun changement de code, déploiement, redémarrage, commande caméra, modification de paramètres ou nouvel essai de rupture. Aucun test automatisé relancé ; documentation locale mise à jour. Les anciens diagnostics restent conservés comme historique.

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
