<?php
session_start();

if (isset($_SESSION["user"])) {
    if ($_SESSION["user"] == "" || $_SESSION['usertype'] != 'p') {
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

// Fetch patient info for profile
$stmt = $database->prepare("SELECT * FROM tbl_patients WHERE Email=?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$userfetch = $stmt->get_result()->fetch_assoc();

if (!$userfetch) {
    session_destroy();
    header("location: ../login.php");
    exit();
}

$userid = $userfetch["Patient_id"];
$username = $userfetch["Fname"]; // Just first name

// NEW: profile image path (use default if not set)
$profileImage = !empty($userfetch['ProfileImage']) ? '../Images/profiles/' . $userfetch['ProfileImage'] : '../Images/user.png';

// Fetch regions for dropdown
$region_options = '';
$region_query = $database->query("SELECT region_id, region_name FROM tbl_region ORDER BY region_name ASC");
while ($row = $region_query->fetch_assoc()) {
    $selected = ($row['region_id'] == $userfetch['Region']) ? 'selected' : '';
    $region_options .= '<option value="' . htmlspecialchars($row['region_id']) . '" ' . $selected . '>' . htmlspecialchars($row['region_name']) . '</option>';
}

// Handle form submission for profile update
$update_success = false;
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Sanitize and validate input data
    // Names and birthdate are not editable: use existing DB values
    $fname = $userfetch['Fname'];
    $mname = $userfetch['Mname'];
    $lname = $userfetch['Lname'];
    $suffix = $userfetch['Suffix'];
    $region_id = $_POST['region'];
    $province_id = $_POST['province'];
    $city_id = $_POST['city'];
    $barangay_id = $_POST['barangay'];
    $street = trim($_POST['street']);
    $birthdate = $userfetch['Birthdate'];
    $phone = trim($_POST['phone']);
    
    // Get the actual names from the database based on the IDs
    $region_name = '';
    if (!empty($region_id)) {
        $region_stmt = $database->prepare("SELECT region_name FROM tbl_region WHERE region_id = ?");
        $region_stmt->bind_param("s", $region_id);
        $region_stmt->execute();
        $region_result = $region_stmt->get_result();
        if ($region_row = $region_result->fetch_assoc()) {
            $region_name = $region_row['region_name'];
        }
    }
    
    $province_name = '';
    if (!empty($province_id)) {
        $province_stmt = $database->prepare("SELECT province_name FROM tbl_province WHERE province_id = ?");
        $province_stmt->bind_param("s", $province_id);
        $province_stmt->execute();
        $province_result = $province_stmt->get_result();
        if ($province_row = $province_result->fetch_assoc()) {
            $province_name = $province_row['province_name'];
        }
    }
    
    $city_name = '';
    if (!empty($city_id)) {
        $city_stmt = $database->prepare("SELECT municipality_name FROM tbl_municipality WHERE municipality_id = ?");
        $city_stmt->bind_param("s", $city_id);
        $city_stmt->execute();
        $city_result = $city_stmt->get_result();
        if ($city_row = $city_result->fetch_assoc()) {
            $city_name = $city_row['municipality_name'];
        }
    }
    
    $barangay_name = '';
    if (!empty($barangay_id)) {
        $barangay_stmt = $database->prepare("SELECT barangay_name FROM tbl_barangay WHERE barangay_id = ?");
        $barangay_stmt->bind_param("s", $barangay_id);
        $barangay_stmt->execute();
        $barangay_result = $barangay_stmt->get_result();
        if ($barangay_row = $barangay_result->fetch_assoc()) {
            $barangay_name = $barangay_row['barangay_name'];
        }
    }
    
    // Basic validation
    if (empty($fname) || empty($lname) || empty($phone) || empty($birthdate)) {
        $update_error = "Please fill in all required fields (First Name, Last Name, Phone, Birthdate)";
    } elseif (strlen($phone) < 11 || !is_numeric($phone)) {
        $update_error = "Phone number must be exactly 11 digits";
    } else {
        // Update query
        $update_sql = "UPDATE tbl_patients SET 
            Fname = ?, Mname = ?, Lname = ?, Suffix = ?, 
            Province = ?, `City/Municipality` = ?, Barangay = ?, Street = ?, 
            Birthdate = ?, PhoneNo = ?, Region = ?
            WHERE Patient_id = ?";
        
        $update_stmt = $database->prepare($update_sql);
        $update_stmt->bind_param("sssssssssssi", 
            $fname, $mname, $lname, $suffix,
            $province_name, $city_name, $barangay_name, $street,
            $birthdate, $phone, $region_name, $userid
        );
        
        if ($update_stmt->execute()) {
            $update_success = true;
            // Refresh user data
            $stmt = $database->prepare("SELECT * FROM tbl_patients WHERE Email=?");
            $stmt->bind_param("s", $useremail);
            $stmt->execute();
            $userfetch = $stmt->get_result()->fetch_assoc();
            
            // Update username to just first name
            $username = $userfetch["Fname"];
            
            // NEW: handle profile image upload (optional)
            if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['profileImage'];
                $allowed = ['image/jpeg','image/jpg','image/png','image/gif'];
                if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowed) && $file['size'] <= 5 * 1024 * 1024) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $uploadDir = __DIR__ . '/../Images/profiles/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $newName = $userid . '_' . time() . '.' . $ext;
                    $target = $uploadDir . $newName;
                    if (move_uploaded_file($file['tmp_name'], $target)) {
                        // Optionally remove previous image
                        if (!empty($userfetch['ProfileImage']) && file_exists($uploadDir . $userfetch['ProfileImage'])) {
                            @unlink($uploadDir . $userfetch['ProfileImage']);
                        }
                        // Update DB with new filename
                        $imgStmt = $database->prepare("UPDATE tbl_patients SET ProfileImage = ? WHERE Patient_id = ?");
                        $imgStmt->bind_param("si", $newName, $userid);
                        $imgStmt->execute();
                        $imgStmt->close();
                        // Refresh user data again
                        $stmt = $database->prepare("SELECT * FROM tbl_patients WHERE Email=?");
                        $stmt->bind_param("s", $useremail);
                        $stmt->execute();
                        $userfetch = $stmt->get_result()->fetch_assoc();
                    } else {
                        $update_error .= " Profile image upload failed.";
                    }
                } else {
                    $update_error .= " Invalid profile image (allowed: JPG, PNG, GIF; max 5MB).";
                }
            }
            
            // Refresh region options with potentially new selection
            $region_options = '';
            $region_query = $database->query("SELECT region_id, region_name FROM tbl_region ORDER BY region_name ASC");
            while ($row = $region_query->fetch_assoc()) {
                $selected = ($row['region_name'] == $region_name) ? 'selected' : '';
                $region_options .= '<option value="' . htmlspecialchars($row['region_id']) . '" ' . $selected . '>' . htmlspecialchars($row['region_name']) . '</option>';
            }
        } else {
            $update_error = "Error updating profile: " . $database->error;
        }
    }
}

