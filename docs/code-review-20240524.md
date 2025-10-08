# Revue du code Theme Export – JLG (24 mai 2024)

## Points forts
- Architecture orientée objets : les classes `TEJLG_Admin_*` encapsulent bien les responsabilités (enregistrement du menu, rendu des écrans, traitement des formulaires), ce qui facilite l’extension et les tests unitaires ciblés.
- Sécurité : chaque action critique vérifie les capacités (ex. `TEJLG_Capabilities::current_user_can('exports')`) et les formulaires utilisent des nonces (`wp_verify_nonce` / `wp_create_nonce`).
- Accessibilité & UX : l’interface admin exploite les composants WordPress (`components-card`, classes `wp-ui-*`) et les scripts localisés exposent des libellés internationalisés et des attributs ARIA (`aria-live`, `aria-atomic`).

## Risques et axes d’amélioration
1. **Compatibilité CSS (color-mix)**  
   La feuille `assets/css/admin-styles.css` utilise massivement `color-mix()`. Les navigateurs antérieurs (Safari < 15.4, Chrome < 111) ignorent cette fonction et annulent la déclaration entière, ce qui peut laisser des fonds transparents ou des bordures absentes.  
   → Solution implémentée : ajout de fallbacks dans un bloc `@supports not (color: color-mix(...))` et séparation du fond en `background-color`/`background-image` pour conserver une mise en page lisible sans `color-mix()`.

2. **Surveillance des fichiers volumineux**  
   `assets/js/admin-scripts.js` dépasse 3 000 lignes. L’absence de découpage en modules ou en sous-fichiers rend la maintenance plus délicate (risque d’effets de bord lors des modifications).  
   → Recommandation : migrer progressivement vers des modules ES (via un bundler ou `@wordpress/scripts`) pour segmenter les responsabilités (dropzones, pattern tester, file d’attente d’exports…).

3. **Gestion des erreurs asynchrones**  
   Les appels AJAX de la file d’export gèrent bien les messages (`failed`, `unknownError`). En revanche, aucune stratégie de retry progressif n’est prévue et un simple `fetch` échoué stoppe le processus.  
   → Recommandation : introduire un backoff exponentiel (2–3 tentatives) et enregistrer l’échec côté PHP afin de nourrir l’historique (`TEJLG_Export_History`).

4. **Journalisation enrichie**
   `TEJLG_Export_History::record_job()` enregistre désormais la durée, la taille, l’origine et l’initiateur d’un export ; ces données alimentent l’interface et la CLI.
   → Prochaine étape : documenter et exposer un hook dédié pour diffuser ces informations vers un système externe (Slack, webhook maison) et prévoir un rapport agrégé.

5. **Tests automatisés**
   Le dépôt expose des scripts `npm run test:php` et `npm run test:e2e`, et une première suite PHPUnit (`tests/test-export-sanitization.php`) couvre la sanitisation des motifs.
   → Clarifier dans la documentation comment préparer l’environnement de test (WordPress de démo, configuration Playwright) et ajouter un exemple Playwright pour valider les parcours multi-étapes.

## Suivi
- Prioriser la factorisation de `admin-scripts.js` lors d’une future évolution majeure.
- Documenter la préparation de l’environnement de test (base WordPress, Playwright) pour fiabiliser la CI.
