<?php
// staff/dump_june_to_csv.php
// ONE-TIME SCRIPT: Run this on the LIVE server to dump all June 2026
// admission enquiries from the database into a downloadable CSV file.
// Access via: https://yourdomain.com/gate-pass/staff/dump_june_to_csv.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

// Only allow logged-in staff
if (empty($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

// ── Pull all June 2026 admission enquiries from live DB ─────────────────────
$stmt = $pdo->prepare("
    SELECT
        student_name,
        mobile,
        purpose,
        status,
        created_at
    FROM gate_passes
    WHERE created_at >= '2026-06-01 00:00:00'
      AND created_at <= '2026-06-30 23:59:59'
      AND (roll_no = 'Admission Visitor' OR purpose LIKE '%Admission Enquiry%' OR purpose LIKE '%Admission%')
    ORDER BY created_at ASC
");
$stmt->execute();
$rows = $stmt->fetchAll();

$total = count($rows);

// ── Serve as downloadable CSV ────────────────────────────────────────────────
$filename = 'June_2026_All_Enquiries_LIVE_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM for Excel UTF-8

fputcsv($out, ['Sr. No.', 'Student Name', 'Mobile Number', 'Purpose', 'Date & Time', 'Status']);

foreach ($rows as $i => $row) {
    $purposeClean = $row['purpose'];
    $purposeClean = preg_replace('/\s*\|\s*Date:\s*\S+/', '', $purposeClean);
    $purposeClean = preg_replace('/^Purpose:\s*/i', '', trim($purposeClean));

    fputcsv($out, [
        $i + 1,
        $row['student_name'],
        $row['mobile'],
        $purposeClean,
        date('d M Y, h:i A', strtotime($row['created_at'])),
        strtoupper($row['status'] ?? 'EXPIRED'),
    ]);
}

fclose($out);
exit;
