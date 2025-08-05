<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $program_id = $data['program_id'] ?? '';
    
    if (!$program_id) {
        echo json_encode(['success' => false, 'message' => 'Program ID required.']);
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First, get all documents for this program
        $stmt = $conn->prepare('SELECT * FROM documents WHERE program_id = ?');
        $stmt->bind_param('i', $program_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $documents = [];
        while ($row = $result->fetch_assoc()) {
            $documents[] = $row;
        }
        
        // Delete physical files
        foreach ($documents as $doc) {
            $file_path = __DIR__ . '/uploads/' . $doc['filename'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete documents from database
        $stmt = $conn->prepare('DELETE FROM documents WHERE program_id = ?');
        $stmt->bind_param('i', $program_id);
        $stmt->execute();
        
        // Delete remarks from database
        $stmt = $conn->prepare('DELETE FROM remarks WHERE program_id = ?');
        $stmt->bind_param('i', $program_id);
        $stmt->execute();
        
        // Delete queries from database
        $stmt = $conn->prepare('DELETE FROM queries WHERE program_id = ?');
        $stmt->bind_param('i', $program_id);
        $stmt->execute();
        
        // Finally, delete the program
        $stmt = $conn->prepare('DELETE FROM programs WHERE id = ?');
        $stmt->bind_param('i', $program_id);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Commit transaction
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Program deleted successfully']);
        } else {
            // Rollback transaction
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Program not found']);
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error deleting program: ' . $e->getMessage()]);
    }
    
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']); 