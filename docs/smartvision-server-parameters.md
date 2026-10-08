# Lecture des serveurs Backup SmartVision — 29 septembre 2026

> 30 septembre, 16 h 37 Kinshasa : essai distant effectué sur le Hilux
> SmartVision 352538106693487. Écriture 0x8103 du seul Backup standard 0x0017
> vide acceptée (ACK 0) ; relecture : principal 62.171.190.15:7808 inchangé,
> Backup standard vide, déjà vide avant essai. Cela ne prouve pas l’effacement
> des backups propriétaires CarAssist. Huit tests ciblés réussis, inspecteur
> refermé, aucun redémarrage. Pas de passage par Windows/Wi-Fi. Tous les
> backups ne sont pas encore vérifiés supprimés ; voir la dernière entrée de
> docs/smartvision-server-parameters.md. Aucun formulaire d’envoi réintroduit.
> Une nouvelle coupure par le pair et une reconnexion spontanée ont eu lieu.
> Relecture après reconnexion à 16 h 40 min 51 s : principal inchangé, Backup
> standard vide. La stabilité durable n’est pas démontrée.


> État au 30 septembre 2026 : interface de préparation retirée du site en
> production à 16 h 11 (Kinshasa), à la demande de l’utilisateur. Un brouillon
> conservé, empreinte du contenu identique avant/après. Aucun envoi, aucun
> changement de configuration des caméras ni redémarrage des services.
> Le lot local a passé 48 tests / 357 assertions avant déploiement. Les
> descriptions du formulaire livré le 29 septembre constituent l’historique.

## Résultat établi dans le code CarAssist

Le code de CarAssist Android 3.4.8 distribué par l’éditeur contient une lecture
de la section `jt808` par la commande cloud `settings`. Le formulaire lit
explicitement les quatre champs `ipbak`, `portbak`, `ipbak2` et `portbak2`.
Cette identification statique n’est pas une réponse réelle du Hilux.

Version ciblée de la requête, avec uniquement la section nécessaire :

```json
{
  "peer": "<SN_CARASSIST_DU_HILUX>",
  "cmd": "settings",
  "get": {"what": ["jt808"]},
  "wakeup": 1
}
```

Trame texte du relais WebSocket sur une session CarAssist authentifiée :

```text
relay:<compteur>{"peer":"<SN_CARASSIST_DU_HILUX>","cmd":"settings","get":{"what":["jt808"]},"wakeup":1}
```

`<compteur>` désigne le compteur de requête de la session, pas un identifiant de
caméra. L’application ajoute également `relayid` lorsqu’un callback est utilisé.
`peer` est le **SN CarAssist** de la caméra liée au compte : le modèle d’appareil
stocke séparément `sn` et `imei`. Ne pas substituer automatiquement l’IMEI, le
terminal JT808 ou le nom Wi-Fi.

Le message `get` demande une lecture. `wakeup:1` est le drapeau que l’application
ajoute à ses lectures de réglages ; il peut demander au cloud d’activer la caméra,
sans modifier ici les paramètres de serveur.

Champs à relever dans la section `jt808` de la réponse :

| Champs | Signification dans le formulaire |
| --- | --- |
| `ip`, `port` | Main IP / Main Port |
| `ipbak`, `portbak` | Backup IP / Backup Port |
| `ip2`, `port2` | IP2 / Port2 |
| `ipbak2`, `portbak2` | Backup IP2 / Backup Port2 |
| `ip3`, `port3`, `ip4`, `port4` | Serveurs additionnels, si présents dans ce firmware |

Un champ absent ne prouve pas que son serveur est désactivé. L’application
utilise `optString` et `optInt`, qui affichent aussi vide/0 en cas d’absence ;
il faut conserver cette distinction dans un éventuel lecteur EXADCAM.

## Sources vérifiées

APK obtenu précédemment depuis le lien Android de l’éditeur :
http://dl.carassist.cn/upgrade/CarControl.apk, via http://www.carassist.cn/.
SHA-256 : `28e6ab8de76757e528f6d1af3a41b0e76671f343782b8d027ccf3913c94edcc7`.
Package `com.car.control`, version interne 3.4.8. Version réelle sur le téléphone
de l’utilisateur non confirmée ; ne pas annoncer qu’il s’agit de la dernière.

Fichiers locaux sous `analysis/carassist-wake-20260924/decompiled/sources/` :

- `com/car/cloud/WebSocketUtil.java:1241` : `cmd=settings`, `get.what` incluant
  `jt808`, `wakeup=1`, envoi via `relay`.
