<?php
// filepath: c:\xampp\htdocs\AppointmentBackup\ajax-address.php
header('Content-Type: application/json');
include("connection.php");

$type = isset($_GET['type']) ? $_GET['type'] : '';
$data = [];

if ($type === 'province' && isset($_GET['region'])) {
    $region_id = $_GET['region'];
    $result = $database->query("SELECT province_id, province_name FROM tbl_province WHERE region_id = '$region_id' ORDER BY province_name ASC");
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'province_code' => $row['province_id'],
            'province_name' => $row['province_name']
        ];
    }
} elseif ($type === 'municipality' && isset($_GET['province'])) {
    $province_id = $_GET['province'];
    $result = $database->query("SELECT municipality_id, municipality_name FROM tbl_municipality WHERE province_id = '$province_id' ORDER BY municipality_name ASC");
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'municipality_code' => $row['municipality_id'],
            'municipality_name' => $row['municipality_name']
        ];
    }
} elseif ($type === 'barangay' && isset($_GET['municipality'])) {
    $municipality_id = $_GET['municipality'];
    $result = $database->query("SELECT barangay_id, barangay_name FROM tbl_barangay WHERE municipality_id = '$municipality_id' ORDER BY barangay_name ASC");
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'barangay_code' => $row['barangay_id'],
            'barangay_name' => $row['barangay_name']
        ];
    }
}

echo json_encode($data);