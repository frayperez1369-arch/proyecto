<?php
// empleado.php — Módulo de EMPLEADOS: registrar, buscar, editar y eliminar al personal y sus datos de acceso.
// Aquí el rol de la sesión viaja como $rol_sistema, para no confundirlo con el campo «rol» del empleado.
//
// Conexión a la base de datos (deja lista la variable $conn).
include("conexion.php");
 
// Variables de estado: $mensaje (aviso de éxito o error), $registro_editar (registro en edición, null si no hay) y $busqueda (texto buscado, si aplica).
$mensaje = "";
$registro_editar = null;
$busqueda = "";
// Hace que los errores de MySQL se lancen como excepciones para poder capturarlos con try/catch.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
 
// --- SEGURIDAD: sesión, rol y token CSRF ---
// seguridad.php inicia la sesión y define las funciones de protección.
include_once("seguridad.php");
// Esta sección es exclusiva del administrador: sin sesión va al login; si es Empleado se responde «403 Acceso denegado».
requerir_admin();
// Rol del SISTEMA tomado de la sesión del servidor (no de la URL ni de formularios, que cualquiera podría modificar).
$rol_sistema = rol_actual();
// Todo formulario POST debe traer su token CSRF (ver campo_csrf() en los formularios); si no coincide, se detiene la página.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verificar_csrf();
}
 
