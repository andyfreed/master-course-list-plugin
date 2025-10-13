# Master Course List Plugin

This WordPress plugin adds an admin interface for managing course metadata that originates from the Beacon Hill master course spreadsheet. It relies on Fragments LMS for course storage.

## Features

- Read-only Course List table that surfaces course numbers, credit totals, metadata, and WooCommerce product links.
- CSV importer with dry-run support to preview how spreadsheet columns map into the LMS before applying changes.
- Sync layer that updates the latest course version with credits, course numbers, notes, word counts, and prices while refreshing FLMS search metadata.
- Automatic registration of spreadsheet columns as course metadata so new headings appear in WordPress without code changes.

## Requirements

- WordPress (tested with 6.x)
- Fragments LMS (FLMS) plugin active
- WooCommerce active for product integration (optional but recommended)

## Installation

1. Upload the `master-course-list-plugin` directory to your `wp-content/plugins` folder (or install via Composer if desired).
2. Activate “Master Course List” through the WordPress Plugins screen.
3. Ensure Fragments LMS is active and course data is present.

## Usage

### Course Overview

- Navigate to **Master Course List → Course List** in wp-admin.
- Filter/search to audit course numbers, credit totals, and metadata per course.

### Import Preview / Apply

- Navigate to **Master Course List → Import**.
- Upload a CSV export from the master spreadsheet.
- Keep “Dry run” checked to preview mappings without changing data.
- Review the summary, warnings, and preview rows.
- Uncheck “Dry run” and re-upload to apply changes to matching courses.

## Development

- The plugin is structured similarly to the FLMS codebase. Core logic lives under `includes/`:
  - `class-master-course-list-data.php`: field discovery and course fetch helpers.
  - `class-master-course-list-table.php`: admin list table implementation.
  - `class-master-course-list-importer.php`: CSV parsing, mapping, summaries, and sync triggers.
  - `class-master-course-list-sync.php`: updates FLMS course versions and auxiliary meta.

## Contributing

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/your-feature`).
3. Commit your changes.
4. Submit a pull request.

## License

Distributed under the GPL-2.0 license.