$date = date('Y-m-d');
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
    <link rel="stylesheet" href="../css/psettings.css">
    <title>Patient Settings</title>
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
        /* Mobile hamburger + centered layout */
        @media (max-width: 992px){
            .mobile-header{ display:flex !important; align-items:center; justify-content:space-between; padding:12px 16px; background:#fff; border-bottom:1px solid #eaeaea; position:sticky; top:0; z-index:1001; width:100%; box-sizing:border-box; max-width:560px; margin:0 auto; }
            .hamburger{ width:28px; height:22px; position:relative; cursor:pointer; }
            .hamburger span{ position:absolute; left:0; right:0; height:3px; background:#333; border-radius:2px; transition:.3s; }
            .hamburger span:nth-child(1){ top:0; }
            .hamburger span:nth-child(2){ top:9px; }
            .hamburger span:nth-child(3){ bottom:0; }
            .mobile-title{ font-weight:600; color:#161c2d; }
            .container{ height:auto; flex-direction:column; }
            .menu{ width:260px; height:100vh; position:fixed; top:0; left:-280px; background:#fff; z-index:1002; overflow-y:auto; transition:left .3s ease; box-shadow:2px 0 12px rgba(0,0,0,.06); }
            .menu.open{ left:0; }
            .dash-body{ width:100% !important; padding:15px; max-width:560px; margin:0 auto; box-sizing:border-box; }
            .overlay{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1000; }
            .overlay.show{ display:block; }
        }
        
        /* Mobile-specific form layout fixes */
        @media (max-width: 768px) {
            html, body { overflow-x: hidden; }
            
            /* Fix main content spacing */
            .dash-body {
                padding: 10px !important;
                margin: 0 auto !important;
                max-width: 100% !important;
            }
            
            /* Hide desktop header elements on mobile */
            .header-table {
                display: none !important;
            }
            
            /* Mobile form layout improvements */
            .form-row {
                display: block !important;
                margin-bottom: 0 !important;
            }
            
            .form-col {
                width: 100% !important;
                margin-bottom: 15px !important;
                padding: 0 !important;
            }
            
            /* Profile picture section mobile layout */
            .form-group {
                margin-bottom: 20px !important;
            }
            
            .form-group label {
                display: block !important;
                margin-bottom: 8px !important;
                font-weight: 600 !important;
                color: #333 !important;
            }
            
            /* Profile picture mobile layout */
            .form-group div[style*="display:flex"] {
                display: block !important;
                text-align: center !important;
            }
            
            .form-group div[style*="display:flex"] img {
                margin: 0 auto 15px auto !important;
                display: block !important;
            }
            
            .form-group div[style*="display:flex"] div {
                width: 100% !important;
            }
            
            /* Form inputs mobile optimization */
            .form-control {
                width: 100% !important;
                padding: 12px !important;
                font-size: 16px !important; /* Prevents zoom on iOS */
                border: 2px solid #e1e5e9 !important;
                border-radius: 8px !important;
                box-sizing: border-box !important;
            }
            
            .form-control:focus {
                border-color: #007bff !important;
                outline: none !important;
                box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1) !important;
            }
            
            /* Searchable select mobile optimization */
            .searchable-select {
                position: relative !important;
            }
            
            .searchable-select input[type="text"] {
                width: 100% !important;
                padding: 12px !important;
                font-size: 16px !important;
                border: 2px solid #e1e5e9 !important;
                border-radius: 8px !important;
                margin-bottom: 8px !important;
                box-sizing: border-box !important;
            }
            
            .searchable-select select {
                width: 100% !important;
                padding: 12px !important;
                font-size: 16px !important;
                border: 2px solid #e1e5e9 !important;
                border-radius: 8px !important;
                background-color: white !important;
                box-sizing: border-box !important;
            }
            
            /* Textarea mobile optimization */
            textarea.form-control {
                min-height: 80px !important;
                resize: vertical !important;
            }
            
            /* Button mobile optimization */
            .btn-update, .btn-primary {
                width: 100% !important;
                padding: 15px !important;
                font-size: 16px !important;
                font-weight: 600 !important;
                border-radius: 8px !important;
                margin-top: 10px !important;
            }
            
            /* Section titles mobile optimization */
            .section-title {
                font-size: 20px !important;
                margin-bottom: 20px !important;
                color: #333 !important;
                font-weight: 600 !important;
            }
            
            /* Alert messages mobile optimization */
            .alert {
                margin: 0 0 20px 0 !important;
                padding: 15px !important;
                border-radius: 8px !important;
                font-size: 14px !important;
            }
            
            /* Settings title mobile optimization */
            h2 {
                font-size: 24px !important;
                margin-bottom: 10px !important;
                color: #333 !important;
            }
            
            /* Password section mobile optimization */
            form[action="../createnew-password.php"] {
                padding: 20px !important;
                margin: 20px 0 !important;
            }
            
            /* Required field indicators */
            .required::after {
                content: " *" !important;
                color: #dc3545 !important;
                font-weight: bold !important;
            }
            
            /* Small text mobile optimization */
            small {
                display: block !important;
                margin-top: 5px !important;
                font-size: 12px !important;
                color: #666 !important;
            }
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
        <div class="mobile-title">Settings</div>
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
                            <!-- Replace existing profile image tag in menu area with dynamic image -->
                            <td width="30%" style="padding-left:20px" >
                                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                            </td>
                            <td style="padding:0px;margin:0px;">
                                <p class="profile-title"><?php echo htmlspecialchars(substr($username,0,13)) ?></p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <a href="../logout.php" ><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="menu-row" >
                <td class="menu-btn menu-icon-home">
                    <a href="index.php" class="non-style-link-menu"><div><p class="menu-text">Home</p></div></a>
                </td>
            </tr>
            <tr class="menu-row" >
                <td class="menu-btn menu-icon-session">
                    <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Book Appointment</p></div></a>
                </td>
            </tr>
            <tr class="menu-row" >
                <td class="menu-btn menu-icon-appoinment">
                    <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">My Bookings</p></div></a>
                </td>
            </tr>
            <tr class="menu-row" >
                <td class="menu-btn menu-icon-settings menu-active menu-icon-settings-active">
                    <a href="settings.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Settings</p></div></a>
                </td>
            </tr>
        </table>
    </div>
    <div class="dash-body" id="content">
        <table border="0" width="100%" class="header-table">
            <tr>
                <td width="13%">
                    <a href="index.php" ><button class="login-btn btn-primary-soft btn btn-icon-back" style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px"><font class="tn-in-text">Back</font></button></a>
                </td>
                <td>
                    <div class="date-container">
                        <div class="date-text">
                            <p style="font-size: 14px;color: rgb(119, 119, 119);padding: 0;margin: 0;">
                                Today's Date
                            </p>
                            <p class="heading-sub12" style="padding: 0;margin: 0;">
                                <?php echo $date; ?>
                            </p>
                        </div>
                        <button class="btn-label" style="display: flex;justify-content: center;align-items: center;">
                            <img src="../Images/calendar.svg" width="100%">
                        </button>
                    </div>
                </td>
            </tr>
        </table>
        
        <!-- Settings title and description below the back button -->
        <div style="margin: 40px; margin-top: 20px;">
            <h2>Settings</h2>
            <p>Here you can update your profile or change your password.</p>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if ($update_success): ?>
            <div class="alert alert-success" style="margin: 0 40px 20px 40px;">
                Profile updated successfully!
            </div>
        <?php elseif ($update_error): ?>
            <div class="alert alert-error" style="margin: 0 40px 20px 40px;">
                <?php echo htmlspecialchars($update_error); ?>
            </div>
        <?php endif; ?>

        <!-- Profile Information section -->
        <div style="margin: 40px;">
            <h3 class="section-title">Profile Information</h3>
            
            <form method="POST" action="" class="profile-form" id="profileForm" enctype="multipart/form-data">

            <!-- Inside the Profile Information form add a profile upload field (place near other inputs) -->
                <div class="form-group">
                    <label>Profile Picture</label>
                    <div style="display:flex;align-items:center;gap:15px;">
                        <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Current" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:1px solid #eee;">
                        <div>
                            <input type="file" name="profileImage" accept="image/jpeg,image/jpg,image/png,image/gif">
                            <p style="font-size:12px;color:#666;margin:6px 0 0;">Optional. JPG/PNG/GIF, max 5MB. Leave empty to keep current image.</p>
                        </div>
                    </div>
                </div>

                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="fname" class="required">First Name</label>
                            <input type="text" id="fname" name="fname" class="form-control" 
                                   value="<?php echo htmlspecialchars($userfetch['Fname']); ?>" required disabled style="background:#f8f9fa;color:#666;">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="mname">Middle Name</label>
                            <input type="text" id="mname" name="mname" class="form-control" 
                                   value="<?php echo htmlspecialchars($userfetch['Mname']); ?>" disabled style="background:#f8f9fa;color:#666;">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="lname" class="required">Last Name</label>
                            <input type="text" id="lname" name="lname" class="form-control" 
                                   value="<?php echo htmlspecialchars($userfetch['Lname']); ?>" required disabled style="background:#f8f9fa;color:#666;">
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="suffix">Suffix</label>
                            <input type="text" id="suffix" name="suffix" class="form-control" 
                                   value="<?php echo htmlspecialchars($userfetch['Suffix']); ?>" disabled style="background:#f8f9fa;color:#666;">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="birthdate" class="required">Birthdate</label>
                    <input type="date" id="birthdate" name="birthdate" class="form-control" 
                           value="<?php echo htmlspecialchars($userfetch['Birthdate']); ?>" required disabled style="background:#f8f9fa;color:#666;">
                </div>

                <div class="form-group">
                    <label for="phone" class="required">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           value="<?php echo htmlspecialchars($userfetch['PhoneNo']); ?>" 
                           pattern="[0-9]{11}" maxlength="11" required
                           oninput="this.value = this.value.replace(/[^0-9]/g, '').substr(0, 11);">
                    <small style="color: #666; font-size: 12px;">Format: 09123456789 (11 digits)</small>
                </div>

                <!-- Region Dropdown -->
                <div class="form-group">
                    <label for="region" class="required">Region</label>
                    <div class="searchable-select">
                        <select name="region" id="region" class="form-control" required onchange="loadProvinces(this.value)">
                            <option value="">Select Region</option>
                            <?php echo $region_options; ?>
                        </select>
                    </div>
                </div>

                <!-- Province Dropdown -->
                <div class="form-group">
                    <label for="province">Province</label>
                    <div class="searchable-select">
                        <select name="province" id="province" class="form-control" onchange="loadMunicipalities(this.value)">
                            <option value="">Select Province</option>
                            <?php
                            // If user has a province selected, we need to load it via AJAX later
                            if (!empty($userfetch['Province'])) {
                                echo '<option value="loading">Loading...</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- Municipality Dropdown -->
                <div class="form-group">
                    <label for="city">City/Municipality</label>
                    <div class="searchable-select">
                        <select name="city" id="municipality" class="form-control" onchange="loadBarangays(this.value)">
                            <option value="">Select Municipality/City</option>
                            <?php
                            // If user has a city selected, we need to load it via AJAX later
                            if (!empty($userfetch['City/Municipality'])) {
                                echo '<option value="loading">Loading...</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <!-- Barangay Dropdown -->
                <div class="form-group">
                    <label for="barangay">Barangay</label>
                    <div class="searchable-select">
                        <select name="barangay" id="barangay" class="form-control">
                            <option value="">Select Barangay</option>
                            <?php
                            // If user has a barangay selected, we need to load it via AJAX later
                            if (!empty($userfetch['Barangay'])) {
                                echo '<option value="loading">Loading...</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="street">Street Address</label>
                    <textarea id="street" name="street" class="form-control" rows="3"><?php echo htmlspecialchars($userfetch['Street']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="email" style="color: #666;">Email Account (Cannot be changed)</label>
                    <input type="email" id="email" class="form-control" 
                           value="<?php echo htmlspecialchars($userfetch['Email']); ?>" disabled
                           style="background-color: #f8f9fa; color: #666;">
                </div>


                <div style="text-align: right; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                    <button type="submit" name="update_profile" class="btn-update">
                        Update Profile
                    </button>
                </div>
            </form>

            <hr style="margin: 40px 0; border: none; border-top: 1px solid #eee;">
            
            <h3 class="section-title">Change Password</h3>
            <form action="../createnew-password.php" method="POST" style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <input type="hidden" name="reset_email" value="<?php echo htmlspecialchars($useremail); ?>">
                <p style="margin-bottom: 20px; color: #666;">Click the button below to change your password.</p>
                <div style="text-align: right;">
                    <button type="submit" name="change_password" class="login-btn btn-primary btn">Change Password</button>
                </div>
            </form>
        </div>
    </div>
    </div>

<script>
    // Filter dropdown options by search input
    function filterOptions(searchId, selectId) {
        var input = document.getElementById(searchId).value.toLowerCase();
        var select = document.getElementById(selectId);
        for (var i = 0; i < select.options.length; i++) {
            var txt = select.options[i].text.toLowerCase();
            select.options[i].style.display = txt.includes(input) ? '' : 'none';
        }
    }

    // AJAX loaders for cascading dropdowns
    function loadProvinces(region_id) {
        var provinceSelect = document.getElementById('province');
        provinceSelect.innerHTML = '<option value="">Select Province</option>';
        document.getElementById('municipality').innerHTML = '<option value="">Select Municipality/City</option>';
        document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
        if (!region_id) return; // Don't fetch if nothing is selected

        console.log('Loading provinces for region:', region_id);
        
        fetch('../ajax-address.php?type=province&region=' + encodeURIComponent(region_id))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Provinces data:', data);
                provinceSelect.innerHTML = '<option value="">Select Province</option>';
                if (data && data.length > 0) {
                    data.forEach(function(item) {
                        var opt = document.createElement('option');
                        // Use the property names from ajax-address.php (province_code instead of province_id)
                        opt.value = item.province_code || item.province_id || item.id || item.value;
                        opt.text = item.province_name || item.name || item.text;
                        provinceSelect.appendChild(opt);
                    });
                    
                    // Try to select the user's current province if available
                    <?php if (!empty($userfetch['Province'])): ?>
                    setTimeout(function() {
                        var userProvince = "<?php echo $userfetch['Province']; ?>";
                        for (var i = 0; i < provinceSelect.options.length; i++) {
                            var opt = provinceSelect.options[i];
                            if (opt.text === userProvince || String(opt.value) === String(userProvince)) {
                                provinceSelect.selectedIndex = i;
                                console.log('Selected province:', opt.text, '(value:', opt.value, ')');
                                loadMunicipalities(provinceSelect.value);
                                break;
                            }
                        }
                    }, 150);
                    <?php endif; ?>
                } else {
                    console.error('No provinces returned from server');
                    provinceSelect.innerHTML = '<option value="">No provinces found</option>';
                }
            })
            .catch(error => {
                console.error('Error loading provinces:', error);
                provinceSelect.innerHTML = '<option value="">Error loading provinces</option>';
            });
    }

    function loadMunicipalities(province_code) {
        var municipalitySelect = document.getElementById('municipality');
        municipalitySelect.innerHTML = '<option value="">Loading...</option>';
        
        if (!province_code) {
            municipalitySelect.innerHTML = '<option value="">Select Municipality/City</option>';
            return;
        }

        console.log('Loading municipalities for province:', province_code);
        
        fetch('../ajax-address.php?type=municipality&province=' + encodeURIComponent(province_code))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Municipalities data:', data);
                municipalitySelect.innerHTML = '<option value="">Select Municipality/City</option>';
                if (data && data.length > 0) {
                    data.forEach(function(item) {
                        var opt = document.createElement('option');
                        // Use the property names from ajax-address.php (municipality_code instead of municipality_id)
                        opt.value = item.municipality_code || item.municipality_id || item.id || item.value;
                        opt.text = item.municipality_name || item.name || item.text;
                        municipalitySelect.appendChild(opt);
                    });
                    
                    // Try to select the user's current municipality if available
                    <?php if (!empty($userfetch['City/Municipality'])): ?>
                    setTimeout(function() {
                        var userMunicipality = "<?php echo $userfetch['City/Municipality']; ?>";
                        for (var i = 0; i < municipalitySelect.options.length; i++) {
                            var opt = municipalitySelect.options[i];
                            if (opt.text === userMunicipality || String(opt.value) === String(userMunicipality)) {
                                municipalitySelect.selectedIndex = i;
                                console.log('Selected municipality:', opt.text, '(value:', opt.value, ')');
                                loadBarangays(municipalitySelect.value);
                                break;
                            }
                        }
                    }, 150);
                    <?php endif; ?>
                } else {
                    console.error('No municipalities returned from server');
                    municipalitySelect.innerHTML = '<option value="">No municipalities found</option>';
                }
            })
            .catch(error => {
                console.error('Error loading municipalities:', error);
                municipalitySelect.innerHTML = '<option value="">Error loading municipalities</option>';
            });
    }

    function loadBarangays(municipality_code) {
        var barangaySelect = document.getElementById('barangay');
        barangaySelect.innerHTML = '<option value="">Loading...</option>';
        
        if (!municipality_code) {
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            return;
        }

        console.log('Loading barangays for municipality:', municipality_code);
        
        fetch('../ajax-address.php?type=barangay&municipality=' + encodeURIComponent(municipality_code))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Barangays data:', data);
                barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
                if (data && data.length > 0) {
                    data.forEach(function(item) {
                        var opt = document.createElement('option');
                        // Use the property names from ajax-address.php (barangay_code instead of barangay_id)
                        opt.value = item.barangay_code || item.barangay_id || item.id || item.value;
                        opt.text = item.barangay_name || item.name || item.text;
                        barangaySelect.appendChild(opt);
                    });
                    
                    // Try to select the user's current barangay if available
                    <?php if (!empty($userfetch['Barangay'])): ?>
                    setTimeout(function() {
                        var userBarangay = "<?php echo $userfetch['Barangay']; ?>";
                        for (var i = 0; i < barangaySelect.options.length; i++) {
                            var opt = barangaySelect.options[i];
                            if (opt.text === userBarangay || String(opt.value) === String(userBarangay)) {
                                barangaySelect.selectedIndex = i;
                                console.log('Selected barangay:', opt.text, '(value:', opt.value, ')');
                                break;
                            }
                        }
                    }, 150);
                    <?php endif; ?>
                } else {
                    console.error('No barangays returned from server');
                    barangaySelect.innerHTML = '<option value="">No barangays found</option>';
                }
            })
            .catch(error => {
                console.error('Error loading barangays:', error);
                barangaySelect.innerHTML = '<option value="">Error loading barangays</option>';
            });
    }

    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('profileForm');
        const phoneInput = document.getElementById('phone');
        
        // Initialize province dropdown if region is already selected
        <?php if (!empty($userfetch['Region'])): ?>
        const regionSelect = document.getElementById('region');
        for (var i = 0; i < regionSelect.options.length; i++) {
            if (regionSelect.options[i].text === "<?php echo $userfetch['Region']; ?>") {
                regionSelect.selectedIndex = i;
                console.log('Initializing with region:', regionSelect.value);
                loadProvinces(regionSelect.value);
                break;
            }
        }
        <?php endif; ?>
        
        form.addEventListener('submit', function(e) {
            // Validate phone number
            if (phoneInput.value.length !== 11 || !/^\d+$/.test(phoneInput.value)) {
                e.preventDefault();
                alert('Phone number must be exactly 11 digits');
                phoneInput.focus();
                return false;
            }
            
            // Validate birthdate (not in future)
            const birthdate = new Date(document.getElementById('birthdate').value);
            const today = new Date();
            if (birthdate > today) {
                e.preventDefault();
                alert('Birthdate cannot be in the future');
                return false;
            }
        });
        
        // Auto-format phone number
        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substr(0, 11);
        });
        
        // Reset all dropdowns on form reset
        form.addEventListener('reset', function() {
            document.getElementById('province').innerHTML = '<option value="">Select Province</option>';
            document.getElementById('municipality').innerHTML = '<option value="">Select Municipality/City</option>';
            document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
        });
    });
