<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

// Add error logging
error_log("Profile picture upload request received. Method: " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = file_get_contents('php://input');
        error_log("Raw input received: " . substr($input, 0, 100) . "...");
        
        $data = json_decode($input, true);
        error_log("Decoded data keys: " . implode(', ', array_keys($data ?? [])));
        
        $user_id = $data['user_id'] ?? '';
        $original_image = $data['original_image'] ?? ''; // Base64 encoded original image
        $cropped_image = $data['cropped_image'] ?? ''; // Base64 encoded cropped image
        
        error_log("User ID: " . $user_id);
        error_log("Original image data length: " . strlen($original_image));
        error_log("Cropped image data length: " . strlen($cropped_image));
        
        if (!$user_id || !$original_image || !$cropped_image) {
            error_log("Missing required data. User ID: " . ($user_id ? 'present' : 'missing') . ", Original image: " . ($original_image ? 'present' : 'missing') . ", Cropped image: " . ($cropped_image ? 'present' : 'missing'));
            echo json_encode(['success' => false, 'message' => 'User ID, original image, and cropped image are required.']);
            exit;
        }
        
        // Decode base64 images
        $original_image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $original_image));
        $cropped_image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $cropped_image));
        
        error_log("Decoded original image data length: " . strlen($original_image_data));
        error_log("Decoded cropped image data length: " . strlen($cropped_image_data));
        
        // Update user's profile pictures - save both original and cropped
        $stmt = $conn->prepare('UPDATE users SET profile_picture = ?, cropped_profile_picture = ? WHERE id = ?');
        $stmt->bind_param('ssi', $original_image_data, $cropped_image_data, $user_id);
        
        if ($stmt->execute()) {
            error_log("Profile pictures updated successfully for user ID: " . $user_id);
            echo json_encode(['success' => true, 'message' => 'Profile picture updated successfully.']);
        } else {
            error_log("Database error: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Error updating profile picture.']);
        }
        
    } catch (Exception $e) {
        error_log("Exception in profile picture upload: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
?> 