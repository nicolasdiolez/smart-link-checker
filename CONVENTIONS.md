# CONVENTIONS.md — Conventions de code et patterns du projet Smart Link Checker

> Ce fichier complète `CLAUDE.md` avec des exemples détaillés et des patterns de référence.
> Il sert de guide rapide quand l'IA code un fichier spécifique.

---

## 1. STRUCTURE D'UN FICHIER PHP

Chaque fichier PHP suit exactement ce squelette :

```php
<?php
/**
 * Nom descriptif de la classe.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\SousNamespace;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Models\Link;
use FlavorLinkChecker\Models\Enums\LinkStatus;

/**
 * Description de la classe.
 *
 * @since 1.0.0
 */
class NomDeLaClasse {

    /**
     * Description de la propriété.
     *
     * @since 1.0.0
     * @var string
     */
    private string $table_name;

    /**
     * Constructeur.
     *
     * @since 1.0.0
     *
     * @param \wpdb $wpdb Instance WordPress database.
     */
    public function __construct(
        private readonly \wpdb $wpdb,
    ) {
        $this->table_name = $this->wpdb->prefix . 'flc_links';
    }

    /**
     * Enregistre les hooks WordPress.
     *
     * @since 1.0.0
     */
    public function register(): void {
        add_action( 'rest_api_init', $this->register_routes( ... ) );
    }

    /**
     * Description de la méthode.
     *
     * @since 1.0.0
     *
     * @param LinkStatus $status  Le statut à filtrer.
     * @param int        $limit   Nombre de résultats max.
     * @return Link[]             Tableau de liens.
     */
    public function find_by_status( LinkStatus $status, int $limit = 25 ): array {
        // ...
    }
}
```

### Règles strictes

