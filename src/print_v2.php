<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

$ids = $_GET['ids'] ?? [];
if (!is_array($ids)) {
    $ids = [$ids];
}

$cleanIds = array_values(array_unique(array_filter(array_map(static fn ($id) => (int)$id, $ids), static fn ($id) => $id > 0)));
if ($cleanIds === []) {
    http_response_code(422);
    echo 'No rows selected for APP.';
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

$totalEpa = 0.0;
$totalNonEpa = 0.0;
foreach ($rows as $row) {
    if (strcasecmp((string)$row['covered_by_epa'], 'Yes') === 0) {
        $totalEpa += (float)$row['estimated_budget'];
    } else {
        $totalNonEpa += (float)$row['estimated_budget'];
    }
}

$signatoryStmt = $pdo->query('SELECT prepared_by_name, prepared_by_designation, submitted_by_name, submitted_by_designation, sign_date FROM app_settings WHERE id = 1');
$signatory = $signatoryStmt->fetch() ?: [
    'prepared_by_name' => 'JIMMY B. LOMOCSO JR.',
    'prepared_by_designation' => 'CMT II, IMIS Section Head',
    'submitted_by_name' => 'DONNABELLE L. ARANAS, MPA, FPCHA, CESE',
    'submitted_by_designation' => 'Chief Administrative Officer',
    'sign_date' => '',
];

$displayDate = '';
if (!empty($signatory['sign_date'])) {
    $dt = DateTime::createFromFormat('Y-m-d', (string)$signatory['sign_date']);
    $displayDate = $dt ? $dt->format('m/d/Y') : (string)$signatory['sign_date'];
}

function sanitizeDescriptionHtml(string $html): string
{
    $normalized = preg_replace('/<\s*\/?\s*(div|p|li|h[1-6])[^>]*>/i', '<br>', $html) ?? $html;
    $normalized = str_replace(["\r\n", "\r", "\n"], '<br>', $normalized);

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?><div id="root">' . $normalized . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $root = $doc->getElementById('root');
    if (!$root) {
        return '';
    }

    $allowed = ['b', 'i', 'u', 'br'];
    $walker = function (DOMNode $node) use (&$walker, $allowed): void {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $walker($child);
                $tag = strtolower($child->tagName);
                if (!in_array($tag, $allowed, true)) {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }

                while ($child->attributes->length > 0) {
                    $child->removeAttributeNode($child->attributes->item(0));
                }
            }
        }
    };

    $walker($root);
    $htmlOut = '';
    foreach ($root->childNodes as $child) {
        $htmlOut .= $doc->saveHTML($child);
    }

    return $htmlOut;
}

