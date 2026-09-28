# Vidéo du tableau de bord

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

## 24 septembre 2026 — Démarrage vidéo accéléré, version finale à 8 secondes

Demande : ouverture lente des directs sur les deux modèles, avec les absences
de Véhicule 2 à traiter séparément. Réserve initiale réduite de 15 à 8 secondes,
remplissage après épuisement conservé à 15 secondes. Vérification du premier
manifest toutes les secondes, puis renouvellement à 5 secondes. Trois interfaces
versionnées live-startup-20260924b ; droits, leases, audio/micro conservés.

62 tests JavaScript réussis sur la version finale. Déploiement final à
11:51:09 UTC, huit fichiers et leurs empreintes vérifiés ; sauvegarde
/var/backups/exadcam-live-startup-20260924-115108. Vues compilées, aucun cache
de leases vidé ni service redémarré (trois PID inchangés). Cinq ressources JS
publiques HTTP 200 avec empreinte conforme. La première version à 4 secondes
a été ajustée après un remplissage observé sur CH2 ES500 ; son historique et
la sauvegarde de la version initiale sont dans docs/live-stream-startup.md.

Essais navigateur sur ES500 fiche 4 et JK114 fiche 3 : les deux canaux de chaque
appareil sont effectivement lus, leur progression est constatée à deux relevés
séparés. Les flux d'essai sont arrêtés explicitement, aucun micro ouvert. Ces
essais courts ne constituent ni un test d'endurance ni une promesse d'ouverture
totale en huit secondes. Le délai matériel et réseau subsiste.

Véhicule 2 encore absent à 11:54:03 UTC, contact 11:29:07, après l'essai RST
précédent. Aucun nouvel essai de déconnexion. Retour automatique non résolu ;
timeout 30 s/retransmissions 3 à restaurer vers 10 s/0 au prochain retour.
Défaut distinct de présence sur réponses de commandes encore non corrigé.
Voir docs/es500-auto-return.md ; aucune tâche de surveillance laissée en fond.

Fichiers : public/js/{live-player.mjs,map-video.mjs,google-map.js,dashcams.js,
dashboard-video.mjs}, trois entrées Blade, deux tests JS. Preuves et sauvegardes
locales : workspace analysis/live-startup-20260924/. Détails complets dans
docs/live-stream-startup.md. Aucune suite Laravel complète rejouée dans ce lot.

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
