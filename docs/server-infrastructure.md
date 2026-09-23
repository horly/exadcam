# Infrastructure du serveur EXADCAM

État vérifié le 21 septembre 2026 (Africa/Kinshasa).

## Accès et périmètre

- Serveur : `62.171.190.15`, hôte `vmi3599783`, Ubuntu 24.04.5 LTS.
- La bannière système indique Contabo. Les comparatifs Netcup antérieurs ne
  décrivent pas nécessairement le serveur finalement communiqué.
- Connexion : `ssh exad-cam@62.171.190.15`, puis `sudo` pour administrer les services.
- Domaine configuré : `https://exadcam.app` uniquement.
- Résolution A observée : `62.171.190.15`. Aucun AAAA retourné lors de la vérification.
- L'accès SSH, ses mots de passe et la configuration SSH existante n'ont pas été
  modifiés pendant la préparation Apache/MariaDB/HTTPS.

## Composants installés

| Composant | Version de paquet vérifiée | État |
| --- | --- | --- |
| Apache | `2.4.58-1ubuntu8.15` | Actif et activé au démarrage |
| MariaDB | `1:10.11.14-0ubuntu0.24.04.1` | Actif et activé au démarrage |
| OpenSSL | `3.0.13-0ubuntu3.15` | Déjà présent, vérifié lors de l'installation |
| Certbot | `2.9.0-1` | Certificat installé et renouvellement planifié |
| Plugin Apache Certbot | `2.9.0-1` | Émission et installation du certificat |
| PHP CLI / FPM | `8.2.33-1+ubuntu24.04.1+deb.sury.org+1` | FPM actif et activé au démarrage |
| Composer | `2.10.3` | Installé et exigences du projet vérifiées |
| phpMyAdmin | `5.2.3` | Connexion HTTPS authentifiée vérifiée |
| Node.js | `24.21.0` LTS | Services GPS et vidéo actifs |
| FFmpeg | `6.1.1-3ubuntu5` | Conversion H.264 vers HLS testée |

Paquets installés via les dépôts Ubuntu : Apache, MariaDB, Certbot et son plugin
Apache, OpenSSL, curl et certificats CA. Aucun redémarrage système ni mise à
niveau générale de la distribution réalisé. Un redémarrage pour un noyau déjà
en attente était signalé avant l'intervention ; il reste à planifier.

## Apache et domaine

- Racine publique : `/var/www/exadcam/public`, propriétaire `exad-cam:www-data`.
- HTTP : `/etc/apache2/sites-available/exadcam.app.conf`.
- HTTPS : `/etc/apache2/sites-available/exadcam.app-le-ssl.conf`.
- Configuration serveur : `/etc/apache2/conf-available/zz-exadcam-server.conf`.
- Modules activés : `rewrite`, `ssl`, `headers`, `proxy_fcgi`, `setenvif` et dépendances Apache.
- Site par défaut désactivé ; index de répertoire désactivé ; réécritures
  `.htaccess` autorisées dans la racine publique pour Laravel.
- Redirection HTTP vers HTTPS (301), TLS 1.2/1.3, version Apache masquée dans
  les réponses HTTP. En-têtes nosniff, SAMEORIGIN et politique de référent.
- Journaux : `/var/log/apache2/exadcam-access.log` et `exadcam-error.log`.
- Page d'attente statique : `/var/www/exadcam/public/index.html`, en français,
  aux couleurs EXADCAM, avec instruction noindex/nofollow.

La page d'attente ne représente pas le déploiement de l'application Laravel.
Le futur déploiement devra remplacer cette page et fournir `public/index.php`.

## MariaDB

- Écoute TCP limitée à `127.0.0.1:3306` ; aucune écoute publique de la base.
- Administration locale : `sudo mariadb`, via l'authentification Unix socket.
- Aucun compte anonyme, aucun compte root distant et aucune base de test observés.
- Aucun mot de passe système réutilisé pour un compte applicatif MariaDB.
- Base de production `exadcam` créée, `utf8mb4_unicode_ci`, actuellement vide.
- Comptes locaux `exad_cam_user` (Laravel) et `phpmyadmin` (administration web),
  mots de passe demandés configurés ; droits sur `exadcam.*` uniquement, sans
  privilèges globaux ni GRANT OPTION. Lecture/écriture PDO vérifiées pour les deux.
- La base technique `phpmyadmin` stocke les préférences de son interface ; son
  compte technique est distinct des deux comptes demandés.
- Aucune migration Laravel exécutée et aucune donnée métier locale copiée.

## PHP, Composer et phpMyAdmin

