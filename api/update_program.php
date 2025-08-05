<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Debug logging
        error_log('Update program request data: ' . json_encode($data));
        
        $id = $data['id'] ?? '';
        $program_name = $data['program_name'] ?? '';
        $budget = $data['budget'] ?? '';
        $recipient_name = $data['recipient_name'] ?? '';
        $exco_letter_ref = $data['exco_letter_ref'] ?? '';
        
        error_log('Parsed values - ID: ' . $id . ', Name: ' . $program_name . ', Budget: ' . $budget);
        
        if (!$id || !$program_name || !$budget || !$recipient_name || !$exco_letter_ref) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }
        
        // First check if the program exists
        $checkStmt = $conn->prepare('SELECT id, program_name, budget, recipient_name, exco_letter_ref FROM programs WHERE id = ?');
        $checkStmt->bind_param('i', $id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Program not found with ID: ' . $id]);
            $checkStmt->close();
            exit;
        }
        
        $existingProgram = $result->fetch_assoc();
        error_log('Existing program data: ' . json_encode($existingProgram));
        error_log('New program data: ' . json_encode($data));
        
        // Check if there are any actual changes
        $hasChanges = false;
        if ($existingProgram['program_name'] !== $program_name) $hasChanges = true;
        if (floatval($existingProgram['budget']) != floatval($budget)) $hasChanges = true;
        if ($existingProgram['recipient_name'] !== $recipient_name) $hasChanges = true;
        if ($existingProgram['exco_letter_ref'] !== $exco_letter_ref) $hasChanges = true;
        
        error_log('Change detection - program_name: ' . ($existingProgram['program_name'] !== $program_name ? 'CHANGED' : 'SAME'));
        error_log('Change detection - budget: ' . (floatval($existingProgram['budget']) != floatval($budget) ? 'CHANGED' : 'SAME'));
        error_log('Change detection - recipient_name: ' . ($existingProgram['recipient_name'] !== $recipient_name ? 'CHANGED' : 'SAME'));
        error_log('Change detection - exco_letter_ref: ' . ($existingProgram['exco_letter_ref'] !== $exco_letter_ref ? 'CHANGED' : 'SAME'));
        
        error_log('Final change detection result: ' . ($hasChanges ? 'CHANGES DETECTED' : 'NO CHANGES'));
        
        // Note: We'll allow the update to proceed even if no field changes are detected
        // because the frontend might be uploading new documents
        if (!$hasChanges) {
            error_log('No field changes detected, but allowing update to proceed for potential document uploads');
        }
        
        $checkStmt->close();
        
        // Update program
        $stmt = $conn->prepare('UPDATE programs SET program_name = ?, budget = ?, recipient_name = ?, exco_letter_ref = ? WHERE id = ?');
        $stmt->bind_param('sdssi', $program_name, $budget, $recipient_name, $exco_letter_ref, $id);
        
        if ($stmt->execute()) {
            error_log('SQL executed successfully. Affected rows: ' . $stmt->affected_rows);
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Program updated successfully.']);
            } else {
                // Even if no rows were affected (no field changes), we still return success
                // because the frontend might be uploading documents
                echo json_encode(['success' => true, 'message' => 'Program update completed.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating program: ' . $conn->error]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        error_log('Update program exception: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
?> 