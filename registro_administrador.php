<?php
require __DIR__ . '/incluides/autenticacion.php';
require __DIR__ . '/incluides/conexion.php';
require '/configuracion_administrador.php';
$conexion = $conexion ?? $conn ?? null;

if (!($conexion instanceof mysqli)) {
    http_response_code(500);
    exit('No se pudo conectar con la base de datos.');
}

$hayAdmin = (int)$conexion->query("SELECT COUNT(*) FROM usuario WHERE rol = 'admin'")->fetch_row()[0] > 0;
$error = '';
$v = ['nombre' => '', 'apellido' => '', 'correo' => '', 'direccion' => ''];

if (!$hayAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $v['nombre']    = trim((string)($_POST['nombre'] ?? ''));
    $v['apellido']  = trim((string)($_POST['apellido'] ?? ''));
    $v['correo']    = trim((string)($_POST['correo'] ?? ''));
    $v['direccion'] = trim((string)($_POST['direccion'] ?? ''));
    $clave          = (string)($_POST['contrasena'] ?? '');
    $confirmar      = (string)($_POST['confirmar'] ?? '');
    $codigo         = (string)($_POST['codigo'] ?? '');

    if (CODIGO_ADMIN === 'CAMBIA_ESTE_CODIGO') {
        $error = 'Primero cambia el código en configuracion_administrador.php.';
    } elseif (!hash_equals(CODIGO_ADMIN, $codigo)) {
        $error = 'Código de autorización incorrecto.';
    } elseif ($v['nombre'] === '' || $v['apellido'] === '') {
        $error = 'Completa nombre y apellido.';
    } elseif (!filter_var($v['correo'], FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo no tiene un formato válido.';
    } elseif (strlen($clave) < 8) {
        $error = 'La contraseña debe tener mínimo 8 caracteres.';
    } elseif ($clave !== $confirmar) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $chk = $conexion->prepare("SELECT id FROM usuario WHERE correo = ?");
        $chk->bind_param('s', $v['correo']);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $error = 'Ya existe una cuenta con ese correo.';
        } else {
            try {
                $hash = md5($clave);
                $ins = $conexion->prepare(
                    "INSERT INTO usuario (nombre, apellido, correo, contrasena, direccion, rol)
                     VALUES (?, ?, ?, ?, ?, 'admin')"
                );
                $ins->bind_param('sssss', $v['nombre'], $v['apellido'], $v['correo'], $hash, $v['direccion']);
                $ins->execute();
                header('Location: login_administrador.php?registrado=1');
                exit;
            } catch (Throwable $e) {
                error_log('[registro_administrador] ' . $e->getMessage());
                $error = 'No se pudo crear el administrador.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registrar administrador · Denuncia Digital</title>
  <link rel="stylesheet" href="style_acceso_administrador.css">
</head>
<body>
  <a class="esquina" href="login_administrador.php">Acceso administración</a>

  <main class="acceso">
    <?php if ($hayAdmin): ?>
      <section class="tarjeta">
        <h1>Registro cerrado</h1>
        <p class="sub">Ya existe un administrador. Solo puede haber uno.</p>
        <a class="boton" href="login_administrador.php">Ir al acceso de administración</a>
      </section>
    <?php else: ?>
      <form class="tarjeta" method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">

        <h1>Registrar administrador</h1>
        <p class="sub">Solo se puede hacer una vez.</p>

        <?php if ($error): ?><p class="aviso error"><?= e($error) ?></p><?php endif; ?>

        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" value="<?= e($v['nombre']) ?>" required>

        <label for="apellido">Apellido</label>
        <input type="text" id="apellido" name="apellido" value="<?= e($v['apellido']) ?>" required>

        <label for="correo">Correo</label>
        <input type="email" id="correo" name="correo" value="<?= e($v['correo']) ?>" required>

        <label for="direccion">Dirección</label>
        <input type="text" id="direccion" name="direccion" value="<?= e($v['direccion']) ?>">

        <label for="contrasena">Contraseña (mín. 8)</label>
        <input type="password" id="contrasena" name="contrasena" minlength="8" autocomplete="new-password" required>

        <label for="confirmar">Confirmar contraseña</label>
        <input type="password" id="confirmar" name="confirmar" autocomplete="new-password" required>

        <label for="codigo">Código de autorización</label>
        <input type="password" id="codigo" name="codigo" autocomplete="off" required>

        <button type="submit">Crear administrador</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>