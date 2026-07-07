<?php
// staff/export_june_csv.php
// Exports all June 2026 Admission Enquiries as a UTF-8 CSV file.
// DB-first; falls back to local admission_enquiries.csv snapshot if DB is unreachable.

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../config.php';

if (empty($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

$rows    = [];
$csvPath = __DIR__ . '/../admission_enquiries.csv';

// Try live DB first
try {
    $stmt = $pdo->prepare("
        SELECT
            id,
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
} catch (Exception $e) {
    $rows = [];
}

// Fallback: read from local CSV snapshot
if (empty($rows) && file_exists($csvPath)) {
    if (($fh = fopen($csvPath, 'r')) !== false) {
        fgetcsv($fh); // skip header
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 4) continue;
            $dateRaw = trim($row[3]);
            $ts = strtotime($dateRaw);
            if ($ts === false) continue;
            $ymd = date('Y-m-d', $ts);
            if ($ymd < '2026-06-01' || $ymd > '2026-06-30') continue;
            $rows[] = [
                'id'           => '',
                'student_name' => trim($row[0]),
                'mobile'       => trim($row[1]),
                'purpose'      => trim($row[2]),
                'status'       => strtoupper(trim($row[4] ?? 'EXPIRED')),
                'created_at'   => date('Y-m-d H:i:s', $ts),
            ];
        }
        fclose($fh);
        usort($rows, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));
    }
}

$filename = 'June_2026_Admission_Enquiries_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');

// BOM for Excel UTF-8 compatibility
fwrite($out, "\xEF\xBB\xBF");

// Header row
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