- PHP **8.2.33**, CLI et FPM, paquets versionnés du dépôt signé `ppa:ondrej/php`.
- Extensions ajoutées : MySQL/PDO, mbstring, XML/DOM, cURL, ZIP, BCMath, Intl,
  GD, OPcache, SQLite/PDO SQLite et BZip2. Les modules intégrés complètent les
  exigences Laravel et phpMyAdmin ; 30 extensions vérifiées via une requête FPM.
- Apache : `proxy_fcgi`, `setenvif`, configuration `php8.2-fpm` activée ; socket
  `/run/php/php8.2-fpm.sock`. Service `php8.2-fpm` actif et activé au démarrage.
- Réglages : `/etc/php/8.2/fpm/conf.d/99-exadcam.ini` (256 Mo de mémoire,
  upload 64 Mo, POST 80 Mo, délai 120 s, OPcache, erreurs non affichées,
  cookies de session Secure/HttpOnly/SameSite=Lax et Africa/Kinshasa).
  `/etc/php/8.2/cli/conf.d/99-exadcam.ini` définit le fuseau et la mémoire CLI
  sans limite, notamment pour les futures opérations Composer.
- Composer **2.10.3** : `/usr/local/bin/composer`, installateur officiel contrôlé
  par SHA-384. À utiliser sous `exad-cam` pour le futur déploiement.
- phpMyAdmin **5.2.3** : distribution officielle contrôlée par SHA-256,
  `/usr/local/share/phpMyAdmin-5.2.3-all-languages`, lien `/usr/local/share/phpmyadmin`.
- Accès : **https://exadcam.app/phpmyadmin/**. Identifiant **`phpmyadmin`**, mot de
  passe fourni par l’utilisateur et configuré. `@localhost` est la partie hôte
  du compte MariaDB et ne doit pas être saisie dans le formulaire de connexion.
- Alias défini dans `/etc/phpmyadmin/apache.conf`, inclus uniquement dans le
  vhost HTTPS. Répertoires setup/libraries/templates/vendor/sql et fichiers
  de configuration non accessibles en HTTP. Les assets publics restent disponibles.
- Configuration : `/etc/phpmyadmin/config.inc.php`, `root:www-data`, mode 640.
  Authentification cookie, comptes sans mot de passe et compte root refusés dans
  l’interface, serveurs arbitraires désactivés. Temporaire `/var/lib/phpmyadmin/tmp`
  hors racine web, mode 750, propriétaire `www-data`.
- Base technique `phpmyadmin` et compte séparé `exad_pma_control@localhost`,
  limité à SELECT/INSERT/UPDATE/DELETE sur ses métadonnées. Son secret généré et
  la clé de chiffrement des cookies restent uniquement dans la configuration serveur.

