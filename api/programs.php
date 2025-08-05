<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $exco_user_id = isset($_GET['exco_user_id']) ? $_GET['exco_user_id'] : null;
    if ($exco_user_id) {
        $stmt = $conn->prepare('SELECT * FROM programs WHERE created_by = ? ORDER BY created_at DESC');
        $stmt->bind_param('i', $exco_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query('SELECT * FROM programs ORDER BY created_at DESC');
    }
    $programs = [];
    while ($row = $result->fetch_assoc()) {
        // Get documents for this program
        $stmt2 = $conn->prepare('SELECT * FROM documents WHERE program_id = ?');
        $stmt2->bind_param('i', $row['id']);
        $stmt2->execute();
        $documents_result = $stmt2->get_result();
        $documents = [];
        while ($doc = $documents_result->fetch_assoc()) {
            $documents[] = $doc;
        }
        $row['documents'] = $documents;
        $stmt2->close();

        // Get remarks for this program
        $stmt3 = $conn->prepare('SELECT * FROM remarks WHERE program_id = ? ORDER BY created_at DESC');
        $stmt3->bind_param('i', $row['id']);
        $stmt3->execute();
        $remarks_result = $stmt3->get_result();
        $remarks = [];
        while ($remark = $remarks_result->fetch_assoc()) {
            $remarks[] = $remark;
        }
        $row['remarks'] = $remarks;
        $stmt3->close();

        // Get queries for this program
        $stmt4 = $conn->prepare('SELECT * FROM queries WHERE program_id = ? ORDER BY created_at DESC');
        $stmt4->bind_param('i', $row['id']);
        $stmt4->execute();
        $queries_result = $stmt4->get_result();
        $queries = [];
        while ($query = $queries_result->fetch_assoc()) {
            $queries[] = $query;
        }
        $row['queries'] = $queries;
        $stmt4->close();

        // Get status history for this program
        $stmt5 = $conn->prepare('
            SELECT 
                sh.id,
                sh.status,
                sh.changed_at,
                sh.remarks,
                u.full_name as changed_by_name,
                u.email as changed_by_email
            FROM status_history sh
            LEFT JOIN users u ON sh.changed_by = u.id
            WHERE sh.program_id = ?
            ORDER BY sh.changed_at ASC
        ');
        $stmt5->bind_param('i', $row['id']);
        $stmt5->execute();
        $status_history_result = $stmt5->get_result();
        $status_history = [];
        while ($history = $status_history_result->fetch_assoc()) {
            $status_history[] = $history;
        }
        $row['status_history'] = $status_history;
        $stmt5->close();

        $programs[] = $row;
    }
    echo json_encode(['success' => true, 'programs' => $programs]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $program_name = $data['program_name'] ?? '';
    $budget = $data['budget'] ?? '';
    $recipient_name = $data['recipient_name'] ?? '';
    $exco_letter_ref = $data['exco_letter_ref'] ?? '';
    $created_by = $data['created_by'] ?? '';
    $status = $data['status'] ?? 'draft';
    // Set timezone to Malaysia time (UTC+8)
    date_default_timezone_set('Asia/Kuala_Lumpur');
    $created_at = date('Y-m-d H:i:s');
    if (!$program_name || !$budget || !$recipient_name) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }
    $stmt = $conn->prepare('INSERT INTO programs (program_name, budget, recipient_name, exco_letter_ref, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sdsssis', $program_name, $budget, $recipient_name, $exco_letter_ref, $status, $created_by, $created_at);
    if ($stmt->execute()) {
        $program_id = $stmt->insert_id;
        
        // Create notifications for different user roles
        $admin_users = $conn->query("SELECT id FROM users WHERE role = 'admin'");
        while ($admin = $admin_users->fetch_assoc()) {
            $notification_stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, program_id) VALUES (?, ?, ?, ?, ?)');
            $title = 'New Program Created';
            $message = "Program '$program_name' has been created by $created_by";
            $type = 'program_created';
            $notification_stmt->bind_param('isssi', $admin['id'], $title, $message, $type, $program_id);
            $notification_stmt->execute();
        }
        
        // EXCO users will NOT receive notifications when programs are created
        // (This section has been removed - no notifications for EXCO users)
        
        // Notify Finance MMK users if status is under_review
        if ($status === 'under_review') {
            $finance_users = $conn->query("SELECT id FROM users WHERE role IN ('finance_mmk', 'finance_officer', 'super_admin')");
            while ($finance = $finance_users->fetch_assoc()) {
                $notification_stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, program_id) VALUES (?, ?, ?, ?, ?)');
                $title = 'New Program Under Review';
                $message = "Program '$program_name' is now under review";
                $type = 'status_change';
                $notification_stmt->bind_param('isssi', $finance['id'], $title, $message, $type, $program_id);
                $notification_stmt->execute();
            }
        }
        
        echo json_encode(['success' => true, 'program_id' => $program_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error creating program.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']); 