# CLAUDE.md — Instructions permanentes pour le développement du plugin Smart Link Checker

> **Ce fichier est la source de vérité du projet.** Il doit être lu par l'IA au début de CHAQUE conversation.
> Il ne change que lors de décisions architecturales majeures. Pour l'état courant du projet, voir `HANDOFF.md`.

---

## 1. IDENTITÉ DU PROJET

- **Nom** : Smart Link Checker
- **Slug WordPress** : `smart-link-checker` (préfixe `flavor_lc_` pour toutes les fonctions globales, `flc_` pour les shortcodes)
- **Namespace PHP** : `FlavorLinkChecker\`
- **REST API namespace** : `smart-link-checker/v1`
- **Text domain** : `smart-link-checker`
- **Préfixe tables DB** : `{$wpdb->prefix}flc_`
- **Préfixe options WP** : `flc_`
- **Préfixe hooks (actions/filtres)** : `flc/`

## 2. EXIGENCES TECHNIQUES NON-NÉGOCIABLES

| Contrainte               | Valeur                                      |
|---------------------------|---------------------------------------------|
| WordPress minimum         | 6.9+                                        |
| PHP minimum               | 8.2+                                        |
| MySQL/MariaDB             | 5.7+ / 10.4+                                |
| Autoloading               | PSR-4 via Composer                           |
| Build JS                  | `@wordpress/scripts` (wp-scripts)            |
| Interface admin           | React via `@wordpress/element` + `@wordpress/components` |
| State management          | `@wordpress/data` (createReduxStore)         |
| Affichage données         | `@wordpress/dataviews` (bundled, import sans suffixe `/wp`) |
| Background processing     | Action Scheduler 3.x (bundled dans `vendor/woocommerce/action-scheduler/`) |
| Tests PHP                 | PHPUnit 10+ avec `yoast/phpunit-polyfills`   |
| Tests JS                  | Jest (intégré via wp-scripts)                |
| Linting PHP               | PHPCS avec WordPress Coding Standards 3.x    |
| Analyse statique PHP      | PHPStan niveau 6+ avec `szepeviktor/phpstan-wordpress` |
| Linting JS                | ESLint via `@wordpress/eslint-plugin`        |
| Environnement dev         | LocalWP (principal) + `@wordpress/env` (CI)  |

## 3. STRUCTURE DE FICHIERS CANONIQUE

```
smart-link-checker/
├── smart-link-checker.php         # Point d'entrée (headers plugin + bootstrap)
├── uninstall.php                    # Nettoyage complet à la suppression
├── composer.json                    # PSR-4, dépendances dev (WPCS, PHPStan, PHPUnit)
├── package.json                     # @wordpress/scripts, dépendances React
├── phpcs.xml                        # Configuration PHPCS + WPCS
├── phpstan.neon                     # Configuration PHPStan
├── .wp-env.json                     # Environnement Docker pour CI
├── README.md                        # Documentation utilisateur
├── CLAUDE.md                        # CE FICHIER — Instructions IA permanentes
├── HANDOFF.md                       # État courant du projet (mis à jour chaque session)
├── CONVENTIONS.md                   # Conventions de code détaillées
│
├── src/                             # Code PHP — racine PSR-4 → FlavorLinkChecker\
│   ├── Plugin.php                   # Bootstrap : hooks principaux, singleton
│   ├── Activator.php                # register_activation_hook : tables, options, scheduler
│   ├── Deactivator.php              # register_deactivation_hook : arrêt cron/scheduler
│   │
│   ├── Admin/
│   │   ├── AdminPage.php            # add_menu_page + enqueue React conditionnel
│   │   ├── Settings.php             # register_setting + page de réglages
│   │   └── ReviewNotice.php         # Admin notice d'avis WordPress.org
│   │
│   ├── REST/
│   │   ├── LinksController.php      # GET/PUT/DELETE /links, POST /links/bulk
│   │   ├── ScanController.php       # POST /scan/start, GET /scan/status
│   │   └── SettingsController.php   # GET/PUT /settings
│   │
│   ├── Scanner/
│   │   ├── ContentParser.php        # Extraction liens depuis HTML (DOMDocument)
│   │   ├── BlockParser.php          # Extraction liens depuis blocs Gutenberg
│   │   ├── LinkExtractor.php        # Orchestrateur : combine parsers, déduplique
│   │   ├── HttpChecker.php          # Vérification HTTP (HEAD→GET fallback)
│   │   └── LinkClassifier.php       # Catégorisation : rel, affiliate, interne/externe
│   │
│   ├── Queue/
│   │   ├── ScanJob.php              # Job : indexer les liens d'un post
│   │   ├── CheckJob.php             # Job : vérifier le statut HTTP d'un lien
│   │   ├── BatchOrchestrator.php    # Découpe le travail en lots, gère la reprise
│   │   └── SchedulerBootstrap.php   # Enregistrement hooks Action Scheduler
│   │
│   ├── Database/
│   │   ├── Migrator.php             # Création/mise à jour tables via dbDelta()
│   │   ├── LinksRepository.php      # CRUD table flc_links
│   │   ├── InstancesRepository.php  # CRUD table flc_instances
│   │   └── QueryBuilder.php         # Construction requêtes filtrées + pagination curseur
│   │
│   └── Models/
│       ├── Link.php                 # DTO readonly class
│       ├── LinkInstance.php         # DTO readonly class
│       ├── Enums/
│       │   ├── LinkStatus.php       # enum LinkStatus: string (ok, redirect, broken, error, timeout, pending)
│       │   ├── LinkType.php         # enum LinkType: string (internal, external)
│       │   └── RelAttribute.php     # enum RelAttribute: string (nofollow, sponsored, ugc, noopener, noreferrer)
│       └── ScanResult.php           # DTO pour les résultats de scan
│
├── admin/                           # Sources React (pré-build)
│   └── src/
│       ├── index.js                 # Point d'entrée React, render dans #flc-root
│       ├── index.scss               # Styles globaux du plugin
│       ├── App.js                   # Router principal (tabs/sections)
│       ├── store/
│       │   ├── index.js             # createReduxStore('smart-link-checker', {...})
│       │   ├── actions.js           # Actions : fetchLinks, startScan, updateLink, bulkAction
│       │   ├── reducer.js           # État : links, scan, filters, pagination, notices
│       │   ├── selectors.js         # getLinks, getScanStatus, getFilters, isLoading
│       │   └── resolvers.js         # Auto-fetch via REST API au premier useSelect()
│       ├── components/
│       │   ├── Dashboard.js         # Summary cards (total, broken, redirects, affiliate)
│       │   ├── LinkTable.js         # DataViews table avec filtres/tri/pagination
│       │   ├── FilterBar.js         # Filtres rapides par catégorie
│       │   ├── LinkEditModal.js     # Modal : éditer URL, anchor, rel attributes
│       │   ├── ScanPanel.js         # Contrôle du scan + barre de progression
│       │   ├── BulkActions.js       # Actions en masse
│       │   └── SettingsPanel.js     # Réglages du plugin
│       └── utils/
│           ├── api.js               # Wrappers apiFetch typés
│           └── constants.js         # Statuts, catégories, labels i18n
│
├── build/                           # Généré par `npm run build` — IGNORÉ par git
│
├── vendor/
│   └── woocommerce/action-scheduler/ # Action Scheduler bundled via Composer
│
├── languages/
│   └── smart-link-checker.pot      # Template de traduction
│
└── tests/
    ├── php/
    │   ├── bootstrap.php            # Chargement WP test suite
    │   ├── Unit/                    # Tests unitaires (pas de DB)
    │   │   ├── ContentParserTest.php
    │   │   ├── LinkClassifierTest.php
    │   │   └── HttpCheckerTest.php
    │   └── Integration/             # Tests d'intégration (avec DB)
    │       ├── LinksRepositoryTest.php
    │       ├── ScanJobTest.php
    │       └── RestApiTest.php
    └── js/
        ├── store.test.js
        └── components/
            └── LinkTable.test.js
