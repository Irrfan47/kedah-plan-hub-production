<?php
// Prevent any output before headers
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'config.php';

try {
    // Get raw POST data
    $raw_data = file_get_contents('php://input');
    error_log("Raw POST data: " . $raw_data);
    error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
    
    // Initialize data array
    $data = [];
    
    // Try to decode as JSON first
    if (!empty($raw_data)) {
        $data = json_decode($raw_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            $data = [];
        }
    }
    
    // If JSON failed or empty, try $_POST
    if (empty($data) && !empty($_POST)) {
        error_log("Using $_POST data instead");
        $data = $_POST;
    }
    
    error_log("Final data array: " . print_r($data, true));
    
    // Extract email and password
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    error_log("Extracted - Email: '$email', Password length: " . strlen($password));

    if (empty($email) || empty($password)) {
        error_log("Missing email or password");
        echo json_encode(['success' => false, 'message' => 'Email and password required.']);
        exit;
    }

    // Log the login attempt
    error_log("Login attempt for email: " . $email);

    // Prepare and execute query
    $stmt = $conn->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param('s', $email);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        error_log("User found: " . $user['email'] . " with role: " . $user['role']);
        
        if (password_verify($password, $user['password'])) {
            // Update last login time
            $update_stmt = $conn->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
            $update_stmt->bind_param('i', $user['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            unset($user['password']); // Remove password from response
            
            // Convert cropped profile picture to base64 for frontend
            if ($user['cropped_profile_picture']) {
                $user['profilePhoto'] = 'data:image/jpeg;base64,' . base64_encode($user['cropped_profile_picture']);
            }
            
            // Remove the binary data to avoid JSON encoding issues
            unset($user['profile_picture']);
            unset($user['cropped_profile_picture']);
            
            $response = ['success' => true, 'user' => $user];
            error_log("Login successful for: " . $email);
        } else {
            error_log("Password verification failed for: " . $email);
            $response = ['success' => false, 'message' => 'Invalid credentials.'];
        }
    } else {
        error_log("No user found for email: " . $email);
        $response = ['success' => false, 'message' => 'Invalid credentials.'];
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Unexpected error occurred.'];
}

$conn->close();

// Clear any output buffer and send response
ob_end_clean();
echo json_encode($response);
?> 