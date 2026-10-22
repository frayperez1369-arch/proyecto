<?php
include("conexion.php");

$mensaje = "";
$registro_editar = null;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Leer el rol (llega por POST desde el menú o por GET desde los enlaces)
$rol = "";
if (isset($_POST["rol"])) {
    $rol = $_POST["rol"];
}
if (isset($_GET["rol"])) {
    $rol = $_GET["rol"];
}
$esAdmin = ($rol === "Administrador");

// Eliminar
if ($esAdmin && isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id_venta'])) {
    $id_eliminar = $_GET['id_venta'];
    try {
        $sql_del = "DELETE FROM venta WHERE id_venta = ?";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bind_param("i", $id_eliminar);
        $stmt_del->execute();
        $mensaje = "<p style='color:green;font-weight:bold;'>Venta eliminada correctamente.</p>";
        $stmt_del->close();
    } catch (mysqli_sql_exception $e) {
        $mensaje = "<p style='color:red;font-weight:bold;'>Error al eliminar: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Editar
if ($esAdmin && isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['id_venta'])) {
    $id_buscar = $_GET['id_venta'];
    $sql_buscar = "SELECT * FROM venta WHERE id_venta = ?";
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
        $fecha = date('Y-m-d');
        $id_empleado = $_POST["id_empleado"];
        $id_producto = $_POST["id_producto"];
        $cantidad = $_POST["cantidad"];
        $precio_unitario = $_POST["precio_unitario"];
        $subtotal = $cantidad * $precio_unitario;
        $total = $subtotal;
        try {
            $sql = "INSERT INTO venta (fecha, id_empleado, id_producto, cantidad, precio_unitario, subtotal, total) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siiiddd", $fecha, $id_empleado, $id_producto, $cantidad, $precio_unitario, $subtotal, $total);
            $stmt->execute();
            $mensaje = "<p style='color:green;font-weight:bold;'>Venta registrada correctamente.</p>";
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<p style='color:red;font-weight:bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    if ($esAdmin && isset($_POST["actualizar"])) {
        $id = $_POST["id"];
        $id_empleado = $_POST["id_empleado"];
        $id_producto = $_POST["id_producto"];
        $cantidad = $_POST["cantidad"];
        $precio_unitario = $_POST["precio_unitario"];
        $subtotal = $cantidad * $precio_unitario;
        $total = $subtotal;
        try {
            $sql_up = "UPDATE venta SET id_empleado=?, id_producto=?, cantidad=?, precio_unitario=?, subtotal=?, total=? WHERE id_venta=?";
            $stmt_up = $conn->prepare($sql_up);
            $stmt_up->bind_param("iiidddi", $id_empleado, $id_producto, $cantidad, $precio_unitario, $subtotal, $total, $id);
            $stmt_up->execute();
            $mensaje = "<p style='color:green;font-weight:bold;'>Venta actualizada correctamente.</p>";
            $stmt_up->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<p style='color:red;font-weight:bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

// Listar ventas
$registros = $conn->query("SELECT v.*, e.nombre AS empleado_nombre, p.nombre AS producto_nombre
                           FROM venta v
                           LEFT JOIN empleado e ON v.id_empleado = e.id_empleado
                           LEFT JOIN producto p ON v.id_producto = p.id_producto
                           ORDER BY v.id_venta DESC");

// Funciones simples para selects
function selectEmpleados($conn, $seleccionado = null) {
    $res = $conn->query("SELECT id_empleado, nombre FROM empleado ORDER BY id_empleado ASC");
    $options = '<option value="">Seleccione empleado</option>';
    while ($f = $res->fetch_assoc()) {
        $sel = ($seleccionado == $f['id_empleado']) ? 'selected' : '';
        $options .= '<option value="' . $f['id_empleado'] . '" ' . $sel . '>' . $f['id_empleado'] . ' - ' . htmlspecialchars($f['nombre']) . '</option>';
    }
    return $options;
}

function selectProductos($conn, $seleccionado = null) {
    $res = $conn->query("SELECT id_producto, nombre FROM producto ORDER BY id_producto ASC");
    $options = '<option value="">Seleccione producto</option>';
    while ($f = $res->fetch_assoc()) {
        $sel = ($seleccionado == $f['id_producto']) ? 'selected' : '';
        $options .= '<option value="' . $f['id_producto'] . '" ' . $sel . '>' . $f['id_producto'] . ' - ' . htmlspecialchars($f['nombre']) . '</option>';
    }
    return $options;
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Ventas</title></head>
<body>
<?php echo $mensaje; ?>

<?php if ($registro_editar): ?>
<h2>Editar Venta N. <?php echo htmlspecialchars($registro_editar['id_venta']); ?></h2>
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <input type="hidden" name="id" value="<?php echo $registro_editar['id_venta']; ?>">
    <label>Fecha:</label><br>
    <input type="text" value="<?php echo htmlspecialchars($registro_editar["fecha"]); ?>" readonly><br><br>
    <label>Empleado:</label><br>
    <select name="id_empleado" required><?php echo selectEmpleados($conn, $registro_editar["id_empleado"]); ?></select><br><br>
    <label>Producto:</label><br>
    <select name="id_producto" required><?php echo selectProductos($conn, $registro_editar["id_producto"]); ?></select><br><br>
    <label>Cantidad:</label><br>
    <input type="number" name="cantidad" value="<?php echo htmlspecialchars($registro_editar["cantidad"]); ?>" min="1" required><br><br>
    <label>Precio unitario:</label><br>
    <input type="number" step="0.01" name="precio_unitario" value="<?php echo htmlspecialchars($registro_editar["precio_unitario"]); ?>" min="0" required><br><br>
    <button type="submit" name="actualizar">Actualizar</button>
    <a href="?rol=<?php echo htmlspecialchars($rol); ?>">Cancelar</a>
</form>
<?php else: ?>
<h2>Registrar Venta</h2>
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <label>Fecha:</label><br>
    <input type="text" value="<?php echo date('Y-m-d'); ?>" readonly><br><br>
    <label>Empleado:</label><br>
    <select name="id_empleado" required><?php echo selectEmpleados($conn); ?></select><br><br>
    <label>Producto:</label><br>
    <select name="id_producto" required><?php echo selectProductos($conn); ?></select><br><br>
    <label>Cantidad:</label><br><input type="number" name="cantidad" min="1" required><br><br>
    <label>Precio unitario:</label><br><input type="number" step="0.01" name="precio_unitario" min="0" required><br><br>
    <button type="submit" name="guardar">Guardar</button>
</form>
<?php endif; ?>

<br>
<form method="POST" action="menu.php">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <button type="submit">Menú Principal</button>
</form>
<hr>

<h3>Listado de Ventas</h3>
<table border="1" cellpadding="5" cellspacing="0">
<thead><tr>
<th>ID</th><th>Fecha</th><th>Empleado</th><th>Producto</th><th>Cantidad</th>
<?php if ($esAdmin): ?><th>P. Unit.</th><th>Subtotal</th><th>Total</th><th>Acciones</th><?php endif; ?>
</tr></thead>
<tbody>
<?php if ($registros && $registros->num_rows > 0): ?>
<?php while ($f = $registros->fetch_assoc()): ?>
<tr>
<td><?php echo htmlspecialchars($f["id_venta"]); ?></td>
<td><?php echo htmlspecialchars($f["fecha"]); ?></td>
<td><?php echo htmlspecialchars($f["empleado_nombre"] ?? "—"); ?></td>
<td><?php echo htmlspecialchars($f["producto_nombre"] ?? "—"); ?></td>
<td><?php echo htmlspecialchars($f["cantidad"]); ?></td>
<?php if ($esAdmin): ?>
<td><?php echo htmlspecialchars($f["precio_unitario"]); ?></td>
<td><?php echo htmlspecialchars($f["subtotal"]); ?></td>
<td><?php echo htmlspecialchars($f["total"]); ?></td>
<td>
    <a href="?accion=editar&id_venta=<?php echo $f['id_venta']; ?>&rol=<?php echo htmlspecialchars($rol); ?>">Editar</a>
    <a href="?accion=eliminar&id_venta=<?php echo $f['id_venta']; ?>&rol=<?php echo htmlspecialchars($rol); ?>" onclick="return confirm('¿Eliminar?');">Eliminar</a>
</td>
<?php endif; ?>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="9">No se encontraron registros.</td></tr>
<?php endif; ?>
</tbody></table>
</body>
</html>
<?php $conn->close(); ?>
