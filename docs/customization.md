# Personnalisation EXADCAM

Livrée le 28 septembre 2026. EXAD Tracking a été consulté en lecture seule comme référence visuelle.

## Deux périmètres

- Superadmin actif : menu Personnalisation après Monitoring. Identité de l’application (nom, nom court, site web), logos principal/interne, favicon, sept couleurs, coordonnées du support et fond de carte par défaut. Ces réglages s’appliquent aux vues partagées et à l’écran de connexion. Les couleurs de statut restent sémantiques. La carte reste Google Maps : choix Plan, Satellite avec libellés, Satellite ou Relief, sans nouveau fournisseur ni changement de clé/API.
- Admin actif d’une flotte active : même menu, formulaire limité au nom et au logo de sa flotte. Le nom est obligatoire lorsqu’il est transmis, limité à 255 caractères et enregistré dans fleets.name. Renommer seul conserve le logo, son fichier, le code, le statut, l’abonnement et les affectations. Le serveur déduit la flotte depuis le compte connecté et refuse un identifiant de flotte ou des réglages globaux fournis par le client.
- Membres ordinaires : voient le logo de leur propre flotte dans le menu, sans droit de modification ni menu Personnalisation.
- Le logo de flotte remplace seulement l’image dans le menu. **EXADCAM** et **VIDÉO & GPS** restent à côté pour les comptes clients, même si le superadmin personnalise le nom global. Aucun logo d’une autre flotte n’est exposé. Sans logo de flotte, retour au logo interne global, puis au logo principal global, puis au logo EXAD livré avec l’application.

## Utilisation

Les aperçus de couleurs et d’images sont locaux tant que le formulaire n’est pas enregistré. Annuler rétablit les valeurs enregistrées. Quitter le module annule l’application visuelle des couleurs non enregistrées ; revenir retrouve le formulaire en cours. Le bouton de restauration rétablit la palette EXADCAM dans le formulaire ; il faut enregistrer pour la publier.

Après enregistrement, la page se recharge pour appliquer les textes, les images et le favicon. Les autres onglets/utilisateurs récupèrent la nouvelle identité à leur prochain chargement de page. Une case permet de supprimer chaque image personnalisée et de revenir au visuel par défaut ; un nouvel envoi remplace l’ancien. Aucun logo client n’a été choisi ou installé arbitrairement pendant la livraison.

## Restaurer par défaut

Bouton permanent dans les deux formulaires, même si aucune image personnalisée n’a été enregistrée. Superadmin : prépare les noms EXADCAM, logos/favicon livrés, palette, fond Plan et coordonnées de support vides. Admin : prépare le retour au logo de la plateforme et remet le champ de nom à sa valeur enregistrée ; aucun nom de flotte fictif ou générique n’est créé. Enregistrer applique la restauration, Annuler rétablit l’état enregistré. La restauration ne modifie pas les logos des autres flottes. Cette possibilité de retour aux valeurs par défaut est une préférence explicite de l’utilisateur pour les prochains écrans de personnalisation.

## Données et accès

Migration ciblée 2026_09_28_140000_add_application_and_fleet_branding : table application_settings (singleton id 1, valeurs JSON) et colonne nullable fleets.logo_path. Les autres colonnes de flotte sont préservées. Lecture avec valeurs par défaut lorsque la migration n’est pas encore appliquée ; elle est obligatoire pour les écritures.

BrandingService résout l’identité sans cache global de compte. CustomizationController contrôle le rôle, le statut et la flotte, puis les vérifie de nouveau sous verrou transactionnel avant écriture. Les images remplacées sont supprimées après validation de la transaction ; les nouveaux fichiers sont nettoyés en cas d’échec. Modification limitée à dix requêtes par minute, session active, CSRF et middleware habituel.

Uploads : PNG/JPG/WebP, logos 2 Mio maximum et 30 × 20 à 2400 × 1200 pixels ; favicon 1 Mio maximum et 16 à 512 pixels. Validation MIME/contenu/dimensions puis décodage et réencodage PNG via GD, noms UUID, sans conserver de métadonnées actives. SVG/HTML/faux fichiers image refusés. Images privées sous storage/app/private/branding/global ou branding/fleets/<id> ; aucun lien symbolique public supplémentaire.

