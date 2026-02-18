# APP Procurement Manager (PHP + jQuery + Tailwind + SQLite)

## Requirements
- PHP 8.2+
- Composer

## Setup
1. Install dependencies:
   ```bash
   composer install
   ```
2. Start server (pick one):
   ```bash
   php -S localhost:8000 -t src
   ```
   or run:
   ```bat
   run-server.bat
   ```
3. Open:
   - `http://localhost:8000`

## Current Features
- CRUD for APP data columns:
  - Column 1: Project Title
  - Column 2: End User
  - Column 3: General Description (rich editor with Bold/Italic/Underline)
  - Column 4: Mode of Procurement
  - Column 5: Covered by EPA
  - Column 10: Estimated Budget
- Modal-based create/edit form
- General Description table preview:
  - Preserves line breaks and formatting
  - Collapses long text with `See more..`
- Custom delete confirmation modal (no native browser confirm)
- Signatories settings modal:
  - Prepared by + Designation
  - Submitted by + Designation
  - Date
  - Saved in SQLite and used by print output
- Row action buttons:
  - `APP` (opens print page)
  - `Edit`
  - `Delete`

## Export / Print
- Main UI export path is now `APP` button (new page print flow), not the old export button.
- Print page:
  - `src/print_v2.php`
  - Legal size (8.5 x 13), landscape
  - APP-style layout with headers, sections, totals, and signatories
  - Uses optional top banner image from:
    - `src/assets/print-header.png`
- Legacy XLSX-to-PDF export endpoint still exists:
  - `src/export.php`

## Database
- SQLite file:
  - `src/database/app.sqlite`
- Tables:
  - `procurement_projects`
  - `app_settings` (signatory values)

## Main Files
- `src/index.php` main UI and modals
- `src/assets/app.js` frontend behavior (CRUD, modals, APP print action)
- `src/api.php` JSON API (CRUD + signatory settings actions)
- `src/print_v2.php` APP print layout page
- `src/export.php` legacy template-based PDF export
- `src/config.php` SQLite connection and schema bootstrap
- `run-server.bat` local PHP server launcher