// Eliminar
// SEGURIDAD: eliminar ahora se hace por POST con token CSRF (antes era un enlace GET que cualquiera podía disparar).
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['eliminar'], $_POST['id_empleado'])) {
    $id_eliminar = $_POST['id_empleado'];
    try {
        // SEGURIDAD: el administrador no puede eliminar su propia cuenta (se quedaría sin acceso al sistema).
        if ((int)$id_eliminar === (int)$_SESSION["id_empleado"]) {
            throw new mysqli_sql_exception("No puedes eliminar tu propia cuenta mientras tienes la sesión iniciada.");
        }
        // Consulta preparada: el ? se reemplaza de forma segura por el id (evita inyección SQL).
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
    // Busca el registro por su id para cargarlo en el formulario de edición.
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
    // Botón Guardar: toma los datos del formulario y crea un registro nuevo.
    if (isset($_POST["guardar"])) {
        $nombre = $_POST["nombre"];
        $telefono = $_POST["telefono"];
        $email = $_POST["email"];
        $tipo_documento = $_POST["tipo_documento"];
        $documento = $_POST["documento"];
        $username = $_POST["username"];
        $password = $_POST["password"];
        // Rol del empleado que se registra o edita (Administrador/Empleado). No es el rol de quien tiene la sesión: ese es $rol_sistema.
        $rol_empleado = $_POST["rol"];
        $estado = $_POST["estado"];
        try {
            // SEGURIDAD: la contraseña debe tener al menos 8 caracteres y se guarda CIFRADA (hash), nunca en texto plano.
            if (!contrasena_valida($password)) {
                throw new mysqli_sql_exception("La contraseña debe tener al menos 8 caracteres.");
            }
            $password = password_hash($password, PASSWORD_DEFAULT);
            // Inserta el registro nuevo; cada ? se llena con bind_param (s = texto, i = entero, d = decimal).
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
 
    // Botón Actualizar: modifica el registro existente (su id llega en un campo oculto del formulario).
    if (isset($_POST["actualizar"])) {
        $id = $_POST["id"];
        $nombre = $_POST["nombre"];
        $telefono = $_POST["telefono"];
        $email = $_POST["email"];
        $tipo_documento = $_POST["tipo_documento"];
        $documento = $_POST["documento"];
        $username = $_POST["username"];
        $password = $_POST["password"];
        // Rol del empleado que se registra o edita (Administrador/Empleado). No es el rol de quien tiene la sesión: ese es $rol_sistema.
        $rol_empleado = $_POST["rol"];
        $estado = $_POST["estado"];
        try {
            if ($password === "") {
                // SEGURIDAD: si el campo contraseña se deja vacío, se CONSERVA la contraseña actual (ya no se muestra en el formulario).
                $sql_up = "UPDATE empleado SET nombre = ?, telefono = ?, email = ?, tipo_documento = ?, documento = ?,
                           username = ?, rol = ?, estado = ? WHERE id_empleado = ?";
                $stmt_up = $conn->prepare($sql_up);
                $stmt_up->bind_param("ssssssssi", $nombre, $telefono, $email, $tipo_documento, $documento, $username, $rol_empleado, $estado, $id);
            } else {
                // SEGURIDAD: si se escribió una contraseña nueva, debe tener al menos 8 caracteres y se guarda CIFRADA (hash).
                if (!contrasena_valida($password)) {
                    throw new mysqli_sql_exception("La contraseña debe tener al menos 8 caracteres.");
                }
                $password = password_hash($password, PASSWORD_DEFAULT);
                // Actualiza el registro cuyo id coincide (incluida la contraseña); los ? se llenan con bind_param.
                $sql_up = "UPDATE empleado SET nombre = ?, telefono = ?, email = ?, tipo_documento = ?, documento = ?,
                           username = ?, password = ?, rol = ?, estado = ? WHERE id_empleado = ?";
                $stmt_up = $conn->prepare($sql_up);
                $stmt_up->bind_param("sssssssssi", $nombre, $telefono, $email, $tipo_documento, $documento, $username, $password, $rol_empleado, $estado, $id);
            }
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
    // Los % permiten coincidencias parciales con LIKE (el texto puede estar en cualquier parte).
    $param = "%$busqueda%";
    $stmt = $conn->prepare("SELECT * FROM empleado WHERE nombre LIKE ? OR email LIKE ? OR documento LIKE ? OR username LIKE ? ORDER BY id_empleado DESC");
    $stmt->bind_param("ssss", $param, $param, $param, $param);
    $stmt->execute();
    $registros = $stmt->get_result();
} else {
    // Sin búsqueda: trae todos los registros, del más reciente al más antiguo.
    $registros = $conn->query("SELECT * FROM empleado ORDER BY id_empleado DESC");
}
?>
<?php /* Desde aquí empieza el HTML de la página (lo que ve el usuario). */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Empleados</title>
    <?php /* Hojas de estilo: Bootstrap (diseño), Bootstrap Icons (íconos) y general.css (estilos propios del proyecto). */ ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="general.css">
</head>
<body>
<?php /* Estructura de la página: barra lateral a la izquierda y contenido principal a la derecha. */ ?>
<div class="layout">
<?php
// ===== Barra lateral (logo, opciones de navegación y botón de cerrar sesión) =====
// Rol del usuario y página actual; la página actual se resalta en rojo en el menú.
$rol_nav = $rol_sistema;
$pagina_activa = 'empleado';   // inicio | producto | categoria | proveedor | empleado | compra | venta
// Dos versiones del rol: $rn para mostrarlo en HTML de forma segura y $ru para ponerlo en la URL de los enlaces.
$rn = htmlspecialchars($rol_nav);
$ru = urlencode($rol_nav);

// [archivo, icono, texto, solo administrador]
$items_nav = [
    ["producto",  "bi-box-seam", "Productos",   false],
    ["categoria", "bi-grid",     "Categorías",  true],
    ["proveedor", "bi-truck",    "Proveedores", true],
    ["empleado",  "bi-people",   "Empleados",   true],
    ["compra",    "bi-bag-plus", "Compras",     true],
    ["venta",     "bi-cart",     "Ventas",      false],
];
?>
<?php /* Barra lateral oscura con el logo, las opciones de navegación y el botón de cerrar sesión. */ ?>
<aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
    <?php /* Logo y nombre de la marca (si no existe logo.png se muestra la letra B). */ ?>
    <div class="text-center mb-4">
        <!-- Guarda tu logo como logo.png dentro de la carpeta img -->
        <div class="logo-circulo logo-64">
            <img src="img/logo.png" alt="BRANDON URBAN" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
            <span class="logo-letra">B</span>
        </div>
        <div class="brand-nombre text-white fw-bold mt-2">BRANDON URBAN</div>
    </div>

    <?php /* Menú de navegación. */ ?>
    <nav class="nav nav-pills flex-column gap-1">
        <?php /* Botón «Inicio»: vuelve al menú principal enviando el rol por POST. */ ?>
        <form method="POST" action="menu.php" class="m-0">
            <button type="submit" class="nav-link w-100 text-start<?php echo $pagina_activa === 'inicio' ? ' active' : ''; ?>"><i class="bi bi-house-door me-2"></i>Inicio</button>
        </form>
        <?php /* Dibuja un enlace por cada opción de $items_nav; las exclusivas del administrador se omiten para el empleado (continue). */ ?>
        <?php foreach ($items_nav as $it): ?>
            <?php if ($it[3] && $rol_nav !== "Administrador") continue; ?>
            <a class="nav-link<?php echo $pagina_activa === $it[0] ? ' active' : ''; ?>" href="<?php echo $it[0]; ?>.php"><i class="bi <?php echo $it[1]; ?> me-2"></i><?php echo $it[2]; ?></a>
        <?php endforeach; ?>
    </nav>

    <?php /* Botón «Cerrar sesión» (mt-auto lo empuja al fondo de la barra): menu.php lo detecta y redirige al login. */ ?>
    <form method="POST" action="menu.php" class="mt-auto pt-3 m-0">
        <button type="submit" name="btn_cerrar" class="nav-link w-100 text-start"><i class="bi bi-box-arrow-left me-2"></i>Cerrar sesión</button>
    </form>
</aside>
<?php /* Zona de contenido principal. */ ?>
<main class="contenido">
<?php /* Encabezado: título y descripción del módulo, más una insignia con el rol del usuario. */ ?>
<div class="d-flex justify-content-between align-items-start mb-3">
    <div><h2 class="fw-bold mb-0">Gestión de Empleados</h2><small class="text-muted">Altas, bajas y datos del personal</small></div>
    <?php if ($rol_sistema !== ""): ?><span class="badge bg-danger"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($rol_sistema); ?></span><?php endif; ?>
</div>

<?php /* Muestra el aviso de éxito o error de la última acción (guardar, editar o eliminar). */ ?>
<?php echo $mensaje; ?>

<?php if ($registro_editar): ?>
<?php /* Formulario de edición: aparece cuando se pulsó el lápiz de un registro y trae sus datos cargados. */ ?>
<div class="card shadow-sm mb-3">
<div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square text-danger me-1"></i> Editar Empleado N. <?php echo htmlspecialchars($registro_editar['id_empleado']); ?></div>
<div class="card-body">
<form method="POST">
    <?php /* Token CSRF: demuestra que el formulario salió de esta página. */ echo campo_csrf(); ?>
    <input type="hidden" name="id" value="<?php echo $registro_editar['id_empleado']; ?>">
    <?php /* Campos del formulario distribuidos en columnas (cuadrícula de Bootstrap). */ ?>
    <div class="row g-3">
        <div class="col-md-6 col-lg-4"><label class="form-label">Tipo de documento</label><select class="form-select" name="tipo_documento" required><option value="CC" <?php echo ($registro_editar["tipo_documento"]==="CC")?"selected":""; ?>>Cédula de Ciudadanía</option><option value="CE" <?php echo ($registro_editar["tipo_documento"]==="CE")?"selected":""; ?>>Cédula de Extranjería</option><option value="TI" <?php echo ($registro_editar["tipo_documento"]==="TI")?"selected":""; ?>>Tarjeta de Identidad</option><option value="PA" <?php echo ($registro_editar["tipo_documento"]==="PA")?"selected":""; ?>>Pasaporte</option><option value="NIT" <?php echo ($registro_editar["tipo_documento"]==="NIT")?"selected":""; ?>>NIT</option></select></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Documento</label><input class="form-control" type="text" name="documento" value="<?php echo htmlspecialchars($registro_editar["documento"]); ?>" required></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Nombre</label><input class="form-control" type="text" name="nombre" value="<?php echo htmlspecialchars($registro_editar["nombre"]); ?>" required></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Email</label><input class="form-control" type="text" name="email" value="<?php echo htmlspecialchars($registro_editar["email"]); ?>" required></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Teléfono</label><input class="form-control" type="text" name="telefono" value="<?php echo htmlspecialchars($registro_editar["telefono"]); ?>" required></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Usuario</label><input class="form-control" type="text" name="username" value="<?php echo htmlspecialchars($registro_editar["username"]); ?>" required></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Contraseña</label><input class="form-control" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Dejar vacío para conservar la actual"></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Rol</label><select class="form-select" name="rol" required><option value="Administrador" <?php echo ($registro_editar["rol"]==="Administrador")?"selected":""; ?>>Administrador</option><option value="Empleado" <?php echo ($registro_editar["rol"]==="Empleado")?"selected":""; ?>>Empleado</option></select></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Estado</label><select class="form-select" name="estado" required><option value="Activo" <?php echo ($registro_editar["estado"]==="Activo")?"selected":""; ?>>Activo</option><option value="Inactivo" <?php echo ($registro_editar["estado"]==="Inactivo")?"selected":""; ?>>Inactivo</option></select></div>
    </div>
    <div class="mt-3">
        <button type="submit" name="actualizar" class="btn btn-danger"><i class="bi bi-arrow-repeat me-1"></i>Actualizar</button>
        <a href="?" class="btn btn-outline-secondary ms-1">Cancelar</a>
    </div>
</form>
</div>
</div>
<?php else: ?>
<?php /* Formulario de registro nuevo (campos vacíos). */ ?>
<div class="card shadow-sm mb-3">
<div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle text-danger me-1"></i> Registrar Empleado</div>
<div class="card-body">
<form method="POST">
    <?php /* Token CSRF: demuestra que el formulario salió de esta página. */ echo campo_csrf(); ?>
    <?php /* Campos del formulario distribuidos en columnas (cuadrícula de Bootstrap). */ ?>
    <div class="row g-3">
        <div class="col-md-6 col-lg-4"><label class="form-label">Tipo de documento</label><select class="form-select" name="tipo_documento" required><option value="CC" selected>Cédula de Ciudadanía</option><option value="CE">Cédula de Extranjería</option><option value="TI">Tarjeta de Identidad</option><option value="PA">Pasaporte</option><option value="NIT">NIT</option></select></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Documento</label><input class="form-control" type="text" name="documento" required></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Nombre</label><input class="form-control" type="text" name="nombre" required></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Email</label><input class="form-control" type="text" name="email" required></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Teléfono</label><input class="form-control" type="text" name="telefono" required></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Usuario</label><input class="form-control" type="text" name="username" required></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Contraseña</label><input class="form-control" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres" required></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Rol</label><select class="form-select" name="rol" required><option value="Administrador">Administrador</option><option value="Empleado" selected>Empleado</option></select></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Estado</label><select class="form-select" name="estado" required><option value="Activo" selected>Activo</option><option value="Inactivo">Inactivo</option></select></div>
    </div>
    <div class="mt-3">
        <button type="submit" name="guardar" class="btn btn-danger"><i class="bi bi-save me-1"></i>Guardar</button>
    </div>
</form>
</div>
</div>
<?php endif; ?>

<?php /* Botón «Menú Principal»: vuelve al menú enviando el rol por POST. */ ?>
<form method="POST" action="menu.php" class="mb-3">
    <button type="submit" class="btn btn-dark"><i class="bi bi-arrow-left me-1"></i>Menú Principal</button>
</form>

<div class="card shadow-sm mb-3">
<div class="card-body">
<?php /* Formulario de búsqueda: envía el texto por GET y la página filtra el listado. */ ?>
<form method="GET" class="d-flex gap-2 align-items-center">
    <input type="text" class="form-control form-control-sm" name="buscar" placeholder="Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
    <button type="submit" class="btn btn-danger btn-sm text-nowrap"><i class="bi bi-search me-1"></i>Buscar</button>
    <?php if (!empty($busqueda)): ?><a href="?" class="btn btn-outline-secondary btn-sm text-nowrap">Mostrar todos</a><?php endif; ?>
</form>
</div>
</div>

<?php /* Tarjeta con la tabla del listado de registros. */ ?>
<div class="card shadow-sm">
<div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul text-danger me-1"></i> Listado</div>
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<?php /* Encabezados de las columnas de la tabla. */ ?>
<thead class="table-light"><tr><th>ID</th><th>Tipo Doc.</th><th>Documento</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Usuario</th><th>Rol</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
<tbody>
<?php /* Si hay registros, el while recorre cada uno y dibuja una fila; si no hay, se muestra «No se encontraron registros». */ ?>
<?php if ($registros && $registros->num_rows > 0): ?>
<?php while ($f = $registros->fetch_assoc()): ?>
<tr>
<td><span class="badge bg-secondary"><?php echo htmlspecialchars($f["id_empleado"]); ?></span></td>
<td><?php echo htmlspecialchars($f["tipo_documento"]); ?></td>
<td><?php echo htmlspecialchars($f["documento"]); ?></td>
<td class="fw-bold"><?php echo htmlspecialchars($f["nombre"]); ?></td>
<td class="text-muted"><?php echo htmlspecialchars($f["email"]); ?></td>
<td><?php echo htmlspecialchars($f["telefono"]); ?></td>
<td><?php echo htmlspecialchars($f["username"]); ?></td>
<td><span class="badge <?php echo $f["rol"] === "Administrador" ? "bg-dark" : "bg-secondary"; ?>"><?php echo htmlspecialchars($f["rol"]); ?></span></td>
<td><span class="badge rounded-pill <?php echo $f["estado"] === "Activo" ? "bg-success-subtle text-success-emphasis" : "bg-danger-subtle text-danger-emphasis"; ?>"><?php echo htmlspecialchars($f["estado"]); ?></span></td>
<?php /* Botones de acción: editar (lápiz) y eliminar (papelera, pide confirmación antes de borrar). */ ?>
<td class="text-end text-nowrap">
    <a class="btn btn-warning btn-sm" title="Editar" href="?accion=editar&id_empleado=<?php echo $f['id_empleado']; ?>"><i class="bi bi-pencil"></i></a>
    <?php /* Eliminar: formulario POST con token CSRF y confirmación (no es un enlace, así no se puede disparar desde otra web). */ ?>
    <form method="POST" class="d-inline m-0" onsubmit="return confirm('¿Eliminar?');">
        <?php echo campo_csrf(); ?>
        <input type="hidden" name="id_empleado" value="<?php echo (int)$f['id_empleado']; ?>">
        <button type="submit" name="eliminar" value="1" class="btn btn-danger btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
    </form>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="10" class="text-center text-muted py-4">No se encontraron registros.</td></tr>
<?php endif; ?>
</tbody></table>
</div>
</div>

<?php /* Pie de página: datos del negocio, contacto, soporte técnico y aviso de datos personales (los datos están en conexion.php; el diseño, en general.css). */ ?>
<footer class="pie-pagina">
    <div class="pie-caja">
        <div class="pie-columnas">
            <?php /* Columna 1: marca y datos de identificación del responsable. */ ?>
            <div>
                <div class="pie-marca">BRANDON URBAN</div>
                <p class="pie-texto"><?php echo htmlspecialchars($pie_razon_social); ?> &middot; NIT <?php echo htmlspecialchars($pie_nit); ?></p>
                <p class="pie-texto"><i class="bi bi-geo-alt"></i><?php echo htmlspecialchars($pie_direccion); ?></p>
            </div>
            <?php /* Columna 2: contacto y soporte técnico. */ ?>
            <div>
                <div class="pie-titulo">Contacto y soporte</div>
                <p class="pie-texto"><i class="bi bi-envelope"></i><a href="mailto:<?php echo htmlspecialchars($pie_correo); ?>"><?php echo htmlspecialchars($pie_correo); ?></a> &middot; <i class="bi bi-telephone"></i><?php echo htmlspecialchars($pie_telefono); ?></p>
                <p class="pie-texto"><i class="bi bi-headset"></i>Soporte técnico: <a href="mailto:<?php echo htmlspecialchars($pie_soporte); ?>"><?php echo htmlspecialchars($pie_soporte); ?></a></p>
                <p class="pie-texto"><i class="bi bi-telephone"></i>Número de soporte técnico: <?php echo htmlspecialchars($pie_soporte_tel); ?></p>
            </div>
        </div>
        <?php /* Aviso de tratamiento de datos (Ley 1581 de 2012) y de cookies. */ ?>
        <p class="pie-aviso"><i class="bi bi-shield-lock"></i>Los datos personales de este sistema se tratan conforme a la Ley 1581 de 2012. Para consultas, actualización o supresión de tus datos escribe a <?php echo htmlspecialchars($pie_correo); ?>. El sistema solo usa una cookie de sesión necesaria para mantener tu ingreso.</p>
        <?php /* Derecho de autor: el año se actualiza solo con date("Y"). */ ?>
        <div class="pie-copy">&copy; <?php echo date("Y"); ?> BRANDON URBAN &middot; Todos los derechos reservados</div>
    </div>
</footer>
</main>
</div>
</body>
</html>
<?php /* Cierra la conexión con la base de datos al terminar la página. */ ?>
<?php $conn->close(); ?>
