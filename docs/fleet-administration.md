# Administration et accès par flotte

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

## Navigation des comptes de flotte — 22 septembre 2026

Les admins et utilisateurs de flotte ont des entrées directes Véhicules, Dashcams
et Départements selon leurs autorisations. Le groupe Flottes et son lien de gestion
n'apparaissent plus pour ces comptes. Le superadmin conserve le groupe Flottes.
Ce changement de présentation ne modifie ni les permissions ni le périmètre serveur.


## Permission microphone — 23 septembre 2026

audio.talk autorise Parler, uniquement avec video.view et dans la flotte permise.
Écouter utilise video.view. Les comptes normaux nécessitent une délégation explicite
du microphone ; les admins/superadmins conservent leurs droits de périmètre.
Le retrait d’un droit, la désactivation ou un transfert d’affectation invalide les
baux audio contrôlés par le serveur. Voir [live-audio.md](live-audio.md).
