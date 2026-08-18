# Meta Description Handler

A comprehensive WordPress plugin for managing meta titles and meta descriptions for all pages, posts, custom post types, taxonomies, and archives.

## Features

### 🎯 Complete Meta Management
- **Posts & Pages**: Add custom meta titles and descriptions to every post and page
- **Custom Post Types**: Full support for all registered custom post types
- **Taxonomies**: Manage meta data for categories, tags, and custom taxonomies
- **Archives**: Configure meta for date archives, author archives, and post type archives
- **Special Pages**: Set meta for search results and 404 pages

### 🔧 Admin Features
- **Tabbed Interface**: Clean, organized settings pages with tabs
- **Live Preview**: Real-time Google SERP preview while editing
- **Character Counter**: Visual feedback for optimal title (30-60 chars) and description (70-160 chars) length
- **Bulk Overview**: See meta status in post/taxonomy list columns
- **Template System**: Use placeholders like `%post_title%`, `%site_title%`, `%separator%`

### 🌐 SEO Features
- **Meta Description**: Automatic output in HTML head
- **Document Title**: Filters WordPress document title
- **Robots Meta**: Control noindex/nofollow per post or term
- **Open Graph Tags**: Automatic OG and Twitter Card tags
- **Pagination Support**: Page numbers in archive titles

### 🔌 Headless / API
- **WPGraphQL**: `mdhSeo` field on every enabled post type and taxonomy
- **REST API**: `mdh_seo` field on every enabled post type
- **Context-free resolver**: `MDH_Resolver` resolves titles and descriptions for any post or
  term outside the loop — the same code path the front end uses, so the API can never drift
  from what `wp_head` outputs
- **Yoast SEO import**: one-shot migration via WP-CLI, with a dry-run mode

## Installation

1. Upload the `meta-description-handler` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Meta Handler** in the admin menu to configure settings

## Usage

### General Settings

Navigate to **Meta Handler → General Settings** to:
- Set the title separator (default: `|`)
- Enable/disable meta fields for specific post types
- Enable/disable meta fields for specific taxonomies
- Configure homepage meta title and description

### Post/Page Meta

When editing a post or page, you'll find a new meta box called "Meta Title & Description" with:
- Meta Title input with character counter
- Meta Description textarea with character counter
- Live Google SERP preview
- Robots settings (noindex, nofollow)

### Post Type Settings

Navigate to **Meta Handler → Post Types** to configure:
- Default title format for each post type
- Default description template
- Archive page meta (for post types with archives)

### Taxonomy Settings

Navigate to **Meta Handler → Taxonomies** to configure:
- Default title format for each taxonomy
- Default description template

### Archive Settings

Navigate to **Meta Handler → Archives** to configure:
- Date archives (yearly, monthly, daily)
- Author archives

### Special Pages

Navigate to **Meta Handler → Special Pages** to configure:
- Search results page title format
- 404 error page title and description

## Template Placeholders

Use these placeholders in title formats:

| Placeholder | Description |
|------------|-------------|
| `%post_title%` | Current post/page title |
| `%site_title%` | Website name |
| `%site_description%` | Website tagline |
| `%separator%` | Title separator (configurable) |
| `%archive_title%` | Archive title (date, post type name) |
| `%term_title%` | Taxonomy term name |
| `%term_description%` | Taxonomy term description |
| `%search_query%` | Search query string |
| `%author_name%` | Author display name |
| `%current_year%` | Current year |
| `%page_number%` | Pagination page number |

## Hooks & Filters

### Filters

```php
// Modify meta description output
add_filter('mdh_meta_description', function($description) {
    return $description;
});

// Modify document title output
add_filter('mdh_document_title', function($title) {
    return $title;
});

// Modify Open Graph image
add_filter('mdh_og_image', function($image) {
    return $image;
});
```

## Headless Usage

The plugin normally writes meta tags through `wp_head`. A decoupled front end (Astro, Next.js,
Nuxt…) never renders `wp_head`, so the same values are also exposed through WPGraphQL and REST.

### WPGraphQL

Fields appear on every post type and taxonomy that is (a) enabled in **Meta Handler → General
Settings** and (b) exposed to GraphQL (`show_in_graphql`).

