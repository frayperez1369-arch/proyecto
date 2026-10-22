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
        $mensaje = "<div class='alert alert-success'>Compra eliminada correctamente.</div>";
        $stmt_del->close();
    } catch (mysqli_sql_exception $e) {
        $mensaje = "<div class='alert alert-danger'>Error al eliminar: " . htmlspecialchars($e->getMessage()) . "</div>";
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
            $mensaje = "<div class='alert alert-success'>Compra registrada correctamente.</div>";
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
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
            $mensaje = "<div class='alert alert-success'>Compra actualizada correctamente.</div>";
            $stmt_up->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
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
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="general.css">
</head>
<body>
<div class="d-flex">
<?php $rol_nav = $rol; $pagina_activa = 'compra'; include("sidebar.php"); ?>
<main class="flex-grow-1 p-4" style="min-width:0;">
<div class="d-flex justify-content-between align-items-start mb-3">
    <div><h2 class="fw-bold mb-0">Registro de Compras</h2><small class="text-muted">Entrada de mercancía al inventario JFYJ</small></div>
    <?php if ($rol !== ""): ?><span class="badge bg-danger"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($rol); ?></span><?php endif; ?>
</div>

<?php echo $mensaje; ?>

<?php if (!$esAdmin): ?>
<div class='alert alert-danger'>Solo el administrador puede registrar compras.</div>
<?php elseif ($registro_editar): ?>
<div class="card shadow-sm mb-3">
<div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square text-danger me-1"></i> Editar Compra N. <?php echo htmlspecialchars($registro_editar['id_compra']); ?></div>
<div class="card-body">
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <input type="hidden" name="id" value="<?php echo $registro_editar['id_compra']; ?>">
    <div class="row g-3">
<div class="col-md-4"><label class="form-label">Fecha</label><input class="form-control" type="text" value="<?php echo htmlspecialchars($registro_editar["fecha"]); ?>" readonly></div>
    <div class="col-md-4"><label class="form-label">Proveedor</label><select class="form-select" name="id_proveedor" required><?php echo selectProveedores($conn, $registro_editar["id_proveedor"]); ?></select></div>
    <div class="col-md-4"><label class="form-label">Empleado</label><input class="form-control" type="text" value="<?php echo htmlspecialchars($admin['id_empleado'] . ' - ' . $admin['nombre']); ?>" readonly></div>
    <div class="col-md-4"><label class="form-label">Producto</label><select class="form-select" name="id_producto" required><?php echo selectProductos($conn, $registro_editar["id_producto"]); ?></select></div>
    <div class="col-md-4"><label class="form-label">Cantidad</label><input class="form-control" type="number" name="cantidad" value="<?php echo htmlspecialchars($registro_editar["cantidad"]); ?>" required></div>
    <div class="col-md-4"><label class="form-label">Precio unitario</label><input class="form-control" type="number" step="0.01" name="precio_unitario" value="<?php echo htmlspecialchars($registro_editar["precio_unitario"]); ?>" required></div>
    </div>
<div class="mt-3">
<button type="submit" name="actualizar" class="btn btn-danger"><i class="bi bi-arrow-repeat me-1"></i>Actualizar</button>
    <a href="?rol=<?php echo htmlspecialchars($rol); ?>" class="btn btn-outline-secondary ms-1">Cancelar</a>
</div>
</form>
</div>
</div>
<?php else: ?>
<div class="card shadow-sm mb-3">
<div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle text-danger me-1"></i> Registrar Compra</div>
<div class="card-body">
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <div class="row g-3">
<div class="col-md-4"><label class="form-label">Fecha</label><input class="form-control" type="text" value="<?php echo date('Y-m-d'); ?>" readonly></div>
    <div class="col-md-4"><label class="form-label">Proveedor</label><select class="form-select" name="id_proveedor" required><?php echo selectProveedores($conn); ?></select></div>
    <div class="col-md-4"><label class="form-label">Empleado</label><input class="form-control" type="text" value="<?php echo htmlspecialchars($admin['id_empleado'] . ' - ' . $admin['nombre']); ?>" readonly></div>
    <div class="col-md-4"><label class="form-label">Producto</label><select class="form-select" name="id_producto" required><?php echo selectProductos($conn); ?></select></div>
    <div class="col-md-4"><label class="form-label">Cantidad</label><input class="form-control" type="number" name="cantidad" required></div>
    <div class="col-md-4"><label class="form-label">Precio unitario</label><input class="form-control" type="number" step="0.01" name="precio_unitario" required></div>
    </div>
<div class="mt-3">
<button type="submit" name="guardar" class="btn btn-danger"><i class="bi bi-save me-1"></i>Guardar</button>
</div>
</form>
</div>
</div>
<?php endif; ?>

<form method="POST" action="menu.php" class="mb-3">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <button type="submit" class="btn btn-dark"><i class="bi bi-arrow-left me-1"></i>Menú Principal</button>
</form>

<div class="card shadow-sm">
<div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul text-danger me-1"></i> Listado de Compras</div>
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead class="table-light"><tr>
<th>ID</th><th>Fecha</th><th>Proveedor</th><th>Empleado</th><th>Producto</th>
<th>Cantidad</th><th>P. Unit.</th><th>Subtotal</th><th>Total</th><th>Acciones</th>
</tr></thead>
<tbody>
<?php if ($registros && $registros->num_rows > 0): ?>
<?php while ($f = $registros->fetch_assoc()): ?>
<tr>
<td><span class="badge bg-secondary"><?php echo htmlspecialchars($f["id_compra"]); ?></span></td>
<td><?php echo htmlspecialchars($f["fecha"]); ?></td>
<td><?php echo htmlspecialchars($f["proveedor_nombre"] ?? "—"); ?></td>
<td><?php echo htmlspecialchars($f["empleado_nombre"] ?? "—"); ?></td>
<td><?php echo htmlspecialchars($f["producto_nombre"] ?? "—"); ?></td>
<td><?php echo htmlspecialchars($f["cantidad"]); ?></td>
<td><?php echo htmlspecialchars($f["precio_unitario"]); ?></td>
<td><?php echo htmlspecialchars($f["subtotal"]); ?></td>
<td><?php echo htmlspecialchars($f["total"]); ?></td>
<td class="text-end text-nowrap">
<?php if ($esAdmin): ?>
    <a class="btn btn-warning btn-sm" title="Editar" href="?accion=editar&id_compra=<?php echo $f['id_compra']; ?>&rol=<?php echo htmlspecialchars($rol); ?>"><i class="bi bi-pencil"></i></a>
    <a class="btn btn-danger btn-sm" title="Eliminar" href="?accion=eliminar&id_compra=<?php echo $f['id_compra']; ?>&rol=<?php echo htmlspecialchars($rol); ?>" onclick="return confirm(\'¿Eliminar?\');"><i class="bi bi-trash"></i></a>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="10" class="text-center text-muted py-4">No se encontraron registros.</td></tr>
<?php endif; ?>
</tbody></table>
</div>
</div>

</main>
</div>
</body>
</html>
<?php $conn->close(); ?>
