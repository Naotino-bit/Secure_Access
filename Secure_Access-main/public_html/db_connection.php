<?php
$servername = getenv('DB_HOST') ?: "mysql_db_project";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASSWORD') ?: "root";
$dbname = getenv('DB_NAME') ?: "Secure_Access";
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4"); //caratteri speciali 

if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Errore di connessione al DataBase");
} 

$conn->set_charset("utf8mb4");

?>