```

> **RÈGLE ABSOLUE** : Ne jamais créer un fichier hors de cette structure sans d'abord le documenter ici ET dans HANDOFF.md.

## 4. SCHÉMA DE BASE DE DONNÉES

### Table `{prefix}flc_links`

Stocke chaque URL **unique** (dédupliquée par `url_hash` SHA-256).

```sql
CREATE TABLE {prefix}flc_links (
    id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    url             TEXT NOT NULL,
    url_hash        CHAR(64) NOT NULL,
    final_url       TEXT DEFAULT NULL,
    http_status     SMALLINT(6) DEFAULT NULL,
    status_category VARCHAR(20) NOT NULL DEFAULT 'pending',
    is_external     TINYINT(1) NOT NULL DEFAULT 0,
    is_affiliate    TINYINT(1) NOT NULL DEFAULT 0,
    affiliate_network VARCHAR(50) DEFAULT NULL,
    response_time   INT(11) DEFAULT NULL,
    redirect_count  TINYINT(4) NOT NULL DEFAULT 0,
    last_checked    DATETIME DEFAULT NULL,
    check_count     INT(11) NOT NULL DEFAULT 0,
    last_error      TEXT DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY url_hash (url_hash),
    KEY idx_status_category (status_category),
    KEY idx_last_checked (last_checked),
    KEY idx_is_external (is_external),
    KEY idx_is_affiliate (is_affiliate)
) {charset_collate};
```

### Table `{prefix}flc_instances`

Stocke chaque **occurrence** d'un lien dans un contenu.

```sql
CREATE TABLE {prefix}flc_instances (
    id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    link_id         BIGINT(20) UNSIGNED NOT NULL,
    post_id         BIGINT(20) UNSIGNED NOT NULL,
    source_type     VARCHAR(30) NOT NULL DEFAULT 'post_content',
    anchor_text     TEXT DEFAULT NULL,
    rel_nofollow    TINYINT(1) NOT NULL DEFAULT 0,
    rel_sponsored   TINYINT(1) NOT NULL DEFAULT 0,
    rel_ugc         TINYINT(1) NOT NULL DEFAULT 0,
    is_dofollow     TINYINT(1) NOT NULL DEFAULT 1,
    link_position   INT(11) DEFAULT NULL,
    block_name      VARCHAR(100) DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY idx_link_id (link_id),
    KEY idx_post_id (post_id),
    KEY idx_source_type (source_type),
    KEY idx_rel_nofollow (rel_nofollow),
    KEY idx_rel_sponsored (rel_sponsored),
    KEY idx_is_dofollow (is_dofollow)
) {charset_collate};
```

### Valeurs de `status_category`

| Valeur     | Signification                             |
|------------|-------------------------------------------|
| `pending`  | Jamais vérifié                            |
| `ok`       | HTTP 200                                  |
| `redirect` | HTTP 301, 302, 303, 307, 308             |
| `broken`   | HTTP 404, 410                             |
| `error`    | HTTP 5xx, erreur réseau, SSL invalide     |
| `timeout`  | Pas de réponse dans le délai imparti      |
| `skipped`  | Exclu par l'utilisateur ou pattern ignoré |

### Valeurs de `source_type`

| Valeur           | Signification                    |
|------------------|----------------------------------|
| `post_content`   | Corps de l'article/page          |
| `post_excerpt`   | Extrait                          |
| `custom_field`   | Champ personnalisé (meta_key)    |
| `block_attribute`| Attribut d'un bloc Gutenberg     |

## 5. ENDPOINTS REST API

Base : `wp-json/smart-link-checker/v1`

### 5.1 Liens

| Méthode | Route               | Description                  | Permission          |
|---------|----------------------|------------------------------|---------------------|
| GET     | `/links`             | Liste paginée + filtres      | `manage_options`    |
| GET     | `/links/{id}`        | Détails d'un lien            | `manage_options`    |
| PUT     | `/links/{id}`        | Modifier URL/rel dans source | `manage_options`    |
| DELETE  | `/links/{id}`        | Supprimer le lien du contenu | `manage_options`    |
| POST    | `/links/bulk`        | Action en masse              | `manage_options`    |
| POST    | `/links/{id}/recheck`| Re-vérifier un lien          | `manage_options`    |

**Paramètres GET /links :**

```
page            int     (défaut: 1)
per_page        int     (défaut: 25, max: 100)
status          string  (ok|redirect|broken|error|timeout|pending)
link_type       string  (internal|external)
is_affiliate    bool
rel             string  (nofollow|sponsored|ugc|dofollow)
search          string  (recherche dans url + anchor_text)
post_id         int     (filtrer par article source)
orderby         string  (url|http_status|last_checked|created_at)
order           string  (asc|desc)
```

**Headers de réponse :** `X-WP-Total`, `X-WP-TotalPages`

### 5.2 Scan

| Méthode | Route           | Description                        | Permission        |
|---------|-----------------|------------------------------------|--------------------|
| POST    | `/scan/start`   | Lancer un scan (full ou delta)     | `manage_options`   |
| GET     | `/scan/status`  | Statut du scan en cours            | `manage_options`   |
| POST    | `/scan/cancel`  | Annuler le scan en cours           | `manage_options`   |

**Réponse GET /scan/status :**

```json
{
  "status": "running|complete|idle|cancelled",
  "total_posts": 1250,
  "scanned_posts": 340,
  "total_links": 5200,
  "checked_links": 1100,
  "broken_count": 23,
  "redirect_count": 89,
  "started_at": "2025-01-15T10:30:00Z",
  "estimated_completion": "2025-01-15T11:45:00Z"
}
```

### 5.3 Réglages

| Méthode | Route         | Description               | Permission        |
|---------|---------------|---------------------------|--------------------|
| GET     | `/settings`   | Récupérer les réglages    | `manage_options`   |
| PUT     | `/settings`   | Modifier les réglages     | `manage_options`   |

## 6. RÈGLES DE SÉCURITÉ STRICTES

Ces règles s'appliquent à CHAQUE ligne de code produite. Toute violation sera traitée comme un bug critique.

### 6.1 Inputs

```php
// TOUJOURS — Sanitize AVANT tout usage
$url    = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
$search = sanitize_text_field( wp_unslash( $_REQUEST['search'] ?? '' ) );
$id     = absint( $_GET['id'] ?? 0 );

