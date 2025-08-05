<?php
// Set timezone to Malaysia time (UTC+8)
date_default_timezone_set('Asia/Kuala_Lumpur');

// Database configuration
// For hosted deployment, you need to update these values with your hosting provider's database credentials
$host = 'localhost'; // Usually 'localhost' for shared hosting
$db   = 'kedah-plan-hub'; // Replace with your actual database name
$user = 'root'; // Replace with your actual database username  
$pass = ''; // Replace with your actual database password

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    // For production, don't expose connection details
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Set charset to utf8
$conn->set_charset("utf8");
?> 