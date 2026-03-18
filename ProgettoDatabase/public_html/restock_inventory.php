<?php
require "db_connection.php";
session_start();

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: index.php");
    exit();
}

$idItem = intval($_POST['id_item']);
$qty = intval($_POST['quantity']);

if ($idItem <= 0 || $qty <= 0) {
     header("Location: maintenance_dashboard.php?msg=Dati non validi");
     exit();
}

$stmt = $conn->prepare("UPDATE Inventory SET Quantity = Quantity + ? WHERE IdItem = ?");
$stmt->bind_param("ii", $qty, $idItem);

if ($stmt->execute()) {
    // Scriviamo nel diario degli admin cosa abbiamo rifornito
    $user = $_SESSION['user'];
    $desc = "Rifornimento inventario: Item $idItem + $qty pezzi da $user";
    $conn->query("INSERT INTO AdminLogs (Description, DateTime) VALUES ('$desc', NOW())");

    header("Location: maintenance_dashboard.php?msg=Inventario aggiornato con successo");
} else {
    header("Location: maintenance_dashboard.php?msg=Errore aggiornamento DB");
}
$stmt->close();
?>
