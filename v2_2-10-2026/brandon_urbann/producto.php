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
$mensaje_permiso = "<div class='alert alert-danger'>No tiene permisos para realizar esta acción.</div>";
 
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
            $mensaje = "<div class='alert alert-success'>Producto eliminado correctamente.</div>";
            $stmt_del->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<div class='alert alert-danger'>Error al eliminar: " . htmlspecialchars($e->getMessage()) . "</div>";
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
                $mensaje = "<div class='alert alert-success'>Producto registrado correctamente.</div>";
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                $mensaje = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
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
                $mensaje = "<div class='alert alert-success'>Producto actualizado correctamente.</div>";
                $stmt_up->close();
            } catch (mysqli_sql_exception $e) {
                $mensaje = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
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
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Productos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="general.css">
</head>
<body>
<div class="d-flex">
<?php $rol_nav = $rol; $pagina_activa = 'producto'; include("sidebar.php"); ?>
<main class="flex-grow-1 p-4" style="min-width:0;">
<div class="d-flex justify-content-between align-items-start mb-3">
    <div><h2 class="fw-bold mb-0"><?php echo $es_empleado ? 'Consulta de Productos' : 'Gestión de Productos'; ?></h2><small class="text-muted">Prendas, precios y existencias del inventario JFYJ</small></div>
    <?php if ($rol !== ""): ?><span class="badge bg-danger"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($rol); ?></span><?php endif; ?>
</div>

<?php echo $mensaje; ?>
 
<?php if (!$es_empleado): ?>
 
    <?php if ($registro_editar): ?>
    <!-- FORMULARIO DE EDICIÓN -->
    <div class="card shadow-sm mb-3">
<div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square text-danger me-1"></i> Editar Producto N. <?php echo htmlspecialchars($registro_editar['id_producto']); ?></div>
<div class="card-body">
<form method="POST">
        <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($registro_editar['id_producto']); ?>">
        <div class="row g-3">
<div class="col-md-4"><label class="form-label">Nombre</label><input class="form-control" type="text" name="nombre" value="<?php echo htmlspecialchars($registro_editar["nombre"]); ?>" required></div>
        <div class="col-md-4"><label class="form-label">Talla</label><input class="form-control" type="text" name="talla" value="<?php echo htmlspecialchars($registro_editar["talla"]); ?>" required></div>
        <div class="col-md-4"><label class="form-label">Color</label><input class="form-control" type="text" name="color" value="<?php echo htmlspecialchars($registro_editar["color"]); ?>" required></div>
        <div class="col-md-4"><label class="form-label">Stock</label><input class="form-control" type="number" name="stock" value="<?php echo htmlspecialchars($registro_editar["stock"]); ?>" required></div>
        <div class="col-md-4"><label class="form-label">Precio de venta</label><input class="form-control" type="number" step="0.01" name="precio_venta" value="<?php echo htmlspecialchars($registro_editar["precio_venta"]); ?>" required></div>
        <div class="col-md-4"><label class="form-label">Categoría</label><select class="form-select" name="id_categoria" required><?php echo selectCategorias($conn, $registro_editar["id_categoria"]); ?></select></div>
        </div>
<div class="mt-3">
<button type="submit" name="actualizar" class="btn btn-danger"><i class="bi bi-arrow-repeat me-1"></i>Actualizar</button>
        <a href="?rol=<?php echo htmlspecialchars($rol); ?>" class="btn btn-outline-secondary ms-1">Cancelar</a>
</div>
</form>
</div>
</div>
    <?php else: ?>
    <!-- FORMULARIO DE REGISTRO -->
    <div class="card shadow-sm mb-3">
<div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle text-danger me-1"></i> Registrar Producto</div>
<div class="card-body">
<form method="POST">
        <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
        <div class="row g-3">
<div class="col-md-4"><label class="form-label">Nombre</label><input class="form-control" type="text" name="nombre" required></div>
        <div class="col-md-4"><label class="form-label">Talla</label><input class="form-control" type="text" name="talla" required></div>
        <div class="col-md-4"><label class="form-label">Color</label><input class="form-control" type="text" name="color" required></div>
        <div class="col-md-4"><label class="form-label">Stock</label><input class="form-control" type="number" name="stock" required></div>
        <div class="col-md-4"><label class="form-label">Precio de venta</label><input class="form-control" type="number" step="0.01" name="precio_venta" required></div>
        <div class="col-md-4"><label class="form-label">Categoría</label><select class="form-select" name="id_categoria" required><?php echo selectCategorias($conn); ?></select></div>
        </div>
<div class="mt-3">
<button type="submit" name="guardar" class="btn btn-danger"><i class="bi bi-save me-1"></i>Guardar</button>
</div>
</form>
</div>
</div>
    <?php endif; ?>
 
<?php endif; ?>
 
<form method="POST" action="menu.php" class="mb-3">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <button type="submit" class="btn btn-dark"><i class="bi bi-arrow-left me-1"></i>Menú Principal</button>
</form>
 
<div class="card shadow-sm mb-3">
<div class="card-body">
<form method="GET" class="d-flex gap-2 align-items-center">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <input type="text" class="form-control form-control-sm" name="buscar" placeholder="Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
    <button type="submit" class="btn btn-danger btn-sm text-nowrap"><i class="bi bi-search me-1"></i>Buscar</button>
    <?php if (!empty($busqueda)): ?><a href="?rol=<?php echo htmlspecialchars($rol); ?>" class="btn btn-outline-secondary btn-sm text-nowrap">Mostrar todos</a><?php endif; ?>
</form>
</div>
</div>
 
<div class="card shadow-sm">
<div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul text-danger me-1"></i> Listado de Productos</div>
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead class="table-light">
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
    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($f["id_producto"]); ?></span></td>
    <td class="fw-bold"><?php echo htmlspecialchars($f["nombre"]); ?></td>
    <td><?php echo htmlspecialchars($f["talla"]); ?></td>
    <td><?php echo htmlspecialchars($f["color"]); ?></td>
    <td><?php echo htmlspecialchars($f["stock"]); ?></td>
    <td><?php echo htmlspecialchars($f["precio_venta"]); ?></td>
    <td><?php echo htmlspecialchars($f["categoria_nombre"] ?? "—"); ?></td>
    <?php if (!$es_empleado): ?>
    <td class="text-end text-nowrap">
        <a class="btn btn-warning btn-sm" title="Editar" href="?accion=editar&id_producto=<?php echo $f['id_producto']; ?>&rol=<?php echo htmlspecialchars($rol); ?>"><i class="bi bi-pencil"></i></a>
        <a class="btn btn-danger btn-sm" title="Eliminar" href="?accion=eliminar&id_producto=<?php echo $f['id_producto']; ?>&rol=<?php echo htmlspecialchars($rol); ?>" onclick="return confirm(\'¿Eliminar?\');"><i class="bi bi-trash"></i></a>
    </td>
    <?php endif; ?>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="<?php echo $es_empleado ? 7 : 8; ?>" class="text-center text-muted py-4">No se encontraron registros.</td></tr>
<?php endif; ?>
</tbody></table>
</div>
</div>

</main>
</div>
</body>
</html>
<?php $conn->close(); ?>
