<?php
session_start();
require_once 'db.php'; 


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$message = '';
$payment_history = [];
$student_profile = [];

try {
   
    $stmt_profile = $pdo->prepare("
        SELECT first_name, last_name, student_id, university_name
        FROM students 
        WHERE id = ?
    ");
    $stmt_profile->execute([$student_id]);
    $student_profile = $stmt_profile->fetch();

    if (!$student_profile) {
        $message = "Error: Could not find your student profile.";
    } else {
        
        // 3. FETCH PAYMENT HISTORY
        $stmt_history = $pdo->prepare("
            SELECT amount, payment_month, disbursement_date, status, transaction_ref
            FROM payments 
            WHERE student_id = ? 
            ORDER BY payment_month DESC
        ");
        $stmt_history->execute([$student_id]);
        $payment_history = $stmt_history->fetchAll();
    }

} catch (\PDOException $e) {
    error_log("Payment History DB Error: " . $e->getMessage());
    $message = "A system error occurred while loading your payment history.";
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - ANAB Niger</title>
    <style>
        :root { 
            --primary: rgba(252, 139, 0, 1); 
            --secondary: #006233; 
            --accent: #E31B23; 
            --white: #ffffff; 
            --gray: #f5f5f5; 
            --dark: #333; 
        }
        body { 
            font-family: Arial, sans-serif; 
            background-color: var(--gray); 
            margin: 0; 
        }
        .container { 
            max-width: 1000px; 
            margin: 20px auto; 
            background: var(--white); 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 8px 
            rgba(0,0,0,0.1); }
        h1 { 
            color: var(--secondary); 
            border-bottom: 2px solid var(--primary); 
            padding-bottom: 10px; 
            margin-bottom: 20px; 
        }
        .info-box { 
            background-color: #f0f0f0; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 20px; 
            border-left: 5px solid var(--primary);
        }
        
        /* Table Styles */
        .payment-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .payment-table th, .payment-table td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        .payment-table th { background-color: var(--secondary); color: white; }
        .payment-table tr:nth-child(even) { background-color: #f9f9f9; }
        .payment-table tr:hover { background-color: #f0f0f0; }

        /* Status Highlighting */
        .status-paid { color: var(--secondary); font-weight: bold; background-color: #e6ffed; }
        .status-scheduled { color: var(--primary); font-weight: bold; background-color: #fffde6; }
        .status-pending { color: #555; font-weight: bold; background-color: #e0e0e0; }
        .status-failed, .status-returned { color: var(--accent); font-weight: bold; background-color: #ffe6e6; }
        
        /* Status Key */
        .status-key { margin-top: 30px; padding: 15px; border: 1px dashed #ccc; border-radius: 5px; }
        .status-key h3 { color: var(--dark); margin-bottom: 10px; }
        .status-key p { margin-bottom: 5px; font-size: 0.9em; }

        /* Responsive Table */
        @media (max-width: 768px) {
            .payment-table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>

<div class="container">
    <a href="student_dashboard.php" style="float: right; text-decoration: none; color: var(--secondary); font-weight: bold;">
        <i class="fas fa-chevron-left"></i> Back to Dashboard
    </a>
    <h1>Scholarship Payment History</h1>
    <hr>

    <?php if (!empty($message)): ?>
        <p style="color: var(--accent); font-weight: bold;"><i class="fas fa-exclamation-triangle"></i> <?php echo $message; ?></p>
    <?php endif; ?>

    <?php if (!empty($student_profile)): ?>
        <div class="info-box">
            <p><strong>Scholar:</strong> <?php echo htmlspecialchars($student_profile['first_name'] . ' ' . $student_profile['last_name']); ?></p>
            <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student_profile['student_id']); ?></p>
            <p><strong>University:</strong> <?php echo htmlspecialchars($student_profile['university_name'] ?? 'N/A'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($payment_history)): ?>
        <p style="margin-bottom: 10px;">Showing **<?php echo count($payment_history); ?>** records found in your payment history.</p>
        <table class="payment-table">
            <thead>
                <tr>
                    <th><i class="fas fa-calendar-alt"></i> Month Cycle</th>
                    <th><i class="fas fa-money-bill-wave"></i> Amount (XOF)</th>
                    <th><i class="fas fa-info-circle"></i> Status</th>
                    <th><i class="fas fa-check-circle"></i> Actual Disbursement Date</th>
                    <th><i class="fas fa-exchange-alt"></i> Transaction Ref.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payment_history as $payment): 
                    $status = htmlspecialchars($payment['status']);
                    $status_class = strtolower($status);
                    $date = $payment['disbursement_date'] ? date('Y-m-d', strtotime($payment['disbursement_date'])) : 'N/A';
                ?>
                <tr>
                    <td><?php echo date('F Y', strtotime($payment['payment_month'])); ?></td>
                    <td><?php echo number_format($payment['amount'], 0, '', ' '); ?></td>
                    <td class="status-<?php echo $status_class; ?>"><?php echo $status; ?></td>
                    <td><?php echo $date; ?></td>
                    <td><?php echo htmlspecialchars($payment['transaction_ref'] ?? 'N/A'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="status-key">
            <h3>Payment Status Key</h3>
            <p><span class="status-paid">Paid:</span> The funds have successfully left the ANAB account and should be in your bank account.</p>
            <p><span class="status-scheduled">Scheduled:</span> The payment file has been sent to the bank for processing. Payment is imminent.</p>
            <p><span style="color: #555; font-weight: bold;">Pending:</span> The payment is awaiting inclusion in the next disbursement run by an ANAB administrator.</p>
            <p><span class="status-failed">Failed:</span> The payment was unsuccessful (e.g., incorrect bank details). Contact ANAB support.</p>
        </div>

    <?php else: ?>
        <p style="padding: 20px; border: 1px dashed #ccc; text-align: center;">
            <i class="fas fa-search-dollar"></i> No payment history records found for your account at this time.
        </p>
    <?php endif; ?>

</div>

</body>
</html>