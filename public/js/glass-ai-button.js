document.querySelectorAll(".glass-search-frame").forEach((frame) => {
  const iframe = frame.querySelector("iframe");
  const form = frame.closest("form");
  if (!iframe || !form) return;

  const submit = () => {
    if (form.dataset.submitting === "1") return;
    window.setTimeout(() => {
      if (form.dataset.submitting === "1") return;
      if (!form.reportValidity()) return;
      form.dataset.submitting = "1";
      form.requestSubmit();
    }, 280);
  };

  const setPaused = (paused) => {
    const win = iframe.contentWindow;
    if (!win) return;
    win.postMessage({ type: "relic-glass", paused }, "*");
  };

  iframe.addEventListener("load", () => {
    const doc = iframe.contentDocument;
    const activate = doc && doc.getElementById("activate");
    if (!activate) return;
    frame.dataset.state = "ready";
    setPaused(false);

    let armed = false;
    const arm = () => {
      if (armed || form.dataset.submitting === "1") return;
      armed = true;
      submit();
    };

    activate.addEventListener("pointerdown", (event) => {
      if (event.button !== 0) return;
      arm();
    });
    activate.addEventListener("keydown", (event) => {
      if (event.repeat) return;
      if (event.key === "Enter" || event.key === " ") arm();
    });
  });

  if (typeof IntersectionObserver === "undefined") return;
  const observer = new IntersectionObserver(([entry]) => {
    setPaused(!entry.isIntersecting);
  }, { rootMargin: "120px" });
  observer.observe(frame);
});
