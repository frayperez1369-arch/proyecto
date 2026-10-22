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
 
// Si es empleado, solo puede consultar
$es_empleado = (strtolower(trim($rol)) === "empleado");
$mensaje_permiso = "<p style='color:red;font-weight:bold;'>No tiene permisos para realizar esta acción.</p>";
 
// Eliminar
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id_producto'])) {
    if ($es_empleado) {
        $mensaje = $mensaje_permiso;
    } else {
        $id_eliminar = $_GET['id_producto'];
        try {
            $sql_del = "DELETE FROM producto WHERE id_producto = ?";
            $stmt_del = $conn->prepare($sql_del);
            $stmt_del->bind_param("i", $id_eliminar);
            $stmt_del->execute();
            $mensaje = "<p style='color:green;font-weight:bold;'>Producto eliminado correctamente.</p>";
            $stmt_del->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<p style='color:red;font-weight:bold;'>Error al eliminar: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}
 
// Editar (cargar datos en el formulario)
if (!$es_empleado && isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['id_producto'])) {
    $id_buscar = $_GET['id_producto'];
    $sql_buscar = "SELECT * FROM producto WHERE id_producto = ?";
    $stmt_buscar = $conn->prepare($sql_buscar);
    $stmt_buscar->bind_param("i", $id_buscar);
    $stmt_buscar->execute();
    $res = $stmt_buscar->get_result();
    $registro_editar = $res->fetch_assoc();
    $stmt_buscar->close();
}
 
