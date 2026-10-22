<?php
// reporte.php — Módulo de REPORTES: empleados que más vendieron, productos más vendidos, ventas y compras.
// Solo el administrador entra: escoge el reporte y el rango de fechas, lo ve en pantalla y lo descarga en PDF o Excel.
// El PDF se arma a mano (sin librerías externas) y el Excel es una tabla HTML que Excel abre directamente.
//
// Conexión a la base de datos (deja lista la variable $conn).
include("conexion.php");
 
// Variables de estado: $mensaje (aviso de error) y $mostrar (true cuando hay un reporte para dibujar en pantalla).
$mensaje = "";
$mostrar = false;
// Hace que los errores de MySQL se lancen como excepciones para poder capturarlos con try/catch.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
 
// --- SEGURIDAD: sesión, rol y token CSRF ---
// seguridad.php inicia la sesión y define las funciones de protección.
include_once("seguridad.php");
// Si no hay sesión iniciada (o caducó por inactividad), manda al login y detiene la página.
requerir_login();
// El rol sale de la sesión del servidor, ya NO de la URL ni de formularios (cualquiera podría modificarlos).
$rol = rol_actual();
// Todo formulario POST debe traer su token CSRF (ver campo_csrf() en los formularios); si no coincide, se detiene la página.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verificar_csrf();
}
 
// Solo el administrador puede ver y descargar reportes.
$esAdmin = ($rol === "Administrador");
 
// Nombre del administrador que firma el reporte (el primero que aparezca en la tabla empleado).
$consulta_admin = $conn->query("SELECT nombre FROM empleado WHERE rol='Administrador' ORDER BY id_empleado ASC LIMIT 1");
$fila_admin = $consulta_admin->fetch_assoc();
$nombre_admin = "Administrador";
if ($fila_admin) {
    $nombre_admin = $fila_admin["nombre"];
}
 
// Tipos de reporte que se pueden escoger (clave => texto que ve el usuario).
$tipos = array(
    "empleados" => "Empleados que más vendieron",
    "productos" => "Productos más vendidos",
    "ventas"    => "Ventas",
    "compras"   => "Compras"
);
 
// Valores del formulario: se conservan después de buscar. Si es la primera vez, el reporte es el de empleados y el rango son los últimos 7 días.
$v_tipo = "empleados";
if (isset($_POST["tipo"])) {
    $v_tipo = $_POST["tipo"];
}
$v_desde = date("Y-m-d", strtotime("-7 days"));
if (isset($_POST["desde"])) {
    $v_desde = $_POST["desde"];
}
$v_hasta = date("Y-m-d");
if (isset($_POST["hasta"])) {
    $v_hasta = $_POST["hasta"];
}
 
// Función simple: da formato de dinero. Ejemplo: 15000 queda como $ 15.000
function dinero($valor) {
    $valor = (float)$valor;
    $signo = "";
    if ($valor < 0) {
        $signo = "-";
    }
    return $signo . "$ " . number_format(abs($valor), 0, ",", ".");
}
 
// Función simple: cambia la fecha de 2026-10-15 a 15/10/2026.
function fecha_corta($fecha) {
    return date("d/m/Y", strtotime($fecha));
}
 
// Función simple del PDF: devuelve la instrucción que escribe un texto en la página.
// x = distancia desde la izquierda, y = distancia desde arriba, size = tamaño de letra, color = "rojo verde azul" de 0 a 1.
function texto_pdf($x, $y, $texto, $size, $negrita, $color) {
    // F1 es la letra normal y F2 la negrita (se definen más abajo, en los objetos 3 y 4).
    $fuente = "F1";
    if ($negrita) {
        $fuente = "F2";
    }
    // Pasa el texto a Windows-1252 para que las tildes y la ñ se vean bien, y protege los paréntesis y la barra invertida.
    $texto = mb_convert_encoding($texto, "Windows-1252", "UTF-8");
    $texto = str_replace(array("\\", "(", ")"), array("\\\\", "\\(", "\\)"), $texto);
    // En el PDF el 0 está abajo de la hoja (842 de alto), por eso se resta: así el 0 queda arriba como se piensa normalmente.
    $yy = 842 - $y;
    return "$color rg BT /$fuente $size Tf $x $yy Td ($texto) Tj ET\n";
}
 
