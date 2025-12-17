<?php
session_start();
require_once 'db.php'; 

//  SECURITY & ACCESS CONTROL
$allowed_roles = ['SuperAdmin', 'Finance']; 

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    session_destroy();
    header("Location: login.php?error=unauthorized_access");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_username = ''; 
$error = '';
$payment_queue = [];
$total_payments = 0;
$total_amount_xof = 0;

//  FILE GENERATION AND DATABASE COMMIT LOGIC 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_payment'])) {
    
    // Re-fetch the queue to ensure no stale data is processed
    try {
        $stmt_commit_queue = $pdo->prepare("
            SELECT 
                p.id, s.student_id, s.first_name, s.last_name, p.amount, bd.account_number, bd.bank_name
            FROM payments p
            JOIN students s ON p.student_id = s.id
            JOIN bank_details bd ON p.student_id = bd.student_id
            WHERE p.status = 'Pending' AND bd.is_verified = 1
            ORDER BY s.student_id
        ");
        $stmt_commit_queue->execute();
        $commit_queue = $stmt_commit_queue->fetchAll();

    } catch (\PDOException $e) {
        error_log("Payment Run Commit Queue Fetch Error: " . $e->getMessage());
        $error = "Database error loading queue for commitment.";
        $commit_queue = [];
    }

    $total_payments_to_commit = count($commit_queue);
    $total_amount_to_commit = array_sum(array_column($commit_queue, 'amount'));

    if ($total_payments_to_commit > 0) {
        
        //  GENERATE BULK PAYMENT FILE (CSV)
        $filename = 'ANAB_DISB_' . date('Ymd_His') . '.csv';
        $file_path = __DIR__ . '/' . $filename; 
        
        $csv_data = "TransactionRef,StudentID,BeneficiaryName,Amount,BankName,AccountNumber\n";
        $transaction_ref_prefix = 'TRX' . date('ymd');

        $payment_ids_to_update = [];
        $i = 0;
        
        foreach ($commit_queue as $payment) {
            $ref = $transaction_ref_prefix . str_pad($i++, 4, '0', STR_PAD_LEFT);
            $full_name = $payment['first_name'] . ' ' . $payment['last_name'];
            $csv_data .= "$ref,{$payment['student_id']},{$full_name},{$payment['amount']},{$payment['bank_name']},{$payment['account_number']}\n";
            
            $payment_ids_to_update[] = $payment['id']; 
        }

        // 2. COMMIT DATABASE TRANSACTION 
        try {
            $pdo->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($payment_ids_to_update), '?'));
            $stmt_update = $pdo->prepare("
                UPDATE payments 
                SET status = 'Scheduled', admin_user_id = ?, disbursement_date = NOW()
                WHERE id IN ($placeholders)
            ");
            $update_params = array_merge([$admin_id], $payment_ids_to_update);
            $stmt_update->execute($update_params);

            $pdo->commit();
            
            error_log("AUDIT: Admin ID {$admin_id} successfully scheduled {$total_payments_to_commit} payments totaling {$total_amount_to_commit} XOF. File: {$filename}");

            file_put_contents($file_path, $csv_data);

            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
           
            if (ob_get_level()) ob_end_clean(); 
            readfile($file_path);
            unlink($file_path); 
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack(); 
            $error = "Payment run FAILED. Database changes rolled back. Error: " . $e->getMessage();
            error_log("Payment Run Fatal Error: " . $error);
        }
    } else {
        $error = "No payments are currently ready for disbursement.";
    }
}


