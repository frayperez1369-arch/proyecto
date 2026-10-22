<?php
// seguridad.php — Archivo CENTRAL de seguridad del sistema BRANDON URBAN.
// Todas las páginas lo cargan con include_once("seguridad.php") y reutilizan sus funciones.
// Aquí vive: la sesión, la verificación de login y de rol, la protección CSRF y el límite de intentos de login.
//
// Orden del archivo:
//   1) Cabeceras de seguridad HTTP
//   2) Sesión segura
//   3) Funciones de sesión (login, logout)
//   4) Funciones de autorización (quién puede entrar a qué)
//   5) Protección CSRF (formularios falsos enviados desde otra web)
//   6) Límite de intentos de login (contra adivinar contraseñas)
//   7) Validación de contraseñas nuevas
 
 
// =====================================================
//  BLOQUE 1: CABECERAS DE SEGURIDAD HTTP
// =====================================================
// Le dan instrucciones de seguridad al navegador en cada respuesta:
//  - X-Frame-Options: nadie puede meter tu sistema dentro de un <iframe> de otra web (evita el "clickjacking").
//  - X-Content-Type-Options: el navegador no "adivina" el tipo de archivo, usa solo el que declaramos.
//  - Referrer-Policy: no se filtra la dirección de tu sistema a otras webs al hacer clic en enlaces externos.
//  - Cache-Control: las páginas privadas no se guardan en el caché; así, después de cerrar sesión, el botón «Atrás» no muestra datos.
// Nota: se omite si la página ya empezó a enviar contenido (headers_sent), para no provocar errores.
if (!headers_sent()) {
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: same-origin");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
}
 
 
// =====================================================
//  BLOQUE 2: SESIÓN SEGURA
// =====================================================
// Inicia la sesión (si no estaba iniciada) con una cookie configurada de forma segura:
//  - httponly: JavaScript no puede leer la cookie (protege el robo de sesión por XSS).
//  - samesite Lax: el navegador no envía la cookie en peticiones disparadas desde otras webs (ayuda contra CSRF).
//  - secure: solo viaja por HTTPS cuando el sitio se sirve con HTTPS.
//  - use_strict_mode: PHP rechaza identificadores de sesión inventados por un atacante.
if (session_status() === PHP_SESSION_NONE) {
    $conexion_https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
    ini_set("session.use_strict_mode", "1");
    // PHP borra por su cuenta las sesiones viejas (por defecto ~24 min); esto evita que cierre sesiones activas.
    ini_set("session.gc_maxlifetime", "86400");
    session_name("BRANDON_SESION");
    session_set_cookie_params([
        "lifetime" => 0,
        "path"     => "/",
        "secure"   => $conexion_https,
        "httponly" => true,
        "samesite" => "Lax",
    ]);
    session_start();
}
 
 
// =====================================================
//  BLOQUE 3: FUNCIONES DE SESIÓN
// =====================================================
 
// ¿Hay un usuario con sesión iniciada? Devuelve true o false.
function usuario_logueado() {
    return isset($_SESSION["id_empleado"]) && isset($_SESSION["rol"]);
}
 
// Devuelve el rol REAL del usuario (Administrador o Empleado), tomado de la sesión del servidor.
// Es la única fuente confiable del rol: ya no se lee de la URL ni de formularios, que el usuario puede modificar.
function rol_actual() {
    return isset($_SESSION["rol"]) ? $_SESSION["rol"] : "";
}
 
// Registra la sesión del usuario después de un login correcto.
//  - session_regenerate_id(true): cambia el identificador de sesión al entrar, así no se puede "fijar" una sesión ajena (session fixation).
//  - Guarda quién es y su rol.
function iniciar_sesion_usuario($id_empleado, $nombre, $rol) {
    session_regenerate_id(true);
    $_SESSION["id_empleado"]      = (int)$id_empleado;
    $_SESSION["nombre"]           = $nombre;
    $_SESSION["rol"]              = $rol;
    // Token CSRF nuevo para esta sesión (ver bloque 5).
    $_SESSION["csrf_token"]       = bin2hex(random_bytes(32));
}
 
// Cierra la sesión por completo: borra los datos del servidor, la cookie del navegador y destruye la sesión.
function cerrar_sesion() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
    }
    session_destroy();
}
 
// Envía al usuario a la pantalla de login y detiene la página (exit) para que no se ejecute nada más.
function redirigir_login() {
    header("Location: index.html");
    exit();
}
 
 
// =====================================================
//  BLOQUE 4: AUTORIZACIÓN (QUIÉN PUEDE ENTRAR A QUÉ)
// =====================================================
 
// Exige que haya un usuario con sesión iniciada. Se llama al principio de CADA página privada.
//  - Sin sesión: lo manda al login.
//  - Con sesión: deja continuar (la sesión ya no se cierra por inactividad).
function requerir_login() {
    if (!usuario_logueado()) {
        redirigir_login();
    }
}
 
