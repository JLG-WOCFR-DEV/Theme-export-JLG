# Recettes pour `tejlg_export_history_report_ready`

Ce document rassemble des exemples directement exploitables pour transformer le rapport agrégé déclenché par `tejlg_export_history_report_ready` en alertes multi-canaux. Chaque extrait peut être placé dans un plugin dédié ou dans le fichier `functions.php` d’un thème enfant.

La fonction `TEJLG_Export_History::generate_report()` est appelée automatiquement par le plugin lorsque l’historique d’un export est enregistré. Le hook `tejlg_export_history_report_ready` reçoit alors :

1. `$report` — tableau associatif prêt à l’emploi contenant les agrégats (`counts`, `totals`, `averages`, `uptime_rate`, etc.).
2. `$entry` — la ligne brute ajoutée à l’historique.
3. `$job` — les métadonnées de la tâche d’export.
4. `$context` — informations additionnelles, notamment l’utilisateur et l’origine.
5. `$args` — paramètres passés à `generate_report()` (fenêtre, filtres).

## 1. Publier un message Slack enrichi

```php
add_action(
    'tejlg_export_history_report_ready',
    static function (array $report, array $entry) {
        $webhook = getenv('TEJLG_SLACK_WEBHOOK');

        if (!$webhook) {
            return;
        }

        $counts   = $report['counts']['results'] ?? [];
        $success  = (int) ($counts['success'] ?? 0);
        $warning  = (int) ($counts['warning'] ?? 0);
        $error    = (int) ($counts['error'] ?? 0);
        $uptime   = $report['uptime_rate'] ?? null;
        $duration = $report['averages']['duration_seconds'] ?? null;

        $fields = [
            ['title' => __('Succès', 'theme-export-jlg'), 'value' => (string) $success, 'short' => true],
            ['title' => __('Avertissements', 'theme-export-jlg'), 'value' => (string) $warning, 'short' => true],
            ['title' => __('Erreurs', 'theme-export-jlg'), 'value' => (string) $error, 'short' => true],
        ];

        if (null !== $uptime) {
            $fields[] = [
                'title' => __('Uptime exports', 'theme-export-jlg'),
                'value' => sprintf('%.1f%%', $uptime),
                'short' => true,
            ];
        }

        if (null !== $duration) {
            $fields[] = [
                'title' => __('Durée moyenne', 'theme-export-jlg'),
                'value' => sprintf('%ss', (int) $duration),
                'short' => true,
            ];
        }

        wp_remote_post(
            $webhook,
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode([
                    'text'        => sprintf(
                        /* translators: %s: site name */
                        __('Exports terminés sur %s', 'theme-export-jlg'),
                        get_bloginfo('name')
                    ),
                    'attachments' => [
                        [
                            'color'  => $error > 0 ? 'danger' : ($warning > 0 ? 'warning' : 'good'),
                            'fields' => $fields,
                            'footer' => home_url(),
                            'ts'     => time(),
                        ],
                    ],
                ]),
                'timeout' => 5,
            ]
        );
    },
    10,
    2
);
```

## 2. Envoyer un e-mail HTML récapitulatif

```php
add_action(
    'tejlg_export_history_report_ready',
    static function (array $report, array $entry) {
        $to = ['ops@example.com'];

        $subject = sprintf(
            /* translators: 1: site name, 2: result label */
            __('[%1$s] Synthèse export : %2$s', 'theme-export-jlg'),
            get_bloginfo('name'),
            ucfirst($entry['result'] ?? 'inconnu')
        );

        $body_rows = [
            sprintf('<li><strong>%s</strong> : %d</li>', __('Exports analysés', 'theme-export-jlg'), (int) ($report['totals']['entries'] ?? 0)),
            sprintf('<li><strong>%s</strong> : %.1f%%</li>', __('Uptime', 'theme-export-jlg'), (float) ($report['uptime_rate'] ?? 0)),
            sprintf('<li><strong>%s</strong> : %s</li>', __('Dernier statut', 'theme-export-jlg'), esc_html($entry['result_label'] ?? $entry['result'] ?? '')),
        ];

        if (!empty($entry['persistent_url'])) {
            $body_rows[] = sprintf(
                '<li><a href="%1$s">%2$s</a></li>',
                esc_url($entry['persistent_url']),
                esc_html__('Télécharger la dernière archive', 'theme-export-jlg')
            );
        }

        $body = sprintf(
            '<p>%s</p><ul>%s</ul>',
            esc_html__('Résumé hebdomadaire des exports', 'theme-export-jlg'),
            implode('', $body_rows)
        );

        wp_mail(
            $to,
            $subject,
            $body,
            ['Content-Type: text/html; charset=UTF-8']
        );
    },
    10,
    2
);
```

## 3. Déposer le rapport JSON sur un espace S3

```php
use Aws\S3\S3Client;

add_action(
    'tejlg_export_history_report_ready',
    static function (array $report) {
        if (!class_exists(S3Client::class)) {
            return;
        }

        $client = new S3Client([
            'version'     => 'latest',
            'region'      => 'eu-west-3',
            'credentials' => [
                'key'    => getenv('AWS_ACCESS_KEY_ID'),
                'secret' => getenv('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);

        $key = sprintf(
            'reports/theme-export/%s.json',
            gmdate('Y/m/d/His')
        );

        $client->putObject([
            'Bucket'      => getenv('TEJLG_S3_BUCKET'),
            'Key'         => $key,
            'Body'        => wp_json_encode($report, JSON_PRETTY_PRINT),
            'ContentType' => 'application/json',
        ]);
    },
    10,
    1
);
```

Ces extraits peuvent être combinés ou adaptés pour alimenter un outil de supervision existant. Ils capitalisent sur les métadonnées déjà stockées (durée, taille, statut, origine) sans requête supplémentaire dans la base. Pour des besoins avancés, pensez à filtrer l’argument `$args` afin de modifier la fenêtre d’analyse (par défaut sept jours).

