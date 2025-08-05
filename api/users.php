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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['exco_stats'])) {
    try {
        // Get all exco_users with their budgets and program stats
        $users = [];
        $result = $conn->query("SELECT id, full_name, total_budget FROM users WHERE role = 'exco_user'");
        if (!$result) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        
        while ($user = $result->fetch_assoc()) {
            $user_id = $user['id'];
            // Total programs
            $totalPrograms = $conn->query("SELECT COUNT(*) as cnt FROM programs WHERE created_by = '$user_id'")->fetch_assoc()['cnt'];
            // Pending programs
            $pendingPrograms = $conn->query("SELECT COUNT(*) as cnt FROM programs WHERE created_by = '$user_id' AND status NOT IN ('payment_completed','rejected')")->fetch_assoc()['cnt'];
            // Payment completed programs budget
            $approvedBudget = $conn->query("SELECT SUM(budget) as sum FROM programs WHERE created_by = '$user_id' AND status = 'payment_completed'")->fetch_assoc()['sum'] ?? 0;
            $approvedBudget = $approvedBudget ? floatval($approvedBudget) : 0;
            // Remaining budget
            $remainingBudget = floatval($user['total_budget']) - $approvedBudget;
            $users[] = [
                'id' => $user_id,
                'name' => $user['full_name'],
                'total_budget' => floatval($user['total_budget']),
                'remaining_budget' => $remainingBudget,
                'total_programs' => intval($totalPrograms),
                'pending_programs' => intval($pendingPrograms),
            ];
        }
        echo json_encode(['success' => true, 'users' => $users]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching EXCO stats: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query('SELECT id, full_name, email, phone_number, role, is_active, created_at, last_login FROM users');
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    echo json_encode(['success' => true, 'users' => $users]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['set_budget'])) {
            // Only finance_mmk can set budget (finance_officer and super_admin are view-only)
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? null;
    $total_budget = $data['total_budget'] ?? null;
    if (!$user_id || $total_budget === null) {
        echo json_encode(['success' => false, 'message' => 'User ID and total_budget required.']);
        exit;
    }
    $stmt = $conn->prepare('UPDATE users SET total_budget = ? WHERE id = ? AND role = "exco_user"');
    $stmt->bind_param('di', $total_budget, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update budget.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $full_name = $data['full_name'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $phone_number = $data['phone_number'] ?? '';
    $role = $data['role'] ?? '';
    if (!$full_name || !$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit;
    }
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO users (full_name, email, password, phone_number, role) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $full_name, $email, $hashed_password, $phone_number, $role);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'user_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error creating user.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']); 