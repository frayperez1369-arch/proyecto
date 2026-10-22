<?php
// menu.php — Menú principal (inicio): resumen del inventario, últimos productos, actividad reciente y gráfica.
// SEGURIDAD: el rol ya NO llega por POST ni por la URL; se toma de la sesión que crea usuario.php al iniciar sesión.
//
// --- Seguridad: exigir sesión iniciada ---
// seguridad.php inicia la sesión y define las funciones de protección; requerir_login() manda al login a quien no haya entrado.
include_once("seguridad.php");

// --- Cerrar sesión ---
// Se revisa ANTES de exigir login para que cerrar sesión siempre funcione: destruye la sesión del servidor y vuelve al login.
if (isset($_POST["btn_cerrar"])) {
    cerrar_sesion();
    header("Location: index.html");
    exit();
}
requerir_login();

// --- Leer el rol ---
// Viene de la sesión del servidor (no se puede falsificar desde el navegador).
$rol = rol_actual();

// --- Navegación ---
// Cada botón envía su nombre por POST; según cuál sea, redirige al módulo correspondiente (el rol ya viaja en la sesión).
if (isset($_POST["btn_prt"])) {
    header("Location: producto.php");
    exit();
} elseif (isset($_POST["btn_ct"])) {
    header("Location: categoria.php");
    exit();
} elseif (isset($_POST["btn_pv"])) {
    header("Location: proveedor.php");
    exit();
} elseif (isset($_POST["btn_emp"])) {
    header("Location: empleado.php");
    exit();
} elseif (isset($_POST["btn_c"])) {
    header("Location: compra.php");
    exit();
} elseif (isset($_POST["btn_vts"])) {
    header("Location: venta.php");
    exit();
}
?>
<?php
// =====================================================
//  DATOS DEL RESUMEN (dashboard)
// =====================================================
// Conexión a la base de datos (variable $conn).
include_once("conexion.php");

$UMBRAL_BAJO = 10;   // stock <= 10 se considera "stock bajo"
// Bandera: solo el administrador ve la actividad reciente.
$esAdminMenu = ($rol == "Administrador");

// Ejecuta una consulta y devuelve un arreglo (si falla, devuelve vacío)
function consultar_menu($conn, $sql) {
    try {
        $r = $conn->query($sql);
    } catch (Throwable $e) {
        return [];
    }
    if (!$r) { return []; }
    $out = [];
    while ($f = $r->fetch_assoc()) { $out[] = $f; }
    return $out;
}

