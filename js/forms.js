(function () {
  'use strict';

  var SKIP_TYPES = { hidden: 1, checkbox: 1, radio: 1, file: 1, submit: 1, button: 1, image: 1, reset: 1, range: 1, color: 1 };
  var pendingConfirm = false;

  function digits(value) {
    return String(value || '').replace(/\D+/g, '');
  }

  function labelText(el) {
    if (el.id) {
      var safeId = (window.CSS && CSS.escape) ? CSS.escape(el.id) : String(el.id).replace(/"/g, '\\"');
      var byId = document.querySelector('label[for="' + safeId + '"]');
      if (byId) return (byId.textContent || '').replace(/\s+/g, ' ').trim();
    }
    var wrap = el.closest('label, .field, .campo');
    if (!wrap) return '';
    var label = wrap.querySelector('label');
    return ((label ? label.textContent : wrap.textContent) || '').replace(/\s+/g, ' ').trim();
  }

  function haystack(el) {
    return ((el.name || '') + ' ' + (el.id || '') + ' ' + (el.className || '') + ' ' + labelText(el)).toLowerCase();
  }

  function inferMask(el) {
    if (el.dataset.mask) return el.dataset.mask;
    if (SKIP_TYPES[el.type] || el.readOnly || el.disabled) return '';
    if (el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') return '';
    if (el.type === 'date' || el.type === 'datetime-local' || el.type === 'time' || el.type === 'number' || el.type === 'password' || el.type === 'email') return '';
    var hay = haystack(el);
    if (/\bphone\b|telefone|celular|whatsapp|whats/.test(hay)) return 'phone';
    if (/cnpj/.test(hay) && !/cpf/.test(hay)) return 'cnpj';
    if (/\bcpf\b/.test(hay) && !/cnpj/.test(hay)) return 'cpf';
    if (/\bdocument\b|cpf\/cnpj|cpf-cnpj/.test(hay)) return 'cpf_cnpj';
    if (/\bcep\b|postal/.test(hay)) return 'cep';
    if (/\bncm\b/.test(hay)) return 'ncm';
    if (/\bcest\b/.test(hay)) return 'cest';
    if (/\bcfop\b/.test(hay)) return 'cfop';
    if (/cst_csosn|csosn|\bcst\b/.test(hay)) return 'cst';
    if (/percent|percentual|al[ií]quota/.test(hay)) return 'percent';
    if (/amount|sale_price|cost_price|promotional_price|counted|unit_cost|costs\[|payment-amount|campo-preco|\bvalor\b|pre[cç]o/.test(hay)) return 'money';
    return '';
  }

  function formatPhone(value) {
    var d = digits(value);
    if (d.startsWith('55') && d.length > 11) d = d.slice(2);
    else if (d.startsWith('55') && d.length >= 12) d = d.slice(2);
    d = d.slice(0, 11);
    if (!d) return '';
    var out = '+55';
    if (d.length <= 2) return out + ' (' + d;
    out += ' (' + d.slice(0, 2) + ') ';
    if (d.length <= 6) return out + d.slice(2);
    if (d.length <= 10) return out + d.slice(2, 6) + '-' + d.slice(6);
    return out + d.slice(2, 3) + ' ' + d.slice(3, 7) + '-' + d.slice(7, 11);
  }

  function formatCpf(value) {
    var d = digits(value).slice(0, 11);
    if (d.length <= 3) return d;
    if (d.length <= 6) return d.slice(0, 3) + '.' + d.slice(3);
    if (d.length <= 9) return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6);
    return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
  }

  function formatCnpj(value) {
    var d = digits(value).slice(0, 14);
    if (d.length <= 2) return d;
    if (d.length <= 5) return d.slice(0, 2) + '.' + d.slice(2);
    if (d.length <= 8) return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5);
    if (d.length <= 12) return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8);
    return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8, 12) + '-' + d.slice(12);
  }

  function formatCpfCnpj(value) {
    var d = digits(value);
    if (d.length <= 11) return formatCpf(d);
    return formatCnpj(d);
  }

  function formatCep(value) {
    var d = digits(value).slice(0, 8);
    if (d.length <= 5) return d;
    return d.slice(0, 5) + '-' + d.slice(5);
  }

  function formatNcm(value) {
    var d = digits(value).slice(0, 8);
    if (d.length <= 4) return d;
    if (d.length <= 6) return d.slice(0, 4) + '.' + d.slice(4);
    return d.slice(0, 4) + '.' + d.slice(4, 6) + '.' + d.slice(6);
  }

  function formatCest(value) {
    var d = digits(value).slice(0, 7);
    if (d.length <= 2) return d;
    if (d.length <= 5) return d.slice(0, 2) + '.' + d.slice(2);
    return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5);
  }

  function formatCfop(value) {
    return digits(value).slice(0, 4);
  }

  function formatCst(value) {
    return digits(value).slice(0, 4);
  }

  function formatMoney(value) {
    var raw = String(value || '');
    var neg = /^-/.test(raw.trim());
    var d = digits(raw);
    if (!d) return neg ? '-' : '';
    d = d.replace(/^0+(?=\d)/, '');
    if (!d) d = '0';
    while (d.length < 3) d = '0' + d;
    var intPart = d.slice(0, -2);
    var dec = d.slice(-2);
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return (neg ? '-' : '') + intPart + ',' + dec;
  }

  function formatPercent(value) {
    var raw = String(value || '');
    var d = digits(raw).slice(0, 6);
    if (!d) return '';
    if (d.length === 1) return '0,' + d;
    if (d.length === 2) return d[0] + ',' + d[1];
    return d.slice(0, -1).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + d.slice(-1);
  }

  function applyMask(el, mask) {
    if (!mask) return;
    var formatted;
    switch (mask) {
      case 'phone': formatted = formatPhone(el.value); break;
      case 'cpf': formatted = formatCpf(el.value); break;
      case 'cnpj': formatted = formatCnpj(el.value); break;
      case 'cpf_cnpj': formatted = formatCpfCnpj(el.value); break;
      case 'cep': formatted = formatCep(el.value); break;
      case 'ncm': formatted = formatNcm(el.value); break;
      case 'cest': formatted = formatCest(el.value); break;
      case 'cfop': formatted = formatCfop(el.value); break;
      case 'cst': formatted = formatCst(el.value); break;
      case 'money': formatted = formatMoney(el.value); break;
      case 'percent': formatted = formatPercent(el.value); break;
      default: return;
    }
    if (el.value !== formatted) el.value = formatted;
  }

  function unformatMoney(value) {
    var raw = String(value || '').replace(/[R$\s]/g, '').trim();
    if (!raw || raw === '-') return '';
    var neg = raw.charAt(0) === '-';
    raw = raw.replace(/-/g, '');
    if (raw.indexOf(',') >= 0) raw = raw.replace(/\./g, '').replace(',', '.');
    var n = Number(raw);
    if (!Number.isFinite(n)) return String(value || '');
    var abs = Math.abs(n).toFixed(2).replace('.', ',');
    return (neg ? '-' : '') + abs;
  }

  function unformatPercent(value) {
    var raw = String(value || '').replace(/[%\s]/g, '').trim();
    if (!raw) return '';
    if (raw.indexOf(',') >= 0) raw = raw.replace(/\./g, '').replace(',', '.');
    var n = Number(raw);
    if (!Number.isFinite(n)) return String(value || '');
    return String(n).replace('.', ',');
  }

  function isPhonePrefixOnly(value) {
    return /^\+?55\s*$/.test(String(value || '').trim());
  }

  var PLACEHOLDERS = [
    { test: /email/, value: 'Ex.: contato@empresa.com.br' },
    { test: /password|senha|confirmation/, value: 'Mínimo de 10 caracteres' },
    { test: /^q$|busca|search|pesquis/, value: 'Digite para pesquisar...' },
    { test: /legal_name|raz[aã]o social/, value: 'Ex.: Empresa Exemplo Ltda.' },
    { test: /trade_name|nome fantasia/, value: 'Ex.: Mercado Central' },
    { test: /complementary_name|nome complementar/, value: 'Ex.: Unidade 128 GB, cor preta' },
    { test: /description|descri/, value: 'Ex.: Camiseta básica masculina' },
    { test: /internal_code|c[oó]digo interno/, value: 'Ex.: PROD-001' },
    { test: /barcode|c[oó]digo de barras|campo-codigo/, value: 'Ex.: 7891234567895' },
    { test: /model|modelo/, value: 'Ex.: Galaxy A15' },
    { test: /cost_price|custo/, value: 'Ex.: 25,90' },
    { test: /sale_price|pre[cç]o de venda|campo-preco/, value: 'Ex.: 49,90' },
    { test: /promotional_price|promocional/, value: 'Ex.: 39,90' },
    { test: /minimum_stock|estoque m[ií]nimo/, value: 'Ex.: 5' },
    { test: /initial_stock|saldo inicial/, value: 'Ex.: 10' },
    { test: /\bncm\b/, value: 'Ex.: 8517.12.31' },
    { test: /\bcest\b/, value: 'Ex.: 21.064.00' },
    { test: /origin_code|origem/, value: 'Ex.: 0' },
    { test: /\bcfop\b/, value: 'Ex.: 5102' },
    { test: /cst|csosn/, value: 'Ex.: 102' },
    { test: /notes|observa/, value: 'Ex.: Adicione informações complementares.' },
    { test: /phone|telefone|celular/, value: 'Ex.: +55 (47) 9 9999-9999' },
    { test: /cnpj/, value: 'Ex.: 00.000.000/0000-00' },
    { test: /\bcpf\b/, value: 'Ex.: 000.000.000-00' },
    { test: /document/, value: 'Ex.: 000.000.000-00 ou 00.000.000/0000-00' },
    { test: /\bcep\b/, value: 'Ex.: 88330-000' },
    { test: /amount|counted|valor|costs\[/, value: 'Ex.: 150,00' },
    { test: /discount|desconto/, value: 'Ex.: 10,00' },
    { test: /quantity|quantidade|quantities|counts\[/, value: 'Ex.: 10' },
    { test: /^name$|nome \*|nome \/|nome do|funcion[aá]rio/, value: 'Ex.: João da Silva' },
    { test: /reason|justificativa|motivo/, value: 'Ex.: Informe o motivo com pelo menos 5 caracteres.' },
    { test: /reference|refer[eê]ncia/, value: 'Ex.: NSU, autorizacao ou comprovante' },
    { test: /label|suspens/, value: 'Ex.: Mesa 3 / cliente aguardando' },
    { test: /decimal_places|casas decimais/, value: 'Ex.: 2' },
    { test: /\bcode\b|c[oó]digo \*/, value: 'Ex.: UN' },
    { test: /payment-amount/, value: 'Ex.: 150,00' }
  ];

  function inferPlaceholder(el) {
    if (el.placeholder) return '';
    if (SKIP_TYPES[el.type]) return '';
    if (el.tagName === 'SELECT') return '';
    if (el.type === 'date') return 'aaaa-mm-dd';
    if (el.type === 'time') return 'hh:mm';
    if (el.type === 'datetime-local') return 'aaaa-mm-dd hh:mm';
    if (el.type === 'password') return 'Mínimo de 10 caracteres';
    if (el.type === 'email') return 'Ex.: contato@empresa.com.br';
    var hay = haystack(el);
    if (el.classList.contains('cart-qty')) return 'Ex.: 1';
    if (el.classList.contains('cart-discount') || el.classList.contains('payment-amount')) return 'Ex.: 10,00';
    if (el.classList.contains('payment-reference')) return 'Ex.: NSU ou comprovante';
    for (var i = 0; i < PLACEHOLDERS.length; i++) {
      if (PLACEHOLDERS[i].test.test(hay)) return PLACEHOLDERS[i].value;
    }
    if (el.closest('.filters') || el.name === 'q') return 'Digite para pesquisar...';
    if (el.tagName === 'TEXTAREA') return 'Ex.: Adicione informações complementares.';
    if (el.type === 'number') return 'Ex.: 10';
    if (el.type === 'search') return 'Digite para pesquisar...';
    return 'Ex.: Preencha este campo.';
  }

  function enhanceSelect(el) {
    if (el.tagName !== 'SELECT' || el.multiple || el.dataset.spSelect === '1') return;
    var first = el.options[0];
    if (!first) return;
    var text = (first.textContent || '').trim();
    if (first.value === '' && (/^(selecione|—|-|–)?$/i.test(text) || text === 'Selecione')) {
      first.textContent = 'Selecione uma opção...';
    }
    el.dataset.spSelect = '1';
  }

  function enhanceField(el) {
    if (!(el instanceof HTMLElement)) return;
    if (el.dataset.spReady === '1') return;
    if (el.tagName === 'SELECT') {
      enhanceSelect(el);
      el.dataset.spReady = '1';
      return;
    }
    if (!(el instanceof HTMLInputElement) && !(el instanceof HTMLTextAreaElement)) return;
    if (SKIP_TYPES[el.type]) {
      if (el.type === 'file' && !el.title) el.title = 'Selecione o arquivo a enviar.';
      el.dataset.spReady = '1';
      return;
    }
    var ph = inferPlaceholder(el);
    if (ph && !el.placeholder) el.placeholder = ph;
    if (el.type === 'date' || el.type === 'time' || el.type === 'datetime-local') {
      if (!el.title) el.title = 'Use o calendário. Formato aceito: ' + (el.placeholder || 'aaaa-mm-dd');
    }
    var mask = inferMask(el);
    if (mask) {
      el.dataset.mask = mask;
      if (el.value) applyMask(el, mask);
      if (mask === 'phone' && !el.placeholder) el.placeholder = 'Ex.: +55 (47) 9 9999-9999';
    }
    el.dataset.spReady = '1';
  }

  function enhance(root) {
    var scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll('input, select, textarea').forEach(enhanceField);
    if (scope instanceof HTMLElement && /^(INPUT|SELECT|TEXTAREA)$/.test(scope.tagName)) enhanceField(scope);
  }

  function onInput(event) {
    var el = event.target;
    if (!(el instanceof HTMLInputElement) && !(el instanceof HTMLTextAreaElement)) return;
    var mask = el.dataset.mask || inferMask(el);
    if (!mask) return;
    if (mask === 'phone') {
      if (event.inputType === 'deleteContentBackward' && (isPhonePrefixOnly(el.value) || !digits(el.value))) {
        el.value = '';
        return;
      }
      if (isPhonePrefixOnly(el.value)) {
        el.value = '+55 ';
        return;
      }
    }
    applyMask(el, mask);
  }

  function onFocus(event) {
    var el = event.target;
    if (!(el instanceof HTMLInputElement)) return;
    if ((el.dataset.mask || inferMask(el)) === 'phone' && !el.value) el.value = '+55 ';
  }

  function onBlur(event) {
    var el = event.target;
    if (!(el instanceof HTMLInputElement)) return;
    if ((el.dataset.mask || '') === 'phone' && isPhonePrefixOnly(el.value)) el.value = '';
  }

  function prepareSubmit(form) {
    form.querySelectorAll('[data-mask]').forEach(function (el) {
      var mask = el.dataset.mask;
      if (mask === 'money') el.value = unformatMoney(el.value);
      else if (mask === 'percent') el.value = unformatPercent(el.value);
      else if (mask === 'phone' && isPhonePrefixOnly(el.value)) el.value = '';
    });
  }

  var KINDS = {
    excluir: {
      title1: 'Deseja realmente excluir este registro?',
      title2: 'Confirme novamente a exclusão.',
      irreversible: 'Esta operação poderá ser irreversível.',
      text2: 'A exclusão será executada após esta confirmação.',
      confirm: 'Confirmar exclusão'
    },
    inativar: {
      title1: 'Deseja realmente inativar este registro?',
      title2: 'Confirme novamente a inativação.',
      irreversible: 'O registro deixará de aparecer nas operações ativas.',
      text2: 'A inativação será executada após esta confirmação.',
      confirm: 'Confirmar inativação'
    },
    cancelar: {
      title1: 'Deseja realmente cancelar/estornar esta venda?',
      title2: 'Confirme novamente o cancelamento.',
      irreversible: 'A venda será estornada e a operação poderá ser irreversível.',
      text2: 'O cancelamento será executado após esta confirmação.',
      confirm: 'Confirmar cancelamento'
    },
    devolver: {
      title1: 'Deseja realmente confirmar esta devolução?',
      title2: 'Confirme novamente a devolução.',
      irreversible: 'Os itens selecionados serão devolvidos. Esta operação poderá ser irreversível.',
      text2: 'A devolução será executada após esta confirmação.',
      confirm: 'Confirmar devolução'
    },
    limpar: {
      title1: 'Deseja realmente limpar estes registros?',
      title2: 'Confirme novamente a exclusão.',
      irreversible: 'Esta operação poderá ser irreversível.',
      text2: 'A exclusão será executada após esta confirmação.',
      confirm: 'Confirmar exclusão'
    }
  };

  function ensureDialog() {
    var box = document.getElementById('sp-confirm');
    if (box) return box;
    box = document.createElement('dialog');
    box.id = 'sp-confirm';
    box.className = 'sp-confirm';
    box.innerHTML = '<div class="dialogo-corpo">' +
      '<h2 id="sp-confirm-title"></h2>' +
      '<p id="sp-confirm-text"></p>' +
      '</div>' +
      '<div class="dialogo-rodape">' +
      '<button type="button" id="sp-confirm-secondary"></button>' +
      '<button type="button" class="danger" id="sp-confirm-primary"></button>' +
      '</div>';
    document.body.appendChild(box);
    return box;
  }

  function confirmTwice(options) {
    options = options || {};
    var kind = KINDS[options.kind] || KINDS.excluir;
    var item = String(options.item || 'este registro');
    var count = Number(options.count || 0);
    var identity = count > 1
      ? (count + ' registros selecionados')
      : ('“' + item + '”');

    return new Promise(function (resolve) {
      if (pendingConfirm) {
        resolve(false);
        return;
      }
      pendingConfirm = true;
      var box = ensureDialog();
      var title = document.getElementById('sp-confirm-title');
      var text = document.getElementById('sp-confirm-text');
      var secondary = document.getElementById('sp-confirm-secondary');
      var primary = document.getElementById('sp-confirm-primary');
      var step = 1;
      var settled = false;

      function finish(ok) {
        if (settled) return;
        settled = true;
        pendingConfirm = false;
        try { box.close(); } catch (e) { box.removeAttribute('open'); }
        box.removeEventListener('cancel', onCancel);
        primary.removeEventListener('click', onPrimary);
        secondary.removeEventListener('click', onSecondary);
        resolve(ok);
      }

      function paint() {
        if (step === 1) {
          title.textContent = options.title1 || kind.title1;
          text.textContent = identity + '. ' + (options.text1 || kind.irreversible);
          secondary.textContent = 'Cancelar';
          primary.textContent = 'Continuar';
          primary.className = 'primary';
        } else {
          title.textContent = options.title2 || kind.title2;
          text.textContent = identity + '. ' + (options.text2 || kind.text2 || 'A exclusão será executada após esta confirmação.');
          secondary.textContent = 'Voltar';
          primary.textContent = options.confirmLabel || kind.confirm;
          primary.className = 'danger';
        }
      }

      function onPrimary(event) {
        event.preventDefault();
        if (step === 1) {
          step = 2;
          paint();
          return;
        }
        finish(true);
      }

      function onSecondary(event) {
        event.preventDefault();
        if (step === 2) {
          step = 1;
          paint();
          return;
        }
        finish(false);
      }

      function onCancel(event) {
        event.preventDefault();
        finish(false);
      }

      paint();
      primary.addEventListener('click', onPrimary);
      secondary.addEventListener('click', onSecondary);
      box.addEventListener('cancel', onCancel);
      if (typeof box.showModal === 'function') box.showModal();
      else box.setAttribute('open', 'open');
    });
  }

  function formKind(form) {
    if (form.dataset.confirmTwice && KINDS[form.dataset.confirmTwice]) return form.dataset.confirmTwice;
    var action = form.getAttribute('action') || '';
    if (/\/sales\/cancel$/.test(action)) return 'cancelar';
    if (/\/sales\/return$/.test(action)) return 'devolver';
    if (/\/products\/status$/.test(action)) {
      var active = form.querySelector('[name="active"]');
      if (active && active.value === '0') return 'inativar';
    }
    return 'excluir';
  }

  function isDestructiveForm(form) {
    if (form.dataset.confirmTwice === '0') return false;
    if (form.dataset.confirmTwice && form.dataset.confirmTwice !== '0') return true;
    var action = form.getAttribute('action') || '';
    if (/\/sales\/(cancel|return)$/.test(action)) return true;
    if (/\/products\/status$/.test(action)) {
      var active = form.querySelector('[name="active"]');
      return !!(active && active.value === '0');
    }
    return false;
  }

  function formItem(form) {
    if (form.dataset.confirmItem) return form.dataset.confirmItem;
    if (form.dataset.confirmCount) return form.dataset.confirmCount + ' registros';
    var reason = form.querySelector('[name="reason"]');
    var named = form.querySelector('[name="description"], [name="name"], [name="q"]');
    return (named && named.value) || (reason && reason.value) || 'este registro';
  }

  function onSubmit(event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.spConfirmed === '1') {
      delete form.dataset.spConfirmed;
      prepareSubmit(form);
      return;
    }
    if (!isDestructiveForm(form)) {
      prepareSubmit(form);
      return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    confirmTwice({
      kind: formKind(form),
      item: formItem(form),
      count: Number(form.dataset.confirmCount || 0)
    }).then(function (ok) {
      if (!ok) return;
      form.dataset.spConfirmed = '1';
      if (typeof form.requestSubmit === 'function') form.requestSubmit();
      else form.submit();
    });
  }

  document.addEventListener('input', onInput, true);
  document.addEventListener('focusin', onFocus, true);
  document.addEventListener('focusout', onBlur, true);
  document.addEventListener('submit', onSubmit, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { enhance(document); });
  } else {
    enhance(document);
  }

  var observer = new MutationObserver(function (records) {
    records.forEach(function (record) {
      record.addedNodes.forEach(function (node) {
        if (!(node instanceof HTMLElement)) return;
        enhance(node);
      });
    });
  });
  observer.observe(document.documentElement, { childList: true, subtree: true });

  window.SmartPlanetForms = {
    enhance: enhance,
    confirmTwice: confirmTwice,
    formatPhone: formatPhone,
    formatCpfCnpj: formatCpfCnpj,
    formatCep: formatCep,
    formatMoney: formatMoney,
    unformatMoney: unformatMoney
  };
})();
