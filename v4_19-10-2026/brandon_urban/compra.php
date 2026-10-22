<?php
// compra.php — Módulo de COMPRAS (solo administrador): registra la entrada de mercancía y suma el stock.
// Al editar o eliminar una compra, el inventario se ajusta de forma automática.
//
// Conexión a la base de datos (deja lista la variable $conn).
include("conexion.php");
 
// Control de inventario compartido por venta.php y compra.php.
//
// ajustar_stock($conn, $id_producto, $cambio)
//   $cambio > 0 : entra mercancía (compra, o se anula una venta)
//   $cambio < 0 : sale mercancía  (venta, o se anula una compra)
// Bloquea la fila del producto mientras se hace el cambio y lanza una
// excepción si el stock llegara a quedar en negativo.
// Debe llamarse dentro de una transacción ($conn->begin_transaction()).
function ajustar_stock($conn, $id_producto, $cambio) {
    // 1) Lee el producto y bloquea su fila (FOR UPDATE) para que nadie más cambie el stock al mismo tiempo.
    $stmt = $conn->prepare("SELECT nombre, stock FROM producto WHERE id_producto = ? FOR UPDATE");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();
 
    // 2) Valida que el producto exista.
    if (!$p) {
        throw new Exception("El producto seleccionado no existe.");
    }
    // 3) Impide que el stock quede en negativo.
    if ((int)$p["stock"] + $cambio < 0) {
        throw new Exception("Stock insuficiente de «" . $p["nombre"] . "»: hay " . (int)$p["stock"]
            . " unidad(es) y se necesitan " . abs($cambio) . ".");
    }
 
    // 4) Aplica el cambio (suma o resta) al stock del producto.
    $stmt = $conn->prepare("UPDATE producto SET stock = stock + ? WHERE id_producto = ?");
    $stmt->bind_param("ii", $cambio, $id_producto);
    $stmt->execute();
    $stmt->close();
}
 
// Variables de estado: $mensaje (aviso de éxito o error), $registro_editar (registro en edición, null si no hay) y $busqueda (texto buscado, si aplica).
$mensaje = "";
$registro_editar = null;
// Hace que los errores de MySQL se lancen como excepciones para poder capturarlos con try/catch.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
 
// Leer el rol (llega por POST desde el menú o por GET desde los enlaces)
$rol = "";
if (isset($_POST["rol"])) {
    $rol = $_POST["rol"];
}
if (isset($_GET["rol"])) {
    $rol = $_GET["rol"];
}
// El administrador puede editar y eliminar; el empleado solo consulta/registra según el módulo.
$esAdmin = ($rol === "Administrador");
 
// El empleado de la compra es siempre el administrador
$admin = $conn->query("SELECT id_empleado, nombre FROM empleado WHERE rol='Administrador' ORDER BY id_empleado ASC LIMIT 1")->fetch_assoc();
 
