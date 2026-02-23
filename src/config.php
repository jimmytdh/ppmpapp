<?php

declare(strict_types=1);

const DB_FILE = __DIR__ . '/database/app.sqlite';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbDir = dirname(DB_FILE);
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0777, true);
    }

    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    initializeSchema($pdo);

    return $pdo;
}

function initializeSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS procurement_projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_title TEXT NOT NULL,
            end_user TEXT NOT NULL,
            type_of_project TEXT NOT NULL CHECK(type_of_project IN ("Goods", "Infrastructure", "Service")),
            general_description TEXT NOT NULL,
            mode_of_procurement TEXT NOT NULL CHECK(mode_of_procurement IN ("Public Bidding", "Small Value Procurement")),
            covered_by_epa TEXT NOT NULL CHECK(covered_by_epa IN ("Yes", "No")),
            estimated_budget REAL NOT NULL CHECK(estimated_budget >= 0),
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    // Lightweight schema migration for existing databases.
    $columns = $pdo->query("PRAGMA table_info(procurement_projects)")->fetchAll();
    $hasTypeOfProject = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'type_of_project') {
            $hasTypeOfProject = true;
            break;
        }
    }
    if (!$hasTypeOfProject) {
        $pdo->exec('ALTER TABLE procurement_projects ADD COLUMN type_of_project TEXT NOT NULL DEFAULT "Goods"');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_settings (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            prepared_by_name TEXT NOT NULL,
            prepared_by_designation TEXT NOT NULL,
            submitted_by_name TEXT NOT NULL,
            submitted_by_designation TEXT NOT NULL,
            sign_date TEXT NOT NULL
        )'
    );

    $stmt = $pdo->query('SELECT COUNT(*) FROM app_settings WHERE id = 1');
    $exists = (int)$stmt->fetchColumn();
    if ($exists === 0) {
        $insert = $pdo->prepare(
            'INSERT INTO app_settings
            (id, prepared_by_name, prepared_by_designation, submitted_by_name, submitted_by_designation, sign_date)
            VALUES (1, :prepared_by_name, :prepared_by_designation, :submitted_by_name, :submitted_by_designation, :sign_date)'
        );
        $insert->execute([
            ':prepared_by_name' => 'JIMMY B. LOMOCSO JR.',
            ':prepared_by_designation' => 'CMT II, IMIS Section Head',
            ':submitted_by_name' => 'DONNABELLE L. ARANAS, MPA, FPCHA, CESE',
            ':submitted_by_designation' => 'Chief Administrative Officer',
            ':sign_date' => '',
        ]);
    }
}