//  DATA FETCHING FOR DISPLAY (Runs on GET and failed POST) 
try {
    // Fetch username for header
    $stmt_admin = $pdo->prepare("SELECT username FROM admin_users WHERE id = ?");
    $stmt_admin->execute([$admin_id]);
    $admin_user = $stmt_admin->fetch();
    $admin_username = $admin_user['username'];

    // Fetch the list of payments ready for disbursement: 
    $stmt_payments = $pdo->prepare("
        SELECT 
            s.student_id, s.first_name, s.last_name, p.amount, bd.account_number, bd.bank_name
        FROM payments p
        JOIN students s ON p.student_id = s.id
        JOIN bank_details bd ON p.student_id = bd.student_id
        WHERE p.status = 'Pending' AND bd.is_verified = 1
        ORDER BY s.student_id
    ");
    $stmt_payments->execute();
    $payment_queue = $stmt_payments->fetchAll();

    $total_payments = count($payment_queue);
    $total_amount_xof = array_sum(array_column($payment_queue, 'amount'));

} catch (\PDOException $e) {
    error_log("Payment Run Initial Fetch Error: " . $e->getMessage());
    $error = "Database error loading payment queue for display.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Run - ANAB Finance</title>
    <style>
        :root { 
            --orange: #F77F00; 
            --green: #009E60; 
            --red: #E31B23; 
            --white: #FFFFFF; 
            --gray-bg: #f4f7f6; 
            --dark: #333333; 
        }
        body { 
            font-family: Arial, sans-serif; 
            background-color: var(--gray-bg); 
            margin: 0; 
        }
        .dashboard-container { display: flex; min-height: 100vh; }
       
        .sidebar { 
            width: 250px; 
            background-color: var(--dark); 
            color: var(--white); 
            padding: 20px; 
        }
        .sidebar a { 
            display: block; 
            padding: 12px 10px; 
            color: var(--white); 
            text-decoration: none; 
            margin-bottom: 5px; 
            border-radius: 5px; 
        }

        .sidebar a:hover { background-color: var(--orange); }

        .main-content { flex-grow: 1; padding: 30px; }
        .summary-box { 
            background-color: #fff8e1; 
            border: 2px solid var(--orange); 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 30px; 
        }
        .summary-box h2 { color: var(--dark); margin-top: 0; }
        .summary-box p { font-size: 1.1rem; }
        .btn-commit { 
            background-color: var(--red); 
            color: white; 
            padding: 15px 30px; 
            border: none; 
            cursor: pointer; 
            border-radius: 5px; 
            font-size: 1.1rem; 
            margin-bottom: 20px; 
            transition: background 0.3s;
        }
        .btn-commit:hover { background-color: #a00; }

        .payment-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .payment-table th, .payment-table td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        .payment-table th { background-color: var(--green); color: white; }
        
        /* Responsive Table */
        @media (max-width: 768px) {
            .payment-table { display: block; overflow-x: auto; white-space: nowrap; }
            .payment-table thead, .payment-table tbody, .payment-table th, .payment-table td, .payment-table tr { display: block; }
            .payment-table thead { display: none; }
            .payment-table tbody tr { margin-bottom: 10px; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="logo" style="text-align: center; color: var(--orange); margin-bottom: 30px;">
            <h2>ANAB Admin</h2>
        </div>
        <nav>
            <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="payment_run.php" class="active"><i class="fas fa-money-check-alt"></i> **Payment Run**</a>
            <a href="bank_verification.php"><i class="fas fa-university"></i> Bank Verification</a>
            <a href="student_management.php"><i class="fas fa-users"></i> Student Mgmt</a>
            <a href="logout.php" style="color: var(--red);"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1>Payment Run Module</h1>
        <p>Admin: **<?php echo htmlspecialchars($admin_username); ?>** | Role: **<?php echo htmlspecialchars($_SESSION['role']); ?>**</p>
        <hr>

        <?php if (!empty($error)): ?>
            <p style="color: var(--red); font-weight: bold; padding: 10px; background-color: #ffe6e6; border-radius: 5px;"><?php echo $error; ?></p>
        <?php endif; ?>

        <div class="summary-box">
            <h2>Payment Summary Review</h2>
            <p>Total Payments Ready: **<?php echo $total_payments; ?>** scholars</p>
            <p>Total Amount to Disburse: **<?php echo number_format($total_amount_xof, 0, '', ' '); ?> XOF**</p>
            <p style="color: var(--red); font-weight: bold;">
                <i class="fas fa-exclamation-triangle"></i> WARNING: This action is irreversible. It will generate the bank file and mark payments as 'Scheduled'.
            </p>
        </div>

        <?php if ($total_payments > 0): ?>
            <form method="POST" action="payment_run.php" onsubmit="return confirm('ABSOLUTELY CONFIRM: Are you sure you want to commit this payment run?');">
                <input type="hidden" name="run_payment" value="1">
                <button type="submit" class="btn-commit">
                    <i class="fas fa-file-export"></i> GENERATE FILE & SCHEDULE PAYMENTS
                </button>
            </form>
            
            <h3>Detailed Payment Queue (<?php echo $total_payments; ?> Scholars)</h3>
            <table class="payment-table">
                <thead>
                    <tr>
                        <th>Scholar ID</th>
                        <th>Name</th>
                        <th>Amount (XOF)</th>
                        <th>Bank Name</th>
                        <th>Account No.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payment_queue as $payment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($payment['student_id']); ?></td>
                        <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                        <td><?php echo number_format($payment['amount'], 0, '', ' '); ?></td>
                        <td><?php echo htmlspecialchars($payment['bank_name']); ?></td>
                        <td>**** <?php echo substr(htmlspecialchars($payment['account_number']), -4); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
                The **Payment Queue** is currently empty. Reasons: Payments are not marked 'Pending', or bank details are not yet verified.
            </p>
        <?php endif; ?>
        <form method="POST" action="payment_run.php" id="payment-run-form"> </form>

    </main>
</div>

</body>
</html>