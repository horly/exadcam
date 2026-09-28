# Demande technique — reconnexion JT808 et réveil ES500

Brouillon non envoyé. Aucun secret ni identifiant de compte.

Appareil commercial ES500-603, propriétés reçues en JT808 `0x0107` :
- Manufacturer: 12345; model: FX; hardware: T1.
- Firmware: PL_TW1-V1.1_4G_NCS_EN_2.2.31_2.3.7.
- Deux exemplaires annoncent ces mêmes versions ; l'un continue de transmettre
  et renouvelle ses connexions, l'autre reste sans session EXADCAM après fermeture.

Le second appareil est alimenté en continu et immobilisé. CarAssist reste
accessible selon l'utilisateur. Enregistrer à nouveau la configuration JT808
dans CarAssist restaure la connexion au serveur tiers.

Merci de fournir :

1. La logique de reconnexion après FIN, RST et perte cellulaire, les temporisations
   et le comportement Main/Backup, y compris le retour au principal.
2. La commande officielle permettant de relancer seulement le client JT808,
   accessible par le canal de réveil quand sa session TCP est absente.
3. Le SDK/API authentifié permettant au serveur tiers de réveiller l'appareil,
   avec méthode d'obtention des droits d'intégration et association du SN au compte.
4. Les paramètres de veille, la distinction ACC réel/rapporté, et la possibilité
   de maintenir ou rétablir JT808 sans ouvrir une vidéo CarAssist en permanence.
5. Le firmware correctif ou la procédure documentée si cette version présente
   un défaut de reconnexion ; aucun firmware ne sera installé sans validation.

Relevé des paramètres standard avant essai : heartbeat 20 s, timeout TCP 10 s,
retransmissions 0, intervalles de position normal/veille 10 s. Modifier timeout
à 30 s et retransmissions à 3 est acquitté et confirmé par relecture, mais ne
rétablit pas à lui seul la reconnexion après fermeture. Le paramètre standard
0x0017 renvoie une chaîne vide alors que CarAssist affiche une adresse Backup.
Merci de préciser si ces deux configurations correspondent au même client réseau.

L'application Android officielle analysée envoie pour l'enregistrement :
`relay:<n>{"peer":"<SN>","cmd":"settings","set":{"jt808":{<configuration>}},"wakeup":1}`.
Cette structure n'est pas un contrat d'intégration : nous demandons la méthode
officielle d'authentification, les réponses, limites et conditions d'utilisation.
