# Code Review Notes

## Blocking Issues

- **tests/test-export-sanitization.php**: La fonction factice `wp_check_invalid_utf8()` n'accepte qu'un seul argument, alors que l'implémentation WordPress reçoit un second argument optionnel (`$strip`). L'appel `wp_check_invalid_utf8($pattern, true)` dans `TEJLG_Exclusion_Patterns_Sanitizer::sanitize_list()` va provoquer une erreur fatale pendant les tests. Il faut ajouter le paramètre optionnel pour aligner la signature sur celle de WordPress.

## Recommandations

- Ajuster la signature de l'ersatz `wp_check_invalid_utf8()` pour éviter l'erreur fatale (par exemple `function wp_check_invalid_utf8($string, $strip = false)`).