```graphql
{
  posts(first: 100) {
    nodes {
      slug
      mdhSeo {
        title
        description
        noindex
        nofollow
        ogImage
      }
    }
  }
}
```

### REST API

```
GET /wp-json/wp/v2/posts?_fields=slug,mdh_seo
```

### PHP

```php
MDH_Resolver::post_title( $post_id );
MDH_Resolver::post_description( $post_id );
MDH_Resolver::post_payload( $post_id );   // title, description, noindex, nofollow, ogImage
MDH_Resolver::term_title( $term_id, $taxonomy );
MDH_Resolver::term_description( $term_id, $taxonomy );
```

## Migrating from Yoast SEO

Yoast stores its own replacement variables (`%%sep%%`, `%%sitename%%`) inside the saved text,
so values cannot simply be copied — they are translated into MDH placeholders during import.
Variables with no MDH equivalent are removed from the text and reported, because leaving them
in would print them literally in the title.

Always start with a dry run:

```bash
wp mdh import-yoast --dry-run --verbose
```

Then run it for real:

```bash
wp mdh import-yoast
```

| Flag | Meaning |
| --- | --- |
| `--dry-run` | Report only, write nothing |
| `--overwrite` | Overwrite MDH fields that already have a value (off by default) |
| `--post-types=<list>` | Comma-separated post types (default: types enabled in settings) |
| `--skip-terms` | Skip taxonomy meta |
| `--verbose` | Print every changed item |

What is migrated:

| Yoast | MDH |
| --- | --- |
| `_yoast_wpseo_title` | `_mdh_meta_title` |
| `_yoast_wpseo_metadesc` | `_mdh_meta_description` |
| `_yoast_wpseo_opengraph-image` | `_mdh_og_image` |
| `_yoast_wpseo_meta-robots-noindex` = `1` | `_mdh_robots_noindex` |
| `_yoast_wpseo_meta-robots-nofollow` = `1` | `_mdh_robots_nofollow` |
| `wpseo_taxonomy_meta` (`wpseo_title`, `wpseo_desc`, `wpseo_noindex`) | term meta |

Yoast is never modified — its data stays in place, so deactivating MDH rolls everything back.

### Verifying the migration

Dump the resolved values and compare them against a crawl of the live site before switching
Yoast off:

```bash
wp mdh list --format=json > after-migration.json
```

## File Structure

```
meta-description-handler/
├── meta-description-handler.php    # Main plugin file
├── uninstall.php                   # Cleanup on uninstall
├── README.md                       # Documentation
├── includes/
│   ├── class-mdh-admin.php         # Admin settings pages
│   ├── class-mdh-post-meta.php     # Post/page meta boxes
│   ├── class-mdh-taxonomy-meta.php # Taxonomy meta fields
│   ├── class-mdh-frontend.php      # Frontend output
│   ├── class-mdh-resolver.php      # Context-free title/description resolution
│   ├── class-mdh-headless.php      # WPGraphQL + REST exposure
│   ├── class-mdh-import.php        # Yoast SEO migration
│   ├── class-mdh-cli.php           # WP-CLI commands
│   └── class-mdh-helpers.php       # Helper functions
├── assets/
│   ├── css/
│   │   └── admin.css               # Admin styles
│   └── js/
│       └── admin.js                # Admin JavaScript
└── languages/
    └── meta-description-handler.pot # Translation template
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher

## Changelog

### 1.1.0
- WPGraphQL `mdhSeo` field on enabled post types and taxonomies
- REST `mdh_seo` field on enabled post types
- New `MDH_Resolver` — resolves meta outside the loop; `MDH_Frontend` now uses it, so the
  frontend and the API can no longer disagree
- Yoast SEO importer with variable translation and dry-run (`wp mdh import-yoast`)
- `wp mdh list` for before/after comparison of resolved meta

### 1.0.0
- Initial release
- Full support for posts, pages, and custom post types
- Taxonomy meta support
- Archive settings
- SERP preview
- Character counters
- Open Graph tags
- Twitter Card tags

## License

GPL v2 or later

## Support

For support, please create an issue in the GitHub repository.