// Guardar o actualizar
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($es_empleado) {
        if (isset($_POST["guardar"]) || isset($_POST["actualizar"])) {
            $mensaje = $mensaje_permiso;
        }
    } else {
        if (isset($_POST["guardar"])) {
            $nombre = $_POST["nombre"];
            $talla = $_POST["talla"];
            $color = $_POST["color"];
            $stock = $_POST["stock"];
            $precio_venta = $_POST["precio_venta"];
            $id_categoria = $_POST["id_categoria"];
            try {
                $sql = "INSERT INTO producto (nombre, talla, color, stock, precio_venta, id_categoria) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssidi", $nombre, $talla, $color, $stock, $precio_venta, $id_categoria);
                $stmt->execute();
                $mensaje = "<p style='color:green;font-weight:bold;'>Producto registrado correctamente.</p>";
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                $mensaje = "<p style='color:red;font-weight:bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
 
        if (isset($_POST["actualizar"])) {
            $id = $_POST["id"];
            $nombre = $_POST["nombre"];
            $talla = $_POST["talla"];
            $color = $_POST["color"];
            $stock = $_POST["stock"];
            $precio_venta = $_POST["precio_venta"];
            $id_categoria = $_POST["id_categoria"];
            try {
                $sql_up = "UPDATE producto SET nombre=?, talla=?, color=?, stock=?, precio_venta=?, id_categoria=? WHERE id_producto=?";
                $stmt_up = $conn->prepare($sql_up);
                $stmt_up->bind_param("sssidii", $nombre, $talla, $color, $stock, $precio_venta, $id_categoria, $id);
                $stmt_up->execute();
                $mensaje = "<p style='color:green;font-weight:bold;'>Producto actualizado correctamente.</p>";
                $stmt_up->close();
            } catch (mysqli_sql_exception $e) {
                $mensaje = "<p style='color:red;font-weight:bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
}
 
// Buscar o listar
if (isset($_GET['buscar']) && !empty(trim($_GET['buscar']))) {
    $busqueda = trim($_GET['buscar']);
    $param = "%$busqueda%";
    $sql = "SELECT p.*, c.nombre AS categoria_nombre FROM producto p LEFT JOIN categoria c ON p.id_categoria=c.id_categoria WHERE p.nombre LIKE ? OR p.talla LIKE ? OR p.color LIKE ? ORDER BY p.id_producto DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $param, $param, $param);
    $stmt->execute();
    $registros = $stmt->get_result();
} else {
    $registros = $conn->query("SELECT p.*, c.nombre AS categoria_nombre FROM producto p LEFT JOIN categoria c ON p.id_categoria=c.id_categoria ORDER BY p.id_producto DESC");
}
 
// Función simple para select de categorías
function selectCategorias($conn, $seleccionado = null) {
    $sql = "SELECT id_categoria, nombre FROM categoria ORDER BY id_categoria ASC";
    $res = $conn->query($sql);
    $options = '<option value="">Seleccione categoría</option>';
    if ($res && $res->num_rows > 0) {
        while ($cat = $res->fetch_assoc()) {
            $sel = ($seleccionado == $cat['id_categoria']) ? 'selected' : '';
            $options .= '<option value="' . htmlspecialchars($cat['id_categoria']) . '" ' . $sel . '>'
                . htmlspecialchars($cat['id_categoria']) . ' - ' . htmlspecialchars($cat['nombre'])
                . '</option>';
        }
    } else {
        $options .= '<option value="">No hay categorías</option>';
    }
    return $options;
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Productos</title></head>
<body>
<?php echo $mensaje; ?>
 
<?php if (!$es_empleado): ?>
 
    <?php if ($registro_editar): ?>
    <!-- FORMULARIO DE EDICIÓN -->
    <h2>Editar Producto N. <?php echo htmlspecialchars($registro_editar['id_producto']); ?></h2>
    <form method="POST">
        <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($registro_editar['id_producto']); ?>">
        <label>Nombre:</label><br><input type="text" name="nombre" value="<?php echo htmlspecialchars($registro_editar["nombre"]); ?>" required><br><br>
        <label>Talla:</label><br><input type="text" name="talla" value="<?php echo htmlspecialchars($registro_editar["talla"]); ?>" required><br><br>
        <label>Color:</label><br><input type="text" name="color" value="<?php echo htmlspecialchars($registro_editar["color"]); ?>" required><br><br>
        <label>Stock:</label><br><input type="number" name="stock" value="<?php echo htmlspecialchars($registro_editar["stock"]); ?>" required><br><br>
        <label>Precio de venta:</label><br><input type="number" step="0.01" name="precio_venta" value="<?php echo htmlspecialchars($registro_editar["precio_venta"]); ?>" required><br><br>
        <label>Categoría:</label><br>
        <select name="id_categoria" required><?php echo selectCategorias($conn, $registro_editar["id_categoria"]); ?></select><br><br>
        <button type="submit" name="actualizar">Actualizar</button>
        <a href="?rol=<?php echo htmlspecialchars($rol); ?>">Cancelar</a>
    </form>
    <?php else: ?>
    <!-- FORMULARIO DE REGISTRO -->
    <h2>Registrar Producto</h2>
    <form method="POST">
        <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
        <label>Nombre:</label><br><input type="text" name="nombre" required><br><br>
        <label>Talla:</label><br><input type="text" name="talla" required><br><br>
        <label>Color:</label><br><input type="text" name="color" required><br><br>
        <label>Stock:</label><br><input type="number" name="stock" required><br><br>
        <label>Precio de venta:</label><br><input type="number" step="0.01" name="precio_venta" required><br><br>
        <label>Categoría:</label><br>
        <select name="id_categoria" required><?php echo selectCategorias($conn); ?></select><br><br>
        <button type="submit" name="guardar">Guardar</button>
    </form>
    <?php endif; ?>
 
<?php else: ?>
    <h2>Consulta de Productos</h2>
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
 
<h3>Listado de Productos</h3>
<table border="1" cellpadding="5" cellspacing="0">
<thead>
<tr>
    <th>ID</th><th>Nombre</th><th>Talla</th><th>Color</th><th>Stock</th>
    <th>Precio</th><th>Categoría</th>
    <?php if (!$es_empleado): ?><th>Acciones</th><?php endif; ?>
</tr>
</thead>
<tbody>
<?php if ($registros && $registros->num_rows > 0): ?>
<?php while ($f = $registros->fetch_assoc()): ?>
<tr>
    <td><?php echo htmlspecialchars($f["id_producto"]); ?></td>
    <td><?php echo htmlspecialchars($f["nombre"]); ?></td>
    <td><?php echo htmlspecialchars($f["talla"]); ?></td>
    <td><?php echo htmlspecialchars($f["color"]); ?></td>
    <td><?php echo htmlspecialchars($f["stock"]); ?></td>
    <td><?php echo htmlspecialchars($f["precio_venta"]); ?></td>
    <td><?php echo htmlspecialchars($f["categoria_nombre"] ?? "—"); ?></td>
    <?php if (!$es_empleado): ?>
    <td>
        <a href="?accion=editar&id_producto=<?php echo $f['id_producto']; ?>&rol=<?php echo htmlspecialchars($rol); ?>">Editar</a>
        <a href="?accion=eliminar&id_producto=<?php echo $f['id_producto']; ?>&rol=<?php echo htmlspecialchars($rol); ?>" onclick="return confirm('¿Eliminar?');">Eliminar</a>
    </td>
    <?php endif; ?>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="<?php echo $es_empleado ? 7 : 8; ?>">No se encontraron registros.</td></tr>
<?php endif; ?>
</tbody></table>
</body>
</html>
<?php $conn->close(); ?>