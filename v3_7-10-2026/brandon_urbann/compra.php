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

// El empleado de la compra es siempre el administrador
$admin = $conn->query("SELECT id_empleado, nombre FROM empleado WHERE rol='Administrador' ORDER BY id_empleado ASC LIMIT 1")->fetch_assoc();

// Eliminar
if ($esAdmin && isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id_compra'])) {
    $id_eliminar = $_GET['id_compra'];
    try {
        $sql_del = "DELETE FROM compra WHERE id_compra = ?";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bind_param("i", $id_eliminar);
        $stmt_del->execute();
        $mensaje = "<p style='color:green;font-weight:bold;'>Compra eliminada correctamente.</p>";
        $stmt_del->close();
    } catch (mysqli_sql_exception $e) {
        $mensaje = "<p style='color:red;font-weight:bold;'>Error al eliminar: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Editar
if ($esAdmin && isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['id_compra'])) {
    $id_buscar = $_GET['id_compra'];
    $sql_buscar = "SELECT * FROM compra WHERE id_compra = ?";
    $stmt_buscar = $conn->prepare($sql_buscar);
    $stmt_buscar->bind_param("i", $id_buscar);
    $stmt_buscar->execute();
    $res = $stmt_buscar->get_result();
    $registro_editar = $res->fetch_assoc();
    $stmt_buscar->close();
}

// Guardar o actualizar (solo administrador)
if ($esAdmin && $_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["guardar"])) {
        $fecha = date('Y-m-d');
        $id_proveedor = $_POST["id_proveedor"];
        $id_empleado = $admin["id_empleado"];
        $id_producto = $_POST["id_producto"];
        $cantidad = $_POST["cantidad"];
        $precio_unitario = $_POST["precio_unitario"];
        $subtotal = $cantidad * $precio_unitario;
        $total = $subtotal;
        try {
            $sql = "INSERT INTO compra (fecha, id_proveedor, id_empleado, id_producto, cantidad, precio_unitario, subtotal, total)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siiiiddd", $fecha, $id_proveedor, $id_empleado, $id_producto, $cantidad, $precio_unitario, $subtotal, $total);
            $stmt->execute();
            $mensaje = "<p style='color:green;font-weight:bold;'>Compra registrada correctamente.</p>";
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<p style='color:red;font-weight:bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    if (isset($_POST["actualizar"])) {
        $id = $_POST["id"];
        $id_proveedor = $_POST["id_proveedor"];
        $id_empleado = $admin["id_empleado"];
        $id_producto = $_POST["id_producto"];
        $cantidad = $_POST["cantidad"];
        $precio_unitario = $_POST["precio_unitario"];
        $subtotal = $cantidad * $precio_unitario;
        $total = $subtotal;
        try {
            $sql_up = "UPDATE compra SET id_proveedor=?, id_empleado=?, id_producto=?, cantidad=?, precio_unitario=?, subtotal=?, total=? WHERE id_compra=?";
            $stmt_up = $conn->prepare($sql_up);
            $stmt_up->bind_param("iiiidddi", $id_proveedor, $id_empleado, $id_producto, $cantidad, $precio_unitario, $subtotal, $total, $id);
            $stmt_up->execute();
            $mensaje = "<p style='color:green;font-weight:bold;'>Compra actualizada correctamente.</p>";
            $stmt_up->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<p style='color:red;font-weight:bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

// Listar compras
$registros = $conn->query("SELECT c.*, p.nombre AS proveedor_nombre, e.nombre AS empleado_nombre, pr.nombre AS producto_nombre
                           FROM compra c
                           LEFT JOIN proveedor p ON c.id_proveedor = p.id_proveedor
                           LEFT JOIN empleado e  ON c.id_empleado  = e.id_empleado
                           LEFT JOIN producto pr ON c.id_producto  = pr.id_producto
                           ORDER BY c.id_compra DESC");

// Funciones simples para selects
function selectProveedores($conn, $seleccionado = null) {
    $res = $conn->query("SELECT id_proveedor, nombre FROM proveedor ORDER BY id_proveedor ASC");
    $options = '<option value="">Seleccione proveedor</option>';
    while ($f = $res->fetch_assoc()) {
        $sel = ($seleccionado == $f['id_proveedor']) ? 'selected' : '';
        $options .= '<option value="' . $f['id_proveedor'] . '" ' . $sel . '>' . $f['id_proveedor'] . ' - ' . htmlspecialchars($f['nombre']) . '</option>';
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
<head><meta charset="UTF-8"><title>Compras</title></head>
<body>
<?php echo $mensaje; ?>

<?php if (!$esAdmin): ?>
<p style="color:red;font-weight:bold;">Solo el administrador puede registrar compras.</p>
<?php elseif ($registro_editar): ?>
<h2>Editar Compra N. <?php echo htmlspecialchars($registro_editar['id_compra']); ?></h2>
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <input type="hidden" name="id" value="<?php echo $registro_editar['id_compra']; ?>">
    <label>Fecha:</label><br>
    <input type="text" value="<?php echo htmlspecialchars($registro_editar["fecha"]); ?>" readonly><br><br>
    <label>Proveedor:</label><br>
    <select name="id_proveedor" required><?php echo selectProveedores($conn, $registro_editar["id_proveedor"]); ?></select><br><br>
    <label>Empleado:</label><br>
    <input type="text" value="<?php echo htmlspecialchars($admin['id_empleado'] . ' - ' . $admin['nombre']); ?>" readonly><br><br>
    <label>Producto:</label><br>
    <select name="id_producto" required><?php echo selectProductos($conn, $registro_editar["id_producto"]); ?></select><br><br>
    <label>Cantidad:</label><br>
    <input type="number" name="cantidad" value="<?php echo htmlspecialchars($registro_editar["cantidad"]); ?>" required><br><br>
    <label>Precio unitario:</label><br>
    <input type="number" step="0.01" name="precio_unitario" value="<?php echo htmlspecialchars($registro_editar["precio_unitario"]); ?>" required><br><br>
    <button type="submit" name="actualizar">Actualizar</button>
    <a href="?rol=<?php echo htmlspecialchars($rol); ?>">Cancelar</a>
</form>
<?php else: ?>
<h2>Registrar Compra</h2>
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <label>Fecha:</label><br>
    <input type="text" value="<?php echo date('Y-m-d'); ?>" readonly><br><br>
    <label>Proveedor:</label><br>
    <select name="id_proveedor" required><?php echo selectProveedores($conn); ?></select><br><br>
    <label>Empleado:</label><br>
    <input type="text" value="<?php echo htmlspecialchars($admin['id_empleado'] . ' - ' . $admin['nombre']); ?>" readonly><br><br>
    <label>Producto:</label><br>
    <select name="id_producto" required><?php echo selectProductos($conn); ?></select><br><br>
    <label>Cantidad:</label><br><input type="number" name="cantidad" required><br><br>
    <label>Precio unitario:</label><br><input type="number" step="0.01" name="precio_unitario" required><br><br>
    <button type="submit" name="guardar">Guardar</button>
</form>
<?php endif; ?>

<br>
<form method="POST" action="menu.php">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <button type="submit">Menú Principal</button>
</form>
<hr>

<h3>Listado de Compras</h3>
<table border="1" cellpadding="5" cellspacing="0">
<thead><tr>
<th>ID</th><th>Fecha</th><th>Proveedor</th><th>Empleado</th><th>Producto</th>
<th>Cantidad</th><th>P. Unit.</th><th>Subtotal</th><th>Total</th><th>Acciones</th>
</tr></thead>
<tbody>
<?php if ($registros && $registros->num_rows > 0): ?>
<?php while ($f = $registros->fetch_assoc()): ?>
<tr>
<td><?php echo htmlspecialchars($f["id_compra"]); ?></td>
<td><?php echo htmlspecialchars($f["fecha"]); ?></td>
<td><?php echo htmlspecialchars($f["proveedor_nombre"] ?? "—"); ?></td>
<td><?php echo htmlspecialchars($f["empleado_nombre"] ?? "—"); ?></td>
<td><?php echo htmlspecialchars($f["producto_nombre"] ?? "—"); ?></td>
<td><?php echo htmlspecialchars($f["cantidad"]); ?></td>
<td><?php echo htmlspecialchars($f["precio_unitario"]); ?></td>
<td><?php echo htmlspecialchars($f["subtotal"]); ?></td>
<td><?php echo htmlspecialchars($f["total"]); ?></td>
<td>
<?php if ($esAdmin): ?>
    <a href="?accion=editar&id_compra=<?php echo $f['id_compra']; ?>&rol=<?php echo htmlspecialchars($rol); ?>">Editar</a>
    <a href="?accion=eliminar&id_compra=<?php echo $f['id_compra']; ?>&rol=<?php echo htmlspecialchars($rol); ?>" onclick="return confirm('¿Eliminar?');">Eliminar</a>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="10">No se encontraron registros.</td></tr>
<?php endif; ?>
</tbody></table>
</body>
</html>
<?php $conn->close(); ?>
