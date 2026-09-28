# Identification des caméras — correction du 25 septembre 2026

> Appellations applicatives confirmées par l’utilisateur et déployées le 28 septembre :
> « ESTON ES500-603 JK114 » pour l’ancienne JK114 ;
> « 4G SmartVision JT808/1078 » pour l’ancien profil affiché ES500-603.
> Les quatre fiches existantes sont migrées. Les anciens diagnostics ci-dessous
> sont historiques ; les clés techniques internes restent compatibles.


> Mise à jour du 25 septembre : le manuel T2 est déjà accepté par l'utilisateur.
> FX-4PVK sert uniquement à la recherche Internet ; connexion Wi-Fi interdite.
> La démarche locale mentionnée plus bas est annulée. Voir l'entrée finale.

L'utilisateur corrige l'identification précédente : les caméras de Véhicule 2
et du Toyota Hilux 9863BV01 sont des « 4G SmartVision Dash Camera », et non
des ES500-603. Il indique également que ES500-603 et JK114 désignent le même
matériel de son autre famille. Cette dernière équivalence est une information
utilisateur ; aucune documentation OEM consultée ne la confirme encore.

Dans les anciens documents, « ES500 » désigne souvent ces deux SmartVision.
Les mesures effectuées sur les appareils restent des observations utiles ;
leur attribution à un modèle commercial ES500-603 doit être corrigée. Le
champ model de la base n'est pas une identification automatique du matériel.

## Sources vérifiées

- Fiche DSE transmise par l'utilisateur :
  https://www.dsecctv.com/Prod_telecamere_dashcam_sim_4G_sorveglianza_auto_flotte_veicoli.htm
  Elle présente DK-V2-4GBM et DK-V2-4GBMR avec CMSV6/CMSV7. Ces références
  ne suffisent pas à identifier les exemplaires installés chez l'utilisateur.
- Manuel officiel DSE DK-V2-4GBM, version 2L4 :
  https://www.dsecctv.com/files/Istruzioni%20DK-V2-4GBM.pdf
  Page 2 : activation depuis le mode parking par demande distante dans l'app.
  Pages 3 et 5 : application CloudDVR et accès distinct à CMSV6/CMSV7.
  Ce manuel ne fournit ni commande de réveil documentée ni API d'intégration.
- Manuel SmartVision T2 publié par Oranic :
  https://www.oranic-tech.com/uploads/43575/files/%5BUser-Manual%5DT2.pdf
  Pages 2 et 5 : JT/T808, JT/T1078, veille parking et fonctions distantes
  CarAssist. Il s'agit d'une référence de famille, pas d'une identification
  prouvée de Véhicule 2 ou du Hilux. Ne pas confondre T2 avec les propriétés
  historiques FX/T1 déjà relevées et documentées dans es500-auto-return.md.

La différence CloudDVR/CarAssist et les variantes matérielles imposent de
vérifier la référence physique ou une confirmation du vendeur. La compatibilité
avec une plateforme CMSV6/CMSV7 ne démontre ni JT808 2019, ni la disponibilité
d'une commande de réveil sur la liaison actuelle, ni la cause des déconnexions.

## Conséquence pour le diagnostic

Le réveil par un service distant est une piste cohérente avec le comportement
décrit et les documents de cette famille. La cause de chaque fermeture JT808
reste à distinguer : veille, réseau, client embarqué ou serveur. Les observations
ne permettent pas de toutes les attribuer à la veille ou à JT808 2013.

La passerelle actuelle a réellement reçu JT808 et la vidéo de ces appareils ;
le nom commercial erroné ne rend pas ces échanges fictifs. Le défaut serveur
503 corrigé précédemment est indépendant du nom commercial. Aucun connecteur
CMSV6/CMSV7 ou réveil cloud n'est livré par ce lot documentaire.

## Dépendances du code à préserver avant un changement de libellé

Le code utilise encore ES500-603 comme clé de comportement :

- app/Support/DashcamProfile.php : identité de communication, format initial,
  correction des horodatages vidéo ;
- app/Http/Controllers/DashcamController.php : cohérence des identités et
  révocation de connexion lorsque model change ;
- listener/src/gps.js : délai d'inactivité et keepalive du matériel CarAssist ;
- listener/src/video.js : réservation des canaux et transition interphone ;
- listener/src/audio.js : amplification micro ;
- listener/src/video-profile.js : démarrage vidéo ;
- services et vues de présentation : nom, filtre et cadrage vidéo.

