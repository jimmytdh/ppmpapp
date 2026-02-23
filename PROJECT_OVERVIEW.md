# Project Overview

## Purpose
APP/PPMP Procurement Manager is a lightweight PHP + SQLite web app for managing procurement project entries and producing APP/PPMP formatted print outputs.

The app supports:
- CRUD for procurement records
- Signatory settings management
- APP print view generation
- PPMP print view generation
- Legacy template-based PDF export from `APP.xlsx`

## Tech Stack
- Backend: PHP 8.2+, PDO (SQLite)
- Frontend: server-rendered HTML, jQuery, Tailwind via CDN
- Database: SQLite (`src/database/app.sqlite`)
- Document export: `phpoffice/phpspreadsheet`, `mpdf/mpdf`

## Repository Structure
- `src/index.php`: Main UI page (table + modals)
- `src/assets/app.js`: Client-side CRUD/modals/formatting logic
- `src/api.php`: JSON API for records and signatories
- `src/config.php`: DB connection + schema bootstrap + default settings seed
- `src/print_v2.php`: Browser print page (legal landscape APP layout)
- `src/ppmp_v2.php`: Browser print page (legal landscape PPMP layout)
- `src/export.php`: Legacy XLSX template -> PDF export flow
- `src/APP.xlsx`: Export template used by `src/export.php`
- `run-server.bat`: Local PHP server launcher

## Runtime Flow
1. User opens `/` (`src/index.php`).
2. `src/assets/app.js` calls `api.php?action=list` to load rows.
3. Create/Edit/Delete actions call:
- `action=create`
- `action=update`
- `action=delete`
4. Signatories modal uses:
- `action=get_signatories`
- `action=save_signatories`
5. APP button opens `print_v2.php?ids[]=<id>` in a new tab for print/PDF via browser.
6. PPMP button opens `ppmp_v2.php?ids[]=<id>` in a new tab for print/PDF via browser.

## Data Model
Database is initialized automatically in `src/config.php`.

### `procurement_projects`
- `id` (PK, autoincrement)
- `project_title` (TEXT, required)
- `end_user` (TEXT, required)
- `type_of_project` (TEXT, enum: `Goods|Infrastructure|Service`)
- `general_description` (TEXT, required; stored as sanitized rich HTML)
- `mode_of_procurement` (TEXT, enum: `Public Bidding|Small Value Procurement`)
- `covered_by_epa` (TEXT, enum: `Yes|No`)
- `estimated_budget` (REAL, `>= 0`)
- `created_at`, `updated_at` (timestamps)

Notes:
- Existing SQLite files are auto-migrated by `src/config.php` to add `type_of_project` with default `Goods` if missing.

### `app_settings`
- Single-row settings table (`id = 1`)
- `prepared_by_name`, `prepared_by_designation`
- `submitted_by_name`, `submitted_by_designation`
- `sign_date` (`YYYY-MM-DD` or empty)

## Validation and Sanitization
- API-level validation in `validatePayload()` enforces required fields, enum values, and numeric budget.
- `type_of_project` is validated against `Goods`, `Infrastructure`, `Service`.
- Rich text description is sanitized client-side (`sanitizeRichHtml`) and re-validated server-side for non-empty plain text.
- `print_v2.php` sanitizes description HTML again before rendering print output.

## Print and Export
### Current primary flow
- `print_v2.php` renders an APP-style HTML document for legal landscape printing.
- Pulls selected rows by `ids[]` and uses signatories from `app_settings`.
- Includes optional header image from `src/assets/print-header.png`.
- `ppmp_v2.php` renders a PPMP-style HTML document for legal landscape printing.
- PPMP output includes the 12-column PPMP header structure, `TOTAL BUDGET`, and signatories.
- PPMP Column 2 uses `type_of_project`.

### Legacy flow
- `export.php` loads `APP.xlsx`, maps template columns by label, injects row data, and writes PDF using mPDF.
- Keeps rich text formatting in descriptions through PhpSpreadsheet `RichText`.

## Setup and Run
1. Install dependencies:
```bash
composer install
```
2. Start server:
```bash
php -S localhost:8000 -t src
```
or run `run-server.bat`.

3. Open:
`http://localhost:8000`

## Current Constraints / Notes
- No authentication/authorization layer.
- No CSRF protection on write endpoints.
- API returns raw exception messages on 500 errors.
- No automated tests in repository currently.
- Tailwind and jQuery are loaded from CDN (network required unless vendored locally).
