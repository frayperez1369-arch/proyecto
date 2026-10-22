<?php
// conexion.php — Conexión a la base de datos MySQL.
// Las demás páginas la cargan con include("conexion.php") y reutilizan la variable $conn.
//
// --- Datos de conexión: servidor, usuario, contraseña y nombre de la base de datos ---
// SEGURIDAD: cada dato puede venir de una variable de entorno (DB_HOST, DB_USER, DB_PASS, DB_NAME).
// Así, en un servidor real puedes usar un usuario propio con contraseña SIN escribir claves dentro del código.
// Si la variable no existe, se usan los valores de desarrollo de abajo (root sin contraseña: SOLO para tu computador local).
$servidor = getenv("DB_HOST") ?: "localhost";
$usuario = getenv("DB_USER") ?: "root";
$password = getenv("DB_PASS") !== false ? getenv("DB_PASS") : "";
$base_datos = getenv("DB_NAME") ?: "brandon_urban";

// --- Abrir la conexión ---
// SEGURIDAD: si falla, el motivo real (que puede revelar el usuario o el servidor) se guarda en el log del servidor
// y al visitante solo se le muestra un mensaje genérico.
try {
    $conn = new mysqli($servidor, $usuario, $password, $base_datos);
} catch (Throwable $e) {
    error_log("Error de conexión MySQL: " . $e->getMessage());
    http_response_code(500);
    die("No se pudo conectar con la base de datos. Intenta más tarde.");
}
// Versiones antiguas de PHP no lanzan excepción: avisan por connect_error. Se trata igual que arriba.
if ($conn->connect_error) {
    error_log("Error de conexión MySQL: " . $conn->connect_error);
    http_response_code(500);
    die("No se pudo conectar con la base de datos. Intenta más tarde.");
}
// Usa UTF-8 para que las tildes y la ñ se guarden y se vean bien.
$conn->set_charset("utf8");
?>
