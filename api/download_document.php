<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['id'] ?? '';
    if (!$id) {
        http_response_code(400);
        echo 'Document ID required.';
        exit;
    }
    $stmt = $conn->prepare('SELECT * FROM documents WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doc = $result->fetch_assoc();
    if ($doc) {
        $file_path = __DIR__ . '/uploads/' . $doc['filename'];
        if (file_exists($file_path)) {
            // Get the original filename and ensure it's properly encoded
            $original_filename = $doc['original_name'];
            
            // Set proper content type based on file extension
            $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
            $content_type = 'application/octet-stream';
            
            switch ($file_extension) {
                case 'jpg':
                case 'jpeg':
                    $content_type = 'image/jpeg';
                    break;
                case 'png':
                    $content_type = 'image/png';
                    break;
                case 'gif':
                    $content_type = 'image/gif';
                    break;
                case 'bmp':
                    $content_type = 'image/bmp';
                    break;
                case 'webp':
                    $content_type = 'image/webp';
                    break;
                case 'pdf':
                    $content_type = 'application/pdf';
                    break;
                case 'doc':
                    $content_type = 'application/msword';
                    break;
                case 'docx':
                    $content_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                    break;
                case 'xls':
                    $content_type = 'application/vnd.ms-excel';
                    break;
                case 'xlsx':
                    $content_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                    break;
            }
            
            // For all files, use attachment to force download
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: attachment; filename="' . $original_filename . '"; filename*=UTF-8\'\'' . rawurlencode($original_filename));
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            
            // Clear any output buffers to prevent corruption
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            readfile($file_path);
            exit;
        } else {
            http_response_code(404);
            echo 'File not found.';
        }
    } else {
        http_response_code(404);
        echo 'Document not found.';
    }
    exit;
}
http_response_code(405);
echo 'Invalid request method.'; 