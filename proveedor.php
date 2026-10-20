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
$rol_h = htmlspecialchars($rol);
$rol_u = urlencode($rol);
 
// Eliminar
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id_proveedor'])) {
    $id_eliminar = $_GET['id_proveedor'];
    try {
        $sql_del = "DELETE FROM proveedor WHERE id_proveedor = ?";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bind_param("i", $id_eliminar);
        $stmt_del->execute();
        $mensaje = "<div class='alert alert-success'><i class='bi bi-check-circle'></i> Proveedor eliminado correctamente.</div>";
        $stmt_del->close();
    } catch (mysqli_sql_exception $e) {
        $mensaje = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Error al eliminar: " . htmlspecialchars($e->getMessage()) . "</div>";
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
            $mensaje = "<div class='alert alert-success'><i class='bi bi-check-circle'></i> Proveedor registrado correctamente.</div>";
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Error: " . htmlspecialchars($e->getMessage()) . "</div>";
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
            $mensaje = "<div class='alert alert-success'><i class='bi bi-check-circle'></i> Proveedor actualizado correctamente.</div>";
            $stmt_up->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle'></i> Error: " . htmlspecialchars($e->getMessage()) . "</div>";
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
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Proveedores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="general.css">
</head>
<body>
<div class="d-flex">
<?php $rol_nav = $rol; $pagina_activa = 'proveedor'; include("sidebar.php"); ?>
<main class="flex-grow-1 p-4" style="min-width:0;">
<div class="d-flex justify-content-between align-items-start mb-3">
    <div><h2 class="fw-bold mb-0">Gestión de Proveedores</h2><small class="text-muted">Administra los proveedores del inventario JFYJ</small></div>
    <?php if ($rol !== ""): ?><span class="badge bg-danger"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($rol); ?></span><?php endif; ?>
</div>

<?php echo $mensaje; ?>

<?php if ($registro_editar): ?>
<div class="card shadow-sm mb-3">
<div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square text-danger me-1"></i> Editar Proveedor N. <?php echo htmlspecialchars($registro_editar['id_proveedor']); ?></div>
<div class="card-body">
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <input type="hidden" name="id" value="<?php echo $registro_editar['id_proveedor']; ?>">
    <div class="row g-3">
<div class="col-md-3"><label class="form-label">NIT</label><input class="form-control" type="text" name="nit" value="<?php echo htmlspecialchars($registro_editar["nit"]); ?>" required></div>
    <div class="col-md-3"><label class="form-label">Nombre</label><input class="form-control" type="text" name="nombre" value="<?php echo htmlspecialchars($registro_editar["nombre"]); ?>" required></div>
    <div class="col-md-3"><label class="form-label">Teléfono</label><input class="form-control" type="text" name="telefono" value="<?php echo htmlspecialchars($registro_editar["telefono"]); ?>" required></div>
    <div class="col-md-3"><label class="form-label">Dirección</label><input class="form-control" type="text" name="direccion" value="<?php echo htmlspecialchars($registro_editar["direccion"]); ?>" required></div>
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
<div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle text-danger me-1"></i> Registrar Proveedor</div>
<div class="card-body">
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <div class="row g-3">
<div class="col-md-3"><label class="form-label">NIT</label><input class="form-control" type="text" name="nit" required></div>
    <div class="col-md-3"><label class="form-label">Nombre</label><input class="form-control" type="text" name="nombre" required></div>
    <div class="col-md-3"><label class="form-label">Teléfono</label><input class="form-control" type="text" name="telefono" required></div>
    <div class="col-md-3"><label class="form-label">Dirección</label><input class="form-control" type="text" name="direccion" required></div>
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
<div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul text-danger me-1"></i> Listado</div>
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead class="table-light"><tr><th>ID</th><th>NIT</th><th>Nombre</th><th>Teléfono</th><th>Dirección</th><th>Acciones</th></tr></thead>
<tbody>
<?php if ($registros && $registros->num_rows > 0): ?>
<?php while ($f = $registros->fetch_assoc()): ?>
<tr>
<td><span class="badge bg-secondary"><?php echo htmlspecialchars($f["id_proveedor"]); ?></span></td>
<td><?php echo htmlspecialchars($f["nit"]); ?></td>
<td class="fw-bold"><?php echo htmlspecialchars($f["nombre"]); ?></td>
<td><?php echo htmlspecialchars($f["telefono"]); ?></td>
<td><?php echo htmlspecialchars($f["direccion"]); ?></td>
<td class="text-end text-nowrap">
    <a class="btn btn-warning btn-sm" title="Editar" href="?accion=editar&id_proveedor=<?php echo $f['id_proveedor']; ?>&rol=<?php echo htmlspecialchars($rol); ?>"><i class="bi bi-pencil"></i></a>
    <a class="btn btn-danger btn-sm" title="Eliminar" href="?accion=eliminar&id_proveedor=<?php echo $f['id_proveedor']; ?>&rol=<?php echo htmlspecialchars($rol); ?>" onclick="return confirm(\'¿Eliminar?\');"><i class="bi bi-trash"></i></a>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="6" class="text-center text-muted py-4">No se encontraron registros.</td></tr>
<?php endif; ?>
</tbody></table>
</div>
</div>

</main>
</div>
</body>
</html>
<?php $conn->close(); ?>
