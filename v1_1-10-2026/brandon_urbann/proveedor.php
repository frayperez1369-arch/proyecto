<?php
include("conexion.php");

$mensaje = "";
$registro_editar = null;
$busqueda = "";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Leer el rol (llega por POST desde el menú o por GET desde los enlaces)
$rol = "";
if (isset($_POST["rol"])) {
    $rol = $_POST["rol"];
}
if (isset($_GET["rol"])) {
    $rol = $_GET["rol"];
}

// Eliminar
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id_proveedor'])) {
    $id_eliminar = $_GET['id_proveedor'];
    try {
        $sql_del = "DELETE FROM proveedor WHERE id_proveedor = ?";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bind_param("i", $id_eliminar);
        $stmt_del->execute();
        $mensaje = "<p style='color:green;font-weight:bold;'>Proveedor eliminado correctamente.</p>";
        $stmt_del->close();
    } catch (mysqli_sql_exception $e) {
        $mensaje = "<p style='color:red;font-weight:bold;'>Error al eliminar: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Editar
if (isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['id_proveedor'])) {
    $id_buscar = $_GET['id_proveedor'];
    $sql_buscar = "SELECT * FROM proveedor WHERE id_proveedor = ?";
    $stmt_buscar = $conn->prepare($sql_buscar);
    $stmt_buscar->bind_param("i", $id_buscar);
    $stmt_buscar->execute();
    $res = $stmt_buscar->get_result();
    $registro_editar = $res->fetch_assoc();
    $stmt_buscar->close();
}

// Guardar o actualizar
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["guardar"])) {
        $nit = $_POST["nit"];
        $nombre = $_POST["nombre"];
        $telefono = $_POST["telefono"];
        $direccion = $_POST["direccion"];
        try {
            $sql = "INSERT INTO proveedor (nit, nombre, telefono, direccion) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $nit, $nombre, $telefono, $direccion);
            $stmt->execute();
            $mensaje = "<p style='color:green;font-weight:bold;'>Proveedor registrado correctamente.</p>";
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<p style='color:red;font-weight:bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    if (isset($_POST["actualizar"])) {
        $id = $_POST["id"];
        $nit = $_POST["nit"];
        $nombre = $_POST["nombre"];
        $telefono = $_POST["telefono"];
        $direccion = $_POST["direccion"];
        try {
            $sql_up = "UPDATE proveedor SET nit = ?, nombre = ?, telefono = ?, direccion = ? WHERE id_proveedor = ?";
            $stmt_up = $conn->prepare($sql_up);
            $stmt_up->bind_param("ssssi", $nit, $nombre, $telefono, $direccion, $id);
            $stmt_up->execute();
            $mensaje = "<p style='color:green;font-weight:bold;'>Proveedor actualizado correctamente.</p>";
            $stmt_up->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<p style='color:red;font-weight:bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

// Buscar o listar
if (isset($_GET['buscar']) && !empty(trim($_GET['buscar']))) {
    $busqueda = trim($_GET['buscar']);
    $param = "%$busqueda%";
    $stmt = $conn->prepare("SELECT * FROM proveedor WHERE nit LIKE ? OR nombre LIKE ? ORDER BY id_proveedor DESC");
    $stmt->bind_param("ss", $param, $param);
    $stmt->execute();
    $registros = $stmt->get_result();
} else {
    $registros = $conn->query("SELECT * FROM proveedor ORDER BY id_proveedor DESC");
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Proveedores</title></head>
<body>
<?php echo $mensaje; ?>

<?php if ($registro_editar): ?>
<h2>Editar Proveedor N. <?php echo htmlspecialchars($registro_editar['id_proveedor']); ?></h2>
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <input type="hidden" name="id" value="<?php echo $registro_editar['id_proveedor']; ?>">
    <label>NIT:</label><br><input type="text" name="nit" value="<?php echo htmlspecialchars($registro_editar["nit"]); ?>" required><br><br>
    <label>Nombre:</label><br><input type="text" name="nombre" value="<?php echo htmlspecialchars($registro_editar["nombre"]); ?>" required><br><br>
    <label>Teléfono:</label><br><input type="text" name="telefono" value="<?php echo htmlspecialchars($registro_editar["telefono"]); ?>" required><br><br>
    <label>Dirección:</label><br><input type="text" name="direccion" value="<?php echo htmlspecialchars($registro_editar["direccion"]); ?>" required><br><br>
    <button type="submit" name="actualizar">Actualizar</button>
    <a href="?rol=<?php echo htmlspecialchars($rol); ?>">Cancelar</a>
</form>
<?php else: ?>
<h2>Registrar Proveedor</h2>
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <label>NIT:</label><br><input type="text" name="nit" required><br><br>
    <label>Nombre:</label><br><input type="text" name="nombre" required><br><br>
    <label>Teléfono:</label><br><input type="text" name="telefono" required><br><br>
    <label>Dirección:</label><br><input type="text" name="direccion" required><br><br>
    <button type="submit" name="guardar">Guardar</button>
</form>
<?php endif; ?>

<br>
<form method="POST" action="menu.php">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <button type="submit">Menú Principal</button>
</form>
<hr>

<h3>Buscar</h3>
<form method="GET">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <input type="text" name="buscar" placeholder="Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
    <button type="submit">Buscar</button>
    <?php if (!empty($busqueda)): ?><a href="?rol=<?php echo htmlspecialchars($rol); ?>">Mostrar todos</a><?php endif; ?>
</form>

<h3>Listado</h3>
<table border="1" cellpadding="5" cellspacing="0">
<thead><tr><th>ID</th><th>NIT</th><th>Nombre</th><th>Teléfono</th><th>Dirección</th><th>Acciones</th></tr></thead>
<tbody>
<?php if ($registros && $registros->num_rows > 0): ?>
<?php while ($f = $registros->fetch_assoc()): ?>
<tr>
<td><?php echo htmlspecialchars($f["id_proveedor"]); ?></td>
<td><?php echo htmlspecialchars($f["nit"]); ?></td>
<td><?php echo htmlspecialchars($f["nombre"]); ?></td>
<td><?php echo htmlspecialchars($f["telefono"]); ?></td>
<td><?php echo htmlspecialchars($f["direccion"]); ?></td>
<td>
    <a href="?accion=editar&id_proveedor=<?php echo $f['id_proveedor']; ?>&rol=<?php echo htmlspecialchars($rol); ?>">Editar</a>
    <a href="?accion=eliminar&id_proveedor=<?php echo $f['id_proveedor']; ?>&rol=<?php echo htmlspecialchars($rol); ?>" onclick="return confirm('¿Eliminar?');">Eliminar</a>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="6">No se encontraron registros.</td></tr>
<?php endif; ?>
</tbody></table>
</body>
</html>
<?php $conn->close(); ?>