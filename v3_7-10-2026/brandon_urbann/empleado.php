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
        $mensaje = "<p style='color:green;font-weight:bold;'>Empleado eliminado correctamente.</p>";
        $stmt_del->close();
    } catch (mysqli_sql_exception $e) {
        $mensaje = "<p style='color:red;font-weight:bold;'>Error al eliminar: " . htmlspecialchars($e->getMessage()) . "</p>";
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
            $mensaje = "<p style='color:green;font-weight:bold;'>Empleado registrado correctamente.</p>";
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<p style='color:red;font-weight:bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
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
            $mensaje = "<p style='color:green;font-weight:bold;'>Empleado actualizado correctamente.</p>";
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
<head><meta charset="UTF-8"><title>Empleados</title></head>
<body>
<?php echo $mensaje; ?>
 
<?php if ($registro_editar): ?>
<h2>Editar Empleado N. <?php echo htmlspecialchars($registro_editar['id_empleado']); ?></h2>
<form method="POST">
    <input type="hidden" name="rol_sistema" value="<?php echo htmlspecialchars($rol_sistema); ?>">
    <input type="hidden" name="id" value="<?php echo $registro_editar['id_empleado']; ?>">
    <label>Tipo de documento:</label><br>
    <select name="tipo_documento" required>
        <option value="CC" <?php echo ($registro_editar["tipo_documento"]==="CC")?"selected":""; ?>>Cédula de Ciudadanía</option>
        <option value="CE" <?php echo ($registro_editar["tipo_documento"]==="CE")?"selected":""; ?>>Cédula de Extranjería</option>
        <option value="TI" <?php echo ($registro_editar["tipo_documento"]==="TI")?"selected":""; ?>>Tarjeta de Identidad</option>
        <option value="PA" <?php echo ($registro_editar["tipo_documento"]==="PA")?"selected":""; ?>>Pasaporte</option>
        <option value="NIT" <?php echo ($registro_editar["tipo_documento"]==="NIT")?"selected":""; ?>>NIT</option>
    </select><br><br>
    <label>Documento:</label><br><input type="text" name="documento" value="<?php echo htmlspecialchars($registro_editar["documento"]); ?>" required><br><br>
    <label>Nombre:</label><br><input type="text" name="nombre" value="<?php echo htmlspecialchars($registro_editar["nombre"]); ?>" required><br><br>
    <label>Email:</label><br><input type="text" name="email" value="<?php echo htmlspecialchars($registro_editar["email"]); ?>" required><br><br>
     <label>Teléfono:</label><br><input type="text" name="telefono" value="<?php echo htmlspecialchars($registro_editar["telefono"]); ?>" required><br><br>
    <label>Usuario:</label><br><input type="text" name="username" value="<?php echo htmlspecialchars($registro_editar["username"]); ?>" required><br><br>
    <label>Contraseña:</label><br><input type="text" name="password" value="<?php echo htmlspecialchars($registro_editar["password"]); ?>" required><br><br>
    <label>Rol:</label><br>
    <select name="rol" required>
        <option value="Administrador" <?php echo ($registro_editar["rol"]==="Administrador")?"selected":""; ?>>Administrador</option>
        <option value="Empleado" <?php echo ($registro_editar["rol"]==="Empleado")?"selected":""; ?>>Empleado</option>
    </select><br><br>
    <label>Estado:</label><br>
    <select name="estado" required>
        <option value="Activo" <?php echo ($registro_editar["estado"]==="Activo")?"selected":""; ?>>Activo</option>
        <option value="Inactivo" <?php echo ($registro_editar["estado"]==="Inactivo")?"selected":""; ?>>Inactivo</option>
    </select><br><br>
    <button type="submit" name="actualizar">Actualizar</button>
    <a href="?rol_sistema=<?php echo htmlspecialchars($rol_sistema); ?>">Cancelar</a>
</form>
<?php else: ?>
<h2>Registrar Empleado</h2>
<form method="POST">
    <input type="hidden" name="rol_sistema" value="<?php echo htmlspecialchars($rol_sistema); ?>">
    <label>Tipo de documento:</label><br>
    <select name="tipo_documento" required>
        <option value="CC" selected>Cédula de Ciudadanía</option>
        <option value="CE">Cédula de Extranjería</option>
        <option value="TI">Tarjeta de Identidad</option>
        <option value="PA">Pasaporte</option>
        <option value="NIT">NIT</option>
    </select><br><br>
    <label>Documento:</label><br><input type="text" name="documento" required><br><br>
    <label>Nombre:</label><br><input type="text" name="nombre" required><br><br>
     <label>Email:</label><br><input type="text" name="email" required><br><br>
    <label>Teléfono:</label><br><input type="text" name="telefono" required><br><br>
    <label>Usuario:</label><br><input type="text" name="username" required><br><br>
    <label>Contraseña:</label><br><input type="text" name="password" required><br><br>
    <label>Rol:</label><br>
    <select name="rol" required>
        <option value="Administrador">Administrador</option>
        <option value="Empleado" selected>Empleado</option>
    </select><br><br>
    <label>Estado:</label><br>
    <select name="estado" required>
        <option value="Activo" selected>Activo</option>
        <option value="Inactivo">Inactivo</option>
    </select><br><br>
    <button type="submit" name="guardar">Guardar</button>
</form>
<?php endif; ?>
 
<br>
<form method="POST" action="menu.php">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol_sistema); ?>">
    <button type="submit">Menú Principal</button>
</form>
<hr>
 
<h3>Buscar</h3>
<form method="GET">
    <input type="hidden" name="rol_sistema" value="<?php echo htmlspecialchars($rol_sistema); ?>">
    <input type="text" name="buscar" placeholder="Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
    <button type="submit">Buscar</button>
    <?php if (!empty($busqueda)): ?><a href="?rol_sistema=<?php echo htmlspecialchars($rol_sistema); ?>">Mostrar todos</a><?php endif; ?>
</form>
 
<h3>Listado</h3>
<table border="1" cellpadding="5" cellspacing="0">
<thead><tr>
<th>ID</th><th>Tipo Doc.</th><th>Documento</th><th>Nombre</th><th>Email</th><th>Teléfono</th>
<th>Usuario</th><th>Rol</th><th>Estado</th><th>Acciones</th>
</tr></thead>
<tbody>
<?php if ($registros && $registros->num_rows > 0): ?>
<?php while ($f = $registros->fetch_assoc()): ?>
<tr>
<td><?php echo htmlspecialchars($f["id_empleado"]); ?></td>
<td><?php echo htmlspecialchars($f["tipo_documento"]); ?></td>
<td><?php echo htmlspecialchars($f["documento"]); ?></td>
<td><?php echo htmlspecialchars($f["nombre"]); ?></td>
<td><?php echo htmlspecialchars($f["email"]); ?></td>
<td><?php echo htmlspecialchars($f["telefono"]); ?></td>
<td><?php echo htmlspecialchars($f["username"]); ?></td>
<td><?php echo htmlspecialchars($f["rol"]); ?></td>
<td><?php echo htmlspecialchars($f["estado"]); ?></td>
<td>
    <a href="?accion=editar&id_empleado=<?php echo $f['id_empleado']; ?>&rol_sistema=<?php echo htmlspecialchars($rol_sistema); ?>">Editar</a>
    <a href="?accion=eliminar&id_empleado=<?php echo $f['id_empleado']; ?>&rol_sistema=<?php echo htmlspecialchars($rol_sistema); ?>" onclick="return confirm('¿Eliminar?');">Eliminar</a>
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