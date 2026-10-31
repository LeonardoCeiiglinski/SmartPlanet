(function () {
  if (!window.SmartPlanetWedge || window.__smartPlanetScanner) return;
  window.__smartPlanetScanner = true;

  var wedge = SmartPlanetWedge.create();
  var snap = null;
  var audioCtx = null;

  function beep(ok) {
    try {
      audioCtx = audioCtx || new AudioContext();
      var osc = audioCtx.createOscillator();
      var gain = audioCtx.createGain();
      osc.frequency.value = ok ? 880 : 220;
      gain.gain.setValueAtTime(0.0001, audioCtx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.15, audioCtx.currentTime + 0.01);
      gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + (ok ? 0.12 : 0.28));
      osc.connect(gain);
      gain.connect(audioCtx.destination);
      osc.start();
      osc.stop(audioCtx.currentTime + 0.3);
      audioCtx.resume();
    } catch (e) { /* sem saída de áudio */ }
  }

  function flash(message, ok) {
    var el = document.getElementById('scanner-live');
    if (!el) return;
    el.hidden = false;
    el.className = 'scanner-live' + (ok === true ? ' ok' : ok === false ? ' erro' : '');
    el.textContent = message;
  }

  window.SmartPlanetScanner = { beep: beep, flash: flash };

  function remember(target) {
    if (!target || (target.tagName !== 'INPUT' && target.tagName !== 'TEXTAREA')) {
      snap = null;
      return;
    }
    if (target.id === 'pos-search' || target.id === 'campo-leitor') {
      snap = null;
      return;
    }
    snap = {
      el: target,
      value: target.value,
      start: target.selectionStart,
      end: target.selectionEnd
    };
  }

  function restore() {
    if (!snap || !snap.el || !document.contains(snap.el)) {
      snap = null;
      return;
    }
    snap.el.value = snap.value;
    if (typeof snap.start === 'number' && snap.el.setSelectionRange) {
      var end = Math.min(snap.value.length, snap.start);
      snap.el.setSelectionRange(end, end);
    }
    snap = null;
  }

  document.addEventListener('keydown', function (evento) {
    if (evento.ctrlKey || evento.altKey || evento.metaKey || evento.isComposing) return;
    var alvo = evento.target;
    if (alvo && alvo.type === 'password') return;
    var resultado = wedge.push(evento.key, performance.now(), evento.repeat);
    if (resultado.action === 'buffer' && resultado.index === 1) remember(alvo);
    if (resultado.action === 'swallow') {
      evento.preventDefault();
      evento.stopPropagation();
      return;
    }
    if (resultado.action !== 'scan') return;
    evento.preventDefault();
    evento.stopPropagation();
    restore();
    if (alvo && (alvo.tagName === 'INPUT' || alvo.tagName === 'TEXTAREA') && alvo.value.endsWith(resultado.code)) {
      alvo.value = alvo.value.slice(0, -resultado.code.length);
    }
    document.dispatchEvent(new CustomEvent('smartplanet:scan', {
      cancelable: true,
      detail: { code: resultado.code }
    }));
  }, true);

  document.addEventListener('smartplanet:scan', function (evento) {
    if (evento.defaultPrevented) return;
    if (document.getElementById('pos-app') || document.getElementById('campo-leitor')) return;
    var campo = document.querySelector('[data-scanner="search"]');
    if (!campo || !campo.form) {
      flash('Código ' + evento.detail.code + ' — abra o PDV para vender.', null);
      beep(false);
      return;
    }
    if (campo.tagName === 'SELECT') {
      var code = String(evento.detail.code || '');
      var match = null;
      for (var i = 0; i < campo.options.length; i++) {
        var opt = campo.options[i];
        if (opt.value === code || opt.dataset.code === code || opt.dataset.barcode === code) {
          match = opt;
          break;
        }
      }
      if (!match) match = new Option(code, code, true, true);
      campo.value = match.value;
    } else {
      campo.value = evento.detail.code;
    }
    flash('Buscando ' + evento.detail.code, true);
    beep(true);
    if (typeof campo.form.requestSubmit === 'function') campo.form.requestSubmit();
    else campo.form.submit();
  });

  flash('Leitor USB pronto. Bipe um código 1D.', null);
})();
