# Presets graphiques pour Theme Export - JLG

Ce document propose plusieurs presets d'interface réutilisables inspirés d'écosystèmes UI populaires. Chaque preset inclut une intention graphique, des variables de design principales et des composants phares afin de pouvoir décliner rapidement un thème cohérent dans l'administration WordPress.

## 1. Preset « Pavillon » — esprit Headless UI
- **Intention** : sobriété utilitaire avec une hiérarchie basée sur la typographie et les espacements, tout en conservant la liberté d'assemblage propre aux composants non stylisés.
- **Palette** : tons neutres (`--color-surface: #f8fafc`, `--color-border: #e2e8f0`) relevés par un accent profond (`--color-accent: #2563eb`).
- **Typographie** : polices sans empattement à chasse variable (Inter, Source Sans) avec un `font-weight` modulé par rôle (600 pour les titres, 500 pour les CTA).
- **Composants clés** : cartes légères (ombre douce, radius 12px), en-têtes collants, menus déroulants minimalistes utilisant des transitions d'opacité.
- **Interactions** : focus visibles (anneau 2px accentué), animations discrètes (`transition: 120ms ease-out`).
- **Cas d'usage** : tableau de bord et écrans listant des exports où la clarté prime, sans surcharge décorative.
- **Activation rapide** : un bouton « Preset Pavillon+ » est disponible dans le bandeau d’action du centre de contrôle pour appliquer un contraste élevé sans passer par les options avancées.

## 2. Preset « Spectrum » — esprit Shadcn UI
- **Intention** : offrir des interfaces ciselées, modulaires, avec un fort travail sur les contrastes et la micro-typographie.
- **Palette** : duo clair/foncé par défaut (`--color-surface: #ffffff`, `--color-surface-strong: #0f172a`) et un accent dynamique (`--color-accent: #7c3aed`). Prévoir un jeu complet de 12 teintes pour générer des variantes.
- **Typographie** : Spline Sans Mono pour les valeurs techniques, Work Sans pour les titres. `letter-spacing` resserré sur les labels.
- **Composants clés** : onglets segmentés, toasts empilés, sliders numériques, avancement de file d'attente avec gradient animé.
- **Interactions** : micro-animations sur `transform` (zoom 1.02), transitions `cubic-bezier(0.16, 1, 0.3, 1)` pour rappeler les motions Shadcn.
- **Cas d'usage** : assistant d'import/export avancé, écrans nécessitant de nombreuses options tout en restant lisibles.

## 3. Preset « Orbit » — esprit Radix UI
- **Intention** : composantisation poussée, axes clairs pour les états, emphase sur l'accessibilité (ARIA, contrastes).
- **Palette** : `--color-surface: #101828`, `--color-surface-alt: #1d2939`, `--color-border: rgba(255,255,255,0.12)`, accent vert `--color-accent: #22c55e`.
- **Typographie** : Geomanist ou IBM Plex Sans, haute lisibilité. `line-height` généreux (1.6) pour les paragraphes.
- **Composants clés** : boîtes de dialogue modales superposables, popovers avec flèches, menus contextuels hiérarchiques, switchs état ON/OFF très contrastés.
- **Interactions** : états actifs clairement différenciés (fond accent + contour), focus ring double (1px blanc + 2px accent).
- **Cas d'usage** : gestion des paramètres avancés, panneaux coulissants (Sheet) pour l'historique des exports.

## 4. Preset « Agora » — esprit Bootstrap 5
- **Intention** : grille robuste, composants familiers, large compatibilité.
- **Palette** : duo primaire (`--bs-primary: #0d6efd`) et neutres modulés (`--color-surface: #f1f5f9`, `--color-border: #cbd5f5`).
- **Typographie** : System font stack (`-apple-system`, `Segoe UI`, `Roboto`). Titres en 1.25rem, paragraphes 1rem.
- **Composants clés** : alertes colorées, badges de statut, tables responsives avec en-têtes collants, boutons `btn-outline` pour les actions secondaires.
- **Interactions** : transitions standard (150ms ease-in-out), effets de survol visibles (`box-shadow` léger).
- **Cas d'usage** : pages de paramètres, listes d'archives, interfaces devant rester familières aux utilisateurs WordPress.

## 5. Preset « Agora Dense » — esprit Semantic UI
- **Intention** : densité contrôlée, typographie modulée, composants segmentés.
- **Palette** : `--color-surface: #ffffff`, `--color-border: #d4d4d8`, `--color-accent: #db2828` (pour les avertissements) et `#21ba45` (succès).
- **Typographie** : Lato ou Open Sans avec tailles multiples (12/14/16px) pour hiérarchiser les listes d'informations.
- **Composants clés** : segments empilables, steps (étapes) horizontales, tableaux à cellules colorées, boutons iconiques alignés à gauche.
- **Interactions** : changements d'état par `background`/`border` combinés, transitions `ease` 120ms.
- **Cas d'usage** : workflows multi-étapes (wizard d'import), affichages analytiques.

## 6. Preset « Kinesis » — esprit Anime.js
- **Intention** : motion design expressif pour mettre en avant les progressions et feedbacks.
- **Palette** : fond sombre pour faire ressortir les animations (`--color-surface: #0b1120`, `--color-accent: #38bdf8`, `--color-warning: #f59e0b`).
- **Typographie** : Space Grotesk pour le dynamisme, poids 500-600.
- **Composants clés** : timelines animées, compteurs de progression avec traînées lumineuses, boutons avec effets de particules lors de la confirmation.
- **Interactions** : importer les courbes Anime.js (`anime.easing('easeOutElastic')`), jouer sur des durées 300–600ms. Prioriser la réduction de mouvement via `prefers-reduced-motion`.
- **Cas d'usage** : feedback visuel lors des exports/imports lourds, onboarding guidé avec étapes animées.

## Mise en œuvre transversale
- Déclarer les variables CSS des presets dans `:root` et les surcharger via une classe utilitaire (ex. `.preset--pavillon`).
- Mapper chaque preset vers un jeu de tokens TypeScript/PHP pour l’export automatique vers le `theme.json`.
- Prévoir un contrôleur en React (ou JS vanilla) permettant de prévisualiser et d’activer un preset depuis l’écran d’administration.
- Documenter les composants compatibles et les dépendances éventuelles (Radix Primitives, Anime.js) pour faciliter la maintenance.
