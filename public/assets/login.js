(function () {
  document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.getAttribute('data-toggle-password'));
      if (!input) return;
      var visible = input.type === 'text';
      input.type = visible ? 'password' : 'text';
      button.textContent = visible ? 'Mostrar' : 'Ocultar';
    });
  });

  document.querySelectorAll('form[data-vault]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var button = form.querySelector('[type="submit"]');
      if (!button) return;
      button.setAttribute('aria-busy', 'true');
      button.textContent = 'Verificando…';
      window.setTimeout(function () { button.disabled = true; }, 0);
    });
  });
})();
