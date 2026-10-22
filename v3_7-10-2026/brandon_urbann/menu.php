<?php
// --- Leer el rol ---
// Si viene de un módulo, llega por POST. Si viene del login (usuario.php), ya existe.
if (isset($_POST["rol"])) {
    $rol = $_POST["rol"];
}
if (!isset($rol)) {
    $rol = "";
}
 
// --- Cerrar sesión (solo redirige al login) ---
if (isset($_POST["btn_cerrar"])) {
    header("Location: index.html");
    exit();
}
 
// --- Navegación ---
if (isset($_POST["btn_prt"])) {
    header("Location: producto.php?rol=$rol");
    exit();
} elseif (isset($_POST["btn_ct"])) {
    header("Location: categoria.php?rol=$rol");
    exit();
} elseif (isset($_POST["btn_pv"])) {
    header("Location: proveedor.php?rol=$rol");
    exit();
} elseif (isset($_POST["btn_emp"])) {
    header("Location: empleado.php?rol=$rol");
    exit();
} elseif (isset($_POST["btn_c"])) {
    header("Location: compra.php?rol=$rol");
    exit();
} elseif (isset($_POST["btn_vts"])) {
    header("Location: venta.php?rol=$rol");
    exit();
}
?>
<?php
// =====================================================
//  DATOS DEL RESUMEN (dashboard)
// =====================================================
include_once("conexion.php");
 
$UMBRAL_BAJO = 10;   // stock <= 10 se considera "stock bajo"
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
$st = $res_stats ? $res_stats[0] : [];
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
$colores_cat = ["#dc3545", "#1a1a1a", "#6c757d", "#adb5bd", "#ffc107", "#fd7e14", "#198754", "#0d6efd"];
 
