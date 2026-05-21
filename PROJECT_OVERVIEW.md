# Project Overview

## Purpose
APP/PPMP Procurement Manager is a lightweight PHP + SQLite web app for managing procurement project entries and producing APP/PPMP formatted print outputs.

The app supports:
- CRUD for procurement records
- Signatory settings management
- APP print view generation (legal landscape, browser print/PDF)
- PPMP print view generation (legal landscape, browser print/PDF)
- Legacy template-based PDF export from `APP.xlsx`

## Tech Stack
- Backend: PHP 8.2+, PDO (SQLite)
- Frontend: server-rendered HTML, jQuery 3.7.1, Tailwind CSS via CDN, Plus Jakarta Sans font via Google Fonts
- Database: SQLite (`src/database/app.sqlite`)
- Document export: `phpoffice/phpspreadsheet`, `mpdf/mpdf`

## Repository Structure
- `src/index.php`: Main UI page (table + modals)
- `src/assets/app.js`: Client-side CRUD/modals/formatting logic
- `src/api.php`: JSON API for records and signatories
- `src/config.php`: DB connection + schema bootstrap + default settings seed + `ALLOWED_MODES_OF_PROCUREMENT` constant
- `src/print_v2.php`: Browser print page (legal landscape APP layout)
- `src/ppmp_v2.php`: Browser print page (legal landscape PPMP layout)
- `src/export.php`: Legacy XLSX template -> PDF export flow
- `src/APP.xlsx`: Export template used by `src/export.php`
- `src/assets/print-header.png`: Optional agency header image embedded in print views
- `run-server.bat`: Local PHP server launcher

## Runtime Flow
1. User opens `/` (`src/index.php`).
2. `src/assets/app.js` calls `api.php?action=list` to load rows.
3. Create/Edit/Delete actions call:
   - `action=create`
   - `action=update`
   - `action=delete`
4. Single-record fetch uses `action=get&id=<id>` (used by edit modal pre-fill).
5. Signatories modal uses:
   - `action=get_signatories`
   - `action=save_signatories`
6. APP button opens `print_v2.php?ids[]=<id>` in a new tab for print/PDF via browser.
7. PPMP button opens `ppmp_v2.php?ids[]=<id>` in a new tab for print/PDF via browser.

## Data Model
Database is initialized automatically in `src/config.php`.

### `procurement_projects`
- `id` (PK, autoincrement)
- `project_title` (TEXT, required)
- `end_user` (TEXT, required)
- `type_of_project` (TEXT, CHECK: `Goods|Infrastructure|Service`)
- `general_description` (TEXT, required; stored as sanitized rich HTML)
- `mode_of_procurement` (TEXT, CHECK: see `ALLOWED_MODES_OF_PROCUREMENT` below)
- `covered_by_epa` (TEXT, CHECK: `Yes|No`)
- `document_type` (TEXT, CHECK: `Empty|Updated|Supplemental`, default `Empty`)
- `estimated_budget` (REAL, `>= 0`)
- `created_at`, `updated_at` (TEXT, timestamps)

#### Allowed Modes of Procurement
Defined as `ALLOWED_MODES_OF_PROCUREMENT` in `src/config.php`:
- `Public Bidding`
- `Small Value Procurement`
- `Direct Retail Purchase of Petroleum Fuel, Oil and Lubricant Products Electronic Charging Devices, and Online Subscriptions`

### `app_settings`
- Single-row settings table (`id = 1`, enforced via CHECK constraint)
- `prepared_by_name`, `prepared_by_designation`
- `submitted_by_name`, `submitted_by_designation`
- `sign_date` (`YYYY-MM-DD` or empty string)

> **Note:** "Recommended by" (MR. WILLY JOHN DELUTE) and "Approved by" (AGUSTIN D. AGOS JR., MD…) signatories in `print_v2.php` are **hardcoded** and not configurable via `app_settings`. `ppmp_v2.php` only has "Recommended by" hardcoded — "Approved by" was removed from the PPMP view.

