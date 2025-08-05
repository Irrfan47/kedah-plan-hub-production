<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

// Get parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$userId = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$statusFilter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'both';
$download = isset($_GET['download']) ? $_GET['download'] : null;

try {
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    $types = '';

    // Date range filter
    if ($startDate && $endDate) {
        $whereConditions[] = "created_at BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= 'ss';
    }

    // Status filter based on status_filter parameter
    if ($statusFilter === 'approved') {
        $whereConditions[] = "status = 'payment_completed'";
    } elseif ($statusFilter === 'rejected') {
        $whereConditions[] = "status = 'rejected'";
    } else {
        // Default: both approved and rejected
        $whereConditions[] = "status IN ('payment_completed', 'rejected')";
    }

    // User filter
    if ($userId && $userId !== 'all') {
        $whereConditions[] = "CAST(created_by AS CHAR) = ?";
        $params[] = $userId;
        $types .= 's';
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get programs
    $stmt = $conn->prepare("
        SELECT id, program_name, budget, recipient_name, exco_letter_ref, status, created_at, created_by, voucher_number, eft_number 
        FROM programs 
        $whereClause 
        ORDER BY created_by, created_at DESC
    ");

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $programs = [];
    
    while ($row = $result->fetch_assoc()) {
        $programs[] = [
            'id' => $row['id'],
            'program_name' => $row['program_name'],
            'budget' => (float)$row['budget'],
            'recipient_name' => $row['recipient_name'],
            'exco_letter_ref' => $row['exco_letter_ref'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'created_by' => $row['created_by'],
            'voucher_number' => $row['voucher_number'],
            'eft_number' => $row['eft_number']
        ];
    }

    // If download is requested, generate PDF
    if ($download === '1') {
        generatePDF($programs, $startDate, $endDate, $userId, $statusFilter);
        exit();
    }

    echo json_encode([
        'success' => true,
        'programs' => $programs
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating report: ' . $e->getMessage()
    ]);
}

$conn->close();

function generatePDF($programs, $startDate, $endDate, $userId, $statusFilter) {
    try {
        // Set proper headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="program_report.pdf"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Use FPDF for PDF generation
        require_once('fpdf/fpdf.php');
    
    // Create new PDF document
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('Arial', 'B', 16);
    
    // Add logo if exists (centered)
    $logoPath = '../img/logo.png';
    if (file_exists($logoPath)) {
        // Get page width and logo width to center it
        $pageWidth = $pdf->GetPageWidth();
        $logoWidth = 30; // Logo width in mm
        $logoX = ($pageWidth - $logoWidth) / 2; // Center position
        $pdf->Image($logoPath, $logoX, 10, $logoWidth);
        $pdf->Ln(35); // Space after logo
    }
    
    // Add title
    $pdf->Cell(0, 10, 'SISTEM PENGURUSAN PERUNTUKAN EXCO', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Add subtitle based on status filter
    $pdf->SetFont('Arial', '', 12);
    $statusText = '';
    if ($statusFilter === 'approved') {
        $statusText = 'approved status only';
    } elseif ($statusFilter === 'rejected') {
        $statusText = 'rejected status only';
    } else {
        $statusText = 'approved & rejected status';
    }
    $pdf->Cell(0, 8, 'Program Report - This report contains programs with ' . $statusText, 0, 1, 'C');
    $pdf->Cell(0, 8, 'Report Period: ' . date('d/m/Y', strtotime($startDate)) . ' to ' . date('d/m/Y', strtotime($endDate)), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Group programs by user
    $groupedPrograms = [];
    foreach ($programs as $program) {
        $userName = getUserName($program['created_by']);
        if (!isset($groupedPrograms[$userName])) {
            $groupedPrograms[$userName] = [];
        }
        $groupedPrograms[$userName][] = $program;
    }
    
    $totalBudget = 0;
    $totalPrograms = 0;
    
    foreach ($groupedPrograms as $userName => $userPrograms) {
        // Add user section title
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'Programs for ' . $userName, 0, 1, 'L');
        $pdf->Ln(5);
        
        // Create table header
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(240, 240, 240);
        
        // Calculate column widths - adjusted for voucher and EFT numbers
        $colWidths = [35, 20, 25, 20, 20, 20, 20, 20];
        $pdf->Cell($colWidths[0], 8, 'Program Name', 1, 0, 'L', true);
        $pdf->Cell($colWidths[1], 8, 'Budget', 1, 0, 'R', true);
        $pdf->Cell($colWidths[2], 8, 'Recipient', 1, 0, 'L', true);
        $pdf->Cell($colWidths[3], 8, 'Reference', 1, 0, 'L', true);
        $pdf->Cell($colWidths[4], 8, 'Voucher', 1, 0, 'L', true);
        $pdf->Cell($colWidths[5], 8, 'EFT', 1, 0, 'L', true);
        $pdf->Cell($colWidths[6], 8, 'Created', 1, 0, 'C', true);
        $pdf->Cell($colWidths[7], 8, 'Status', 1, 1, 'C', true);
        
        // Add program data
        $pdf->SetFont('Arial', '', 9);
        $userBudget = 0;
        
        foreach ($userPrograms as $program) {
            // Check if we need a new page
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                // Re-add header for new page
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetFillColor(240, 240, 240);
                $pdf->Cell($colWidths[0], 8, 'Program Name', 1, 0, 'L', true);
                $pdf->Cell($colWidths[1], 8, 'Budget', 1, 0, 'R', true);
                $pdf->Cell($colWidths[2], 8, 'Recipient', 1, 0, 'L', true);
                $pdf->Cell($colWidths[3], 8, 'Reference', 1, 0, 'L', true);
                $pdf->Cell($colWidths[4], 8, 'Voucher', 1, 0, 'L', true);
                $pdf->Cell($colWidths[5], 8, 'EFT', 1, 0, 'L', true);
                $pdf->Cell($colWidths[6], 8, 'Created', 1, 0, 'C', true);
                $pdf->Cell($colWidths[7], 8, 'Status', 1, 1, 'C', true);
                $pdf->SetFont('Arial', '', 9);
            }
            
            // Truncate long text with better limits for smaller columns
            $programName = strlen($program['program_name']) > 18 ? substr($program['program_name'], 0, 15) . '...' : $program['program_name'];
            $recipientName = strlen($program['recipient_name']) > 12 ? substr($program['recipient_name'], 0, 9) . '...' : $program['recipient_name'];
            $reference = strlen($program['exco_letter_ref']) > 8 ? substr($program['exco_letter_ref'], 0, 5) . '...' : $program['exco_letter_ref'];
            $voucherNumber = $program['voucher_number'] ?: '-';
            $eftNumber = $program['eft_number'] ?: '-';
            
            // Format status text to be shorter and more readable
            $statusText = strtoupper(str_replace('_', ' ', $program['status']));
            if ($statusText === 'PAYMENT COMPLETED') {
                $statusText = 'APPROVED';
            } elseif ($statusText === 'PAYMENT IN PROGRESS') {
                $statusText = 'IN PROGRESS';
            } elseif ($statusText === 'UNDER REVIEW BY MMK') {
                $statusText = 'UNDER REVIEW';
            } elseif ($statusText === 'DOCUMENT ACCEPTED BY MMK') {
                $statusText = 'ACCEPTED';
            } elseif ($statusText === 'COMPLETE CAN SEND TO MMK') {
                $statusText = 'READY TO SEND';
            } elseif ($statusText === 'UNDER REVIEW BY MMK OFFICE') {
                $statusText = 'MMK REVIEW';
            }
            
            // Final fallback: truncate if still too long
            if (strlen($statusText) > 8) {
                $statusText = substr($statusText, 0, 5) . '...';
            }
            
            $pdf->Cell($colWidths[0], 6, $programName, 1, 0, 'L');
            $pdf->Cell($colWidths[1], 6, number_format($program['budget'], 2), 1, 0, 'R');
            $pdf->Cell($colWidths[2], 6, $recipientName, 1, 0, 'L');
            $pdf->Cell($colWidths[3], 6, $reference, 1, 0, 'L');
            $pdf->Cell($colWidths[4], 6, $voucherNumber, 1, 0, 'L');
            $pdf->Cell($colWidths[5], 6, $eftNumber, 1, 0, 'L');
            $pdf->Cell($colWidths[6], 6, date('d/m/Y', strtotime($program['created_at'])), 1, 0, 'C');
            $pdf->Cell($colWidths[7], 6, $statusText, 1, 1, 'C');
            
            // Only count budget for completed programs, not rejected
            if ($program['status'] === 'payment_completed') {
                $userBudget += $program['budget'];
                $totalBudget += $program['budget'];
            }
            $totalPrograms++;
        }
        
        // Add user summary
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 8, 'Total programs: ' . count($userPrograms) . ' | Total budget: RM ' . number_format($userBudget, 2), 0, 1, 'L');
        $pdf->Ln(10);
    }
    
    // Add overall summary
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Overall Summary', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, 'Total programs: ' . $totalPrograms . ' | Total budget: RM ' . number_format($totalBudget, 2), 0, 1, 'L');
    
    // Output PDF
    $pdf->Output('program_report.pdf', 'D');
    } catch (Exception $e) {
        // If PDF generation fails, return error as JSON
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error generating PDF: ' . $e->getMessage()
        ]);
    }
}

function getUserName($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['full_name'] : 'Unknown User';
}
?> 