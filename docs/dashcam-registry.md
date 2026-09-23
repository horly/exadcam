# Registre dashcams, véhicules et flottes

## Administration de flotte — 22 septembre 2026

Un admin actif affecté à une flotte active gère ses véhicules et départements
(création et modification), ses dashcams déjà affectées (nom, véhicule de sa
flotte et activation) et ses utilisateurs simples. Il ne peut consulter une
autre flotte, transférer des données vers celle-ci, créer un admin ou provisionner
du matériel. La création/configuration technique des dashcams reste au superadmin.

Les permissions déléguables ajoutées sont vehicles.manage, departments.manage,
dashcams.manage et video.view ; map.view reste indépendante. Les autres permissions
du catalogue antérieur sont conservées, sans annoncer de nouveaux modules pour
les rapports, garages, entretiens ou commandes moteur.

FleetAccess vérifie les autorisations et la flotte active. Listes, recherches,
compteurs, choix de véhicules/départements et écritures sont filtrés côté serveur.
Les réponses JSON et HTML destinées aux clients omettent IMEI, identifiants SIM et
terminaux, IP, secrets et paramètres de connexion. La carte fournit uniquement
id interne, nom, modèle et canaux pour accéder au direct autorisé ; les détails
techniques restent réservés au superadmin. La recherche ne porte pas sur l’IMEI
pour les comptes de flotte. Le tableau de bord client utilise des compteurs réels,
sans les anciennes lignes de véhicules de démonstration.

Un utilisateur simple n’accède qu’aux fonctions cochées par son admin ; il ne
gère jamais les comptes. La flotte de création des véhicules/départements est
implicite et les valeurs étrangères forgées sont refusées. Le département du
véhicule reste facultatif et doit appartenir à la même flotte. La flotte d’un
admin/user créé en console est désormais obligatoire via --fleet=<id> et la
liaison manager/viewer est synchronisée.

Le direct exige video.view, une caméra de la flotte et une session web propriétaire
du bail. Chaque renouvellement revérifie le périmètre. Les transferts de véhicules
ou caméras entre flottes demandent une révocation des flux ; pas de coupure sur un
simple renommage. Limite du transport existant : les URL média sont des capacités
temporaires de 60 secondes ; en cas de révocation inaccessible, elles expirent sans
renouvellement (des segments déjà reçus peuvent rester en mémoire du lecteur).
L’isolation des historiques GPS après affectation reste inchangée.

Les sections historiques ci-dessous décrivent les lots précédents ; les règles
d’accès ci-dessus remplacent la restriction antérieure au superadmin du registre.

État livré le 22 septembre 2026, dans le projet local et sur EXADCAM.

## Parcours

1. Créer une flotte dans **Flottes**, avec un nom et un code.
2. Créer son véhicule dans **Véhicules**. L’immatriculation, la marque et le modèle du véhicule peuvent être complétés ensuite.
3. Dans **Dashcams**, ouvrir **Ajouter une dashcam**, choisir d’abord le modèle, puis saisir son nom et son IMEI de 15 chiffres.
4. Rechercher et choisir directement le véhicule (nom, immatriculation ou flotte). Sa flotte est déduite de la relation existante. L’affectation est obligatoire pour une nouvelle fiche et pour une édition complète.
5. Renseigner les paramètres du profil et enregistrer. La modification utilise la même modale et préremplit les valeurs existantes.

Les flottes et véhicules disposent de créations/modifications en modales, recherche, tri et pagination. Le tableau dashcams ajoute un filtre par modèle, les compteurs, le statut, l’année configurée et celle effectivement reçue, le choix du canal, le direct et l’activation/désactivation. Le style reprend le tableau Utilisateurs d’EXADCAM et le principe des tableaux EXAD Tracking : requêtes serveur et contrôles locaux, sans CDN ni dépendance DataTables supplémentaire. Les textes sont disponibles en français et en anglais.

## Profils supportés

| Modèle | Transport | JT808 | Identité GPS et vidéo |
| --- | --- | --- | --- |
| ES500-603 | TCP | 2013 fixe | Recopier exactement les 12 chiffres de « SIM Number » dans CarAssist, zéro initial compris. Ils alimentent `terminal_id_2013` et `video_terminal_id`. |
| JK114 | TCP | 2013 ou 2019 | Provisionnement du format court (2013) ou étendu (2019) depuis l’IMEI. Les alias personnalisés existants sont conservés lors d’une édition de métadonnées. |

L’identifiant CarAssist n’est pas l’IMEI et ne doit pas être déduit par une formule ni réutilisé pour toutes les ES500. Le profil ES500 active la normalisation d’horodatage vidéo déjà validée ; la JK114 utilise son mode natif. Une modification d’identité ou de profil révoque les anciennes connexions afin que le listener recharge les nouveaux paramètres. Un changement limité au nom ou au véhicule ne coupe pas le direct.

