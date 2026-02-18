<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

$ids = $_POST['ids'] ?? [];
if (!is_array($ids) || $ids === []) {
    http_response_code(422);
    echo 'No rows selected for export.';
    exit;
}

$cleanIds = array_values(array_unique(array_filter(array_map(static fn ($id) => (int)$id, $ids), static fn ($id) => $id > 0)));
if ($cleanIds === []) {
    http_response_code(422);
    echo 'Invalid selection.';
    exit;
}

$pdo = db();
$placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
$stmt = $pdo->prepare("SELECT * FROM procurement_projects WHERE id IN ($placeholders) ORDER BY id ASC");
$stmt->execute($cleanIds);
$rows = $stmt->fetchAll();

if (!$rows) {
    http_response_code(404);
    echo 'No matching rows found.';
    exit;
}

$templatePath = __DIR__ . '/APP.xlsx';
if (!file_exists($templatePath)) {
    http_response_code(500);
    echo 'Template APP.xlsx not found.';
    exit;
}

$spreadsheet = IOFactory::load($templatePath);
$sheet = $spreadsheet->getSheet(0);
normalizeTemplatePresentation($sheet);

$columnMap = resolveTemplateColumnMap($sheet);
$startRow = $columnMap['start_row'];
$tableEndCol = $columnMap['table_end_col'];

$expanded = [];
$totalRowsNeeded = 0;
$totalEpa = 0.0;
$totalNonEpa = 0.0;

foreach ($rows as $row) {
    $chunks = splitDescriptionIntoChunks((string)$row['general_description'], 420);
    $expanded[] = ['row' => $row, 'chunks' => $chunks];
    $totalRowsNeeded += count($chunks);

    $budget = (float)$row['estimated_budget'];
    if (strcasecmp((string)$row['covered_by_epa'], 'Yes') === 0) {
        $totalEpa += $budget;
    } else {
        $totalNonEpa += $budget;
    }
}

if ($totalRowsNeeded > 1) {
    $sheet->insertNewRowBefore($startRow + 1, $totalRowsNeeded - 1);
    for ($r = $startRow + 1; $r <= ($startRow + $totalRowsNeeded - 1); $r++) {
        $sheet->duplicateStyle($sheet->getStyle("A{$startRow}:{$tableEndCol}{$startRow}"), "A{$r}:{$tableEndCol}{$r}");
    }
}