// Función simple del PDF: devuelve la instrucción que dibuja una línea gris horizontal.
function linea_pdf($x1, $x2, $y) {
    $yy = 842 - $y;
    return "0.7 G $x1 $yy m $x2 $yy l S\n";
}
 
// Función simple del PDF: devuelve una fila de la tabla (o el encabezado rojo cuando $cabecera es true).
function fila_pdf($celdas, $anchos, $y, $cabecera) {
    $c = "";
    $color = "0 0 0";
 
    // El encabezado lleva un rectángulo rojo de fondo y el texto en blanco.
    if ($cabecera) {
        $color = "1 1 1";
        $c .= "0.86 0.21 0.27 rg 40 " . (836 - $y) . " 515 18 re f\n";
    }
 
    // Escribe cada celda una al lado de la otra.
    $x = 40;
    foreach ($celdas as $i => $texto) {
        // Si el texto es más largo que la columna, se corta y se le pone "..." al final.
        $max = max(1, (int)floor(($anchos[$i] - 8) / 4.7));
        if (mb_strlen($texto) > $max) {
            $texto = mb_substr($texto, 0, $max - 3) . "...";
        }
        $c .= texto_pdf($x + 4, $y, $texto, 9, $cabecera, $color);
        $x = $x + $anchos[$i];
    }
 
    // Línea gris debajo de cada fila (el encabezado no lleva).
    if (!$cabecera) {
        $c .= linea_pdf(40, 555, $y + 5);
    }
    return $c;
}
 
