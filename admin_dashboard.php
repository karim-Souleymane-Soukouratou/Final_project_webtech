<?php
session_start();
require_once 'db.php'; 
$allowed_roles = ['SuperAdmin', 'Finance']; 

//  Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// 2. Check if the user's role is authorized 
if (!in_array($_SESSION['role'], $allowed_roles)) {
     //If not authorized, destroy session and redirect
    session_destroy();
    header("Location: login.php?error=unauthorized");
    exit();
}

// Fetch Admin User Details 
$admin_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username FROM admin_users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

//  FETCH KEY FINANCIAL DATA 
//  Total Paid This Year
$stmt_paid = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'Paid' AND YEAR(disbursement_date) = YEAR(CURDATE())");
$total_paid = number_format($stmt_paid->fetchColumn() ?? 0, 0, '', ' ') . ' XOF';

// Total Pending Payments 
$stmt_pending = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'Pending'");
$total_pending = number_format($stmt_pending->fetchColumn() ?? 0, 0, '', ' ') . ' XOF';

//  Number of Payments in Next Run
$stmt_count = $pdo->query("SELECT COUNT(id) FROM payments WHERE status = 'Pending'");
$count_pending = $stmt_count->fetchColumn();

// Pending Bank Detail Updates (Requires Admin Review)
$stmt_bank = $pdo->query("SELECT COUNT(id) FROM bank_details WHERE is_verified = 0");
$count_bank_pending = $stmt_bank->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ANAB Finance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --orange: #F77F00;      
            --green: #009E60;       
            --red: #E31B23;         
            --white: #FFFFFF;
            --gray-bg: #f4f7f6;
            --dark: #333333;
        }
        body { font-family: Arial, sans-serif; background-color: var(--gray-bg); margin: 0; }
        .dashboard-container { display: flex; min-height: 100vh; }

        .sidebar {
            width: 250px;
            background-color: var(--dark);
            color: var(--white);
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.2);
        }
        .sidebar a { display: block; padding: 12px 10px; color: var(--white); text-decoration: none; margin-bottom: 5px; border-radius: 5px; }
        .sidebar a:hover, .sidebar a.active { background-color: var(--orange); }
        .logo h2 { text-align: center; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; }

        /* Main Content */
        .main-content { flex-grow: 1; padding: 30px; }
        .header { margin-bottom: 40px; }
        .header h1 { color: var(--green); }
        .header p { font-size: 1.1rem; color: #666; }

        /* KPI Cards */
        .kpi-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .kpi-card {
            background-color: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 5px solid;
            transition: transform 0.2s;
        }
        .kpi-card:hover { transform: translateY(-3px); }
        .kpi-card h3 { font-size: 1rem; color: #777; margin-bottom: 10px; }
        .kpi-value { font-size: 2rem; font-weight: bold; }
        .kpi-link { display: block; margin-top: 15px; font-size: 0.9rem; color: var(--green); text-decoration: none; }
        
        /* Specific Card Colors */
        .kpi-card.paid { border-left-color: var(--green); }
        .kpi-card.pending { border-left-color: var(--orange); }
        .kpi-card.alert { border-left-color: var(--red); }
    </style>
</head>
<body>

<div class="dashboard-container">
    <aside class="sidebar">
        <div class="logo">
            <h2>ANAB Admin Portal</h2>
        </div>
        <nav>
            <a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="payment_run.php"><i class="fas fa-money-check-alt"></i> **Payment Run**</a>
            <a href="bank_verification.php"><i class="fas fa-university"></i> Bank Verification</a>
            <a href="student_management.php"><i class="fas fa-users"></i> Student Management</a>
            <a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
            <a href="logout.php" style="color: var(--red); margin-top: 40px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <h1>Welcome Back, <?php echo htmlspecialchars($admin['username'] ?? 'Admin'); ?>!</h1>
            <p>Your Role: **<?php echo htmlspecialchars($_SESSION['role']); ?>**</p>
        </header>

        <h2>Financial Summary (<?php echo date('Y'); ?>)</h2>
        <div class="kpi-cards">
            
            <div class="kpi-card paid">
                <h3>Total Paid This Year</h3>
                <span class="kpi-value"><?php echo $total_paid; ?></span>
                <a href="reports.php" class="kpi-link">View Full Financial Report →</a>
            </div>

            <div class="kpi-card pending">
                <h3>Pending Payment Run</h3>
                <span class="kpi-value"><?php echo $total_pending; ?></span>
                <p><?php echo $count_pending; ?> scholars pending disbursement.</p>
                <a href="payment_run.php" class="kpi-link">**Prepare Payment File →**</a>
            </div>

            <div class="kpi-card alert">
                <h3>Bank Details Pending Verification</h3>
                <span class="kpi-value"><?php echo $count_bank_pending; ?></span>
                <p>New or updated bank accounts require approval.</p>
                <a href="bank_verification.php" class="kpi-link">Review Verification Queue →</a>
            </div>

        </div>

        <section class="recent-actions">
            <h2>Recent System Activities (Audit Log Preview)</h2>
            <p>Admin logs here...</p>
        </section>

    </main>
</div>

</body>
</html>