- `declare( strict_types=1 );` en PREMIÈRE déclaration (après l'ouverture PHP et le docblock)
- `defined( 'ABSPATH' ) || exit;` AVANT tout code
- Un seul `namespace` par fichier
- Imports `use` groupés : d'abord les classes du projet, puis les classes externes
- Pas de `?>` à la fin du fichier
- Docblocks PHPDoc sur TOUTES les classes, méthodes, et propriétés
- `@since 1.0.0` sur tout (requis pour WordPress.org)

## 2. STRUCTURE D'UN CONTROLLER REST API

```php
<?php
declare( strict_types=1 );

namespace FlavorLinkChecker\REST;

defined( 'ABSPATH' ) || exit;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class LinksController extends WP_REST_Controller {

    protected $namespace = 'smart-link-checker/v1';
    protected $rest_base = 'links';

    public function __construct(
        private readonly \FlavorLinkChecker\Database\LinksRepository $repository,
        private readonly \FlavorLinkChecker\Database\QueryBuilder $query_builder,
    ) {}

    /**
     * Enregistre les routes REST.
     */
    public function register_routes(): void {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => $this->get_items( ... ),
                    'permission_callback' => $this->check_permissions( ... ),
                    'args'                => $this->get_collection_params(),
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => $this->get_item( ... ),
                    'permission_callback' => $this->check_permissions( ... ),
                    'args'                => [
                        'id' => [
                            'validate_callback' => static fn( $param ): bool => is_numeric( $param ) && (int) $param > 0,
                            'sanitize_callback' => 'absint',
                        ],
                    ],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => $this->update_item( ... ),
                    'permission_callback' => $this->check_permissions( ... ),
                    'args'                => $this->get_update_params(),
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => $this->delete_item( ... ),
                    'permission_callback' => $this->check_permissions( ... ),
                ],
            ]
        );
    }

    /**
     * Vérifie les permissions.
     */
    public function check_permissions( WP_REST_Request $request ): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Récupère la liste paginée des liens.
     */
    public function get_items( $request ): WP_REST_Response|WP_Error {
        $params = [
            'page'         => $request->get_param( 'page' ) ?? 1,
            'per_page'     => $request->get_param( 'per_page' ) ?? 25,
            'status'       => $request->get_param( 'status' ),
            'link_type'    => $request->get_param( 'link_type' ),
            'is_affiliate' => $request->get_param( 'is_affiliate' ),
            'search'       => $request->get_param( 'search' ),
            'orderby'      => $request->get_param( 'orderby' ) ?? 'created_at',
            'order'        => $request->get_param( 'order' ) ?? 'desc',
        ];

        $result = $this->query_builder->get_links( $params );

        $response = new WP_REST_Response( $result['items'] );
        $response->header( 'X-WP-Total', (string) $result['total'] );
        $response->header( 'X-WP-TotalPages', (string) $result['total_pages'] );

        return $response;
    }

    /**
     * Paramètres de la collection.
     */
    public function get_collection_params(): array {
        return [
            'page'         => [
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $v ): bool => (int) $v > 0,
            ],
            'per_page'     => [
                'default'           => 25,
                'sanitize_callback' => 'absint',
                'validate_callback' => static fn( $v ): bool => (int) $v >= 1 && (int) $v <= 100,
            ],
            'status'       => [
                'default'           => null,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => static fn( $v ): bool => in_array( $v, [ 'ok', 'redirect', 'broken', 'error', 'timeout', 'pending', null ], true ),
            ],
            'search'       => [
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'orderby'      => [
                'default'           => 'created_at',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => static fn( $v ): bool => in_array( $v, [ 'url', 'http_status', 'last_checked', 'created_at' ], true ),
            ],
            'order'        => [
                'default'           => 'desc',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => static fn( $v ): bool => in_array( strtolower( $v ), [ 'asc', 'desc' ], true ),
            ],
        ];
    }
}
```

## 3. STRUCTURE D'UN COMPOSANT REACT

```jsx
/**
 * LinkTable component — Affiche la table DataViews des liens.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { DataViews } from '@wordpress/dataviews/wp';
import { STORE_NAME } from '../store';
import { STATUS_LABELS, REL_LABELS } from '../utils/constants';

const LinkTable = () => {
    const { links, totalItems, totalPages, isLoading } = useSelect( ( select ) => {
        const store = select( STORE_NAME );
        return {
            links: store.getLinks(),
            totalItems: store.getTotalItems(),
            totalPages: store.getTotalPages(),
            isLoading: store.isLoading(),
        };
    }, [] );

    const { fetchLinks, updateLink, deleteLink } = useDispatch( STORE_NAME );

    const fields = [
        {
            id: 'url',
            label: __( 'URL', 'smart-link-checker' ),
            enableSorting: true,
            enableGlobalSearch: true,
            render: ( { item } ) => (
                <a href={ item.url } target="_blank" rel="noopener noreferrer">
                    { item.url }
                </a>
            ),
        },
        {
            id: 'http_status',
            label: __( 'Statut HTTP', 'smart-link-checker' ),
            enableSorting: true,
            render: ( { item } ) => (
                <StatusBadge status={ item.status_category } code={ item.http_status } />
            ),
        },
        {
            id: 'status_category',
            label: __( 'Catégorie', 'smart-link-checker' ),
            elements: Object.entries( STATUS_LABELS ).map( ( [ value, label ] ) => ( { value, label } ) ),
            filterBy: { operators: [ 'is', 'isNot' ] },
        },
        {
            id: 'link_type',
            label: __( 'Type', 'smart-link-checker' ),
            render: ( { item } ) => item.is_external
                ? __( 'Externe', 'smart-link-checker' )
                : __( 'Interne', 'smart-link-checker' ),
            elements: [
                { value: 'external', label: __( 'Externe', 'smart-link-checker' ) },
                { value: 'internal', label: __( 'Interne', 'smart-link-checker' ) },
            ],
            filterBy: { operators: [ 'is' ] },
        },
        {
            id: 'last_checked',
            label: __( 'Dernière vérification', 'smart-link-checker' ),
            enableSorting: true,
            render: ( { item } ) => item.last_checked
                ? new Date( item.last_checked ).toLocaleString()
                : __( 'Jamais', 'smart-link-checker' ),
        },
    ];

    const actions = [
        {
            id: 'recheck',
            label: __( 'Re-vérifier', 'smart-link-checker' ),
            icon: 'update',
            supportsBulk: true,
            callback: async ( items ) => {
                // ... appel REST API
            },
        },
        {
            id: 'edit',
            label: __( 'Modifier', 'smart-link-checker' ),
            callback: ( [ item ] ) => {
                // ... ouvrir modal d'édition
            },
        },
        {
            id: 'delete',
            label: __( 'Supprimer du contenu', 'smart-link-checker' ),
            supportsBulk: true,
            isDestructive: true,
            callback: async ( items ) => {
                // ... suppression avec confirmation
            },
        },
    ];

    return (
        <DataViews
            data={ links }
            fields={ fields }
            actions={ actions }
            paginationInfo={ { totalItems, totalPages } }
            getItemId={ ( item ) => item.id }
            isLoading={ isLoading }
            defaultLayouts={ { table: {} } }
        />
    );
};

export default LinkTable;
```

## 4. STRUCTURE DU STORE @wordpress/data

```js
// admin/src/store/index.js
import { createReduxStore, register } from '@wordpress/data';
import reducer from './reducer';
import * as actions from './actions';
import * as selectors from './selectors';
import * as resolvers from './resolvers';

export const STORE_NAME = 'smart-link-checker';

const store = createReduxStore( STORE_NAME, {
    reducer,
    actions,
    selectors,
    resolvers,
} );

register( store );
export default store;
```

### Pattern action + thunk

```js
// admin/src/store/actions.js
import apiFetch from '@wordpress/api-fetch';

export const setLinks = ( links ) => ( { type: 'SET_LINKS', links } );
export const setLoading = ( isLoading ) => ( { type: 'SET_LOADING', isLoading } );
export const setTotalItems = ( total ) => ( { type: 'SET_TOTAL_ITEMS', total } );
export const setTotalPages = ( total ) => ( { type: 'SET_TOTAL_PAGES', total } );

export const fetchLinks = ( params ) => async ( { dispatch } ) => {
    dispatch( setLoading( true ) );
    try {
        const response = await apiFetch( {
            path: '/smart-link-checker/v1/links',
            method: 'GET',
            data: params,
            parse: false, // Important : pour accéder aux headers
        } );

        const total = parseInt( response.headers.get( 'X-WP-Total' ), 10 );
        const totalPages = parseInt( response.headers.get( 'X-WP-TotalPages' ), 10 );
        const links = await response.json();

        dispatch( setLinks( links ) );
        dispatch( setTotalItems( total ) );
        dispatch( setTotalPages( totalPages ) );
    } catch ( error ) {
        // Dispatch erreur via core/notices
        const { createErrorNotice } = dispatch( 'core/notices' );
        createErrorNotice( error.message );
    } finally {
        dispatch( setLoading( false ) );
    }
};
```

### Pattern resolver (auto-fetch)

```js
// admin/src/store/resolvers.js
import { fetchLinks } from './actions';

export const getLinks = () => async ( { dispatch, select } ) => {
    const filters = select.getFilters();
    await dispatch( fetchLinks( filters ) );
};
```

## 5. ENQUEUE CONDITIONNEL

```php
// src/Admin/AdminPage.php

public function enqueue_assets( string $hook_suffix ): void {
    // Ne charger QUE sur notre page
    if ( 'toplevel_page_smart-link-checker' !== $hook_suffix ) {
        return;
    }

    $asset_file = FLC_PLUGIN_DIR . 'build/index.asset.php';
    if ( ! file_exists( $asset_file ) ) {
        return;
    }

    $asset = require $asset_file;

    wp_enqueue_script(
        'flc-admin',
        FLC_PLUGIN_URL . 'build/index.js',
        $asset['dependencies'],
        $asset['version'],
        true
    );

    wp_enqueue_style(
        'flc-admin',
        FLC_PLUGIN_URL . 'build/index.css',
        [ 'wp-components' ],
        $asset['version']
    );

    // Données PHP → React
    wp_add_inline_script(
        'flc-admin',
        'window.flcData = ' . wp_json_encode( [
            'restUrl'  => rest_url( 'smart-link-checker/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'adminUrl' => admin_url(),
            'version'  => FLC_VERSION,
        ] ) . ';',
        'before'
    );
}
```

## 6. PATTERN dbDelta()

```php
// src/Database/Migrator.php

public function create_tables(): void {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // RÈGLES dbDelta strictes :
    // - Deux espaces avant PRIMARY KEY
    // - KEY au lieu de INDEX
    // - Chaque instruction CREATE TABLE séparée
    // - Pas de IF NOT EXISTS
    // - varchar(191) max pour les colonnes indexées (utf8mb4 + InnoDB)

    $sql_links = "CREATE TABLE {$wpdb->prefix}flc_links (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        url text NOT NULL,
        url_hash char(64) NOT NULL,
        ...
        PRIMARY KEY  (id),
        UNIQUE KEY url_hash (url_hash),
        KEY idx_status_category (status_category)
    ) {$charset_collate};";

    $sql_instances = "CREATE TABLE {$wpdb->prefix}flc_instances (
        ...
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_links );
    dbDelta( $sql_instances );

    // Stocker la version du schéma
    update_option( 'flc_db_version', FLC_VERSION );
}
```

## 7. NOMMAGE

| Élément                  | Convention                          | Exemple                               |
|--------------------------|-------------------------------------|---------------------------------------|
| Classes PHP              | PascalCase                          | `LinksController`, `BatchOrchestrator`|
| Méthodes PHP             | snake_case                          | `find_by_status()`, `get_items()`     |
| Variables PHP            | snake_case                          | `$link_status`, `$batch_size`         |
| Constantes PHP           | UPPER_SNAKE_CASE                    | `FLC_VERSION`, `FLC_PLUGIN_DIR`       |
| Enums PHP                | PascalCase (enum + cases)           | `LinkStatus::Broken`                  |
| Fichiers PHP             | PascalCase (PSR-4)                  | `LinksRepository.php`                 |
| Composants React         | PascalCase                          | `LinkTable`, `ScanPanel`              |
| Fichiers React           | PascalCase.js                       | `LinkTable.js`, `Dashboard.js`        |
| Store actions            | camelCase                           | `fetchLinks`, `setLoading`            |
| Store selectors          | camelCase (prefix get/is/has)       | `getLinks`, `isLoading`, `hasError`   |
| Hooks WordPress          | snake_case avec préfixe `flc/`      | `flc/scan/started`                    |
| Options WP               | snake_case avec préfixe `flc_`      | `flc_db_version`, `flc_settings`      |
| Tables DB                | snake_case avec préfixe `flc_`      | `flc_links`, `flc_instances`          |
| Transients               | snake_case avec préfixe `flc_`      | `flc_scan_status`, `flc_rate_limit_*` |
| REST routes              | kebab-case                          | `/links`, `/scan/start`               |

## 8. GESTION D'ERREURS

### PHP

```php
// Erreurs dans les contrôleurs REST : retourner WP_Error
return new WP_Error(
    'flc_link_not_found',
    __( 'Link not found.', 'smart-link-checker' ),
    [ 'status' => 404 ]
);

// JAMAIS wp_die() dans un contexte REST
// JAMAIS throw une exception non-catchée dans un hook WordPress
// Les jobs Action Scheduler : catch TOUTES les exceptions
try {
    $this->process_batch( $batch_id );
} catch ( \Throwable $e ) {
    error_log( sprintf( '[FlavorLinkChecker] Batch %d failed: %s', $batch_id, $e->getMessage() ) );
    // Re-planifier le batch ou le marquer comme échoué
}
```

### JavaScript

```js
// Les appels API wrappent toujours le try/catch
try {
    const result = await apiFetch( { path, method } );
    dispatch( setLinks( result ) );
} catch ( error ) {
    dispatch( 'core/notices' ).createErrorNotice(
        error.message || __( 'Une erreur est survenue.', 'smart-link-checker' )
    );
}
```

## 9. PATTERN DE MODIFICATION D'UN LIEN DANS LE CONTENU SOURCE

Quand l'utilisateur modifie ou supprime un lien via l'interface, le plugin doit modifier le `post_content` du post source. C'est l'opération la plus délicate du plugin.

```php
/**
 * Remplace un lien dans le contenu d'un post.
 *
 * @param int    $post_id  ID du post.
 * @param string $old_url  URL actuelle.
 * @param string $new_url  Nouvelle URL (vide pour supprimer le lien).
 * @param array  $new_attrs Attributs à modifier (rel, target, etc.).
 */
public function replace_link_in_post( int $post_id, string $old_url, string $new_url, array $new_attrs = [] ): bool {
    $post = get_post( $post_id );
    if ( ! $post ) {
        return false;
    }

    $content = $post->post_content;

    // Utiliser DOMDocument pour modifier le lien de manière sûre
    $dom = new \DOMDocument();
    libxml_use_internal_errors( true );
    $dom->loadHTML(
        '<?xml encoding="utf-8"><div>' . $content . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $modified = false;

    foreach ( $dom->getElementsByTagName( 'a' ) as $node ) {
        if ( $node->getAttribute( 'href' ) !== $old_url ) {
            continue;
        }

        if ( empty( $new_url ) ) {
            // Supprimer le lien mais garder le texte
            $text_node = $dom->createTextNode( $node->textContent );
            $node->parentNode->replaceChild( $text_node, $node );
        } else {
            $node->setAttribute( 'href', esc_url( $new_url ) );
            foreach ( $new_attrs as $attr => $value ) {
                if ( empty( $value ) ) {
                    $node->removeAttribute( $attr );
                } else {
                    $node->setAttribute( $attr, $value );
                }
            }
        }

        $modified = true;
    }

    if ( ! $modified ) {
        return false;
    }

    // Extraire le contenu modifié (sans le wrapper <div>)
    $wrapper = $dom->getElementsByTagName( 'div' )->item( 0 );
    $new_content = '';
    foreach ( $wrapper->childNodes as $child ) {
        $new_content .= $dom->saveHTML( $child );
    }

    // Sauvegarder via wp_update_post
    $result = wp_update_post(
        [
            'ID'           => $post_id,
            'post_content' => $new_content, // wp_update_post gère la sanitization
        ],
        true
    );

    return ! is_wp_error( $result );
}
```

## 10. CHECKLIST AVANT COMMIT

Avant de considérer un fichier comme terminé :

- [ ] `defined( 'ABSPATH' ) || exit;` présent
- [ ] `declare( strict_types=1 );` présent
- [ ] Namespace correct selon l'emplacement dans `src/`
- [ ] Docblocks PHPDoc sur classe, méthodes, propriétés
- [ ] `@since 1.0.0` sur tout
- [ ] Types de retour sur toutes les méthodes
- [ ] Inputs sanitizés (`sanitize_text_field`, `absint`, `esc_url_raw`)
- [ ] Outputs échappés (`esc_html`, `esc_attr`, `esc_url`)
- [ ] Requêtes DB avec `$wpdb->prepare()`
- [ ] Endpoints REST avec `permission_callback`
- [ ] Pas de superglobales directes
- [ ] Pas de `eval()`, `extract()`, `serialize()`
- [ ] Textes utilisateur via `__()` ou `_e()`
- [ ] Aucun `// TODO` ou code commenté laissé sans documentation
