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
        $mensaje = "<div class='alerta ok'><i class='bi bi-check-circle'></i> Proveedor eliminado correctamente.</div>";
        $stmt_del->close();
    } catch (mysqli_sql_exception $e) {
        $mensaje = "<div class='alerta error'><i class='bi bi-exclamation-triangle'></i> Error al eliminar: " . htmlspecialchars($e->getMessage()) . "</div>";
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
            $mensaje = "<div class='alerta ok'><i class='bi bi-check-circle'></i> Proveedor registrado correctamente.</div>";
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<div class='alerta error'><i class='bi bi-exclamation-triangle'></i> Error: " . htmlspecialchars($e->getMessage()) . "</div>";
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
            $mensaje = "<div class='alerta ok'><i class='bi bi-check-circle'></i> Proveedor actualizado correctamente.</div>";
            $stmt_up->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<div class='alerta error'><i class='bi bi-exclamation-triangle'></i> Error: " . htmlspecialchars($e->getMessage()) . "</div>";
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="proveedor.css">
</head>
<body>
<div class="layout">
 
    <!-- ============ SIDEBAR ============ -->
    <aside class="sidebar">
        <div class="logo-wrap">
            <!-- Guarda tu logo como logo.png en la misma carpeta -->
            <div class="logo-circulo">
                <img src="logo.png" alt="JFYJ" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <span>J</span>
            </div>
            <div class="brand-jfyj">JFYJ</div>
        </div>
 
        <nav>
            <form method="POST" action="menu.php">
                <input type="hidden" name="rol" value="<?php echo $rol_h; ?>">
                <button type="submit" class="nav-link"><i class="bi bi-house-door"></i>Inicio</button>
            </form>
            <a class="nav-link" href="producto.php?rol=<?php echo $rol_u; ?>"><i class="bi bi-box-seam"></i>Productos</a>
            <?php if ($rol === "Administrador"): ?>
                <a class="nav-link" href="categoria.php?rol=<?php echo $rol_u; ?>"><i class="bi bi-grid"></i>Categorías</a>
                <a class="nav-link active" href="proveedor.php?rol=<?php echo $rol_u; ?>"><i class="bi bi-truck"></i>Proveedores</a>
                <a class="nav-link" href="empleado.php?rol=<?php echo $rol_u; ?>"><i class="bi bi-people"></i>Empleados</a>
                <a class="nav-link" href="compra.php?rol=<?php echo $rol_u; ?>"><i class="bi bi-bag-plus"></i>Compras</a>
            <?php endif; ?>
            <a class="nav-link" href="venta.php?rol=<?php echo $rol_u; ?>"><i class="bi bi-cart"></i>Ventas</a>
        </nav>
 
        <form method="POST" action="menu.php" class="salir">
            <input type="hidden" name="rol" value="<?php echo $rol_h; ?>">
            <button type="submit" name="btn_cerrar" class="nav-link"><i class="bi bi-box-arrow-left"></i>Cerrar sesión</button>
        </form>
    </aside>
 
    <!-- ============ CONTENIDO ============ -->
    <main class="contenido">
 
        <div class="encabezado">
            <div>
                <h2>Gestión de Proveedores</h2>
                <small>Administra los proveedores del inventario JFYJ</small>
            </div>
            <?php if ($rol !== ""): ?>
                <span class="badge-rol"><i class="bi bi-person-circle"></i> <?php echo $rol_h; ?></span>
            <?php endif; ?>
        </div>
 
        <?php echo $mensaje; ?>
 
        <!-- Formulario registrar / editar -->
        <div class="card">
            <?php if ($registro_editar): ?>
                <div class="card-header"><i class="bi bi-pencil-square"></i>Editar Proveedor N. <?php echo htmlspecialchars($registro_editar['id_proveedor']); ?></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="rol" value="<?php echo $rol_h; ?>">
                        <input type="hidden" name="id" value="<?php echo $registro_editar['id_proveedor']; ?>">
                        <div class="form-grid">
                            <div class="campo"><label>NIT</label><input type="text" name="nit" value="<?php echo htmlspecialchars($registro_editar["nit"]); ?>" required></div>
                            <div class="campo"><label>Nombre</label><input type="text" name="nombre" value="<?php echo htmlspecialchars($registro_editar["nombre"]); ?>" required></div>
                            <div class="campo"><label>Teléfono</label><input type="text" name="telefono" value="<?php echo htmlspecialchars($registro_editar["telefono"]); ?>" required></div>
                            <div class="campo"><label>Dirección</label><input type="text" name="direccion" value="<?php echo htmlspecialchars($registro_editar["direccion"]); ?>" required></div>
                        </div>
                        <div class="form-botones">
                            <button type="submit" name="actualizar" class="btn btn-rojo"><i class="bi bi-arrow-repeat"></i>Actualizar</button>
                            <a href="?rol=<?php echo $rol_u; ?>" class="btn btn-borde">Cancelar</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="card-header"><i class="bi bi-plus-circle"></i>Registrar Proveedor</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="rol" value="<?php echo $rol_h; ?>">
                        <div class="form-grid">
                            <div class="campo"><label>NIT</label><input type="text" name="nit" required></div>
                            <div class="campo"><label>Nombre</label><input type="text" name="nombre" required></div>
                            <div class="campo"><label>Teléfono</label><input type="text" name="telefono" required></div>
                            <div class="campo"><label>Dirección</label><input type="text" name="direccion" required></div>
                        </div>
                        <div class="form-botones">
                            <button type="submit" name="guardar" class="btn btn-rojo"><i class="bi bi-save"></i>Guardar</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
 
        <!-- Botón menú principal -->
        <form method="POST" action="menu.php" style="margin-bottom:16px;">
            <input type="hidden" name="rol" value="<?php echo $rol_h; ?>">
            <button type="submit" class="btn btn-oscuro"><i class="bi bi-arrow-left"></i>Menú Principal</button>
        </form>
 
        <!-- Buscar -->
        <div class="card">
            <div class="card-body">
                <form method="GET" class="buscador">
                    <input type="hidden" name="rol" value="<?php echo $rol_h; ?>">
                    <input type="text" name="buscar" placeholder="Buscar proveedor..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    <button type="submit" class="btn btn-rojo btn-sm"><i class="bi bi-search"></i>Buscar</button>
                    <?php if (!empty($busqueda)): ?>
                        <a href="?rol=<?php echo $rol_u; ?>" class="btn btn-borde btn-sm">Mostrar todos</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
 
        <!-- Listado -->
        <div class="card">
            <div class="card-header"><i class="bi bi-list-ul"></i>Listado de Proveedores</div>
            <div class="tabla-wrap">
                <table class="tabla">
                    <thead>
                        <tr><th>ID</th><th>NIT</th><th>Nombre</th><th>Teléfono</th><th>Dirección</th><th style="text-align:right;">Acciones</th></tr>
                    </thead>
                    <tbody>
                    <?php if ($registros && $registros->num_rows > 0): ?>
                        <?php while ($f = $registros->fetch_assoc()): ?>
                        <tr>
                            <td><span class="id-badge"><?php echo htmlspecialchars($f["id_proveedor"]); ?></span></td>
                            <td><?php echo htmlspecialchars($f["nit"]); ?></td>
                            <td class="nombre"><?php echo htmlspecialchars($f["nombre"]); ?></td>
                            <td class="suave"><?php echo htmlspecialchars($f["telefono"]); ?></td>
                            <td class="suave"><?php echo htmlspecialchars($f["direccion"]); ?></td>
                            <td class="acciones">
                                <a class="btn btn-amarillo btn-sm btn-icono" title="Editar" href="?accion=editar&id_proveedor=<?php echo $f['id_proveedor']; ?>&rol=<?php echo $rol_u; ?>"><i class="bi bi-pencil"></i></a>
                                <a class="btn btn-rojo btn-sm btn-icono" title="Eliminar" href="?accion=eliminar&id_proveedor=<?php echo $f['id_proveedor']; ?>&rol=<?php echo $rol_u; ?>" onclick="return confirm('¿Eliminar?');"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="vacio">No se encontraron registros.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
 
    </main>
</div>
</body>
</html>
<?php $conn->close(); ?>