# Base de données et accès EXADCAM

État au 16 septembre 2026.

## Base locale

L’application utilise MySQL/MariaDB sur `127.0.0.1:3306`, base **exadcam**.
Les paramètres sont dans `.env`, avec un exemple sans secret dans `.env.example`.
La clé d’application EXADCAM a été conservée. La base SQLite initiale ne contenait
aucun utilisateur et reste disponible dans le projet ; elle n’est plus utilisée
par l’application. Les tests automatisés continuent à utiliser SQLite en mémoire.

La base `exad_tracking` et son code ont été inspectés en lecture seule. Aucune
donnée métier, aucun compte, jeton, mot de passe, secret 2FA ou session de cette
application n’a été importé dans EXADCAM.

## Comparaison effectuée sur la base réelle

| Table | Reprise dans EXADCAM |
| --- | --- |
| `users` | 21 colonnes identiques : types, ordre, valeurs par défaut, index et clés étrangères vérifiés |
| `subscriptions` | 9 colonnes, index et relations identiques |
| `fleets` | 8 colonnes, index et relations identiques ; abonnement facultatif |
| `fleet_user` | Liaison utilisateurs/flottes ; permission `viewer` ou `manager` ; clé composée |
| `user_login_histories` | Historique des connexions avec utilisateur, appareil, adresse IP et date |
| `sessions`, `password_reset_tokens`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Tables Laravel du socle EXADCAM |

Les 21 colonnes de `users` sont : `id`, `subscription_id`, `fleet_id`,
`created_by`, `name`, `email`, `email_verified_at`, `role`, `status`,
`disabled_at`, `permissions`, `phone`, `address`, `profile_photo_path`,
`password`, `two_factor_secret`, `two_factor_recovery_codes`,
`two_factor_confirmed_at`, `remember_token`, `created_at`, `updated_at`.

Une différence volontaire concerne `user_login_histories.logged_in_at` : la date
de connexion reste fixe lors d’une modification ultérieure de la ligne. La base
de référence lui appliquait automatiquement la date de dernière modification.

## Rôles et permissions

EXAD Tracking n’a pas de tables `roles`, `permissions`, `model_has_roles` ou
`model_has_permissions`. Son modèle est repris sans ajouter une autre bibliothèque :

- `users.role` : `superadmin`, `admin`, `user`, avec enum PHP `UserRole`.
- `users.status` et `disabled_at` : un compte est actif uniquement si son statut
  est `active` et si `disabled_at` est nul.
- `users.permissions` : liste JSON des droits individuels.
- `fleet_user.permission` : niveau d’accès à une flotte, distinct des droits individuels.

Les codes repris de la référence sont `map.view`, `reports.generate`,
`engine.control`, `garages.manage`, `maintenance.manage`. Ces codes constituent
le socle des autorisations ; leur présence ne signifie pas que les fonctions
métier ou les commandes des équipements sont déjà implémentées. Les droits vidéo
seront ajoutés avec les modules correspondants.

Un superadmin ou un admin actif possède les droits connus de ce catalogue. Un
utilisateur simple doit avoir reçu le droit demandé dans `permissions`. Un droit
inconnu est refusé. `Fleet::visibleTo()` limite les comptes ordinaires à leur
flotte principale ; seul un superadmin actif voit toutes les flottes.

Les middleware `active`, `superadmin` et `client.permission` sont disponibles.
Les routes authentifiées existantes utilisent `active`. Un compte désactivé ne
peut pas se connecter et sa session est révoquée à la prochaine requête protégée.
La gestion des utilisateurs applique ces contrôles via `UserPolicy` et les routes
authentifiées ; les autres écrans d’administration devront également les appliquer.

## Compte initial local

Le compte demandé a été créé explicitement dans `exadcam` : nom `superadmin`,
adresse `superadmin@erp.loc`, rôle `superadmin`, statut `active`. Son mot de passe
est haché par Laravel et n’est présent dans aucun seeder ni fichier du projet.
Ses rattachements à une flotte ou à un abonnement restent nuls : il est global.

Pour créer ensuite un compte depuis un terminal :

```sh
php artisan app:create-user --role=superadmin
```

La commande demande le nom, l’adresse et le mot de passe masqué avec confirmation.
Sans `--role`, elle crée un `user`. Elle refuse un rôle inconnu et n’écrase jamais
un compte existant. Les migrations et `DatabaseSeeder` ne créent aucun compte partagé.

## Historique et suites

Une connexion réussie enregistre l’utilisateur, l’appareil, l’adresse IP et la date
dans `user_login_histories`. Les échecs ne créent pas d’historique de connexion réussie.
Les mots de passe, jetons de mémorisation et secrets 2FA restent masqués dans les
représentations JSON du modèle.

Les tables de référence `personal_access_tokens`, `mobile_sessions` et `passkeys`
ont aussi été inspectées. Elles ne sont pas créées à ce stade : l’application mobile
et la connexion par passkey ne sont pas encore implémentées. Les colonnes 2FA sont
présentes pour garder la structure de `users`, mais l’activation de la double
authentification attend son parcours d’enrôlement et de récupération.

