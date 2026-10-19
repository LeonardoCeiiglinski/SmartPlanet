(function () {
  var top = document.querySelector("[data-top]");
  var bar = document.querySelector("[data-progress]");
  var toggle = document.querySelector(".nav-toggle");
  var menu = document.querySelector(".menu");
  var form = document.querySelector("[data-zap]");
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  if (bar) bar.hidden = false;

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
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && menu.classList.contains("is-open")) {
        menu.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
        toggle.focus();
      }
    });
  }

  if ("IntersectionObserver" in window && !reduce) {
    var seen = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("in");
        seen.unobserve(entry.target);
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -6% 0px" });
    document.querySelectorAll("[data-reveal]").forEach(function (el) { seen.observe(el); });
  } else {
    document.querySelectorAll("[data-reveal]").forEach(function (el) { el.classList.add("in"); });
  }

  function field(id) {
    var el = form.querySelector("#" + id);
    return el && el.value ? el.value.trim() : "";
  }

  if (form) {
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      if (!form.reportValidity()) return;
      var text = "Oi, vim pelo site da SmartPlanet.";
      var nome = field("nome");
      var modelo = field("modelo");
      var acessorio = field("acessorio");
      var bairro = field("bairro");
      if (nome) text += " Meu nome é " + nome + ".";
      if (modelo) text += " Celular: " + modelo + ".";
      if (acessorio) text += " Acessório: " + acessorio + ".";
      if (bairro) text += " Bairro: " + bairro + ".";
      window.location.href = "https://wa.me/5548996628811?text=" + encodeURIComponent(text);
    });
  }
})();
