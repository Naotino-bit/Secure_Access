<?php
// Puliamo tutto e torniamo alla home
session_start();
$_SESSION = array();
session_destroy();
header("Location: index.php");
?>

