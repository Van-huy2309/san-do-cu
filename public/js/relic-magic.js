(function () {
  function wordRotate(root) {
    const words = root.querySelectorAll('[data-word]');
    if (!words.length) return;
    let i = 0;
    words[0].classList.add('is-on');
    setInterval(() => {
      words[i].classList.remove('is-on');
      i = (i + 1) % words.length;
      words[i].classList.add('is-on');
    }, 2200);
  }

  function numberTicker(el) {
    const target = Number(el.getAttribute('data-to') || 0);
    const duration = Number(el.getAttribute('data-duration') || 1200);
    const start = performance.now();
    const from = 0;
    function frame(now) {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - t, 3);
      const value = Math.round(from + (target - from) * eased);
      el.textContent = value.toLocaleString('vi-VN');
      if (t < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  function blurFade() {
    const nodes = document.querySelectorAll('.blur-fade');
    if (!nodes.length) return;
    if (!('IntersectionObserver' in window)) {
      nodes.forEach((n) => n.classList.add('is-in'));
      return;
    }
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            e.target.classList.add('is-in');
            io.unobserve(e.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
    );
    nodes.forEach((n) => io.observe(n));
  }

  function magicCards() {
    document.querySelectorAll('.magic-card').forEach((card) => {
      card.addEventListener('pointermove', (e) => {
        const r = card.getBoundingClientRect();
        card.style.setProperty('--mx', e.clientX - r.left + 'px');
        card.style.setProperty('--my', e.clientY - r.top + 'px');
      });
    });
  }

  document.querySelectorAll('[data-word-rotate]').forEach(wordRotate);
  document.querySelectorAll('[data-ticker]').forEach(numberTicker);
  blurFade();
  magicCards();
})();
