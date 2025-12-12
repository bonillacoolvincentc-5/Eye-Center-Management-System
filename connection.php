<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ims/ams"; // your database name

$database = new mysqli($servername, $username, $password, $dbname);

if ($database->connect_error) {
    die("Connection failed: " . $database->connect_error);
}
?>
