# Revue de code du plugin Theme Export JLG

## Points forts
- La classe `TEJLG_Export` applique systématiquement des normalisations et contrôles d'erreurs lors de la constitution des archives, ce qui limite les risques de parcours de répertoires et d'injections de chemins. Les fonctions `normalize_path()` et `should_exclude_file()` contribuent à sécuriser le processus d'exclusion des fichiers.
- L'historique des exports (`TEJLG_Export_History`) conserve des entrées normalisées et expose des filtres (`tejlg_export_history_entry`, `tejlg_export_history_max_entries`) facilitant l'extension par des thèmes ou extensions tierces.

## Améliorations intégrées
- Le CLI dispose désormais de filtres `--result` et `--origin` pour cibler rapidement un sous-ensemble d'exports, ce qui facilite l'exploitation des environnements de production et le diagnostic d'incidents.

## Opportunités supplémentaires
- `TEJLG_Export::persist_export_archive()` retourne silencieusement un tableau vide en cas d'échec de création de dossier ou de copie. Journaliser ces erreurs (via `error_log()` ou une action dédiée) simplifierait le diagnostic en production.
- `TEJLG_Export_Process::task()` ferme l'archive ZIP avant tout retour mais ne journalise pas la raison exacte d'un échec d'ajout dans l'archive. Un appel à `error_log()` ou à un hook pourrait aider à identifier les fichiers problématiques.
- Les commandes CLI pourraient proposer un format de sortie alternatif (JSON, CSV) pour faciliter l'automatisation ou l'intégration avec d'autres outils.
