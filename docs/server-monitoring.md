# Monitoring serveur EXADCAM

Livré le 28 septembre 2026. Référence EXAD Tracking consultée en lecture seule ; réalisation indépendante pour le serveur EXADCAM.

## Consultation

Menu **Monitoring**, après **Logs serveur**, réservé au superadmin actif. Indicateurs réels CPU, RAM, disque et charge moyenne, graphiques CPU/RAM, disque, trafic réseau et charge, tableau des interfaces et informations système (hôte, noyau, durée de fonctionnement, PHP, Laravel, environnement, swap). Traductions FR/EN et présentation corporate responsive.

Actualisation toutes les cinq secondes lorsque le menu et l’onglet sont visibles. Les boutons Pause et Rafraîchir ont été retirés à la demande de l’utilisateur ; l’actualisation reste entièrement automatique. L’heure affichée est celle de la mesure, en heure de Kinshasa. Le volet des dernières mesures rend les valeurs des graphiques accessibles dans un tableau. Graphiques locaux ApexCharts, chargés une seule fois et partagés avec le tableau de bord ; aucun CDN.

## Mesures et historique

Service indépendant **exadcam-monitoring.service** : script Python standard, lecture de /proc/stat, /proc/meminfo, /proc/net/dev, /proc/loadavg et /proc/uptime ; disque mesuré sur le système de fichiers de /var/www/exadcam. Aucun processus externe ni requête réseau, aucune commande caméra.

- CPU : différences de compteurs sur cinq secondes, temps idle + iowait exclus, guest déjà compris dans user/nice donc non compté deux fois. Le nombre de cœurs correspond aux processeurs logiques visibles.
- RAM : MemTotal moins MemAvailable, cache récupérable pris en compte. Swap absent affiché comme non configuré.
- Disque : occupation physique total moins blocs libres ; disponibilité pour l’application distincte des éventuels blocs réservés. Stockage affiché en Go/To décimaux, RAM et réseau en unités binaires ; pourcentages calculés sans arrondi entier prématuré. Le libellé Volume EXADCAM et sa note distinguent la capacité du système de fichiers de la capacité brute du disque.
- Charge : moyennes sur 1/5/15 minutes, non assimilées à des pourcentages CPU.
- Réseau : différences des compteurs reçus/envoyés divisées par le temps monotone écoulé. Interfaces hors boucle locale, sommes des interfaces affichées ; sur un hôte avec ponts ou interfaces virtuelles multiples, ces sommes ne sont pas une facturation du trafic externe. Compteurs cumulés depuis l’initialisation de chaque interface.

Première mesure CPU/débits, compteurs réinitialisés ou nouvelle interface : valeur inconnue, pas de zéro fabriqué. Intervalle interrompu de plus de quinze secondes : nouvelle base pour les taux et remise à zéro de l’historique afin de ne pas relier les périodes séparées. Changement d’horloge en arrière : historique remis à zéro.

Historique roulant limité à 180 points, soit jusqu’à quinze minutes. Le collecteur continue en l’absence de navigateur ; ouvrir le menu récupère les points disponibles. Le redémarrage du collecteur recommence cet historique. Aucune conservation longue durée ni surveillance avec notifications n’est ajoutée dans ce lot. Snapshot de plus de 25 secondes, futur incohérent, lecture absente/invalide ou erreur réseau : état explicitement signalé. Les anciennes mesures peuvent rester visibles avec leur heure.

## Isolation et exploitation

GET /server-monitoring/metrics : authentification, compte actif, middleware superadmin et contrôle dans le contrôleur ; 40 requêtes/minute, réponse no-store/private. Aucun endpoint de commande ou choix de fichier utilisateur. Lecture JSON plafonnée à 512 Kio, lien symbolique direct refusé, contrat de champs fermé, historique 180 points et interfaces 64 lignes maximum. Valeurs DOM rendues avec textContent.

Collecteur sous le compte système exad-monitor-reader sans shell, groupe www-data. Script root-owned dans /usr/local/libexec/exadcam-monitoring.py. StateDirectory /var/lib/exadcam-monitoring (0750), snapshot metrics.json (0640) remplacé atomiquement. Le serveur web peut lire mais ne peut modifier ni le fichier ni son répertoire. Unité durcie, aucun privilège/capacité, mémoire 64 Mio, écritures bornées au répertoire d’état, réseau restreint à AF_UNIX. Ne pas activer PrivateNetwork : cela masquerait les interfaces réseau réelles du serveur.

