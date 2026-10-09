<?php

$servidor = "localhost";
$usuario = "root";
$contrasena = "";
$baseDatos = "denunciadigital";

$conn = new mysqli($servidor, $usuario, $contrasena, $baseDatos);

if ($conn->connect_error) {
    die("Error de conexion a la base de datos: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>