## Schema Migrations
`src/config.php` runs lightweight auto-migrations on every startup:
1. Adds `type_of_project` column (default `Goods`) if missing from existing databases.
2. Adds `document_type` column (default `Empty`) if missing from existing databases.
3. `ensureModeOfProcurementConstraint()` — if the existing table's CHECK constraint does not include the "Direct Retail Purchase..." mode, it recreates the table in a transaction to apply the updated constraint (preserving all data).

## Validation and Sanitization
- API-level validation in `validatePayload()` (`src/api.php`) enforces required fields, enum values, and numeric budget.
- `type_of_project` validated against: `Goods`, `Infrastructure`, `Service`.
- `document_type` validated against: `Empty`, `Updated`, `Supplemental`.
- `mode_of_procurement` validated against `ALLOWED_MODES_OF_PROCUREMENT` constant.
- Rich text description is sanitized client-side and re-validated server-side for non-empty plain text.
- `print_v2.php` and `ppmp_v2.php` each define `sanitizeDescriptionHtml()` which strips all tags except `<b>`, `<i>`, `<u>`, `<br>` and normalises block elements to `<br>`.

## Print and Export

### APP Print (`print_v2.php`)
- Renders a 12-column APP-style HTML table for legal landscape printing.
- Pulls selected rows by `ids[]`, ordered by `id ASC`.
- Title is dynamically prefixed based on `document_type`:
  - `Supplemental` → `SUPPLEMENTAL ANNUAL PROCUREMENT PLAN FOR FY <year>`
  - `Updated` → `UPDATED ANNUAL PROCUREMENT PLAN FOR FY <year>`
  - Mixed rows → `ANNUAL PROCUREMENT PLAN FOR FY <year>` (no prefix)
- Embeds `src/assets/print-header.png` as a base64 data URI if the file exists.
- Signatories pulled from `app_settings` (prepared/submitted); recommended/approved are hardcoded.

### PPMP Print (`ppmp_v2.php`)
- Renders a 12-column PPMP-style HTML table for legal landscape printing.
- Column 2 uses `type_of_project` (Goods / Infrastructure / Service).
- Displays a computed `TOTAL BUDGET` row summing `estimated_budget` across all selected rows.
- Title dynamically prefixed from `document_type` (same logic as APP, but `Empty` yields no prefix).
- Shows Fiscal Year and End-User(s) as metadata above the grid.
- Embeds `src/assets/print-header.png` as a base64 data URI if the file exists.
- Signatories pulled from `app_settings` (prepared/submitted); recommended/approved are hardcoded.

### Legacy flow
- `export.php` loads `APP.xlsx`, maps template columns by label, injects row data, and writes PDF using mPDF.
- Keeps rich text formatting in descriptions through PhpSpreadsheet `RichText`.

## API Reference

All endpoints are on `api.php`. Responses follow `{"ok": true/false, ...}`.

| Action | Method | Parameters | Description |
|--------|--------|------------|-------------|
| `list` | GET | — | Returns all records ordered by `id DESC` |
| `get` | GET | `id` | Returns a single record by ID |
| `create` | POST | record fields | Inserts a new procurement record |
| `update` | POST | `id` + record fields | Updates an existing record |
| `delete` | POST | `id` | Deletes a record |
| `get_signatories` | GET | — | Returns current signatory settings |
| `save_signatories` | POST | signatory fields | Saves signatory settings |

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

3. Open: `http://localhost:8000`

## Current Constraints / Notes
- No authentication/authorization layer.
- No CSRF protection on write endpoints.
- API returns raw exception messages on 500 errors.
- No automated tests in repository currently.
- Tailwind and jQuery are loaded from CDN (network required unless vendored locally).
- "Recommended by" and "Approved by" signatories are hardcoded in both print views — not editable via UI.
- `sanitizeDescriptionHtml()` is duplicated between `print_v2.php` and `ppmp_v2.php` (identical implementations).
