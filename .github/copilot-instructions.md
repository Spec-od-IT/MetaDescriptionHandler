# GitHub Copilot Instructions for Meta Description Handler

## Project Overview

This is a WordPress plugin called **Meta Description Handler** that manages meta titles and meta descriptions for all pages, posts, custom post types, taxonomies, and archives.

## Tech Stack

- **Language:** PHP 7.2+
- **Platform:** WordPress 5.0+
- **Frontend:** Vanilla JavaScript, jQuery (WordPress bundled)
- **Styling:** CSS (no preprocessors)
- **Build:** No build process needed - plain PHP plugin

## Project Structure

```
meta-description-handler/
├── meta-description-handler.php    # Main plugin file (entry point)
├── uninstall.php                   # Cleanup on plugin deletion
├── includes/
│   ├── class-mdh-admin.php         # Admin settings pages & UI
│   ├── class-mdh-post-meta.php     # Post/page meta boxes
│   ├── class-mdh-taxonomy-meta.php # Taxonomy term meta fields
│   ├── class-mdh-frontend.php      # Frontend meta tag output
│   ├── class-mdh-helpers.php       # Utility/helper functions
│   └── class-mdh-bulk-editor.php   # Bulk meta editor for all content
├── assets/
│   ├── css/admin.css               # Admin panel styles
│   └── js/admin.js                 # Admin panel JavaScript
└── languages/
    ├── meta-description-handler.pot     # Translation template
    └── meta-description-handler-pl_PL.php # Polish translations
```

## Coding Standards

### PHP Standards
- Follow [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use tabs for indentation (not spaces)
- Use snake_case for function and variable names
- Use PascalCase for class names with `MDH_` prefix
- Always use strict comparison (`===`, `!==`)
- Escape all output using `esc_html()`, `esc_attr()`, `esc_url()`, etc.
- Sanitize all input using `sanitize_text_field()`, `sanitize_textarea_field()`, etc.
- Use nonces for form security
- Check user capabilities before performing actions

### JavaScript Standards
- Follow [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
- Use jQuery with `(function($) { ... })(jQuery);` wrapper
- Use camelCase for variables and functions
- Namespace global objects under `MDHAdmin`

### CSS Standards
- Follow [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)
- Use `.mdh-` prefix for all class names
- Use tabs for indentation
- One selector per line for multiple selectors

## Key Conventions

### Text Domain
Always use `'meta-description-handler'` as the text domain for translations:
```php
__('Text to translate', 'meta-description-handler')
_e('Text to echo', 'meta-description-handler')
```

### Meta Keys
All post/term meta keys are prefixed with `_mdh_`:
- `_mdh_meta_title`
- `_mdh_meta_description`
- `_mdh_robots_noindex`
- `_mdh_robots_nofollow`

### Options
Plugin settings are stored in a single option: `mdh_settings`

### Hooks & Filters
Custom hooks use `mdh_` prefix:
- `mdh_meta_description` - Filter meta description output
- `mdh_document_title` - Filter document title output
- `mdh_og_image` - Filter Open Graph image

### Class Pattern
All classes use singleton pattern:
```php
class MDH_Example {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize hooks
    }
}
```

## Common Tasks

### Adding a new setting
1. Add default value in `meta-description-handler.php` → `activate()` method
2. Add sanitization in `class-mdh-admin.php` → `sanitize_settings()` method
3. Add UI field in appropriate `render_*_page()` method
4. Use via `MDH_Helpers::get_setting('setting_key')`

### Adding a new meta field for posts
1. Add field in `class-mdh-post-meta.php` → `render_meta_box()` method
2. Save field in `save_meta_box()` method
3. Output on frontend in `class-mdh-frontend.php`

### Adding a new meta field for taxonomies
1. Add field in `class-mdh-taxonomy-meta.php` → `add_term_fields()` and `edit_term_fields()`
2. Save in `save_term_meta()` method
3. Output on frontend in `class-mdh-frontend.php`

## Security Checklist

When writing code, always ensure:
- [ ] Nonce verification for form submissions
- [ ] Capability checks (`current_user_can()`)
- [ ] Data sanitization on input
- [ ] Data escaping on output
- [ ] Prepared statements for custom SQL queries

## Testing

### Manual Testing Checklist
- Test on single posts, pages, and custom post types
- Test on category, tag, and custom taxonomy archives
- Test on date archives (year, month, day)
- Test on author archives
- Test on search results page
- Test on 404 page
- Test with pagination
- Verify meta tags in page source
- Verify Open Graph tags with Facebook debugger
- Test with SEO plugins disabled to avoid conflicts

## Dependencies

- WordPress 5.0+
- PHP 7.2+
- jQuery (bundled with WordPress)

No external dependencies or npm packages required.

## Build & Release

The plugin is released via GitHub Actions:
- Push a tag (e.g., `v1.0.0`) to trigger a release
- The workflow creates a ZIP file excluding dev files
- ZIP is attached to GitHub Release

## Contact

- **Author:** Spec od IT
- **Email:** biuro@specodit.pl
- **Website:** https://specodit.pl
