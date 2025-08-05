<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $program_id = $_GET['program_id'] ?? '';
    if (!$program_id) {
        echo json_encode(['success' => false, 'message' => 'Program ID required.']);
        exit;
    }
    $stmt = $conn->prepare('SELECT * FROM remarks WHERE program_id = ?');
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $remarks = [];
    while ($row = $result->fetch_assoc()) {
        $remarks[] = $row;
    }
    echo json_encode(['success' => true, 'remarks' => $remarks]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $program_id = $data['program_id'] ?? '';
    $remark = $data['remark'] ?? '';
    $created_by = $data['created_by'] ?? '';
    $user_role = $data['user_role'] ?? '';
    if (!$program_id || !$remark) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }
    $stmt = $conn->prepare('INSERT INTO remarks (program_id, remark, created_by, user_role) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isss', $program_id, $remark, $created_by, $user_role);
    if ($stmt->execute()) {
        // Get program details for notifications
        $program_stmt = $conn->prepare('SELECT program_name, created_by FROM programs WHERE id = ?');
        $program_stmt->bind_param('i', $program_id);
        $program_stmt->execute();
        $program_result = $program_stmt->get_result()->fetch_assoc();
        
        if ($program_result) {
            $program_name = $program_result['program_name'];
            $program_creator = $program_result['created_by'];
            
            // Get the user ID of the person who added the remark
            $remark_creator_stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ?");
            $remark_creator_stmt->bind_param('s', $created_by);
            $remark_creator_stmt->execute();
            $remark_creator_result = $remark_creator_stmt->get_result();
            $remark_creator_id = null;
            if ($remark_creator_row = $remark_creator_result->fetch_assoc()) {
                $remark_creator_id = $remark_creator_row['id'];
            }
            $remark_creator_stmt->close();
            
            // Notify users based on their role
            $all_users = $conn->query("SELECT id, role, full_name FROM users WHERE role IN ('admin', 'exco_user', 'finance_mmk', 'finance_officer', 'super_admin')");
            while ($user = $all_users->fetch_assoc()) {
                // Skip the user who added the remark
                if ($user['id'] != $remark_creator_id) {
                    // For EXCO users, only notify if they created the program
                    if ($user['role'] === 'exco_user') {
                        if ($user['id'] == $program_creator) {
                            $notification_stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, program_id) VALUES (?, ?, ?, ?, ?)');
                            $title = 'New Remark Added';
                            $message = "New remark added to your program '$program_name' by $created_by";
                            $type = 'remark_added';
                            $notification_stmt->bind_param('isssi', $user['id'], $title, $message, $type, $program_id);
                            $notification_stmt->execute();
                        }
                    } else {
                        // For other roles (admin, finance_mmk, finance_officer, super_admin), notify for all programs
                        $notification_stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, program_id) VALUES (?, ?, ?, ?, ?)');
                        $title = 'New Remark Added';
                        $message = "New remark added to program '$program_name' by $created_by";
                        $type = 'remark_added';
                        $notification_stmt->bind_param('isssi', $user['id'], $title, $message, $type, $program_id);
                        $notification_stmt->execute();
                    }
                }
            }
        }
        
        echo json_encode(['success' => true, 'remark_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding remark.']);
    }
    exit;
}
echo json_encode(['success' => false, 'message' => 'Invalid request method.']); 