- `com/car/cloud/WebSocketUtil.java:1975` : sérialisation
  `relay:<compteur><JSON>`, `relayid` éventuel.
- `com/car/cloud/WebSocketUtil.java:1216` et `:1563` : lecture de `get`, puis
  extraction de `jt808` et transmission au formulaire.
- `com/car/control/remotetest/Jt808ConfigActivity.java:315` : correspondance
  précise des champs principaux et Backup, y compris les seconds serveurs.
- `com/car/cloud/d.java:29` : SN et IMEI distincts.
- `com/car/cloud/WebSocketUtil.java:915` : connexion utilisateur préalable.

La requête ciblée ci-dessus conserve la structure du lecteur de réglages de
l’application, en réduisant sa liste de sections à `jt808`. Elle n’a pas été
envoyée au cloud pendant cette recherche. Aucun compte ou secret embarqué n’a
été extrait. Aucun accès Wi-Fi, lancement de vidéo ou message fournisseur.

## Accès direct par EXADCAM

Le standard JT808 utilise `0x8106` pour lire des paramètres précis, avec réponse
`0x0104`. `0x0017` correspond au serveur Backup standard. `0x0026` est un champ
de serveur secondaire ajouté dans la famille 2019 ; sa présence ne doit pas
être supposée sur ce firmware qui échange des trames 2013.

Référence primaire :
https://github.com/QuecPython/jtt808/blob/master/docs/en/API_Reference.md

Une commande cloud CarAssist ne peut pas être envoyée telle quelle dans le
socket JT808 de la caméra. Aucune encapsulation constructeur vérifiée dans
`0x8900` n’a été trouvée ; ne pas inventer un type de transmission transparente.

Le Hilux cible est le Toyota Hilux **9863BV01**, SmartVision, terminal JT808
`053810669348`. Le Nissan Patrol, ancien Véhicule 2, n’est pas la cible.

Lecture complémentaire préparée : `0x8106`, paramètres `0x0013`, `0x0018`,
`0x0017`, `0x0026`. Corps hexadécimal :
`04 00000013 00000018 00000017 00000026`.
Conserver la version d’en-tête 2013 de la session active ; aucun passage forcé à
2019. Lecture uniquement, aucun `0x8103`, redémarrage ou arrêt de connexion.

Résultat réel du 29 septembre à **08 h 20 min 27 s, Kinshasa** : le Hilux a
répondu en 175 ms. Principal `62.171.190.15`, port TCP `7808` ; `0x0017` de
longueur zéro ; `0x0026` de longueur quatre, valeur binaire `00 00 00 00`.
Ce dernier n’est donc pas une liste lisible de serveurs au format 2019.
L’absence d’adresse dans cette réponse ne démontre pas l’absence du Backup
CarAssist. Aucun changement de configuration effectué.

Les deux tests ciblés du lecteur ont réussi avant l’envoi : sélection exclusive
du Hilux et commandes de lecture, nettoyage des observateurs, valeurs non
nécessaires masquées. Le PID GPS est resté `263273`, l’inspecteur temporaire
loopback a été fermé. Compte rendu filtré conservé sur le serveur dans
`/home/exad-cam/smartvision-backup-command-20260929/`.

## Ce qui reste nécessaire

Pour lire les champs CarAssist réels du Hilux avec la commande identifiée :
une session CarAssist autorisée et le SN de la caméra liée à ce compte.
L’accès Windows est actuellement indisponible et l’utilisateur a indiqué ne pas
avoir accès au formulaire distant. Aucun mot de passe n’est demandé dans la
conversation. Aucune suppression de Backup n’est déclarée effectuée.

## 29 septembre 2026 — Requête complète des rubriques de réglages CarAssist

À la demande de l’utilisateur, la lecture distante générale a été extraite directement de WebSocketUtil.c(String,j), lignes 1241–1263, dans CarAssist Android 3.4.8 déjà acquis. Elle envoie cmd=settings, get.what contenant exactement generic, mobile, softap, dvr, sdcard, bondlist, update, adas, hud, user_agreement, jt808 et wakeup=1, via relay:<compteur><JSON>. Aucune clé wildcard all ni commande forçant l’affichage de tous les formulaires n’est identifiée dans ce chemin.

Exemple du corps, destiné à une session CarAssist authentifiée (peer = SN CarAssist, pas automatiquement IMEI) :

