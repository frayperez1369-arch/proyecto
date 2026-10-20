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
<div class="d-flex">
<?php $rol_nav = $rol; $pagina_activa = 'inicio'; include("sidebar.php"); ?>
<main class="flex-grow-1 p-4" style="min-width:0;">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h2 class="fw-bold mb-0">Bienvenido al sistema</h2>
            <small class="text-muted"><?php echo $rol == "Administrador" ? "Panel de Administrador" : ($rol == "Empleado" ? "Panel de Empleado" : "Rol no reconocido"); ?></small>
        </div>
        <?php if ($rol != ""): ?><span class="badge bg-danger"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($rol); ?></span><?php endif; ?>
    </div>

<?php if ($rol == "Administrador"): ?>
    <form method="POST" action="menu.php">
        <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <button type="submit" name="btn_prt" class="btn btn-light border-0 shadow-sm w-100 h-100 text-start p-3">
                    <div class="fs-3 text-danger"><i class="bi bi-box-seam"></i></div>
                    <strong class="d-block">Gestionar Productos</strong>
                    <span class="text-muted small">Prendas, precios y existencias</span>
                </button>
            </div>
            <div class="col-md-4">
                <button type="submit" name="btn_ct" class="btn btn-light border-0 shadow-sm w-100 h-100 text-start p-3">
                    <div class="fs-3 text-danger"><i class="bi bi-grid"></i></div>
                    <strong class="d-block">Gestionar Categorías</strong>
                    <span class="text-muted small">Organiza el catálogo</span>
                </button>
            </div>
            <div class="col-md-4">
                <button type="submit" name="btn_pv" class="btn btn-light border-0 shadow-sm w-100 h-100 text-start p-3">
                    <div class="fs-3 text-danger"><i class="bi bi-truck"></i></div>
                    <strong class="d-block">Gestionar Proveedores</strong>
                    <span class="text-muted small">Datos de quienes te surten</span>
                </button>
            </div>
            <div class="col-md-4">
                <button type="submit" name="btn_emp" class="btn btn-light border-0 shadow-sm w-100 h-100 text-start p-3">
                    <div class="fs-3 text-danger"><i class="bi bi-people"></i></div>
                    <strong class="d-block">Gestionar Empleados</strong>
                    <span class="text-muted small">Altas, bajas y datos del personal</span>
                </button>
            </div>
            <div class="col-md-4">
                <button type="submit" name="btn_c" class="btn btn-light border-0 shadow-sm w-100 h-100 text-start p-3">
                    <div class="fs-3 text-danger"><i class="bi bi-bag-plus"></i></div>
                    <strong class="d-block">Registrar Compra</strong>
                    <span class="text-muted small">Entrada de mercancía</span>
                </button>
            </div>
            <div class="col-md-4">
                <button type="submit" name="btn_vts" class="btn btn-light border-0 shadow-sm w-100 h-100 text-start p-3">
                    <div class="fs-3 text-danger"><i class="bi bi-cart"></i></div>
                    <strong class="d-block">Registrar Venta</strong>
                    <span class="text-muted small">Salida de mercancía</span>
                </button>
            </div>
        </div>
    </form>
<?php elseif ($rol == "Empleado"): ?>
    <form method="POST" action="menu.php">
        <input type="hidden" name="rol" value="<?php echo htmlspecialchars($rol); ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <button type="submit" name="btn_prt" class="btn btn-light border-0 shadow-sm w-100 h-100 text-start p-3">
                    <div class="fs-3 text-danger"><i class="bi bi-box-seam"></i></div>
                    <strong class="d-block">Consultar Productos</strong>
                    <span class="text-muted small">Revisa precios y existencias</span>
                </button>
            </div>
            <div class="col-md-4">
                <button type="submit" name="btn_vts" class="btn btn-light border-0 shadow-sm w-100 h-100 text-start p-3">
                    <div class="fs-3 text-danger"><i class="bi bi-cart"></i></div>
                    <strong class="d-block">Registrar Venta</strong>
                    <span class="text-muted small">Salida de mercancía</span>
                </button>
            </div>
        </div>
    </form>
<?php else: ?>
    <div class="alert alert-danger">Rol no reconocido.</div>
    <form method="POST" action="menu.php">
        <button type="submit" name="btn_cerrar" class="btn btn-dark">Volver al Login</button>
    </form>
<?php endif; ?>
</main>
</div>
</body>
</html>