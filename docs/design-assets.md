# Visuels corporate EXADCAM — première version

La version actuelle met la dashcam au premier plan et utilise le logo officiel EXAD.
Voir [exad-identity.md](exad-identity.md). Les visuels ci-dessous sont conservés comme variantes.

Créés le 15 septembre 2026 avec l’outil intégré `image_gen` (aucun appel API séparé).
Ces images photoréalistes sont générées par IA : elles représentent le contexte
de supervision d’une flotte, et non une photographie d’un site EXAD existant.
La dashcam est illustrative et n’est pas une reproduction certifiée de l’ES500-603.

## Assets locaux

- `public/images/brand/exadcam-fleet.webp` : flotte professionnelle, version ordinateur.
- `public/images/brand/exadcam-cabin.webp` : vue depuis le véhicule, version mobile/tablette.
- Originaux PNG conservés dans `resources/images/brand/`.

Les WebP sont des conversions optimisées des images générées. Le composant
`picture` sert le visuel adapté à l’écran. Aucun service d’images distant n’est appelé.
Les titres et le logo restent du HTML/SVG pour conserver leur netteté et leur accessibilité.

## Prompt final — flotte

Use case: photorealistic-natural. Asset type: premium corporate website login hero photograph for EXADCAM, a vehicle fleet and dashcam supervision platform. Create a genuinely photographic, highly professional editorial image, not a web page or illustration. Subject: a clean unbranded dark graphite modern commercial SUV in the near foreground, a silver company pickup and two white logistics trucks farther behind it, aligned along a broad paved access road outside a contemporary business logistics facility in central Africa. Tasteful modern African urban setting with a few palms, glass and steel architecture, no recognizable monument. Camera: elevated three-quarter front angle from roughly 3 meters high, 50mm professional full-frame lens, realistic automotive proportions and exact wheels, crisp material detail, subtle natural reflections on bodywork. Composition: portrait orientation, approximately 2:3, intended to cover the left half of a desktop login page. Keep vehicles visually strongest in the middle third, roughly 38 to 64 percent down, with generous dark architectural negative space at the upper part and quiet dark asphalt in the bottom quarter for HTML headline overlay. Mood: calm early blue-hour corporate photography with a small amount of warm sunset light, ink navy shadows and restrained neutral colors, believable natural exposure, excellent detail, polished but not oversaturated. Keep all vehicles wholly credible and realistic. Constraints: no lettering, no logos, no text, no watermark, no signage with words, no UI, no icons, no illustrations, no cartoons, no sci-fi, no neon, no speed streaks, no collage.

## Prompt final — dashcam à bord

Use case: photorealistic-natural. Asset type: secondary corporate brand photograph for the responsive mobile and tablet login banner of EXADCAM, a dashcam and fleet management web application. Generate an elegant, very realistic commercial editorial photograph, landscape 3:2 orientation. View from the passenger side of a modern company SUV cabin, looking toward the windshield and the broad tree-lined approach of a modern logistics center in a central African city at dawn. A compact unbranded black dual-lens fleet dashcam, unobtrusively mounted on the upper windshield near the rear-view mirror, is clearly visible near the right third of the image; the dashboard foreground is refined charcoal textured automotive material. Through the windshield, an out-of-focus white fleet vehicle and modern logistics buildings give subtle context. No people necessary. Photographic real-world optics, exact credible perspective, realistic windshield reflections and device mounting, premium automotive photography, full-frame 50mm lens, moderate shallow depth of field. Palette: understated graphite and ink navy cabin, natural morning blue outside, subtle warm light, no saturation excess. Compose for a wide horizontal crop with device and distant fleet in the center band; keep the left third uncluttered for small HTML caption. This is a generic contextual dashcam, not a replica of a named hardware model. Constraints: no visible logos, no text, no watermark, no illustration, no UI overlay, no diagrams, no sci-fi, no neon, no cables blocking the road view. Make it look like a commissioned professional photograph, not a 3D render.
