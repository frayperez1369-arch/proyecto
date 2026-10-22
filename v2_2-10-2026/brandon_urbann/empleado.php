<?php
include("conexion.php");
 
$mensaje = "";
$registro_editar = null;
$busqueda = "";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
 
// Leer el rol del SISTEMA (llega por POST desde el menú o por GET desde los enlaces)
$rol_sistema = "";
if (isset($_POST["rol_sistema"])) {
    $rol_sistema = $_POST["rol_sistema"];
}
if (isset($_GET["rol_sistema"])) {
    $rol_sistema = $_GET["rol_sistema"];
}
// Compatibilidad: si llega "rol" desde el menú, lo tomamos
if ($rol_sistema === "" && isset($_GET["rol"])) {
    $rol_sistema = $_GET["rol"];
}
 
// Eliminar
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id_empleado'])) {
    $id_eliminar = $_GET['id_empleado'];
    try {
        $sql_del = "DELETE FROM empleado WHERE id_empleado = ?";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bind_param("i", $id_eliminar);
        $stmt_del->execute();
        $mensaje = "<div class='alert alert-success'>Empleado eliminado correctamente.</div>";
        $stmt_del->close();
    } catch (mysqli_sql_exception $e) {
        $mensaje = "<div class='alert alert-danger'>Error al eliminar: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
 
// Editar
if (isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['id_empleado'])) {
    $id_buscar = $_GET['id_empleado'];
    $sql_buscar = "SELECT * FROM empleado WHERE id_empleado = ?";
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
        $nombre = $_POST["nombre"];
        $telefono = $_POST["telefono"];
        $email = $_POST["email"];
        $tipo_documento = $_POST["tipo_documento"];
        $documento = $_POST["documento"];
        $username = $_POST["username"];
        $password = $_POST["password"];
        $rol_empleado = $_POST["rol"];
        $estado = $_POST["estado"];
        try {
            $sql = "INSERT INTO empleado (nombre, telefono, email, tipo_documento, documento, username, password, rol, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssss", $nombre, $telefono, $email, $tipo_documento, $documento, $username, $password, $rol_empleado, $estado);
            $stmt->execute();
            $mensaje = "<div class='alert alert-success'>Empleado registrado correctamente.</div>";
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
 
    if (isset($_POST["actualizar"])) {
        $id = $_POST["id"];
        $nombre = $_POST["nombre"];
        $telefono = $_POST["telefono"];
        $email = $_POST["email"];
        $tipo_documento = $_POST["tipo_documento"];
        $documento = $_POST["documento"];
        $username = $_POST["username"];
        $password = $_POST["password"];
        $rol_empleado = $_POST["rol"];
        $estado = $_POST["estado"];
        try {
            $sql_up = "UPDATE empleado SET nombre = ?, telefono = ?, email = ?, tipo_documento = ?, documento = ?,
                       username = ?, password = ?, rol = ?, estado = ? WHERE id_empleado = ?";
            $stmt_up = $conn->prepare($sql_up);
            $stmt_up->bind_param("sssssssssi", $nombre, $telefono, $email, $tipo_documento, $documento, $username, $password, $rol_empleado, $estado, $id);
            $stmt_up->execute();
            $mensaje = "<div class='alert alert-success'>Empleado actualizado correctamente.</div>";
            $stmt_up->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
 
// Buscar o listar
if (isset($_GET['buscar']) && !empty(trim($_GET['buscar']))) {
    $busqueda = trim($_GET['buscar']);
    $param = "%$busqueda%";
    $stmt = $conn->prepare("SELECT * FROM empleado WHERE nombre LIKE ? OR email LIKE ? OR documento LIKE ? OR username LIKE ? ORDER BY id_empleado DESC");
    $stmt->bind_param("ssss", $param, $param, $param, $param);
    $stmt->execute();
    $registros = $stmt->get_result();
} else {
    $registros = $conn->query("SELECT * FROM empleado ORDER BY id_empleado DESC");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Empleados</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="general.css">
</head>
<body>
<div class="d-flex">
<?php $rol_nav = $rol_sistema; $pagina_activa = 'empleado'; include("sidebar.php"); ?>
<main class="flex-grow-1 p-4" style="min-width:0;">
<div class="d-flex justify-content-between align-items-start mb-3">
    <div><h2 class="fw-bold mb-0">Gestión de Empleados</h2><small class="text-muted">Administra el personal del sistema JFYJ</small></div>
    <?php if ($rol_sistema !== ""): ?><span class="badge bg-danger"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($rol_sistema); ?></span><?php endif; ?>
</div>

<?php echo $mensaje; ?>
 
<?php if ($registro_editar): ?>
<div class="card shadow-sm mb-3">
<div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square text-danger me-1"></i> Editar Empleado N. <?php echo htmlspecialchars($registro_editar['id_empleado']); ?></div>
<div class="card-body">
<form method="POST">
    <input type="hidden" name="rol_sistema" value="<?php echo htmlspecialchars($rol_sistema); ?>">
    <input type="hidden" name="id" value="<?php echo $registro_editar['id_empleado']; ?>">
    <div class="row g-3">
<div class="col-md-4"><label class="form-label">Tipo de documento</label><select class="form-select" name="tipo_documento" required>
        <option value="CC" <?php echo ($registro_editar["tipo_documento"]==="CC")?"selected":""; ?>>Cédula de Ciudadanía</option>
        <option value="CE" <?php echo ($registro_editar["tipo_documento"]==="CE")?"selected":""; ?>>Cédula de Extranjería</option>
        <option value="TI" <?php echo ($registro_editar["tipo_documento"]==="TI")?"selected":""; ?>>Tarjeta de Identidad</option>
        <option value="PA" <?php echo ($registro_editar["tipo_documento"]==="PA")?"selected":""; ?>>Pasaporte</option>
        <option value="NIT" <?php echo ($registro_editar["tipo_documento"]==="NIT")?"selected":""; ?>>NIT</option>
    </select></div>
    <div class="col-md-4"><label class="form-label">Documento</label><input class="form-control" type="text" name="documento" value="<?php echo htmlspecialchars($registro_editar["documento"]); ?>" required></div>
    <div class="col-md-4"><label class="form-label">Nombre</label><input class="form-control" type="text" name="nombre" value="<?php echo htmlspecialchars($registro_editar["nombre"]); ?>" required></div>
    <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="text" name="email" value="<?php echo htmlspecialchars($registro_editar["email"]); ?>" required></div>
     <div class="col-md-4"><label class="form-label">Teléfono</label><input class="form-control" type="text" name="telefono" value="<?php echo htmlspecialchars($registro_editar["telefono"]); ?>" required></div>
    <div class="col-md-4"><label class="form-label">Usuario</label><input class="form-control" type="text" name="username" value="<?php echo htmlspecialchars($registro_editar["username"]); ?>" required></div>
    <div class="col-md-4"><label class="form-label">Contraseña</label><input class="form-control" type="text" name="password" value="<?php echo htmlspecialchars($registro_editar["password"]); ?>" required></div>
    <div class="col-md-4"><label class="form-label">Rol</label><select class="form-select" name="rol" required>
        <option value="Administrador" <?php echo ($registro_editar["rol"]==="Administrador")?"selected":""; ?>>Administrador</option>
        <option value="Empleado" <?php echo ($registro_editar["rol"]==="Empleado")?"selected":""; ?>>Empleado</option>
    </select></div>
    <div class="col-md-4"><label class="form-label">Estado</label><select class="form-select" name="estado" required>
        <option value="Activo" <?php echo ($registro_editar["estado"]==="Activo")?"selected":""; ?>>Activo</option>
        <option value="Inactivo" <?php echo ($registro_editar["estado"]==="Inactivo")?"selected":""; ?>>Inactivo</option>
    </select></div>
    </div>
<div class="mt-3">
<button type="submit" name="actualizar" class="btn btn-danger"><i class="bi bi-arrow-repeat me-1"></i>Actualizar</button>
    <a href="?rol_sistema=<?php echo htmlspecialchars($rol_sistema); ?>" class="btn btn-outline-secondary ms-1">Cancelar</a>
</div>
</form>
</div>
</div>
<?php else: ?>
<div class="card shadow-sm mb-3">
<div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle text-danger me-1"></i> Registrar Empleado</div>
<div class="card-body">
<form method="POST">
    <input type="hidden" name="rol_sistema" value="<?php echo htmlspecialchars($rol_sistema); ?>">
    <div class="row g-3">
<div class="col-md-4"><label class="form-label">Tipo de documento</label><select class="form-select" name="tipo_documento" required>
        <option value="CC" selected>Cédula de Ciudadanía</option>
        <option value="CE">Cédula de Extranjería</option>
        <option value="TI">Tarjeta de Identidad</option>
        <option value="PA">Pasaporte</option>
        <option value="NIT">NIT</option>
    </select></div>
    <div class="col-md-4"><label class="form-label">Documento</label><input class="form-control" type="text" name="documento" required></div>
    <div class="col-md-4"><label class="form-label">Nombre</label><input class="form-control" type="text" name="nombre" required></div>
     <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="text" name="email" required></div>
    <div class="col-md-4"><label class="form-label">Teléfono</label><input class="form-control" type="text" name="telefono" required></div>
    <div class="col-md-4"><label class="form-label">Usuario</label><input class="form-control" type="text" name="username" required></div>
    <div class="col-md-4"><label class="form-label">Contraseña</label><input class="form-control" type="text" name="password" required></div>
    <div class="col-md-4"><label class="form-label">Rol</label><select class="form-select" name="rol" required>
        <option value="Administrador">Administrador</option>
        <option value="Empleado" selected>Empleado</option>
    </select></div>
    <div class="col-md-4"><label class="form-label">Estado</label><select class="form-select" name="estado" required>
        <option value="Activo" selected>Activo</option>
        <option value="Inactivo">Inactivo</option>
    </select></div>
    </div>
<div class="mt-3">
<button type="submit" name="guardar" class="btn btn-danger"><i class="bi bi-save me-1"></i>Guardar</button>
</div>
</form>
</div>
</div>
<?php endif; ?>
 
<form method="POST" action="menu.php" class="mb-3">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol_sistema); ?>">
    <button type="submit" class="btn btn-dark"><i class="bi bi-arrow-left me-1"></i>Menú Principal</button>
</form>
 
<div class="card shadow-sm mb-3">
<div class="card-body">
<form method="GET" class="d-flex gap-2 align-items-center">
    <input type="hidden" name="rol_sistema" value="<?php echo htmlspecialchars($rol_sistema); ?>">
    <input type="text" class="form-control form-control-sm" name="buscar" placeholder="Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
    <button type="submit" class="btn btn-danger btn-sm text-nowrap"><i class="bi bi-search me-1"></i>Buscar</button>
    <?php if (!empty($busqueda)): ?><a href="?rol_sistema=<?php echo htmlspecialchars($rol_sistema); ?>" class="btn btn-outline-secondary btn-sm text-nowrap">Mostrar todos</a><?php endif; ?>
</form>
</div>
</div>
 
<div class="card shadow-sm">
<div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul text-danger me-1"></i> Listado</div>
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<thead class="table-light"><tr>
<th>ID</th><th>Tipo Doc.</th><th>Documento</th><th>Nombre</th><th>Email</th><th>Teléfono</th>
<th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th>
</tr></thead>
<tbody>
<?php if ($registros && $registros->num_rows > 0): ?>
<?php while ($f = $registros->fetch_assoc()): ?>
<tr>
<td><span class="badge bg-secondary"><?php echo htmlspecialchars($f["id_empleado"]); ?></span></td>
<td><?php echo htmlspecialchars($f["tipo_documento"]); ?></td>
<td><?php echo htmlspecialchars($f["documento"]); ?></td>
<td class="fw-bold"><?php echo htmlspecialchars($f["nombre"]); ?></td>
<td><?php echo htmlspecialchars($f["email"]); ?></td>
<td><?php echo htmlspecialchars($f["telefono"]); ?></td>
<td><?php echo htmlspecialchars($f["username"]); ?></td>
<td><?php echo htmlspecialchars($f["rol"]); ?></td>
<td><?php echo htmlspecialchars($f["estado"]); ?></td>
<td class="text-end text-nowrap">
    <a class="btn btn-warning btn-sm" title="Editar" href="?accion=editar&id_empleado=<?php echo $f['id_empleado']; ?>&rol_sistema=<?php echo htmlspecialchars($rol_sistema); ?>"><i class="bi bi-pencil"></i></a>
    <a class="btn btn-danger btn-sm" title="Eliminar" href="?accion=eliminar&id_empleado=<?php echo $f['id_empleado']; ?>&rol_sistema=<?php echo htmlspecialchars($rol_sistema); ?>" onclick="return confirm(\'¿Eliminar?\');"><i class="bi bi-trash"></i></a>
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
