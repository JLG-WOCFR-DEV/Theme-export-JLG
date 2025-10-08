# Revue de code

## Aperçu général
Le cœur du plugin repose principalement sur deux classes statiques volumineuses : `TEJLG_Export` et `TEJLG_Admin_Export_Page`. Elles regroupent une grande diversité de responsabilités (planification, persistance, rendu d’interface, manipulation de fichiers, etc.), ce qui complique la lisibilité et la maintenance long terme. J’ai résumé ci-dessous les opportunités de refactorings structurants, de nettoyages ciblés et quelques points de vigilance sur les dépendances.

## Refactorings structurants proposés

### 1. Scinder `TEJLG_Export` en services spécialisés
`TEJLG_Export` dépasse 1 700 lignes et mélange des préoccupations très différentes : sanitisation d’entrées utilisateur, calculs de planification, gestion de file d’attente, persistance des jobs et manipulation d’archives ZIP.【F:theme-export-jlg/includes/class-tejlg-export.php†L22-L423】【F:theme-export-jlg/includes/class-tejlg-export.php†L1104-L1184】【F:theme-export-jlg/includes/class-tejlg-export.php†L1408-L1760】

**Pistes concrètes :**
- Extraire une classe `ExclusionPatternsSanitizer` (ou équivalent) pour regrouper la logique des méthodes `sanitize_exclusion_patterns` / `sanitize_exclusion_patterns_string` et éviter les vérifications redondantes (ex. multiples `is_string` après un cast en chaîne).【F:theme-export-jlg/includes/class-tejlg-export.php†L22-L106】
- Isoler la logique de planification (`maybe_schedule_theme_export_event`, `calculate_next_schedule_timestamp`, etc.) dans un service `ExportScheduler`, facilitant les tests unitaires de calcul d’horodatage.【F:theme-export-jlg/includes/class-tejlg-export.php†L200-L383】
- Déplacer la persistance des jobs (méthodes `persist_job`, `get_job`, `delete_job`, `cleanup_stale_jobs`, etc.) dans un repository dédié. Cela clarifierait la responsabilité du service principal et permettrait de tester séparément le nettoyage des options WordPress.【F:theme-export-jlg/includes/class-tejlg-export.php†L1408-L1760】
- Conserver dans `TEJLG_Export` uniquement l’orchestration haut niveau (démarrage d’un export, déclenchement du process de fond), ce qui réduira la surface d’une classe « dieu » difficile à faire évoluer.

### 2. Découper `TEJLG_Admin_Export_Page`
Cette classe gère à la fois la soumission de formulaires, la gestion des préférences, le rendu de deux écrans complexes (page principale et sélection des compositions) et des appels directs à `WP_Query`. Le mélange de logique métier et de rendu rend difficile l’extension ou la couverture tests.【F:theme-export-jlg/includes/class-tejlg-admin-export-page.php†L29-L237】【F:theme-export-jlg/includes/class-tejlg-admin-export-page.php†L240-L392】

**Actions proposées :**
- Isoler le traitement des formulaires (planification, export manuel, notifications) dans des classes ou traits dédiés pour clarifier le flux et faciliter la validation des autorisations.
- Déplacer la préparation des données d’affichage (historique, pagination, filtres, sélection de patterns) dans des view-models ou services afin de ne laisser à la classe d’écran que la coordination et l’appel au template.
- Envisager d’introduire des contrôleurs REST/ajx spécifiques pour certaines actions (sélection de patterns, génération de statistiques) afin d’alléger les formulaires multi-actions actuels.

### 3. Clarifier l’API de `TEJLG_Import`
La méthode `import_theme` retourne parfois un `WP_Error` (ou `null`) alors que sa PHPDoc annonce `void`, ce qui peut induire en erreur les appelants et masque des échecs potentiels.【F:theme-export-jlg/includes/class-tejlg-import.php†L125-L200】 Ajuster la signature (ou documenter correctement) et envisager d’extraire la logique d’obtention des identifiants/du filesystem dans un collaborateur injectable rendrait la méthode plus testable.

## Nettoyage et cohérence du code

- **Suppression des `require_once` redondants :** les classes incluent parfois explicitement des dépendances déjà chargées par le fichier principal (`theme-export-jlg.php`). Par exemple, `class-tejlg-export.php` `require_once` à nouveau l’historique et l’écrivain ZIP, ce qui serait superflu avec un autoloader approprié.【F:theme-export-jlg/theme-export-jlg.php†L26-L42】【F:theme-export-jlg/includes/class-tejlg-export.php†L2-L3】
- **Sanitisation centralisée :** `get_schedule_exclusion_list` duplique partiellement la logique de `sanitize_exclusion_patterns`. Une fois la sanitisation extraite, cette méthode pourrait simplement déléguer, réduisant la duplication et le risque d’incohérence.【F:theme-export-jlg/includes/class-tejlg-export.php†L394-L423】
- **Documentation/cohérence :** Aligner les PHPDoc sur les comportements réels (retours `WP_Error`, types d’array) aidera les IDE et les outils statiques, et évitera des suppositions incorrectes dans le code appelant.

## Dépendances et chargement automatique

- **Autoload Composer** : `composer.json` ne définit aucun namespace ni classe à charger automatiquement.【F:composer.json†L11-L15】 Mettre en place un autoload PSR-4 (ex. `"TEJLG\\": "theme-export-jlg/includes/"`) permettrait de supprimer les `require_once` manuels du bootstrap et de déléguer la résolution des classes à Composer, améliorant la maintenabilité et l’intégration aux outils (PHPStan, Rector, etc.).【F:theme-export-jlg/theme-export-jlg.php†L26-L42】
- **Dépendances JS** : Les dépendances Playwright côté Node sont strictement en `devDependencies`. Aucun nettoyage particulier à signaler tant qu’elles sont utilisées par les tests E2E ; garder un œil sur leur mise à jour de sécurité.

## Priorisation

1. **Refactorer `TEJLG_Export` en modules ciblés** pour réduire la dette technique et simplifier les tests.
2. **Réduire la taille et la responsabilité de `TEJLG_Admin_Export_Page`**, en introduisant des services ou contrôleurs dédiés pour les différentes actions (export, historique, patterns).
3. **Corriger la documentation/retours de `TEJLG_Import`** et factoriser la sanitisation des motifs afin d’éviter des erreurs de compréhension et du code dupliqué.
4. **Mettre en place un autoload Composer** pour supprimer les inclusions manuelles et fiabiliser le chargement des classes.

Ces chantiers apporteront un gain de clarté immédiat, faciliteront la contribution future et prépareront le terrain pour des tests automatisés plus fins.