// JAMAIS utiliser une superglobale directement
// INTERDIT : $url = $_POST['url'];
```

### 6.2 Outputs

```php
// HTML : esc_html()
echo '<span>' . esc_html( $link->anchor_text ) . '</span>';

// Attributs : esc_attr()
echo '<a href="' . esc_url( $link->url ) . '" title="' . esc_attr( $link->title ) . '">';

// JavaScript inline : wp_json_encode()
wp_add_inline_script( 'flc-admin', 'const flcData = ' . wp_json_encode( $data ) . ';', 'before' );
```

### 6.3 Base de données

```php
// TOUJOURS utiliser $wpdb->prepare() avec des requêtes variables
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$this->table} WHERE status_category = %s AND is_external = %d LIMIT %d",
        $status,
        $is_external,
        $limit
    )
);

// Identifiants dynamiques : utiliser %i (WordPress 6.2+)
$wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table_name, $id );

// JAMAIS concaténer des variables dans une requête SQL
// INTERDIT : "SELECT * FROM $table WHERE id = $id"
```

### 6.4 Nonces et capabilities

```php
// REST API : permission_callback obligatoire
'permission_callback' => function ( WP_REST_Request $request ): bool {
    return current_user_can( 'manage_options' );
}

// Actions admin classiques :
check_admin_referer( 'flc_action_nonce', '_flc_nonce' );
```

### 6.5 Fichiers PHP

```php
// PREMIÈRE LIGNE de chaque fichier PHP (sauf le fichier principal)
defined( 'ABSPATH' ) || exit;
```

### 6.6 Logging

```php
// TOUJOURS conditionner error_log() à WP_DEBUG (exigence WordPress.org)
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    error_log( '[FlavorLinkChecker] ...' );
}
```

### 6.7 Ce qui est INTERDIT

- `eval()`, `extract()`, `compact()` dans un contexte de données utilisateur
- `file_get_contents()` pour les URLs → utiliser `wp_remote_get()`
- `$_SERVER['REQUEST_URI']` sans sanitization
- HEREDOC/NOWDOC (le code sniffer ne peut pas vérifier l'escaping à l'intérieur)
- `serialize()`/`unserialize()` → utiliser `wp_json_encode()`/`json_decode()`
- Création de fichiers dans `wp-content/` sans passer par les API WP

## 7. CONVENTIONS PHP 8.2+

### 7.1 Classes et types

```php
// Readonly classes pour les DTOs
readonly class Link {
    public function __construct(
        public int $id,
        public string $url,
        public LinkStatus $status_category,
        public bool $is_external,
        public bool $is_affiliate,
        public ?int $http_status,
        public ?\DateTimeImmutable $last_checked,
    ) {}
}

