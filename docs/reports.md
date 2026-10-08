# Rapports EXADCAM

## Périmètre livré le 28 septembre 2026

Quatre vues d’un même calcul : Synthèse de flotte, Trajets et arrêts, Utilisation
des véhicules et Sécurité. Filtres de période (au plus 31 jours), flotte,
département et véhicules (au plus 100). Tableau triable, recherche et pagination
5/10/25/50, cinq lignes par défaut. Graphique quotidien, comparaison avec une
période précédente de même durée écoulée, exports PDF et XLSX locaux au navigateur.
Le superadmin voit son périmètre global ; les comptes clients restent dans leur
flotte. L’identité des exports provient de la personnalisation (logo de flotte
pour le client, identité globale pour le superadmin).

Les modèles personnels enregistrent les filtres et le nombre de jours, pas des
dates figées ni les résultats ; maximum 20 par utilisateur. Restaurer par défaut
rétablit sept jours, les véhicules autorisés et cinq lignes, sans effacer les
modèles. La recherche du tableau filtre les lignes et les exports ; les cartes
d’indicateurs et le graphique concernent toujours la sélection du formulaire.

Les actions cartographiques ouvrent une carte du segment ou une position d’arrêt.
L’action Enregistrements présélectionne la caméra et le jour du contexte ; cliquer
Rechercher interroge ensuite la carte SD. Aucun téléchargement, réveil caméra ou
commande de lecture n’est déclenché par le calcul du rapport. Un transfert SD en
cours est conservé et empêche le changement de contexte.

## Source et méthode, version 2

- Lecture de `dashcam_positions` ; aucune modification des relevés ni des
  paramètres d’équipement. Une caméra activée fixe par véhicule : affectation
  la plus récente puis identifiant décroissant. Pas de cumul de caméras.
- Historique limité à la plus récente des dates d’affectation caméra/véhicule et
  véhicule/flotte. Affectation inconnue : pas de lecture historique.
- Horaires Afrique/Kinshasa ; le jour courant s’arrête à la demande du rapport.
  La comparaison décale le début d’autant de jours calendaires et conserve la
  même durée écoulée, sans inventer le reste d’une journée courante.
- Coordonnées valides, bit GPS acquis, vitesse entre 0 et 250 km/h. Intervalles
  jusqu’à cinq minutes, écart géographique borné par la vitesse reçue et une
  tolérance. Les grands sauts, positions invalides et interruptions restent
  inconnus ; aucun prolongement aux limites de période.
- Mouvement candidat à partir de 3 km/h. Les pauses avec ACC allumé inférieures
  à trois minutes peuvent être jointes, uniquement sans interruption.
  Confirmation : durée au moins 60 secondes, distance cumulée au moins 100 m et
  diagonale de l’emprise GPS au moins 50 m. Sinon l’intervalle est incertain et
  retiré des totaux de distance, mouvement et couverture documentée.
- Distances haversine estimées, non corrigées sur le réseau routier. Ces seuils
  réduisent les faux trajets dus aux petites variations GPS, sans garantir une
  suppression de toutes les erreurs de localisation. Ils peuvent aussi écarter
  de très courts déplacements réels. Les dates de début/fin sont observées et
  ne garantissent pas le départ/l’arrivée réels. Aucun kilométrage odomètre.
- Arrêts affichés dès trois minutes : ACC allumé ou stationnement ACC éteint.
  ACC ne prouve pas le fonctionnement du moteur. Les jours d’utilisation sont
  les jours contenant du mouvement dans un trajet confirmé.
- Alarmes : apparition d’un bit du masque terminal, en tenant compte du dernier
  masque reçu avant la période dans le périmètre autorisé. Une alarme maintenue
  ne se répète pas à chaque point. Bits SOS, vitesse, fatigue, GPS/antenne,
  batterie/alimentation, écran/audio/caméra, collision, retournement ; autres
  bits libellés génériquement. Pas d’inférence ADAS/DMS ni de score conducteur.
- Graphique quotidien : absence de segment documenté représentée par une valeur
  manquante, pas une barre de zéro kilomètre présenté comme activité mesurée.

## Exécution et sécurité

`GenerateFleetReport` utilise exclusivement la queue database `reports`, servie
par `exadcam-reports.service` sous www-data. Timeout 75 s, une tentative, lecture
bornée à 500 000 points (période courante et précédente réunies), budget de calcul
55 s, 20 000 segments/événements, JSON privé limité à 24 Mio. Une sélection trop
volumineuse invite à réduire la période ou le parc. Au plus deux calculs récents
en attente par utilisateur. Aucun calcul coûteux dans la requête web.

