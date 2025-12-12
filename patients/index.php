<?php
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'p') {
        header("location: ../login.php");
        exit();
    } else {
        $useremail = $_SESSION["user"];
    }
} else {
    header("location: ../login.php");
    exit();
}

// Import database
include("../connection.php");

$sqlmain = "SELECT * FROM tbl_patients WHERE Email=?";
$stmt = $database->prepare($sqlmain);
$stmt->bind_param("s", $useremail);
$stmt->execute();
$userrow = $stmt->get_result();
$userfetch = $userrow->fetch_assoc();

$userid = $userfetch["Patient_id"];
$username = $userfetch["Fname"]; // Just first name
// NEW: profile image path
$profileImage = !empty($userfetch['ProfileImage']) ? '../Images/profiles/' . $userfetch['ProfileImage'] : '../Images/user.png';

$fullname = $userfetch["Fname"] . ' ' . $userfetch["Mname"] . ' ' . $userfetch["Lname"];
if (!empty($userfetch["Suffix"])) {
    $fullname .= ' ' . $userfetch["Suffix"];
}

// Calculate patient age for senior discount
$birthdate = $userfetch["Birthdate"];
$age = 0;
$isEligibleForDiscount = false;
if (!empty($birthdate)) {
    $birthDate = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    $isEligibleForDiscount = $age >= 60;
}

// Get today's appointments
$today = date('Y-m-d');
$sql_today = "SELECT ar.*, 
    DATE_FORMAT(ar.booking_date, '%M %d, %Y') as formatted_date,
    DATE_FORMAT(ar.appointment_time, '%h:%i %p') as formatted_time
    FROM tbl_appointment_requests ar 
    WHERE ar.patient_id = ? 
    AND ar.booking_date = ? 
    AND ar.status = 'approved'
    AND ar.appointment_progress != 'cancelled'";
$stmt_today = $database->prepare($sql_today);
$stmt_today->bind_param("is", $userid, $today);
$stmt_today->execute();
$today_appointments = $stmt_today->get_result();

// Get total successful appointments
$sql_success = "SELECT COUNT(*) as total FROM tbl_appointment_requests 
    WHERE patient_id = ? 
    AND status = 'approved' 
    AND appointment_progress = 'done'";
$stmt_success = $database->prepare($sql_success);
$stmt_success->bind_param("i", $userid);
$stmt_success->execute();
$success_count = $stmt_success->get_result()->fetch_assoc()['total'];

// Get upcoming appointments
$sql_upcoming = "SELECT ar.*, 
    DATE_FORMAT(ar.booking_date, '%M %d, %Y') as formatted_date,
    DATE_FORMAT(ar.appointment_time, '%h:%i %p') as formatted_time
    FROM tbl_appointment_requests ar 
    WHERE ar.patient_id = ? 
    AND ar.booking_date > CURDATE()
    AND ar.status = 'approved'
    AND ar.appointment_progress != 'cancelled'
    ORDER BY ar.booking_date ASC
    LIMIT 3";
$stmt_upcoming = $database->prepare($sql_upcoming);
$stmt_upcoming->bind_param("i", $userid);
$stmt_upcoming->execute();
$upcoming_appointments = $stmt_upcoming->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Dashboard</title>
    <style>
        /* General entrance animations */
.fade-in {
    animation: fadeIn 0.5s ease forwards;
}

.slide-in {
    animation: slideInRight 0.5s ease forwards;
}

.pop-in {
    animation: popIn 0.4s ease forwards;
}

.bounce-in {
    animation: bounceIn 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
}

/* Interactive animations */
.float-animation {
    animation: floatAnimation 3s ease-in-out infinite;
}

.pulse-on-hover:hover {
    animation: pulseAnimation 0.3s ease-in-out;
}

/* Shimmer effect for loading states */
.shimmer {
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0%,
        rgba(255, 255, 255, 0.8) 50%,
        rgba(255, 255, 255, 0) 100%
    );
    background-size: 1000px 100%;
    animation: shimmer 2s infinite linear;
}

/* Button hover effects */
.btn-hover-effect {
    transition: all 0.3s ease;
}

.btn-hover-effect:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

