# Modèle de rapport hebdomadaire basé sur `generate_report()`

Ce guide fournit un exemple prêt à l'emploi pour exploiter le rapport agrégé produit par `TEJLG_Export_History::generate_report()` et le diffuser chaque semaine (e-mail, Slack, dépôt de fichier). Le format présenté s'appuie exclusivement sur les métadonnées déjà stockées dans le journal d'export et ne nécessite pas de personnalisation du plugin.

## 1. Générer le JSON hebdomadaire via WP-CLI

Utilisez la commande WP-CLI intégrée afin de récupérer un snapshot sur sept jours :

```bash
wp theme-export-jlg history report \
  --window=7 \
  --result=success \
  --format=json \
  --limit=25 \
  --path=/var/www/html
```

- `--window` définit la fenêtre d'analyse (en jours). 7 couvre une semaine glissante.【F:theme-export-jlg/includes/class-tejlg-export-history.php†L704-L786】
- `--result` ou `--origin` filtrent le rapport si nécessaire (par exemple pour suivre uniquement les exports automatisés).【F:theme-export-jlg/includes/class-tejlg-cli.php†L214-L282】
- `--limit` borne le nombre d'entrées détaillées renvoyées dans la clé `entries` du rapport.
- Sans `--format=json`, le rendu est textuel dans le terminal. Conservez l'option JSON pour automatiser l'envoi ou la conversion.

### Exemple de charge utile JSON

```json
{
  "generated_at": 1733090400,
  "filters": {
    "window_days": 7,
    "result": "success",
    "origin": "",
    "limit": 25
  },
  "totals": {
    "entries": 12,
    "duration_seconds": 1812,
    "archive_size_bytes": 94371840
  },
  "averages": {
    "duration_seconds": 151,
    "archive_size_bytes": 7864320
  },
  "counts": {
    "results": {
      "success": 11,
      "warning": 1,
      "error": 0,
      "info": 0
    },
    "origins": {
      "cli": 9,
      "schedule": 3
    }
  },
  "uptime_rate": 91.7,
  "period_start": 1732572005,
  "period_end": 1733090312,
  "latest_entry": {
    "job_id": "export_673c5f48d4976",
    "timestamp": 1733090312,
    "status": "completed",
    "result": "success",
    "origin": "cli",
    "user_id": 1,
    "user_name": "admin",
    "duration": 143,
    "zip_file_name": "theme-export-2024-12-02-07-18.zip",
    "zip_file_size": 8388608,
    "persistent_url": "https://example.com/wp-content/uploads/exports/theme-export-2024-12-02-07-18.zip",
    "status_message": "Export terminé sans exclusion.",
    "exclusions": []
  },
  "entries": [
    {
      "job_id": "export_673c5f48d4976",
      "timestamp": 1733090312,
      "status": "completed",
      "result": "success",
      "origin": "cli",
      "user_name": "admin",
      "duration": 143,
      "zip_file_size": 8388608,
      "persistent_url": "https://example.com/wp-content/uploads/exports/theme-export-2024-12-02-07-18.zip"
    },
    {
      "job_id": "export_673b26a1115ab",
      "timestamp": 1733003885,
      "status": "completed",
      "result": "warning",
      "origin": "schedule",
      "user_name": "cron",
      "duration": 198,
      "zip_file_size": 7340032,
      "status_message": "Exclusion automatique des logs debug.log"
    }
  ]
}
```

Les clés `totals`, `averages`, `counts` et `uptime_rate` peuvent être directement injectées dans un message Slack ou dans l'entête d'un e-mail de rapport. Les entrées listées (limitée à `limit`) permettent d'ajouter un tableau détaillé ou une section « incidents ».

## 2. Générer un CSV synthétique des entrées

Pour produire un fichier CSV compatible tableur à partir de la charge utile précédente, transformez la section `entries` avec `jq` :

```bash
wp theme-export-jlg history report --window=7 --format=json --limit=50 \
  | jq -r '
    ("job_id;timestamp;result;origin;user_name;duration_seconds;zip_file_size_bytes;persistent_url"),
    (.entries[] | [
      .job_id,
      (.timestamp | strftime("%Y-%m-%d %H:%M:%S")),
      .result,
      .origin,
      (.user_name // ""),
      (.duration // 0),
      (.zip_file_size // 0),
      (.persistent_url // "")
    ] | @csv)
  ' | sed 's/,/;/g' > rapport-hebdomadaire.csv
```

- `strftime` convertit les timestamps UNIX selon le fuseau de l'environnement WP-CLI.
- Le remplacement des virgules par des points-virgules (`sed`) garantit une ouverture directe dans Excel en français.
- Les champs absents sont remplacés par des chaînes vides ou des zéros pour conserver des types cohérents.

### Extrait de CSV obtenu

```csv
job_id;timestamp;result;origin;user_name;duration_seconds;zip_file_size_bytes;persistent_url
export_673c5f48d4976;2024-12-02 07:18:32;success;cli;admin;143;8388608;https://example.com/wp-content/uploads/exports/theme-export-2024-12-02-07-18.zip
export_673b26a1115ab;2024-12-01 06:18:05;warning;schedule;cron;198;7340032;
export_673a11ce02cbd;2024-11-30 02:11:21;success;cli;admin;156;8912896;https://example.com/wp-content/uploads/exports/theme-export-2024-11-30-02-11.zip
```

## 3. Automatiser l'envoi hebdomadaire

Deux approches complémentaires sont disponibles :

1. **Cron système** : planifiez la commande WP-CLI suivie de votre script d'envoi (mailx, `curl` vers Slack, dépôt S3).
2. **Hook natif** : branchez-vous sur `tejlg_export_history_report_ready` pour déclencher un traitement dès qu'un export se termine. Exemple minimal pour poster le résumé agrégé dans Slack :

```php
add_action('tejlg_export_history_report_ready', function ($report) {
    $payload = [
        'text' => sprintf(
            "Exports sur %d j – %d succès / %d avertissements / %d erreurs (uptime %s%%)",
            (int) $report['filters']['window_days'],
            (int) ($report['counts']['results']['success'] ?? 0),
            (int) ($report['counts']['results']['warning'] ?? 0),
            (int) ($report['counts']['results']['error'] ?? 0),
            $report['uptime_rate'] ?? 'N/A'
        ),
    ];

    wp_remote_post('https://hooks.slack.com/services/XXX/YYY/ZZZ', [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode($payload),
        'timeout' => 10,
    ]);
});
```

Cette approche s'appuie sur le même rapport agrégé que la commande WP-CLI et garantit une cohérence entre les canaux de diffusion.【F:theme-export-jlg/includes/class-tejlg-export-history.php†L734-L827】 Ajustez la logique d'envoi (fréquence, formatage) selon vos besoins.
