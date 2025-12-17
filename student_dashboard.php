<?php
session_start();
require_once 'db.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$display_error = '';

try {
    $stmt_student = $pdo->prepare("
        SELECT id, first_name, student_id, program_name, university_name
        FROM students 
        WHERE id = ?
    ");
    $stmt_student->execute([$student_id]);
    $student = $stmt_student->fetch();
    
    if (!$student) {
        session_destroy();
        header("Location: login.php?error=data_error");
        exit();
    }

    $student_db_id = $student['id'];

    // FETCH PAYMENT DATA 
    $stmt_total = $pdo->prepare("SELECT SUM(amount) AS total FROM payments WHERE student_id = ? AND status = 'Paid'");
    $stmt_total->execute([$student_db_id]);
    $total_received = $stmt_total->fetchColumn() ?? 0;

    // Next Payment (Pending or Scheduled status, ordered by date)
    $stmt_next = $pdo->prepare("
        SELECT amount, payment_month, status 
        FROM payments 
        WHERE student_id = ? AND status IN ('Pending', 'Scheduled') 
        ORDER BY payment_month ASC 
        LIMIT 1
    ");
    $stmt_next->execute([$student_db_id]);
    $next_payment = $stmt_next->fetch();

    // DATA PROCESSING FOR DISPLAY 
    
    $display_name = htmlspecialchars($student['first_name'] ?? 'Scholar');
    $display_total = number_format($total_received, 0, '', ' ') . ' XOF';
    
    if ($next_payment) {
        $next_amount = number_format($next_payment['amount'], 0, '', ' ') . ' XOF';
        $next_date = date('M d, Y', strtotime($next_payment['payment_month']));
        $next_status = $next_payment['status'];
    } else {
        $next_amount = 'N/A';
        $next_date = 'No Scheduled Payment';
        $next_status = 'Inactive';
    }

} catch (\PDOException $e) {
    error_log("Student Dashboard DB Error: " . $e->getMessage());
    $display_error = "A system error occurred while fetching your data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard - ANAB Niger</title>
<style>


* { 
    margin: 0;
    padding: 0; 
    box-sizing: border-box; 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
}

:root {
    --primary: #fc9700;     
    --secondary: #006233;  
    --white: #ffffff;
    --gray: #f5f5f5;
    --dark: #333;
    --scheduled: #007bff;     
    --pending: #ffc107;    
    --paid: #28a745;        
    --failed: #dc3545;      
}
body { 
    background-color: var(--gray); 
}

.dashboard-container { display: flex; min-height: 100vh; }

/* Sidebar */
.sidebar {
    width: 250px;
    background-color: var(--secondary);
    color: var(--white);
    display: flex;
    flex-direction: column;
    padding: 20px;
    box-shadow: 2px 0 5px rgba(0,0,0,0.1);
}

.sidebar .logo { text-align: center; margin-bottom: 40px; }
.sidebar .logo img { width: 60px; height: auto; margin-bottom: 10px; border-radius: 50%; }
.sidebar .logo h2 { font-size: 1.3rem; font-weight: bold; color: var(--primary); }

.sidebar-nav a {
    display: block;
    padding: 12px 15px;
    color: var(--white);
    text-decoration: none;
    border-radius: 5px;
    margin-bottom: 8px;
    transition: background 0.3s;
    font-size: 1.05rem;
}

.sidebar-nav a i { margin-right: 10px; }

.sidebar-nav a:hover,
.sidebar-nav a.active { 
    background-color: var(--primary); 
    color: var(--dark);
}

.main-content { flex: 1; padding: 30px; }

header h1 { font-size: 2.5rem; color: var(--dark); margin-bottom: 10px; }
.student-details { font-size: 1rem; color: #666; margin-bottom: 30px; }

/* Dashboard Cards */
.dashboard-cards { display: flex; gap: 20px; margin-bottom: 40px; flex-wrap: wrap; }
.card {
    background-color: var(--white);
    padding: 20px;
    flex: 1 1 250px;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    text-align: center;
    border-left: 5px solid; /* For status color */
}
.card h3 { margin-bottom: 15px; color: #555; font-size: 1rem; }
.card p { font-size: 1.8rem; font-weight: bold; margin-top: 5px; }
.card i { font-size: 1.5rem; margin-right: 8px; }

/* Card Status Styling */
.card.status-inactive { border-left-color: #aaa; }
.card.status-scheduled { border-left-color: var(--scheduled); }
.card.status-pending { border-left-color: var(--pending); }
.card.status-paid { border-left-color: var(--paid); }

/* Recent Updates */
.recent-updates h2 { margin-bottom: 15px; color: var(--secondary); font-size: 1.8rem;}
.recent-updates ul { list-style: none; }
.recent-updates li {
    background-color: var(--white);
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-left: 5px solid var(--primary);
    line-height: 1.4;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-container { flex-direction: column; }
    .sidebar { width: 100%; flex-direction: row; overflow-x: auto; height: auto; }
    .sidebar-nav { display: flex; gap: 10px; flex-shrink: 0; }
    .sidebar-nav a { margin-bottom: 0; }
    .dashboard-cards { flex-direction: column; }
}
</style>
</head>
<body>

<div class="dashboard-container">

    <aside class="sidebar">
        <div class="logo">
            <img src="Anab_logo.jpeg" alt="ANAB Niger Logo">
            <h2>E-Bourse Portal</h2>
        </div>
        <nav class="sidebar-nav">
            <a href="student_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="payment_history.php"><i class="fas fa-history"></i> Payment History</a>
            <a href="payment_calendar.php"><i class="fas fa-calendar-alt"></i> Payment Calendar</a>
            <a href="edit_bank.php"><i class="fas fa-user-edit"></i> Update Bank Details</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>

    <main class="main-content">
        <header>
            <h1>Welcome, <?php echo $display_name; ?>!</h1>
            <p class="student-details">
                <i class="fas fa-id-badge"></i> ID: <?php echo htmlspecialchars($student['student_id']); ?> | 
                <i class="fas fa-school"></i> University: <?php echo htmlspecialchars($student['university_name'] ?? 'N/A'); ?> | 
                <i class="fas fa-graduation-cap"></i> Program: <?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?>
            </p>
            <?php if ($display_error): ?>
                <p style="color: var(--failed); background-color: #fff0f0; font-weight: bold; padding: 10px; border: 1px solid var(--failed); border-radius: 5px;"><?php echo $display_error; ?></p>
            <?php endif; ?>
        </header>

        <section class="dashboard-cards">
            <?php 
                $status_class = 'status-inactive';
                if ($next_status === 'Pending') {
                    $status_class = 'status-pending';
                } elseif ($next_status === 'Scheduled') {
                    $status_class = 'status-scheduled';
                } elseif ($next_status === 'Paid') {
                    $status_class = 'status-paid';
                }
            ?>
            
            <div class="card <?php echo $status_class; ?>">
                <h3>Next Payment Status</h3>
                <p><i class="fas fa-clock"></i> <?php echo $next_status; ?></p>
            </div>
            
            <div class="card status-scheduled">
                <h3>Next Payment Amount</h3>
                <p><i class="fas fa-money-bill-wave"></i> <?php echo $next_amount; ?></p>
            </div>
            
            <div class="card status-paid">
                <h3>Total Received (YTD)</h3>
                <p><i class="fas fa-hand-holding-usd"></i> <?php echo $display_total; ?></p>
            </div>
        </section>

        <section class="recent-updates">
            <h2><i class="fas fa-info-circle"></i> Key Information</h2>
            <ul>
                <li>Next Payment Date: <span style="font-weight: bold; color: <?php echo ($next_status === 'Scheduled' ? 'var(--scheduled)' : '#555'); ?>;"><?php echo $next_date; ?></span></li>
                <li>Institution: <?php echo htmlspecialchars($student['university_name'] ?? 'N/A'); ?></li>
                <li>Important Security Note: The status **Scheduled** confirms ANAB has sent the payment file. Always verify final disbursement directly with your bank.</li>
            </ul>
        </section>
    </main>
</div>

</body>
</html>