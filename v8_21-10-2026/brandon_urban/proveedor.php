<?php
// proveedor.php — Módulo de PROVEEDORES: registrar, buscar, editar y eliminar a quienes surten la mercancía.
// Es una sola página: arriba PHP procesa la acción pedida y abajo se dibuja el HTML.
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
// Si no hay sesión iniciada (o caducó por inactividad), manda al login y detiene la página.
requerir_login();
// Esta sección es exclusiva del administrador: si el usuario es Empleado se responde «403 Acceso denegado».
requerir_admin();   // solo el administrador puede ver esta sección
// El rol sale de la sesión del servidor, ya NO de la URL ni de formularios (cualquiera podría modificarlos).
$rol = rol_actual();
// Todo formulario POST debe traer su token CSRF (ver campo_csrf() en los formularios); si no coincide, se detiene la página.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verificar_csrf();
}

// Eliminar
// SEGURIDAD: eliminar ahora se hace por POST con token CSRF (antes era un enlace GET que cualquiera podía disparar).
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['eliminar'], $_POST['id_proveedor'])) {
    $id_eliminar = $_POST['id_proveedor'];
    try {
        // Consulta preparada: el ? se reemplaza de forma segura por el id (evita inyección SQL).
        $sql_del = "DELETE FROM proveedor WHERE id_proveedor = ?";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bind_param("i", $id_eliminar);
        $stmt_del->execute();
        $mensaje = "<div class='alert alert-success'>Proveedor eliminado correctamente.</div>";
        $stmt_del->close();
    } catch (mysqli_sql_exception $e) {
        $mensaje = "<div class='alert alert-danger'>Error al eliminar: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Editar
if (isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['id_proveedor'])) {
    $id_buscar = $_GET['id_proveedor'];
    // Busca el registro por su id para cargarlo en el formulario de edición.
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
    // Botón Guardar: toma los datos del formulario y crea un registro nuevo.
    if (isset($_POST["guardar"])) {
        $nit = $_POST["nit"];
        $nombre = $_POST["nombre"];
        $telefono = $_POST["telefono"];
        $direccion = $_POST["direccion"];
        try {
            // Inserta el registro nuevo; cada ? se llena con bind_param (s = texto, i = entero, d = decimal).
            $sql = "INSERT INTO proveedor (nit, nombre, telefono, direccion) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $nit, $nombre, $telefono, $direccion);
            $stmt->execute();
            $mensaje = "<div class='alert alert-success'>Proveedor registrado correctamente.</div>";
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $mensaje = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // Botón Actualizar: modifica el registro existente (su id llega en un campo oculto del formulario).
    if (isset($_POST["actualizar"])) {
        $id = $_POST["id"];
        $nit = $_POST["nit"];
        $nombre = $_POST["nombre"];
        $telefono = $_POST["telefono"];
        $direccion = $_POST["direccion"];
        try {
            // Actualiza el registro cuyo id coincide; los ? se llenan con bind_param.
            $sql_up = "UPDATE proveedor SET nit = ?, nombre = ?, telefono = ?, direccion = ? WHERE id_proveedor = ?";
            $stmt_up = $conn->prepare($sql_up);
            $stmt_up->bind_param("ssssi", $nit, $nombre, $telefono, $direccion, $id);
            $stmt_up->execute();
            $mensaje = "<div class='alert alert-success'>Proveedor actualizado correctamente.</div>";
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
    $stmt = $conn->prepare("SELECT * FROM proveedor WHERE nit LIKE ? OR nombre LIKE ? ORDER BY id_proveedor DESC");
    $stmt->bind_param("ss", $param, $param);
    $stmt->execute();
    $registros = $stmt->get_result();
} else {
    // Sin búsqueda: trae todos los registros, del más reciente al más antiguo.
    $registros = $conn->query("SELECT * FROM proveedor ORDER BY id_proveedor DESC");
}
?>
<?php /* Desde aquí empieza el HTML de la página (lo que ve el usuario). */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Proveedores</title>
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
$rol_nav = $rol;
$pagina_activa = 'proveedor';   // inicio | producto | categoria | proveedor | empleado | compra | venta
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
    ["reporte",   "bi-file-earmark-bar-graph", "Reportes",  true],
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
    <div><h2 class="fw-bold mb-0">Gestión de Proveedores</h2><small class="text-muted">Datos de quienes te surten de mercancía</small></div>
    <?php if ($rol !== ""): ?><span class="badge bg-danger"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($rol); ?></span><?php endif; ?>
</div>

<?php /* Muestra el aviso de éxito o error de la última acción (guardar, editar o eliminar). */ ?>
<?php echo $mensaje; ?>

<?php if ($registro_editar): ?>
<?php /* Formulario de edición: aparece cuando se pulsó el lápiz de un registro y trae sus datos cargados. */ ?>
<div class="card shadow-sm mb-3">
<div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square text-danger me-1"></i> Editar Proveedor N. <?php echo htmlspecialchars($registro_editar['id_proveedor']); ?></div>
<div class="card-body">
<form method="POST">
    <?php /* Token CSRF: demuestra que el formulario salió de esta página. */ echo campo_csrf(); ?>
    <input type="hidden" name="id" value="<?php echo $registro_editar['id_proveedor']; ?>">
    <?php /* Campos del formulario distribuidos en columnas (cuadrícula de Bootstrap). */ ?>
    <div class="row g-3">
        <div class="col-md-6 col-lg-3"><label class="form-label">NIT</label><input class="form-control" type="text" name="nit" value="<?php echo htmlspecialchars($registro_editar["nit"]); ?>" required></div>
        <div class="col-md-6 col-lg-3"><label class="form-label">Nombre</label><input class="form-control" type="text" name="nombre" value="<?php echo htmlspecialchars($registro_editar["nombre"]); ?>" required></div>
        <div class="col-md-6 col-lg-3"><label class="form-label">Teléfono</label><input class="form-control" type="text" name="telefono" value="<?php echo htmlspecialchars($registro_editar["telefono"]); ?>" required></div>
        <div class="col-md-6 col-lg-3"><label class="form-label">Dirección</label><input class="form-control" type="text" name="direccion" value="<?php echo htmlspecialchars($registro_editar["direccion"]); ?>" required></div>
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
<div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle text-danger me-1"></i> Registrar Proveedor</div>
<div class="card-body">
<form method="POST">
    <?php /* Token CSRF: demuestra que el formulario salió de esta página. */ echo campo_csrf(); ?>
    <?php /* Campos del formulario distribuidos en columnas (cuadrícula de Bootstrap). */ ?>
    <div class="row g-3">
        <div class="col-md-6 col-lg-3"><label class="form-label">NIT</label><input class="form-control" type="text" name="nit" required></div>
        <div class="col-md-6 col-lg-3"><label class="form-label">Nombre</label><input class="form-control" type="text" name="nombre" required></div>
        <div class="col-md-6 col-lg-3"><label class="form-label">Teléfono</label><input class="form-control" type="text" name="telefono" required></div>
        <div class="col-md-6 col-lg-3"><label class="form-label">Dirección</label><input class="form-control" type="text" name="direccion" required></div>
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
<thead class="table-light"><tr><th>ID</th><th>NIT</th><th>Nombre</th><th>Teléfono</th><th>Dirección</th><th class="text-end">Acciones</th></tr></thead>
<tbody>
<?php /* Si hay registros, el while recorre cada uno y dibuja una fila; si no hay, se muestra «No se encontraron registros». */ ?>
<?php if ($registros && $registros->num_rows > 0): ?>
<?php while ($f = $registros->fetch_assoc()): ?>
<tr>
<td><span class="badge bg-secondary"><?php echo htmlspecialchars($f["id_proveedor"]); ?></span></td>
<td><?php echo htmlspecialchars($f["nit"]); ?></td>
<td class="fw-bold"><?php echo htmlspecialchars($f["nombre"]); ?></td>
<td><?php echo htmlspecialchars($f["telefono"]); ?></td>
<td class="text-muted"><?php echo htmlspecialchars($f["direccion"]); ?></td>
<?php /* Botones de acción: editar (lápiz) y eliminar (papelera, pide confirmación antes de borrar). */ ?>
<td class="text-end text-nowrap">
    <a class="btn btn-warning btn-sm" title="Editar" href="?accion=editar&id_proveedor=<?php echo $f['id_proveedor']; ?>"><i class="bi bi-pencil"></i></a>
    <?php /* Eliminar: formulario POST con token CSRF y confirmación (no es un enlace, así no se puede disparar desde otra web). */ ?>
    <form method="POST" class="d-inline m-0" onsubmit="return confirm('¿Eliminar?');">
        <?php echo campo_csrf(); ?>
        <input type="hidden" name="id_proveedor" value="<?php echo (int)$f['id_proveedor']; ?>">
        <button type="submit" name="eliminar" value="1" class="btn btn-danger btn-sm" title="Eliminar"><i class="bi bi-trash"></i></button>
    </form>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="6" class="text-center text-muted py-4">No se encontraron registros.</td></tr>
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
                <p class="pie-texto">NIT <?php echo htmlspecialchars($pie_nit); ?></p>
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