/* Card hover effects */
.card-hover-effect {
    transition: all 0.3s ease;
}

.card-hover-effect:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

        .dashbord-tables{
            animation: transitionIn-Y-over 0.5s;
        }
        .filter-container{
            animation: transitionIn-Y-bottom  0.5s;
        }
        .sub-table,.anime{
            animation: transitionIn-Y-bottom 0.5s;
        }
        .dashboard-container {
            padding: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .card-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .card-icon:hover {
            transform: scale(1.05);
        }

        .card-title {
            color: #2d3436;
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .appointment-list {
            margin-top: 15px;
        }

        .appointment-item {
            padding: 12px;
            border-radius: 8px;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-left: 4px solid var(--primarycolor);
        }

        .appointment-time {
            color: var(--primarycolor);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primarycolor);
            margin: 10px 0;
        }

        .book-appointment-card {
            text-align: center;
            padding: 30px 20px;
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarycolorhover) 100%);
            color: white;
        }

        .book-appointment-card h3 {
            color: white;
            margin-bottom: 15px;
        }

        .big-button {
            background: white;
            color: var(--primarycolor);
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .big-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .welcome-section {
            background: url('../Images/b3.jpg') no-repeat center center;
            background-size: cover;
            padding: 40px;
            border-radius: 15px;
            color: white;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1;
        }

        .welcome-content {
            position: relative;
            z-index: 2;
        }

        /* Added styles for health tips section */
.health-tips-section {
    margin-top: 30px;
    padding: 0 25px;
}

.tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.tip-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    border-top: 4px solid var(--primarycolor);
}

.tip-icon {
    width: 40px;
    height: 40px;
    margin-bottom: 15px;
}

.tip-title {
    font-weight: 600;
    color: #2d3436;
    margin-bottom: 10px;
}

.upcoming-appointments {
    margin-top: 30px;
    padding: 0 25px;
}

.timeline {
    position: relative;
    margin: 20px 0;
}