Résultats JSON dans le disque Laravel privé `reports/<uuid>.json`, durée 24 h.
`exadcam-report-cleanup.timer` exécute `reports:prune` toutes les heures et retire
également les fichiers orphelins anciens après suppression d’un utilisateur.
Le téléchargement d’un rapport ne crée pas de PDF/XLSX permanent sur le serveur.
Cette durée ne change pas la rétention des extraits vidéo SD (six heures).

Chaque lecture, export, détail et exécution recontrôle l’acteur actif, son droit
`reports.generate`, sa flotte et l’empreinte des affectations. Résultat individuel
non accessible à un autre utilisateur, même superadmin. Le changement de rôle,
permissions, statut ou affectation invalide le résultat. Les coordonnées et le
tracé exigent `map.view` ; le contexte vidéo exige `video.view`. Aucun IMEI,
identifiant de connexion, protocole ou modèle technique dans les résultats.

Les fichiers de modèle, l’historique GPS et la personnalisation ne sont pas
supprimés par le nettoyage des résultats. Les fonctionnalités hors horaires,
géofences, disponibilité réseau historique, envois programmés et archivage
permanent d’incidents restent hors de ce premier lot.

## Dépendances et validation

Bibliothèques locales chargées à la demande pour les exports :
[jsPDF](https://github.com/parallax/jsPDF) 4.2.1,
[AutoTable](https://github.com/simonbengtsson/jsPDF-AutoTable) 5.0.8 et
[ExcelJS](https://github.com/exceljs/exceljs) 4.4.0, copiées par
`scripts/publish-node-assets.mjs`. Licences livrées dans `public/vendor/reports`.
Pas de CDN ni d’import de tableurs provenant de l’utilisateur. Le XLSX conserve
les types numériques, les durées, les filtres et des chaînes littérales (aucune
formule créée à partir d’un nom de véhicule). PDF : polices standard, pas de
garantie de rendu pour tous les alphabets hors français/anglais.

Audit npm du lot : aucune vulnérabilité élevée/critique dans les dépendances de
production ; deux entrées modérées liées à uuid transitif d’ExcelJS. L’avis
GHSA-w5hq-g745-h8pq concerne v3/v5/v6 avec buffer fourni. Le code ExcelJS installé
utilise uniquement v4 pour l’identifiant de formatage conditionnel ; cette
fonctionnalité n’est pas utilisée par ces exports. Le bundle officiel n’a pas
été modifié ni rétrogradé pour faire disparaître artificiellement cet avis.

Contrôles ciblés finaux : 37 tests PHP / 405 assertions (FleetReportsTest,
RecordingAccessTest, CustomizationTest) sur SQLite en mémoire et stockage factice,
GD activé directement dans Pest. Un test Node crée un vrai PDF et relit un XLSX :
entête PDF, structure du classeur, valeurs numériques/durées, texte commençant
par `=` conservé comme chaîne, fuseau Kinshasa. Pint, syntaxes JS et diff --check
réussis. Pas de prétention de suite exhaustive ni de nouveau test matériel.

Déploiement initial : `/var/backups/exadcam-reports-20260928-130549`, 34 fichiers,
migration des tables de résultats/modèles et activation du worker/nettoyage.
Affinage des trajets : `/var/backups/exadcam-reports-refinement-20260928-131323`.
Seul le nouveau worker Rapports a été rechargé ; les processus GPS, vidéo, audio,
enregistrements et monitoring existants ont été conservés. Pas de purge du cache
des baux vidéo. Retour arrière : arrêter uniquement le worker/timer Rapports,
restaurer les sources sauvegardées ; conserver les nouvelles tables privées en
attendant une décision sur leur suppression, sans migration globale inverse.


Vérifications navigateur de production terminées : rapport réel sur sept jours, les quatre vues, pagination de cinq lignes, passage à la page suivante et carte contextuelle. Exports réellement téléchargés dans le navigateur : PDF valide (en-tête %PDF) et XLSX relu avec trois feuilles, quatre lignes de synthèse, valeurs numériques, filtres et image du logo. Modèle temporaire créé, restauré après retour aux valeurs par défaut, puis supprimé ; aucun modèle de test conservé. Accès Enregistrements depuis une alarme : bon véhicule et bon jour présélectionnés, aucune requête SD déclenchée automatiquement. Bureau 1536 px, tablette 820 px et mobile 390 px vérifiés sans débordement horizontal de page ; dates Du/Au alignées côte à côte sur mobile après rechargement de la version 3. Taille du navigateur restaurée en fin de vérification.

Dernière synchronisation des sources et documents : /var/backups/exadcam-reports-docs-20260928-132412. Worker de rapport et timer actifs, dernière exécution du nettoyage réussie. Les processus des cinq services préexistants sont inchangés. La durée observée du premier calcul sur les données réelles était d’environ 0,8 s côté worker ; ce constat ne garantit pas le temps de toutes les périodes ou tailles de flotte.