// Buscar el reporte o descargarlo (solo administrador, y solo cuando se pulsó Buscar, PDF o Excel).
if ($esAdmin && $_SERVER["REQUEST_METHOD"] === "POST" && (isset($_POST["buscar"]) || isset($_POST["formato"]))) {
 
    // Validaciones en orden: 1) el tipo de reporte existe, 2) las fechas son válidas, 3) la fecha inicial no es mayor que la final.
    if (!isset($tipos[$v_tipo])) {
        $mensaje = "<div class='alert alert-danger'>Escoge un reporte válido.</div>";
    } elseif (date("Y-m-d", strtotime($v_desde)) !== $v_desde || date("Y-m-d", strtotime($v_hasta)) !== $v_hasta) {
        $mensaje = "<div class='alert alert-danger'>Escoge una fecha válida.</div>";
    } elseif ($v_desde > $v_hasta) {
        $mensaje = "<div class='alert alert-danger'>La fecha inicial no puede ser mayor que la final.</div>";
    } else {
        try {
 
            // Según el tipo se escoge la consulta, el título, las columnas y los anchos de las columnas del PDF.
            if ($v_tipo === "empleados") {
                // Suma las unidades y el dinero vendido por cada empleado (el que más vendió primero).
                $sql = "SELECT e.nombre, SUM(v.cantidad) AS unidades, SUM(v.total) AS total_vendido
                        FROM venta v
                        LEFT JOIN empleado e ON v.id_empleado = e.id_empleado
                        WHERE DATE(v.fecha) BETWEEN ? AND ?
                        GROUP BY v.id_empleado, e.nombre
                        ORDER BY total_vendido DESC, unidades DESC";
                $titulo = "Empleados que más vendieron";
                $cols = array("#", "Empleado", "Unidades", "Total vendido");
                $anchos = array(40, 255, 100, 120);
                $etiqueta = "Total vendido";
            } elseif ($v_tipo === "productos") {
                // Suma las unidades y el dinero vendido de cada producto (el más vendido primero).
                $sql = "SELECT p.nombre, SUM(v.cantidad) AS unidades, SUM(v.total) AS total_vendido
                        FROM venta v
                        LEFT JOIN producto p ON v.id_producto = p.id_producto
                        WHERE DATE(v.fecha) BETWEEN ? AND ?
                        GROUP BY v.id_producto, p.nombre
                        ORDER BY unidades DESC, total_vendido DESC";
                $titulo = "Productos más vendidos";
                $cols = array("#", "Producto", "Unidades", "Total vendido");
                $anchos = array(40, 255, 100, 120);
                $etiqueta = "Total vendido";
            } elseif ($v_tipo === "ventas") {
                // Lista cada venta del periodo con el nombre del empleado y del producto.
                $sql = "SELECT v.id_venta, v.fecha, e.nombre AS persona, p.nombre AS producto, v.cantidad, v.precio_unitario, v.total
                        FROM venta v
                        LEFT JOIN empleado e ON v.id_empleado = e.id_empleado
                        LEFT JOIN producto p ON v.id_producto = p.id_producto
                        WHERE DATE(v.fecha) BETWEEN ? AND ?
                        ORDER BY v.fecha ASC, v.id_venta ASC";
                $titulo = "Reporte de ventas";
                $cols = array("Fecha", "Empleado", "Producto", "Cant.", "P. Unit.", "Forma de pago", "Total");
                $anchos = array(60, 95, 105, 40, 65, 80, 70);
                $etiqueta = "Total de ventas";
            } else {
                // Lista cada compra del periodo con el nombre del proveedor y del producto.
                $sql = "SELECT c.fecha, pv.nombre AS persona, p.nombre AS producto, c.cantidad, c.precio_unitario, c.total
                        FROM compra c
                        LEFT JOIN proveedor pv ON c.id_proveedor = pv.id_proveedor
                        LEFT JOIN producto p ON c.id_producto = p.id_producto
                        WHERE DATE(c.fecha) BETWEEN ? AND ?
                        ORDER BY c.fecha ASC, c.id_compra ASC";
                $titulo = "Reporte de compras";
                $cols = array("Fecha", "Proveedor", "Producto", "Cant.", "P. Unit.", "Total");
                $anchos = array(65, 110, 150, 50, 65, 75);
                $etiqueta = "Total de compras";
            }
 
            // Consulta preparada: los dos ? se reemplazan de forma segura por la fecha inicial y la final (s = texto).
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $v_desde, $v_hasta);
            $stmt->execute();
            $res = $stmt->get_result();
 
            // Pasa cada registro a una fila de texto y va sumando el total del reporte.
            $filas = array();
            $suma = 0;
            $posicion = 1;
            // Total vendido por cada forma de pago (solo se usa en el reporte de ventas). Todas empiezan en 0 para que siempre se vean.
            $totales_pago = array();
            if ($v_tipo === "ventas") {
                foreach (array("Efectivo", "Nequi", "Bancolombia", "Daviplata", "Datáfono") as $fp) {
                    $totales_pago[$fp] = 0;
                }
            }
            while ($f = $res->fetch_assoc()) {
                if ($v_tipo === "empleados" || $v_tipo === "productos") {
                    // Reportes con posición: #, nombre, unidades y total vendido.
                    $nombre = $f["nombre"];
                    if ($nombre === null) {
                        $nombre = "—";
                    }
                    $filas[] = array((string)$posicion, (string)$nombre, (string)(int)$f["unidades"], dinero($f["total_vendido"]));
                    $suma = $suma + $f["total_vendido"];
                    $posicion++;
                } else {
                    // Ventas y compras: fecha, persona, producto, cantidad, precio unitario y total.
                    $persona = $f["persona"];
                    if ($persona === null) {
                        $persona = "—";
                    }
                    $producto = $f["producto"];
                    if ($producto === null) {
                        $producto = "—";
                    }
                    if ($v_tipo === "ventas") {
                        // La forma de pago no está en la base de datos: se toma de la sesión (la guarda venta.php). Si no hay, se muestra «—».
                        $forma_pago = $_SESSION["formas_pago"][$f["id_venta"]] ?? "—";
                        // Suma el total de esta venta a su forma de pago (las que no tienen forma de pago van en «Sin forma de pago»).
                        $clave_pago = isset($totales_pago[$forma_pago]) ? $forma_pago : "Sin forma de pago";
                        $totales_pago[$clave_pago] = ($totales_pago[$clave_pago] ?? 0) + $f["total"];
                        $filas[] = array(fecha_corta($f["fecha"]), (string)$persona, (string)$producto, (string)(int)$f["cantidad"], dinero($f["precio_unitario"]), (string)$forma_pago, dinero($f["total"]));
                    } else {
                        $filas[] = array(fecha_corta($f["fecha"]), (string)$persona, (string)$producto, (string)(int)$f["cantidad"], dinero($f["precio_unitario"]), dinero($f["total"]));
                    }
                    $suma = $suma + $f["total"];
                }
            }
            $stmt->close();
            // Hay reporte listo: el HTML de abajo dibuja la tabla.
            $mostrar = true;
 
            // Si pulsó el botón PDF o Excel, se arma el archivo y se descarga.
            if (isset($_POST["formato"]) && in_array($_POST["formato"], array("pdf", "excel"))) {
                $archivo = "reporte_" . $v_tipo . "_" . $v_desde . "_a_" . $v_hasta;
 
                if ($_POST["formato"] === "pdf") {
 
                    // ===== PDF =====
                    // Se va armando el texto del PDF: $paginas guarda las páginas terminadas, $c la página actual y $y la altura donde se escribe.
                    $paginas = array();
                    $c = "";
                    $y = 105;
 
                    // Cabecera: franja negra, línea roja y el nombre de la tienda en blanco.
                    $c .= "0.1 0.1 0.1 rg 0 772 595 70 re f\n";
                    $c .= "0.86 0.21 0.27 rg 0 768 595 4 re f\n";
                    $c .= texto_pdf(40, 45, "BRANDON URBAN", 22, true, "1 1 1");
 
                    // Título y datos del reporte (cada línea baja un poco más en la hoja).
                    $c .= texto_pdf(40, $y, mb_strtoupper($titulo), 15, true, "0 0 0");
                    $y = $y + 22;
                    $c .= texto_pdf(40, $y, "Periodo: " . fecha_corta($v_desde) . " al " . fecha_corta($v_hasta), 10, false, "0 0 0");
                    $y = $y + 14;
                    $c .= texto_pdf(40, $y, "Generado por: $nombre_admin (Administrador)", 10, false, "0 0 0");
                    $y = $y + 14;
                    $c .= texto_pdf(40, $y, "Fecha y hora de generación: " . date("d/m/Y H:i"), 10, false, "0 0 0");
                    $y = $y + 14;
                    $c .= texto_pdf(40, $y, "Total de registros: " . count($filas), 10, false, "0 0 0");
                    $y = $y + 28;
 
                    if (count($filas) === 0) {
                        // Si no hay datos en el periodo, solo se avisa.
                        $c .= texto_pdf(40, $y, "No hay registros en este periodo.", 11, false, "0 0 0");
                        $y = $y + 40;
                    } else {
                        // Si no caben el encabezado y unas filas, se pasa a una página nueva (la hoja se escribe hasta la altura 790).
                        if ($y + 18 * (min(count($filas), 3) + 1) + 10 > 790) {
                            $paginas[] = $c;
                            $c = "";
                            $y = 60;
                        }
 
                        // Encabezado rojo de la tabla.
                        $c .= fila_pdf($cols, $anchos, $y, true);
                        $y = $y + 18;
 
                        // Una fila por cada registro.
                        foreach ($filas as $fila) {
                            // Si se acabó la página: se guarda, se empieza otra y se repite el encabezado rojo.
                            if ($y > 790) {
                                $paginas[] = $c;
                                $c = "";
                                $y = 60;
                                $c .= fila_pdf($cols, $anchos, $y, true);
                                $y = $y + 18;
                            }
                            $c .= fila_pdf($fila, $anchos, $y, false);
                            $y = $y + 18;
                        }
 
                        // Total del reporte debajo de la tabla (en ventas se muestra más abajo, después de las formas de pago).
                        $y = $y + 14;
                        if ($v_tipo !== "ventas") {
                            if ($y + 30 > 790) {
                                $paginas[] = $c;
                                $c = "";
                                $y = 60;
                            }
                            $c .= texto_pdf(40, $y, $etiqueta . ": " . dinero($suma), 11, true, "0 0 0");
                            $y = $y + 40;
                        }
 
                        // Reporte de ventas: total vendido por cada forma de pago (la de «Sin forma de pago» solo sale si tiene valor).
                        if ($v_tipo === "ventas") {
                            $lineas_pago = array();
                            foreach ($totales_pago as $nombre_pago => $valor_pago) {
                                if ($nombre_pago === "Sin forma de pago" && $valor_pago == 0) {
                                    continue;
                                }
                                $lineas_pago[$nombre_pago] = $valor_pago;
                            }
                            // Si no caben el título y todas las líneas, se pasa a una página nueva.
                            if ($y + 20 + 15 * count($lineas_pago) + 30 > 790) {
                                $paginas[] = $c;
                                $c = "";
                                $y = 60;
                            }
                            $c .= texto_pdf(40, $y, "Total por forma de pago", 11, true, "0 0 0");
                            $y = $y + 18;
                            foreach ($lineas_pago as $nombre_pago => $valor_pago) {
                                $c .= texto_pdf(40, $y, $nombre_pago . ": " . dinero($valor_pago), 10, false, "0 0 0");
                                $y = $y + 15;
                            }
                            // Total de ventas justo debajo de la última forma de pago (Datáfono).
                            $y = $y + 10;
                            $c .= texto_pdf(40, $y, $etiqueta . ": " . dinero($suma), 11, true, "0 0 0");
                            $y = $y + 40;
                        }
                    }
 
                    // Firma del administrador (una línea con su nombre debajo).
                    if ($y + 60 > 790) {
                        $paginas[] = $c;
                        $c = "";
                        $y = 60;
                    }
                    $y = $y + 20;
                    $c .= linea_pdf(40, 240, $y);
                    $y = $y + 14;
                    $c .= texto_pdf(40, $y, $nombre_admin, 10, true, "0 0 0");
                    $y = $y + 13;
                    $c .= texto_pdf(40, $y, "Administrador - BRANDON URBAN", 9, false, "0 0 0");
 
                    // Guarda la última página y cuenta cuántas son en total.
                    $paginas[] = $c;
                    $n = count($paginas);
 
                    // Pie de página en todas las hojas: una línea, el nombre del reporte y "Página X de Y".
                    for ($i = 0; $i < $n; $i++) {
                        $paginas[$i] .= linea_pdf(40, 555, 808);
                        $paginas[$i] .= texto_pdf(40, 822, "BRANDON URBAN - " . $titulo, 8, false, "0.4 0.4 0.4");
                        $paginas[$i] .= texto_pdf(493, 822, "Página " . ($i + 1) . " de " . $n, 8, false, "0.4 0.4 0.4");
                    }
 
                    // Un PDF está hecho de "objetos" numerados que se conectan entre sí (3 0 R significa "ver el objeto 3").
                    $obj = array();
                    // Objeto 1: el catálogo, es por donde empieza el visor; dice que las páginas están en el objeto 2.
                    $obj[1] = "<< /Type /Catalog /Pages 2 0 R >>";
                    // Objeto 2: la lista de páginas. Las páginas empiezan en el objeto 5 y cada una usa 2 objetos, por eso 5, 7, 9...
                    $kids = "";
                    for ($i = 0; $i < $n; $i++) {
                        $kids .= (5 + 2 * $i) . " 0 R ";
                    }
                    $obj[2] = "<< /Type /Pages /Kids [$kids] /Count $n >>";
                    // Objetos 3 y 4: las dos letras que se usan, normal y negrita (ya vienen incluidas en todos los visores de PDF).
                    $obj[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
                    $obj[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
                    // Por cada página: un objeto con su tamaño (A4 = 595 x 842) y otro con lo que se dibuja en ella.
                    for ($i = 0; $i < $n; $i++) {
                        $obj[5 + 2 * $i] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents " . (6 + 2 * $i) . " 0 R >>";
                        $obj[6 + 2 * $i] = "<< /Length " . strlen($paginas[$i]) . " >>\nstream\n" . $paginas[$i] . "\nendstream";
                    }
 
                    // Escribe el archivo: la cabecera y cada objeto, guardando en qué posición quedó cada uno.
                    $contenido = "%PDF-1.4\n";
                    $posiciones = array();
                    foreach ($obj as $numero => $texto_obj) {
                        $posiciones[$numero] = strlen($contenido);
                        $contenido .= "$numero 0 obj\n$texto_obj\nendobj\n";
                    }
 
                    // Tabla de referencias (xref): un índice con la posición de cada objeto para que el visor los encuentre rápido.
                    $xref = strlen($contenido);
                    $total = count($obj) + 1;
                    $contenido .= "xref\n0 $total\n0000000000 65535 f \n";
                    foreach ($posiciones as $pos) {
                        $contenido .= sprintf("%010d 00000 n \n", $pos);
                    }
                    // Final del archivo: indica que el catálogo es el objeto 1 y dónde está la tabla xref.
                    $contenido .= "trailer\n<< /Size $total /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
 
                    // Avisa al navegador que es un PDF y que debe descargarlo con ese nombre.
                    header("Content-Type: application/pdf");
                    header("Content-Disposition: attachment; filename=\"$archivo.pdf\"");
 
                } else {
 
                    // ===== EXCEL (una tabla HTML que Excel abre directamente; el diseño va en los style de cada celda) =====
                    $n = count($cols);
 
                    // Estilos reutilizables (mismos colores del PDF: negro, rojo y gris).
                    $borde = 'border:1px solid #d9d9d9;';
                    $estilo_info = 'color:#555555;font-size:10pt;';
                    $contenido = '<html><head><meta charset="UTF-8"></head><body>';
                    $contenido .= '<table border="0" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-family:Segoe UI,Arial,sans-serif;font-size:10pt">';
 
                    // Franja negra con el nombre de la tienda en blanco y una línea roja debajo.
                    $contenido .= '<tr><td colspan="' . $n . '" style="background:#1a1a1a;color:#ffffff;font-size:20pt;font-weight:bold;height:42px;border-bottom:4px solid #dc3545">BRANDON URBAN</td></tr>';
                    // Título del reporte.
                    $contenido .= '<tr><td colspan="' . $n . '" style="font-size:15pt;font-weight:bold;height:30px">' . htmlspecialchars(mb_strtoupper($titulo)) . '</td></tr>';
                    // Datos del reporte en gris.
                    $contenido .= '<tr><td colspan="' . $n . '" style="' . $estilo_info . '">Periodo: ' . fecha_corta($v_desde) . ' al ' . fecha_corta($v_hasta) . '</td></tr>';
                    $contenido .= '<tr><td colspan="' . $n . '" style="' . $estilo_info . '">Generado por: ' . htmlspecialchars($nombre_admin) . ' (Administrador)</td></tr>';
                    $contenido .= '<tr><td colspan="' . $n . '" style="' . $estilo_info . '">Fecha y hora de generación: ' . date("d/m/Y H:i") . '</td></tr>';
                    $contenido .= '<tr><td colspan="' . $n . '" style="' . $estilo_info . 'height:26px">Total de registros: ' . count($filas) . '</td></tr>';
 
                    // Títulos de las columnas: fondo rojo, letra blanca en negrita y ancho según la columna.
                    $contenido .= '<tr>';
                    foreach ($cols as $i => $col) {
                        $ancho_col = (int)round($anchos[$i] * 1.5);
                        $contenido .= '<th style="background:#dc3545;color:#ffffff;font-weight:bold;text-align:center;width:' . $ancho_col . 'px;height:26px;border:1px solid #dc3545">' . htmlspecialchars($col) . '</th>';
                    }
                    $contenido .= '</tr>';
 
                    // Una fila por cada registro: filas alternas en gris claro, bordes finos y los valores de dinero/números a la derecha.
                    foreach ($filas as $k => $fila) {
                        $fondo = ($k % 2 === 1) ? 'background:#f5f5f5;' : 'background:#ffffff;';
                        $contenido .= '<tr>';
                        foreach ($fila as $celda) {
                            $alinear = (substr($celda, 0, 1) === '$' || ctype_digit($celda)) ? 'text-align:right;' : 'text-align:left;';
                            $contenido .= '<td style="' . $fondo . $borde . $alinear . '">' . htmlspecialchars($celda) . '</td>';
                        }
                        $contenido .= '</tr>';
                    }
 
                    // Total del reporte al final: fondo rojo suave, letra en negrita y línea roja arriba.
                    // (En ventas se pone después de las formas de pago, justo debajo de Datáfono.)
                    $fila_total = '<tr><td colspan="' . $n . '" style="background:#fde8ea;font-weight:bold;font-size:11pt;height:28px;border-top:2px solid #dc3545">' . $etiqueta . ': ' . dinero($suma) . '</td></tr>';
                    if ($v_tipo !== "ventas") {
                        $contenido .= $fila_total;
                    }
 
                    // Reporte de ventas: total vendido por cada forma de pago (nombre a la izquierda y valor a la derecha).
                    if ($v_tipo === "ventas") {
                        $contenido .= '<tr><td colspan="' . $n . '" style="height:14px"></td></tr>';
                        $contenido .= '<tr><td colspan="' . $n . '" style="background:#1a1a1a;color:#ffffff;font-weight:bold;height:26px">TOTAL POR FORMA DE PAGO</td></tr>';
                        $k = 0;
                        foreach ($totales_pago as $nombre_pago => $valor_pago) {
                            if ($nombre_pago === "Sin forma de pago" && $valor_pago == 0) {
                                continue;
                            }
                            $fondo = ($k % 2 === 1) ? 'background:#f5f5f5;' : 'background:#ffffff;';
                            $contenido .= '<tr><td colspan="' . ($n - 1) . '" style="' . $fondo . $borde . '">' . htmlspecialchars($nombre_pago) . '</td>'
                                        . '<td style="' . $fondo . $borde . 'text-align:right;font-weight:bold">' . dinero($valor_pago) . '</td></tr>';
                            $k++;
                        }
                        // Total de ventas justo debajo de la última forma de pago (Datáfono).
                        $contenido .= $fila_total;
                    }
                    $contenido .= '</table></body></html>';
 
                    // Avisa al navegador que es un archivo de Excel y que debe descargarlo con ese nombre.
                    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
                    header("Content-Disposition: attachment; filename=\"$archivo.xls\"");
                }
 
                // Envía el archivo al navegador, cierra la conexión y termina (no se dibuja el HTML de abajo).
                header("Content-Length: " . strlen($contenido));
                echo $contenido;
                $conn->close();
                exit();
            }
        } catch (Throwable $e) {
            // Si algo falla se muestra el error y no se dibuja la tabla.
            $mostrar = false;
            $mensaje = "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>
<?php /* Desde aquí empieza el HTML de la página (lo que ve el usuario). */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reportes</title>
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
$pagina_activa = 'reporte';   // inicio | producto | categoria | proveedor | empleado | compra | venta | reporte
// Dos versiones del rol: $rn para mostrarlo en HTML de forma segura y $ru para ponerlo en la URL de los enlaces.
$rn = htmlspecialchars($rol_nav);
$ru = urlencode($rol_nav);
 
// [archivo, icono, texto, solo administrador]
$items_nav = [
    ["producto",  "bi-box-seam",             "Productos",   false],
    ["categoria", "bi-grid",                 "Categorías",  true],
    ["proveedor", "bi-truck",                "Proveedores", true],
    ["empleado",  "bi-people",               "Empleados",   true],
    ["compra",    "bi-bag-plus",             "Compras",     true],
    ["venta",     "bi-cart",                 "Ventas",      false],
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
        <div>
            <h2 class="fw-bold mb-0">Reportes</h2>
            <small class="text-muted">Escoge el reporte y el rango de fechas, y busca para verlo en pantalla</small>
        </div>
        <?php if ($rol !== ""): ?>
            <span class="badge bg-danger"><i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($rol); ?></span>
        <?php endif; ?>
    </div>
 
    <?php /* Muestra el aviso de error de la última acción (fechas inválidas, error de la base de datos, etc.). */ ?>
    <?php echo $mensaje; ?>
 
    <?php if (!$esAdmin): ?>
 
        <?php /* Si no es administrador, no ve nada del módulo. */ ?>
        <div class="alert alert-danger">Solo el administrador puede ver los reportes.</div>
 
    <?php else: ?>
 
        <?php /* Formulario de filtros: tipo de reporte y rango de fechas. */ ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-funnel text-danger me-1"></i> Filtros</div>
            <div class="card-body">
                <form method="POST">
                    <?php /* Token CSRF: demuestra que el formulario salió de esta página. */ echo campo_csrf(); ?>
                    <?php /* Campos del formulario distribuidos en columnas (cuadrícula de Bootstrap). */ ?>
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-6">
                            <label class="form-label">Reporte</label>
                            <?php /* El select se arma con el arreglo $tipos y deja marcado el que ya estaba escogido. */ ?>
                            <select class="form-select" name="tipo" required>
                                <?php foreach ($tipos as $clave => $texto): ?>
                                    <option value="<?php echo $clave; ?>" <?php if ($v_tipo === $clave) { echo "selected"; } ?>><?php echo $texto; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label">Desde</label>
                            <input class="form-control" type="date" name="desde" value="<?php echo htmlspecialchars($v_desde); ?>" required>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label">Hasta</label>
                            <input class="form-control" type="date" name="hasta" value="<?php echo htmlspecialchars($v_hasta); ?>" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" name="buscar" class="btn btn-danger"><i class="bi bi-search me-1"></i>Buscar</button>
                    </div>
                    <small class="text-muted d-block mt-2">Para ver un solo día, pon la misma fecha en "Desde" y "Hasta".</small>
                </form>
            </div>
        </div>
 
        <?php if ($mostrar): ?>
 
            <?php /* Tarjeta con la tabla del reporte. */ ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">
                        <i class="bi bi-list-ul text-danger me-1"></i> <?php echo htmlspecialchars($titulo); ?>
                        <small class="text-muted">del <?php echo fecha_corta($v_desde); ?> al <?php echo fecha_corta($v_hasta); ?></small>
                    </span>
                    <span class="badge bg-secondary"><?php echo count($filas); ?> registro(s)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <?php /* Encabezados de las columnas de la tabla. */ ?>
                        <thead class="table-light">
                            <tr>
                                <?php foreach ($cols as $col): ?>
                                    <th><?php echo htmlspecialchars($col); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php /* Si hay registros, el foreach dibuja una fila por cada uno; si no hay, se muestra «No hay registros en este periodo». */ ?>
                            <?php if (count($filas) > 0): ?>
                                <?php foreach ($filas as $fila): ?>
                                    <tr>
                                        <?php foreach ($fila as $i => $celda): ?>
                                            <td>
                                                <?php /* En los reportes con ranking, la primera columna (la posición) va dentro de una insignia gris. */ ?>
                                                <?php if ($i === 0 && ($v_tipo === "empleados" || $v_tipo === "productos")): ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($celda); ?></span>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($celda); ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo count($cols); ?>" class="text-center text-muted py-4">No hay registros en este periodo.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php /* Total del reporte (solo si hay registros). */ ?>
                <?php if (count($filas) > 0): ?>
                    <div class="card-footer bg-white fw-bold"><?php echo htmlspecialchars($etiqueta); ?>: <?php echo dinero($suma); ?></div>
                    <?php /* Reporte de ventas: total vendido por cada forma de pago. */ ?>
                    <?php if ($v_tipo === "ventas"): ?>
                        <div class="card-footer bg-white">
                            <div class="fw-bold mb-3">Total por forma de pago</div>
                            <?php /* Icono de cada forma de pago (el diseño de las cajas está en general.css). */ ?>
                            <?php $iconos_pago = array("Efectivo" => "bi-cash-coin", "Nequi" => "bi-phone", "Bancolombia" => "bi-bank", "Daviplata" => "bi-wallet2", "Datáfono" => "bi-credit-card", "Sin forma de pago" => "bi-question-circle"); ?>
                            <div class="row g-3">
                                <?php foreach ($totales_pago as $nombre_pago => $valor_pago): ?>
                                    <?php if ($nombre_pago === "Sin forma de pago" && $valor_pago == 0) continue; ?>
                                    <div class="col-12 col-md-6 col-lg-4 col-xl">
                                        <div class="total-pago-caja">
                                            <div class="total-pago-icono"><i class="bi <?php echo $iconos_pago[$nombre_pago] ?? "bi-cash"; ?>"></i></div>
                                            <div>
                                                <div class="total-pago-nombre"><?php echo htmlspecialchars($nombre_pago); ?></div>
                                                <div class="total-pago-valor"><?php echo dinero($valor_pago); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
 
            <?php /* Botones de descarga: reenvían los mismos filtros y el botón pulsado (pdf o excel) llega en $_POST["formato"]. */ ?>
            <?php if (count($filas) > 0): ?>
                <div class="card shadow-sm mb-3">
                    <div class="card-body d-flex flex-wrap align-items-center gap-2">
                        <span class="fw-semibold me-auto"><i class="bi bi-download text-danger me-1"></i> Descargar este reporte</span>
                        <form method="POST" class="d-flex gap-2 m-0">
                            <?php /* Token CSRF: demuestra que el formulario salió de esta página. */ echo campo_csrf(); ?>
                            <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($v_tipo); ?>">
                            <input type="hidden" name="desde" value="<?php echo htmlspecialchars($v_desde); ?>">
                            <input type="hidden" name="hasta" value="<?php echo htmlspecialchars($v_hasta); ?>">
                            <button type="submit" name="formato" value="pdf" class="btn btn-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
                            <button type="submit" name="formato" value="excel" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
 
        <?php endif; ?>
    <?php endif; ?>
 
    <?php /* Botón «Menú Principal»: vuelve al menú enviando el rol por POST. */ ?>
    <form method="POST" action="menu.php" class="mb-3">
        <button type="submit" class="btn btn-dark"><i class="bi bi-arrow-left me-1"></i>Menú Principal</button>
    </form>
 
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