`protocol_version` représente l’année choisie dans la fiche ; `last_protocol` reste le protocole réellement reçu. Enregistrer un profil ne reconfigure pas le firmware à distance. L’écouteur continue de décoder le format de chaque trame. Le transport exposé ici est TCP ; le flux vidéo conserve le service JT1078 existant. Ce lot ne change ni les ports ni le code Node.

## Relations et sécurité

- `vehicles.fleet_id` est obligatoire ; `dashcams.vehicle_id` définit la relation dashcam → véhicule → flotte. La flotte d’une dashcam est obtenue par son véhicule, sans champ redondant.
- Le véhicule soumis doit exister. Sa flotte est obtenue depuis sa relation en base ; aucune flotte distincte n’est demandée ni utilisée depuis le formulaire dashcam. Les identifiants de communication et l’IMEI sont contrôlés côté serveur ; leurs contraintes d’unicité existantes restent en place.
- Une fiche véhicule peut porter plusieurs dashcams, comme plusieurs équipements de suivi ; chaque dashcam n’a qu’un véhicule.
- La FK dashcam est nullable uniquement pour permettre une migration sans couper les caméras préexistantes non encore affectées. Celles-ci sont signalées « À affecter ».
- Les routes de ce registre restent réservées au superadmin avec session Fortify, contrôle de compte actif et CSRF. Aucune extension des accès des administrateurs clients dans ce lot.
- Le jeton d’authentification terminal n’est jamais retourné par le registre public. L’autorisation IMEI/alias exacts et les baux de lecture par session demeurent inchangés.
- Suppression des flottes/véhicules non proposée dans ces écrans. Les références empêchent une suppression accidentelle de parents utilisés.

## Affectations réalisées en production

| Flotte | Véhicule | Dashcam |
| --- | --- | --- |
| EXAD CARS | Véhicule test 1 | JK114 |
| EXAD CARS | Véhicule 2 | ES500-603 |

L’ordre donné par l’utilisateur a été repris et annoncé. Les immatriculations restent à renseigner. La migration et l’affectation ont préservé les alias, secrets, fuseaux, autorisations et réglages vidéo. Les deux appareils transmettent encore après le déploiement ; l’ES500 garde `053810725721` et le format 2013, la JK114 son identité vidéo étendue et 2019.

## Validation et déploiement

- Suite Laravel finale : **158 tests, 792 assertions, tous réussis**, sur SQLite isolé. Inclus : restrictions modèle/TCP/année, identifiants courts, doublons, cohérence flotte/véhicule, préservation des identités et des secrets à la migration et à l’édition, autorisations, recherche et pagination.
- Syntaxe des deux fichiers JavaScript contrôlée avec `node --check`. PHP formaté avec Pint sur les fichiers concernés.
- Navigateur local sur une base SQLite dédiée : création ES500, validation dynamique, édition JK114, recherche, création flotte et véhicule, sélection des véhicules (remplacée ensuite par une recherche directe, voir correction ci-dessous), rendu des modales.
- Navigateur production : tableau, contacts récents, affectations, préremplissage exact de l’ES500 et année 2013 fixe contrôlés. Pas de nouvel essai vidéo prolongé dans ce lot ; les validations vidéo antérieures restent documentées séparément.
- Sauvegarde production avant changement : `/var/backups/exadcam-registry-20260922-111550` (fichiers et export SQL, accès root uniquement). Migration additive appliquée avant les fichiers qui utilisent les colonnes. Écouteurs GPS/vidéo non redémarrés ; cache des baux vidéo non vidé. Apache, PHP-FPM et les deux écouteurs actifs après livraison.
- MariaDB local relancé et migrations en attente appliquées ; le schéma local est à jour. Les équipements et affectations réels sont en production. Les données d’essai navigateur restent dans une base SQLite distincte, extérieure au projet.

## Limites conservées

La carte et les indicateurs du dashboard restent en démonstration. Les modules flottes/véhicules livrés couvrent le registre et les affectations, pas encore toutes les fonctions métier d’EXAD Tracking. Archives, audio, sauvegarde vidéo cloud, applications mobiles et test d’endurance ne sont pas livrés par ce changement. L’observation antérieure de rafale ES500 et de limiteur reste à suivre.


## Correction UX — 22 septembre 2026

Le champ Flotte a été retiré des modales dashcams : un seul choix de véhicule est nécessaire.
Le serveur ne demande plus `fleet_id` pour créer/modifier une dashcam et ne laisse pas
une valeur envoyée par un ancien formulaire remplacer la flotte effective du véhicule.
Les affectations existantes et le schéma restent inchangés.

