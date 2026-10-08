# Notifications EXADCAM

Fonction web livrée le 8 octobre 2026. Les alertes restent dans le menu Alertes ; le toast complète cette liste sans modifier sa définition (alarmes montantes sur sept jours et pertes de contact actives).

## Utilisation

Les nouvelles alertes affichent le véhicule, son immatriculation, le type, la date et un lien vers Alertes. Le premier chargement ne rejoue pas les anciennes alertes. Le flux fonctionne sur toutes les vues visibles de l'application et se suspend quand l'onglet est masqué ; il reprend à son retour. Il ne s'agit pas d'une notification système lorsque l'application est fermée.

Dans Alertes, activer le son ou utiliser Tester le son. La préférence est conservée dans le navigateur pour le compte courant et reste désactivée initialement. Après rechargement, une interaction autorise AudioContext selon les règles du navigateur. Son original : deux notes descendantes E6/B5, durée utile environ 0,52 seconde ; distinct des notes ascendantes de Tracking. Le test du son n'active pas les alertes sonores.

## Implémentation et accès

GET /dashboard/alerts/recent : session authentifiée, utilisateur actif, permission map.view et FleetAccess. Même clôture d'affectation véhicule/flotte que la liste. Réponse privée no-store ; absence d'IMEI, coordonnées et données techniques côté client. Sans curseur : base silencieuse. Ensuite trois paramètres requis ensemble : after_alarm, after_connection_at (Y-m-d H:i:s, UTC), after_connection_id. Réponse data, total, cursor et has_more. Vingt alarmes et vingt pertes de contact au maximum par réponse. Les curseurs avancent sur les données effectivement traitées ; une reconnexion puis une nouvelle perte reste notifiable. La borne haute des paquets est capturée avant calcul pour ne pas sauter une arrivée concurrente.

Code : DashboardService et DashboardPreviewController, alert-notifications.mjs, alert-sound.mjs, alert-notifications.css, partiels alert-notifications et alert-sound-controls. Texte FR/EN dans lang/*/notifications.php. Aucun nouvel enregistrement serveur, aucune migration. Données de préférence et curseurs locaux limités au compte ; Web Locks évite les notifications répétées entre onglets compatibles. Erreurs de réseau réessayées sans avancer ; 401/403 arrêtent le flux et retirent les toasts.

Validation, limites de recette et reçu : dernière entrée de project-history.md.
