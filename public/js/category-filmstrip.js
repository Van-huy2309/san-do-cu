(() => {
  const stage = document.querySelector("#category-filmstrip-stage");
  const deck = document.querySelector("#category-filmstrip-deck");
  const picked = document.querySelector("#category-filmstrip-picked");
  const host = stage?.closest(".category-filmstrip-host");
  if (!stage || !deck) return;

  const cards = Array.from(deck.querySelectorAll(".card"));
  const count = cards.length;
  if (!count) return;

  const reducedMotion = matchMedia("(prefers-reduced-motion: reduce)").matches;
  const startIndex = Math.min(2, count - 1);
  const state = {
    phase: startIndex,
    target: startIndex,
    base: startIndex,
    pointerX: 0,
    pointerY: 0,
    active: false,
    lastInput: performance.now(),
  };

  let visible = false;
  let running = false;
  let previousTime = performance.now();
  let selectedIndex = startIndex;
  let forceCompact = null;

  function isCompact() {
    return forceCompact === null ? innerWidth < 650 : forceCompact;
  }

  function syncOrientation() {
    const compact = isCompact();
    host?.classList.toggle("is-vertical", compact);
    stage.dataset.orientation = compact ? "vertical" : "horizontal";
    stage.style.cursor = compact ? "ns-resize" : "ew-resize";
  }

  function wrappedDelta(index, phase) {
    let delta = index - phase;
    while (delta > count / 2) delta -= count;
    while (delta < -count / 2) delta += count;
    return delta;
  }

  function nearestIndex() {
    return (((Math.round(state.phase) % count) + count) % count);
  }

  function showPicked(index) {
    if (!picked) return;
    const card = cards[index];
    if (!card) return;
    selectedIndex = index;
    picked.hidden = false;
    const icon = picked.querySelector("[data-picked-icon]");
    const name = picked.querySelector("[data-picked-name]");
    const desc = picked.querySelector("[data-picked-desc]");
    const link = picked.querySelector("[data-picked-link]");
    if (icon) icon.textContent = card.dataset.icon || "";
    if (name) name.textContent = card.dataset.name || "";
    if (desc) desc.textContent = card.dataset.price || "";
    if (link && card.dataset.url) link.setAttribute("href", card.dataset.url);
  }

  let pointerDown = false;
  let dragMoved = false;
  let downX = 0;
  let downY = 0;

  function pointerFromEvent(event) {
    const rect = stage.getBoundingClientRect();
    return {
      nx: Math.max(-1, Math.min(1, ((event.clientX - rect.left) / rect.width - 0.5) * 2)),
      ny: Math.max(-1, Math.min(1, ((event.clientY - rect.top) / rect.height - 0.5) * 2)),
    };
  }

  stage.addEventListener("pointerdown", (event) => {
    if (event.button !== 0) return;
    pointerDown = true;
    dragMoved = false;
    downX = event.clientX;
    downY = event.clientY;
    stage.setPointerCapture(event.pointerId);
    state.lastInput = performance.now();
  });

  stage.addEventListener("pointermove", (event) => {
    if (!pointerDown) return;
    const { nx, ny } = pointerFromEvent(event);
    if (Math.hypot(event.clientX - downX, event.clientY - downY) > 6) {
      dragMoved = true;
      state.pointerX = nx;
      state.pointerY = ny;
      state.active = true;
      state.target = state.base + (isCompact() ? ny * 2.2 : nx * 3.1);
      state.lastInput = performance.now();
      stage.style.setProperty("--pointer-x", `${(nx + 1) * 50}%`);
    }
  });

  stage.addEventListener("pointerup", (event) => {
    if (!pointerDown) return;
    pointerDown = false;
    state.active = false;
    if (stage.hasPointerCapture(event.pointerId)) {
      stage.releasePointerCapture(event.pointerId);
    }
    if (!dragMoved) {
      const compact = isCompact();
      const rect = stage.getBoundingClientRect();
      const mid = compact ? rect.top + rect.height / 2 : rect.left + rect.width / 2;
      const pos = compact ? event.clientY : event.clientX;
      state.base += pos > mid ? 1 : -1;
      state.target = state.base;
    } else {
      state.base = nearestIndex();
      state.target = state.base;
    }
    state.pointerX = 0;
    state.pointerY = 0;
    stage.style.setProperty("--pointer-x", "50%");
    state.lastInput = performance.now();
  });

  stage.addEventListener("pointercancel", () => {
    pointerDown = false;
    dragMoved = false;
    state.active = false;
    state.pointerX = 0;
    state.pointerY = 0;
    state.target = state.base;
    stage.style.setProperty("--pointer-x", "50%");
  });

  stage.addEventListener("dblclick", (event) => {
    event.preventDefault();
    forceCompact = !isCompact();
    syncOrientation();
    state.lastInput = performance.now();
  });

  addEventListener("keydown", (event) => {
    if (!visible || !stage.matches(":hover, :focus-within")) return;
    const forward = event.key === "ArrowRight" || event.key === "ArrowDown";
    const backward = event.key === "ArrowLeft" || event.key === "ArrowUp";
    if (!forward && !backward) return;
    event.preventDefault();
    state.base += forward ? 1 : -1;
    state.target = state.base;
    state.active = false;
    state.lastInput = performance.now();
  });

  function render(time) {
    const deltaTime = Math.min(32, time - previousTime);
    previousTime = time;
    const ease = reducedMotion ? 1 : 1 - Math.pow(0.001, deltaTime / 1000);

    if (!state.active && time - state.lastInput > 3600) {
      const idle = time - state.lastInput - 3600;
      state.target = state.base + Math.sin(idle * 0.00042) * 2.45;
    }

    state.phase += (state.target - state.phase) * ease;
    const compact = isCompact();
    const activeIndex = nearestIndex();
    if (activeIndex !== selectedIndex) showPicked(activeIndex);

    const horizontalSpacing = Math.min(168, Math.max(112, innerWidth * 0.116));
    const verticalSpacing = Math.min(122, Math.max(88, innerHeight * 0.112));

    cards.forEach((card, index) => {
      const delta = wrappedDelta(index, state.phase);
      const distance = Math.abs(delta);
      const focus = Math.exp(-distance * distance * 1.28);
      const side = Math.max(0, 1 - distance / 5);
      const direction = Math.sign(delta);
      const x = compact
        ? delta * 24 + Math.sin(delta * 0.9) * 25
        : delta * horizontalSpacing;
      const y = compact
        ? delta * verticalSpacing
        : distance * 8 + state.pointerY * focus * 10;
      const z = focus * 145 - distance * 148;
      const scale = 0.54 + side * 0.15 + focus * 0.54;
      const rotateX = compact ? delta * 2.1 : -state.pointerY * focus * 3.5;
      const rotateY = compact
        ? -delta * 5
        : -direction * (distance > 0.2 ? 14 + Math.min(distance, 3) * 5 : 0) + state.pointerX * focus * 3;
      const rotateZ = compact ? delta * -1.4 : delta * 0.7;
      const far = distance > 2.4;

      card.style.setProperty("--focus", focus.toFixed(4));
      card.style.zIndex = String(Math.round(1000 - distance * 100));
      card.style.opacity = String(Math.max(0.13, side * 0.76 + focus * 0.24));
      card.style.filter = (!reducedMotion && distance > 1.6 && distance < 3.4)
        ? `blur(${Math.max(0, distance - 1.5) * 0.38}px)`
        : "none";
      card.style.transform = [
        "translate(-50%, -50%)",
        `translate3d(${x.toFixed(2)}px, ${y.toFixed(2)}px, ${z.toFixed(2)}px)`,
        `rotateX(${rotateX.toFixed(2)}deg)`,
        `rotateY(${rotateY.toFixed(2)}deg)`,
        `rotateZ(${rotateZ.toFixed(2)}deg)`,
        `scale(${scale.toFixed(4)})`,
      ].join(" ");
      card.style.visibility = far && count > 8 ? "hidden" : "visible";
      card.setAttribute("aria-current", index === activeIndex ? "true" : "false");
    });
  }

  function tick(time) {
    if (!visible || document.hidden) {
      running = false;
      return;
    }
    render(time);
    requestAnimationFrame(tick);
  }

  function start() {
    if (running) return;
    running = true;
    previousTime = performance.now();
    requestAnimationFrame(tick);
  }

  if (typeof IntersectionObserver !== "undefined") {
    const observer = new IntersectionObserver(([entry]) => {
      visible = entry?.isIntersecting ?? true;
      if (visible) start();
    }, { threshold: 0.08, rootMargin: "80px 0px" });
    observer.observe(stage);
  } else {
    visible = true;
    start();
  }

  document.addEventListener("visibilitychange", () => {
    if (!document.hidden && visible) start();
  });

  addEventListener("resize", () => {
    if (forceCompact === null) syncOrientation();
  });

  syncOrientation();
  showPicked(startIndex);
})();
