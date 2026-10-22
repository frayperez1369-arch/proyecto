<?php
// usuario.php — Procesa el inicio de sesión que se envía desde index.html.
// Valida usuario, contraseña, estado y rol; si todo es correcto muestra el menú principal.
//
// Conexión a la base de datos (deja lista la variable $conn).
include("conexion.php");

// Datos que llegan por POST desde el formulario de login (index.html).
$username = $_POST["username"];
$password = $_POST["password"];
$tipo_acceso = $_POST["tipo_acceso"];

// Busca en la tabla empleado a alguien con ese usuario y contraseña (consulta preparada: los ? se reemplazan de forma segura).
$stmt = $conn->prepare("SELECT id_empleado, nombre, rol, estado FROM empleado WHERE username = ? AND password = ?");
$stmt->bind_param("ss", $username, $password);
$stmt->execute();
$resultado = $stmt->get_result();

// Validaciones en orden: 1) se eligió rol, 2) el usuario existe, 3) está activo, 4) su rol coincide con el elegido.
if ($tipo_acceso == "select") {
// No se eligió ningún rol: avisa y vuelve al login.
    echo "<script>alert('Debe seleccionar un rol'); window.location.href = 'index.html';</script>";
} elseif ($resultado->num_rows == 0) {
// Ninguna fila coincide: usuario o contraseña incorrectos.
    echo "<script>alert('Usuario o contraseña incorrectos'); window.location.href = 'index.html';</script>";
} else {
    // El usuario existe: toma sus datos (nombre, rol y estado) para las siguientes validaciones.
    $fila = $resultado->fetch_assoc();
    $nombre = $fila["nombre"];
    $rol = $fila["rol"];

    if ($fila["estado"] != "Activo") {
    // El empleado está inactivo: no puede entrar.
        echo "<script>alert('Usuario inactivo'); window.location.href = 'index.html';</script>";
    } elseif ($rol != $tipo_acceso) {
    // El rol guardado en la base de datos no coincide con el que eligió en el login.
        echo "<script>alert('El rol seleccionado no coincide'); window.location.href = 'index.html';</script>";
    } else {
        // Todo correcto: carga el menú principal (menu.php usa la variable $rol definida arriba).
        include("menu.php");
    }
}

// Libera la consulta y cierra la conexión con la base de datos.
$stmt->close();
$conn->close();
?>