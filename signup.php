<?php
session_start();
require_once 'db.php'; 

$error = '';
$success = '';
$student_details = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_final_activation = (
        isset($_POST['password']) && !empty($_POST['password'])
    );

    $student_id = trim($_POST['Student_Id']);
    
    if (!$is_final_activation) {
        // VERIFICATION ATTEMPT (Checking Student ID)
        
        if (empty($student_id)) {
            $error = "Please enter your Student ID to begin account activation.";
        } else {
            $stmt_details = $pdo->prepare("
                SELECT id, is_active, first_name, last_name, university_name, program_name 
                FROM students
                WHERE student_id = ?
            ");
            $stmt_details->execute([$student_id]);
            $student_details = $stmt_details->fetch();

            if (!$student_details) {
                $error = "Student ID not found in ANAB records.";
            } elseif ($student_details['is_active'] == 1) {
                $error = "Account is already active. Please use the login page.";
                $student_details = null; 
            } else {
                $success = "Verification successful! Please confirm your details and create your password below.";
            }
        }

    } else {
        //  FINAL ACTIVATION ATTEMPT (All fields submitted) 
        $phone      = trim($_POST['Phone_Number']);
        $email      = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password   = $_POST['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $error = "Please provide a valid email and a password of at least 8 characters.";
        } else {
            $stmt_check = $pdo->prepare("SELECT id FROM students WHERE student_id = ? AND is_active = 0");
            $stmt_check->execute([$student_id]);
            $student = $stmt_check->fetch();

            if (!$student) {
                $error = "Activation failed. ID not found or account is already active.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_update = $pdo->prepare("
                    UPDATE students 
                    SET email = ?, phone = ?, password_hash = ?, is_active = 1 
                    WHERE id = ?
                ");
                
                if ($stmt_update->execute([$email, $phone, $hashed_password, $student['id']])) {
                    $success = "Account successfully activated! Redirecting to dashboard...";
                    $_SESSION['user_id'] = $student['id'];
                    $_SESSION['role'] = 'student';
                    header("Location: student_dashboard.php");
                    exit();
                } else {
                    $error = "Error during account activation. Please try again or contact support.";
                }
            }
        }
    }
}

if (isset($_POST['Student_Id']) && empty($student_details)) {
    $stmt_details = $pdo->prepare("
        SELECT id, is_active, first_name, last_name, university_name, program_name 
        FROM students
        WHERE student_id = ?
    ");
    $stmt_details->execute([$_POST['Student_Id']]);
    $student_details = $stmt_details->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Bourse Account Activation</title>
    <link rel="stylesheet" href="style.css?v=2">
</head>
<body>
    <div class="overlay"></div>

    <div class="signup-container">
        <h2>E-Bourse Account Activation</h2>

        <div class="login-box">
            <img src="Anab_logo.jpeg" alt="Logo" width="120" height="120">

            <?php if (isset($error)) : ?>
                <p style="color:red; font-weight:bold;"><?php echo $error; ?></p>
            <?php endif; ?>
            <?php if (isset($success)) : ?>
                <p style="color:green; font-weight:bold;"><?php echo $success; ?></p>
            <?php endif; ?>

            <form method="post" action="signup.php">
                
                <div class="form-group">
                    <label for="Student_Id">Your Unique Student ID:</label>
                    <input type="text" id="Student_Id" name="Student_Id" placeholder="Enter your Student ID" required 
                           value="<?php echo htmlspecialchars($_POST['Student_Id'] ?? ''); ?>" 
                           <?php echo ($student_details && $student_details['is_active'] != 1) ? 'readonly' : ''; ?>
                           >
                </div>

                <?php if ($student_details && $student_details['is_active'] != 1): ?>
                    <div style="padding: 15px; border: 1px solid var(--secondary, #006233); border-radius: 5px; margin-bottom: 15px; background-color: #e6ffed;">
                        <h3>✅ Approved Scholarship Details</h3>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($student_details['first_name'] . ' ' . $student_details['last_name']); ?></p>
                        <p><strong>University:</strong> <?php echo htmlspecialchars($student_details['university_name'] ?? 'N/A'); ?></p>
                        <p><strong>Program:</strong> <?php echo htmlspecialchars($student_details['program_name'] ?? 'N/A'); ?></p>
                        <hr>
                        <p style="font-size: 0.9em; color: var(--accent, #E31B23);">If these details are incorrect, DO NOT proceed and contact ANAB support.</p>
                    </div>
                <?php endif; ?>

                <?php if (!$student_details || ($student_details && $student_details['is_active'] == 1)): ?>
                    <p>Enter your ID above and click 'Activate' (or press Enter) to view your approved details.</p>
                    
                <?php else: ?>
                    <div class="form-group">
                        <label for="email">Confirmed Email (Will be used for recovery):</label>
                        <input type="email" id="email" name="email" placeholder="Enter your current Email" required>
                    </div>

                    <div class="form-group">
                        <label for="Phone_Number">Confirmed Phone Number (For SMS Alerts):</label>
                        <input type="tel" id="Phone_Number" name="Phone_Number" placeholder="Enter your Phone Number" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Create New Password:</label>
                        <input type="password" id="password" name="password" placeholder="Choose a secure password" required minlength="8">
                    </div>

                    <input type="submit" value="Activate Account">
                <?php endif; ?>
                
                <button type="button" onclick="window.location='login.php'">Back to Login</button>
            </form>
        </div>
    </div>
    <script src="main.js"></script>
</body>
</html>