// --- Solo administrador: actividad reciente y gráfico ventas vs compras ---
$actividad = [];
$dias = [];
$serie_ventas = [];
$serie_compras = [];
if ($esAdminMenu) {
    $actividad = consultar_menu($conn, "(SELECT 'Venta' AS tipo, v.id_venta AS id, v.fecha, p.nombre AS producto, v.cantidad
            FROM venta v LEFT JOIN producto p ON v.id_producto = p.id_producto)
        UNION ALL
        (SELECT 'Compra' AS tipo, c.id_compra AS id, c.fecha, pr.nombre AS producto, c.cantidad
            FROM compra c LEFT JOIN producto pr ON c.id_producto = pr.id_producto)
        ORDER BY fecha DESC, id DESC LIMIT 5");
 
    $mapa_v = [];
    foreach (consultar_menu($conn, "SELECT DATE(fecha) AS d, SUM(total) AS t FROM venta
            WHERE fecha >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(fecha)") as $f) { $mapa_v[$f["d"]] = (float)$f["t"]; }
    $mapa_c = [];
    foreach (consultar_menu($conn, "SELECT DATE(fecha) AS d, SUM(total) AS t FROM compra
            WHERE fecha >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(fecha)") as $f) { $mapa_c[$f["d"]] = (float)$f["t"]; }
    for ($i = 6; $i >= 0; $i--) {
        $d = date("Y-m-d", strtotime("-$i day"));
        $dias[] = date("d/m", strtotime($d));
        $serie_ventas[]  = $mapa_v[$d] ?? 0;
        $serie_compras[] = $mapa_c[$d] ?? 0;
    }
}
 
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
 
<!-- ============ BARRA SUPERIOR ============ -->
<nav class="menu-nav">
    <div class="container-xl d-flex align-items-center justify-content-between py-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-white d-flex align-items-center justify-content-center overflow-hidden" style="width:44px;height:44px;">
                <img src="logo.png" alt="JFYJ" class="w-100 h-100 object-fit-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <span class="fw-bold text-dark" style="display:none;">J</span>
            </div>
            <span class="brand-jfyj text-white fw-bold fs-5">JFYJ</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if ($rol != ""): ?>
                <span class="badge bg-danger"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($rol); ?></span>
            <?php endif; ?>
            <form method="POST" action="menu.php" class="m-0">
                <button type="submit" name="btn_cerrar" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-left me-1"></i>Cerrar sesión</button>
            </form>
        </div>
    </div>
</nav>
 
<?php if ($rol != "Administrador" && $rol != "Empleado"): ?>
<main class="container-xl py-4">
    <div class="alert alert-danger">Rol no reconocido.</div>
    <form method="POST" action="menu.php">
        <button type="submit" name="btn_cerrar" class="btn btn-dark"><i class="bi bi-arrow-left me-1"></i>Volver al Login</button>
    </form>
</main>
</body>
</html>
<?php exit(); endif; ?>
 
<!-- ============ ENCABEZADO ============ -->
<header class="menu-hero">
    <div class="container-xl py-4">
        <h1 class="fw-bold mb-1">Bienvenido, <?php echo htmlspecialchars($rol); ?></h1>
        <p class="mb-0 text-muted">Aquí tienes un resumen de tu inventario.</p>
    </div>
</header>
 
<main class="container-xl py-4">
 
    <!-- ============ BOTONES (ARRIBA) ============ -->
    <form method="POST" action="menu.php">
        <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
        <div class="row g-3 mb-4">
            <?php foreach ($opciones as $op): ?>
            <div class="col-md-6 col-lg-4">
                <button type="submit" name="<?php echo $op[0]; ?>" class="menu-card card w-100 h-100 text-start p-3 flex-row align-items-center gap-3 shadow-sm">
                    <span class="menu-icon<?php echo $op[4] ? ' oscuro' : ''; ?>"><i class="bi <?php echo $op[1]; ?>"></i></span>
                    <span class="flex-grow-1">
                        <strong class="d-block"><?php echo $op[2]; ?></strong>
                        <span class="text-muted small"><?php echo $op[3]; ?></span>
                    </span>
                    <i class="bi bi-chevron-right text-muted"></i>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </form>
 
    <!-- ============ RESUMEN ============ -->
    <h6 class="menu-seccion">Resumen del inventario</h6>
 
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100"><div class="card-body d-flex gap-3 align-items-center">
                <span class="stat-icon rojo"><i class="bi bi-tags"></i></span>
                <div><div class="text-muted small">Total de productos</div><div class="stat-num"><?php echo $total_prod; ?></div></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100"><div class="card-body d-flex gap-3 align-items-center">
                <span class="stat-icon verde"><i class="bi bi-box-seam"></i></span>
                <div><div class="text-muted small">En stock</div><div class="stat-num"><?php echo $en_stock; ?></div></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100"><div class="card-body d-flex gap-3 align-items-center">
                <span class="stat-icon naranja"><i class="bi bi-exclamation-triangle"></i></span>
                <div><div class="text-muted small">Stock bajo</div><div class="stat-num"><?php echo $stock_bajo; ?></div></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
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
                    <a class="btn btn-danger btn-sm" href="producto.php?rol=<?php echo $rol_url; ?>">
                        <?php if ($esAdminMenu): ?><i class="bi bi-plus-lg me-1"></i>Agregar producto<?php else: ?><i class="bi bi-eye me-1"></i>Ver productos<?php endif; ?>
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Nombre</th><th>Categoría</th><th>Talla</th><th>Precio</th><th>Stock</th><th>Estado</th></tr>
                        </thead>
                        <tbody>
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
 
    <?php if ($esAdminMenu): ?>
    <!-- Ventas vs compras -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <span class="fw-bold fs-6">Ventas y compras · últimos 7 días</span>
            <span class="small text-muted">Valores en pesos</span>
        </div>
        <div class="card-body">
            <div class="chart-box ancho"><canvas id="graficoMovimientos"></canvas></div>
        </div>
    </div>
    <?php endif; ?>
 
</main>
 
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
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
 
    // Barras: ventas vs compras
    var elMov = document.getElementById("graficoMovimientos");
    if (elMov) {
        new Chart(elMov, {
            type: "bar",
            data: {
                labels: <?php echo json_encode($dias); ?>,
                datasets: [
                    { label: "Ventas",  data: <?php echo json_encode($serie_ventas); ?>,  backgroundColor: "#dc3545", borderRadius: 6 },
                    { label: "Compras", data: <?php echo json_encode($serie_compras); ?>, backgroundColor: "#1a1a1a", borderRadius: 6 }
                ]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: "bottom" } },
                scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return "$ " + v.toLocaleString("es-CO"); } } } }
            }
        });
    }
})();
</script>
</body>
</html>