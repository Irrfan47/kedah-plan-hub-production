<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $program_id = $_GET['program_id'] ?? null;
    
    if ($program_id) {
        // Get queries for a specific program
        $stmt = $conn->prepare('SELECT * FROM queries WHERE program_id = ? ORDER BY created_at DESC');
        $stmt->bind_param('i', $program_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $queries = [];
        while ($row = $result->fetch_assoc()) {
            $queries[] = $row;
        }
        echo json_encode(['success' => true, 'queries' => $queries]);
    } else {
        // Get all queries
        $result = $conn->query('SELECT * FROM queries ORDER BY created_at DESC');
        $queries = [];
        while ($row = $result->fetch_assoc()) {
            $queries[] = $row;
        }
        echo json_encode(['success' => true, 'queries' => $queries]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $program_id = $data['program_id'] ?? '';
    $question = $data['question'] ?? '';
    $created_by = $data['created_by'] ?? '';
    
    if (!$program_id || !$question || !$created_by) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }
    
    // Insert the query
    $stmt = $conn->prepare('INSERT INTO queries (program_id, question, created_by) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $program_id, $question, $created_by);
    
    if ($stmt->execute()) {
        $query_id = $stmt->insert_id;
        
        // Update program status to 'query'
        $update_stmt = $conn->prepare('UPDATE programs SET status = ? WHERE id = ?');
        $status = 'query';
        $update_stmt->bind_param('si', $status, $program_id);
        $update_stmt->execute();
        
        // Get program name for notification
        $program_stmt = $conn->prepare('SELECT program_name, created_by FROM programs WHERE id = ?');
        $program_stmt->bind_param('i', $program_id);
        $program_stmt->execute();
        $program_result = $program_stmt->get_result()->fetch_assoc();
        $program_name = $program_result['program_name'];
        $program_creator = $program_result['created_by'];
        
        // Create notifications for the specific EXCO user who created the program
        // But only if the query creator is different from the program creator
        $creator_user = $conn->query("SELECT id FROM users WHERE full_name = '$program_creator' AND role = 'exco_user'");
        if ($creator = $creator_user->fetch_assoc()) {
            // Get the user ID of the person who created the query
            $query_creator_stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ?");
            $query_creator_stmt->bind_param('s', $created_by);
            $query_creator_stmt->execute();
            $query_creator_result = $query_creator_stmt->get_result();
            $query_creator_id = null;
            if ($query_creator_row = $query_creator_result->fetch_assoc()) {
                $query_creator_id = $query_creator_row['id'];
            }
            $query_creator_stmt->close();
            
            // Only notify if the query creator is different from the program creator
            if ($creator['id'] != $query_creator_id) {
                $notification_stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, program_id) VALUES (?, ?, ?, ?, ?)');
                $title = 'New Query from Finance MMK';
                $message = "Finance MMK has submitted a query for program '$program_name'";
                $type = 'query';
                $notification_stmt->bind_param('isssi', $creator['id'], $title, $message, $type, $program_id);
                $notification_stmt->execute();
            }
        }
        
        echo json_encode(['success' => true, 'query_id' => $query_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create query.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $query_id = $data['query_id'] ?? '';
    $answer = $data['answer'] ?? '';
    $answered_by = $data['answered_by'] ?? '';
    
    if (!$query_id || !$answer || !$answered_by) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }
    
    // Update the query with answer
    $stmt = $conn->prepare('UPDATE queries SET answer = ?, answered = 1 WHERE id = ?');
    $stmt->bind_param('si', $answer, $query_id);
    
    if ($stmt->execute()) {
        // Get the program_id for this query
        $get_program_stmt = $conn->prepare('SELECT program_id FROM queries WHERE id = ?');
        $get_program_stmt->bind_param('i', $query_id);
        $get_program_stmt->execute();
        $program_result = $get_program_stmt->get_result();
        $program_row = $program_result->fetch_assoc();
        $program_id = $program_row['program_id'];
        
        // Update program status to 'query_answered'
        $update_stmt = $conn->prepare('UPDATE programs SET status = ? WHERE id = ?');
        $status = 'query_answered';
        $update_stmt->bind_param('si', $status, $program_id);
        $update_stmt->execute();
        
        // Get program name for notification
        $program_stmt = $conn->prepare('SELECT program_name FROM programs WHERE id = ?');
        $program_stmt->bind_param('i', $program_id);
        $program_stmt->execute();
        $program_result = $program_stmt->get_result()->fetch_assoc();
        $program_name = $program_result['program_name'];
        
        // Create notifications for Finance MMK users only, except the one who answered
        // Get the user ID of the person who answered the query
        $answer_creator_stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ?");
        $answer_creator_stmt->bind_param('s', $answered_by);
        $answer_creator_stmt->execute();
        $answer_creator_result = $answer_creator_stmt->get_result();
        $answer_creator_id = null;
        if ($answer_creator_row = $answer_creator_result->fetch_assoc()) {
            $answer_creator_id = $answer_creator_row['id'];
        }
        $answer_creator_stmt->close();
        
        $finance_mmk_users = $conn->query("SELECT id FROM users WHERE role = 'finance_mmk'");
        while ($finance_mmk = $finance_mmk_users->fetch_assoc()) {
            // Skip the user who answered the query
            if ($finance_mmk['id'] != $answer_creator_id) {
                $notification_stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, program_id) VALUES (?, ?, ?, ?, ?)');
                $title = 'Query Answered';
                $message = "EXCO USER has answered a query for program '$program_name'";
                $type = 'query_answered';
                $notification_stmt->bind_param('isssi', $finance_mmk['id'], $title, $message, $type, $program_id);
                $notification_stmt->execute();
            }
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to answer query.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']); 