```json
{
  "peer": "<SN_CARASSIST_DU_HILUX>",
  "cmd": "settings",
  "get": {
    "what": [
      "generic",
      "mobile",
      "softap",
      "dvr",
      "sdcard",
      "bondlist",
      "update",
      "adas",
      "hud",
      "user_agreement",
      "jt808"
    ]
  },
  "wakeup": 1
}
```

Le chargement retourne des valeurs de réglage ; les formulaires sont définis dans l’application et restent conditionnels aux données/fonctions renvoyées et aux restrictions du client. Par exemple, ll808 est initialement caché et devient visible après réception de jt808, via setJt808Config/s() dans QuickSettingFragment2. Inclure adas dans la requête ne démontre pas à lui seul une fonction ADAS disponible sur le Hilux.

Autres lectures identifiées séparément dans WebSocketUtil : settings/get/what=[mobile,gps,record] pour les états (lignes 1888–1901) et [reporttimes] (1397–1410). La clé apn est présente dans le chemin de lecture locale QuickSettingFragment2.t(), mais absente de cette requête distante générale ; son support distant n’est pas prouvé. Aucun accès local/Wi-Fi tenté ou demandé.

Contrôle effectué : lecture statique des producteurs, sérialiseur, traitement de réponse et visibilité UI ; extraction vérifiée des 11 sections. Aucune nouvelle commande envoyée à une caméra ou au cloud, aucun réglage modifié, aucune interface EXADCAM implémentée/déployée et aucun test d’intégration réel de cette requête. L’identification ne lève pas le besoin d’une session CarAssist autorisée et du SN exact. La suppression des Backups du Hilux reste non appliquée.


## 29 septembre 2026 — Formulaire SmartVision complet, sauvegarde en attente

L’utilisateur a précisé que la capture était un exemple et demandé une fonction
EXADCAM pour toutes les SmartVision, l’ESTON étant reportée. Il a choisi
explicitement « Formulaire complet avec envoi en attente ». Cette version ne
doit pas être confondue avec une intégration de commandes matérielles complète.

Accès livré : superadmin, Flottes → Dashcams → bouton Configuration SmartVision
(roue dentée). Routes GET/PUT /dashcams/{dashcam}/configuration, réservées au
superadmin actif et aux modèles SmartVision canoniques. ESTON refusée côté
serveur et sans bouton. Interface FR/EN compacte, mobile et tablette.

Champs : Main IP/Port, Backup IP/Port, IP2/Port2, Backup IP2/Port2, numéro SIM,
Terminal ID, Manufacturer ID, Terminal Model, Province ID, City ID, plaque et
code couleur. Serveurs secondaires : Conserver / Modifier / Supprimer. Une
suppression est une intention explicite ; elle n’est pas déduite d’un champ vide.
Une identité facultative vide signifie conserver. Aucune copie implicite d’un
IMEI, de l’exemple utilisateur ou d’une ancienne réponse JT808 dans ces champs.
Les valeurs actuelles de la caméra restent inconnues dans ce formulaire.

Le bouton Utiliser le serveur EXADCAM renseigne uniquement le principal dans le
formulaire, par défaut 62.171.190.15:7808. Valeurs configurables par
SMARTVISION_SERVER_HOST et SMARTVISION_SERVER_PORT dans config/smartvision.php.
Annuler les modifications revient à la dernière sauvegarde chargée ; Recharger
relit le brouillon EXADCAM, pas la caméra. Enregistrer en attente persiste le
brouillon avec révision, auteur et date. État toujours « En attente · Non
envoyée », bouton Envoyer désactivé. Aucune file d’envoi automatique, aucun
listener appelé et aucun réglage ou identifiant du registre modifié.

Migration additive smartvision_configuration_drafts : un brouillon par dashcam,
relation supprimée avec celle-ci, auteur nullable si compte retiré. Verrouillage
de la caméra et de l’acteur, contrôle de révision et empreinte d’identité /
affectation pour refuser un onglet périmé. Un ancien brouillon est signalé si la
caméra a changé d’identité ou de véhicule. Les contacts GPS ne l’invalident pas.
Validation stricte des hôtes, ports, longueurs et champs autorisés ; CSRF,
authentification, compte actif, superadmin et limitation des requêtes conservés.

