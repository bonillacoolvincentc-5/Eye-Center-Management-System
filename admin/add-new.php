<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <link rel="stylesheet" href="../css/admin.css">
        
    <title>Doctor</title>
    <style>
        .popup{
            animation: transitionIn-Y-bottom 0.5s;
        }
</style>
</head>
<body>
    <?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

// Import database
include("../connection.php");

$error = '3';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept name parts and normalize into a single docname for storage
    $first = trim($_POST['first_name'] ?? '');
    $middle = trim($_POST['middle_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');

    // Basic required name validation
    if ($first === '' || $last === '') {
        $error = '2'; // reuse error code for mismatch/invalid - will redirect back
        header("location: doctors.php?action=add&error=" . $error);
        exit();
    }

    $nic = $_POST['nic'] ?? '';
    $spec = $_POST['spec'] ?? '';
    $email = $_POST['email'] ?? '';
    $tele = $_POST['Tele'] ?? '';
    $password = $_POST['password'] ?? '';
    $cpassword = $_POST['cpassword'] ?? '';

    // Basic validation
    if ($password !== $cpassword) {
        $error = '2'; // Passwords do not match
    } else {
        // Check if email already exists in tbl_webuser
        $stmt = $database->prepare("SELECT * FROM tbl_webuser WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $error = '1'; // Email already exists
        } else {
            // Ensure NIC is unique in tbl_doctor
            $nicStmt = $database->prepare("SELECT 1 FROM tbl_doctor WHERE docnic = ? LIMIT 1");
            $nicStmt->bind_param("s", $nic);
            $nicStmt->execute();
            $nicRes = $nicStmt->get_result();
            if ($nicRes->num_rows > 0) {
                $error = 'nic_exists';
            } else {
            // Hash the password before storing
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert into tbl_doctor with name parts only (no composed docname)
            $stmt1 = $database->prepare("INSERT INTO tbl_doctor (docemail, docpassword, docnic, doctel, specialties, first_name, middle_name, last_name, suffix) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt1->bind_param("sssssssss", $email, $hashed_password, $nic, $tele, $spec, $first, $middle, $last, $suffix);
            $stmt1->execute();

            // Insert into tbl_webuser
            $stmt2 = $database->prepare("INSERT INTO tbl_webuser (email, usertype) VALUES (?, 'd')");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();

            $error = '4'; // Success
            }
        }
    }
    header("location: doctors.php?action=add&error=" . $error);
    exit();
} else {
    header("location: doctors.php?action=add&error=" . $error);
    exit();
}
?>

</body>
</html>
