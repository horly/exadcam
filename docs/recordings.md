# Enregistrements des cartes mémoire

Fonction livrée le 28 septembre 2026 pour les caméras **ESTON ES500-603 JK114** et **4G SmartVision JT808/1078**. Les profils internes des listeners restent respectivement `JK114` et `ES500-603`.

## Parcours utilisateur

Le menu **Enregistrements** apparaît aux comptes disposant du droit `video.view` dans une flotte active (ou au superadmin). Choisir un véhicule dans le sélecteur avec recherche et statut, une date et un canal ou tous les canaux. La caméra doit être connectée à EXADCAM. Les heures sont celles de Kinshasa.

La recherche interroge la carte SD et affiche cinq lignes par page par défaut, les plus récentes en premier. **Ouvrir** permet de sélectionner une période dans le fichier. **Préparer la vidéo** récupère cette période, affiche la progression puis présente un lecteur et **Télécharger le MP4**. L'annulation arrête uniquement la relecture SD. Aucun fichier n'est effacé de la carte mémoire.

Le fichier téléchargé est un MP4 préparé depuis la relecture distante, avec vidéo H.264 et audio AAC si la caméra le transmet. Ce n'est pas une copie binaire du conteneur d'origine de la carte. Le début dépend de la recherche temporelle et de l'image clé fournie par le firmware. La taille annoncée dans la liste vient de l'appareil et peut différer fortement du MP4 obtenu.

## Autorisations et conservation

- Chaque recherche et chaque transfert sont liés au compte, à la caméra et à son affectation actuelle. Tous les endpoints, y compris les requêtes HTTP Range du lecteur et les téléchargements, revérifient droits, caméra activée et flotte.
- Pour un client, les fichiers antérieurs à l'affectation courante du véhicule à sa flotte ou de la caméra au véhicule sont exclus. Un fichier qui chevauche cette affectation est exclu entièrement : un firmware peut rechercher l'image clé ou le début du fichier avant l'heure demandée. Les affectations sans date connue sont refusées.
- Une modification d'affectation invalide les recherches et fichiers précédemment autorisés. Aucun IMEI, modèle technique ou identifiant de communication n'est renvoyé au client par ces endpoints.
- Recherche en cache 30 minutes ; transfert et MP4 disponibles au maximum 6 heures depuis la demande. Les fichiers terminés survivent au redémarrage du récepteur ; un transfert en cours doit être relancé après un redémarrage. Le transfert continue si l'onglet est masqué. Un rechargement complet de la page ne restaure pas encore son suivi dans l'interface.
- Limites initiales : extrait de 30 minutes, 512 Mio reçus, un transfert par caméra et quatre transferts simultanés. La durée réelle dépend du réseau mobile et du firmware. Au-delà de la taille limite, demander un extrait plus court.

## Architecture

1. Laravel autorise et borne la recherche, puis appelle l'API GPS interne.
2. La commande JT1078 `0x9205` recherche les ressources. `0x1205` renvoie la liste ; réassemblage borné des fragments, ACK individuels, vérification du numéro de la requête dans le corps. Les deux firmwares testés incrémentent le numéro JT808 à chaque fragment ; le numéro constant standard est également accepté.
3. Le récepteur crée un identifiant de transfert imprévisible et réserve la caméra avant `0x9201`. Le flux de relecture arrive sur **1081/TCP**, distinct du direct **1078/TCP** et de l'interphone **1080/TCP**. Seul le terminal exact, le canal demandé et un transfert actif sont admis ; le registre est revérifié.
4. Réassemblage H.264 et audio G.711/AAC, attente de la dernière donnée reçue, puis conversion FFmpeg vers MP4. Une fermeture TCP normale ne doit pas éliminer les paquets encore en attente de traitement. Une coupure avant la fin marque le transfert incomplet.
5. L'arrêt `0x9202` conserve la réservation jusqu'à sa réponse/expiration afin qu'un arrêt tardif ne coupe pas un nouveau transfert.
6. Le MP4 est servi par Laravel sous HTTPS, avec session et autorisation. Aucun alias Apache public n'expose le répertoire d'archives.

### Exploitation

Service : `deployment/systemd/exadcam-recordings.service`, exécution `/opt/exadcam-listener/src/recordings.js`, coordination privée `127.0.0.1:3004`, même jeton interne que les autres listeners. Installer FFmpeg et rendre le port 1081/TCP joignable par les caméras. Il est transmis dans la demande de relecture ; ne pas remplacer le port serveur GPS de la caméra.

Répertoire `/var/lib/exadcam-recordings`, propriétaire `exad-listener`, groupe `www-data`, mode `2750` ; sous-répertoires `0750`, fichiers `0640`. Le service utilise le groupe `www-data` et le groupe supplémentaire `exad-listener`. Laravel doit pouvoir lire les MP4 sans accès public direct. Les répertoires temporaires expirés sont purgés, y compris après redémarrage.

Variables : `RECORDING_PORT=1081`, `RECORDING_API_PORT=3004`, `RECORDING_STORAGE=/var/lib/exadcam-recordings` côté Node ; `LISTENER_RECORDING_URL=http://127.0.0.1:3004` et le même `RECORDING_STORAGE` côté Laravel. Les valeurs par défaut correspondent au déploiement. Ne pas vider le cache applicatif des baux vidéo lors de l'installation.

Contrôles utiles : santé privée `/health`, `systemctl status exadcam-recordings`, événements `recording_ready`/`recording_failed` dans le journal du service. Ne pas exporter les captures réseau, enregistrements ou journaux détaillés de production dans le dépôt.

## Validation du 28 septembre

