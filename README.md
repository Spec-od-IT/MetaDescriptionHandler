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