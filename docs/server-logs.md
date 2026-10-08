# Logs serveur EXADCAM

Livré le 28 septembre 2026. Référence EXAD Tracking consultée en lecture seule pour le parcours de consultation ; aucune console ou commande distante ajoutée.

## Utilisation

Menu **Logs serveur**, après Utilisateurs, uniquement pour le superadmin actif. Sources réelles : GPS TCP, Vidéo en direct, Audio, Enregistrements et Laravel. Affichage de 300 lignes par défaut, choix 100/300/600/1000, rafraîchissement toutes les cinq secondes, Pause/Reprendre et Rafraîchir manuel. Recherche textuelle, filtre Erreurs et option de suivi des dernières lignes. La pause conserve l’affichage ; changer de source ou cliquer Rafraîchir permet une lecture ponctuelle. Les requêtes automatiques s’arrêtent lorsque le menu ou l’onglet est masqué.

La fenêtre de lecture contient au plus 1 000 dernières lignes disponibles, dans la limite de taille du collecteur. Les filtres s’appliquent à cette fenêtre, pas à tout l’historique. Les horodatages ajoutés par le collecteur et les préfixes Laravel sont affichés en heure de Kinshasa ; les éventuels champs UTC internes des messages JSON restent ceux du service source. Le panneau indique le nombre affiché et l’heure de lecture ; Laravel indique aussi sa dernière écriture. Un collecteur âgé de plus de 45 secondes est explicitement signalé comme non actualisé.

## Accès et limites

- Endpoint GET `/server-logs/content`, authentification, compte actif, middleware superadmin et contrôle supplémentaire dans le contrôleur ; limite de 40 requêtes/minute par compte. Aucun endpoint de commande, effacement ou téléchargement de fichier arbitraire.
- Sources, tailles et filtres sur listes fermées ; recherche limitée à 100 caractères. Lecture de fichiers autorisés uniquement, rejet des liens symboliques directs et des chemins hors du répertoire configuré. Réponses `no-store, private`.
- Lecture PHP bornée : snapshots JSON au plus 1 Mio, contenu au plus 512 Kio ; Laravel lit au plus les 512 derniers Kio du journal courant/le plus récemment modifié et conserve au plus 1 000 lignes. Les fichiers `laravel.log` et `laravel-YYYY-MM-DD.log` sont admis.
- Masquage avant réponse des champs password/passwd/secret/token/API key/authorization/cookie, des identifiants Bearer/Basic, des mots de passe dans les URL et des blocs de clé privée reconnus. Ce masquage cible les formats explicitement reconnus ; continuer à éviter les secrets dans les journaux source.
- Le navigateur affiche le contenu avec `textContent`, sans interprétation HTML. Les journaux détaillés restent sur le serveur ; aucune copie dans le dépôt.

## Collecteur système

Les quatre listeners écrivent dans journald. Un service distinct `exadcam-log-snapshot.service`, déclenché par `exadcam-log-snapshot.timer` toutes les cinq secondes après sa fin, lit uniquement les unités fixées dans le script et remplace atomiquement quatre snapshots. Aucun paramètre fourni par la page n’est transmis à un programme système.

Script source : `deployment/scripts/exadcam-log-snapshot.py`, installé root:root sous `/usr/local/libexec/`, mode 0755. Compte système `exad-log-reader`, sans shell ni répertoire personnel, groupe de service `www-data`, groupe supplémentaire `systemd-journal`. Il ne reçoit aucune capacité ni droit sudo. Service protégé avec `NoNewPrivileges`, `ProtectSystem=strict`, `ProtectHome`, espace temporaire privé, limites mémoire/temps/taille et accès réseau limité à AF_UNIX.

Snapshots : `/var/lib/exadcam-logs`, propriétaire exad-log-reader, groupe www-data, répertoire 0750 et fichiers 0640. Le serveur web peut les lire mais ne peut pas les modifier ; il n’obtient pas l’accès général au journal système. Les snapshots sont remplacés, pas archivés ; la rétention des journaux source reste celle de journald/Laravel.

Chaque collecte utilise les 1 000 dernières entrées d’une unité, avec délai de trois secondes par lecture, tampon de lecture de 2 Mio, contenu final de 256 Kio maximum et messages individuels tronqués à 8 192 caractères. Une erreur de lecture est publiée explicitement, sans contenu simulé. La présence du service et ses permissions sont indispensables aux quatre sources système ; Laravel reste lu directement par PHP.

Configuration Laravel : `config/server_logs.php`. Sources des unités : `deployment/systemd/exadcam-log-snapshot.service` et `.timer`. Installer le compte/répertoire et le script, vérifier les unités, recharger systemd puis activer le timer. Mettre à jour le cache de configuration Laravel et purger uniquement les vues compilées ; ne pas vider le cache des sessions de direct.

## Validation et déploiement

28 septembre : 20 tests PHP ciblés / 190 assertions (ServerLogsTest et RecordingAccessTest), SQLite en mémoire ; permissions, désactivation, paramètres invalides, absence de console, bornes de lecture, filtres, états manquant/corrompu/périmé et masquage des secrets. Quatre tests Python exécutés sous Linux : formats, limites, refus d’unité arbitraire et erreur du lecteur. Syntaxes PHP/JS, Pint et git diff --check contrôlés. Il ne s’agit pas d’une suite exhaustive ni d’un test de charge.

Déploiement après sauvegarde `/var/backups/exadcam-server-logs-20260928-122003`. Collecteur et timer actifs, snapshots renouvelés et lecture seule par www-data vérifiés. GPS, vidéo, audio et enregistrements ont conservé leurs quatre processus ; aucun redémarrage ni commande envoyée aux caméras. Configuration et vues Laravel actualisées.

Navigateur de production : cinq sources réelles disponibles, 300 lignes GPS/vidéo/audio, 21 lignes enregistrements et 28 lignes Laravel lors du contrôle ; chiffres susceptibles d’évoluer. Pause conservée, rafraîchissement ponctuel en pause, filtre Erreurs, recherche sans résultat, changement de taille et reprise automatique vérifiés. Présentation contrôlée aux largeurs 1536, 820 et 390 px sans débordement de page.
