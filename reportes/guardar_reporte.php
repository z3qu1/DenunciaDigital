<?php
require __DIR__ . '/../incluides/autenticacion.php';
require __DIR__ . '/../incluides/conexion.php';   // debe definir $conexion (mysqli)

header('Content-Type: application/json; charset=utf-8');

function responder($ok, $mensaje, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método no permitido.', 405);
}
if (empty($_SESSION['usuario']['id'])) {
    responder(false, 'Debes iniciar sesión para enviar una denuncia.', 401);
}

$token = $_POST['csrf'] ?? '';
if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
    responder(false, 'La sesión expiró. Recarga la página e inténtalo de nuevo.', 419);
}

$tiposValidos = ['seguridad', 'alumbrado', 'basura', 'ruidos', 'servicios_basicos', 'vias_transporte'];

$usuarioId   = (int)$_SESSION['usuario']['id'];
$nombre      = trim($_SESSION['usuario']['nombre'] . ' ' . $_SESSION['usuario']['apellido']);
$tipo        = $_POST['tipo'] ?? '';
$descripcion = trim($_POST['descripcion'] ?? '');
$direccion   = trim($_POST['direccion'] ?? '');

if ($descripcion === '' || $direccion === '') {
    responder(false, 'Completa todos los campos obligatorios.', 422);
}
if (!in_array($tipo, $tiposValidos, true)) {
    responder(false, 'Tipo de denuncia no válido.', 422);
}

// 1. Guardar el reporte
$stmt = $conexion->prepare(
    "INSERT INTO reportes (usuario_id, nombre, tipo, descripcion, direccion) VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param('issss', $usuarioId, $nombre, $tipo, $descripcion, $direccion);

if (!$stmt->execute()) {
    responder(false, 'Error al guardar la denuncia.', 500);
}
$reporteId = $conexion->insert_id;

// 2. Guardar fotos y videos
$permitidos = [
    'image/jpeg' => ['jpg',  'imagen'],
    'image/png'  => ['png',  'imagen'],
    'image/webp' => ['webp', 'imagen'],
    'video/mp4'  => ['mp4',  'video'],
    'video/webm' => ['webm', 'video'],
];
$maxBytes = 20 * 1024 * 1024;
$omitidos = 0;

if (!empty($_FILES['evidencias']['name'][0])) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $total = min(count($_FILES['evidencias']['name']), 5);

    for ($i = 0; $i < $total; $i++) {
        $tmp = $_FILES['evidencias']['tmp_name'][$i];

        if ($_FILES['evidencias']['error'][$i] !== UPLOAD_ERR_OK
            || $_FILES['evidencias']['size'][$i] > $maxBytes) {
            $omitidos++;
            continue;
        }

        $mime = $finfo->file($tmp);
        if (!isset($permitidos[$mime])) {
            $omitidos++;
            continue;
        }

        [$ext, $categoria] = $permitidos[$mime];
        $nuevoNombre = bin2hex(random_bytes(16)) . '.' . $ext;

        if (move_uploaded_file($tmp, __DIR__ . '/../uploads/' . $nuevoNombre)) {
            $ins = $conexion->prepare("INSERT INTO evidencias (reporte_id, archivo, tipo) VALUES (?, ?, ?)");
            $ins->bind_param('iss', $reporteId, $nuevoNombre, $categoria);
            $ins->execute();
        } else {
            $omitidos++;
        }
    }
}

$msg = 'Denuncia enviada correctamente. Puedes ver su estado en "Mis denuncias".';
if ($omitidos > 0) {
    $msg .= " ($omitidos archivo(s) no se subieron por tipo o tamaño no permitido.)";
}
responder(true, $msg);