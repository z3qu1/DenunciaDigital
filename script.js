(() => {
  /* ---------- Año del pie de página ---------- */
  const year = document.getElementById('year');
  if (year) year.textContent = new Date().getFullYear();

  /* ---------- Header con sombra al hacer scroll + botón subir ---------- */
  const header = document.getElementById('header');
  const topBtn = document.getElementById('top');

  function onScroll() {
    if (header) header.classList.toggle('scrolled', window.scrollY > 20);
    if (topBtn) topBtn.classList.toggle('show', window.scrollY > 500);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  if (topBtn) {
    topBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }

  /* ---------- Menú hamburguesa ---------- */
  const burger = document.getElementById('burger');
  const nav = document.getElementById('nav');

  if (burger && nav) {
    burger.addEventListener('click', () => {
      const abierto = nav.classList.toggle('open');
      burger.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });
    nav.querySelectorAll('a').forEach((a) => {
      a.addEventListener('click', () => {
        nav.classList.remove('open');
        burger.setAttribute('aria-expanded', 'false');
      });
    });
  }

  /* ---------- Carrusel ---------- */
  const slides = document.querySelectorAll('.slide');
  const dotsBox = document.getElementById('dots');
  const bar = document.getElementById('bar');
  const prev = document.getElementById('prev');
  const next = document.getElementById('next');
  const DURACION = 6000;

  if (slides.length && dotsBox) {
    let actual = 0;
    let timer = null;
    const dots = [];

    slides.forEach((_, i) => {
      const d = document.createElement('button');
      d.setAttribute('aria-label', 'Ir a la diapositiva ' + (i + 1));
      d.addEventListener('click', () => { ir(i); reiniciar(); });
      dotsBox.appendChild(d);
      dots.push(d);
    });

    function animarBarra() {
      if (!bar) return;
      bar.style.transition = 'none';
      bar.style.width = '0';
      void bar.offsetWidth;
      bar.style.transition = 'width ' + DURACION + 'ms linear';
      bar.style.width = '100%';
    }

    function ir(n) {
      slides[actual].classList.remove('active');
      dots[actual].classList.remove('active');
      actual = (n + slides.length) % slides.length;
      slides[actual].classList.add('active');
      dots[actual].classList.add('active');
      animarBarra();
    }

    function reiniciar() {
      clearInterval(timer);
      timer = setInterval(() => ir(actual + 1), DURACION);
    }

    if (prev) prev.addEventListener('click', () => { ir(actual - 1); reiniciar(); });
    if (next) next.addEventListener('click', () => { ir(actual + 1); reiniciar(); });

    dots[0].classList.add('active');
    animarBarra();
    reiniciar();
  }

  /* ---------- Pestañas de la comunidad ---------- */
  const tabs = document.querySelectorAll('.tab');
  const paneles = document.querySelectorAll('.panel');

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      tabs.forEach((t) => t.classList.remove('active'));
      paneles.forEach((p) => p.classList.remove('active'));
      tab.classList.add('active');
      const destino = document.getElementById(tab.dataset.tab);
      if (destino) destino.classList.add('active');
    });
  });

  /* ---------- Aparición al hacer scroll ---------- */
  const reveals = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const obs = new IntersectionObserver((entradas) => {
      entradas.forEach((en) => {
        if (en.isIntersecting) {
          en.target.classList.add('in');
          obs.unobserve(en.target);
        }
      });
    }, { threshold: 0.1 });
    reveals.forEach((el) => obs.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add('in'));
  }

  // Seguro: si algo falla, a los 3 segundos todo se muestra
  setTimeout(() => reveals.forEach((el) => el.classList.add('in')), 3000);
})();