Un remplacement global ES500-603 par JK114 ferait perdre des comportements
validés sur le matériel CarAssist. Une correction applicative devra séparer
référence commerciale et profil de compatibilité, puis migrer les fiches
identifiées en conservant les identités et réglages réellement observés.
Cette migration n'a pas été exécutée.

## Travail effectué et limites

Lecture des sources publiques et audit ciblé du code, sans accès aux caméras,
sans changement de base, paramètres, protocole, firmware ou services.
Pas de tests applicatifs exécutés : aucune modification fonctionnelle.
La référence exacte des deux SmartVision, leur option CMSV6/CMSV7 et leur
interface officielle de réveil restent à confirmer. Aucun compte tiers créé,
aucune souscription, aucun message au fournisseur, aucun changement serveur.


## 25 septembre 2026 — Couverture du manuel fournie par l’utilisateur

Photo fournie : img20260925_14024670.jpg (Documents de l'utilisateur).
Titre imprimé lisible : « 4G SmartVision Dash Camera — User Manual ».
Illustration : boîtier à deux objectifs avec partie intérieure orientable.
La mention CMSV6 ou CMSV7 est manuscrite ; aucun numéro de modèle, fabricant,
identifiant réglementaire ou version de firmware n'est lisible sur cette page.
La couverture confirme l'appellation SmartVision, mais ne prouve pas un T2
précis, une variante DSE ou l'équivalence ES500-603 / JK114.

Le texte du manuel T2 publié par Oranic, déjà référencé ci-dessus, décrit
CarAssist, JT808/JT1078 et la veille parking. La correspondance exacte de la
couverture avec ce document n'a pas pu être vérifiée visuellement : échec du
rendu PDF distant et du téléchargement de la couverture publique (HTTP 403).
Ne pas annoncer une identification matérielle certaine sur la base du seul titre.
Le manuel public ne donne pas de commande d'intégration pour rétablir une
session JT808 fermée. La référence exacte et l'interface de réveil restent ouvertes.

Aucune manipulation caméra, connexion à la production, modification de code,
de base, de paramètres ou de services. Aucun test applicatif exécuté.


## 25 septembre 2026 — Manuel SmartVision T2 confirmé par l’utilisateur

Confirmation explicite : « c'est le meme manuel que tu as trouvé tu peux utiliser ».
Le manuel SmartVision T2 publié par Oranic est donc accepté comme référence pour
les caméras CarAssist de Véhicule 2 et du Hilux 9863BV01. Cette confirmation lève
la demande de nouvelles photos/pages du manuel ; ne pas la réitérer. Les mentions
antérieures d'identification documentaire en attente sont dépassées par cette
confirmation. Une compatibilité de firmware précis demeure à vérifier avant
toute mise à jour matérielle.

Manuel : https://www.oranic-tech.com/uploads/43575/files/%5BUser-Manual%5DT2.pdf
Lecture des parties 1, 3 et 5 : JT808/JT1078, veille parking, fonctions CarAssist.
Il ne contient pas de contrat API pour réveiller un appareil dont JT808 est fermé,
ni de commande garantissant une reconnexion à un serveur tiers. La mention de
veille n'établit pas à elle seule la cause de chaque coupure observée.

Recoupement du code Android précédemment acquis : WebSocketUtil envoie wakeup=1
avec preview, livekeep et settings ; la lecture settings inclut jt808.
Les réglages génériques incluent autosleeptime, mais le support exact du firmware
et l'effet sur JT808 restent non validés. Aucune écriture de veille tentée.
Le canal cloud et son authentification restent nécessaires pour ces requêtes ;
leur identification dans le code n'est pas une intégration livrée.

Nouvelle recherche publique : pas de contrat API de réveil exploitable trouvé.
Nouvel essai d'accès Windows via Computer Use : native pipe indisponible,
os error 2 avant toute interaction. Aucune session ou donnée de compte extraite.
Demande technique ciblée préparée : docs/demande-integration-smartvision.md,
destinée au fournisseur SmartVision/CarAssist approprié, non envoyée.

Contexte, identification, diagnostic de retour et réveil actualisés. Aucun code
applicatif, profil stocké, configuration caméra ou service de production modifié.
Aucun test applicatif exécuté. Réveil et reconnexion rapide toujours non résolus.

## 25 septembre 2026 — Contact réel et piste Wi-Fi SmartVision

Demande : essayer de contacter la caméra ; rechercher également le nom Wi-Fi.
L'utilisateur précise que FX-4PVK appartient à Véhicule 2 et qu'il est à portée
de ce réseau. Ne pas attribuer ce SSID au Hilux 9863BV01.

Le Hilux absent a d'abord été visé, compte tenu du diagnostic précédent :
les appels internes status/capabilities renvoient 409 avant tout envoi au
matériel. Ce refus local n'est pas une absence de réponse à une trame envoyée.
L'observation passive bornée ne montre aucune nouvelle ouverture TCP sur le
port GPS durant sa fenêtre. Elle ne démontre pas une absence permanente.

Après clarification, Véhicule 2 répond réellement à la demande de capacités
audio en environ 0,21 s ; le journal audio_capabilities confirme une réponse
de la caméra pendant cet essai, pas seulement une valeur en cache. Aucune
écoute, parole, vidéo, reconfiguration ou fermeture de session déclenchée.
Les détails de production restent sur le serveur, sans export local.

Recherche publique : le manuel T2 confirmé décrit les SSID FX-xxxx ; le suffixe
fourni ne prouve pas une référence matérielle ou une version de protocole.
Source : https://www.oranic-tech.com/uploads/43575/files/%5BUser-Manual%5DT2.pdf
Un manuel P9000 de la même plateforme montre aussi un service JT808 distinct ;
ce rapprochement documentaire n'identifie pas le matériel installé.
Source : https://www.oranic-tech.com/uploads/43575/files/%5BUser-Manual%5D4G-Dash-Cam-P9000.pdf

Le code CarAssist déjà acquis utilise une connexion locale WebSocket sur 8081
et la lecture f=get / what=[jt808]. Une sonde bornée à cette seule lecture est
préparée : workspace analysis/smartvision-wifi-20260925/query-local.mjs.
Elle masque les identifiants, mots de passe et données de localisation, et
n'envoie aucune écriture. Syntaxe Node vérifiée ; PAS exécutée sur la caméra.

Accès Windows : Computer Use échoue avant interaction (native pipe absent,
os error 2). Les lectures réseau autorisées montrent Ethernet actif, Wi-Fi
déconnecté, sans profil FX-4PVK enregistré. Une limite temporaire du contrôle
automatique a bloqué une vérification ; après la demande de continuer, la même
lecture autorisée réussit et confirme que FX-4PVK n'est toujours pas connecté.
L'utilisateur doit effectuer la connexion Wi-Fi du PC, en conservant Ethernet ;
aucun mot de passe à communiquer dans la conversation. Ne pas supposer la
connexion établie sur la seule base de la proximité ou de « continue ».

Reprise : confirmer le SSID réellement connecté, relever la passerelle locale,
puis lancer la lecture sur cette adresse uniquement. Ne pas sonder une adresse
privée supposée ni modifier les paramètres tant que la réponse n'est pas comprise.
Le correctif serveur préparé précédemment reste non activé ; aucun service
redémarré dans ce lot. Reconnexion automatique et stabilité longue durée non
résolues par ce test de contact. Session SSH fermée et observation terminée.

## 25 septembre 2026 — FX : recherche Internet uniquement, accès Wi-Fi annulé

### Périmètre corrigé par l'utilisateur

FX-4PVK est uniquement un indice pour rechercher des informations sur Internet.
L'utilisateur interdit expressément la connexion au Wi-Fi de la caméra. Aucun
accès Wi-Fi n'a eu lieu. La demande antérieure de connecter le PC est annulée ;
ne pas la réitérer. La sonde locale préparée n'a jamais interrogé la caméra et
est maintenant désactivée explicitement avant toute opération réseau.
La priorité reste la liaison directe caméra → EXADCAM, sans démarche fournisseur.

### Résultats et portée

1. Le manuel T2, déjà confirmé par l'utilisateur, décrit le préfixe FX-xxxx
   (page 9 du PDF) et les fonctions cloud CarAssist. Il confirme JT808/JT1078,
   sans identifier une année de protocole ni donner de correctif de reconnexion.
   https://www.oranic-tech.com/uploads/43575/files/%5BUser-Manual%5DT2.pdf

2. Le manuel P9000/T88 distingue l'application Jt808service utilisée pour CMSV6
   des fonctions de CarAssist. Sa capture de configuration affiche exactement
   119.23.78.106:6608, adresse présente dans le champ Backup fourni par
   l'utilisateur, ainsi que Manufacturer ID 12345 et Terminal Model FX.
   Il s'agit d'un autre modèle de la même famille logicielle, pas d'une preuve
   d'identité matérielle. Le moteur de recherche restitue le texte et les champs
   de la capture ; l'ouverture intégrale du PDF a échoué par expiration de délai.
   https://www.oranic-tech.com/uploads/43575/files/%5BUser-Manual%5D4G-Dash-Cam-P9000.pdf

3. Le manuel original LiveEye de TopDawg/Falcon, conservé sur device.report,
   prescrit un serveur principal personnalisé et un Backup IP vide. Il décrit
   également Jt808service et un identifiant FX-xxxx. Cette consigne concerne ce
   modèle LiveEye ; elle ne prouve pas le comportement du firmware SmartVision.
   https://device.report/m/780e14d5af32e471ff4b110f4e31c6ae2190dad0872b215402b00da6d34427b2
   La page officielle Falcon répertorie aussi le manuel LiveEye :
   https://falconelectronics.com/pages/product-quick-start-guides-manuals

Le rapprochement avec le code CarAssist déjà acquis appuie l'existence de deux
liaisons distinctes : le cloud CarAssist et le client JT808. Le statut CarAssist
ne suffit donc pas à établir une connexion JT808 à EXADCAM. Le retour observé
par l'utilisateur après Submit est compatible avec une réinitialisation de la
configuration/connexion JT808 ; l'implémentation du client embarqué n'a pas été
inspectée, et ce mécanisme ne doit pas être annoncé comme démontré.

Hypothèse à vérifier : la sélection du serveur principal/de secours ou les délais
de reconnexion du client JT808 pourraient expliquer certaines absences. Aucune
preuve de connexion de la caméra au serveur de secours n'a été obtenue. L'adresse
publique trouvée n'est pas démontrée comme indispensable au cloud CarAssist.
Le précédent essai Backup = EXADCAM n'avait pas obtenu de retour rapide pendant
sa courte fenêtre ; il ne faut ni le présenter comme concluant, ni le répéter
automatiquement. La réponse standard 0x0017 précédemment vide ne coïncide pas
avec le champ propriétaire Backup affiché : une écriture 0x8103 aveugle n'est
donc pas un correctif fiable de ce champ.

Aucun correctif public confirmé pour PL_TW1-V1.1_4G_NCS_EN_2.2.31_2.3.7 trouvé
dans les recherches effectuées. Ne pas forcer JT808 2019 ni installer un firmware
d'un autre modèle à partir du seul nom FX ou d'une ressemblance de manuel.

### État livré

Recherche publique et suivi documentaire uniquement. Aucun réglage de caméra,
code applicatif actif ou service de production modifié. Aucun test applicatif
relancé. Le correctif de délais des ACK déjà testé reste préparé, non activé.
Le retour automatique fiable n'est pas encore résolu. La prochaine observation
utile doit départager absence de tentative TCP et tentative reçue puis refusée
ou interrompue, sans provoquer une coupure d'une session saine.


## 25 septembre 2026 — Backup retiré par l’utilisateur, CarAssist toujours accessible

L'utilisateur indique avoir supprimé l'IP Backup 119.23.78.106 et son port sur
Véhicule 2, en conservant uniquement EXADCAM comme serveur principal. Il indique
que l'accès à distance dans CarAssist fonctionne toujours. Ce changement a été
effectué par l'utilisateur ; aucune écriture de configuration caméra n'a été
envoyée par l'assistant. Ne pas restaurer automatiquement l'ancien Backup ni
appliquer ce changement au Hilux sur la seule base de cette observation.

Contrôle en lecture seule : Véhicule 2 a une session GPS authentifiée active
dans EXADCAM et un contact récent. La session observée est restée ouverte pendant
environ dix-huit minutes sans coupure enregistrée. L'heure exacte de retrait du
Backup n'est pas connue : cette durée n'est pas une mesure depuis le changement
et ne démontre pas une amélioration qui lui serait due. Aucun redémarrage,
commande de réveil, lecture vidéo ou microphone déclenché pendant le contrôle.

L'observation renforce l'hypothèse de deux liaisons : client JT808 vers le serveur
principal/de secours configuré, et fonctions cloud CarAssist. Le manuel T2
accepté par l'utilisateur décrit explicitement les fonctions cloud sur réseaux
distincts (partie 5, pages PDF 11-12), dont vidéo et interphone à distance :
https://www.oranic-tech.com/uploads/43575/files/%5BUser-Manual%5DT2.pdf

La coexistence observée ne suffit pas à démontrer que l'ancien Backup causait
les coupures. La persistance du réglage et du fonctionnement CarAssist après
une future reconnexion normale restent à confirmer. Aucune reconnexion forcée
n'est demandée pour ce seul constat. Le champ standard JT808 0x0017 précédemment
vide n'est toujours pas une lecture fiable du champ Backup propriétaire.

Suivi local actualisé ; détails de production conservés sur le serveur. Aucun
code applicatif modifié, aucun déploiement ni test applicatif rejoué dans ce lot.

## 28 septembre 2026 — Nomenclature des dashcams corrigée et déployée

Demande utilisateur : corriger partout dans l’application les appellations et les données déjà enregistrées :

| Ancienne appellation | Appellation désormais enregistrée et affichée |
| --- | --- |
| JK114 | ESTON ES500-603 JK114 |
| ES500-603 | 4G SmartVision JT808/1078 |

Les deux fiches de chaque famille ont été migrées en production : champs model et noms par défaut corrigés, aucun ancien model restant. Les éventuels noms personnalisés sont conservés. Migration également appliquée à la base MySQL locale EXADCAM. Les données historiques GPS et les associations véhicule/flotte restent inchangées. L’utilisateur reporte à plus tard son intervention sur le Backup du Hilux distant ; aucune configuration serveur de caméra n’a été modifiée dans ce lot.

Réalisation : constantes et normalisation centralisées dans DashcamProfile, conversion des anciennes valeurs à la lecture/écriture du modèle Dashcam, validation compatible avec les formulaires déjà ouverts, filtres/recherche et affichage dans les listes, la carte, les détails, le tableau de bord et les alertes. Les traductions françaises/anglaises et les noms proposés à la création sont corrigés. Le filtre de modèle a été élargi et les versions des fichiers JS/CSS incrémentées.

Compatibilité : les clés internes envoyées exclusivement aux listeners restent JK114 pour ESTON et ES500-603 pour SmartVision. Ce sont désormais des identifiants de profil historiques, pas les modèles affichés ni stockés en base. Cette correspondance explicite dans ListenerController préserve les politiques TCP, l’inactivité GPS, le cadrage, les paramètres vidéo et le gain microphone, sans redémarrer les listeners. Ne pas remplacer globalement ces clés internes par les appellations commerciales : toute évolution de ce contrat doit conserver le comportement des connexions actives.

Migration 2026_09_28_090000_correct_dashcam_model_names.php : mise à jour ciblée et transactionnelle des noms/modèles avec query builder, sans événements Eloquent, sans modification des horodatages ni des identifiants de communication. Elle préserve les noms personnalisés, accepte une réexécution et dispose d’un retour arrière. Les sources et noms/modèles précédents ont été sauvegardés avant application ; preuves détaillées de production conservées sur le serveur.

Validation réellement exécutée sur le code final : 65 tests ciblés réussis, 566 assertions, sur SQLite en mémoire isolée. Sont couverts le renommage/idempotence/retour arrière, la conservation de toutes les colonnes hors nom/modèle, les profils internes, les filtres, la carte, les détails, le tableau de bord et les permissions des comptes flotte. Suites : DashcamModelNamesTest, DashcamRegistryTest, ListenerAccessTest, FleetAdministrationTest, DashboardVideoTest et RealDashboardTest. Contrôle syntaxique JavaScript et syntaxe PHP des fichiers du lot réussi. Il ne s’agit pas d’une suite complète ni d’un nouvel essai physique du haut-parleur.

Déploiement du 28 septembre réussi : 15 fichiers vérifiés/installés et migration ciblée appliquée. Quatre fiches vérifiées par comparaison des empreintes de leurs paramètres de communication et d’affectation : inchangés. Réponse HTTP réelle du résolveur interne : profils préservés pour les quatre équipements. Véhicule 2 et Hilux SmartVision connectés après migration. Les trois processus GPS/vidéo/audio sont conservés, sans redémarrage. Connexion web et ressource JS répondent HTTP 200. Seules les vues compilées ont été purgées ; aucun vidage du cache des baux vidéo. Recharger la page permet aux onglets déjà ouverts de récupérer les nouveaux formulaires et ressources.
