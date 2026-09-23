# Typographie EXADCAM

La police d’interface est **Manrope**, en version variable (graisses 200 à 800),
pour la connexion et le tableau de bord. Les titres du formulaire utilisent une
graisse 600 ; la lecture courante conserve une graisse 400. Les logos EXAD restent
des visuels et ne sont pas remplacés par du texte.

Les fichiers WOFF2 latins et latins étendus sont servis depuis `public/fonts/manrope`.
Le préchargement du latin et `font-display: swap` limitent l’attente au premier
affichage. Le navigateur n’appelle aucun service de polices externe. Les fichiers
sont inclus dans le projet : aucune installation supplémentaire n’est requise
pour les servir en production.

Les deux layouts incluent `partials/fonts.blade.php`. La variable CSS
`--exad-font-sans` dans `public/css/fonts.css` définit la famille commune. Les
accents français, les ligatures œ/Œ, la ponctuation et le symbole euro sont inclus
dans les plages Unicode fournies par la distribution.

Source : [Google Fonts — Manrope](https://github.com/google/fonts/tree/main/ofl/manrope).
Fichiers WOFF2 issus de la distribution Google Fonts v20, récupérés le 15/09/2026.
La licence SIL Open Font License 1.1 est conservée dans `public/fonts/manrope/OFL.txt`.

Empreintes SHA-256 des fichiers distribués :

- `manrope-latin-ext-v20.woff2` : `3911b66d9f2e005a4b989223405d0e5032619c668597ba467cc76a23c8fffcfb`
- `manrope-latin-v20.woff2` : `a30ddcd349703aff7464c34bef3fffdff405ee50c113440d7c8693c02d210972`
