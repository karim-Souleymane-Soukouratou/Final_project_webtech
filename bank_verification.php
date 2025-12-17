<?php
session_start();
require_once 'db.php'; 

// Define roles allowed to verify bank details
$allowed_roles = ['SuperAdmin', 'Finance', 'Reviewer']; 

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    session_destroy();
    header("Location: login.php?error=unauthorized_access");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_role = $_SESSION['role'];
$message = '';
$error = '';

//  HANDLE APPROVAL / REJECTION ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Input Validation (Check if the required action/ID is present)
    if (isset($_POST['action']) && isset($_POST['bank_id'])) {
        $action = $_POST['action'];
        $bank_id = $_POST['bank_id'];
        
        // Ensure the ID is an integer
        if (!is_numeric($bank_id)) {
            $error = "Invalid bank record ID.";
        } else {
            
            try {
                // Start a transaction for atomicity
                $pdo->beginTransaction();

                if ($action === 'approve') {
                    // Set is_verified = 1
                    $stmt = $pdo->prepare("UPDATE bank_details SET is_verified = 1 WHERE id = ? AND is_verified = 0");
                    $stmt->execute([$bank_id]);
                    
                    if ($stmt->rowCount()) {
                        $message = "Bank details (ID: {$bank_id}) successfully **APPROVED** and marked as verified.";
                        $log_action = 'BANK_VERIFY_APPROVED';
                    } else {
                        $error = "Approval failed or record already verified.";
                        $log_action = 'BANK_VERIFY_ATTEMPT_FAILED';
                    }
                    
                } elseif ($action === 'reject') {
                    
                    $stmt = $pdo->prepare("DELETE FROM bank_details WHERE id = ? AND is_verified = 0");
                    $stmt->execute([$bank_id]);

                    if ($stmt->rowCount()) {
                        $message = "Bank details (ID: {$bank_id}) **REJECTED** and removed. Student must re-enter details.";
                        $log_action = 'BANK_VERIFY_REJECTED';
                    } else {
                        $error = "Rejection failed or record was already processed.";
                        $log_action = 'BANK_VERIFY_ATTEMPT_FAILED';
                    }
                }

                if (isset($log_action)) {
                    $log_details = "Bank ID {$bank_id} changed by Admin {$admin_id}. Action: {$action}";
                    $stmt_log = $pdo->prepare("
                        INSERT INTO audit_logs (user_id, user_role, action_type, details, ip_address) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt_log->execute([$admin_id, $admin_role, $log_action, $log_details, $_SERVER['REMOTE_ADDR']]);
                }
                
                $pdo->commit();
                header("Location: bank_verification.php?status=success&msg=" . urlencode($message));
                exit();

            } catch (\PDOException $e) {
                $pdo->rollBack();
                error_log("Bank Verification DB Error: " . $e->getMessage());
                $error = "A database transaction error occurred. Changes rolled back.";
            }
        }
    }
}

// -. FETCH PENDING VERIFICATION QUEUE 
try {
    // Select all details where is_verified = 0, joining to get student name
    $stmt_queue = $pdo->prepare("
        SELECT 
            bd.id AS bank_detail_id, bd.bank_name, bd.account_number, bd.last_updated,
            s.student_id, s.first_name, s.last_name, s.email, s.phone
        FROM bank_details bd
        JOIN students s ON bd.student_id = s.id
        WHERE bd.is_verified = 0
        ORDER BY bd.last_updated ASC
    ");
    $stmt_queue->execute();
    $verification_queue = $stmt_queue->fetchAll();

} catch (\PDOException $e) {
    error_log("Bank Verification Fetch Queue Error: " . $e->getMessage());
    $error = "Could not load the verification queue from the database.";
    $verification_queue = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Verification Queue - ANAB Admin</title>
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
        .dashboard-container { 
            display: flex; 
            min-height: 100vh; 
        }
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
        .verification-card { 
            background-color: var(--white); 
            border-left: 5px solid var(--orange); 
            padding: 20px; 
            margin-bottom: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        }
        .details-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 10px; 
            margin-bottom: 15px; 
        }
        .actions button { 
            padding: 10px 15px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: bold; 
            margin-right: 10px; 
        }
        .btn-approve { background-color: var(--green); color: white; }
        .btn-reject { background-color: var(--red); color: white; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="logo" style="text-align: center; color: var(--orange); margin-bottom: 30px;"><h2>ANAB Admin</h2></div>
        <nav>
            <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="payment_run.php"><i class="fas fa-money-check-alt"></i> Payment Run</a>
            <a href="bank_verification.php" class="active"><i class="fas fa-university"></i> **Bank Verification**</a>
            <a href="student_management.php"><i class="fas fa-users"></i> Student Mgmt</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1>Bank Verification Queue (<?php echo count($verification_queue); ?> Pending)</h1>
        <p>Review and authorize all new or changed bank account details before disbursement.</p>
        <hr>

        <?php 
        // Display generic errors
        if (!empty($error)) {
            echo '<div style="background-color: #ffe6e6; color: var(--red); padding: 10px; border-radius: 5px; margin-bottom: 15px;">Error: ' . htmlspecialchars($error) . '</div>';
        }
        // Display successful actions
        if (isset($_GET['status']) && $_GET['status'] == 'success') {
            echo '<div style="background-color: #e6ffed; color: var(--green); padding: 10px; border-radius: 5px; margin-bottom: 15px;">Success: ' . htmlspecialchars($_GET['msg']) . '</div>';
        }
        ?>

        <?php if (count($verification_queue) > 0): ?>
            <?php foreach ($verification_queue as $item): ?>
                <div class="verification-card">
                    <h3>Scholar: <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?> (ID: <?php echo htmlspecialchars($item['student_id']); ?>)</h3>
                    <p style="font-size: 0.9em; color: #777;">Submitted/Updated: <?php echo date('Y-m-d H:i', strtotime($item['last_updated'])); ?></p>
                    
                    <div class="details-grid">
                        <div>
                            <strong>Bank Name:</strong> <?php echo htmlspecialchars($item['bank_name']); ?>
                        </div>
                        <div>
                            <strong>Account No.:</strong> <span style="font-weight: bold; color: var(--secondary);"><?php echo htmlspecialchars($item['account_number']); ?></span>
                            <i class="fas fa-exclamation-triangle" style="color: var(--red);" title="Verify this number manually!"></i>
                        </div>
                        <div>
                            <strong>Email:</strong> <?php echo htmlspecialchars($item['email']); ?>
                        </div>
                        <div>
                            <strong>Phone:</strong> <?php echo htmlspecialchars($item['phone']); ?>
                        </div>
                    </div>

                    <div class="actions">
                        <form method="POST" action="bank_verification.php" style="display: inline-block;">
                            <input type="hidden" name="bank_id" value="<?php echo $item['bank_detail_id']; ?>">
                            <button type="submit" name="action" value="approve" class="btn-approve">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </form>
                        
                        <form method="POST" action="bank_verification.php" style="display: inline-block;">
                            <input type="hidden" name="bank_id" value="<?php echo $item['bank_detail_id']; ?>">
                            <button type="submit" name="action" value="reject" class="btn-reject" 
                                onclick="return confirm('WARNING: Are you sure you want to REJECT and delete this account submission?');">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="padding: 20px; border: 1px solid var(--green); background-color: #e6ffed; border-radius: 5px; font-weight: bold;">
                <i class="fas fa-thumbs-up"></i> The verification queue is clear. No bank details are currently pending review.
            </p>
        <?php endif; ?>
    </main>
</div>

</body>
</html>