Sources : deployment/scripts/exadcam-monitoring.py et deployment/systemd/exadcam-monitoring.service. Installer le compte système, copier le script/unité, daemon-reload puis enable --now exadcam-monitoring.service. Configuration Laravel config/server_monitoring.php. Après installation, actualiser config:cache et view:clear uniquement ; ne pas vider les baux vidéo. Le service est indépendant des quatre listeners et du collecteur de logs.

## Validation du lot

12 tests PHP ciblés / 128 assertions (ServerMonitoringTest + ServerLogsTest), SQLite en mémoire : superadmin/client/inactif/invité, consultation seule, absence de cache, champs autorisés, données manquantes/corrompues/trop grandes/anciennes/futures, borne d’historique et chargement unique ApexCharts. Six tests Python exécutés sous Linux : CPU/guest, mémoire, taux réseau et resets, disque réservé, historique et publication atomique privée. Pint, syntaxe JS et diff contrôlés. Ce ne sont pas une suite exhaustive ni un test de charge.

Déploiement avec sauvegarde /var/backups/exadcam-monitoring-20260928-104742 ; seize fichiers applicatifs et d’exploitation, puis documentation. Correction complémentaire de hauteur des graphiques sauvegardée dans chart-layout. Configuration applicative existante comparée et inchangée, quatre PID des services GPS/vidéo/audio/enregistrements conservés. Collecteur actif et permissions de lecture seule vérifiées sur le serveur. Aucun réglage caméra modifié.

Navigateur réel : mesures et trois graphiques, Pause stable, rafraîchissement ponctuel en pause, reprise et arrêt des requêtes hors menu ; formats 1536, 820 et 390 px sans débordement horizontal, graphiques contenus dans leurs cartes (260 px bureau/tablette, 240 px mobile). Les courbes ne contiennent que les mesures collectées.


## 28 septembre 2026 — Monitoring automatique et clarification du stockage

Demande : retirer Pause et Rafraîchir ; capacité annoncée par l’utilisateur : 600 Go de base et extension de 1,2 To, NVMe. Boutons, écouteurs et traductions associés supprimés du Monitoring uniquement. Actualisation 5 s et suspension hors menu/onglet conservées ; refus d’accès arrête les requêtes. Les contrôles du menu Logs serveur restent inchangés.

Contrôle réel en lecture seule : lsblk et /sys/block ne montrent qu’un disque sda, QEMU HARDDISK, non rotatif, 1 288 490 188 800 octets = 1 200 Gio. Partition principale sda1 : 1 287 415 381 504 octets, montée sur / ; /boot et /boot/efi sur les petites partitions du même disque. statvfs(/var/www/exadcam) : 1 247 017 926 656 octets de capacité du système de fichiers. Aucun second disque de 600 Go, ni zone libre de cet ordre à la fin de la partition principale, n’est visible. La couche virtuelle n’identifie pas le protocole physique NVMe. Le total attendu de 1,8 To n’est pas confirmé ; la capacité/l’affectation de l’extension chez l’hébergeur reste à vérifier, sans conclure qu’un disque a disparu. Aucun montage, partitionnement, formatage ou réglage d’hébergeur effectué.

Présentation : libellé Volume EXADCAM, capacités du disque en Go/To décimaux (environ 1,25 To utilisables), explication du périmètre système de fichiers. RAM et réseau conservent leurs unités binaires. Aucune capacité fictive ajoutée et aucune modification du collecteur. Cinq fichiers UI/traductions déployés avec sauvegarde /var/backups/exadcam-monitoring-storage-20260928-111808, vues seules purgées ; PID des quatre listeners et du collecteur Monitoring inchangés.

Validation réellement exécutée : cinq tests ciblés ServerMonitoringTest / 52 assertions sur SQLite en mémoire, syntaxe JS et git diff --check réussis. Navigateur de production : absence des deux boutons, trois graphiques présents, horodatage avançant automatiquement, capacité 1,25 To et note explicative, mobile 390 px sans débordement. Ce lot ne valide pas une modification du stockage physique.
