<?php
$servername = "mysql_db_project";
$username = "root";
$password = "root";
$dbname = "Secure_Access";
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("". $conn->connect_error);
} 

