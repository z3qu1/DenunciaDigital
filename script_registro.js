/* script_registro.js | Lógica del formulario de registro */
(() => {
  const form    = document.getElementById('registerForm');
  const btn     = document.getElementById('submitBtn');
  const btnText = btn.querySelector('.btn-text');
  const toasts  = document.getElementById('toasts');
  const meter   = document.getElementById('meter');
  const rules   = document.getElementById('rules');
  const $       = (id) => document.getElementById(id);

  const EMAIL_RE  = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  const NAME_RE   = /^\p{L}[\p{L}\s'.-]{1,59}$/u;

  /* ---------- Notificaciones ---------- */
  const ICONS = {
    success: '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16 9.8"/>',
    error:   '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5"/><path d="M12 16.4v.1"/>',
    info:    '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><path d="M12 7.6v.1"/>'
  };
  function removeToast(t) {
    if (!t || t.dataset.closing) return;
    t.dataset.closing = '1';
    t.classList.add('out');
    setTimeout(() => t.remove(), 260);
  }
  function toast(msg, type = 'info', ms = 5000) {
    [...toasts.children].forEach(el => { if (el.dataset.msg === msg) el.remove(); });
    while (toasts.children.length >= 3) toasts.firstElementChild.remove();
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.dataset.msg = msg;
    t.setAttribute('role', type === 'error' ? 'alert' : 'status');
    t.innerHTML =
      '<svg class="toast-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[type] || ICONS.info) + '</svg>' +
      '<div class="toast-msg"></div>' +
      '<button type="button" class="toast-close" aria-label="Cerrar notificación"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button>' +
      '<span class="toast-bar"></span>';
    t.querySelector('.toast-msg').textContent = msg;
    t.querySelector('.toast-bar').style.animationDuration = ms + 'ms';
    t.querySelector('.toast-close').addEventListener('click', () => removeToast(t));
    t.querySelector('.toast-bar').addEventListener('animationend', () => removeToast(t));
    toasts.appendChild(t);
  }

  /* ---------- Estado visual de cada campo ---------- */
  function setState(id, ok, msg) {
    const input = $(id);
    const field = input.closest('.field');
    field.classList.toggle('invalid', ok === false);
    field.classList.toggle('valid', ok === true);
    input.setAttribute('aria-invalid', ok === false ? 'true' : 'false');
    $(id + 'Hint').textContent = ok === false ? msg : '';
  }

  /* ---------- Validaciones por campo ---------- */
  const validators = {
    nombre: (v) => !v ? 'Ingresa tu nombre.' : (!NAME_RE.test(v) ? 'Usa solo letras (mínimo 2).' : ''),
    apellido: (v) => !v ? 'Ingresa tu apellido.' : (!NAME_RE.test(v) ? 'Usa solo letras (mínimo 2).' : ''),
    correo: (v) => !v ? 'Ingresa tu correo electrónico.' : (!EMAIL_RE.test(v) ? 'Escribe un correo válido, por ejemplo nombre@dominio.com.' : ''),
    direccion: (v) => !v ? 'Ingresa tu dirección.' : (v.length < 5 ? 'La dirección es muy corta (mínimo 5 caracteres).' : ''),
    contrasena: (v) => {
      if (!v) return 'Crea una contraseña.';
      if (v.length < 8) return 'Debe tener al menos 8 caracteres.';
      if (!/[A-Za-z]/.test(v)) return 'Agrega al menos una letra.';
      if (!/\d/.test(v)) return 'Agrega al menos un número.';
      return '';
    },
    confirmar: (v) => !v ? 'Repite tu contraseña.' : (v !== $('contrasena').value ? 'Las contraseñas no coinciden.' : '')
  };
  const order = ['nombre', 'apellido', 'correo', 'direccion', 'contrasena', 'confirmar'];

  function check(id, showEmpty = false) {
    const raw = $(id).value;
    const v = (id === 'contrasena' || id === 'confirmar') ? raw : raw.trim();
    if (!v && !showEmpty) { setState(id, null, ''); return false; }
    const err = validators[id](v);
    setState(id, err ? false : true, err);
    return !err;
  }

  order.forEach((id) => {
    const el = $(id);
    el.addEventListener('blur', () => { if (el.value) check(id); });
    el.addEventListener('input', () => {
      if (el.closest('.field').classList.contains('invalid')) check(id);
      if (id === 'contrasena') {
        updateStrength();
        if ($('confirmar').value) check('confirmar');
      }
    });
  });

  /* ---------- Medidor y requisitos de la contraseña ---------- */
  function updateStrength() {
    const v = $('contrasena').value;
    const has = { len: v.length >= 8, letter: /[A-Za-z]/.test(v), num: /\d/.test(v) };
    rules.querySelectorAll('li').forEach(li => li.classList.toggle('ok', has[li.dataset.rule]));
    let s = 0;
    if (v.length >= 6) s++;
    if (v.length >= 10) s++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
    if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
    meter.classList.toggle('show', v.length > 0);
    meter.dataset.level = Math.min(s, 4);
  }

  /* ---------- Mostrar / ocultar contraseñas ---------- */
  document.querySelectorAll('.eye[data-toggle]').forEach((b) => {
    b.addEventListener('click', () => {
      const input = $(b.dataset.toggle);
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      b.classList.toggle('on', show);
      b.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
      input.focus();
    });
  });

  /* ---------- Utilidades ---------- */
  function setLoading(on) { btn.disabled = on; btn.classList.toggle('loading', on); }
  function shake() {
    form.classList.remove('shake');
    void form.offsetWidth;
    form.classList.add('shake');
  }

  /* ---------- Envío ---------- */
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (btn.disabled) return;

    let firstBad = null;
    order.forEach((id) => { if (!check(id, true) && !firstBad) firstBad = id; });
    if (firstBad) {
      shake();
      $(firstBad).focus();
      toast('Revisa los campos marcados.', 'error');
      return;
    }

    setLoading(true);
    try {
      const res = await fetch(form.getAttribute('action') || 'registro.php', {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
      });

      const raw = await res.text();
      let data = null;
      try { data = JSON.parse(raw); } catch (_) { /* no es JSON */ }

      if (!data) {
        console.error('Respuesta inesperada del servidor:', raw);
        toast('El servidor devolvió una respuesta inesperada. Revisa conexion.php y los errores de PHP.', 'error', 7000);
        setLoading(false);
        return;
      }

      if (data.ok) {
        toast(data.mensaje || 'Cuenta creada.', 'success', 3000);
        btn.classList.add('loading');
        setTimeout(() => { window.location.href = data.redirigir || 'login.php'; }, 1600);
        return;
      }

      setLoading(false);
      shake();
      toast(data.mensaje || 'No se pudo crear la cuenta.', 'error', 6000);

      if (data.campo && $(data.campo)) {
        setState(data.campo, false, data.mensaje);
        $(data.campo).focus();
      }
      if (res.status === 419) setTimeout(() => window.location.reload(), 2200);
    } catch (err) {
      console.error(err);
      setLoading(false);
      toast('No se pudo conectar con el servidor. Revisa tu conexión.', 'error');
    }
  });
})();