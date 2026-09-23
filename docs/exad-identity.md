# Identité EXAD et dashcam au premier plan

Mise à jour du 15 septembre 2026. La palette de l’interface est conservée.

## Logo officiel

Sources fournies par EXAD :

- `G:/EXAD/EXAD WEB SITE/NEW LOGO/exad-1200x1200.png`
- `G:/EXAD/EXAD WEB SITE/NEW LOGO/exad-1200x1200-white.png`

Les deux fichiers sont conservés sans modification dans
`resources/images/brand/exad-source-dark.png` et `exad-source-white.png`.
Le dessin et la signature « Solution & Services » proviennent de ces fichiers.
Le logo n’est pas redessiné par IA.

Deux enveloppes SVG assurent sa présentation dans le thème :

- `public/images/brand/exad-logo-navy.svg` : bleu proche de `#203D65`, gris bleuté proche de `#879BAF`.
- `public/images/brand/exad-logo-light.svg` : blanc et gris bleuté clair pour les fonds sombres.

Chaque SVG contient le PNG officiel encodé et applique un filtre de couleur qui
conserve son canal alpha. Le `viewBox` élimine les marges transparentes à l’affichage,
sans déformer les lettres. Le composant Blade `company-logo` permet leur réutilisation
sur la connexion et dans le menu latéral de la plateforme.

## Visuel de la dashcam

- Fichier utilisé : `public/images/brand/exadcam-dashcam-focus.webp`.
- Original : `resources/images/brand/exadcam-dashcam-focus.png`.
- Outil : génération d’images intégrée `image_gen`, mode édition.
- Référence : le précédent visuel `resources/images/brand/exadcam-cabin.png`.

La dashcam est désormais le sujet principal, sur ordinateur comme sur mobile.
Le cadrage est adapté par CSS. Cette photographie est générée par IA et représente
un appareil illustratif, pas une reproduction certifiée du modèle ES500-603.

## Prompt final du visuel

Use case: precise-object-edit. Input image 1 is the EXADCAM cabin photograph to edit. Keep its photographic realism, ink navy and graphite color palette, understated warm daylight, windshield/cabin context and professional corporate atmosphere. The requested change is to make the DASHCAM the unmistakable hero subject instead of the distant fleet: reframe as a vertical 2:3 premium automotive product photograph shot inside the vehicle, with a sharply focused large compact black dual-camera dashcam occupying about 55 percent of the frame width in the middle third, complete device and credible windshield mount visible, nuanced glass reflections on the lens, realistic black polymer finish and fine manufacturing details. Place the lens and camera body around 55 percent across and 43 percent down. The device must read immediately as a professionally installed fleet dashcam, not a phone, handheld camera, projector or 3D mockup. Keep the road and a white fleet vehicle very softly out of focus beyond the glass, only environmental context. Leave the upper-left area quiet and dark for a small white EXAD logo added later in HTML, and the bottom quarter dark and uncluttered for a headline added later in HTML. Maintain the current corporate navy mood; do not add strong purple, blue neon, futuristic overlays or color changes. This is a generic contextual device, not a claim to reproduce a named hardware model. No printed text, no logos, no watermark, no UI, no diagrams. Entire device must be inside the image boundaries. Photorealistic editorial photograph, believable optics, exceptional professional quality.
