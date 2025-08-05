<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

// Get notifications for a user
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = $_GET['user_id'] ?? '';
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required.']);
        exit;
    }

    try {
        // Get user role first
        $stmt = $conn->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $userResult = $stmt->get_result()->fetch_assoc();
        
        if (!$userResult) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        $userRole = $userResult['role'];
        
        // Get notifications based on user role
        $query = '';
        $params = [];
        $types = '';

        if ($userRole === 'admin') {
            // Admin sees all notifications with proper user names
            $query = 'SELECT n.*, p.program_name, u.full_name as program_creator_name 
                     FROM notifications n 
                     LEFT JOIN programs p ON n.program_id = p.id 
                     LEFT JOIN users u ON p.created_by = u.id 
                     WHERE n.user_id = ? 
                     ORDER BY n.created_at DESC';
            $params = [$user_id];
            $types = 'i';
        } else {
            // Other roles see notifications for their role
            $query = 'SELECT n.*, p.program_name, u.full_name as program_creator_name 
                     FROM notifications n 
                     LEFT JOIN programs p ON n.program_id = p.id 
                     LEFT JOIN users u ON p.created_by = u.id 
                     WHERE n.user_id = ? 
                     ORDER BY n.created_at DESC';
            $params = [$user_id];
            $types = 'i';
        }

        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }

        echo json_encode(['success' => true, 'notifications' => $notifications]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching notifications: ' . $e->getMessage()]);
    }
    exit;
}

// Mark notification as read
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $notification_id = $data['notification_id'] ?? '';
    $user_id = $data['user_id'] ?? '';
    $mark_all = $data['mark_all'] ?? false;

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required.']);
        exit;
    }

    try {
        if ($mark_all) {
            // Mark all notifications as read for the user
            $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
            $stmt->bind_param('i', $user_id);
        } else {
            // Mark specific notification as read
            if (!$notification_id) {
                echo json_encode(['success' => false, 'message' => 'Notification ID required.']);
                exit;
            }
            $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $notification_id, $user_id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Notification(s) marked as read.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating notification.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating notification: ' . $e->getMessage()]);
    }
    exit;
}

// Delete notification
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $notification_id = $data['notification_id'] ?? '';
    $user_id = $data['user_id'] ?? '';
    $delete_all = $data['delete_all'] ?? false;

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required.']);
        exit;
    }

    try {
        if ($delete_all) {
            // Delete all notifications for the user
            $stmt = $conn->prepare('DELETE FROM notifications WHERE user_id = ?');
            $stmt->bind_param('i', $user_id);
        } else {
            // Delete specific notification
            if (!$notification_id) {
                echo json_encode(['success' => false, 'message' => 'Notification ID required.']);
                exit;
            }
            $stmt = $conn->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
            $stmt->bind_param('ii', $notification_id, $user_id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Notification(s) deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting notification.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting notification: ' . $e->getMessage()]);
    }
    exit;
}

// Create notification (internal use)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? '';
    $title = $data['title'] ?? '';
    $message = $data['message'] ?? '';
    $type = $data['type'] ?? 'info';
    $program_id = $data['program_id'] ?? null;

    if (!$user_id || !$title || !$message) {
        echo json_encode(['success' => false, 'message' => 'User ID, title, and message required.']);
        exit;
    }

    try {
        $stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message, type, program_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('isssi', $user_id, $title, $message, $type, $program_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'notification_id' => $stmt->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error creating notification.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error creating notification: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
?> 