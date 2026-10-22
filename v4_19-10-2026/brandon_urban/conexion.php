<?php
// conexion.php — Conexión a la base de datos MySQL.
// Las demás páginas la cargan con include("conexion.php") y reutilizan la variable $conn.
//
// Datos de conexión: servidor, usuario, contraseña y nombre de la base de datos.
$servidor = "localhost";
$usuario = "root";
$password = "";
$base_datos = "brandon_urban";
// Abre la conexión con MySQL usando los datos anteriores.
$conn = new mysqli($servidor, $usuario, $password, $base_datos);
// Si no se pudo conectar, detiene la página y muestra el motivo del error.
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
// Usa UTF-8 para que las tildes y la ñ se guarden y se vean bien.
$conn->set_charset("utf8");
?>