Contrôles réellement effectués : 48 tests PHP ciblés / 350 assertions
(SmartvisionConfigurationTest et DashcamRegistryTest), SQLite en mémoire.
Pint, syntaxe JavaScript et git diff --check réussis. Prévisualisation locale sur
une base SQLite distincte avec deux appareils fictifs : sauvegarde et relecture
du brouillon, suppression prévue du Backup, serveur IP2 personnalisé, zéros
initiaux du SIM/Terminal ID préservés. Rendu bureau, 820 px et 390 px contrôlé,
aucun champ/bouton débordant sur tablette ou mobile. Pas de suite exhaustive,
aucune commande matérielle ni essai physique sur caméra.

Déploiement de 13 fichiers vérifiés par empreinte, migration ciblée appliquée,
sauvegarde /var/backups/exadcam-smartvision-form-20260929-075516. Cache de
configuration régénéré et seules vues compilées purgées ; aucun cache de baux
vidéo effacé. PID conservés : GPS 263273, vidéo 190615, audio 167293,
enregistrements 263274, monitoring 268963, rapports 396144. Navigateur de
production : action présente sur les deux SmartVision, formulaire du Hilux
9863BV01 chargé avec état initial inconnu, zéro erreur JS relevée ; table de
brouillons encore vide. Aucun réglage de test sauvegardé en production.

Reste à faire : intégrer puis valider le véritable transport de configuration
CarAssist/constructeur et sa lecture de retour avant d’activer l’envoi. Toute
future activation doit nécessiter une revue et un envoi explicites ; ne pas
expédier automatiquement les anciens brouillons. Le Backup du Hilux n’a pas
été supprimé par cette livraison. Le modèle ESTON reste hors périmètre.

Retour arrière : restaurer routes/web.php et les deux vues dashcams sauvegardées,
régénérer le cache de configuration si nécessaire, purger uniquement les vues.
La table additive peut rester pour conserver les brouillons ; sa suppression
exigerait de traiter explicitement les données qu’elle contient. Aucun retour
arrière n’a été exécuté.


## 30 septembre 2026 — Connexion directe au Hilux et confirmation du principal

Demande : se connecter à la SmartVision 352538106693487 et conserver le serveur
principal EXADCAM. Cible vérifiée : dashcam 4, Toyota Hilux 9863BV01, terminal
JT808 053810669348. À 16 h 17 min 16 s (Kinshasa), le listener confirme une
session active (HTTP 200, online=true) ; dernier contact reçu cinq secondes
auparavant. Les journaux montrent une authentification à 16 h 13 min 55 s.

Lecture directe ciblée 0x8106 envoyée à 16 h 17 min 48 s, réponse 0x0104 reçue
à 16 h 17 min 53 s : 0x0013 = 62.171.190.15 ; 0x0018 = 7808 ; 0x0017 de
longueur zéro ; 0x0026 de longueur quatre, quatre octets nuls. Le serveur
principal est déjà correct et reste inchangé. Aucune commande 0x8103,
aucun redémarrage, aucune déconnexion, aucune modification des brouillons.

Les deux tests existants du lecteur ont été exécutés et ont réussi avant la
requête. Empreinte du lecteur distant conforme à la copie vérifiée. Le lecteur
ne cible que le Hilux et supprime ses observateurs après 45 secondes. Inspecteur
loopback refermé, PID GPS conservé à 263273. Compte rendu filtré conservé dans
/home/exad-cam/smartvision-backup-command-20260929/ sur le serveur.

Limite inchangée : le Backup standard vide ne démontre pas l’absence des champs
CarAssist ipbak/portbak, ip2/port2 ou ipbak2/portbak2. Aucun changement arbitraire
de paramètre ni encapsulation propriétaire non vérifiée. La suppression des
Backups CarAssist reste non réalisée ; on ne peut pas annoncer « EXADCAM seul ».
La correspondance standard des paramètres a été revérifiée dans la référence
primaire https://github.com/QuecPython/jtt808/blob/master/docs/en/API_Reference.md.

Contrôle ultérieur à 16 h 20 min 13 s (Kinshasa) : le listener répond HTTP 409,
Device offline ; dernier contact enregistré à 16 h 19 min 16 s. La caméra a
donc perdu sa session après la réponse de lecture. Le service GPS reste actif,
PID 263273. Ce constat ne démontre pas la cause de la fermeture et ne doit pas
être présenté comme une connexion stabilisée. Aucun changement de serveur
caméra ni redémarrage n’a été envoyé.


## 30 septembre 2026 — Essai distant d’effacement du Backup standard

Périmètre confirmé par l’utilisateur : commande distante depuis EXADCAM,
sans passer par Windows/Phone Link, USB ni Wi-Fi. Cible unique : Toyota Hilux
9863BV01, IMEI 352538106693487, dashcam 4, terminal 053810669348. Le Nissan
Patrol et les caméras ESTON ne sont pas ciblés.

