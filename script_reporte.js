const form = document.getElementById('formReporte');
const mensaje = document.getElementById('mensaje');
const btn = document.getElementById('btnEnviar');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  mensaje.textContent = '';
  mensaje.className = '';

  const archivos = document.getElementById('evidencias').files;
  if (archivos.length > 5) {
    mensaje.textContent = 'Máximo 5 archivos.';
    mensaje.className = 'error';
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Enviando...';

  try {
    const respuesta = await fetch('guardar_reporte.php', {
      method: 'POST',
      body: new FormData(form)
    });
    const datos = await respuesta.json();

    mensaje.textContent = datos.mensaje;
    mensaje.className = datos.ok ? 'ok' : 'error';
    if (datos.ok) form.reset();
  } catch (error) {
    mensaje.textContent = 'No se pudo conectar con el servidor.';
    mensaje.className = 'error';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Enviar denuncia';
  }
});