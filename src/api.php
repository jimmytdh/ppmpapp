<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $action = $_GET['action'] ?? 'list';
    $pdo = db();

    switch ($action) {
        case 'list':
            listRows($pdo);
            break;
        case 'get':
            getRow($pdo);
            break;
        case 'create':
            createRow($pdo);
            break;
        case 'update':
            updateRow($pdo);
            break;
        case 'delete':
            deleteRow($pdo);
            break;
        case 'get_signatories':
            getSignatories($pdo);
            break;
        case 'save_signatories':
            saveSignatories($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}

function listRows(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT * FROM procurement_projects ORDER BY id DESC');
    echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
}

function getRow(PDO $pdo): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Invalid ID']);
        return;
    }

    $stmt = $pdo->prepare('SELECT * FROM procurement_projects WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Record not found']);
        return;
    }

    echo json_encode(['ok' => true, 'data' => $row]);
}

function createRow(PDO $pdo): void
{
    $payload = validatePayload($_POST);
    if (!$payload['ok']) {
        http_response_code(422);
        echo json_encode($payload);
        return;
    }

    $data = $payload['data'];
    $stmt = $pdo->prepare(
        'INSERT INTO procurement_projects
        (project_title, end_user, general_description, mode_of_procurement, covered_by_epa, estimated_budget, created_at, updated_at)
        VALUES (:project_title, :end_user, :general_description, :mode_of_procurement, :covered_by_epa, :estimated_budget, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
    );

    $stmt->execute([
        ':project_title' => $data['project_title'],
        ':end_user' => $data['end_user'],
        ':general_description' => $data['general_description'],
        ':mode_of_procurement' => $data['mode_of_procurement'],
        ':covered_by_epa' => $data['covered_by_epa'],
        ':estimated_budget' => $data['estimated_budget'],
    ]);

    echo json_encode(['ok' => true, 'message' => 'Created successfully']);
}

function updateRow(PDO $pdo): void
{
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Invalid ID']);
        return;
    }

    $payload = validatePayload($_POST);
    if (!$payload['ok']) {
        http_response_code(422);
        echo json_encode($payload);
        return;
    }

    $data = $payload['data'];
    $stmt = $pdo->prepare(
        'UPDATE procurement_projects SET
            project_title = :project_title,
            end_user = :end_user,
            general_description = :general_description,
            mode_of_procurement = :mode_of_procurement,
            covered_by_epa = :covered_by_epa,
            estimated_budget = :estimated_budget,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id'
    );

    $stmt->execute([
        ':id' => $id,
        ':project_title' => $data['project_title'],
        ':end_user' => $data['end_user'],
        ':general_description' => $data['general_description'],
        ':mode_of_procurement' => $data['mode_of_procurement'],
        ':covered_by_epa' => $data['covered_by_epa'],
        ':estimated_budget' => $data['estimated_budget'],
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Record not found']);
        return;
    }

    echo json_encode(['ok' => true, 'message' => 'Updated successfully']);
}

function deleteRow(PDO $pdo): void
{
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Invalid ID']);
        return;
    }

    $stmt = $pdo->prepare('DELETE FROM procurement_projects WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Record not found']);
        return;
    }

    echo json_encode(['ok' => true, 'message' => 'Deleted successfully']);
}

function getSignatories(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT prepared_by_name, prepared_by_designation, submitted_by_name, submitted_by_designation, sign_date FROM app_settings WHERE id = 1');
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Signatory settings not found']);
        return;
    }

    echo json_encode(['ok' => true, 'data' => $row]);
}

function saveSignatories(PDO $pdo): void
{
    $preparedByName = trim((string)($_POST['prepared_by_name'] ?? ''));
    $preparedByDesignation = trim((string)($_POST['prepared_by_designation'] ?? ''));
    $submittedByName = trim((string)($_POST['submitted_by_name'] ?? ''));
    $submittedByDesignation = trim((string)($_POST['submitted_by_designation'] ?? ''));
    $signDate = trim((string)($_POST['sign_date'] ?? ''));

    if ($preparedByName === '' || $preparedByDesignation === '' || $submittedByName === '' || $submittedByDesignation === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'All signatory name and designation fields are required']);
        return;
    }

    if ($signDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $signDate)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => 'Date must be in YYYY-MM-DD format']);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE app_settings SET
            prepared_by_name = :prepared_by_name,
            prepared_by_designation = :prepared_by_designation,
            submitted_by_name = :submitted_by_name,
            submitted_by_designation = :submitted_by_designation,
            sign_date = :sign_date
        WHERE id = 1'
    );
    $stmt->execute([
        ':prepared_by_name' => $preparedByName,
        ':prepared_by_designation' => $preparedByDesignation,
        ':submitted_by_name' => $submittedByName,
        ':submitted_by_designation' => $submittedByDesignation,
        ':sign_date' => $signDate,
    ]);

    echo json_encode(['ok' => true, 'message' => 'Signatories saved']);
}

function validatePayload(array $input): array
{
    $projectTitle = trim((string)($input['project_title'] ?? ''));
    $endUser = trim((string)($input['end_user'] ?? ''));
    $generalDescription = trim((string)($input['general_description'] ?? ''));
    $generalDescriptionPlain = trim(html_entity_decode(strip_tags($generalDescription), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $mode = trim((string)($input['mode_of_procurement'] ?? ''));
    $epa = trim((string)($input['covered_by_epa'] ?? ''));
    $budgetRaw = (string)($input['estimated_budget'] ?? '');
    $budget = is_numeric($budgetRaw) ? (float)$budgetRaw : -1;

    if ($projectTitle === '' || $endUser === '' || $generalDescriptionPlain === '') {
        return ['ok' => false, 'message' => 'Text fields are required'];
    }

    if (!in_array($mode, ['Public Bidding', 'Small Value Procurement'], true)) {
        return ['ok' => false, 'message' => 'Invalid mode of procurement'];
    }

    if (!in_array($epa, ['Yes', 'No'], true)) {
        return ['ok' => false, 'message' => 'Invalid EPA value'];
    }

    if ($budget < 0) {
        return ['ok' => false, 'message' => 'Estimated budget must be a valid number'];
    }

    return [
        'ok' => true,
        'data' => [
            'project_title' => $projectTitle,
            'end_user' => $endUser,
            'general_description' => $generalDescription,
            'mode_of_procurement' => $mode,
            'covered_by_epa' => $epa,
            'estimated_budget' => $budget,
        ],
    ];
}
