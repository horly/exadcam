# Gestion des utilisateurs

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

Module livré le 16 septembre 2026, accessible par le menu Utilisateurs (`/#users`).
Il utilise les comptes réels de la base EXADCAM. EXAD Tracking a été consulté en
lecture seule ; aucun compte, historique ou secret n’a été importé.

## Fonctions reprises de la référence

| EXAD Tracking | EXADCAM |
| --- | --- |
| Liste, recherche, tri, pagination de cinq lignes | Liste AJAX ; recherche par nom, e-mail, rôle ou téléphone, tri autorisé et tailles de page 5/10/25/50 |
| Superadmin en premier, puis comptes récents | Même ordre par défaut ; tri interactif tout en conservant le superadmin en premier |
| Création et édition en modale | Modales Bootstrap locales, thème bleu nuit, réponses et validation dynamiques |
| Identité et contact | Nom, e-mail, téléphone, adresse |
| Rôle et affectation | Utilisateur ou Admin, flotte active obligatoire, liaison `fleet_user` viewer/manager synchronisée |
| Permissions individuelles | Catalogue de permissions JSON existant repris ; administrateur doté des droits de sa flotte |
| Mot de passe et confirmation | Minimum 12 caractères, majuscule/minuscule, chiffre, symbole ; limite de 72 octets pour bcrypt, critères dynamiques et affichage/masquage |
| Mot de passe facultatif en édition | Deux champs vides conservent le mot de passe actuel |
| Suppression confirmée | Modale de confirmation, suppression du compte, de ses liaisons et de son historique |
| Historique des connexions | Appareil, IP et date, recherche/tri/pagination serveur par cinq lignes, sans limiter arbitrairement la consultation aux 12 premières connexions |
| Compte superadmin protégé | Aucune création de superadmin ni modification/suppression de ces comptes depuis cette page |
| Français et anglais | Catalogues `lang/fr/users.php` et `lang/en/users.php` |

Le statut actif/inactif est affiché ; la page de référence n’expose pas de bouton
d’activation/désactivation, et ce module n’en ajoute pas. Les permissions vidéo
et les fonctionnalités matérielles correspondantes restent à définir avec leurs
modules. Les droits existants ne signifient pas que les écrans métier sont livrés.

## Accès et sécurité

- Superadmin actif : liste globale, création et gestion des comptes admin/user.
- Admin actif : uniquement les utilisateurs simples de sa flotte principale.
  Impossible d’élever leur rôle, de les transférer ailleurs, ou de gérer un autre admin.
- Admin sans flotte : liste et choix de flotte vides, création bloquée. Aucun accès
  aux comptes sans affectation.
- Utilisateur simple : menu absent, accès aux routes refusé.
- Compte désactivé : middleware existant `active`, session invalidée.
- Consultation d’un compte d’une autre flotte : réponse 404 ; superadmin protégé
  contre l’édition et la suppression avec réponse 403.
- `UserPolicy` est découverte par Laravel ; contrôles appliqués aux routes et aux
  boutons. `SaveUserRequest` valide les données avant écriture. Les colonnes sensibles
  (créateur, statut, secrets 2FA, token, abonnement…) ne sont pas assignables depuis
  le formulaire, même si elles sont ajoutées à une requête forgée.
- Écriture et synchronisation des flottes dans une transaction. Flotte active
  revérifiée sous verrou. E-mail normalisé en minuscules et unique.
- Changement de mot de passe/e-mail/rôle/flotte/permissions : renouvellement du
  jeton de mémorisation et révocation des sessions du compte si le pilote de session
  est `database`, comme dans la configuration locale. L’e-mail modifié perd sa
  date de vérification. Le parcours de vérification par e-mail n’est pas livré.
- Routes sous `web`, `auth`, `active`, CSRF et protection contre le cache des pages.
  Erreurs 422 sous les champs, traitement des sessions expirées et échecs réseau.
- Valeurs utilisateur échappées par Blade, données JavaScript encodées avec
  `Js::encode`, aucune interpolation de données saisies dans du HTML côté client.

## Routes et fichiers

| Route | Usage |
| --- | --- |
| `GET /users` | Tableau JSON ; une visite HTML redirige vers `/#users` |
| `GET /users/options` | Flottes actives autorisées pour le formulaire |
| `POST /users` | Création (201) |
| `PUT/PATCH /users/{user}` | Modification |
| `DELETE /users/{user}` | Suppression |
| `GET /users/{user}/login-history` | Historique autorisé et paginé |

Contrôleurs : `UserController`, `UserLoginHistoryController`.
Validation : `SaveUserRequest`. Autorisation : `UserPolicy`.
Vues : `resources/views/users/{module,table,history,pagination}.blade.php`.
Présentation et interactions : `public/css/users.css`, `public/js/users.js`.
La recherche annule les requêtes dépassées ; la liste est chargée à l’ouverture
de la rubrique et dispose d’un bouton d’actualisation. Aucun plugin ni CDN ajouté.

## État local et vérifications

La base réelle contient le compte superadmin provisionné précédemment. Elle ne
contient pas encore de flotte active. Le formulaire affiche cette situation et
bloque l’enregistrement ; le développement du module Flottes permettra de créer
les affectations nécessaires. Aucun faux compte ou fausse flotte n’a été ajouté
dans cette base pour valider la page.

Suite complète : **131 tests réussis, 615 assertions**. Les 44 nouveaux cas portent
sur les routes utilisateurs et l’historique : rôles, isolation, validation,
transactions observables, permissions, mots de passe, sessions, suppression,
CSRF, échappement et traductions. Base SQLite de test isolée, clé Maps neutralisée.
Pint et syntaxe JavaScript vérifiés.

Dans le navigateur existant : tableau réel, compte superadmin protégé, formulaire,
absence de flotte, validation d’e-mail et affichage de l’historique vérifiés.
Le test navigateur complet des écritures a été préparé avec une autre base SQLite.
L’accès à son instance locale sur le port 8012 a été refusé par l’approbation
automatique du navigateur après expiration de son délai ; aucun contournement
n’a été tenté. Cette instance a été arrêtée. Le CRUD est validé par les tests HTTP,
mais son parcours navigateur de bout en bout reste à vérifier.
