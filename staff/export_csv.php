<?php
// staff/export_csv.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';

if (empty($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch all log records with fully selected visitor columns
$stmt = $pdo->query("
    SELECT gp.student_name,
           gp.institute_name,
           gp.mobile,
           gp.created_at,
           gl.scanned_at
    FROM gate_logs gl
    JOIN gate_passes gp ON gl.pass_id = gp.id
    ORDER BY gl.scanned_at DESC
");

$rows = $stmt->fetchAll();

// send headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=gate_logs_' . date('Y-m-d_H-i-s') . '.csv');

// open output stream
$output = fopen('php://output', 'w');

// header row
fputcsv($output, [
    'Student Name',
    'Mobile Number',
    'Registration Date',
    'Scanned At'
]);

// data rows
foreach ($rows as $r) {
    fputcsv($output, [
        $r['student_name'],
        $r['mobile'],
        date('Y-m-d h:i A', strtotime($r['created_at'])),
        date('Y-m-d h:i A', strtotime($r['scanned_at'])),
    ]);
}

fclose($output);
exit;
