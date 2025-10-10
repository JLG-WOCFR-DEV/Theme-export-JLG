# Pistes d'amélioration professionnelles

Ces propositions complètent les chantiers déjà menés sur l'interface d'export. Elles visent à rapprocher l'expérience des attentes observées dans les suites SaaS dédiées à la sauvegarde et au déploiement telles que **ManageWP**, **BlogVault** ou **WP Migrate**.

## Panorama comparatif

| Axes clés | Theme Export - JLG aujourd'hui | Suites professionnelles | Opportunités immédiates |
| --- | --- | --- | --- |
| **Gestion multi-sites** | Paramétrage site par site, historique localisé. | Supervision centralisée avec groupes de sites et rôles partagés. | Export/Import de profils et synchronisation via WP-CLI pour répliquer les réglages sur plusieurs environnements. |
| **Planification & rétention** | File d'attente WordPress, purge locale, notifications e-mail basiques. | Sauvegardes incrémentales, rétention longue durée, stockage externe (S3, SFTP, Google Drive). | Connecteurs distants et seuils configurables pour purger ou déporter les archives volumineuses. |
| **Observabilité** | Journal consultable dans l'onglet Export, téléchargement manuel. | Dashboards consolidés, rapports programmés, alertes multi-canaux. | Journal JSON/CSV téléchargeable, envoi automatisé et webhooks temps réel. |
| **Assistance & UX** | Interface alignée sur l'admin WP, parcours manuel. | Wizards guidés, infobulles contextuelles, suggestions automatisées. | Assistant multi-étapes et bibliothèque d'exclusions prédéfinies. |
| **Robustesse** | Vérifications de prérequis PHP, reprise manuelle des erreurs. | Reprise automatique, segmentation incrémentale, validations en amont. | Détection proactive des erreurs fréquentes et relance automatique avant expiration nonce. |
| **Accessibilité** | Dropzones accessibles, contrastes compatibles admin WP. | Modes haute lisibilité, raccourcis dédiés, résumé vocal. | Mode contraste renforcé, raccourcis clavier globaux, résumé vocal déclenchable. |

## Feuille de route suggérée

### A. Victoires rapides (1 à 2 sprints)

- **Webhooks et intégrations** : offrir un connecteur sortant (Slack, Teams, Discord) pour pousser les alertes critiques en temps réel sans dépendre de l'e-mail.
- ✅ **Flux d'audit exportable** : permettre le téléchargement d'un journal JSON/CSV contenant toutes les métadonnées du job (durée, taille, origine, utilisateur) sur une période donnée, directement depuis l'onglet Historique.
- **Bibliothèque de motifs** : lister les exclusions les plus courantes (node_modules, cache, assets volumineux) et permettre l'ajout en un clic.

### B. Améliorations intermédiaires (3 à 5 sprints)

- **Assistant guidé** : proposer un walkthrough facultatif à la première utilisation et un stepper multi-écrans pour les exports/imports, avec résumé final exportable (PDF/CSV).
- **Rôles affinés** : introduire une capacité « Gestion des exports » afin de déléguer la supervision sans donner l'accès total à l'admin WordPress.
- **Snapshots différés** : sauvegarder les paramètres de planification dans un « brouillon » pour validation par un collègue avant activation.
- **Quotas dynamiques** : calculer la taille cumulée des archives conservées et avertir quand un seuil proche des limites d'hébergement est atteint.

### C. Chantiers structurants (6 sprints et +)

- **Stockage externalisé** : permettre le dépôt automatique des archives vers S3/SFTP/Spaces et la restauration directe depuis ces sources.
- **Tableau de bord analytique** : ajouter un micro-graphique « temps moyen de génération » et « poids moyen » avec bornes configurables, complété par des rapports planifiés.
- **Journal des annotations** : autoriser des commentaires sur un export (raison de la relance, anomalies détectées) visibles par l'équipe support.
- **Auto-reprise & reprise incrémentale** : relancer un export interrompu à cause d'une expiration nonce ou d'une coupure réseau et reprendre à la dernière étape validée.

## Accessibilité et conformité

- **Thème à contraste renforcé certifié** : proposer un mode alternatif aligné sur RGAA/WCAG 2.2 avec indicateurs d'état textuels partout où une couleur est utilisée.
- **Navigation au clavier enrichie** : ajouter des raccourcis (par exemple `g` puis `h` pour aller à l'historique) et afficher une modale d'aide (`?`).
- **Compatibilité lecteur d'écran** : fournir un résumé synthétique du job (statut + prochain pas recommandé) via un bouton « Résumé vocal » qui déclenche un `aria-live` dédié.

Ces axes peuvent être planifiés progressivement : commencer par l'observabilité (faible dette technique), poursuivre avec la gouvernance et la planification (nécessitent des capacités côté serveur), et terminer par les fonctionnalités collaboratives et de stockage avancé.