// Exige que el usuario sea Administrador. Se usa en las páginas que el Empleado nunca debe ver
// (categorías, proveedores, empleados). Si es Empleado, responde «403 Prohibido» y detiene la página.
function requerir_admin() {
    requerir_login();
    if (rol_actual() !== "Administrador") {
        http_response_code(403);
        echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Acceso denegado</title>";
        echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'></head>";
        echo "<body class='p-5'><div class='alert alert-danger'><b>Acceso denegado.</b> Solo el administrador puede entrar a esta sección.</div>";
        echo "<a class='btn btn-dark' href='menu.php'>Volver al menú</a></body></html>";
        exit();
    }
}
 
 
// =====================================================
//  BLOQUE 5: PROTECCIÓN CSRF
// =====================================================
// CSRF = que otra página web haga que tu navegador (ya con sesión iniciada) envíe una acción a tu sistema sin que lo notes,
// por ejemplo borrar un producto. Se evita poniendo en cada formulario un «token» secreto que una web ajena no puede conocer.
 
// Devuelve el token CSRF de la sesión actual (lo crea si todavía no existe).
function token_csrf() {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}
 
// Devuelve el campo oculto con el token. Se imprime con echo campo_csrf() dentro de cada formulario POST.
function campo_csrf() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(token_csrf()) . '">';
}
 
// Comprueba que el formulario recibido trae el token correcto. Si no coincide, responde «403» y detiene la página.
// hash_equals compara de forma segura (sin filtrar información por el tiempo que tarda la comparación).
function verificar_csrf() {
    $enviado = isset($_POST["csrf_token"]) ? $_POST["csrf_token"] : "";
    if (!is_string($enviado) || !hash_equals(token_csrf(), $enviado)) {
        http_response_code(403);
        echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Solicitud no válida</title>";
        echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'></head>";
        echo "<body class='p-5'><div class='alert alert-danger'><b>Solicitud no válida.</b> La página caducó o el formulario no es auténtico. Vuelve atrás, recarga la página e inténtalo de nuevo.</div>";
        echo "<a class='btn btn-dark' href='menu.php'>Volver al menú</a></body></html>";
        exit();
    }
}
 
 
// =====================================================
//  BLOQUE 6: LÍMITE DE INTENTOS DE LOGIN
// =====================================================
// Evita que alguien pruebe miles de contraseñas seguidas: tras 5 intentos fallidos desde la misma dirección IP
// se bloquea el login durante 10 minutos. Los intentos se guardan en la tabla «intentos_login» (se crea sola).
if (!defined("MAX_INTENTOS_LOGIN")) {
    define("MAX_INTENTOS_LOGIN", 5);        // intentos fallidos permitidos
    define("VENTANA_INTENTOS_MIN", 10);     // minutos que dura el bloqueo / la memoria de intentos
}
 
// Crea la tabla donde se anotan los intentos fallidos (solo la primera vez; si ya existe no hace nada).
function preparar_tabla_intentos($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS intentos_login (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        username VARCHAR(100) NOT NULL,
        fecha DATETIME NOT NULL,
        INDEX idx_ip_fecha (ip, fecha)
    )");
}
 
// Dirección IP de quien está intentando entrar. Se usa REMOTE_ADDR porque otras cabeceras (como X-Forwarded-For) las puede falsificar cualquiera.
function ip_cliente() {
    return isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "desconocida";
}
 
// ¿Esta IP ya superó el máximo de intentos fallidos en los últimos 10 minutos? Devuelve true si está bloqueada.
// Si la base de datos falla, no bloquea a nadie (para no dejar al negocio sin poder entrar) pero anota el error en el log del servidor.
function login_bloqueado($conn) {
    try {
        preparar_tabla_intentos($conn);
        $ip = ip_cliente();
        $min = (int)VENTANA_INTENTOS_MIN;
        $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM intentos_login WHERE ip = ? AND fecha > (NOW() - INTERVAL $min MINUTE)");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $n = (int)$stmt->get_result()->fetch_assoc()["n"];
        $stmt->close();
        return $n >= MAX_INTENTOS_LOGIN;
    } catch (Throwable $e) {
        error_log("login_bloqueado: " . $e->getMessage());
        return false;
    }
}
 
// Anota un intento fallido (IP, usuario escrito y hora).
function registrar_intento_fallido($conn, $username) {
    try {
        preparar_tabla_intentos($conn);
        $ip = ip_cliente();
        $user = substr((string)$username, 0, 100);
        $stmt = $conn->prepare("INSERT INTO intentos_login (ip, username, fecha) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $ip, $user);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log("registrar_intento_fallido: " . $e->getMessage());
    }
}
 
// Borra los intentos fallidos de esta IP cuando el login fue correcto (y limpia registros viejos de cualquier IP).
function limpiar_intentos($conn) {
    try {
        preparar_tabla_intentos($conn);
        $ip = ip_cliente();
        $stmt = $conn->prepare("DELETE FROM intentos_login WHERE ip = ? OR fecha < (NOW() - INTERVAL 1 DAY)");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log("limpiar_intentos: " . $e->getMessage());
    }
}
 
 
// =====================================================
//  BLOQUE 7: VALIDACIÓN DE CONTRASEÑAS NUEVAS
// =====================================================
// Regla mínima para las contraseñas que el administrador asigna a los empleados: al menos 8 caracteres.
// Devuelve true si la contraseña cumple la regla.
function contrasena_valida($password) {
    return is_string($password) && strlen($password) >= 8;
}
?>