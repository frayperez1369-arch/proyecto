<?php
// Barra lateral compartida.
// Antes de incluirla, cada página define:
//   $rol_nav        -> rol del sistema ("Administrador" o "Empleado")
//   $pagina_activa  -> inicio | producto | categoria | proveedor | empleado | compra | venta
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
<aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
    <div class="text-center mb-4">
        <!-- Guarda tu logo como logo.png en esta misma carpeta -->
        <div class="rounded-circle bg-white mx-auto d-flex align-items-center justify-content-center overflow-hidden" style="width:64px;height:64px;">
            <img src="logo.png" alt="JFYJ" class="w-100 h-100 object-fit-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
            <span class="fw-bold fs-4 text-dark" style="display:none;">J</span>
        </div>
        <div class="brand-jfyj text-white fw-bold mt-2">JFYJ</div>
    </div>

    <nav class="nav nav-pills flex-column gap-1">
        <form method="POST" action="menu.php" class="m-0">
            <input type="hidden" name="rol" value="<?php echo $rn; ?>">
            <button type="submit" class="nav-link w-100 text-start<?php echo $pagina_activa === 'inicio' ? ' active' : ''; ?>"><i class="bi bi-house-door me-2"></i>Inicio</button>
        </form>
        <?php foreach ($items_nav as $it): ?>
            <?php if ($it[3] && $rol_nav !== "Administrador") continue; ?>
            <a class="nav-link<?php echo $pagina_activa === $it[0] ? ' active' : ''; ?>" href="<?php echo $it[0]; ?>.php?rol=<?php echo $ru; ?>"><i class="bi <?php echo $it[1]; ?> me-2"></i><?php echo $it[2]; ?></a>
        <?php endforeach; ?>
    </nav>

    <form method="POST" action="menu.php" class="mt-auto pt-3 m-0">
        <input type="hidden" name="rol" value="<?php echo $rn; ?>">
        <button type="submit" name="btn_cerrar" class="nav-link w-100 text-start"><i class="bi bi-box-arrow-left me-2"></i>Cerrar sesión</button>
    </form>
</aside>