phpMyAdmin et Composer sont installés manuellement : leurs mises à jour ne sont
pas gérées par les paquets Ubuntu. Vérifier les nouvelles versions officielles
et leurs sommes de contrôle avant mise à jour. PHP 8.2 suit le dépôt ajouté.
Sources d’installation : [dépôt PHP](https://launchpad.net/~ondrej/+archive/ubuntu/php),
[phpMyAdmin](https://www.phpmyadmin.net/downloads/),
[Composer](https://getcomposer.org/download/).

## Certificat HTTPS et renouvellement

- Certificat Let's Encrypt valide pour `exadcam.app` (SAN vérifié).
- Émetteur observé : Let's Encrypt YE2.
- Validité initiale : 21 septembre au 20 décembre 2026 (expiration à 19:34:41 UTC).
- Certificat : `/etc/letsencrypt/live/exadcam.app/fullchain.pem`.
- La clé privée reste sur le serveur ; aucun contenu secret n'est copié ici.
- `certbot.timer` actif et activé au démarrage, avec tâche planifiée.
- Test réussi : `sudo certbot renew --cert-name exadcam.app --dry-run --non-interactive`.
- Compte ACME créé sans adresse e-mail ; la surveillance du renouvellement
  reste à intégrer à la future supervision.
- Conserver le port HTTP 80 accessible pour les validations HTTP-01 automatiques.

## Vérifications réalisées

- `apache2ctl configtest` : Syntax OK ; hôtes virtuels HTTP et HTTPS présents.
- Apache, MariaDB et certbot.timer : actifs et activés au démarrage.
- `mariadb-admin ping` : mysqld is alive ; version et comptes locaux vérifiés.
- Écoutes : SSH 22, HTTP 80, HTTPS 443 ; MariaDB 3306 sur boucle locale uniquement.
- Depuis le poste externe : HTTP 301 vers HTTPS, HTTPS 200 avec validation TLS
  normale et contenu de la page d'attente, sans option ignorant les certificats.
- Vérification OpenSSL du sujet, de l'émetteur, du SAN et de la validité du certificat.
- Simulation de renouvellement Certbot réussie.
- Exigences PHP du vrai `composer.lock` : `composer check-platform-reqs --lock
  --no-dev` réussi, exécuté sous `exad-cam` dans un dossier isolé, sans déploiement.
- PHP-FPM 8.2.33 confirmé par une requête HTTPS ; 30 extensions et réglages
  vérifiés. Script PHP de contrôle supprimé dans un bloc `finally` après lecture.
- Connexions PDO applicative TCP et administrative locale, création et écriture
  de tables temporaires réussies. Base `exadcam` confirmée sans table permanente.
- Connexion phpMyAdmin par HTTPS réussie, base visible et session de test fermée.
  Aucun avertissement de stockage de configuration détecté dans la page testée.
- phpMyAdmin répond HTTP 200 depuis le poste externe avec validation TLS normale.
  Fichiers config.inc.php/composer.json, setup et SQL internes renvoient HTTP 403.

## Déploiement Laravel et listeners — 21 septembre 2026

Les contrôles précédents décrivent les étapes initiales. Laravel a ensuite été
déployé depuis le projet local, sans `.env`, base locale, journaux ni dépendances
de développement. Racine `/var/www/exadcam`, propriétaire `exad-cam:www-data` ;
seuls storage et bootstrap/cache sont inscriptibles par PHP. `.env` en mode 640.
Le fichier d'attente index.html reste secondaire, DirectoryIndex privilégiant index.php.

`composer install --no-dev --prefer-dist --optimize-autoloader` : 99 packages.
Migrations exécutées et caches Laravel reconstruits. Compte superadmin demandé
provisionné séparément avec mot de passe haché. Aucun mot de passe dans les seeders.
Connexion HTTPS, dashboard authentifié et registre Dashcams vide vérifiés.
Le build Vite et les ressources Bootstrap, ApexCharts et hls.js sont locaux.
Pas de worker queue ni tâche planifiée ajoutés : le parcours livré est synchrone.

Node.js est installé depuis l'archive officielle, SHA256 vérifié, dans
`/opt/node-v24.21.0-linux-x64`, avec liens `/usr/local/bin/node` et npm.
Sa mise à jour devra être suivie séparément. FFmpeg vient des paquets Ubuntu.

| Élément | Configuration effective |
| --- | --- |
| Code Node | `/opt/exadcam-listener`, propriété root |
| Identité des services | `exad-listener`, sans shell interactif |
| Configuration privée | `/etc/exadcam/listener.env`, root:exad-listener 640 |
| Tampon vidéo temporaire | `/var/lib/exadcam-media`, exad-listener 750 |
| GPS / commandes | TCP public 7808, JT808 2013 et 2019 |
| Entrée vidéo | TCP public 1078, JT1078 H.264 |
| Coordination Node | 127.0.0.1:3001 et 127.0.0.1:3002, jeton serveur |
| Laravel interne | 127.0.0.1:8081, jeton serveur et contrôle d'adresse locale |
| Lecture web | HTTPS `/live-media/`, URL temporaire par session de lecture |

Units : `/etc/systemd/system/exadcam-gps.service` et `exadcam-video.service`.
Services actifs, activés au démarrage, redémarrage sur sortie, privilèges réduits,
système de fichiers protégé. Limites mémoire 512 Mo GPS et 2 Go vidéo ; plafond
initial 8 flux simultanés à confirmer par tests de charge.
Les templates versionnés sont dans `deployment/`. Le jeton partagé avec Laravel
est généré sur le VPS et n'est jamais présent dans ces templates.

Apache : proxy_http activé, configuration locale `exadcam-listener.conf` et proxy
HTTPS vers `/media/` sur Node 3002. Les routes `/api/internal/listener` sont refusées
dans le vhost public. Les URL temporaires vidéo sont exclues de l'access log HTTPS.
MariaDB reste en boucle locale. UFW était inactif et n'a pas été modifié.

Contrôles finaux : syntaxe Apache et unités systemd, tous services actifs, ports
7808/1078 accessibles depuis le poste externe, API privées refusant l'absence de
jeton, IMEI inconnu refusé, API Laravel interne refusée via HTTPS public, média
inexistant renvoyant 404, login et phpMyAdmin renvoyant 200 avec TLS vérifié.
Sept tests Node réussis sous Linux, dont conversion d'une vidéo synthétique et
révocation de lecture. Aucun matériel ni IMEI réel enregistré ; zéro dashcam en base.

Sauvegardes de retour arrière locales : `/var/backups/exadcam-deploy-20260921`
et `/var/backups/exadcam-listener-20260921` (application, SQL et configuration).
Les archives contenant la configuration restent sous root, répertoire 700.
Les sauvegardes externes, une politique automatique de conservation, la supervision
et la réplication ne sont pas installées. Aucun redémarrage système effectué.

Voir [listener-server.md](listener-server.md) pour les commandes de contrôle,
le protocole de raccordement, les limites et la procédure des essais matériels.

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
