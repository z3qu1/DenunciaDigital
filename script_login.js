(() => {
  'use strict';

  // Credenciales de DEMOSTRACIÓN. Reemplaza authenticate() por tu llamada real al backend.
  const DEMO = { email: 'demo@denunciadigital.com', password: 'Denuncia123' };
  const MAX_TRIES = 3;
  let tries = 0, lockedUntil = 0;

  const $ = (id) => document.getElementById(id);
  const form = $('loginForm'), email = $('email'), pass = $('password');
  const btn = $('submitBtn'), meter = $('meter');
  const emailField = email.closest('.field'), passField = pass.closest('.field');
  const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  /* ---------- Alertas dinámicas ---------- */
  const ICONS = { success: '✓', error: '!', warning: '▲', info: 'i' };
  function toast(type, title, msg = '', ms = 4500) {
    const box = $('toasts');
    while (box.children.length >= 4) box.firstChild.remove();
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = `<div class="t-icon">${ICONS[type]}</div>
      <div class="t-body"><strong></strong><span></span></div>
      <button class="t-close" aria-label="Cerrar">&times;</button>
      <div class="t-bar" style="animation-duration:${ms}ms"></div>`;
    el.querySelector('strong').textContent = title;
    el.querySelector('.t-body span').textContent = msg;
    const close = () => { el.classList.add('out'); setTimeout(() => el.remove(), 350); };
    el.querySelector('.t-close').onclick = close;
    el.querySelector('.t-bar').addEventListener('animationend', close);
    box.appendChild(el);
  }

  /* ---------- Validación en vivo ---------- */
  function setState(field, hintEl, ok, msg) {
    field.classList.toggle('error', !ok);
    field.classList.toggle('valid', ok);
    if (hintEl) hintEl.textContent = ok ? '' : msg;
  }
  function checkEmail(showEmpty) {
    const v = email.value.trim();
    if (!v) { if (showEmpty) setState(emailField, $('emailHint'), false, 'Escribe tu correo electrónico.'); else emailField.classList.remove('error', 'valid'); return false; }
    const ok = emailRx.test(v);
    setState(emailField, $('emailHint'), ok, 'Ingresa un correo válido, por ejemplo nombre@dominio.com.');
    return ok;
  }
  function strength(v) {
    let s = 0;
    if (v.length >= 8) s++;
    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
    if (/\d/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) s++;
    return v ? Math.max(1, s) : 0;
  }
  function checkPass(showEmpty) {
    const v = pass.value;
    meter.dataset.s = strength(v);
    if (!v) { if (showEmpty) setState(passField, $('passHint'), false, 'Escribe tu contraseña.'); else passField.classList.remove('error', 'valid'); return false; }
    const ok = v.length >= 8;
    setState(passField, $('passHint'), ok, 'La contraseña debe tener al menos 8 caracteres.');
    return ok;
  }
  email.addEventListener('input', () => checkEmail(false));
  email.addEventListener('blur', () => email.value && checkEmail(true));
  pass.addEventListener('input', () => checkPass(false));

  /* ---------- Mostrar/ocultar contraseña y bloq mayús ---------- */
  $('togglePass').addEventListener('click', (e) => {
    const show = pass.type === 'password';
    pass.type = show ? 'text' : 'password';
    e.currentTarget.classList.toggle('on', show);
    e.currentTarget.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
    pass.focus();
  });
  let capsShown = false;
  pass.addEventListener('keyup', (e) => {
    const caps = e.getModifierState && e.getModifierState('CapsLock');
    if (caps && !capsShown) toast('warning', 'Bloq Mayús activado', 'Tu contraseña distingue mayúsculas de minúsculas.', 3500);
    capsShown = caps;
  });

  /* ---------- Envío ---------- */
  const wait = (ms) => new Promise((r) => setTimeout(r, ms));
  async function authenticate(mail, pw) {
    await wait(1400); // simula la petición al servidor
    return mail.toLowerCase() === DEMO.email && pw === DEMO.password;
  }
  function fail() {
    form.classList.remove('shake'); void form.offsetWidth; form.classList.add('shake');
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (Date.now() < lockedUntil) {
      const s = Math.ceil((lockedUntil - Date.now()) / 1000);
      return toast('error', 'Acceso bloqueado', `Demasiados intentos. Espera ${s} segundos.`);
    }
    const okE = checkEmail(true), okP = checkPass(true);
    if (!okE || !okP) {
      fail();
      return toast('error', 'Revisa tus datos', 'Corrige los campos marcados para continuar.');
    }
    if (!navigator.onLine) return toast('warning', 'Sin conexión', 'Conéctate a internet e inténtalo de nuevo.');

    btn.classList.add('loading'); btn.disabled = true;
    try {
      const ok = await authenticate(email.value.trim(), pass.value);
      if (ok) {
        btn.classList.remove('loading'); btn.classList.add('success');
        btn.querySelector('.btn-text').textContent = '¡Bienvenido!';
        toast('success', 'Sesión iniciada', 'Redirigiendo a tu panel…', 2500);
        try { $('remember').checked ? localStorage.setItem('dd_email', email.value.trim()) : localStorage.removeItem('dd_email'); } catch (_) {}
        // setTimeout(() => location.href = 'panel.html', 1800);
        await wait(2600);
        btn.classList.remove('success'); btn.disabled = false;
        btn.querySelector('.btn-text').textContent = 'Iniciar sesión';
        return;
      }
      tries++; fail();
      pass.value = ''; checkPass(false); passField.classList.add('error');
      $('passHint').textContent = 'Correo o contraseña incorrectos.';
      if (tries >= MAX_TRIES) {
        lockedUntil = Date.now() + 30000; tries = 0;
        toast('error', 'Acceso bloqueado', 'Demasiados intentos. Espera 30 segundos.', 6000);
      } else {
        toast('error', 'No pudimos iniciar sesión', `Datos incorrectos. Te quedan ${MAX_TRIES - tries} intento(s).`);
      }
    } catch (_) {
      toast('error', 'Error de conexión', 'No se pudo contactar al servidor. Inténtalo más tarde.');
    }
    btn.classList.remove('loading'); btn.disabled = false;
  });

  /* ---------- Enlaces y arranque ---------- */
  $('forgot').addEventListener('click', (e) => { e.preventDefault();
    if (!emailRx.test(email.value.trim())) { checkEmail(true); email.focus(); return toast('info', 'Escribe tu correo', 'Ingresa tu correo para enviarte el enlace de recuperación.'); }
    toast('success', 'Revisa tu bandeja', `Enviamos instrucciones a ${email.value.trim()}.`);
  });
  $('register').addEventListener('click', (e) => { e.preventDefault(); toast('info', 'Registro', 'Aquí puedes enlazar tu página de registro.'); });

  window.addEventListener('offline', () => toast('warning', 'Sin conexión', 'Perdiste la conexión a internet.'));
  window.addEventListener('online', () => toast('success', 'Conexión restablecida', 'Ya puedes iniciar sesión.', 3000));

  try { const saved = localStorage.getItem('dd_email'); if (saved) { email.value = saved; $('remember').checked = true; checkEmail(false); pass.focus(); toast('info', 'Qué bueno verte de nuevo', 'Solo falta tu contraseña.', 3500); return; } } catch (_) {}
  setTimeout(() => toast('info', 'Bienvenido a Denuncia Digital', 'Modo demo: demo@denunciadigital.com / Denuncia123', 7000), 900);
})();