// Enums typés pour les constantes
enum LinkStatus: string {
    case Pending  = 'pending';
    case Ok       = 'ok';
    case Redirect = 'redirect';
    case Broken   = 'broken';
    case Error    = 'error';
    case Timeout  = 'timeout';
    case Skipped  = 'skipped';
}

// Type declarations PARTOUT
public function find_by_status( LinkStatus $status, int $limit = 25 ): array {}
```

### 7.2 Patterns obligatoires

- **Return types** sur TOUTES les méthodes (y compris `: void`)
- **Constructor promotion** pour les DTOs et les classes à injection
- **Named arguments** pour les fonctions WordPress avec beaucoup de paramètres
- **Match expressions** au lieu de switch quand applicable
- **Null-safe operator `?->`** au lieu de vérifications null imbriquées
- **First-class callable syntax** `$this->method(...)` pour les hooks

### 7.3 Hooks WordPress

```php
// Enregistrement avec type-safe callable
add_action( 'rest_api_init', $this->register_routes( ... ) );
add_filter( 'cron_schedules', $this->add_custom_schedule( ... ) );

// Préfixe systématique pour les hooks custom
do_action( 'flc/scan/started', $scan_id );
apply_filters( 'flc/scanner/batch_size', 100 );
apply_filters( 'flc/checker/timeout', 15 );
apply_filters( 'flc/classifier/affiliate_patterns', $patterns );
```

## 8. CONVENTIONS REACT / JAVASCRIPT

### 8.1 Imports WordPress

```js
// TOUJOURS importer depuis les packages WordPress (externals)
import { useState, useEffect } from '@wordpress/element';
import { Button, Modal, Notice, Spinner, TextControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { DataViews } from '@wordpress/dataviews'; // Bundled (PAS de /wp suffix, cf. décision #16)
```

### 8.2 Store @wordpress/data

```js
// Le store doit s'appeler exactement 'smart-link-checker'
import { createReduxStore, register } from '@wordpress/data';

const store = createReduxStore( 'smart-link-checker', {
    reducer,
    actions,
    selectors,
    resolvers, // Auto-fetch au premier useSelect()
} );

register( store );
```

### 8.3 Conventions composants

- **Fonctionnels** uniquement (pas de classes React)
- **Un composant par fichier**, nommé comme le fichier
- **Destructuring des props** dans la signature
- Tout texte affiché passe par `__()` ou `sprintf()` pour l'i18n
- Les `key` de liste utilisent des IDs stables, jamais des index de tableau

## 9. ACTION SCHEDULER — RÈGLES D'UTILISATION

```php
// Planifier une action async (exécution dès que possible)
as_enqueue_async_action( 'flc/scan/process_batch', [ $batch_id ], 'smart-link-checker' );

// Planifier une action récurrente
as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'flc/recheck/daily', [], 'smart-link-checker' );

