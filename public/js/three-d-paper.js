(function () {
  const host = document.querySelector(".three-d-paper[data-variant]");
  if (!host) return;

  const src = host.getAttribute("data-src");
  const title = host.getAttribute("data-title") || "3D Paper";
  if (!src) return;

  let documentVisible = typeof document === "undefined" || !document.hidden;
  let hostVisible = true;
  let ready = false;
  let frame = null;

  function setState() {
    const mounted = hostVisible && documentVisible;
    host.dataset.state = !mounted ? "paused" : ready ? "ready" : "loading";
  }

  function mount() {
    if (frame || !src) return;
    ready = false;
    setState();
    frame = document.createElement("iframe");
    frame.title = title;
    frame.setAttribute("sandbox", "allow-scripts");
    frame.setAttribute("loading", "eager");
    frame.src = src;
    frame.style.cssText = [
      "position:absolute",
      "inset:0",
      "display:block",
      "width:100%",
      "height:100%",
      "border:0",
      "background:#eef7ff",
      "opacity:0",
      "pointer-events:none",
      "transition:opacity 240ms ease-out",
    ].join(";");
    frame.addEventListener("load", function () {
      ready = true;
      if (!frame) return;
      frame.style.opacity = "1";
      frame.style.pointerEvents = "auto";
      setState();
      pushCopy();
    });
    host.appendChild(frame);
  }

  function unmount() {
    if (!frame) return;
    frame.remove();
    frame = null;
    ready = false;
    setState();
  }

  function sync() {
    if (hostVisible && documentVisible) mount();
    else unmount();
  }

  Object.assign(host.style, {
    position: "relative",
    overflow: "hidden",
    background: "#eef7ff",
    pointerEvents: "auto",
  });

  if (typeof IntersectionObserver !== "undefined") {
    const observer = new IntersectionObserver(
      function (entries) {
        hostVisible = entries[0] ? entries[0].isIntersecting : true;
        sync();
      },
      { rootMargin: "80px" }
    );
    observer.observe(host);
  } else {
    mount();
  }

  document.addEventListener("visibilitychange", function () {
    documentVisible = !document.hidden;
    sync();
  });

  function currentName() {
    const typed = document.getElementById("name");
    if (typed && typed.value.trim()) return typed.value.trim();
    return host.getAttribute("data-name") || "";
  }

  function currentEmail() {
    const typed = document.getElementById("email");
    if (typed && typed.value.trim()) return typed.value.trim();
    return host.getAttribute("data-email") || "";
  }

  function pushCopy() {
    if (!frame || !frame.contentWindow) return;
    frame.contentWindow.postMessage({
      type: "relic-paper",
      mode: host.getAttribute("data-mode") || "login",
      name: currentName(),
      email: currentEmail(),
    }, "*");
  }

  ["name", "email"].forEach(function (id) {
    const input = document.getElementById(id);
    if (input) input.addEventListener("input", pushCopy);
  });
})();
