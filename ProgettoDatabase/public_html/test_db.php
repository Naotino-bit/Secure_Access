<?php
require "db_connection.php";
$res = $conn->query("SHOW CREATE TABLE Warnings");
if ($res) {
    if ($row = $res->fetch_row()) {
        echo $row[1];
    }
} else {
    echo "Query failed: " . $conn->error;
}
?>
