<?php
session_start();
require_once 'db.php'; 

//  ACCESS CONTROL: Only SuperAdmin can create new accounts
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'SuperAdmin') {
    // If not SuperAdmin, deny access and redirect
    header("Location: admin_dashboard.php?error=access_denied");
    exit();
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 2. INPUT SANITIZATION AND VALIDATION
    $username = trim($_POST['username'] ?? '');
    $plain_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (empty($username) || empty($plain_password) || empty($confirm_password) || empty($role)) {
        $error = "All fields are required.";
    } elseif ($plain_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($plain_password) < 10) { 
        $error = "Password must be at least 10 characters long for admin accounts.";
    } else {
        
        // CHECK IF USERNAME ALREADY EXISTS
        $stmt_check = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt_check->execute([$username]);
        if ($stmt_check->fetch()) {
            $error = "Username already exists. Please choose another.";
        } else {
            
            //  HASH THE PASSWORD
            $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
            
            try {
                // INSERT SECURE RECORD
                $stmt_insert = $pdo->prepare("
                    INSERT INTO admin_users (username, password_hash, role) 
                    VALUES (?, ?, ?)
                ");
                $stmt_insert->execute([$username, $hashed_password, $role]);
                
                //  LOG AUDIT ENTRY
                $log_stmt = $pdo->prepare("INSERT INTO admin_log (admin_id, action, target_id) VALUES (?, ?, ?)");
                $new_admin_id = $pdo->lastInsertId();
                $log_stmt->execute([$_SESSION['user_id'], "Created new Admin ($role): $username", $new_admin_id]);

                $message = "✅ Success! New admin user **$username** created with role **$role**.";
                
            } catch (\PDOException $e) {
                error_log("Admin Creation DB Error: " . $e->getMessage());
                $error = "Database error: Could not create admin account.";
            }
        }
    }
}

$available_roles = ['Finance', 'SuperAdmin', 'StudentSupport', 'IT']; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin User - ANAB SuperAdmin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Admin Theme Colors */
        :root { --orange: #F77F00; --green: #009E60; --red: #E31B23; --white: #FFFFFF; --gray-bg: #f4f7f6; --dark: #333333; }
        body { font-family: Arial, sans-serif; background-color: var(--gray-bg); margin: 0; }
        .container { max-width: 700px; margin: 50px auto; background: var(--white); padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1 { color: var(--green); border-bottom: 2px solid var(--orange); padding-bottom: 10px; margin-bottom: 20px; }
        
        /* Form Styling */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: var(--dark); }
        .form-control { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            box-sizing: border-box; 
        }
        select.form-control { appearance: none; } /* Remove default styling */

        /* Message Boxes */
        .message-box { padding: 15px; margin-bottom: 20px; border-radius: 5px; font-weight: bold; }
        .success { background-color: #e6ffe6; border: 1px solid var(--green); color: var(--green); }
        .error { background-color: #ffe6e6; border: 1px solid var(--red); color: var(--red); }

        .btn-create {
            background-color: var(--green);
            color: var(--white);
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.05rem;
            margin-top: 15px;
            transition: background-color 0.3s;
        }
        .btn-create:hover { background-color: #004c27; }
    </style>
</head>
<body>

<div class="container">
    <a href="admin_dashboard.php" style="float: right; text-decoration: none; color: var(--dark); font-weight: bold;"><i class="fas fa-chevron-left"></i> Back to Dashboard</a>
    <h1><i class="fas fa-user-plus"></i> Create New Admin User</h1>
    <hr>
    <p style="margin-bottom: 20px;">Use this form to securely add new administrative staff to the system.</p>

    <?php if (!empty($error)): ?>
        <div class="message-box error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (!empty($message)): ?>
        <div class="message-box success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST" action="create_admin.php">
        
        <div class="form-group">
            <label for="username">Username (e.g., finance_doe):</label>
            <input type="text" id="username" name="username" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="role">Role:</label>
            <select id="role" name="role" class="form-control" required>
                <option value="">-- Select Role --</option>
                <?php foreach ($available_roles as $ar): ?>
                    <option value="<?php echo htmlspecialchars($ar); ?>"><?php echo htmlspecialchars($ar); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="password">Password (Minimum 10 characters):</label>
            <input type="password" id="password" name="password" class="form-control" required minlength="10">
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="10">
        </div>

        <button type="submit" class="btn-create"><i class="fas fa-user-plus"></i> Create Admin Account</button>
    </form>
</div>

</body>
</html>