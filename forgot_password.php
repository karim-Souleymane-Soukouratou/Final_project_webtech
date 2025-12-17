<?php
session_start();
require_once 'db.php'; 

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier']);

    if (empty($identifier)) {
        $error = "Please enter your registered email or student ID.";
    } else {
        
        $user_data = null;
        $user_role = null;

        //SEARCH for user 
        
        // Search Students (by email or student_id)
        $stmt_student = $pdo->prepare("SELECT id, 'student' AS role FROM students WHERE student_id = ? OR email = ?");
        $stmt_student->execute([$identifier, $identifier]);
        $user_data = $stmt_student->fetch();
        
        if (!$user_data) {
            // Search Admins (by username)
            $stmt_admin = $pdo->prepare("SELECT id, 'admin' AS role FROM admin_users WHERE username = ?");
            $stmt_admin->execute([$identifier]);
            $user_data = $stmt_admin->fetch();
        }

        // HANDLE TOKEN CREATION (If user exists)
        
        if ($user_data) {
            $user_id = $user_data['id'];
            $user_role = $user_data['role'];
            $token = bin2hex(random_bytes(32)); 
            $expires_at = date("Y-m-d H:i:s", time() + 1800); 

            try {
                // Delete any existing tokens for this user first
                $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND user_role = ?")
                    ->execute([$user_id, $user_role]);

                // Insert the new secure token
                $stmt_insert = $pdo->prepare("
                    INSERT INTO password_resets (user_id, user_role, token, expires_at) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt_insert->execute([$user_id, $user_role, $token, $expires_at]);

                // SEND EMAIL for my projet i will just print the success message(it is just a simulation)
                $message = "If an account matching the identifier was found, a password reset link has been sent to the associated email address. The link is valid for 30 minutes.";

            } catch (\PDOException $e) {
                error_log("Password Reset Token Error: " . $e->getMessage());
                $error = "A system error occurred during the reset process.";
            }

        } else {
             $message = "If an account matching the identifier was found, a password reset link has been sent to the associated email address. The link is valid for 30 minutes.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ANAB E-Bourse</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container { 
            max-width: 400px; 
            margin: 100px auto; 
            padding: 20px; 
            text-align: center; 
        }
        .login-box { 
            background: #fff; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
        }
        .form-group { margin-bottom: 20px; }
        .form-control { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
            box-sizing: border-box; 
        }
        .btn-primary { 
            background-color: #006233; 
            color: white; 
            padding: 10px 15px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }
    </style>
</head>
<body>
    <div class="overlay"></div>

    <div class="login-container">
        <h2>Password Recovery</h2>
        
        <div class="login-box">
            <img src="Anab_logo.jpeg" alt="Logo" width="100" height="100">
            
            <p style="margin-bottom: 20px;">Enter your Student ID, Email, or Admin Username to receive a password reset link.</p>

            <?php if (!empty($error)): ?>
                <p style="color: red; font-weight: bold;"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            
            <?php if (!empty($message)): ?>
                <p style="color: green; font-weight: bold;"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <form method="post" action="forgot_password.php">
                <div class="form-group">
                    <label for="identifier" style="display: block; text-align: left; font-weight: bold;">Identifier:</label>
                    <input type="text" id="identifier" name="identifier" class="form-control" placeholder="Student ID, Email, or Username" required>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>

            <p style="margin-top: 20px;">
                <a href="login.php" style="color: #006233;"><i class="fas fa-chevron-left"></i> Back to Login</a>
            </p>
        </div>
    </div>
</body>
</html>