# Go Deliver – Copilot Agent Instructions

## Version Bumping (REQUIRED on every commit)

**Always increment the patch version** in `go-deliver.php` on every commit, in both places:

1. The `Version:` header comment (e.g. `Version: 1.2.9`)
2. The `GD_VERSION` constant definition (e.g. `define( 'GD_VERSION', '1.2.9' );`)

Bump the patch segment (third number). Never skip this step.

## Coding Conventions

- All PHP follows WordPress coding standards (nonces, `sanitize_*`, `esc_*`, capabilities).
- Use `current_time('timestamp')` (not `time()`) when comparing against WP-stored datetimes.
- Plugin files live at the repo root: `go-deliver.php`, `admin/`, `includes/`, `public/`, `templates/`.
