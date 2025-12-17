<?php
session_start();
require_once 'db.php'; 

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // INPUT SANITIZATION AND VALIDATION
    $identifier = trim($_POST['identifier'] ?? ''); 
    $password   = $_POST['password'] ?? ''; 
    
    if (empty($identifier) || empty($password)) {
        $error = "Please enter both your identifier and password.";
        
    } else {

        // Initialization for the final check
        $authenticated_user = null;
        $role = null;

        //  CHECK ADMIN USERS (Highest priority access) 
        $stmt_admin = $pdo->prepare("SELECT id, password_hash, role FROM admin_users WHERE username = ?");
        $stmt_admin->execute([$identifier]);
        $admin_user = $stmt_admin->fetch();

        if ($admin_user) {
            // Verify Password Hash
            if (password_verify($password, $admin_user['password_hash'])) {
                $authenticated_user = $admin_user;
                $role = $admin_user['role'];
            }
        }
        
        //  CHECK STUDENT USERS (Only if admin check failed) 
        if (!$authenticated_user) {
            // Students can log in with their Student ID or Email
            $stmt_student = $pdo->prepare("SELECT id, password_hash FROM students WHERE student_id = ? OR email = ?");
            $stmt_student->execute([$identifier, $identifier]);
            $student_user = $stmt_student->fetch();

            if ($student_user) {
                if (password_verify($password, $student_user['password_hash'])) {
                    $authenticated_user = $student_user;
                    $role = 'student';
                }
            }
        }
        
        // HANDLE REDIRECTION OR FAILURE
        if ($authenticated_user) {
            // SUCCESSFUL LOGIN
            $_SESSION['user_id'] = $authenticated_user['id'];
            $_SESSION['role'] = $role;
            if ($role !== 'student') {
                $_SESSION['username'] = $identifier; 
            }
            
            if ($role === 'student') {
                header("Location: student_dashboard.php");
            } else {
                header("Location: admin_dashboard.php"); 
            }
            exit();
        } else {
            $error = "Invalid identifier or password. Please try again.";
            error_log("Failed login attempt for identifier: " . $identifier);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="style.css?v=1.2">
        <title>Login Page - E-Bourse ANAB</title>
    </head>
    <body>
        <div class="overlay"></div>
        <div class="login-container">
            <h2>E-Bourse ANAB Bourse Tracking System</h2>
            <div class="login-box">
                <img src="Anab_logo.jpeg" alt="Logo" width="120" height="120">
                <?php if (isset($error)): ?>
                <p style="color: red; font-weight: bold;"><?php echo $error; ?></p>
                <?php endif; ?>
                <form action="login.php" method="post">
                    <div class="form-group">
                    <label for="identifier">Student ID, Email, or Username</label> 
                    <input type="text" id="identifier" name="identifier" placeholder="Enter your identifier" required>
                    </div>
                    <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <input type="submit" value="Login">
                    <button type="button" onclick="window.location='index.php'">Back to Landing Page</button>
                    <button type="button" onclick="window.location='signup.php'">Activate Account</button>
                    <button type="button" onclick="window.location='forgot_password.php'">Forgot password</button> 
                </form>
            </div>
        </div>
        <script src="main.js"></script>
    </body>
</html>