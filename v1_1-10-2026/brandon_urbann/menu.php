<?php
// --- Leer el rol ---
// Si viene de un módulo, llega por POST. Si viene del login (usuario.php), ya existe.
if (isset($_POST["rol"])) {
    $rol = $_POST["rol"];
}
if (!isset($rol)) {
    $rol = "";
}

// --- Cerrar sesión (solo redirige al login) ---
if (isset($_POST["btn_cerrar"])) {
    header("Location: index.html");
    exit();
}

// --- Navegación ---
if (isset($_POST["btn_prt"])) {
    header("Location: producto.php?rol=$rol");
    exit();
} elseif (isset($_POST["btn_ct"])) {
    header("Location: categoria.php?rol=$rol");
    exit();
} elseif (isset($_POST["btn_pv"])) {
    header("Location: proveedor.php?rol=$rol");
    exit();
} elseif (isset($_POST["btn_emp"])) {
    header("Location: empleado.php?rol=$rol");
    exit();
} elseif (isset($_POST["btn_c"])) {
    header("Location: compra.php?rol=$rol");
    exit();
} elseif (isset($_POST["btn_vts"])) {
    header("Location: venta.php?rol=$rol");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Menú Principal - Tienda de Ropa</title>
</head>
<body>

<h2>Bienvenido al sistema</h2>

<?php if ($rol == "Administrador"): ?>
    <h3>Panel de Administrador</h3>
    <form method="POST" action="menu.php">
        <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
        <button type="submit" name="btn_prt">Gestionar Productos</button>
        <button type="submit" name="btn_ct">Gestionar Categorías</button>
        <button type="submit" name="btn_pv">Gestionar Proveedores</button>
        <button type="submit" name="btn_emp">Gestionar Empleados</button>
        <button type="submit" name="btn_c">Registrar Compra</button>
        <button type="submit" name="btn_vts">Registrar Venta</button>
        <br><br>
        <button type="submit" name="btn_cerrar">Cerrar Sesión</button>
    </form>
<?php elseif ($rol == "Empleado"): ?>
    <h2>Panel empleado</h2>
    <form method="POST" action="menu.php">
        <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
        <button type="submit" name="btn_prt">Consultar Productos</button>
        <button type="submit" name="btn_vts">Registrar Venta</button>
        <br><br>
        <button type="submit" name="btn_cerrar">Cerrar Sesión</button>
    </form>
<?php else: ?>
    <p>Rol no reconocido.</p>
    <form method="POST" action="menu.php">
        <button type="submit" name="btn_cerrar">Volver al Login</button>
    </form>
<?php endif; ?>

</body>
</html>