<?php
/**
 * Check what services are currently in the database
 * and show how they should be mapped for inventory deduction
 */

session_start();

// Check if user is logged in as admin
if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
    echo "<h2>Current Services Check</h2>";
    echo "<p>Please log in as an administrator to check current services.</p>";
    echo "<p><a href='../login.php'>Go to Login Page</a></p>";
    exit();
}

include("../connection.php");

echo "<h2>Current Services in Database</h2>";

// Get all services from database
$sql = "SELECT service_id, service_name, service_code, is_active FROM tbl_services ORDER BY service_name";
$result = $database->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<h3>Services Found:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Service Name</th><th>Service Code</th><th>Active</th><th>Inventory Mapping</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $active = $row['is_active'] ? 'Yes' : 'No';
        $service_code = $row['service_code'];
        
        // Check if this service code has inventory mapping
        $mapping_status = "❌ No mapping";
        if (in_array($service_code, ['examination', 'cataract_screening', 'cataract_surgery', 'cornea', 'lasik_screening', 'pediatric', 'colorvision', 'emergency', 'followup'])) {
            $mapping_status = "✅ Has mapping";
        }
        
        echo "<tr>";
        echo "<td>" . $row['service_id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['service_name']) . "</td>";
        echo "<td><code>" . htmlspecialchars($service_code) . "</code></td>";
        echo "<td>" . $active . "</td>";
        echo "<td>" . $mapping_status . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>Recommendations:</h3>";
    echo "<p>To fix the inventory deduction, you need to either:</p>";
    echo "<ol>";
    echo "<li><strong>Update service codes</strong> in your database to match the inventory mapping</li>";
    echo "<li><strong>Update the inventory mapping</strong> to match your current service codes</li>";
    echo "</ol>";
    
    echo "<h3>Current Inventory Mapping Keys:</h3>";
    echo "<ul>";
    echo "<li>examination</li>";
    echo "<li>cataract_screening</li>";
    echo "<li>cataract_surgery</li>";
    echo "<li>cornea</li>";
    echo "<li>lasik_screening</li>";
    echo "<li>pediatric</li>";
    echo "<li>colorvision</li>";
    echo "<li>emergency</li>";
    echo "<li>followup</li>";
    echo "</ul>";
    
} else {
    echo "<p style='color: red;'>No services found in database!</p>";
}

echo "<p><a href='inventory.php'>Go to Inventory Management</a></p>";
?>