// --- Tarjetas de resumen ---
$res_stats = consultar_menu($conn, "SELECT COUNT(*) AS total,
        SUM(stock > $UMBRAL_BAJO) AS en_stock,
        SUM(stock > 0 AND stock <= $UMBRAL_BAJO) AS bajo,
        SUM(stock <= 0) AS agotados
    FROM producto");
// Toma la primera (y única) fila con los contadores.
$st = $res_stats ? $res_stats[0] : [];
// Convierte cada contador a número entero (0 si no hay datos).
$total_prod = (int)($st["total"] ?? 0);
$en_stock   = (int)($st["en_stock"] ?? 0);
$stock_bajo = (int)($st["bajo"] ?? 0);
$agotados   = (int)($st["agotados"] ?? 0);

// --- Últimos productos ---
$ultimos_prod = consultar_menu($conn, "SELECT p.*, c.nombre AS categoria_nombre
    FROM producto p LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
    ORDER BY p.id_producto DESC LIMIT 5");

// --- Productos por categoría ---
$por_categoria = consultar_menu($conn, "SELECT COALESCE(c.nombre, 'Sin categoría') AS nombre, COUNT(*) AS n
    FROM producto p LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
    GROUP BY c.id_categoria, c.nombre ORDER BY n DESC");
// Colores de cada porción de la gráfica y de su leyenda.
$colores_cat = ["#dc3545", "#1a1a1a", "#6c757d", "#adb5bd", "#ffc107", "#fd7e14", "#198754", "#0d6efd"];

// --- Solo administrador: actividad reciente ---
$actividad = [];
if ($esAdminMenu) {
    $actividad = consultar_menu($conn, "(SELECT 'Venta' AS tipo, v.id_venta AS id, v.fecha, p.nombre AS producto, v.cantidad
            FROM venta v LEFT JOIN producto p ON v.id_producto = p.id_producto)
        UNION ALL
        (SELECT 'Compra' AS tipo, c.id_compra AS id, c.fecha, pr.nombre AS producto, c.cantidad
            FROM compra c LEFT JOIN producto pr ON c.id_producto = pr.id_producto)
        ORDER BY fecha DESC, id DESC LIMIT 5");
}

// Lista de opciones del menú según el rol. Nota: actualmente la navegación se hace desde la barra lateral (más abajo en esta misma página), por lo que esta lista ya no se usa en la página.
// --- Botones de navegación (arriba) ---
if ($rol == "Administrador") {
    $opciones = [
        ["btn_prt", "bi-box-seam", "Gestionar Productos",   "Prendas, precios y existencias", false],
        ["btn_ct",  "bi-grid",     "Gestionar Categorías",  "Organiza el catálogo", false],
        ["btn_pv",  "bi-truck",    "Gestionar Proveedores", "Datos de quienes te surten", false],
        ["btn_emp", "bi-people",   "Gestionar Empleados",   "Altas, bajas y datos del personal", false],
        ["btn_c",   "bi-bag-plus", "Registrar Compra",      "Entrada de mercancía", true],
        ["btn_vts", "bi-cart",     "Registrar Venta",       "Salida de mercancía", true],
    ];
} elseif ($rol == "Empleado") {
    $opciones = [
        ["btn_prt", "bi-box-seam", "Consultar Productos", "Revisa precios y existencias", false],
        ["btn_vts", "bi-cart",     "Registrar Venta",     "Salida de mercancía", true],
    ];
} else {
    $opciones = [];
}

// Rol codificado para poder usarlo dentro de enlaces (URL).
$rol_url = urlencode($rol);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menú Principal - Tienda de Ropa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="general.css">
</head>
<body>

<?php /* Si el rol no es válido, muestra un aviso con botón para volver al login y detiene la página (exit). */ ?>
<?php if ($rol != "Administrador" && $rol != "Empleado"): ?>
<main class="contenido">
    <div class="alert alert-danger">Rol no reconocido.</div>
    <form method="POST" action="menu.php">
        <button type="submit" name="btn_cerrar" class="btn btn-dark"><i class="bi bi-arrow-left me-1"></i>Volver al Login</button>
    </form>
</main>
</body>
</html>
<?php exit(); endif; ?>

<div class="layout">
<?php
// ===== Barra lateral (logo, opciones de navegación y botón de cerrar sesión) =====
// Rol del usuario y página actual; la página actual se resalta en rojo en el menú.
$rol_nav = $rol;
$pagina_activa = 'inicio';   // inicio | producto | categoria | proveedor | empleado | compra | venta
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
<main class="contenido">

    <!-- ============ ENCABEZADO ============ -->
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h2 class="fw-bold mb-0">Bienvenido, <?php echo htmlspecialchars($rol); ?></h2>
            <small class="text-muted">Aquí tienes un resumen de tu inventario.</small>
        </div>
        <span class="badge bg-danger"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($rol); ?></span>
    </div>

    <!-- ============ RESUMEN ============ -->
    <h6 class="menu-seccion">Resumen del inventario</h6>

    <?php /* Cuatro tarjetas de resumen: total de productos, en stock, con stock bajo y agotados. */ ?>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100"><div class="card-body d-flex gap-3 align-items-center">
                <span class="stat-icon rojo"><i class="bi bi-tags"></i></span>
                <div><div class="text-muted small">Total de productos</div><div class="stat-num"><?php echo $total_prod; ?></div></div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100"><div class="card-body d-flex gap-3 align-items-center">
                <span class="stat-icon verde"><i class="bi bi-box-seam"></i></span>
                <div><div class="text-muted small">En stock</div><div class="stat-num"><?php echo $en_stock; ?></div></div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100"><div class="card-body d-flex gap-3 align-items-center">
                <span class="stat-icon naranja"><i class="bi bi-exclamation-triangle"></i></span>
                <div><div class="text-muted small">Stock bajo</div><div class="stat-num"><?php echo $stock_bajo; ?></div></div>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card shadow-sm h-100"><div class="card-body d-flex gap-3 align-items-center">
                <span class="stat-icon rojo"><i class="bi bi-x-circle"></i></span>
                <div><div class="text-muted small">Agotados</div><div class="stat-num"><?php echo $agotados; ?></div></div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <!-- Últimos productos -->
        <div class="col-xl-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <span class="fw-bold fs-5">Productos</span>
                    <a class="btn btn-danger btn-sm" href="producto.php">
                        <?php if ($esAdminMenu): ?><i class="bi bi-plus-lg me-1"></i>Agregar producto<?php else: ?><i class="bi bi-eye me-1"></i>Ver productos<?php endif; ?>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Nombre</th><th>Categoría</th><th>Talla</th><th>Precio</th><th>Stock</th><th>Estado</th></tr>
                        </thead>
                        <tbody>
                        <?php /* Recorre los últimos 5 productos y calcula su estado según el stock (Agotado / Stock bajo / Disponible). */ ?>
                        <?php if ($ultimos_prod): ?>
                            <?php foreach ($ultimos_prod as $p):
                                $s = (int)$p["stock"];
                                if ($s <= 0)                     { $estado = "Agotado";    $cls = "bg-danger-subtle text-danger-emphasis"; }
                                elseif ($s <= $UMBRAL_BAJO)      { $estado = "Stock bajo"; $cls = "bg-warning-subtle text-warning-emphasis"; }
                                else                             { $estado = "Disponible"; $cls = "bg-success-subtle text-success-emphasis"; }
                            ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($p["nombre"]); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars($p["categoria_nombre"] ?? "—"); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars($p["talla"]); ?></td>
                                <td>$ <?php echo number_format((float)$p["precio_venta"], 0, ",", "."); ?></td>
                                <td class="<?php echo $s <= 0 ? 'text-danger fw-bold' : ''; ?>"><?php echo $s; ?></td>
                                <td><span class="badge rounded-pill <?php echo $cls; ?>"><?php echo $estado; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No hay productos registrados.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white small text-muted">
                    Mostrando <?php echo count($ultimos_prod); ?> de <?php echo $total_prod; ?> productos
                </div>
            </div>
        </div>

        <!-- Columna derecha -->
        <div class="col-xl-4 d-flex flex-column gap-3">

            <?php /* Actividad reciente (solo administrador): últimas ventas y compras mezcladas por fecha. */ ?>
            <?php if ($esAdminMenu): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3"><span class="fw-bold fs-6">Actividad reciente</span></div>
                <div class="card-body pt-2">
                    <?php if ($actividad): ?>
                        <?php foreach ($actividad as $a):
                            $esVenta = ($a["tipo"] === "Venta");
                        ?>
                        <div class="d-flex align-items-center gap-3 py-2">
                            <span class="act-icon <?php echo $esVenta ? 'rojo' : 'oscuro'; ?>"><i class="bi <?php echo $esVenta ? 'bi-cart' : 'bi-bag-plus'; ?>"></i></span>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small"><?php echo $esVenta ? "Venta registrada" : "Compra registrada"; ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($a["producto"] ?? "—"); ?> × <?php echo (int)$a["cantidad"]; ?></div>
                            </div>
                            <span class="text-muted small text-nowrap"><?php echo date("d/m/Y", strtotime($a["fecha"])); ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted small py-3">Aún no hay movimientos.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm flex-grow-1">
                <div class="card-header bg-white py-3"><span class="fw-bold fs-6">Productos por categoría</span></div>
                <div class="card-body">
                    <?php /* Gráfica de dona y leyenda con la cantidad de productos por categoría. */ ?>
                    <?php if ($por_categoria): ?>
                    <div class="row align-items-center g-2">
                        <div class="col-6">
                            <div class="chart-box">
                                <canvas id="graficoCategorias"></canvas>
                                <div class="chart-centro"><strong><?php echo $total_prod; ?></strong><span>Total</span></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <?php foreach ($por_categoria as $i => $c): ?>
                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                <span><span class="punto" style="background:<?php echo $colores_cat[$i % count($colores_cat)]; ?>"></span><?php echo htmlspecialchars($c["nombre"]); ?></span>
                                <strong><?php echo (int)$c["n"]; ?></strong>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="text-muted small py-3">Aún no hay productos.</div>
                    <?php endif; ?>
                </div>
            </div>
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

<!-- Librería Chart.js, necesaria para dibujar la gráfica. -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<!-- Script que dibuja la gráfica con los datos calculados en PHP (json_encode los pasa a JavaScript). -->
<script>
(function () {
    // Si la librería no cargó (por ejemplo, sin internet), no intenta dibujar la gráfica.
    if (typeof Chart === "undefined") { return; }

    // Dona: productos por categoría
    var elCat = document.getElementById("graficoCategorias");
    if (elCat) {
        new Chart(elCat, {
            type: "doughnut",
            data: {
                labels: <?php echo json_encode(array_column($por_categoria, "nombre"), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_map("intval", array_column($por_categoria, "n"))); ?>,
                    backgroundColor: <?php echo json_encode(array_slice(array_merge($colores_cat, $colores_cat), 0, max(1, count($por_categoria)))); ?>,
                    borderWidth: 2,
                    borderColor: "#ffffff"
                }]
            },
            options: { cutout: "68%", maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
    }
})();
</script>
</body>
</html>