Les images globales sont servies publiquement par une route bornée aux trois types et à la version courante, pour l’écran de connexion. L’image de flotte exige une session active et la flotte courante active ; son URL ne comporte pas de paramètre fleet_id. La version est liée au chemin UUID courant. Le changement de flotte, la désactivation du compte et le remplacement d’une image révoquent l’ancien accès. Réponses PNG avec nosniff, no-store et politique de contenu restrictive. Les chemins de fichiers ne proviennent jamais de la requête.

Prérequis : extension PHP GD (vérifiée active en production). Pour les tests locaux sur XAMPP, GD est activée uniquement pour le processus par php -d extension=gd ; aucun changement permanent du php.ini n’a été effectué.

## Validation et exploitation

37 tests PHP ciblés / 436 assertions réussis sur SQLite en mémoire et stockage factice : CustomizationTest, FleetAdministrationTest, ServerMonitoringTest et RealDashboardTest. Couverture des rôles, isolation interflottes, accès des membres, réaffectation/désactivation, sauvegarde, validation des images/couleurs/liens, remplacement/suppression et retours aux valeurs par défaut. Pint, syntaxes JavaScript et git diff --check contrôlés. Ce résultat n’est pas une suite exhaustive ni un essai physique des caméras.

Navigateur de production : menu superadmin et cinq sections présents, images chargées, aperçu de couleur immédiat, Annuler restaure la couleur enregistrée, sauvegarde des valeurs existantes réussie avec message de confirmation, navigation tableau de bord/personnalisation fonctionnelle. Largeurs 1536, 820 et 390 pixels vérifiées sans débordement horizontal ; champs et couleurs restent dans leur carte. Le formulaire admin et la visibilité des membres sont couverts par les tests HTTP isolés, pas par une connexion manuelle à un compte client de production.

Premier lot : 27 fichiers vérifiés puis installés, migration ciblée appliquée ; sauvegarde des sources et de l’état initial des flottes/migrations sous /var/backups/exadcam-customization-20260928-115145 (accès root uniquement). Enregistrement réel des valeurs par défaut confirmé en base, aucune image personnalisée créée et aucun logo de flotte modifié. Les PID GPS, vidéo, audio, enregistrements et Monitoring ont été comparés : inchangés. Seules les vues compilées sont purgées, aucun vidage du cache des baux vidéo. Dernier ajustement : bouton satellite cohérent avec le fond choisi, version de ressource incrémentée.

Retour arrière : restaurer les sources sauvegardées et purger les vues. Laisser le schéma additif et les images en place préserve les choix sauvegardés. Si un retrait de schéma est réellement nécessaire, exporter d’abord les réglages et logos courants ; le down ciblé retire la table et la colonne, sans supprimer les fichiers image. Ne pas exécuter une annulation générale des migrations ni cache:clear.


## Complément du 28 septembre — nom de flotte et restauration

L’endpoint client accepte fleet_name en plus du logo et de sa suppression ; il refuse toujours les identifiants de flotte imposés, code, statut, abonnement et champs globaux. Le nom est modifié uniquement pour la flotte active du compte admin revérifié sous verrou. Aucun changement de schéma. Menu Enregistrements déplacé juste après Carte sans changement des permissions vidéo.

Validation complémentaire : 27 tests ciblés / 365 assertions, Pint, syntaxe JS et diff --check. Navigateur : restauration après changements non enregistrés du nom court, de la couleur des boutons et du fond de carte ; retour à EXADCAM / palette / Plan, message explicite, Annuler fonctionnel. Ordre des menus vérifié ; mobile 390 px sans débordement. Renommage client couvert par les tests HTTP isolés, sans renommer de flotte réelle en production. Sauvegarde /var/backups/exadcam-customization-reset-20260928-121433 ; sept fichiers applicatifs, vues seules purgées et cinq processus comparés inchangés.