$bannerDataUri = null;
$bannerPath = __DIR__ . '/assets/print-header.png';
if (file_exists($bannerPath)) {
    $mime = mime_content_type($bannerPath) ?: 'image/png';
    $bannerDataUri = 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($bannerPath));
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>APP Print View</title>
    <style>
        @page { size: legal landscape; margin: 0.15in; }
        html, body { margin: 0; padding: 0; color: #000; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10px; }
        .sheet { width: 100%; }
        .no-print { display: flex; gap: 8px; margin: 0 0 8px 0; }
        .no-print button { border: 1px solid #c9c9c9; background: #f7f7f7; padding: 6px 10px; cursor: pointer; }

        .print-banner { text-align: center; }
        .print-banner img { width: 50%; height: auto; display: block; margin: 0 auto; }
        .title-wrap { text-align: center; margin: 28px 0 8px 0; }
        .title { font-weight: 700; font-size: 10px; line-height: 1.15; letter-spacing: 0.2px; }
        .mode-row { margin-top: 10px; font-size: 10px; font-weight: 700; }
        .mode-item { margin: 0 18px; white-space: nowrap; }
        .chk {
            display: inline-block;
            width: 22px;
            height: 22px;
            border: 1.6px solid #000;
            vertical-align: middle;
            margin-right: 8px;
            margin-top: -2px;
        }

        .app-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 10px;
        }
        .app-grid th, .app-grid td {
            border: 1px solid #000;
            padding: 2px 4px;
            vertical-align: top;
        }
        .app-grid thead th {
            text-align: center;
            vertical-align: middle;
            font-weight: 700;
        }
        .group-row th { font-size: 10px; }
        .hdr-row th { font-size: 10px; line-height: 1.1; }
        .colno-row th { font-size: 10px; line-height: 1; }
        .section td {
            font-weight: 700;
            background: #fff;
        }
        .blank td {
            height: 28px;
            padding: 0;
        }
        .data-row td { font-size: 10px; }
        .desc { font-size: 10px; line-height: 1.25; word-break: break-word; }
        .num { text-align: right; white-space: nowrap; }
        .totals-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 22px;
            font-size: 10px;
            font-weight: 700;
        }
        .totals-grid td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .totals-label { text-align: right; padding-right: 8px; }
        .totals-value { text-align: right; white-space: nowrap; }

        .signatories {
            margin-top: 16px;
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 10px;
        }
        .signatories td {
            border: none;
            text-align: center;
            vertical-align: top;
            padding: 0 8px;
        }
        .signatories .label {
            text-align: left;
            padding-bottom: 34px;
        }
        .signatories .name {
            font-weight: 700;
            white-space: nowrap;
        }
        .signatories .name u {
            text-underline-offset: 2px;
            text-decoration-thickness: from-font;
        }
        .signatories .sig {
            padding-top: 2px;
        }
        .signatories .title-row td {
            padding-top: 2px;
        }
        .signatories .date-row td {
            padding-top: 34px;
            text-align: center;
        }
        .date-line {
            display: inline-block;
            min-width: 130px;
            border-bottom: 1px solid #000;
            transform: translateY(-2px);
        }

        @media print {
            .no-print { display: none; }
            .app-grid thead { display: table-row-group; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="no-print">
            <button onclick="window.print()">Print / Save as PDF</button>
            <button onclick="window.close()">Close</button>
        </div>

        <?php if ($bannerDataUri !== null): ?>
            <div class="print-banner">
                <img src="<?= htmlspecialchars($bannerDataUri) ?>" alt="Agency Header">
            </div>
        <?php endif; ?>

        <div class="title-wrap">
            <div class="title">SUPPLEMENTAL ANNUAL PROCUREMENT PLAN FOR FY 2026</div>
            <div class="mode-row">
                <span class="mode-item"><span class="chk"></span>INDICATIVE</span>
                <span class="mode-item"><span class="chk"></span>FINAL</span>
                <span class="mode-item"><span class="chk"></span>UPDATED [Version No. ____ ]</span>
            </div>
        </div>

        <table class="app-grid">
            <colgroup>
                <col style="width:13.2%">
                <col style="width:5.2%">
                <col style="width:14.1%">
                <col style="width:6.5%">
                <col style="width:6.5%">
                <col style="width:6.7%">
                <col style="width:6.7%">
                <col style="width:6.4%">
                <col style="width:7.3%">
                <col style="width:6.3%">
                <col style="width:7.3%">
                <col style="width:13.8%">
            </colgroup>
            <thead>
                <tr class="group-row">
                    <th colspan="6">PROCUREMENT PROJECT DETAILS</th>
                    <th colspan="2">PROJECTED TIMELINE (MM/YYYY)</th>
                    <th colspan="2">FUNDING DETAILS</th>
                    <th rowspan="3">PROCUREMENT<br>STRATEGY OR TOOLS</th>
                    <th rowspan="3">REMARKS<br>(Other relevant descriptions of the procurement project, if applicable)</th>
                </tr>
                <tr class="hdr-row">
                    <th>Project Title</th>
                    <th>End-User or<br>Implementing<br>Unit</th>
                    <th>General Description of the Project</th>
                    <th>Mode of Procurement</th>
                    <th>To be covered by an Early Procurement Activity? (Yes/No)</th>
                    <th>Criteria for Bid Evaluation (Including Sustainability and Domestic Preference)</th>
                    <th>Start of<br>Procurement Activity</th>
                    <th>End of<br>Procurement Activity</th>
                    <th>Source of Fund</th>
                    <th>Estimated Budget / Approved Budget for the Contract (PhP)</th>
                </tr>
                <tr class="colno-row">
                    <th>Column 1</th>
                    <th>Column 2</th>
                    <th>Column 3</th>
                    <th>Column 4</th>
                    <th>Column 5</th>
                    <th>Column 6</th>
                    <th>Column 7</th>
                    <th>Column 8</th>
                    <th>Column 9</th>
                    <th>Column 10</th>
                </tr>
            </thead>
            <tbody>
                <tr class="section"><td colspan="12">General Requirements</td></tr>
                <?php foreach ($rows as $row): ?>
                    <tr class="data-row">
                        <td><?= htmlspecialchars((string)$row['project_title']) ?></td>
                        <td><?= htmlspecialchars((string)$row['end_user']) ?></td>
                        <td class="desc"><?= sanitizeDescriptionHtml((string)$row['general_description']) ?></td>
                        <td><?= htmlspecialchars((string)$row['mode_of_procurement']) ?></td>
                        <td><?= htmlspecialchars((string)$row['covered_by_epa']) ?></td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td class="num"><?= number_format((float)$row['estimated_budget'], 2) ?></td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                <?php endforeach; ?>

                <?php for ($i = 0; $i < max(0, 2 - count($rows)); $i++): ?>
                    <tr class="blank">
                        <?php for ($j = 0; $j < 12; $j++): ?><td>&nbsp;</td><?php endfor; ?>
                    </tr>
                <?php endfor; ?>

                <tr class="section"><td colspan="12">Miscellaneous Items (for Direct Acquisition only) Sec 32.2 of RA No. 12009</td></tr>
                <?php for ($i = 0; $i < 2; $i++): ?>
                    <tr class="blank">
                        <?php for ($j = 0; $j < 12; $j++): ?><td>&nbsp;</td><?php endfor; ?>
                    </tr>
                <?php endfor; ?>

                <tr class="section"><td colspan="12">Common Use Supplies and Equipment (CSE) to be purchased from PS-DBM (kindly indicate the summary/total amounts only)</td></tr>
                <?php for ($i = 0; $i < 2; $i++): ?>
                    <tr class="blank">
                        <?php for ($j = 0; $j < 12; $j++): ?><td>&nbsp;</td><?php endfor; ?>
                    </tr>
                <?php endfor; ?>

                <tr><td colspan="12">Note: Insert additional rows as necessary</td></tr>
            </tbody>
        </table>

        <table class="totals-grid">
            <colgroup>
                <col style="width:13.2%">
                <col style="width:5.2%">
                <col style="width:14.1%">
                <col style="width:6.5%">
                <col style="width:6.5%">
                <col style="width:6.7%">
                <col style="width:6.7%">
                <col style="width:6.4%">
                <col style="width:7.3%">
                <col style="width:6.3%">
                <col style="width:7.3%">
                <col style="width:13.8%">
            </colgroup>
            <tbody>
                <tr>
                    <td colspan="9" class="totals-label">Total Amount of Estimated Budget for EPA Projects:</td>
                    <td class="totals-value"><?= number_format($totalEpa, 2) ?></td>
                    <td colspan="2">&nbsp;</td>
                </tr>
                <tr>
                    <td colspan="9" class="totals-label">Total Amount of CSEs to be purchased from PS-DBM:</td>
                    <td class="totals-value">&nbsp;</td>
                    <td colspan="2">&nbsp;</td>
                </tr>
                <tr>
                    <td colspan="9" class="totals-label">Total Amount of Estimated Budget:</td>
                    <td class="totals-value"><?= number_format($totalNonEpa, 2) ?></td>
                    <td colspan="2">&nbsp;</td>
                </tr>
            </tbody>
        </table>

        <table class="signatories">
            <colgroup>
                <col style="width:20%">
                <col style="width:29%">
                <col style="width:22%">
                <col style="width:29%">
            </colgroup>
            <tbody>
                <tr>
                    <td class="label">Prepared by:</td>
                    <td class="label">Submitted by:</td>
                    <td class="label">Recommended by:</td>
                    <td class="label">Approved by:</td>
                </tr>
                <tr>
                    <td class="name"><u><?= htmlspecialchars((string)$signatory['prepared_by_name']) ?></u></td>
                    <td class="name"><u><?= htmlspecialchars((string)$signatory['submitted_by_name']) ?></u></td>
                    <td class="name"><u>MR. WILLY JOHN DELUTE</u></td>
                    <td class="name"><u>AGUSTIN D. AGOS JR., MD, FPGS, FPCS, MA, DODT, PhD OD, RODC, APRM&trade;</u></td>
                </tr>
                <tr>
                    <td class="sig">Signature over Printed Name</td>
                    <td class="sig">Signature over Printed Name</td>
                    <td class="sig">Signature over Printed Name</td>
                    <td class="sig">Signature over Printed Name</td>
                </tr>
                <tr class="title-row">
                    <td><?= htmlspecialchars((string)$signatory['prepared_by_designation']) ?></td>
                    <td><?= htmlspecialchars((string)$signatory['submitted_by_designation']) ?></td>
                    <td>Budget Officer - SAO</td>
                    <td>Medical Center Chief II</td>
                </tr>
                <tr class="date-row">
                    <td>Date : <span class="date-line"><?= htmlspecialchars($displayDate) ?></span></td>
                    <td>Date : <span class="date-line"><?= htmlspecialchars($displayDate) ?></span></td>
                    <td>Date : <span class="date-line">_______</span></td>
                    <td>Date : <span class="date-line">_______</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
