# Panneau d’actions rapides

Le panneau d’actions rapides ajoute un menu radial fixe dans l’interface d’administration de Theme Export – JLG. Il regroupe les raccourcis essentiels (« Exporter maintenant », « Dernière archive », « Rapport de débogage », etc.) et reste disponible sur les onglets Export, Import et Débogage.

## Accessibilité et interactions

* Le bouton flottant respecte `aria-expanded` et se ferme automatiquement avec la touche `Escape` ou lors d’un clic à l’extérieur.
* Le focus est piégé dans le menu ouvert : `Tab`/`Shift+Tab` circulent sur les actions puis reviennent au déclencheur.
* Un bouton « Masquer ce menu » enregistre la préférence utilisateur dans `localStorage`. Le bouton « Afficher les actions rapides » permet de réactiver le panneau à tout moment.
* Les animations sont neutralisées lorsque `prefers-reduced-motion: reduce` est actif.

## Filtre `tejlg_quick_actions`

Les actions proposées peuvent être enrichies via le filtre suivant :

```php
add_filter( 'tejlg_quick_actions', function( array $actions, array $context ) {
    $actions[] = [
        'id'          => 'support-docs',
        'label'       => __( 'Documentation support', 'theme-export-jlg' ),
        'url'         => 'https://example.com/docs',
        'target'      => '_blank',
        'rel'         => 'noopener',
        'description' => __( 'Ouvre la base de connaissances.', 'theme-export-jlg' ),
        'aria_label'  => __( 'Consulter la documentation de support', 'theme-export-jlg' ),
    ];

    return $actions;
}, 10, 2 );
```

Chaque action accepte les clés suivantes :

| Clé            | Type                | Description |
|----------------|--------------------|-------------|
| `id`           | `string`            | Identifiant unique (utilisé pour le `data-quick-actions-item-id`). |
| `label`        | `string`            | Libellé affiché. |
| `url`          | `string`            | Lien de destination (requis pour `type = link`). |
| `type`         | `link`\|`button`   | Définit si l’action est rendue sous forme de lien ou de bouton. |
| `target`       | `string` (optionnel) | Attribut `target` du lien. |
| `rel`          | `string` (optionnel) | Attribut `rel` du lien. |
| `description`  | `string` (optionnel) | Texte supplémentaire affiché sous le label. |
| `aria_label`   | `string` (optionnel) | Remplace le texte du label pour les technologies d’assistance. |
| `attributes`   | `array`             | Tableau d’attributs HTML additionnels appliqués à l’élément `<a>`/`<button>`. |

Le tableau `$context` fournit notamment :

* `current_tab` : onglet actif (`export`, `import`, `debug`, …)
* `page_slug` : slug de la page d’administration (`theme-export-jlg`).
* `latest_export` : entrée la plus récente de l’historique d’exports (ou `null`).
* `latest_context` : métadonnées dérivées (timestamp, taille, URL de téléchargement).

Les actions invalides (sans label ou sans URL pour un lien) sont automatiquement filtrées avant rendu.
