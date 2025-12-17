<?php
session_start();
require_once 'db.php'; 

// Define roles allowed to manage student data
$allowed_roles = ['SuperAdmin', 'Reviewer']; 

// --- 1. ACCESS CONTROL (OWASP #12) ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    session_destroy();
    header("Location: login.php?error=unauthorized_access");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_role = $_SESSION['role'];
$message = '';
$search_results = [];
$selected_student = null;
$student_payments = [];
$student_bank = null;

// --- 2. HANDLE SEARCH REQUEST ---
if (isset($_GET['search_term']) && !empty($_GET['search_term'])) {
    $term = '%' . trim($_GET['search_term']) . '%';
    
    // Search by student_id, first_name, or last_name
    $stmt = $pdo->prepare("
        SELECT id, student_id, first_name, last_name, email, is_active 
        FROM students 
        WHERE student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? 
        LIMIT 10
    ");
    $stmt->execute([$term, $term, $term]);
    $search_results = $stmt->fetchAll();
    
    if (count($search_results) == 1) {
        // If only one result, automatically select it
        $selected_id = $search_results[0]['id'];
        header("Location: student_management.php?student_id=" . $selected_id);
        exit();
    }
}

// --- 3. HANDLE STUDENT SELECTION & DATA FETCHING ---
if (isset($_GET['student_id']) && is_numeric($_GET['student_id'])) {
    $sid = $_GET['student_id'];

    try {
        // Fetch Student Profile
        $stmt_profile = $pdo->prepare("
            SELECT * FROM students WHERE id = ?
        ");
        $stmt_profile->execute([$sid]);
        $selected_student = $stmt_profile->fetch();

        if ($selected_student) {
            // Fetch Payment History
            $stmt_payments = $pdo->prepare("
                SELECT amount, payment_month, disbursement_date, status 
                FROM payments 
                WHERE student_id = ? 
                ORDER BY payment_month DESC
            ");
            $stmt_payments->execute([$sid]);
            $student_payments = $stmt_payments->fetchAll();

            // Fetch Bank Details
            $stmt_bank = $pdo->prepare("
                SELECT bank_name, account_number, is_verified, last_updated 
                FROM bank_details 
                WHERE student_id = ?
            ");
            $stmt_bank->execute([$sid]);
            $student_bank = $stmt_bank->fetch();
        }

    } catch (\PDOException $e) {
        error_log("Student Management Fetch Error: " . $e->getMessage());
        $message = "Database error loading student data.";
    }
}

// --- 4. HANDLE DATA UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $sid = $_POST['student_db_id'];
    $new_email = trim($_POST['new_email']);
    $new_phone = trim($_POST['new_phone']);
    $original_data = json_decode(base64_decode($_POST['original_data']), true);

    if (empty($sid) || !is_numeric($sid)) {
        $message = "Invalid student ID for update.";
    } else {
        try {
            // Only update fields that have actually changed
            $updates = [];
            $log_details = "Admin {$admin_id} updated student ID {$original_data['student_id']}: ";
            
            if ($new_email !== $original_data['email']) {
                $updates[] = "email = :email";
                $log_details .= "Email: {$original_data['email']} -> {$new_email}; ";
            }
            if ($new_phone !== $original_data['phone']) {
                $updates[] = "phone = :phone";
                $log_details .= "Phone: {$original_data['phone']} -> {$new_phone}; ";
            }

            if (!empty($updates)) {
                $sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE id = :id";
                $stmt_update = $pdo->prepare($sql);
                
                $params = ['id' => $sid];
                if (isset($new_email) && $new_email !== $original_data['email']) $params['email'] = $new_email;
                if (isset($new_phone) && $new_phone !== $original_data['phone']) $params['phone'] = $new_phone;
                
                $stmt_update->execute($params);

                // --- AUDIT LOGGING (OWASP #9) ---
                $stmt_log = $pdo->prepare("
                    INSERT INTO audit_logs (user_id, user_role, action_type, details, ip_address) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt_log->execute([$admin_id, $admin_role, 'STUDENT_PROFILE_UPDATE', $log_details, $_SERVER['REMOTE_ADDR']]);
                
                $message = "Profile updated successfully for Student ID {$original_data['student_id']}.";
                // Redirect to refresh page state and clear POST data
                header("Location: student_management.php?student_id=" . $sid . "&msg=" . urlencode($message));
                exit();
            } else {
                $message = "No changes were made to the profile.";
            }

        } catch (\PDOException $e) {
            error_log("Student Management Update Error: " . $e->getMessage());
            $message = "Database error during update.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - ANAB Admin</title>
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
        h1 { color: var(--green); margin-bottom: 20px; }
        .search-container { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        }
        .search-container input[type="text"] { 
            padding: 10px; 
            width: 300px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            margin-right: 10px; 
        }
        .search-container button { 
            padding: 10px 15px; 
            background: var(--orange); 
            color: white; border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }
        
        /* Profile Details */
        .profile-section { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            margin-top: 20px; 
        }
        .profile-details div { 
            margin-bottom: 10px; 
            padding: 10px; 
            border-bottom: 1px dashed #eee; 
        }
        .edit-form label { display: block; font-weight: bold; margin-top: 10px; }
        .edit-form input[type="email"], .edit-form input[type="tel"] { 
            padding: 8px; 
            width: 300px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
        }
        .edit-form button { 
            background: var(--green); 
            color: white; 
            padding: 10px 15px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            margin-top: 15px; 
        }

        .payment-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .payment-table th, .payment-table td { 
            padding: 10px; 
            border: 1px solid #eee; 
            text-align: left; 
        }
        .payment-table th { background-color: var(--green); color: white; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="logo" style="text-align: center; color: var(--orange); margin-bottom: 30px;"><h2>ANAB Admin</h2></div>
        <nav>
            <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="payment_run.php"><i class="fas fa-money-check-alt"></i> Payment Run</a>
            <a href="bank_verification.php"><i class="fas fa-university"></i> Bank Verification</a>
            <a href="student_management.php" class="active"><i class="fas fa-users"></i> **Student Management**</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <h1>Student Management Portal</h1>
        <p>Role: **<?php echo htmlspecialchars($admin_role); ?>**</p>
        <hr>
        
        <?php 
        $display_msg = isset($_GET['msg']) ? urldecode($_GET['msg']) : $message;
        if (!empty($display_msg)) {
            $class = strpos($display_msg, 'success') !== false ? 'background-color: #e6ffed; color: var(--green);' : 'background-color: #ffe6e6; color: var(--red);';
            echo '<p style="' . $class . ' padding: 10px; border-radius: 5px; font-weight: bold; margin-bottom: 15px;">' . htmlspecialchars($display_msg) . '</p>';
        }
        ?>

        <div class="search-container">
            <form method="GET" action="student_management.php">
                <input type="text" name="search_term" placeholder="Enter Student ID, First Name, or Last Name" required 
                       value="<?php echo htmlspecialchars($_GET['search_term'] ?? ''); ?>">
                <button type="submit"><i class="fas fa-search"></i> Search Scholar</button>
            </form>
        </div>

        <?php if (!empty($search_results) && !isset($_GET['student_id'])): ?>
            <h2>Search Results (<?php echo count($search_results); ?> found)</h2>
            <ul>
                <?php foreach ($search_results as $result): ?>
                    <li>
                        <?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?> (ID: <?php echo htmlspecialchars($result['student_id']); ?>)
                        <a href="student_management.php?student_id=<?php echo $result['id']; ?>" style="margin-left: 15px; color: var(--orange); text-decoration: none;">View Profile</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php elseif (isset($_GET['search_term']) && empty($search_results) && !isset($_GET['student_id'])): ?>
            <p>No students found matching your search term.</p>
        <?php endif; ?>


        <?php if ($selected_student): ?>
            <div class="profile-section">
                <h2>Profile: <?php echo htmlspecialchars($selected_student['first_name'] . ' ' . $selected_student['last_name']); ?></h2>
                <hr>

                <div class="profile-details">
                    <div><strong>Student DB ID:</strong> <?php echo htmlspecialchars($selected_student['id']); ?></div>
                    <div><strong>Scholarship ID:</strong> <?php echo htmlspecialchars($selected_student['student_id']); ?></div>
                    <div><strong>University:</strong> <?php echo htmlspecialchars($selected_student['university_name'] ?? 'N/A'); ?></div>
                    <div><strong>Program:</strong> <?php echo htmlspecialchars($selected_student['program_name'] ?? 'N/A'); ?></div>
                    <div><strong>Account Status:</strong> <?php echo $selected_student['is_active'] ? '✅ Active' : '❌ Inactive'; ?></div>
                </div>

                <h3>Edit Contact Details</h3>
                <form method="POST" action="student_management.php?student_id=<?php echo $selected_student['id']; ?>" class="edit-form">
                    
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="student_db_id" value="<?php echo $selected_student['id']; ?>">
                    <input type="hidden" name="original_data" value="<?php echo base64_encode(json_encode($selected_student)); ?>">
                    
                    <label for="new_email">Email:</label>
                    <input type="email" id="new_email" name="new_email" value="<?php echo htmlspecialchars($selected_student['email']); ?>" required>

                    <label for="new_phone">Phone:</label>
                    <input type="tel" id="new_phone" name="new_phone" value="<?php echo htmlspecialchars($selected_student['phone']); ?>">

                    <button type="submit">Update Profile</button>
                </form>

                <h3 style="margin-top: 30px;">Bank Account Status</h3>
                <?php if ($student_bank): ?>
                    <p>Bank Name: **<?php echo htmlspecialchars($student_bank['bank_name']); ?>**</p>
                    <p>Account No: **<?php echo 'XXXX ' . substr(htmlspecialchars($student_bank['account_number']), -4); ?>**</p>
                    <p>Verification Status: 
                        <?php if ($student_bank['is_verified']): ?>
                            <span style="color: var(--green); font-weight: bold;">✅ Verified</span> (Last Updated: <?php echo date('Y-m-d', strtotime($student_bank['last_updated'])); ?>)
                        <?php else: ?>
                            <span style="color: var(--orange); font-weight: bold;">⏳ Pending Admin Verification</span>
                            <a href="bank_verification.php" style="margin-left: 10px;">(Verify Now)</a>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p>No bank details on file for this student.</p>
                <?php endif; ?>

                <h3 style="margin-top: 30px;">Payment History</h3>
                <?php if (!empty($student_payments)): ?>
                    <table class="payment-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Amount (XOF)</th>
                                <th>Disbursement Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M Y', strtotime($payment['payment_month'])); ?></td>
                                <td><?php echo number_format($payment['amount'], 0, '', ' '); ?></td>
                                <td><?php echo $payment['disbursement_date'] ? date('Y-m-d', strtotime($payment['disbursement_date'])) : 'N/A'; ?></td>
                                <td style="color: <?php echo ($payment['status'] == 'Paid' ? 'var(--green)' : ($payment['status'] == 'Scheduled' ? 'var(--orange)' : 'var(--red)')); ?>; font-weight: bold;"><?php echo htmlspecialchars($payment['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No payment history found for this scholar.</p>
                <?php endif; ?>

            </div>
        <?php endif; ?>
    </main>
</div>

</body>
</html>
