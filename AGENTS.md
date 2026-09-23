# Consignes de travail EXADCAM

## Contexte et suivi

- Avant de modifier le projet, lire `docs/project-context.md` et les dernières
  entrées de `docs/project-history.md`.
- Après chaque lot significatif, mettre à jour `docs/project-history.md` : date,
  demande, réalisation, fichiers concernés, contrôles réellement exécutés et
  limites restantes. Ajouter les corrections sans effacer l’historique des décisions.
- Actualiser la synthèse de l’état courant et les documents spécialisés lorsque
  le changement les concerne. Ne pas présenter une fonctionnalité prévue ou
  simulée comme une fonctionnalité livrée.
- Distinguer les tests ciblés d’une suite complète et ne pas réutiliser un ancien
  résultat comme s’il venait d’être exécuté.
- Ne jamais inscrire de mots de passe, jetons, clés ou contenu de `.env` dans la
  documentation. Mentionner uniquement le provisionnement ou la configuration.

## Cadre confirmé

- EXADCAM est indépendant d’EXAD Tracking. La référence est consultée en lecture seule.
- Développer le web d’abord ; l’application mobile viendra ensuite.
- Utiliser Blade et Bootstrap local, sans CDN ni Tailwind. Conserver l’identité
  visuelle et les ressources locales approuvées.
- La connexion est gérée par Laravel Fortify. Préserver les contrôles serveur,
  CSRF, les autorisations et la séparation de la base de test et de la base métier.
