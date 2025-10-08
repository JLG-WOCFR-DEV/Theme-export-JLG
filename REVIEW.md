# Code Review Notes

## Blocking Issues

- Aucun à date (nov. 2024). La signature factice de `wp_check_invalid_utf8()` dans la suite PHPUnit accepte désormais le paramètre `$strip`, ce qui aligne le comportement sur WordPress.【F:tests/test-export-sanitization.php†L1-L18】

## Observations récentes

- `TEJLG_Export::persist_export_archive()` renvoie toujours un tableau vide en cas d’échec de copie ou de création de dossier. Ajouter un log (`error_log` ou action dédiée) faciliterait le diagnostic lors des exports automatisés.【F:theme-export-jlg/includes/class-tejlg-export.php†L1973-L2053】
- Le module de notifications (`TEJLG_Export_Notifications`) expose déjà un hook `tejlg_export_notifications_mail`. Documenter des exemples (Slack, webhook) aiderait les intégrateurs à l’adopter rapidement.【F:theme-export-jlg/includes/class-tejlg-export-notifications.php†L7-L205】

## Recommandations

- Ajouter une action `do_action('tejlg_export_persist_archive_failed', $job, $destination)` ou un appel `error_log()` dans `persist_export_archive()` pour remonter les erreurs de copie.
- Compléter la documentation développeur avec un exemple de personnalisation du hook `tejlg_export_notifications_mail`.
