<?php
$aut = __DIR__ . '/../incluides/autenticacion.php';
if (!is_file($aut)) { $aut = __DIR__ . '/../includes/auth.php'; }
require $aut;
require __DIR__ . '/../incluides/conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['usuario']['id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$db = $conexion ?? $conn ?? null;
$uid = (int)$_SESSION['usuario']['id'];

/* ---- Marcar como leídas (POST) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(419);
        echo json_encode(['ok' => false]);
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        $st = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND usuario_id = ?");
        $st->bind_param('ii', $id, $uid);
    } else {
        $st = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE usuario_id = ?");
        $st->bind_param('i', $uid);
    }
    $st->execute();
    echo json_encode(['ok' => true]);
    exit;
}

/* ---- Listar (GET) ---- */
$st = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0");
$st->bind_param('i', $uid);
$st->execute();
$noLeidas = (int)$st->get_result()->fetch_row()[0];

$st = $db->prepare("SELECT id, reporte_id, mensaje, leida, fecha
                    FROM notificaciones WHERE usuario_id = ? ORDER BY fecha DESC LIMIT 15");
$st->bind_param('i', $uid);
$st->execute();
$res = $st->get_result();

$items = [];
while ($f = $res->fetch_assoc()) {
    $s = max(0, time() - strtotime($f['fecha']));
    $cuando = $s < 60 ? 'Hace un momento'
            : ($s < 3600 ? 'Hace ' . floor($s / 60) . ' min'
            : ($s < 86400 ? 'Hace ' . floor($s / 3600) . ' h'
            : 'Hace ' . floor($s / 86400) . ' días'));
    $items[] = [
        'id'      => (int)$f['id'],
        'reporte' => (int)$f['reporte_id'],
        'mensaje' => $f['mensaje'],
        'leida'   => (bool)$f['leida'],
        'cuando'  => $cuando,
    ];
}

echo json_encode([
    'ok'        => true,
    'no_leidas' => $noLeidas,
    'items'     => $items,
    'csrf'      => $_SESSION['csrf'],
], JSON_UNESCAPED_UNICODE);