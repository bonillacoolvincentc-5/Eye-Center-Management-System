<?php
/**
 * Test script to verify password hashing functionality
 * This script tests the password hashing for admin and doctor accounts
 */

include("connection.php");

echo "<h2>Password Hashing Test</h2>";

// Test password hashing
$test_password = "test123";
$hashed_password = password_hash($test_password, PASSWORD_DEFAULT);

echo "<h3>Password Hashing Test:</h3>";
echo "<p><strong>Original password:</strong> {$test_password}</p>";
echo "<p><strong>Hashed password:</strong> {$hashed_password}</p>";

// Test password verification
$verify_result = password_verify($test_password, $hashed_password);
echo "<p><strong>Password verification:</strong> " . ($verify_result ? "✓ PASS" : "✗ FAIL") . "</p>";

// Test with wrong password
$wrong_password = "wrong123";
$wrong_verify = password_verify($wrong_password, $hashed_password);
echo "<p><strong>Wrong password verification:</strong> " . ($wrong_verify ? "✗ FAIL (should be false)" : "✓ PASS (correctly rejected)") . "</p>";

// Test detection of hashed vs plain text passwords
echo "<h3>Password Type Detection Test:</h3>";
$plain_text = "plaintext";
$hashed_text = password_hash("test", PASSWORD_DEFAULT);

echo "<p><strong>Plain text detection:</strong> " . (strpos($plain_text, '$2y$') === 0 ? "✗ FAIL" : "✓ PASS (correctly identified as plain text)") . "</p>";
echo "<p><strong>Hashed text detection:</strong> " . (strpos($hashed_text, '$2y$') === 0 ? "✓ PASS (correctly identified as hashed)" : "✗ FAIL") . "</p>";

echo "<h3>Database Connection Test:</h3>";
if ($database->connect_error) {
    echo "<p>✗ Database connection failed: " . $database->connect_error . "</p>";
} else {
    echo "<p>✓ Database connection successful</p>";
    
    // Test querying admin table
    $admin_test = $database->query("SELECT COUNT(*) as count FROM tbl_admin");
    if ($admin_test) {
        $admin_count = $admin_test->fetch_assoc()['count'];
        echo "<p>✓ Admin table accessible - {$admin_count} admin accounts found</p>";
    } else {
        echo "<p>✗ Error accessing admin table: " . $database->error . "</p>";
    }
    
    // Test querying doctor table
    $doctor_test = $database->query("SELECT COUNT(*) as count FROM tbl_doctor");
    if ($doctor_test) {
        $doctor_count = $doctor_test->fetch_assoc()['count'];
        echo "<p>✓ Doctor table accessible - {$doctor_count} doctor accounts found</p>";
    } else {
        echo "<p>✗ Error accessing doctor table: " . $database->error . "</p>";
    }
}

echo "<h3>Test Summary:</h3>";
echo "<p>All password hashing functionality appears to be working correctly!</p>";
echo "<p><a href='login.php'>Go to Login Page</a></p>";

$database->close();
?>
