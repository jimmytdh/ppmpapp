# APP Procurement Manager (PHP + jQuery + Tailwind + SQLite)

## Requirements
- PHP 8.2+
- Composer

## Setup
1. Install dependencies:
   ```bash
   composer install
   ```
2. Run the app:
   ```bash
   php -S 127.0.0.1:8000 -t src
   ```
3. Open:
   - `http://127.0.0.1:8000`

## Features
- CRUD for APP fields:
  - Column 1: Project Title
  - Column 2: End User
  - Column 3: General Description
  - Column 4: Mode of Procurement
  - Column 5: Covered by EPA
  - Column 10: Estimated Budget
- Modal-based form UI
- SQLite-backed persistence (`src/database/app.sqlite`)
- Multi-select export to PDF
- PDF format:
  - Legal size (8.5 x 13)
  - Landscape
  - Generated from `src/APP.xlsx` template formatting and styles

## Main Files
- `src/index.php` UI
- `src/assets/app.js` jQuery behaviors
- `src/api.php` JSON CRUD endpoints
- `src/export.php` selected-row PDF export
  - loads `src/APP.xlsx`, fills corresponding APP columns, then exports to PDF
- `src/config.php` SQLite connection and schema init
