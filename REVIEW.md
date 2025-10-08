# Code Review Notes

## Blocking Issues

- Aucun à date (nov. 2024). La signature factice de `wp_check_invalid_utf8()` dans la suite PHPUnit accepte désormais le paramètre `$strip`, ce qui aligne le comportement sur WordPress.【F:tests/test-export-sanitization.php†L1-L18】

## Observations récentes

- *(Résolu — voir mises à jour)* `TEJLG_Export::persist_export_archive()` renvoyait un tableau vide en cas d’échec de copie ou de création de dossier sans log dédié.【F:theme-export-jlg/includes/class-tejlg-export.php†L1973-L2053】
- *(Résolu — voir mises à jour)* Le module de notifications (`TEJLG_Export_Notifications`) exposait un hook `tejlg_export_notifications_mail` sans exemples pratiques.【F:theme-export-jlg/includes/class-tejlg-export-notifications.php†L7-L205】

## Mises à jour (déc. 2024)

- ✅ `persist_export_archive()` déclenche désormais l’action `tejlg_export_persist_archive_failed` et consigne le motif de l’échec avant de retourner un résultat vide, avec un filtre pour neutraliser le log si besoin.【F:theme-export-jlg/includes/class-tejlg-export.php†L1996-L2060】
- ✅ La documentation développeur inclut des recettes prêtes à l’emploi pour personnaliser `tejlg_export_notifications_mail` et réagir aux échecs de persistance.【F:docs/notifications-guide.md†L1-L122】

## Recommandations

- Ajouter une action `do_action('tejlg_export_persist_archive_failed', $job, $destination)` ou un appel `error_log()` dans `persist_export_archive()` pour remonter les erreurs de copie.
- Compléter la documentation développeur avec un exemple de personnalisation du hook `tejlg_export_notifications_mail`.