// Eliminar (retira del inventario lo que había entrado con la compra)
if ($esAdmin && isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id_compra'])) {
    $id_eliminar = (int)$_GET['id_compra'];
    try {
        // Inicia una transacción: los cambios en el inventario y en la tabla se confirman juntos o no se aplica ninguno.
        $conn->begin_transaction();
        // Lee qué producto y cuántas unidades tenía el registro (FOR UPDATE bloquea la fila mientras se modifica).
        $stmt_old = $conn->prepare("SELECT id_producto, cantidad FROM compra WHERE id_compra = ? FOR UPDATE");
        $stmt_old->bind_param("i", $id_eliminar);
        $stmt_old->execute();
        $old = $stmt_old->get_result()->fetch_assoc();
        $stmt_old->close();
        // Si el registro ya no existe, se cancela la operación.
        if (!$old) {
            throw new Exception("La compra no existe.");
        }
        // Retira del inventario las unidades que habían entrado con la compra eliminada.
        ajustar_stock($conn, $old["id_producto"], -(int)$old["cantidad"]);
        // Consulta preparada: el ? se reemplaza de forma segura por el id (evita inyección SQL).
        $sql_del = "DELETE FROM compra WHERE id_compra = ?";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bind_param("i", $id_eliminar);
        $stmt_del->execute();
        $stmt_del->close();
        // Confirma la transacción: ahora sí se guardan todos los cambios.
        $conn->commit();
        $mensaje = "<div class='alert alert-success'>Compra eliminada correctamente. El stock fue descontado del inventario.</div>";
    } catch (Throwable $e) {
    // Si algo falla (por ejemplo, stock insuficiente) se deshace todo con rollback y se muestra el error.
        $conn->rollback();
        $mensaje = "<div class='alert alert-danger'>Error al eliminar: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
 
// Editar
if ($esAdmin && isset($_GET['accion']) && $_GET['accion'] === 'editar' && isset($_GET['id_compra'])) {
    $id_buscar = $_GET['id_compra'];
    // Busca el registro por su id para cargarlo en el formulario de edición.
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
    // Botón Guardar: toma los datos del formulario y crea un registro nuevo.
    if (isset($_POST["guardar"])) {
        // Datos de la operación: la fecha es la de hoy; subtotal y total se calculan como cantidad × precio unitario.
        $fecha = date('Y-m-d');
        $id_proveedor = $_POST["id_proveedor"];
        // El empleado de la compra es siempre el administrador encontrado arriba.
        $id_empleado = $admin["id_empleado"];
        $id_producto = (int)$_POST["id_producto"];
        $cantidad = (int)$_POST["cantidad"];
        $precio_unitario = $_POST["precio_unitario"];
        $subtotal = $cantidad * $precio_unitario;
        $total = $subtotal;
        try {
            // Validación: la cantidad debe ser al menos 1.
            if ($cantidad < 1) {
                throw new Exception("La cantidad debe ser al menos 1.");
            }
            // Inicia una transacción: los cambios en el inventario y en la tabla se confirman juntos o no se aplica ninguno.
            $conn->begin_transaction();
            // Suma al inventario
            ajustar_stock($conn, $id_producto, $cantidad);
            // Inserta el registro nuevo; cada ? se llena con bind_param (s = texto, i = entero, d = decimal).
            $sql = "INSERT INTO compra (fecha, id_proveedor, id_empleado, id_producto, cantidad, precio_unitario, subtotal, total)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siiiiddd", $fecha, $id_proveedor, $id_empleado, $id_producto, $cantidad, $precio_unitario, $subtotal, $total);
            $stmt->execute();
            $stmt->close();
            // Confirma la transacción: ahora sí se guardan todos los cambios.
            $conn->commit();
            $mensaje = "<div class='alert alert-success'>Compra registrada correctamente.</div>";
        } catch (Throwable $e) {
        // Si algo falla (por ejemplo, stock insuficiente) se deshace todo con rollback y se muestra el error.
            $conn->rollback();
            $mensaje = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
 
    // Botón Actualizar: modifica el registro existente (su id llega en un campo oculto del formulario).
    if (isset($_POST["actualizar"])) {
        $id = (int)$_POST["id"];
        $id_proveedor = $_POST["id_proveedor"];
        // El empleado de la compra es siempre el administrador encontrado arriba.
        $id_empleado = $admin["id_empleado"];
        $id_producto = (int)$_POST["id_producto"];
        $cantidad = (int)$_POST["cantidad"];
        $precio_unitario = $_POST["precio_unitario"];
        $subtotal = $cantidad * $precio_unitario;
        $total = $subtotal;
        try {
            // Validación: la cantidad debe ser al menos 1.
            if ($cantidad < 1) {
                throw new Exception("La cantidad debe ser al menos 1.");
            }
            // Inicia una transacción: los cambios en el inventario y en la tabla se confirman juntos o no se aplica ninguno.
            $conn->begin_transaction();
            // Lee qué producto y cuántas unidades tenía el registro (FOR UPDATE bloquea la fila mientras se modifica).
            $stmt_old = $conn->prepare("SELECT id_producto, cantidad FROM compra WHERE id_compra = ? FOR UPDATE");
            $stmt_old->bind_param("i", $id);
            $stmt_old->execute();
            $old = $stmt_old->get_result()->fetch_assoc();
            $stmt_old->close();
            // Si el registro ya no existe, se cancela la operación.
            if (!$old) {
                throw new Exception("La compra no existe.");
            }
            // Quita lo que había entrado antes y suma lo nuevo
            ajustar_stock($conn, $old["id_producto"], -(int)$old["cantidad"]);
            ajustar_stock($conn, $id_producto, $cantidad);
            // Actualiza el registro cuyo id coincide; los ? se llenan con bind_param.
            $sql_up = "UPDATE compra SET id_proveedor=?, id_empleado=?, id_producto=?, cantidad=?, precio_unitario=?, subtotal=?, total=? WHERE id_compra=?";
            $stmt_up = $conn->prepare($sql_up);
            $stmt_up->bind_param("iiiidddi", $id_proveedor, $id_empleado, $id_producto, $cantidad, $precio_unitario, $subtotal, $total, $id);
            $stmt_up->execute();
            $stmt_up->close();
            // Confirma la transacción: ahora sí se guardan todos los cambios.
            $conn->commit();
            $mensaje = "<div class='alert alert-success'>Compra actualizada correctamente.</div>";
        } catch (Throwable $e) {
        // Si algo falla (por ejemplo, stock insuficiente) se deshace todo con rollback y se muestra el error.
            $conn->rollback();
            $mensaje = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
 
// Listar compras
// Trae las compras con el nombre del proveedor, del empleado y del producto (LEFT JOIN), de la más reciente a la más antigua.
$busqueda = "";
if (isset($_GET['buscar']) && !empty(trim($_GET['buscar']))) {
    $busqueda = trim($_GET['buscar']);
    // Los % permiten coincidencias parciales con LIKE (el texto puede estar en cualquier parte).
    $param = "%$busqueda%";
    $stmt = $conn->prepare("SELECT c.*, p.nombre AS proveedor_nombre, e.nombre AS empleado_nombre, pr.nombre AS producto_nombre
                            FROM compra c
                            LEFT JOIN proveedor p ON c.id_proveedor = p.id_proveedor
                            LEFT JOIN empleado e  ON c.id_empleado  = e.id_empleado
                            LEFT JOIN producto pr ON c.id_producto  = pr.id_producto
                            WHERE c.fecha LIKE ? OR p.nombre LIKE ? OR e.nombre LIKE ? OR pr.nombre LIKE ?
                            ORDER BY c.id_compra DESC");
    $stmt->bind_param("ssss", $param, $param, $param, $param);
    $stmt->execute();
    $registros = $stmt->get_result();
} else {
    // Sin búsqueda: trae todas las compras, de la más reciente a la más antigua.
    $registros = $conn->query("SELECT c.*, p.nombre AS proveedor_nombre, e.nombre AS empleado_nombre, pr.nombre AS producto_nombre
                               FROM compra c
                               LEFT JOIN proveedor p ON c.id_proveedor = p.id_proveedor
                               LEFT JOIN empleado e  ON c.id_empleado  = e.id_empleado
                               LEFT JOIN producto pr ON c.id_producto  = pr.id_producto
                               ORDER BY c.id_compra DESC");
}
 
// Funciones simples para selects
// Devuelve las <option> de la lista desplegable de proveedores (marca la seleccionada al editar).
function selectProveedores($conn, $seleccionado = null) {
    $res = $conn->query("SELECT id_proveedor, nombre FROM proveedor ORDER BY id_proveedor ASC");
    $options = '<option value="">Seleccione proveedor</option>';
    while ($f = $res->fetch_assoc()) {
        $sel = ($seleccionado == $f['id_proveedor']) ? 'selected' : '';
        $options .= '<option value="' . $f['id_proveedor'] . '" ' . $sel . '>' . $f['id_proveedor'] . ' - ' . htmlspecialchars($f['nombre']) . '</option>';
    }
    return $options;
}
 
// Devuelve las <option> de la lista desplegable de productos, mostrando el stock disponible de cada uno.
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
<?php /* Desde aquí empieza el HTML de la página (lo que ve el usuario). */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compras</title>
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
$pagina_activa = 'compra';   // inicio | producto | categoria | proveedor | empleado | compra | venta
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
            <input type="hidden" name="rol" value="<?php echo $rn; ?>">
            <button type="submit" class="nav-link w-100 text-start<?php echo $pagina_activa === 'inicio' ? ' active' : ''; ?>"><i class="bi bi-house-door me-2"></i>Inicio</button>
        </form>
        <?php /* Dibuja un enlace por cada opción de $items_nav; las exclusivas del administrador se omiten para el empleado (continue). */ ?>
        <?php foreach ($items_nav as $it): ?>
            <?php if ($it[3] && $rol_nav !== "Administrador") continue; ?>
            <a class="nav-link<?php echo $pagina_activa === $it[0] ? ' active' : ''; ?>" href="<?php echo $it[0]; ?>.php?rol=<?php echo $ru; ?>"><i class="bi <?php echo $it[1]; ?> me-2"></i><?php echo $it[2]; ?></a>
        <?php endforeach; ?>
    </nav>
 
    <?php /* Botón «Cerrar sesión» (mt-auto lo empuja al fondo de la barra): menu.php lo detecta y redirige al login. */ ?>
    <form method="POST" action="menu.php" class="mt-auto pt-3 m-0">
        <input type="hidden" name="rol" value="<?php echo $rn; ?>">
        <button type="submit" name="btn_cerrar" class="nav-link w-100 text-start"><i class="bi bi-box-arrow-left me-2"></i>Cerrar sesión</button>
    </form>
</aside>
<?php /* Zona de contenido principal. */ ?>
<main class="contenido">
<?php /* Encabezado: título y descripción del módulo, más una insignia con el rol del usuario. */ ?>
<div class="d-flex justify-content-between align-items-start mb-3">
    <div><h2 class="fw-bold mb-0">Registro de Compras</h2><small class="text-muted">Entrada de mercancía al inventario</small></div>
    <?php if ($rol !== ""): ?><span class="badge bg-danger"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($rol); ?></span><?php endif; ?>
</div>
 
<?php /* Muestra el aviso de éxito o error de la última acción (guardar, editar o eliminar). */ ?>
<?php echo $mensaje; ?>
 
<?php if (!$esAdmin): ?>
<?php /* Quien no es administrador solo ve este aviso; el administrador ve el formulario de edición o el de registro. */ ?>
<div class="alert alert-danger">Solo el administrador puede registrar compras.</div>
<?php elseif ($registro_editar): ?>
<?php /* Formulario de edición: aparece cuando se pulsó el lápiz de un registro y trae sus datos cargados. */ ?>
<div class="card shadow-sm mb-3">
<div class="card-header bg-white fw-semibold"><i class="bi bi-pencil-square text-danger me-1"></i> Editar Compra N. <?php echo htmlspecialchars($registro_editar['id_compra']); ?></div>
<div class="card-body">
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <input type="hidden" name="id" value="<?php echo $registro_editar['id_compra']; ?>">
    <?php /* Campos del formulario distribuidos en columnas (cuadrícula de Bootstrap). */ ?>
    <div class="row g-3">
        <div class="col-md-6 col-lg-4"><label class="form-label">Fecha</label><input class="form-control bg-light" type="text" value="<?php echo htmlspecialchars($registro_editar["fecha"]); ?>" readonly></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Proveedor</label><select class="form-select" name="id_proveedor" required><?php echo selectProveedores($conn, $registro_editar["id_proveedor"]); ?></select></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Empleado</label><input class="form-control bg-light" type="text" value="<?php echo htmlspecialchars($admin['id_empleado'] . ' - ' . $admin['nombre']); ?>" readonly></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Producto</label><select class="form-select" name="id_producto" required><?php echo selectProductos($conn, $registro_editar["id_producto"]); ?></select></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Cantidad</label><input class="form-control" type="number" name="cantidad" value="<?php echo htmlspecialchars($registro_editar["cantidad"]); ?>" required></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Precio unitario</label><input class="form-control" type="number" name="precio_unitario" value="<?php echo htmlspecialchars($registro_editar["precio_unitario"]); ?>" step="0.01" required></div>
    </div>
    <div class="mt-3">
        <button type="submit" name="actualizar" class="btn btn-danger"><i class="bi bi-arrow-repeat me-1"></i>Actualizar</button>
        <a href="?rol=<?php echo htmlspecialchars($rol); ?>" class="btn btn-outline-secondary ms-1">Cancelar</a>
    </div>
</form>
</div>
</div>
<?php else: ?>
<?php /* Formulario de registro nuevo (campos vacíos). */ ?>
<div class="card shadow-sm mb-3">
<div class="card-header bg-white fw-semibold"><i class="bi bi-plus-circle text-danger me-1"></i> Registrar Compra</div>
<div class="card-body">
<form method="POST">
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <?php /* Campos del formulario distribuidos en columnas (cuadrícula de Bootstrap). */ ?>
    <div class="row g-3">
        <div class="col-md-6 col-lg-4"><label class="form-label">Fecha</label><input class="form-control bg-light" type="text" value="<?php echo date('Y-m-d'); ?>" readonly></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Proveedor</label><select class="form-select" name="id_proveedor" required><?php echo selectProveedores($conn); ?></select></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Empleado</label><input class="form-control bg-light" type="text" value="<?php echo htmlspecialchars($admin['id_empleado'] . ' - ' . $admin['nombre']); ?>" readonly></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Producto</label><select class="form-select" name="id_producto" required><?php echo selectProductos($conn); ?></select></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Cantidad</label><input class="form-control" type="number" name="cantidad" required></div>
        <div class="col-md-6 col-lg-4"><label class="form-label">Precio unitario</label><input class="form-control" type="number" name="precio_unitario" step="0.01" required></div>
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
    <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
    <button type="submit" class="btn btn-dark"><i class="bi bi-arrow-left me-1"></i>Menú Principal</button>
</form>
 
<?php /* Buscador: envía el texto por GET y la página filtra el listado. */ ?>
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
 
<?php /* Tarjeta con la tabla del listado de registros. */ ?>
<div class="card shadow-sm">
<div class="card-header bg-white fw-semibold"><i class="bi bi-list-ul text-danger me-1"></i> Listado de Compras</div>
<div class="table-responsive">
<table class="table table-hover align-middle mb-0">
<?php /* Encabezados de las columnas de la tabla. */ ?>
<thead class="table-light"><tr><th>ID</th><th>Fecha</th><th>Proveedor</th><th>Empleado</th><th>Producto</th><th>Cantidad</th><th>P. Unit.</th><th>Subtotal</th><th>Total</th><th class="text-end">Acciones</th></tr></thead>
<tbody>
<?php /* Si hay registros, el while recorre cada uno y dibuja una fila; si no hay, se muestra «No se encontraron registros». */ ?>
<?php if ($registros && $registros->num_rows > 0): ?>
<?php while ($f = $registros->fetch_assoc()): ?>
<tr>
<td><span class="badge bg-secondary"><?php echo htmlspecialchars($f["id_compra"]); ?></span></td>
<td><?php echo htmlspecialchars($f["fecha"]); ?></td>
<td class="fw-bold"><?php echo htmlspecialchars($f["proveedor_nombre"] ?? "—"); ?></td>
<td><?php echo htmlspecialchars($f["empleado_nombre"] ?? "—"); ?></td>
<td><?php echo htmlspecialchars($f["producto_nombre"] ?? "—"); ?></td>
<td><?php echo htmlspecialchars($f["cantidad"]); ?></td>
<td><?php echo htmlspecialchars($f["precio_unitario"]); ?></td>
<td><?php echo htmlspecialchars($f["subtotal"]); ?></td>
<td class="fw-bold"><?php echo htmlspecialchars($f["total"]); ?></td>
<?php /* Botones de acción: editar (lápiz) y eliminar (papelera, pide confirmación antes de borrar). */ ?>
<td class="text-end text-nowrap">
<?php /* Estas celdas solo se muestran al administrador (precios y/o botones de acción). */ ?>
<?php if ($esAdmin): ?>
    <a class="btn btn-warning btn-sm" title="Editar" href="?accion=editar&id_compra=<?php echo $f['id_compra']; ?>&rol=<?php echo htmlspecialchars($rol); ?>"><i class="bi bi-pencil"></i></a>
    <a class="btn btn-danger btn-sm" title="Eliminar" href="?accion=eliminar&id_compra=<?php echo $f['id_compra']; ?>&rol=<?php echo htmlspecialchars($rol); ?>" onclick="return confirm('¿Eliminar?');"><i class="bi bi-trash"></i></a>
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
<?php /* Cierra la conexión con la base de datos al terminar la página. */ ?>
<?php $conn->close(); ?>
 