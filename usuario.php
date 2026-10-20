<?php
include("conexion.php");

$username = $_POST["username"];
$password = $_POST["password"];
$tipo_acceso = $_POST["tipo_acceso"];

$stmt = $conn->prepare("SELECT id_empleado, nombre, rol, estado FROM empleado WHERE username = ? AND password = ?");
$stmt->bind_param("ss", $username, $password);
$stmt->execute();
$resultado = $stmt->get_result();

if ($tipo_acceso == "select") {
    echo "<script>alert('Debe seleccionar un rol'); window.location.href = 'index.html';</script>";
} elseif ($resultado->num_rows == 0) {
    echo "<script>alert('Usuario o contraseña incorrectos'); window.location.href = 'index.html';</script>";
} else {
    $fila = $resultado->fetch_assoc();
    $nombre = $fila["nombre"];
    $rol = $fila["rol"];

    if ($fila["estado"] != "Activo") {
        echo "<script>alert('Usuario inactivo'); window.location.href = 'index.html';</script>";
    } elseif ($rol != $tipo_acceso) {
        echo "<script>alert('El rol seleccionado no coincide'); window.location.href = 'index.html';</script>";
    } else {
        include("menu.php");
    }
}

$stmt->close();
$conn->close();
?>