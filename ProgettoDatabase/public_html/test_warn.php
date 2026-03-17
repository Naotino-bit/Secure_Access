<?php
require "db_connection.php";
$stmt = $conn->query("SELECT * FROM Warnings ORDER BY IdWarning DESC LIMIT 5");
while ($row = $stmt->fetch_assoc()) {
    print_r($row);
}
?>