Le composant local `searchable-select.js` d’EXAD Tracking a été adapté au thème EXADCAM,
sans Font Awesome ni bibliothèque externe. Recherche intégrée au choix du véhicule
(nom, immatriculation, nom/code de flotte) et au choix de flotte dans le formulaire
véhicule. Prise en charge du clic, des flèches, d’Entrée, d’Échap et des erreurs dynamiques.
La liste s’insère dans le contenu défilant de la modale pour rester visible près du bas.
Les modèles et années, listes courtes, conservent leurs sélecteurs simples.

Les titres/descriptions doublons dans les panneaux Flottes et Véhicules ont été retirés.
Recherche, taille de page, actualisation et ajout sont regroupés sur une barre d’outils.

Validation de ce correctif : 27 tests Laravel ciblés / 182 assertions, tous réussis,
contrôle de syntaxe des 3 scripts JS et Pint. Essais navigateur sur SQLite isolé :
édition dashcam sans flotte, recherche et choix clavier, résultat vide, Échap,
validation vide puis choix à la souris et création d’un véhicule. Un défaut de fermeture
au changement de focus a été corrigé avant livraison, puis le cas a été rejoué avec succès.
La suite complète de 158 tests du lot précédent n’a pas été rejouée pour cette correction.

Déployé après sauvegarde des fichiers dans
`/var/backups/exadcam-registry-ux-20260922-115425` ; aucune migration, écriture des
données réelles ou modification des écouteurs. Services actifs après déploiement.

## Départements / sites / régions — 22 septembre 2026

Organisation actuelle :
- Une flotte contient des véhicules et peut contenir des départements.
- Un département (ou site/région, selon son nom) appartient à une seule flotte obligatoire.
- Un véhicule appartient à une flotte obligatoire et peut être affecté à un département
  de cette même flotte. Sans département, il reste directement rattaché à sa flotte.
- Une dashcam appartient à un véhicule ; sa flotte et son département éventuel sont déduits
  de ce véhicule. Aucun champ flotte/département supplémentaire dans la modale dashcam.

Créer l’unité dans Départements, choisir sa flotte et son nom, puis éventuellement un code
et une description. Dans Véhicules, choisir la flotte puis rechercher le département souhaité,
ou laisser Aucun département. Modifier la flotte du véhicule efface toute sélection de
département incompatible. Le tableau affiche l’affectation et permet de rechercher son nom/code.

La table departments reprend les champs du registre EXAD Tracking. Le couple de clés
vehicles(department_id, fleet_id) référence departments(id, fleet_id), avec restriction des
changements/suppressions incompatibles. Les écritures de registre sont transactionnelles.
L’API PATCH préserve department_id s’il est omis, et le retire sur null explicite. Changer
la flotte d’un département occupé exige de réaffecter préalablement ses véhicules.

Gestion superadmin, création/modification et listes seulement. Pas de suppression ni de
hiérarchie récursive dans ce lot. Tests : 166 / 868 assertions (suite complète réussie),
essais de formulaires sur SQLite séparé, migration MariaDB locale/production réussie.
Les 2 véhicules de production restent sans département ; aucun nom réel n’a été fourni.
Sauvegarde : /var/backups/exadcam-departments-20260922-121839. Écouteurs non redémarrés.

## Affichage du direct JK114 — 22 septembre 2026

Dans la liste Dashcams, Voir en direct réapplique le modèle de la caméra choisie
à la modale à chaque ouverture. Le lecteur JK114 remplit horizontalement sa
surface via object-fit: fill, comme sur la carte ; image entière et incrustations
conservées, sans recadrage. La taille et la hauteur maximale de la modale ne
changent pas. ES500-603 conserve contain et le flux natif n’est pas réencodé.

Contrôle en production du Canal 2 JK114 (720 × 576, lecture effective et image
pleine largeur), fermeture, puis passage ES500-603/contain vérifié et fermé.
Syntaxe JS valide ; pas de nouvelle suite complète pour ce lot de présentation.
Sauvegarde : /var/backups/exadcam-dashcam-live-image-20260922-151023.

## Correction du provisionnement GPS — 22 septembre 2026

Les nouvelles fiches JK114 et ES500-603 enregistrent maintenant explicitement
gps_timezone_minutes depuis listener.default_gps_timezone_minutes (variable
DASHCAM_GPS_TIMEZONE_MINUTES, défaut 60). Valeur confirmée pour les quatre
appareils actuels à Kinshasa ; ce n’est pas une propriété universelle du modèle.
Les éditions conservent les valeurs personnalisées, y compris 0 ou null ;
l’API de maintenance conserve son réglage par appareil. Le fuseau n’est pas
déduit de la date de réception, car les terminaux peuvent envoyer des archives.

Correction des deux Toyota Hilux VODA FLEET : les coordonnées étaient reçues,
mais leur heure avait sept heures de retard à cause du repli Node UTC+8.
335 horodatages vérifiés et bornés corrigés, sans déplacer d’affectation ni
modifier les coordonnées. Tests ciblés registre / listener / carte : 44 tests,
331 assertions. Sauvegarde exadcam-gps-timezone-20260922-153119.