// Ensure content is visible after nav on mobile
(function(){
  function scrollToContent(){ var el=document.getElementById('content'); if(!el) return; if (window.matchMedia('(max-width: 768px)').matches){ el.scrollIntoView({behavior:'smooth',block:'start'});} }
  window.addEventListener('load',scrollToContent);
  document.querySelectorAll('.menu a').forEach(function(a){ a.addEventListener('click', function(){ setTimeout(scrollToContent,150); }); });
})();
// Mobile hamburger toggle
(function(){
  var header=document.querySelector('.mobile-header');
  var hamburger=document.getElementById('hamburger');
  var sidebar=document.getElementById('sidebar');
  var overlay=document.getElementById('overlay');
  function syncHeader(){ if (window.matchMedia('(max-width: 992px)').matches){ if(header) header.style.display='flex'; if(sidebar) sidebar.classList.remove('open'); if(overlay){ overlay.classList.remove('show'); overlay.style.display='none'; } document.body.style.overflow=''; } else { if(header) header.style.display='none'; if(sidebar) sidebar.classList.remove('open'); if(overlay){ overlay.classList.remove('show'); overlay.style.display='none'; } } }
  function openMenu(){ if(!sidebar) return; sidebar.classList.add('open'); if(overlay){ overlay.classList.add('show'); overlay.style.display='block'; } if(hamburger) hamburger.setAttribute('aria-expanded','true'); document.body.style.overflow='hidden'; }
  function closeMenu(){ if(!sidebar) return; sidebar.classList.remove('open'); if(overlay){ overlay.classList.remove('show'); overlay.style.display='none'; } if(hamburger) hamburger.setAttribute('aria-expanded','false'); document.body.style.overflow=''; }
  if(hamburger){ hamburger.addEventListener('click', function(){ sidebar.classList.contains('open')?closeMenu():openMenu(); }); }
  if(overlay){ overlay.addEventListener('click', closeMenu); }
  window.addEventListener('resize', syncHeader); window.addEventListener('load', syncHeader);
  document.querySelectorAll('.menu a').forEach(function(a){ a.addEventListener('click', function(){ if (window.matchMedia('(max-width: 992px)').matches) closeMenu(); }); });
})();
</script>
</body>
</html>