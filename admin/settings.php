<?php
session_start();

if (isset($_SESSION["user"])) {
    if ($_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
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

// Fetch admin info for profile
$stmt = $database->prepare("SELECT * FROM tbl_admin WHERE email=?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$userfetch = $stmt->get_result()->fetch_assoc();

if (!$userfetch) {
    session_destroy();
    header("location: ../login.php");
    exit();
}

$admin_id = $userfetch["admin_id"];
$username = "Administrator"; // Default admin username

// Check if ProfileImage column exists, if not, add it
$checkColumn = $database->query("SHOW COLUMNS FROM tbl_admin LIKE 'ProfileImage'");
if ($checkColumn->num_rows == 0) {
    // Add ProfileImage column if it doesn't exist
    $database->query("ALTER TABLE tbl_admin ADD COLUMN ProfileImage VARCHAR(255) NULL AFTER password");
}

// Profile image path - check if ProfileImage field exists, otherwise use file-based approach
$profileImage = "../Images/user.png";
if (isset($userfetch['ProfileImage']) && !empty($userfetch['ProfileImage'])) {
    $profileImage = '../Images/profiles/' . $userfetch['ProfileImage'];
    if (!file_exists($profileImage)) {
        $profileImage = "../Images/user.png";
    }
} else {
    // Try file-based approach like doctors
    $fileBasedPath = "../Images/profiles/admin_{$admin_id}.jpg";
    if (file_exists($fileBasedPath)) {
        $profileImage = $fileBasedPath;
    }
}

// Handle form submission for profile update
$update_success = false;
$update_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Handle profile image upload (optional)
    if (isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profileImage'];
        $allowed = ['image/jpeg','image/jpg','image/png','image/gif'];
        if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $uploadDir = __DIR__ . '/../Images/profiles/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $newName = 'admin_' . $admin_id . '_' . time() . '.' . $ext;
            $target = $uploadDir . $newName;
            if (move_uploaded_file($file['tmp_name'], $target)) {
                // Optionally remove previous image
                if (isset($userfetch['ProfileImage']) && !empty($userfetch['ProfileImage']) && file_exists($uploadDir . $userfetch['ProfileImage'])) {
                    @unlink($uploadDir . $userfetch['ProfileImage']);
                }
                // Also remove file-based images
                $oldFileBased = $uploadDir . 'admin_' . $admin_id . '.jpg';
                if (file_exists($oldFileBased)) {
                    @unlink($oldFileBased);
                }
                // Update DB with new filename (column should exist now)
                $imgStmt = $database->prepare("UPDATE tbl_admin SET ProfileImage = ? WHERE admin_id = ?");
                $imgStmt->bind_param("si", $newName, $admin_id);
                $imgStmt->execute();
                $imgStmt->close();
                // Refresh user data
                $stmt = $database->prepare("SELECT * FROM tbl_admin WHERE email=?");
                $stmt->bind_param("s", $useremail);
                $stmt->execute();
                $userfetch = $stmt->get_result()->fetch_assoc();
                // Update profile image path
                $profileImage = '../Images/profiles/' . $newName;
                $update_success = true;
            } else {
                $update_error = "Profile image upload failed.";
            }
        } else {
            $update_error = "Invalid profile image (allowed: JPG, PNG, GIF; max 5MB).";
        }
    } else {
        $update_success = true; // No image uploaded, but that's okay
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
    <title>Admin Settings</title>
    <style>
        .dashbord-tables{
            animation: transitionIn-Y-over 0.5s;
        }
        .filter-container{
            animation: transitionIn-X  0.5s;
        }
        .sub-table{
            animation: transitionIn-Y-bottom 0.5s;
        }
        
        /* Mobile responsive styles */
        @media (max-width: 768px) {
            .dash-body {
                padding: 15px !important;
            }
            
            .form-group {
                margin-bottom: 20px !important;
            }
            
            .form-group label {
                display: block !important;
                margin-bottom: 8px !important;
                font-weight: 600 !important;
                color: #333 !important;
            }
            
            .form-control {
                width: 100% !important;
                padding: 12px !important;
                font-size: 16px !important;
                border: 2px solid #e1e5e9 !important;
                border-radius: 8px !important;
                box-sizing: border-box !important;
            }
            
            .btn-update, .btn-primary {
                width: 100% !important;
                padding: 15px !important;
                font-size: 16px !important;
                font-weight: 600 !important;
                border-radius: 8px !important;
                margin-top: 10px !important;
            }
            
            .section-title {
                font-size: 20px !important;
                margin-bottom: 20px !important;
                color: #333 !important;
                font-weight: 600 !important;
            }
            
            .alert {
                margin: 0 0 20px 0 !important;
                padding: 15px !important;
                border-radius: 8px !important;
                font-size: 14px !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="menu">
            <table class="menu-container" border="0">
                <tr>
                    <td style="padding:10px" colspan="2">
                        <table border="0" class="profile-container">
                            <tr>
                                <td width="30%" style="padding-left:20px" >
                                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title"><?php echo htmlspecialchars($username); ?></p>
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
                    <td class="menu-btn menu-icon-dashbord" >
                        <a href="index.php" class="non-style-link-menu"><div><p class="menu-text">Dashboard</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor ">
                        <a href="doctors.php" class="non-style-link-menu "><div><p class="menu-text">Doctors</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-schedule">
                        <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Schedule</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">Appointment</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-patient">
                        <a href="patient.php" class="non-style-link-menu"><div><p class="menu-text">Patients</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-inventory">
                        <a href="inventory.php" class="non-style-link-menu"><div><p class="menu-text">Inventory</p></div></a>
                    </td>
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-settings menu-active menu-icon-settings-active">
                        <a href="settings.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Settings</p></div></a>
                    </td>
                </tr>
            </table>
        </div>
        <div class="dash-body" style="margin-top: 15px">
            <table border="0" width="100%" style=" border-spacing: 0;margin:0;padding:0;" >
                <tr>
                    <td width="13%">
                        <a href="index.php" ><button class="login-btn btn-primary-soft btn btn-icon-back" style="padding-top:11px;padding-bottom:11px;margin-left:20px;width:125px"><font class="tn-in-text">Back</font></button></a>
                    </td>
                    <td>
                        <div style="margin: 20px 0;">
                            <h2 style="font-size: 23px;padding-left:12px;font-weight: 600;margin: 0;">Settings</h2>
                            <p style="padding-left:12px;color: #666;margin: 5px 0 0 0;">Here you can update your profile picture or change your password.</p>
                        </div>
                    </td>
                    <td width="15%">
                        <p style="font-size: 14px;color: rgb(119, 119, 119);padding: 0;margin: 0;text-align: right;">
                            Today's Date
                        </p>
                        <p class="heading-sub12" style="padding: 0;margin: 0;">
                            <?php echo $date; ?>
                        </p>
                    </td>
                    <td width="10%">
                        <button class="btn-label" style="display: flex;justify-content: center;align-items: center;"><img src="../Images/calendar.svg" width="100%"></button>
                    </td>
                </tr>
                <tr>
                    <td colspan="4">
                        <!-- Success/Error Messages -->
                        <?php if ($update_success): ?>
                            <div class="alert alert-success" style="margin: 20px 40px; padding: 15px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 8px;">
                                Profile picture updated successfully!
                            </div>
                        <?php elseif ($update_error): ?>
                            <div class="alert alert-error" style="margin: 20px 40px; padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 8px;">
                                <?php echo htmlspecialchars($update_error); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Profile Information section -->
                        <div style="margin: 40px;">
                            <h3 class="section-title">Profile Picture</h3>
                            
                            <form method="POST" action="" class="profile-form" id="profileForm" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label>Current Profile Picture</label>
                                    <div style="display:flex;align-items:center;gap:15px;margin-bottom:20px;">
                                        <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Current" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:2px solid #eee;">
                                        <div>
                                            <input type="file" name="profileImage" accept="image/jpeg,image/jpg,image/png,image/gif" class="form-control" style="width:auto;">
                                            <p style="font-size:12px;color:#666;margin:6px 0 0;">Optional. JPG/PNG/GIF, max 5MB. Leave empty to keep current image.</p>
                                        </div>
                                    </div>
                                </div>

                                <div style="text-align: right; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                                    <button type="submit" name="update_profile" class="btn-update login-btn btn-primary btn">
                                        Update Profile Picture
                                    </button>
                                </div>
                            </form>

                            <hr style="margin: 40px 0; border: none; border-top: 1px solid #eee;">
                            
                            <h3 class="section-title">Change Password</h3>
                            <form action="../createnew-password.php" method="POST" style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                                <input type="hidden" name="reset_email" value="<?php echo htmlspecialchars($useremail); ?>">
                                <input type="hidden" name="user_type" value="admin">
                                <p style="margin-bottom: 20px; color: #666;">Click the button below to change your password.</p>
                                <div style="text-align: right;">
                                    <button type="submit" name="change_password" class="login-btn btn-primary btn">Change Password</button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>