// Vérifier les ressources avant chaque item du batch
private function has_resources(): bool {
    $memory_limit  = wp_convert_hr_to_bytes( WP_MEMORY_LIMIT );
    $memory_usage  = memory_get_usage( true );
    $time_elapsed  = microtime( true ) - $this->start_time;

    return ( $memory_usage < $memory_limit * 0.8 ) && ( $time_elapsed < 25 );
}

// TOUJOURS utiliser le groupe 'smart-link-checker' pour identifier nos actions
// TOUJOURS vérifier if ( ! function_exists( 'as_enqueue_async_action' ) ) avant usage
```

### Taille de batch adaptative

```php
$batch_size = apply_filters( 'flc/scanner/batch_size', $this->calculate_optimal_batch_size() );

private function calculate_optimal_batch_size(): int {
    $memory_available = wp_convert_hr_to_bytes( WP_MEMORY_LIMIT ) - memory_get_usage( true );
    $estimated_per_item = 50 * 1024; // ~50KB par post traité
    return max( 10, min( 200, (int) floor( $memory_available * 0.5 / $estimated_per_item ) ) );
}
```

## 10. PATTERNS D'EXTRACTION DE LIENS

### DOMDocument (parser principal)

```php
$dom = new \DOMDocument();
libxml_use_internal_errors( true );
$dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
libxml_clear_errors();

$links = [];
foreach ( $dom->getElementsByTagName( 'a' ) as $node ) {
    $href = $node->getAttribute( 'href' );
    if ( empty( $href ) || str_starts_with( $href, '#' ) || str_starts_with( $href, 'mailto:' ) || str_starts_with( $href, 'tel:' ) ) {
        continue;
    }
    $links[] = [
        'url'         => $href,
        'anchor_text' => $node->textContent,
        'rel'         => $node->getAttribute( 'rel' ),
        'target'      => $node->getAttribute( 'target' ),
    ];
}
```

### Gutenberg blocks (parser complémentaire)

```php
$blocks = parse_blocks( $post->post_content );
$this->extract_from_blocks( $blocks );

