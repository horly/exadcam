> Hors périmètre confirmé le 25 septembre 2026 : l'utilisateur ne souhaite
> aucune demande fournisseur. Ce brouillon n'a pas été envoyé. Ne pas l'envoyer
> ni en faire un prérequis à l'investigation de la liaison directe JT808.

# Demande technique SmartVision / CarAssist — brouillon non envoyé

Objet : Réveil distant et reconnexion JT808 des 4G SmartVision, manuel T2

Nous intégrons deux caméras 4G SmartVision dans notre plateforme EXADCAM.
Le propriétaire confirme que le manuel « 4G SmartVision Dash Camera — User
Manual » diffusé sous la référence T2 correspond à son équipement.

Les propriétés lues précédemment sont : modèle déclaré FX, matériel T1,
firmware PL_TW1-V1.1_4G_NCS_EN_2.2.31_2.3.7. Merci de confirmer la correspondance
entre cette variante et la référence commerciale T2 avant toute proposition
de firmware. Nous ne demandons pas une mise à jour à l'aveugle.

Les appareils ont déjà transmis positions JT808 2013, vidéo JT1078 et audio
à notre serveur. Le problème concerne le retour après veille ou fermeture
de la liaison JT808. Ils peuvent rester accessibles dans CarAssist pendant
qu'ils n'ont plus de connexion vers EXADCAM. Réenregistrer les mêmes paramètres
serveur depuis CarAssist a rétabli la connexion. Un retour autonome lent a
aussi été constaté ; nous n'avons pas établi un délai reproductible.

Merci de fournir pour ce firmware :

1. Le SDK/API officiel permettant à une plateforme tierce de demander le réveil
   sans ouvrir un flux vidéo CarAssist : authentification, association au numéro
   de série, retour d'état, durée et renouvellement du réveil.
2. Le protocole ou réglage permettant de relancer le service JT808 sans reboot,
   ainsi que la politique de reconnexion après FIN, RST, perte réseau et ACC OFF.
3. Les conditions de coexistence CarAssist et serveur JT808 tiers, et le rôle
   exact de Main, Backup, IP2 et Backup IP2 sur cette variante.
4. Les moyens documentés de conserver JT808 en mode parking, avec leur effet
   sur la consommation, et les éventuels correctifs de firmware correspondants.
5. Le protocole réellement requis par l'option CMSV6/CMSV7 de ces appareils :
   JT808/JT1078 ou protocole propriétaire, avec documentation de l'intégration.

Nous souhaitons garder EXADCAM comme interface utilisateur. La disponibilité
de la vidéo dans CarAssist seule ne valide pas une reconnexion JT808. Nous
disposons déjà d'un récepteur compatible 2013 et 2019 ; nous ne supposons pas
qu'un changement d'année du protocole règle ce problème.

Aucun identifiant de compte, mot de passe, IMEI, position GPS ou journal de
production n'est joint à cette demande.