Le tableau de bord de flotte affiche encore des données de démonstration. La
[gestion des utilisateurs](user-management.md) lit et modifie les comptes réels.
Ses formulaires synchronisent le rattachement principal et `fleet_user`, protègent
les superadmins, restreignent les admins à leur flotte et invalident les sessions
du compte lors d’un changement de ses accès. Aucune migration supplémentaire n’a
été nécessaire. Les écrans de flottes et abonnements, l’envoi d’e-mails et les
modules GPS/vidéo restent les prochains lots de développement.

## Base du serveur de production — 21 septembre 2026

La base décrite précédemment et son superadmin sont ceux de l’environnement local.
Sur le serveur `62.171.190.15`, une base `exadcam` distincte a été créée, encodage
`utf8mb4`, collation `utf8mb4_unicode_ci`. Elle était vide lors de la préparation initiale ; le déploiement décrit ci-dessous a ensuite exécuté les migrations.

- Application : `'exad_cam_user'@'localhost'`, droits sur `exadcam.*` uniquement.
  Connexion PDO à `127.0.0.1:3306` et lecture/écriture dans une table temporaire
  vérifiées. Ce compte est configuré dans le `.env` de production.
- Administration web : `'phpmyadmin'@'localhost'`, droits sur `exadcam.*`
  uniquement, sans droits globaux ni gestion générale des comptes MariaDB.
  URL `https://exadcam.app/phpmyadmin/` ; identifiant de formulaire `phpmyadmin`.
  Mot de passe demandé configuré, connexion HTTPS authentifiée vérifiée.
- Préférences internes phpMyAdmin : base technique `phpmyadmin` et compte
  `exad_pma_control@localhost`, avec quatre droits DML limités à cette base.

MariaDB écoute seulement sur `127.0.0.1:3306`. L’administration système reste
possible avec `sudo mariadb`, via Unix socket. Aucun secret de connexion n’est
inscrit dans le dépôt, cette documentation ou un seeder. Le `.env` de production est distinct du local et protégé sur le serveur. Aucun export de données locales n’a été importé.
Voir [server-infrastructure.md](server-infrastructure.md).

## Registre d'écoute et déploiement applicatif — 21 septembre 2026

Les migrations ont été appliquées à la base de production ; un compte superadmin
a été créé avec les identifiants demandés, par la commande interactive existante.
Le contrôle final compte un utilisateur et zéro dashcam. Aucun IMEI réel, y compris
l'ancien matériel d'essai, n'est provisionné par un seeder ou par le déploiement.

Migration `2026_09_21_220000_create_dashcam_listener_tables` :

- `dashcams` : IMEI unique de 15 chiffres, nom, activation, canaux, cadence vidéo,
  identifiants protocolaires exacts JT808 2013/2019 et JT1078, dernier contact et position.
- `dashcam_positions` : positions reçues, état du fix GPS et données de mouvement,
  avec relation à la dashcam. Les positions hors ordre n'écrasent pas la plus récente.
- Code d'authentification terminal généré lors de l'inscription, chiffré avec la
  clé Laravel et masqué dans les représentations JSON ordinaires. Le conserver
  avec la clé applicative dans toute future stratégie de restauration.

Inscription et commandes vidéo réservées au superadmin pour cette première étape.
API listener limitée à la boucle locale, avec jeton partagé côté serveur. Aucun
équipement inconnu n'est créé à réception d'une trame. Désactivation persistée
avant révocation ; les listeners revérifient périodiquement leurs autorisations.
Les identifiants protocolaires sont des alias configurés, pas une authentification
cryptographique du matériel. Les flux natifs JT808/JT1078 de ce socle utilisent TCP
sans TLS ; la consultation web utilise HTTPS.

Les schémas sont présents dans le code local et migrés en production. Cette étape
n'a pas exécuté la nouvelle migration sur la base métier locale. Les tests Laravel
utilisent leur base de test isolée. Droits vidéo par flotte, politique de rétention
des positions et synchronisation avec le dashboard restent à compléter.

## Raccordement réel — 22 septembre 2026

Deux fiches de dashcams autorisées ont été créées en production : JK114 et
ES500-603, avec les IMEI explicitement fournis. JK114 authentifiée en JT808 2019,
GPS reçu et deux flux H.264 validés côté serveur. ES500-603 toujours sans contact
observé ; sa configuration backup doit être testée comme serveur principal.

Deux migrations ajoutent le fuseau GPS nullable par équipement et étendent la
capacité de `video_terminal_id` à 20 caractères. Profil JK114 réglé sur UTC+1 et
identité vidéo étendue observée. Après sauvegarde, 145 horodatages GPS initiaux
de cet appareil ont été corrigés de sept heures avec sélection bornée et contrôlée.
Les copies Node exécutées et les sources applicatives sont mises à jour ; services
relancés et reconnexion réelle confirmée. Aucun firmware/configurateur modifié.

Sauvegardes et captures de diagnostic privées sous
`/var/backups/exadcam-devices-20260922`. Les migrations sont en production ; aucune
donnée réelle n'a été copiée dans la base métier locale ni dans les tests.
Voir [device-commissioning.md](device-commissioning.md) pour les preuves et limites.
