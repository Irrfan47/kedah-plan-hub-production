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
    try {
        // Get budget from budget table
        $result = $conn->query('SELECT * FROM budget LIMIT 1');
        $budgetRow = $result->fetch_assoc();
        
        $totalBudget = $budgetRow ? (float)$budgetRow['total_budget'] : 0;
        
        // Calculate used budget from payment completed programs
        $stmt = $conn->prepare("SELECT SUM(budget) as used_budget FROM programs WHERE status = 'payment_completed'");
        $stmt->execute();
        $usedBudgetResult = $stmt->get_result()->fetch_assoc();
        $usedBudget = $usedBudgetResult['used_budget'] ? (float)$usedBudgetResult['used_budget'] : 0;
        
        $budget = [
            'total_budget' => $totalBudget,
            'used_budget' => $usedBudget,
            'remaining_budget' => $totalBudget - $usedBudget
        ];
        
        echo json_encode(['success' => true, 'budget' => $budget]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching budget: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $total_budget = $data['total_budget'] ?? '';
        
        if ($total_budget === '') {
            echo json_encode(['success' => false, 'message' => 'Total budget required.']);
            exit;
        }
        
        $result = $conn->query('SELECT id FROM budget LIMIT 1');
        if ($row = $result->fetch_assoc()) {
            $stmt = $conn->prepare('UPDATE budget SET total_budget = ? WHERE id = ?');
            $stmt->bind_param('di', $total_budget, $row['id']);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare('INSERT INTO budget (total_budget) VALUES (?)');
            $stmt->bind_param('d', $total_budget);
            $stmt->execute();
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating budget: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
?> 