<?php
session_start();
date_default_timezone_set('Asia/Manila');
$_SESSION["date"] = date('Y-m-d');
include("connection.php");

// Fetch regions for dropdown
$region_options = '';
$region_query = $database->query("SELECT region_id, region_name FROM tbl_region ORDER BY region_name ASC");
while ($row = $region_query->fetch_assoc()) {
    $region_options .= '<option value="' . htmlspecialchars($row['region_id']) . '">' . htmlspecialchars($row['region_name']) . '</option>';
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = $_POST['fname'];
    $mname = $_POST['mname'];
    $lname = $_POST['lname'];
    $suffix = $_POST['suffix'];
    $region = $_POST['region'];
    $province = $_POST['province'];
    $city = $_POST['city'];
    $barangay = $_POST['barangay'];
    $street = $_POST['street'];
    $dob = $_POST['dob'];

    // Basic validation (add more as needed)
    if (
        empty($fname) || empty($lname) || empty($region) ||
        empty($province) || empty($city) || empty($barangay) ||
        empty($street) || empty($dob)
    ) {
        $message = '<span style="color:red;">Please fill in all required fields.</span>';
    } else {
        // Save to session for next step (e.g., create-account.php)
        $_SESSION['personal'] = [
            'fname' => $fname,
            'mname' => $mname,
            'lname' => $lname,
            'suffix' => $suffix,
            'region' => $region,
            'province' => $province,
            'city' => $city,
            'barangay' => $barangay,
            'street' => $street,
            'dob' => $dob
        ];
        // Redirect to next step (e.g., create-account.php)
        header("Location: create-account.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/animations.css">  
    <link rel="stylesheet" href="css/main.css">  
    <link rel="stylesheet" href="css/signup.css">
    <title>Sign Up</title>

    <!-- Mobile responsive styles -->
    <style>
        .logo-container { text-align: center; margin: 28px 0 8px; }
        .logo-img { max-width: 100px; height: auto; display: inline-block; border-radius: 8px; }
        
        /* Mobile responsive fixes */
        @media (max-width: 768px) {
            body {
                margin: 0;
                padding: 10px;
                background-color: #F6F7FA;
            }
            
            .container {
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 20px 15px !important;
                box-sizing: border-box;
            }
            
            table {
                width: 100% !important;
                margin: 0 !important;
            }
            
            .header-text {
                font-size: 24px !important;
                margin-bottom: 8px !important;
            }
            
            .sub-text {
                font-size: 14px !important;
                margin-bottom: 20px !important;
            }
            
            .form-label {
                font-size: 16px !important;
                font-weight: 600 !important;
                margin-bottom: 8px !important;
                display: block;
            }
            
            .input-text {
                width: 100% !important;
                padding: 12px !important;
                font-size: 16px !important; /* Prevents zoom on iOS */
                border: 2px solid #e1e5e9 !important;
                border-radius: 8px !important;
                box-sizing: border-box !important;
                margin-bottom: 10px !important;
            }
            
            .input-text:focus {
                border-color: #007bff !important;
                outline: none !important;
                box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1) !important;
            }
            
            .searchable-select {
                width: 100% !important;
                margin-bottom: 10px !important;
            }
            
            .searchable-select input {
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
            
            .login-btn {
                width: 100% !important;
                padding: 15px !important;
                font-size: 16px !important;
                font-weight: 600 !important;
                border-radius: 8px !important;
                margin: 5px 0 !important;
                box-sizing: border-box !important;
            }
            
            .label-td {
                padding: 5px 0 !important;
            }
            
            /* Button row - stack vertically */
            tr:has(.login-btn) {
                display: block !important;
            }
            
            tr:has(.login-btn) td {
                display: block !important;
                width: 100% !important;
                padding: 0 !important;
            }
            
            /* Login link styling */
            .hover-link1 {
                color: #007bff !important;
                text-decoration: none !important;
                font-weight: 600 !important;
            }
            
            .hover-link1:hover {
                text-decoration: underline !important;
            }
            
            /* Message styling */
            span[style*="color:red"] {
                display: block !important;
                text-align: center !important;
                padding: 10px !important;
                margin: 10px 0 !important;
                background: #ffe6e6 !important;
                border: 1px solid #ff9999 !important;
                border-radius: 6px !important;
            }
        }
        
        @media (max-width: 480px) {
            .logo-img { max-width: 80px; }
            
            .container {
                padding: 15px 10px !important;
            }
            
            .header-text {
                font-size: 22px !important;
            }
            
            .sub-text {
                font-size: 13px !important;
            }
            
            .input-text, .searchable-select input, .searchable-select select {
                padding: 10px !important;
                font-size: 16px !important;
            }
            
            .login-btn {
                padding: 12px !important;
                font-size: 15px !important;
            }
        }
    </style>
</head>
<body>
<center>
    <div class="container">
        <form action="" method="POST" id="signupForm">
            <!-- NEW: logo -->
            <div class="logo-container">
                <img src="logo/Logo.jpg" alt="Clinic Logo" class="logo-img">
            </div>
            <table border="0" style="margin: 0 auto;">
                <tr>
                    <td colspan="3" style="text-align:center;">
                        <p class="header-text">Let's Get Started</p>
                        <p class="sub-text">Add Your Personal Details to Continue</p>
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <label for="name" class="form-label">Name: </label>
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <input type="text" name="fname" class="input-text" placeholder="First Name" required>
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <input type="text" name="mname" class="input-text" placeholder="Middle Name (Optional)">
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <input type="text" name="lname" class="input-text" placeholder="Last Name" required>
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <input type="text" name="suffix" class="input-text" placeholder="Suffix (Optional)">
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <label for="address" class="form-label">Address: </label>
                    </td>
                </tr>
                <!-- Region Dropdown with Search -->
                <tr>
                    <td class="label-td" colspan="2">
                        <select name="region" id="region" class="input-text" required onchange="loadProvinces(this.value)">
                            <option value="">Select Region</option>
                            <?php echo $region_options; ?>
                        </select>
                    </td>
                </tr>
                <!-- Province Dropdown with Search -->
                <tr>
                    <td class="label-td" colspan="2">
                        <select name="province" id="province" class="input-text" required onchange="loadMunicipalities(this.value)">
                            <option value="">Select Province</option>
                        </select>
                    </td>
                </tr>
                <!-- Municipality Dropdown with Search -->
                <tr>
                    <td class="label-td" colspan="2">
                        <select name="city" id="municipality" class="input-text" required onchange="loadBarangays(this.value)">
                            <option value="">Select Municipality/City</option>
                        </select>
                    </td>
                </tr>
                <!-- Barangay Dropdown with Search -->
                <tr>
                    <td class="label-td" colspan="2">
                        <select name="barangay" id="barangay" class="input-text" required>
                            <option value="">Select Barangay</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <input type="text" name="street" class="input-text" placeholder="Street" required>
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <label for="dob" class="form-label">Date of Birth: </label>
                    </td>
                </tr>
                <tr>
                    <td class="label-td" colspan="2">
                        <input type="date" name="dob" class="input-text" max="<?php echo date('Y-m-d'); ?>" required>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:center;">
                        <?php echo $message; ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <input type="reset" value="Reset" class="login-btn btn-primary-soft btn">
                    </td>
                    <td>
                        <input type="submit" value="Next" class="login-btn btn-primary btn">
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <br>
                        <label for="" class="sub-text" style="font-weight: 280;">Already have an account&#63; </label>
                        <a href="login.php" class="hover-link1 non-style-link">Login</a>
                        <br><br><br>
                    </td>
                </tr>
            </table>
        </form>
    </div>
</center>
<script>

// AJAX loaders for cascading dropdowns
function loadProvinces(region_id) {
    var provinceSelect = document.getElementById('province');
    provinceSelect.innerHTML = '<option value="">Select Province</option>';
    document.getElementById('municipality').innerHTML = '<option value="">Select Municipality/City</option>';
    document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
    if (!region_id) return; // Don't fetch if nothing is selected

    fetch('ajax-address.php?type=province&region=' + encodeURIComponent(region_id))
        .then(response => response.json())
        .then(data => {
            provinceSelect.innerHTML = '<option value="">Select Province</option>';
            data.forEach(function(item) {
                var opt = document.createElement('option');
                opt.value = item.province_code;
                opt.text = item.province_name;
                provinceSelect.appendChild(opt);
            });
        });
}

function loadMunicipalities(province_code) {
    var municipalitySelect = document.getElementById('municipality');
    municipalitySelect.innerHTML = '<option value="">Loading...</option>';
    fetch('ajax-address.php?type=municipality&province=' + encodeURIComponent(province_code))
        .then(response => response.json())
        .then(data => {
            municipalitySelect.innerHTML = '<option value="">Select Municipality/City</option>';
            data.forEach(function(item) {
                var opt = document.createElement('option');
                opt.value = item.municipality_code;
                opt.text = item.municipality_name;
                municipalitySelect.appendChild(opt);
            });
            document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
        });
}

function loadBarangays(municipality_code) {
    var barangaySelect = document.getElementById('barangay');
    barangaySelect.innerHTML = '<option value="">Loading...</option>';
    fetch('ajax-address.php?type=barangay&municipality=' + encodeURIComponent(municipality_code))
        .then(response => response.json())
        .then(data => {
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            data.forEach(function(item) {
                var opt = document.createElement('option');
                opt.value = item.barangay_code;
                opt.text = item.barangay_name;
                barangaySelect.appendChild(opt);
            });
        });
}

// Reset all dropdowns on form reset
document.getElementById('signupForm').addEventListener('reset', function() {
    document.getElementById('province').innerHTML = '<option value="">Select Province</option>';
    document.getElementById('municipality').innerHTML = '<option value="">Select Municipality/City</option>';
    document.getElementById('barangay').innerHTML = '<option value="">Select Barangay</option>';
});

// Ctrl+L keyboard shortcut to redirect to admin signup
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'l') {
        e.preventDefault();
        window.location.href = 'admin-signup.php';
    }
});
</script>
</body>
</html>