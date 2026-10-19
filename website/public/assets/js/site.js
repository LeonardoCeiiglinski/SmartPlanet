(function () {
  var top = document.querySelector("[data-top]");
  var bar = document.querySelector("[data-progress]");
  var toggle = document.querySelector(".nav-toggle");
  var menu = document.querySelector(".menu");
  var form = document.querySelector("[data-zap]");
  var cursor = document.querySelector("[data-cursor]");
  var fine = window.matchMedia("(pointer: fine)").matches;
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (bar) bar.hidden = false;
  if (cursor && fine && !reduce) cursor.hidden = false;

  function onScroll() {
    var height = document.documentElement.scrollHeight - window.innerHeight;
    var ratio = height > 0 ? window.scrollY / height : 0;
    if (bar) bar.style.width = Math.min(100, ratio * 100) + "%";
    if (top) top.classList.toggle("is-tight", window.scrollY > 24);
  }
  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });

  if (toggle && menu) {
    toggle.addEventListener("click", function () {
      var open = menu.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    menu.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        menu.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  if ("IntersectionObserver" in window && !reduce) {
    var seen = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("in");
        seen.unobserve(entry.target);
      });
    }, { threshold: 0.18, rootMargin: "0px 0px -8% 0px" });
    document.querySelectorAll("[data-reveal]").forEach(function (el) { seen.observe(el); });
  } else {
    document.querySelectorAll("[data-reveal]").forEach(function (el) { el.classList.add("in"); });
  }

  if (cursor && fine && !reduce) {
    window.addEventListener("pointermove", function (event) {
      cursor.style.transform = "translate(" + event.clientX + "px," + event.clientY + "px)";
    }, { passive: true });
    document.querySelectorAll("a, button, summary").forEach(function (el) {
      el.addEventListener("pointerenter", function () { cursor.classList.add("is-hot"); });
      el.addEventListener("pointerleave", function () { cursor.classList.remove("is-hot"); });
    });
  }

  if (form) {
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      var nome = (form.querySelector("#nome") || {}).value || "";
      var mensagem = (form.querySelector("#mensagem") || {}).value || "";
      var text = "Oi, vim pelo site da SmartPlanet.";
      if (nome.trim()) text += " Meu nome é " + nome.trim() + ".";
      if (mensagem.trim()) text += " " + mensagem.trim();
      window.location.href = "https://wa.me/5548996628811?text=" + encodeURIComponent(text);
    });
  }
})();