À 16 h 36 min 14 s (Kinshasa), la session du Hilux est active (HTTP 200).
Essai exécuté directement sur sa connexion JT808 2013 à 16 h 37 :

- Lecture 0x8106 avant envoi : principal 62.171.190.15, port TCP 7808,
  Backup standard 0x0017 déjà vide.
- Une seule écriture 0x8103, série 0xfc05, corps hexadécimal
  01 00000017 00 : un paramètre, identifiant 0x0017, chaîne de longueur zéro.
  Aucun autre paramètre écrit, aucun changement du serveur principal.
- La caméra accuse réception avec résultat 0 (succès) environ 0,9 seconde
  après l’écriture. La relecture ciblée confirme le principal inchangé et
  le Backup standard vide à 16 h 37 min 08 s.

Ce résultat valide l’acceptation de cette commande, pas l’effacement de tous
les serveurs du formulaire CarAssist. Le champ était déjà annoncé vide avant
le test ; une valeur vide après envoi ne démontre pas que les champs
propriétaires ipbak/portbak, ip2/port2 et ipbak2/portbak2 ont été modifiés.
Aucune écriture à des identifiants propriétaires supposés, aucun 0x8105,
aucun reset, changement de protocole, arrêt de socket ou redémarrage de service.

Script ponctuel et tests dans l’espace de travail :
analysis/smartvision-remove-backups-20260930/. Huit tests ciblés exécutés et
réussis avant envoi : sélection de la cible, garde du principal, rejet des
lectures incomplètes, corps d’écriture strict, corrélation des réponses,
rejet caméra, expiration sans nouvelle tentative, relecture et nettoyage.
Syntaxe du lanceur vérifiée. Ce ne sont pas des tests de l’application complète.
Copie distante vérifiée SHA-256 :
a39b5626368f0cf700bc14a865f1f0e61798ee32440a9f3ea65c3514ed8c8389.
Reçu sous /home/exad-cam/smartvision-remove-backups-20260930/ ; inspecteur
temporaire limité au loopback et refermé, PID GPS conservé à 263273.

Lecture complémentaire 0x8104 à 16 h 38 min 15 s : inventaire de 20 paramètres,
incluant 0x0017 vide, principal et port inchangés. Aucun champ propriétaire
CarAssist identifiable dans cet inventaire filtré. Contrôle à 16 h 40 min 06 s :
session absente. Le journal montre une fermeture par le pair à 16 h 39 min 05 s,
puis une nouvelle authentification spontanée à 16 h 40 min 10 s. La cause de
cette fermeture n’est pas établie ; la commande n’a donc pas démontré une
stabilisation de la connexion. Pas de coupure provoquée côté serveur.

Recherche du transport propriétaire : le code primaire CarAssist Android
confirme aussi l’écriture cloud, et non seulement sa lecture.
Jt808ConfigActivity.java:156–226 construit les champs puis appelle
WebSocketUtil.java:1855–1870 : cmd=settings, set.jt808, wakeup=1,
transport relay WebSocket. Une chaîne vide et un port zéro sont transmis
explicitement. ip3/port3 et ip4/port4 ne sont inclus que si présents ; ne pas
inventer leur disponibilité ni toucher aux paramètres JT905 ou d’identité.

Cette commande cloud pourrait être émise par un client logiciel distant sans
Windows. Son exécution exige cependant une session CarAssist autorisée et le
SN réel de la caméra liée au compte. Ces éléments ne sont pas disponibles ;
ne pas remplacer le SN par l’IMEI ni fabriquer une authentification. Aucun
relais cloud envoyé et aucune correspondance de ces champs avec une extension
JT808 de ce firmware validée. La suppression de tous les backups reste donc
non vérifiée et non résolue. Le formulaire d’envoi non fonctionnel reste retiré.

Contrôle après reconnexion : à 16 h 40 min 51 s, nouvelle réponse 0x0104
confirmant 62.171.190.15:7808 et 0x0017 vide. Comparaison des inventaires
sauvegardés : le 29 septembre, 19 paramètres, 0x0017 absent ; après l’écriture,
20 paramètres avec 0x0017 vide. Cela établit son ajout à la table exposée par
JT808, sans établir son lien avec les champs propriétaires CarAssist ni une
persistance après coupure d’alimentation. Les services GPS, vidéo, audio et
enregistrements sont actifs au dernier contrôle, inspecteur refermé.
