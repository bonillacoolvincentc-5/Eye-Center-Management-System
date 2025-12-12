<?php
session_start();

if (isset($_SESSION["user"])) {
    if ($_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
        header("location: ../login.php");
        exit();
    }
} else {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

// Fetch admin profile picture
$useremail = $_SESSION["user"];
$profileImage = "../Images/user.png";

// Check if ProfileImage column exists, if not, add it
$checkColumn = $database->query("SHOW COLUMNS FROM tbl_admin LIKE 'ProfileImage'");
if ($checkColumn->num_rows == 0) {
    // Add ProfileImage column if it doesn't exist
    $database->query("ALTER TABLE tbl_admin ADD COLUMN ProfileImage VARCHAR(255) NULL AFTER password");
}

// Now query with ProfileImage column
$stmt = $database->prepare("SELECT admin_id, ProfileImage FROM tbl_admin WHERE email=?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$admin_result = $stmt->get_result();
if ($admin_result->num_rows > 0) {
    $admin_row = $admin_result->fetch_assoc();
    $admin_id = $admin_row["admin_id"];
    if (isset($admin_row['ProfileImage']) && !empty($admin_row['ProfileImage'])) {
        $profilePath = '../Images/profiles/' . $admin_row['ProfileImage'];
        if (file_exists($profilePath)) {
            $profileImage = $profilePath;
        }
    } else {
        // Try file-based approach
        $fileBasedPath = "../Images/profiles/admin_{$admin_id}.jpg";
        if (file_exists($fileBasedPath)) {
            $profileImage = $fileBasedPath;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['block_patient'])) {
        $patient_id = $_POST['patient_id'];
        $sql = "UPDATE tbl_patients SET status='blocked' WHERE Patient_id=?";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("i", $patient_id);
        if ($stmt->execute()) {
            echo "<script>
                alert('Patient account has been blocked successfully.');
                window.location.href = 'patient.php';
            </script>";
        } else {
            echo "<script>
                alert('Failed to block patient account. Please try again.');
                window.location.href = 'patient.php';
            </script>";
        }
    }

    if (isset($_POST['unblock_patient'])) {
        $patient_id = $_POST['patient_id'];
        $sql = "UPDATE tbl_patients SET status='active' WHERE Patient_id=?";
        $stmt = $database->prepare($sql);
        $stmt->bind_param("i", $patient_id);
        if ($stmt->execute()) {
            echo "<script>
                alert('Patient account has been unblocked successfully.');
                window.location.href = 'patient.php';
            </script>";
        } else {
            echo "<script>
                alert('Failed to unblock patient account. Please try again.');
                window.location.href = 'patient.php';
            </script>";
        }
    }

}
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
    <title>Dashboard</title>
    <style>
        .dashbord-tables { animation: transitionIn-Y-over 0.5s; }
        .filter-container { animation: transitionIn-Y-bottom 0.5s; }
        .sub-table { animation: transitionIn-Y-bottom 0.5s; }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-active {
            background-color: #28a745;
            color: white;
        }
        .status-blocked {
            background-color: #dc3545;
            color: white;
        }
        .search-container {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 10px;
            margin-right: 12px;
        }

        .search-input {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 300px;
        }

        .search-btn {
            padding: 8px 15px;
            background-color: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .search-btn:hover {
            background-color: #1976D2;
        }

        /* Sticky table header */
        .abc.scroll {
            overflow-y: auto;
            overflow-x: auto;
        }

        .abc.scroll .sub-table {
            border-collapse: separate;
            border-spacing: 0;
            overflow: visible !important;
        }

        .abc.scroll .sub-table thead {
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .abc.scroll .sub-table thead th {
            background-color: #f8f9fa !important;
            border-bottom: 2px solid #dee2e6;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .success-modal {
            background: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            width: 90%;
            max-width: 400px;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: modalSlideDown 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        @keyframes modalSlideDown {
            from {
                top: 45%;
                opacity: 0;
            }
            to {
                top: 50%;
                opacity: 1;
            }
        }
        
        /* Action buttons container */
        .action-buttons-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-start;
            min-width: 150px;
        }
        
        .action-btn {
            padding: 6px 12px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-align: center;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .action-btn:active {
            transform: translateY(0);
        }
        
        .btn-block {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-block:hover {
            background-color: #c82333;
        }
        
        .btn-unblock {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-unblock:hover {
            background-color: #e0a800;
        }
        
        /* Action column styling */
        .sub-table td:last-child {
            padding: 12px 8px;
            vertical-align: middle;
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
                            <td width="30%" style="padding-left:20px">
                                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                            </td>
                            <td style="padding:0px;margin:0px;">
                                <p class="profile-title">Administrator</p>
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
                <td class="menu-btn menu-icon-dashbord">
                    <a href="index.php" class="non-style-link-menu"><div><p class="menu-text">Dashboard</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-doctor">
                    <a href="doctors.php" class="non-style-link-menu"><div><p class="menu-text">Doctors</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-schedule">
                    <a href="schedule.php" class="non-style-link-menu"><div><p class="menu-text">Schedule</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-appoinment">
                    <a href="appointment.php" class="non-style-link-menu"><div><p class="menu-text">Appointment</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-patient menu-active menu-icon-patient-active">
                    <a href="patient.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Patients</p></div></a>
                </td>
            </tr>
            <tr class="menu-row">
                <td class="menu-btn menu-icon-inventory">
                    <a href="inventory.php" class="non-style-link-menu"><div><p class="menu-text">Inventory</p></div></a>
                </td>
            </tr>
            <tr class="menu-row" >
                <td class="menu-btn menu-icon-settings">
                    <a href="settings.php" class="non-style-link-menu"><div><p class="menu-text">Settings</p></div></a>
                </td>
            </tr>
        </table>
    </div>
    <div class="dash-body" style="margin-top: 15px">
        <table border="0" width="100%" style="border-spacing: 0;margin:0;padding:0;">
            <tr>
                <td colspan="4">
                    <p style="font-size: 23px;padding-left:12px;font-weight: 600;">Patient Accounts Management</p>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <div class="search-container">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search patients...">
                        <button onclick="searchPatients()" class="search-btn">
                            Search
                        </button>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="4">
                    <div class="abc scroll" style="height: 400px;">
                        <table width="100%" class="sub-table scrolldown" border="0">
                            <thead>
                                <tr>
                                    <th class="table-headin">Patient ID</th>
                                    <th class="table-headin">Name</th>
                                    <th class="table-headin">Email</th>
                                    <th class="table-headin">Phone</th>
                                    <th class="table-headin">Status</th>
                                    <th class="table-headin">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sqlmain = "SELECT p.*, 
                                    CONCAT(p.Fname, ' ', COALESCE(p.Mname, ''), ' ', p.Lname, ' ', COALESCE(p.Suffix, '')) as full_name,
                                    COALESCE(p.status, 'active') as account_status
                                    FROM tbl_patients p
                                    ORDER BY p.Patient_id ASC";
                                $result = $database->query($sqlmain);

                                if($result->num_rows > 0) {
                                    while($row = $result->fetch_assoc()) {
                                        $status_class = $row['account_status'] == 'blocked' ? 'status-blocked' : 'status-active';
                                        ?>
                                        <tr>
                                            <td><?php echo $row["Patient_id"] ?></td>
                                            <td><?php echo htmlspecialchars($row["full_name"]) ?></td>
                                            <td><?php echo htmlspecialchars($row["Email"]) ?></td>
                                            <td><?php echo htmlspecialchars($row["PhoneNo"]) ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $status_class ?>">
                                                    <?php echo ucfirst($row['account_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons-container">
                                                    <form method="POST" style="display:inline; width: 100%;">
                                                        <input type="hidden" name="patient_id" value="<?php echo $row["Patient_id"] ?>">
                                                        <?php if($row['account_status'] == 'active'): ?>
                                                            <button type="submit" name="block_patient" 
                                                                class="action-btn btn-block"
                                                                onclick="return confirm('Are you sure you want to block this patient?');">
                                                                Block Account
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="submit" name="unblock_patient" 
                                                                class="action-btn btn-unblock"
                                                                onclick="return confirm('Are you sure you want to unblock this patient?');">
                                                                Unblock Account
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo "<tr>
                                        <td colspan='6' style='text-align:center;padding:20px;'>
                                            <img src='../Images/notfound.svg' width='25%'>
                                            <br>
                                            <p style='padding-top:10px;'>No patients found</p>
                                        </td>
                                    </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</div>
<script>
function searchPatients() {
    let input = document.getElementById('searchInput');
    let filter = input.value.toLowerCase();
    let tbody = document.querySelector('.sub-table tbody');
    let rows = tbody.getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
        let visible = false;
        let cells = rows[i].getElementsByTagName('td');
        
        // Skip the "No patients found" row
        if (cells.length === 1 && cells[0].colSpan === 6) continue;

        for (let j = 0; j < cells.length; j++) {
            let cell = cells[j];
            if (cell.textContent.toLowerCase().indexOf(filter) > -1) {
                visible = true;
                break;
            }
        }
        rows[i].style.display = visible ? '' : 'none';
    }
}

// Add search on Enter key press
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchPatients();
    }
});

function showSuccessModal(message) {
    const modal = document.createElement('div');
    modal.className = 'success-modal';
    
    modal.innerHTML = `
        <div style="color: #28a745; font-size: 24px; margin-bottom: 10px;">✓</div>
        <div style="color: #28a745; font-size: 20px; margin-bottom: 15px;">Success!</div>
        <p>${message}</p>
        <button onclick="window.location.reload()" 
                style="background: #007bff; color: white; border: none; padding: 8px 25px; 
                       border-radius: 4px; cursor: pointer; margin-top: 15px;">OK</button>
    `;
    
    document.body.appendChild(modal);
}
</script>
</body>
</html>