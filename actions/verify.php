<?php
// actions/verify.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';

$token = $_GET['token'] ?? '';

if ($token === '') {
    echo json_encode(['status' => 'error', 'message' => 'Token missing']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM gate_passes WHERE token = :t LIMIT 1");
$stmt->execute([':t' => $token]);
$pass = $stmt->fetch();

if (!$pass) {
    echo json_encode(['status' => 'invalid', 'message' => 'Gate pass not found']);
    exit;
}

// expired?
if (!empty($pass['expires_at']) && strtotime($pass['expires_at']) < time()) {
    $pdo->prepare("UPDATE gate_passes SET status = 'EXPIRED' WHERE id = :id")
        ->execute([':id' => $pass['id']]);

    echo json_encode(['status' => 'expired', 'message' => 'Gate pass expired']);
    exit;
}

// at this point: $pass exists and is not expired
try {
    $logIns = $pdo->prepare("
        INSERT INTO gate_logs (pass_id, scanned_at, gate_name) 
        VALUES (:id, NOW(), :gate)
    ");
    $logIns->execute([
        ':id'   => $pass['id'],
        ':gate' => 'Main Gate',
    ]);

    $logId  = $pdo->lastInsertId();
    $logRow = $pdo->prepare("SELECT scanned_at FROM gate_logs WHERE id = :lid");
    $logRow->execute([':lid' => $logId]);
    $log = $logRow->fetch();
} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'DB insert failed: ' . $e->getMessage(),
    ]);
    exit;
}

// mark as USED only first time
if ($pass['status'] !== 'USED') {
    $pdo->prepare("UPDATE gate_passes SET status = 'USED' WHERE id = :id")
        ->execute([':id' => $pass['id']]);
}

// Add today's date and fetch today's logs
$today = date('Y-m-d');

$logsStmt = $pdo->prepare("
    SELECT gp.student_name,
           gp.roll_no,
           gp.department,
           gp.purpose,
           gl.scanned_at
    FROM gate_logs gl
    JOIN gate_passes gp ON gl.pass_id = gp.id
    WHERE DATE(gl.scanned_at) = :today
    ORDER BY gl.scanned_at DESC
");
$logsStmt->execute([':today' => $today]);
$logs = $logsStmt->fetchAll();

echo json_encode([
    'status'       => 'valid',
    'message'      => 'Gate pass valid',
    'student_name' => $pass['student_name'],
    'institute_name'=> $pass['institute_name'],
    'roll_no'      => $pass['roll_no'],
    'department'   => $pass['department'],
    'year'         => $pass['year'],
    'mobile'       => $pass['mobile'],
    'purpose'      => $pass['purpose'],
    'photo_path'   => $pass['photo_path'],
    'expires_at'   => $pass['expires_at'],
    'scanned_at'   => $log['scanned_at'] ?? date('Y-m-d H:i:s'),
    'today_logs'   => $logs,
]);
