<?php
/**
 * Migration script to hash existing admin and doctor passwords
 * Run this script once to convert all plain text passwords to hashed passwords
 * 
 * IMPORTANT: Make sure to backup your database before running this script!
 */

include("connection.php");

echo "<h2>Password Hashing Migration Script</h2>";
echo "<p>This script will hash all existing plain text passwords for admin and doctor accounts.</p>";
echo "<p><strong>WARNING:</strong> Make sure you have backed up your database before proceeding!</p>";

// Check if this is a POST request (confirmation)
if ($_POST && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    echo "<h3>Starting migration...</h3>";
    
    // Hash admin passwords
    echo "<h4>Processing Admin Accounts:</h4>";
    $admin_result = $database->query("SELECT docid, email, password FROM tbl_admin");
    
    if ($admin_result) {
        while ($row = $admin_result->fetch_assoc()) {
            // Check if password is already hashed
            if (strpos($row['password'], '$2y$') !== 0) {
                $hashed_password = password_hash($row['password'], PASSWORD_DEFAULT);
                $stmt = $database->prepare("UPDATE tbl_admin SET password = ? WHERE docid = ?");
                $stmt->bind_param("si", $hashed_password, $row['docid']);
                
                if ($stmt->execute()) {
                    echo "<p>✓ Admin account {$row['email']} password hashed successfully</p>";
                } else {
                    echo "<p>✗ Failed to hash password for admin account {$row['email']}</p>";
                }
                $stmt->close();
            } else {
                echo "<p>- Admin account {$row['email']} password already hashed</p>";
            }
        }
    } else {
        echo "<p>✗ Error querying admin accounts: " . $database->error . "</p>";
    }
    
    // Hash doctor passwords
    echo "<h4>Processing Doctor Accounts:</h4>";
    $doctor_result = $database->query("SELECT docid, docemail, docpassword FROM tbl_doctor");
    
    if ($doctor_result) {
        while ($row = $doctor_result->fetch_assoc()) {
            // Check if password is already hashed
            if (strpos($row['docpassword'], '$2y$') !== 0) {
                $hashed_password = password_hash($row['docpassword'], PASSWORD_DEFAULT);
                $stmt = $database->prepare("UPDATE tbl_doctor SET docpassword = ? WHERE docid = ?");
                $stmt->bind_param("si", $hashed_password, $row['docid']);
                
                if ($stmt->execute()) {
                    echo "<p>✓ Doctor account {$row['docemail']} password hashed successfully</p>";
                } else {
                    echo "<p>✗ Failed to hash password for doctor account {$row['docemail']}</p>";
                }
                $stmt->close();
            } else {
                echo "<p>- Doctor account {$row['docemail']} password already hashed</p>";
            }
        }
    } else {
        echo "<p>✗ Error querying doctor accounts: " . $database->error . "</p>";
    }
    
    echo "<h3>Migration completed!</h3>";
    echo "<p>All existing passwords have been hashed. You can now delete this migration script.</p>";
    
} else {
    // Show confirmation form
    ?>
    <form method="POST" style="margin: 20px 0;">
        <p>Are you sure you want to proceed with hashing all existing passwords?</p>
        <input type="hidden" name="confirm" value="yes">
        <button type="submit" style="background-color: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
            Yes, Hash All Passwords
        </button>
        <a href="login.php" style="margin-left: 10px; padding: 10px 20px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px;">
            Cancel
        </a>
    </form>
    <?php
}

$database->close();
?>
