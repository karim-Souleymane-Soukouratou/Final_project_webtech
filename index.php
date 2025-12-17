<?php
session_start();
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['role'] ?? null;

// Determine the redirect URL if the user is already logged in
if ($is_logged_in) {
    if ($user_role === 'student') {
        $dashboard_url = 'student_dashboard.php';
    } else {
        $dashboard_url = 'admin_dashboard.php';
    }
    $login_button_text = 'Go to Dashboard';
} else {
    $dashboard_url = 'login.php';
    $login_button_text = 'Scholar Login';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Bourse - ANAB Niger Scholarship Portal</title>    
    <link rel="stylesheet" href="index.css?v=1.0">
</head>
<body>
    <header>
        <a href="index.php" class="logo">
            <img src="anab_logo.jpeg" alt="ANAB Niger Logo">
        </a>
        <nav id="nav-menu">
            <a href="#info"><i class="fas fa-info-circle"></i> About</a>
            <a href="#how"><i class="fas fa-cogs"></i> How It Works</a>
            <a href="#faq"><i class="fas fa-question-circle"></i> FAQ</a>
            <a href="#footer"><i class="fas fa-address-book"></i> Contact</a>
        </nav>

        <a href="<?php echo $dashboard_url; ?>" class="btn btn-primary">
            <?php echo $login_button_text; ?>
        </a>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1>E-Bourse - ANAB Niger Scholarship Portal</h1>
            <p>Your secure and transparent platform to track scholarship payments and updates in real-time.</p>
        </div>

        <div class="hero-stats">
            <div class="stat">
                <span class="stat-number">10K+</span>
                <span class="stat-label">Scholars Supported</span>
            </div>
            <div class="stat">
                <span class="stat-number">24/7</span>
                <span class="stat-label">Real-Time Tracking</span>
            </div>
            <div class="stat">
                <span class="stat-number">100%</span>
                <span class="stat-label">Security & Audit</span>
            </div>
        </div>
    </section>
    
    <?php if (!$is_logged_in): ?>
    <section class="quick-access">
        <h2>Quick Access for Scholars</h2>
        <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Login to Your Account</a>
        <a href="signup.php" class="btn btn-secondary"><i class="fas fa-user-plus"></i> **New Scholar? Activate Account**</a>
        <p style="margin-top: 15px; font-size: 0.9em;">(For ANAB Staff: <a href="login.php" style="color: var(--secondary); text-decoration: underline;">Admin Login</a>)</p>
    </section>
    <?php endif; ?>

    <section class="info" id="info">
        <div class="container">
            <h2>About the ANAB E-Bourse Program</h2>
            <p style="max-width: 800px; margin: 0 auto; text-align: center;">The ANAB Scholarship program is committed to supporting students across Niger by providing timely financial assistance, ensuring transparency in payment schedules, and offering direct access to essential documentation.</p>
        </div>
    </section>
    
    <section class="how-it-works" id="how">
        <div class="container">
            <h2>How The E-Bourse Portal Works</h2>
            <div class="steps">
                <div class="step">
                    <h3><i class="fas fa-user-check"></i> 1. Account Activation</h3>
                    <p>Use your official Student ID to activate your pre-approved scholarship account and set your secure password (via `signup.php`).</p>
                </div>
                <div class="step">
                    <h3><i class="fas fa-university"></i> 2. Verify Bank Details</h3>
                    <p>Securely enter your bank information (RIB/Account Number). All new details must be verified by ANAB staff before payment is sent.</p>
                </div>
                <div class="step">
                    <h3><i class="fas fa-money-check-alt"></i> 3. Track Payments</h3>
                    <p>View the real-time status of your monthly stipend: Pending, Scheduled, Paid, or Failed. Access your full payment history.</p>
                </div>
            </div>
        </div>
    </section>

    <footer id="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>ANAB Niger</h4>
                    <a href="#">About</a>
                    <a href="#">Programs</a>
                    <a href="#">News</a>
                </div>
                <div class="footer-section">
                    <h4>Support & Contact</h4>
                    <a href="#footer"><i class="fas fa-envelope"></i> Contact Support</a>
                    <a href="#faq"><i class="fas fa-question-circle"></i> FAQ</a>
                    <a href="#">Privacy Policy</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 ANAB E-Bourse. All rights reserved. Powered by secure PDO implementation.</p>
            </div>
        </div>
    </footer>
</body>
</html>