- 32 tests PHP ciblés, 290 assertions : RecordingAccessTest, FleetAdministrationTest, DashboardVideoTest, sur SQLite en mémoire ; aucune base métier utilisée pour les tests.
- 14 tests Node ciblés protocole/GPS, dont requêtes SD avec trames JT808 2013/2019 sur TCP, réponses fragmentées à numéros constants ou incrémentaux, erreurs et maintien de la session GPS.
- 2 tests Linux du récepteur avec FFmpeg, identités JT1078 sur 6/10 octets, réception vidéo et audio final, concurrence, annulation, arrêt tardif et téléchargement conservé après redémarrage.
- Matériel réel : listes SD et extraits de 15 secondes obtenus sur Véhicule 2 SmartVision et Suzuki Horly ESTON. MP4 H.264/AAC analysés avec ffprobe, durées vidéo/audio cohérentes. Le contrôle ne constitue pas une écoute humaine de tout le contenu audio.
- Recherche sur une journée et tous les canaux : 591 entrées SmartVision et 58 entrées ESTON lors des contrôles, canaux 1 et 2 présents. Ces nombres évoluent avec l'enregistrement.
- Navigateur de production : sélection, recherche, dix lignes par page, préparation d'un extrait du canal 2 ESTON, lecture jusqu'à la fin (15,1 s) et événement de téléchargement MP4 constatés. Pas de débordement horizontal de page aux formats mobile 390×844 et tablette 820×1180 ; le tableau défile dans son propre conteneur.
- Complément : transfert d'un fichier SmartVision du canal 2 de Véhicule 2 sur environ deux minutes, MP4 de 12,4 Mio / 120,032 s, vidéo H.264 640×360 et audio AAC. Chargement dans le navigateur et progression de lecture au-delà de 40 secondes constatés. L'intervalle annoncé par le firmware était de 121 secondes ; la durée exportée suit les images réellement reçues.

Ces contrôles sont ciblés. Ils ne valident pas une charge de plusieurs centaines d'appareils, toutes les cartes mémoire, tous les firmwares ni une copie de toute la carte SD. Les deux Hilux n'ont pas été utilisés pour les essais de relecture de ce lot.

## Références de format

Implémentation primaire consultée pour les corps JT1078 :

- [Requête 0x9205](https://github.com/cuteLittleDevil/go-jt808/blob/main/protocol/model/p_0x9205.go)
- [Réponse 0x1205](https://github.com/cuteLittleDevil/go-jt808/blob/main/protocol/model/t_0x1205.go)
- [Relecture 0x9201](https://github.com/cuteLittleDevil/go-jt808/blob/main/protocol/model/p_0x9201.go)
- [Contrôle 0x9202](https://github.com/cuteLittleDevil/go-jt808/blob/main/protocol/model/p_0x9202.go)

Le comportement réel des firmwares est vérifié séparément ci-dessus ; l'existence d'une commande standard seule ne garantit pas son fonctionnement matériel.


## Présentation — correction du 28 septembre

Formulaire compact dans les couleurs EXADCAM : contrôles de 38 px sur ordinateur/tablette et 40 px sur mobile, boutons et pagination harmonisés. La largeur de la sélection véhicule est bornée sur grand écran ; les filtres se réorganisent selon la largeur du panneau. La liste avec recherche et statuts se superpose au contenu sans déplacer Date, Canal ou Rechercher. Le tableau garde son défilement horizontal local sur mobile.

CSS limité au panneau Enregistrements, version de ressource actualisée dans la vue Blade ; aucun changement des commandes, transferts ou droits. Vérification en production aux largeurs 1536, 820 et 390 px : positions des autres filtres identiques avant/après ouverture de la liste, aucun débordement horizontal de page, recherche Hilux conservant les deux résultats et leur statut. Les éléments masqués de préparation/téléchargement restent masqués. Suite ciblée RecordingAccessTest relancée pour ce lot : 10 tests, 66 assertions. Aucun nouvel essai matériel de transfert requis pour ce changement de présentation.


## Datatable — 28 septembre 2026

Le tableau reprend les composants visuels des listes Véhicules/Dashcams : recherche, choix 5/10/25/50 lignes (5 par défaut), en-têtes triables Canal/Début/Fin/Durée/Taille, numérotation, boutons de pages et compteur « Affichage de … à … sur … ». Les résultats filtrés indiquent aussi le total initial. La recherche porte sur le canal, les dates/heures affichées à Kinshasa, la durée et la taille. Les tris numériques utilisent les valeurs brutes.

Recherche, tri et pagination sont appliqués côté Laravel sur toute la liste autorisée déjà en cache, sans nouvelle interrogation de la caméra. L’index original du fichier est conservé après filtrage/tri pour que Ouvrir/Préparer cible toujours le bon enregistrement. Le contrôle des droits et affectations est inchangé. Les tailles/colonnes/directions sont validées et la recherche est limitée à 100 caractères.

Validation de ce lot : 13 tests RecordingAccessTest, 114 assertions sur SQLite en mémoire, contrôles de syntaxe PHP/JS, format Pint et git diff --check. En production, Véhicule 2 a renvoyé 634 entrées : 10 puis 25 lignes, page 2, filtre Canal 2 (317 résultats), tri numérique par taille, résultat vide puis effacement du filtre, ouverture de la bonne période vérifiés. Pas de nouveau transfert vidéo lancé pour cette modification du tableau. Affichage contrôlé aux largeurs 1536, 820 et 390 px, sans débordement de page ; défilement horizontal propre au tableau sur mobile. Le menu véhicule reste en superposition sans déplacement des filtres. Déploiement sans redémarrage des services GPS/direct/audio/enregistrements.
