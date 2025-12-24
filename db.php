<?php
$host = "localhost";  //Your DB host
$user = "root"; // Your DB Username
$password = "";     // Your DB Password
$dbname = "nexus_chat"; // Your DB Name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>