$currentRow = $startRow;
foreach ($expanded as $item) {
    $row = $item['row'];
    $chunks = $item['chunks'];

    foreach ($chunks as $chunkIndex => $segments) {
        $r = $currentRow++;

        if ($chunkIndex === 0) {
            $sheet->setCellValueExplicit($columnMap['project_title'] . $r, (string)$row['project_title'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit($columnMap['end_user'] . $r, (string)$row['end_user'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit($columnMap['mode_of_procurement'] . $r, (string)$row['mode_of_procurement'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit($columnMap['covered_by_epa'] . $r, (string)$row['covered_by_epa'], DataType::TYPE_STRING);
            $sheet->setCellValue($columnMap['estimated_budget'] . $r, (float)$row['estimated_budget']);
        }

        $descCell = $columnMap['general_description'] . $r;
        $sheet->setCellValue($descCell, segmentsToRichText($segments));
        $sheet->getStyle($descCell)->getFont()->setSize(12);
        $sheet->getStyle($descCell)->getAlignment()->setWrapText(true);
        $sheet->getStyle($descCell)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        foreach ([
            $columnMap['project_title'],
            $columnMap['end_user'],
            $columnMap['mode_of_procurement'],
            $columnMap['covered_by_epa'],
            $columnMap['estimated_budget'],
        ] as $col) {
            $sheet->getStyle($col . $r)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        }
    }
}

writeTotalForLabel($sheet, 'Total Amount of Estimated Budget for EPA Projects', $totalEpa, $columnMap['estimated_budget']);
writeTotalForLabel($sheet, 'Total Amount of Estimated Budget:', $totalNonEpa, $columnMap['estimated_budget']);

$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LEGAL);
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);
$sheet->getPageMargins()->setTop(0.25);
$sheet->getPageMargins()->setRight(0.25);
$sheet->getPageMargins()->setBottom(0.25);
$sheet->getPageMargins()->setLeft(0.25);

IOFactory::registerWriter('Pdf', \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf::class);
$writer = IOFactory::createWriter($spreadsheet, 'Pdf');
$writer->setSheetIndex(0);

$filename = 'APP-Export-' . date('Ymd-His') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$writer->save('php://output');
exit;

function findCellContains(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $needle): ?array
{
    $highestRow = $sheet->getHighestDataRow();
    $highestCol = $sheet->getHighestDataColumn();

    for ($row = 1; $row <= $highestRow; $row++) {
        $range = "A{$row}:{$highestCol}{$row}";
        $values = $sheet->rangeToArray($range, null, true, true, true);
        foreach ($values[$row] as $col => $value) {
            if (is_string($value) && stripos(trim($value), $needle) !== false) {
                return ['row' => $row, 'col' => $col];
            }
        }
    }

    return null;
}

function resolveTemplateColumnMap(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
{
    $marker = findCellContains($sheet, 'Column 1');
    if ($marker === null) {
        throw new RuntimeException('Unable to locate template row with Column labels.');
    }

    $columnLabelRow = $marker['row'];
    $startRow = $columnLabelRow + 2;
    $labels = [
        'project_title' => 'Column 1',
        'end_user' => 'Column 2',
        'general_description' => 'Column 3',
        'mode_of_procurement' => 'Column 4',
        'covered_by_epa' => 'Column 5',
        'estimated_budget' => 'Column 10',
    ];

    $highestCol = $sheet->getHighestDataColumn($columnLabelRow);
    $highestIdx = columnIndex($highestCol);
    $map = [];
    $tableEndIdx = $highestIdx;

    for ($colIdx = 1; $colIdx <= $highestIdx; $colIdx++) {
        $col = columnLetter($colIdx);
        $value = trim((string)$sheet->getCell($col . $columnLabelRow)->getCalculatedValue());
        foreach ($labels as $key => $label) {
            if ($value === $label) {
                $map[$key] = $col;
                $tableEndIdx = max($tableEndIdx, $colIdx);
            }
        }
    }

    foreach (array_keys($labels) as $required) {
        if (!isset($map[$required])) {
            throw new RuntimeException('Template column not found: ' . $labels[$required]);
        }
    }

    $map['start_row'] = $startRow;
    $map['table_end_col'] = columnLetter($tableEndIdx);

    return $map;
}

function columnIndex(string $columnLetter): int
{
    $columnLetter = strtoupper($columnLetter);
    $index = 0;
    for ($i = 0, $len = strlen($columnLetter); $i < $len; $i++) {
        $index = ($index * 26) + (ord($columnLetter[$i]) - 64);
    }
    return $index;
}

function columnLetter(int $columnIndex): string
{
    $column = '';
    while ($columnIndex > 0) {
        $remainder = ($columnIndex - 1) % 26;
        $column = chr(65 + $remainder) . $column;
        $columnIndex = intdiv($columnIndex - $remainder - 1, 26);
    }
    return $column;
}

function writeTotalForLabel(
    \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
    string $labelNeedle,
    float $amount,
    string $budgetCol
): void {
    $cell = findCellContains($sheet, $labelNeedle);
    if ($cell === null) {
        return;
    }

    $budgetIdx = columnIndex($budgetCol);
    $nextCol = columnLetter($budgetIdx + 1);
    $targetCol = $nextCol;

    if (trim((string)$sheet->getCell($budgetCol . $cell['row'])->getCalculatedValue()) === '') {
        $targetCol = $budgetCol;
    }

    $sheet->setCellValueExplicit(
        $targetCol . $cell['row'],
        '  ' . number_format($amount, 2, '.', ','),
        DataType::TYPE_STRING
    );
    $sheet->getStyle($targetCol . $cell['row'])->getFont()->setBold(true);
    $sheet->getStyle($targetCol . $cell['row'])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}

function splitDescriptionIntoChunks(string $html, int $maxCharsPerChunk = 420): array
{
    $segments = htmlToStyledSegments($html);
    if ($segments === []) {
        return [[['text' => '', 'bold' => false, 'italic' => false, 'underline' => false]]];
    }

    $chunks = [];
    $current = [];
    $currentLen = 0;

    foreach ($segments as $seg) {
        if ($seg['text'] === "\n") {
            if ($currentLen + 40 > $maxCharsPerChunk && $current !== []) {
                $chunks[] = $current;
                $current = [];
                $currentLen = 0;
            }
            $current[] = $seg;
            $currentLen += 40;
            continue;
        }

        $parts = preg_split('/(\s+)/u', $seg['text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            continue;
        }

        foreach ($parts as $part) {
            $partLen = mb_strlen($part);
            if ($currentLen + $partLen > $maxCharsPerChunk && $current !== []) {
                $chunks[] = $current;
                $current = [];
                $currentLen = 0;
            }

            $current[] = [
                'text' => $part,
                'bold' => $seg['bold'],
                'italic' => $seg['italic'],
                'underline' => $seg['underline'],
            ];
            $currentLen += $partLen;
        }
    }

    if ($current !== []) {
        $chunks[] = $current;
    }

    return $chunks ?: [[['text' => '', 'bold' => false, 'italic' => false, 'underline' => false]]];
}

function htmlToStyledSegments(string $html): array
{
    $normalized = preg_replace('/<br\\s*\\/?\\>/i', '<br>', $html) ?? $html;
    $allowed = strip_tags($normalized, '<b><i><u><br>');
    $parts = preg_split('/(<\\/?(?:b|i|u|br)\\s*\\/?>)/i', $allowed, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        return [];
    }

    $segments = [];
    $state = ['bold' => false, 'italic' => false, 'underline' => false];

    foreach ($parts as $part) {
        $tag = strtolower(trim($part));
        if ($tag === '<b>') {
            $state['bold'] = true;
            continue;
        }
        if ($tag === '</b>') {
            $state['bold'] = false;
            continue;
        }
        if ($tag === '<i>') {
            $state['italic'] = true;
            continue;
        }
        if ($tag === '</i>') {
            $state['italic'] = false;
            continue;
        }
        if ($tag === '<u>') {
            $state['underline'] = true;
            continue;
        }
        if ($tag === '</u>') {
            $state['underline'] = false;
            continue;
        }
        if ($tag === '<br>' || $tag === '<br/>') {
            $segments[] = ['text' => "\n"] + $state;
            continue;
        }

        $text = html_entity_decode(strip_tags($part), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($text === '') {
            continue;
        }
        $segments[] = ['text' => $text] + $state;
    }

    return $segments;
}

function segmentsToRichText(array $segments): RichText
{
    $richText = new RichText();
    foreach ($segments as $seg) {
        $text = (string)($seg['text'] ?? '');
        if ($text === '') {
            continue;
        }

        if (($seg['bold'] ?? false) === false && ($seg['italic'] ?? false) === false && ($seg['underline'] ?? false) === false) {
            $richText->createText($text);
            continue;
        }

        $run = $richText->createTextRun($text);
        if (!empty($seg['bold'])) {
            $run->getFont()->setBold(true);
        }
        if (!empty($seg['italic'])) {
            $run->getFont()->setItalic(true);
        }
        if (!empty($seg['underline'])) {
            $run->getFont()->setUnderline(\PhpOffice\PhpSpreadsheet\Style\Font::UNDERLINE_SINGLE);
        }
    }

    return $richText;
}

function normalizeTemplatePresentation(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
{
    $sheet->duplicateStyle($sheet->getStyle('C32'), 'G32');
    $sheet->setCellValueExplicit('G32', 'MR. WILLY JOHN DELUTE', DataType::TYPE_STRING);

    $highestRow = $sheet->getHighestDataRow();
    $highestCol = $sheet->getHighestDataColumn();
    for ($row = 1; $row <= $highestRow; $row++) {
        $range = "A{$row}:{$highestCol}{$row}";
        $values = $sheet->rangeToArray($range, null, true, true, true);
        foreach ($values[$row] as $col => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (stripos($value, 'Signature over') !== false && stripos($value, 'Printed Name') !== false) {
                $cell = $col . $row;
                $sheet->setCellValueExplicit($cell, 'Signature over Printed Name', DataType::TYPE_STRING);
                $sheet->getStyle($cell)->getAlignment()->setWrapText(false);
            }
        }
    }

    if (!$sheet->getCell('G33')->isInMergeRange()) {
        $sheet->mergeCells('G33:H33');
    }
    if (!$sheet->getCell('G34')->isInMergeRange()) {
        $sheet->mergeCells('G34:H34');
    }
    $sheet->setCellValueExplicit('G33', 'Signature over Printed Name', DataType::TYPE_STRING);
    $sheet->getStyle('G33:H33')->getAlignment()->setWrapText(false);

    $criteriaCell = findCellContains($sheet, 'Criteria for Bid Evaluation');
    if ($criteriaCell !== null) {
        $sheet->getStyle($criteriaCell['col'] . $criteriaCell['row'])
            ->getFont()
            ->getColor()
            ->setARGB('FF000000');
    }

    $sheet->getStyle('H7:H8')
        ->getFont()
        ->getColor()
        ->setARGB('FF000000');
}

