<?php
session_start();
require_once 'db.php'; 

// --- 1. ACCESS CONTROL ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id']; // This is the ID from the students table (PK)
$error = '';
$success = '';

// Check for success status redirected from POST request
if (isset($_GET['status']) && $_GET['status'] == 'updated') {
    $success = "Success: Your details have been submitted and are now **PENDING ADMIN VERIFICATION**.";
}

// --- 2. FETCH STUDENT NAME AND CURRENT BANK DETAILS ---
try {
    // Fetch student's name (needed for display regardless of bank details existence)
    $stmt_name = $pdo->prepare("SELECT first_name, last_name FROM students WHERE id = ?");
    $stmt_name->execute([$student_id]);
    $student_info = $stmt_name->fetch();

    if (!$student_info) {
        session_destroy();
        header("Location: login.php?error=data_error");
        exit();
    }
    
    // Fetch bank details
    $stmt_bank = $pdo->prepare("
        SELECT bank_name, account_number, iban, is_verified, last_updated
        FROM bank_details
        WHERE student_id = ?
    ");
    $stmt_bank->execute([$student_id]);
    $bank_details = $stmt_bank->fetch();

    // If no bank details exist, initialize array
    if (!$bank_details) {
        $bank_details = ['bank_name' => '', 'account_number' => '', 'iban' => '', 'is_verified' => 0, 'last_updated' => null];
    }

} catch (\PDOException $e) {
    error_log("Bank Details Fetch Error: " . $e->getMessage());
    $error = "A system error occurred. Please try again.";
}

// --- 3. HANDLE UPDATE REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    
    // Input Sanitization and Validation
    $new_bank_name = filter_var(trim($_POST['bank_name']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $new_account_number = filter_var(trim($_POST['account_number']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $new_iban = filter_var(trim($_POST['iban']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (empty($new_bank_name) || empty($new_account_number)) {
        $error = "Bank Name and Account Number are required.";
    } else {
        
        try {
            $pdo->beginTransaction();
            
            // Determine if we INSERT or UPDATE
            if ($bank_details['bank_name'] == '') {
                // INSERT: If record does NOT exist
                $stmt_insert = $pdo->prepare("
                    INSERT INTO bank_details (student_id, bank_name, account_number, iban, is_verified, last_updated) 
                    VALUES (?, ?, ?, ?, 0, NOW())
                ");
                $stmt_insert->execute([$student_id, $new_bank_name, $new_account_number, $new_iban]);
            } else {
                // UPDATE: If record EXISTS, reset is_verified to 0
                $stmt_update = $pdo->prepare("
                    UPDATE bank_details 
                    SET bank_name = ?, account_number = ?, iban = ?, is_verified = 0, last_updated = NOW()
                    WHERE student_id = ?
                ");
                $stmt_update->execute([$new_bank_name, $new_account_number, $new_iban, $student_id]);
            }

            // Commit the transaction
            $pdo->commit();

            // Redirect to prevent form resubmission (Post/Redirect/Get pattern)
            header("Location: edit_bank.php?status=updated");
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Bank Update Error: " . $e->getMessage());
            $error = "Error updating bank details. Please ensure your account number is correct.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Bank Details - ANAB Niger</title>
    
    <style>
        
        :root { 
            --primary: #fc9700; 
            --secondary: #006233; 
            --accent: #E31B23; 
            --white: #ffffff; 
            --gray: #f5f5f5; 
            --dark: #333; 
        }
        body { 
            font-family: Arial, sans-serif;
            background-color: var(--gray); 
            padding: 20px; 
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: var(--white); 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
        }
        h1 { 
            color: var(--secondary); 
            border-bottom: 2px solid var(--primary); 
            padding-bottom: 10px; 
            margin-bottom: 20px; 
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; 
            font-weight: bold; 
            margin-bottom: 5px; 
        }
        .form-control { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            box-sizing: border-box; 
        }
        
        .status-box { 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 5px; 
            font-weight: bold; 
            display: flex;
            align-items: center;
        }
        .status-box i { margin-right: 10px; font-size: 1.2em; }

        .status-verified { 
            background-color: #e6ffed; 
            border: 2px solid var(--secondary); 
            color: var(--secondary); 
        }
        .status-pending { 
            background-color: #fffde6; 
            border: 2px solid var(--primary); 
            color: var(--dark); 
        }
        .status-error { 
            background-color: #ffe6e6; 
            border: 2px solid var(--accent); 
            color: var(--accent); 
        }
        .submit-button {
            background-color: var(--secondary); 
            color: white; 
            padding: 10px 15px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer;
            font-size: 1.1em;
            margin-top: 10px;
        }
        .submit-button:hover { background-color: #004c27; }

        .current-details {
            border: 1px dashed #ccc; 
            padding: 15px; 
            margin-bottom: 20px;
            background-color: var(--gray);
            border-radius: 5px;
        }
        .current-details p { margin: 5px 0; }
    </style>
</head>
<body>

<div class="container">
    <a href="student_dashboard.php" style="float: right; text-decoration: none; color: var(--secondary); font-weight: bold;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    <h1>Update Scholarship Payment Account</h1>
    <hr>

    <?php 
        // Display messages
        if (!empty($error)) {
            echo '<div class="status-box status-error"><i class="fas fa-times-circle"></i> Error: ' . htmlspecialchars($error) . '</div>';
        } elseif (!empty($success)) {
            echo '<div class="status-box status-pending"><i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($success) . '</div>';
        }
    ?>
    
    <?php if ($bank_details['is_verified']): ?>
        <div class="status-box status-verified">
            <i class="fas fa-check-circle"></i> **VERIFIED:** Your current bank details are approved and ready for payment.
        </div>
    <?php elseif ($bank_details['bank_name'] != ''): ?>
        <div class="status-box status-pending">
            <i class="fas fa-clock"></i> **PENDING:** Your details are awaiting verification by an ANAB administrator.
            <?php if ($bank_details['last_updated']): ?>
                <span style="font-size: 0.8em; margin-left: auto;">Last updated: <?php echo date('M d, Y H:i', strtotime($bank_details['last_updated'])); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h2>Current Account Details</h2>
    <p>Account Holder Name: <strong style="color: var(--dark);"><?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name'] ?? 'N/A'); ?></strong> (Must match your bank records)</p>
    
    <div class="current-details">
        <p><i class="fas fa-university"></i> Bank Name: <?php echo htmlspecialchars($bank_details['bank_name'] ?? 'Not Provided'); ?></p>
        <p><i class="fas fa-credit-card"></i> Account Number: **
            <?php 
                // Display only the last 4 digits for security
                if ($bank_details['account_number']) {
                    echo 'XXXX XXXX ' . substr(htmlspecialchars($bank_details['account_number']), -4);
                } else {
                    echo 'Not Yet Provided';
                }
            ?>
        **</p>
    </div>

    <h2><i class="fas fa-pencil-alt"></i> Update Bank Details</h2>
    <form method="POST" action="edit_bank.php" onsubmit="return confirm('WARNING: Are you sure you want to change your payment destination? Submitting new details will immediately make your account status PENDING VERIFICATION and will halt payments until a staff member approves the change.');">
        
        <div class="form-group">
            <label for="bank_name">Bank Name (e.g., CBAO, BOA, Ecobank):</label>
            <input type="text" id="bank_name" name="bank_name" class="form-control" 
                    value="<?php echo htmlspecialchars($_POST['bank_name'] ?? $bank_details['bank_name'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="account_number">Full Account Number (RIB/Compte):</label>
            <input type="text" id="account_number" name="account_number" class="form-control" 
                    placeholder="Enter the full, correct account number" required>
            <small style="color: var(--accent); display: block; margin-top: 5px;"><i class="fas fa-exclamation-triangle"></i> **CRITICAL WARNING:** Ensure this number is 100% correct. Errors lead to payment failure and delay.</small>
        </div>

        <div class="form-group">
            <label for="iban">IBAN/SWIFT (Optional, for international transfers):</label>
            <input type="text" id="iban" name="iban" class="form-control" 
                    value="<?php echo htmlspecialchars($_POST['iban'] ?? $bank_details['iban'] ?? ''); ?>">
        </div>

        <button type="submit" class="submit-button"><i class="fas fa-save"></i> Save and Request Verification</button>
    </form>
</div>

</body>
</html>