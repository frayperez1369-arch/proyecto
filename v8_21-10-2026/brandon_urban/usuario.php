<?php
// usuario.php — Procesa el inicio de sesión que se envía desde index.html.
// Valida usuario, contraseña, estado y rol; si todo es correcto crea la SESIÓN y manda al menú principal.
//
// Conexión a la base de datos (deja lista la variable $conn).
include("conexion.php");
// SEGURIDAD: carga las funciones de sesión, login bloqueado, etc. (ver seguridad.php).
include_once("seguridad.php");

// --- Solo se acepta el envío del formulario (POST) ---
// Si alguien abre usuario.php escribiendo la dirección en el navegador (GET), se le devuelve al login.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirigir_login();
}

// Datos que llegan por POST desde el formulario de login (index.html). Si falta alguno queda vacío en vez de dar error.
$username = isset($_POST["username"]) ? trim($_POST["username"]) : "";
$password = isset($_POST["password"]) ? $_POST["password"] : "";
$tipo_acceso = isset($_POST["tipo_acceso"]) ? $_POST["tipo_acceso"] : "select";

// --- Bloque anti-fuerza-bruta ---
// Si esta IP ya falló demasiadas veces seguidas, se frena el intento antes de mirar la base de datos.
if (login_bloqueado($conn)) {
    echo "<script>alert('Demasiados intentos fallidos. Espera " . (int)VENTANA_INTENTOS_MIN . " minutos e inténtalo de nuevo.'); window.location.href = 'index.html';</script>";
    $conn->close();
    exit();
}

// --- Buscar al empleado SOLO por su usuario ---
// Ya no se compara la contraseña dentro del SQL: ahora está guardada como «hash» (ilegible) y se verifica con password_verify (más abajo).
$stmt = $conn->prepare("SELECT id_empleado, nombre, rol, estado, password FROM empleado WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$resultado = $stmt->get_result();
$fila = $resultado->fetch_assoc();
$stmt->close();

// --- Verificar la contraseña ---
// password_verify compara lo que escribió el usuario contra el hash guardado.
// Si el usuario NO existe se verifica contra un hash falso, para que tarde lo mismo y no se pueda averiguar qué usuarios existen midiendo tiempos.
$hash_falso = '$2y$10$8b6iHAnhoC9hzkmglPjef./gxCTMi.brqyPM5tIvY3tRvi1Cg.dQ.';
$hash_guardado = $fila ? $fila["password"] : $hash_falso;
$clave_correcta = password_verify($password, $hash_guardado) && $fila;

// Validaciones en orden: 1) se eligió rol, 2) usuario y contraseña correctos, 3) está activo, 4) su rol coincide con el elegido.
if ($tipo_acceso !== "Administrador" && $tipo_acceso !== "Empleado") {
    // No se eligió ningún rol válido: avisa y vuelve al login.
    echo "<script>alert('Debe seleccionar un rol'); window.location.href = 'index.html';</script>";
} elseif (!$clave_correcta) {
    // Usuario o contraseña incorrectos: se anota el intento fallido y se avisa con un mensaje genérico (no dice cuál de los dos falló).
    registrar_intento_fallido($conn, $username);
    echo "<script>alert('Usuario o contraseña incorrectos'); window.location.href = 'index.html';</script>";
} elseif ($fila["estado"] != "Activo") {
    // El empleado está inactivo: no puede entrar.
    echo "<script>alert('Usuario inactivo'); window.location.href = 'index.html';</script>";
} elseif ($fila["rol"] != $tipo_acceso) {
    // El rol guardado en la base de datos no coincide con el que eligió en el login.
    echo "<script>alert('El rol seleccionado no coincide'); window.location.href = 'index.html';</script>";
} else {
    // --- Login correcto ---
    // Si PHP mejoró su algoritmo de hash desde que se guardó la contraseña, se actualiza el hash en silencio.
    if (password_needs_rehash($fila["password"], PASSWORD_DEFAULT)) {
        $nuevo_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt_rehash = $conn->prepare("UPDATE empleado SET password = ? WHERE id_empleado = ?");
        $stmt_rehash->bind_param("si", $nuevo_hash, $fila["id_empleado"]);
        $stmt_rehash->execute();
        $stmt_rehash->close();
    }
    // Borra los intentos fallidos de esta IP.
    limpiar_intentos($conn);
    // Crea la sesión (guarda id, nombre y rol del usuario EN EL SERVIDOR).
    iniciar_sesion_usuario($fila["id_empleado"], $fila["nombre"], $fila["rol"]);
    // Redirige al menú principal: menu.php lee el rol desde la sesión, ya no desde una variable pasada por la URL.
    $conn->close();
    header("Location: menu.php");
    exit();
}

// Cierra la conexión con la base de datos (solo se llega aquí si el login falló).
$conn->close();
?>
