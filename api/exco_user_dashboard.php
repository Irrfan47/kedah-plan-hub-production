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
    $user_id = $_GET['user_id'] ?? '';
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required.']);
        exit;
    }
    
    try {
        // Get user's total budget
        $stmt = $conn->prepare('SELECT total_budget FROM users WHERE id = ? AND role = "exco_user"');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'EXCO user not found.']);
            exit;
        }
        
        $total_budget = floatval($user['total_budget']);
        
        // Get programs for this specific user - last 5 programs by ID (high to low)
        // Get the user's full name first, then find programs by that name
        $user_stmt = $conn->prepare('SELECT full_name FROM users WHERE id = ?');
        $user_stmt->bind_param('i', $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();
        $user_name = null;
        if ($user_row = $user_result->fetch_assoc()) {
            $user_name = $user_row['full_name'];
        }
        $user_stmt->close();
        
        if ($user_name) {
            $stmt = $conn->prepare('SELECT * FROM programs WHERE created_by = ? ORDER BY id DESC LIMIT 5');
            $stmt->bind_param('s', $user_name);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query('SELECT * FROM programs ORDER BY id DESC LIMIT 5');
        }
        $programs = [];
        while ($row = $result->fetch_assoc()) {
            $programs[] = $row;
        }
        
        // Calculate statistics
        $total_programs = count($programs);
        $payment_completed_programs = 0;
        $rejected_programs = 0;
        $pending_programs = 0;
        $payment_completed_budget = 0;
        
        foreach ($programs as $program) {
            if ($program['status'] === 'payment_completed') {
                $payment_completed_programs++;
                $payment_completed_budget += floatval($program['budget']);
            } elseif ($program['status'] === 'rejected') {
                $rejected_programs++;
            } else {
                $pending_programs++;
            }
        }
        
        $remaining_budget = $total_budget - $payment_completed_budget;
        
        $dashboard_data = [
            'total_programs' => $total_programs,
            'payment_completed_programs' => $payment_completed_programs,
            'rejected_programs' => $rejected_programs,
            'pending_programs' => $pending_programs,
            'total_budget' => $total_budget,
            'remaining_budget' => $remaining_budget,
            'recent_programs' => $programs
        ];
        
        echo json_encode(['success' => true, 'data' => $dashboard_data]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? '';
    $new_budget = $data['total_budget'] ?? '';
    
    // Debug logging
    error_log("POST request received - user_id: $user_id, new_budget: $new_budget");
    
    if (!$user_id || $new_budget === '') {
        echo json_encode(['success' => false, 'message' => 'User ID and budget are required.']);
        exit;
    }
    
    try {
        // Convert to float to ensure proper type
        $new_budget = floatval($new_budget);
        $user_id = intval($user_id);
        
        error_log("Converted values - user_id: $user_id, new_budget: $new_budget");
        
        // First check if user exists
        $check_stmt = $conn->prepare('SELECT id FROM users WHERE id = ? AND role = "exco_user"');
        $check_stmt->bind_param('i', $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'EXCO user not found with ID: ' . $user_id]);
            exit;
        }
        
        // Use the same approach that works in debug_user.php
        $stmt = $conn->prepare('UPDATE users SET total_budget = ? WHERE id = ?');
        $stmt->bind_param('di', $new_budget, $user_id);
        
        error_log("Executing UPDATE query...");
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            error_log("Update successful - affected rows: $affected_rows");
            echo json_encode(['success' => true, 'message' => 'Budget updated successfully.']);
        } else {
            $error = $stmt->error;
            error_log("Update failed - MySQL error: $error");
            echo json_encode(['success' => false, 'message' => 'Failed to update budget: ' . $error]);
        }
    } catch (Exception $e) {
        error_log("Exception caught: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
?> 