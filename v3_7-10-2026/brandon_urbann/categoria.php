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

// Eliminar categoría
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id_categoria'])) {
    $id_eliminar = $_GET['id_categoria'];
    try {
        $sql_del = "DELETE FROM categoria WHERE id_categoria = ?";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bind_param("i", $id_eliminar);
        $stmt_del->execute();
        $mensaje = "<p style='color:green;font-weight:bold;'>Categoría eliminada correctamente.</p>";
        $stmt_del->close();
    } catch (mysqli_sql_exception $e) {
        $mensaje = "<p style='color:red;font-weight:bold;'>Error al eliminar: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Cargar datos para editar
if (isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['id_categoria'])) {
    $id_buscar = $_GET['id_categoria'];
    $sql_buscar = "SELECT * FROM categoria WHERE id_categoria = ?";
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
        $descripcion = $_POST["descripcion"];
        try {
            $sql = "INSERT INTO categoria (nombre, descripcion) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $nombre, $descripcion);
            $stmt->execute();
            $mensaje = "<p style='color:green;font-weight:bold;'>Categoría registrada correctamente.</p>";
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<p style='color:red;font-weight:bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    if (isset($_POST["actualizar"])) {
        $id = $_POST["id"];
        $nombre = $_POST["nombre"];
        $descripcion = $_POST["descripcion"];
        try {
            $sql_up = "UPDATE categoria SET nombre = ?, descripcion = ? WHERE id_categoria = ?";
            $stmt_up = $conn->prepare($sql_up);
            $stmt_up->bind_param("ssi", $nombre, $descripcion, $id);
            $stmt_up->execute();
            $mensaje = "<p style='color:green;font-weight:bold;'>Categoría actualizada correctamente.</p>";
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
    $stmt = $conn->prepare("SELECT * FROM categoria WHERE nombre LIKE ? OR descripcion LIKE ? ORDER BY id_categoria DESC");
    $stmt->bind_param("ss", $param, $param);
    $stmt->execute();
    $registros = $stmt->get_result();
} else {
    $registros = $conn->query("SELECT * FROM categoria ORDER BY id_categoria DESC");
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Categorías</title></head>
<body>
<?php echo $mensaje; ?>

<?php if ($registro_editar): ?>
<h2>Editar Categoría N. <?php echo htmlspecialchars($registro_editar['id_categoria']); ?></h2>
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <input type="hidden" name="id" value="<?php echo $registro_editar['id_categoria']; ?>">
    <label>Nombre:</label><br>
    <input type="text" name="nombre" value="<?php echo htmlspecialchars($registro_editar["nombre"]); ?>" required><br><br>
    <label>Descripción:</label><br>
    <input type="text" name="descripcion" value="<?php echo htmlspecialchars($registro_editar["descripcion"]); ?>" required><br><br>
    <button type="submit" name="actualizar">Actualizar</button>
    <a href="?rol=<?php echo htmlspecialchars($rol); ?>">Cancelar</a>
</form>
<?php else: ?>
<h2>Registrar Categoría</h2>
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <label>Nombre:</label><br>
    <input type="text" name="nombre" required><br><br>
    <label>Descripción:</label><br>
    <input type="text" name="descripcion" required><br><br>
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
<thead><tr><th>ID</th><th>Nombre</th><th>Descripción</th><th>Acciones</th></tr></thead>
<tbody>
<?php if ($registros && $registros->num_rows > 0): ?>
<?php while ($f = $registros->fetch_assoc()): ?>
<tr>
<td><?php echo htmlspecialchars($f["id_categoria"]); ?></td>
<td><?php echo htmlspecialchars($f["nombre"]); ?></td>
<td><?php echo htmlspecialchars($f["descripcion"]); ?></td>
<td>
    <a href="?accion=editar&id_categoria=<?php echo $f['id_categoria']; ?>&rol=<?php echo htmlspecialchars($rol); ?>">Editar</a>
    <a href="?accion=eliminar&id_categoria=<?php echo $f['id_categoria']; ?>&rol=<?php echo htmlspecialchars($rol); ?>" onclick="return confirm('¿Eliminar?');">Eliminar</a>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="4">No se encontraron registros.</td></tr>
<?php endif; ?>
</tbody></table>
</body>
</html>
<?php $conn->close(); ?>