.timeline-item {
    background: white;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.timeline-date {
    background: var(--btnice);
    color: var(--btnnicetext);
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
}

.quick-actions {
    margin-top: 30px;
    padding: 0 25px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.action-button {
    background: white;
    border: none;
    padding: 15px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    color: #2d3436;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.action-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.section-title {
    color: #2d3436;
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 20px;
}

.dashboard-card:nth-child(1) .card-icon {
    background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
}

        .dashboard-card:nth-child(2) .card-icon {
            background: linear-gradient(135deg, #2196F3 0%, #03A9F4 100%);
        }

/* Responsive fixes for mobile */
@media (max-width: 992px) {
    .dashboard-container { padding: 20px; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); max-width: 560px; margin: 0 auto; }
    .welcome-section { padding: 30px; max-width: 560px; margin: 0 auto 20px; }
    /* Hamburger layout */
    .mobile-header{ display:flex !important; align-items:center; justify-content:space-between; padding:12px 16px; background:#fff; border-bottom:1px solid #eaeaea; position:sticky; top:0; z-index:1001; }
    .hamburger{ width:28px; height:22px; position:relative; cursor:pointer; }
    .hamburger span{ position:absolute; left:0; right:0; height:3px; background:#333; border-radius:2px; transition:.3s; }
    .hamburger span:nth-child(1){ top:0; }
    .hamburger span:nth-child(2){ top:9px; }
    .hamburger span:nth-child(3){ bottom:0; }
    .mobile-title{ font-weight:600; color:#161c2d; }
    .container{ height:auto; flex-direction:column; }
    .menu{ width:260px; height:100vh; position:fixed; top:0; left:-280px; background:#fff; z-index:1002; overflow-y:auto; transition:left .3s ease; box-shadow:2px 0 12px rgba(0,0,0,.06); }
    .menu.open{ left:0; }
    .menu .profile-container{ padding-top:24px; padding-bottom:16px; }
    .dash-body{ width:100% !important; padding:15px; max-width:560px; margin:0 auto; }
    .overlay{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1000; }
    .overlay.show{ display:block; }
}
@media (max-width: 768px) {
    html, body { overflow-x: hidden; }
    .container { flex-direction: column; height: auto; }
    .dash-body { width: 100% !important; padding: 15px; }
    .dashboard-container { padding: 15px; gap: 15px; grid-template-columns: 1fr; max-width: 560px; margin: 0 auto; }
    .welcome-section { padding: 20px; margin: 0 auto 15px; max-width: 560px; }
    .timeline-item { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 480px) {
    .card-title { font-size: 1.05rem; }
    .stats-number { font-size: 2rem; }
    .big-button { width: 100%; text-align: center; }
}
    </style>
</head>
<body>
<div class="mobile-header" style="display:none">
    <div class="hamburger" id="hamburger" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
    </div>
    <div class="mobile-title">Dashboard</div>
    <div style="width:28px;height:22px"></div>
  </div>
<div class="overlay" id="overlay" style="display:none"></div>
<div class="container">
    <div class="menu" id="sidebar">
        <table class="menu-container" border="0">
            <tr>
                <td style="padding:10px" colspan="2">
                    <table border="0" class="profile-container">
                        <tr>
                            <td width="30%" style="padding-left:20px" >
                                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                            </td>
                            <td style="padding:0px;margin:0px;">
                                <p class="profile-title"><?php echo htmlspecialchars($username) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="../logout.php"><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-home menu-active menu-icon-home-active">
                    <a href="index.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Home</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-session">
                    <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Book Appointment</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-appoinment">
                    <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">My Bookings</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-settings">
                    <a href="settings.php" class="non-style-link-menu"><div><p class="menu-text">Settings</p></div></a>
                </td>
            </tr>
        </table>
    </div>
    <div class="dash-body" id="content">
        <!-- Welcome section -->
        <div class="welcome-section fade-in">
            <div class="welcome-content">
                <h1>Welcome Back, <?php echo htmlspecialchars($username); ?>!</h1>
                <p>Manage your appointments and health journey with us.</p>
            </div>
        </div>


        <div class="dashboard-container">
            <!-- Today's Appointments Card -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-calendar-check" style="color: #ffffff; font-size: 24px;"></i>
                    </div>
                    <h2 class="card-title">Today's Appointments</h2>
                </div>
                <div class="appointment-list">
                    <?php if($today_appointments->num_rows > 0): ?>
                        <?php while($app = $today_appointments->fetch_assoc()): ?>
                            <div class="appointment-item">
                                <div class="appointment-time">
                                    <?php echo htmlspecialchars($app['formatted_time']); ?>
                                </div>
                                <div>
                                    <?php echo htmlspecialchars($app['appointment_type']); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No appointments scheduled for today.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Successful Appointments Card -->
            <div class="dashboard-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-star" style="color: #ffffff; font-size: 24px;"></i>
                    </div>
                    <h2 class="card-title">Successful Appointments</h2>
                </div>
                <div class="stats-number"><?php echo $success_count; ?></div>
                <p>Total completed appointments</p>
            </div>

            <!-- Book Appointment Card -->
            <div class="dashboard-card book-appointment-card">
                <h3>Need to see a doctor?</h3>
                <p>Schedule your next appointment with us</p>
                <a href="schedule.php" class="big-button btn-hover-effect pulse-on-hover">
                    Book an Appointment
                </a>
            </div>
        </div>

        <!-- Upcoming Appointments Section -->
        <div class="upcoming-appointments">
            <h2 class="section-title">Upcoming Appointments</h2>
            <div class="timeline">
                <?php if($upcoming_appointments->num_rows > 0): ?>
                    <?php while($upcoming = $upcoming_appointments->fetch_assoc()): ?>
                        <div class="timeline-item">
                            <div class="timeline-date">
                                <?php echo htmlspecialchars($upcoming['formatted_date']); ?>
                            </div>
                            <div>
                                <h3><?php echo htmlspecialchars($upcoming['appointment_type']); ?></h3>
                                <p>Time: <?php echo htmlspecialchars($upcoming['formatted_time']); ?></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No upcoming appointments scheduled.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Health Tips Section -->
        <div class="health-tips-section">
            <h2 class="section-title">Health Tips & Reminders</h2>
            <div class="tips-grid">
                <div class="tip-card card-hover-effect bounce-in" style="animation-delay: 0.4s">
                    <i class="fas fa-eye" style="color: #000000; font-size: 24px;"></i>
                    <h3 class="tip-title">Regular Eye Breaks</h3>
                    <p>Follow the 20-20-20 rule: Every 20 minutes, look at something 20 feet away for 20 seconds.</p>
                </div>
                <div class="tip-card">
                    <i class="fas fa-apple-alt" style="color: #000000; font-size: 24px;"></i>
                    <h3 class="tip-title">Eye-Healthy Foods</h3>
                    <p>Include foods rich in Vitamins A, C, E, and omega-3 fatty acids in your diet for better eye health.</p>
                </div>
                <div class="tip-card">
                    <i class="fas fa-shield-alt" style="color: #000000; font-size: 24px;"></i>
                    <h3 class="tip-title">UV Protection</h3>
                    <p>Wear sunglasses with UV protection when outdoors to protect your eyes from harmful sun rays.</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="quick-actions">
            <h2 class="section-title">Quick Actions</h2>
            <div class="actions-grid">
                <a href="schedule.php" class="action-button btn-hover-effect slide-in" style="animation-delay: 0.6s">
                    <i class="fas fa-calendar-plus" style="color: #000000; font-size: 24px;"></i>
                    <span>Schedule Appointment</span>
                </a>
                <a href="appointment.php" class="action-button">
                    <i class="fas fa-history" style="color: #000000; font-size: 24px;"></i>
                    <span>View History</span>
                </a>
                <a href="settings.php" class="action-button">
                    <i class="fas fa-cog" style="color: #000000; font-size: 24px;"></i>
                    <span>Update Profile</span>
                </a>
            </div>
        </div>
    </div>
</div>
<script src="../js/animations.js"></script>
<script>
// Ensure content is visible on mobile after navigation
(function(){
  function scrollToContent(){
    var el = document.getElementById('content');
    if(!el) return;
    if (window.matchMedia('(max-width: 768px)').matches) {
      el.scrollIntoView({behavior:'smooth', block:'start'});
    }
  }
  // On load
  window.addEventListener('load', scrollToContent);
  // On sidebar link click
  document.querySelectorAll('.menu a').forEach(function(a){
    a.addEventListener('click', function(){
      setTimeout(scrollToContent, 150);
    });
  });
})();

// Mobile hamburger toggle
(function(){
  var header = document.querySelector('.mobile-header');
  var hamburger = document.getElementById('hamburger');
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('overlay');
  function syncHeader(){
    if (window.matchMedia('(max-width: 992px)').matches) {
      if (header) header.style.display = 'flex';
      // Ensure sidebar is closed initially on mobile
      if (sidebar) sidebar.classList.remove('open');
      if (overlay){ overlay.classList.remove('show'); overlay.style.display = 'none'; }
      document.body.style.overflow = '';
    } else {
      if (header) header.style.display = 'none';
      if (sidebar) sidebar.classList.remove('open');
      if (overlay) overlay.classList.remove('show');
      if (overlay) overlay.style.display = 'none';
    }
  }
  function openMenu(){
    if (!sidebar) return;
    sidebar.classList.add('open');
    if (overlay){ overlay.classList.add('show'); overlay.style.display = 'block'; }
    if (hamburger) hamburger.setAttribute('aria-expanded','true');
    document.body.style.overflow = 'hidden';
  }
  function closeMenu(){
    if (!sidebar) return;
    sidebar.classList.remove('open');
    if (overlay){ overlay.classList.remove('show'); overlay.style.display = 'none'; }
    if (hamburger) hamburger.setAttribute('aria-expanded','false');
    document.body.style.overflow = '';
  }
  if (hamburger){
    hamburger.addEventListener('click', function(){
      if (sidebar.classList.contains('open')) closeMenu(); else openMenu();
    });
  }
  if (overlay){ overlay.addEventListener('click', closeMenu); }
  window.addEventListener('resize', syncHeader);
  window.addEventListener('load', syncHeader);
  // Close on menu link click (mobile)
  document.querySelectorAll('.menu a').forEach(function(a){
    a.addEventListener('click', function(){
      if (window.matchMedia('(max-width: 992px)').matches) closeMenu();
    });
  });
})();
</script>
</body>
</html>