private function extract_from_blocks( array $blocks ): void {
    foreach ( $blocks as $block ) {
        // Liens dans le HTML du bloc
        if ( ! empty( $block['innerHTML'] ) ) {
            $this->parse_html( $block['innerHTML'] );
        }
        // Liens dans les attributs du bloc (ex: core/button → attrs.url)
        if ( ! empty( $block['attrs']['url'] ) ) {
            $this->add_link( $block['attrs']['url'], $block['blockName'] );
        }
        // Blocs imbriqués (récursif)
        if ( ! empty( $block['innerBlocks'] ) ) {
            $this->extract_from_blocks( $block['innerBlocks'] );
        }
    }
}
```

## 11. DÉTECTION D'AFFILIATION — PATTERNS

```php
private const AFFILIATE_DOMAINS = [
    'amazon'       => [ 'amzn.to', 'amazon.com', 'amazon.fr', 'amazon.co.uk', 'amazon.de', 'amazon.ca' ],
    'shareasale'   => [ 'shareasale.com' ],
    'cj'           => [ 'anrdoezrs.net', 'dpbolvw.net', 'jdoqocy.com', 'tkqlhce.com', 'kqzyfj.com' ],
    'impact'       => [ 'sjv.io', '7eer.net', 'evyy.net', 'pxf.io' ],
    'rakuten'      => [ 'click.linksynergy.com' ],
    'clickbank'    => [ 'hop.clickbank.net' ],
    'partnerstack' => [ 'partnerstack.com' ],
    'awin'         => [ 'awin1.com' ],
];

private const AFFILIATE_PATH_PATTERNS = [
    '/go/', '/refer/', '/recommends/', '/out/', '/aff/',
    '/partner/', '/affiliate/', '/ref/', '/click/',
];

private const AFFILIATE_PARAMS = [ 'tag', 'ref', 'aff_id', 'affiliate_id', 'partner' ];
```

## 12. PERFORMANCE — CONTRAINTES HÉBERGEMENT MUTUALISÉ

| Paramètre              | Minimum attendu | Notre limite safe   |
|-------------------------|-----------------|----------------------|
| memory_limit            | 128 MB          | Utiliser max 80%     |
| max_execution_time      | 30s             | Checkpoint à 25s     |
| max_input_vars          | 1000            | Batch < 500 items    |
| post_max_size           | 8 MB            | Limiter payloads     |
| MySQL max_allowed_packet| 1 MB            | Bulk INSERT < 500 KB |

### Optimisations obligatoires en batch

```php
// Suspendre le cache objet pendant les opérations en masse
wp_suspend_cache_addition( true );

// Différer le comptage des termes
wp_defer_term_counting( true );

// Désactiver autocommit pour les bulk inserts
$wpdb->query( 'SET autocommit = 0' );
// ... bulk inserts ...
$wpdb->query( 'COMMIT' );
$wpdb->query( 'SET autocommit = 1' );
```

## 13. RÈGLES DE TRAVAIL POUR L'IA

### 13.1 Début de session

1. Lire `CLAUDE.md` (ce fichier) intégralement
2. Lire `HANDOFF.md` pour connaître l'état actuel
3. Ne JAMAIS contredire ces deux fichiers sans accord explicite de l'utilisateur
4. Si une ambiguïté existe entre les deux fichiers, `CLAUDE.md` prévaut

### 13.2 Pendant le développement

- **Un fichier à la fois** : terminer complètement un fichier avant d'en commencer un autre
- **Tester au fur et à mesure** : chaque classe PHP doit être vérifiable indépendamment
- **Commenter les décisions** : documenter les choix techniques dans le code avec `// NOTE:` ou `// DECISION:`
- **Respecter la structure** : ne pas créer de fichiers dans des emplacements non prévus
- **Ne pas deviner** : si une information manque (ex: nom d'une table, préfixe), demander plutôt qu'inventer

### 13.3 Fin de session

1. Résumer ce qui a été accompli
2. Lister ce qui reste à faire
3. Proposer la mise à jour du `HANDOFF.md` avec les changements
4. Signaler tout écart avec le plan initial

### 13.4 Ce que l'IA ne doit JAMAIS faire

- Modifier la structure de la base de données sans discussion préalable
- Changer les noms de namespace, préfixe, ou conventions
- Introduire une dépendance externe non listée dans ce document
- Écrire du code sans sanitization/escaping
- Utiliser `wp_die()` dans un contexte REST API (retourner `WP_Error` à la place)
- Écrire des endpoints REST sans `permission_callback`
- Utiliser `localStorage`/`sessionStorage` dans le code React admin (pas nécessaire ici, mais à rappeler)

---

> **Dernière mise à jour de ce fichier** : 2026-03-11 (Session 22 — Audit v1.0)
> **Version** : 1.0.0
