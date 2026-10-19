const form = document.getElementById('formReporte');
const btn = document.getElementById('btnEnviar');
const modal = document.getElementById('resultado');
const resIcono = document.getElementById('resIcono');
const resTitulo = document.getElementById('resTitulo');
const resTexto = document.getElementById('resTexto');
const resCerrar = document.getElementById('resCerrar');

function mostrar(ok, titulo, texto) {
  modal.className = 'resultado-fondo ' + (ok ? 'ok' : 'error');
  resIcono.textContent = ok ? '✓' : '!';
  resTitulo.textContent = titulo;
  resTexto.textContent = texto;
  modal.hidden = false;
  resCerrar.focus();
}

resCerrar.addEventListener('click', () => { modal.hidden = true; });
modal.addEventListener('click', (e) => { if (e.target === modal) modal.hidden = true; });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') modal.hidden = true; });

form.addEventListener('submit', async (e) => {
  e.preventDefault();

  if (document.getElementById('evidencias').files.length > 5) {
    mostrar(false, 'Demasiados archivos', 'Puedes adjuntar un máximo de 5 archivos.');
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Enviando...';

  try {
    const respuesta = await fetch('guardar_reporte.php', {
      method: 'POST',
      body: new FormData(form),
      credentials: 'same-origin'
    });
    const texto = await respuesta.text();

    let datos;
    try { datos = JSON.parse(texto); }
    catch (_) {
      console.error('Respuesta del servidor:', texto);
      mostrar(false, 'Respuesta inesperada', 'El servidor devolvió un error. Revisa F12 → Consola.');
      return;
    }

    if (datos.ok) {
      mostrar(true, '¡Denuncia enviada!', datos.mensaje);
      form.reset();
    } else {
      mostrar(false, 'No se pudo enviar', datos.mensaje);
    }
  } catch (error) {
    mostrar(false, 'Sin conexión', 'No se pudo conectar con el servidor.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Enviar denuncia';
  }
});