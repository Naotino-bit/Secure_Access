<?php
$password_in_chiaro = "Pasquale2005!"; 

$hash = password_hash($password_in_chiaro, PASSWORD_DEFAULT);

echo "<h1>Copia questa stringa nel Database:</h1>";
echo "<p style='background:#eee; padding:10px; font-family:monospace; font-size:1.2em;'>" . $hash . "</p>";
?>