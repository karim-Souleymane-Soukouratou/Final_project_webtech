<?php
session_start();
require_once 'db.php'; 

$error = '';
$message = '';
$token = $_GET['token'] ?? null;
$show_form = false;
$user_id = null;
$user_role = null;

if (!$token || strlen($token) !== 64) {
    $error = "Invalid or missing password reset token.";
} else {
    try {
        $stmt_check = $pdo->prepare("
            SELECT user_id, user_role, expires_at 
            FROM password_resets 
            WHERE token = ?
        ");
        $stmt_check->execute([$token]);
        $reset_record = $stmt_check->fetch();

        if ($reset_record) {
            if (strtotime($reset_record['expires_at']) < time()) {
                $error = "This password reset link has expired. Please request a new link.";
                $pdo->prepare("DELETE FROM password_resets WHERE token = ?")->execute([$token]);
            } else {
                $show_form = true;
                $user_id = $reset_record['user_id'];
                $user_role = $reset_record['user_role'];
            }
        } else {
            $error = "Invalid password reset token. It may have already been used or deleted.";
        }
    } catch (\PDOException $e) {
        error_log("Reset Token Check Error: " . $e->getMessage());
        $error = "A system error occurred. Please try again later.";
    }
}
if ($show_form && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Password fields cannot be empty.";
    } elseif ($new_password !== $confirm_password) {
        $error = "The new password and confirmation password do not match.";
    } elseif (strlen($new_password) < 8) { 
        $error = "Password must be at least 8 characters long.";
    } else {
        
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $user_table = ($user_role === 'student') ? 'students' : 'admin_users';

        try {
            $pdo->beginTransaction();

            //  Update the user's password hash
            $stmt_update = $pdo->prepare("
                UPDATE {$user_table} 
                SET password_hash = ? 
                WHERE id = ?
            ");
            $stmt_update->execute([$new_password_hash, $user_id]);

            //  Delete the used token from the password_resets table
            $stmt_delete = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt_delete->execute([$token]);
            
            $pdo->commit();
            
            $message = "✅ Success! Your password has been updated. You can now log in.";
            $show_form = false; 
        } catch (\PDOException $e) {
            $pdo->rollBack();
            error_log("Password Reset Update Error: " . $e->getMessage());
            $error = "Database error: Could not update password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ANAB E-Bourse</title>
    <link rel="stylesheet" href="style.css">     
    <style>
        .reset-container { 
            max-width: 450px; 
            margin: 80px auto; 
            padding: 20px; 
            text-align: center; 
        }
        
        .reset-box { 
             background: #009E49; 
             padding: 30px; 
             border-radius: 8px; 
             box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
             color: black;
             margin-top: 15px;
        }

        .message-box {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .message-success {
            background-color: #e6ffe6;
            border: 1px solid green;
            color: green;
        }
        .message-error {
            background-color: #ffe6e6;
            border: 1px solid red;
            color: red;
        }
        
        .btn-primary { 
            width: 80%;
            margin: 10px auto;
            display: block;
        }
    </style>
</head>
<body>
    <div class="overlay"></div>

    <div class="reset-container">
        <h2>Reset Your Password</h2>
        
        <div class="reset-box">
            <img src="Anab_logo.jpeg" alt="Logo" width="100" height="100">
            
            <?php if (!empty($error)): ?>
                <p class="message-box message-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            
            <?php if (!empty($message)): ?>
                <p class="message-box message-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></p>
                <p><a href="login.php" class="btn-primary" style="text-decoration: none;"><i class="fas fa-sign-in-alt"></i> Go to Login</a></p>
            <?php endif; ?>

            <?php if ($show_form): ?>
                <p style="margin-bottom: 20px;">Please enter your new password below. **Minimum 8 characters.**</p>
                
                <form method="post" action="reset.php?token=<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="new_password" style="display: block; text-align: right; font-weight: bold;">New Password:</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" style="display: block; text-align: right; font-weight: bold;">Confirm Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
                    </div>

                    <input type="submit" value="Set New Password" style="background-color: #ffa231; color: white;">

                </form>
            <?php endif; ?>

            <?php if (!$show_form && empty($message)): ?>
                 <p style="margin-top: 20px;"><a href="forgot_password.php" style="color: #333; text-decoration: underline;">Request a new link</a></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>