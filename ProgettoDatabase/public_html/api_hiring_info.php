<?php
require "db_connection.php";
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(["error" => "Accesso negato"]);
    exit();
}

$adminEmail = $_SESSION['user'];

// Verify Admin Privileges
$queryAdmin = "
    SELECT B.BadgeLevel
    FROM Users U
    JOIN Badges B ON U.IdBadge = B.IdBadge
    WHERE U.Email = ?";
$stmt = $conn->prepare($queryAdmin);
$stmt->bind_param("s", $adminEmail);
$stmt->execute();
$resAdmin = $stmt->get_result();
$rowAdmin = $resAdmin->fetch_assoc();

if (!$rowAdmin || $rowAdmin['BadgeLevel'] < 3) {
    http_response_code(403);
    echo json_encode(["error" => "Non autorizzato"]);
    exit();
}
$stmt->close();

$candidateEmail = $_GET['email'] ?? '';
$roleName = $_GET['role'] ?? '';

if (empty($candidateEmail) || empty($roleName)) {
    http_response_code(400);
    echo json_encode(["error" => "Email e Ruolo sono necessari"]);
    exit();
}

// 1. Fetch Candidate Data
$stmt = $conn->prepare("SELECT Name, Surname, Email, DateBirth FROM Users WHERE Email = ?");
$stmt->bind_param("s", $candidateEmail);
$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$candidate) {
    http_response_code(404);
    echo json_encode(["error" => "Candidato non trovato"]);
    exit();
}

// Map Role String mapping exactly like in promote.php just string to search
$searchTerm = "";
if ($roleName == 'Admin' || $roleName == 'Amministratore') $searchTerm = 'Amministratore';
elseif ($roleName == 'Tecnico') $searchTerm = 'Tecnico';
elseif ($roleName == 'Magazziniere') $searchTerm = 'Magazziniere';
elseif ($roleName == 'Chimico') $searchTerm = 'Chimico';
elseif ($roleName == 'Sorveglianza') $searchTerm = 'Sorveglianza';
else $searchTerm = $roleName; // Fallback

// 2. Fetch Current Employees for this Role
$employeesQuery = "
    SELECT U.Name, U.Surname, U.Email 
    FROM Users U 
    JOIN Employees E ON U.IdUser = E.IdEmployee
    JOIN Shifts S ON E.IdRole = S.IdRole
    WHERE S.Role = ?
    ORDER BY U.Surname ASC";
$stmt = $conn->prepare($employeesQuery);
$stmt->bind_param("s", $roleName);
$stmt->execute();
$resEmployees = $stmt->get_result();

$employees = [];
while ($row = $resEmployees->fetch_assoc()) {
    $employees[] = $row;
}
$stmt->close();

// Return JSON Response
echo json_encode([
    "success" => true,
    "candidate" => $candidate,
    "role" => $roleName,
    "employees" => $employees
]);
?>
