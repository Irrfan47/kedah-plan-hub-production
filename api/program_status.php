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



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $program_id = $data['program_id'] ?? '';
    $status = $data['status'] ?? '';
    $voucher_number = $data['voucher_number'] ?? null;
    $eft_number = $data['eft_number'] ?? null;
    
    if (!$program_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Program ID and status are required.']);
        exit;
    }
    
    // Validate status
    $valid_statuses = ['draft', 'under_review', 'query', 'query_answered', 'complete_can_send_to_mmk', 'under_review_by_mmk', 'document_accepted_by_mmk', 'payment_in_progress', 'payment_completed', 'rejected'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        exit;
    }
    
    // Update program status
    if ($status === 'payment_completed' && ($voucher_number || $eft_number)) {
        // For payment_completed status, update with voucher and EFT numbers
        $stmt = $conn->prepare('UPDATE programs SET status = ?, voucher_number = ?, eft_number = ? WHERE id = ?');
        $stmt->bind_param('sssi', $status, $voucher_number, $eft_number, $program_id);
    } else {
        // For other statuses, just update the status
        $stmt = $conn->prepare('UPDATE programs SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $program_id);
    }
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Get program details first
            $program_stmt = $conn->prepare('SELECT program_name, created_by FROM programs WHERE id = ?');
            $program_stmt->bind_param('i', $program_id);
            $program_stmt->execute();
            $program_result = $program_stmt->get_result()->fetch_assoc();
            
            // Insert into status_history table
            $history_stmt = $conn->prepare('INSERT INTO status_history (program_id, status, changed_by, changed_at, remarks) VALUES (?, ?, ?, NOW(), ?)');
            $changed_by = $data['changed_by'] ?? $program_result['created_by'] ?? 1; // Default to user ID 1 if not provided
            $remarks = "Status changed to $status";
            $history_stmt->bind_param('isis', $program_id, $status, $changed_by, $remarks);
            $history_stmt->execute();
            $history_stmt->close();
            
            if ($program_result) {
                $program_name = $program_result['program_name'];
                $created_by = $program_result['created_by'];
                
                // Always notify the program creator for any status change
                if ($created_by) {
                    $notification_stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, program_id) VALUES (?, ?, ?, ?, ?)');
                    $title = 'Program Status Changed';
                    $message = "Your program '$program_name' status changed to " . ucwords(str_replace('_', ' ', $status));
                    $type = 'status_change';
                    $notification_stmt->bind_param('isssi', $created_by, $title, $message, $type, $program_id);
                    $notification_stmt->execute();
                }
                
                // Create notifications based on status change
                if ($status === 'under_review') {
                    // Notify Finance MMK users
                    $finance_users = $conn->query("SELECT id FROM users WHERE role IN ('finance_mmk', 'finance_officer', 'super_admin')");
                    while ($finance = $finance_users->fetch_assoc()) {
                        $notification_stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, program_id) VALUES (?, ?, ?, ?, ?)');
                        $title = 'Program Under Review';
                        $message = "Program '$program_name' is now under review";
                        $type = 'status_change';
                        $notification_stmt->bind_param('isssi', $finance['id'], $title, $message, $type, $program_id);
                        $notification_stmt->execute();
                    }
                } elseif ($status === 'payment_completed') {
                    // Notify Finance Officer and Super Admin
                    $finance_officer_users = $conn->query("SELECT id FROM users WHERE role = 'finance_officer'");
                    while ($finance_officer = $finance_officer_users->fetch_assoc()) {
                        $notification_stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, program_id) VALUES (?, ?, ?, ?, ?)');
                        $title = 'Payment Completed';
                        $message = "Program '$program_name' payment has been completed";
                        $type = 'status_change';
                        $notification_stmt->bind_param('isssi', $finance_officer['id'], $title, $message, $type, $program_id);
                        $notification_stmt->execute();
                    }
                    
                    $super_admin_users = $conn->query("SELECT id FROM users WHERE role = 'super_admin'");
                    while ($super_admin = $super_admin_users->fetch_assoc()) {
                        $notification_stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, program_id) VALUES (?, ?, ?, ?, ?)');
                        $title = 'Payment Completed';
                        $message = "Program '$program_name' payment has been completed";
                        $type = 'status_change';
                        $notification_stmt->bind_param('isssi', $super_admin['id'], $title, $message, $type, $program_id);
                        $notification_stmt->execute();
                    }
                    
                    // Notify Admin users
                    $admin_users = $conn->query("SELECT id FROM users WHERE role = 'admin'");
                    while ($admin = $admin_users->fetch_assoc()) {
                        $notification_stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, program_id) VALUES (?, ?, ?, ?, ?)');
                        $title = 'Payment Completed';
                        $message = "Program '$program_name' payment has been completed";
                        $type = 'status_change';
                        $notification_stmt->bind_param('isssi', $admin['id'], $title, $message, $type, $program_id);
                        $notification_stmt->execute();
                    }
                } elseif ($status === 'document_accepted_by_mmk') {
                    // No need to notify program creator here - already done in general block
                } elseif ($status === 'under_review_by_mmk') {
                    // No need to notify program creator here - already done in general block
                } elseif ($status === 'rejected') {
                    // No need to notify program creator here - already done in general block
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Program status updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Program not found or no changes made.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating program status: ' . $conn->error]);
    }
    
    $stmt->close();
    exit;
}
echo json_encode(['success' => false, 'message' => 'Invalid request method.']); 