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

  function phoneDigits(value) {
    var raw = String(value || '');
    var plus = raw.search(/\+55/);
    var d;
    if (plus >= 0) d = digits(raw.slice(0, plus)) + digits(raw.slice(plus + 3));
    else {
      d = digits(raw);
      if (d.startsWith('55') && d.length > 11) d = d.slice(2);
    }
    return d.slice(0, 11);
  }

  function formatPhone(value) {
    var raw = String(value || '');
    var hasPrefix = raw.search(/\+55/) >= 0;
    var d = phoneDigits(value);
    if (!d) return hasPrefix ? '+55 ' : '';
    var out = '+55';
    if (d.length <= 2) return out + ' (' + d;
    out += ' (' + d.slice(0, 2) + ') ';
    var rest = d.slice(2);
    if (rest.length <= 4) return out + rest;
    if (rest.charAt(0) === '9') {
      if (rest.length <= 5) return out + rest.charAt(0) + ' ' + rest.slice(1);
      return out + rest.charAt(0) + ' ' + rest.slice(1, 5) + '-' + rest.slice(5);
    }
    return out + rest.slice(0, 4) + '-' + rest.slice(4);
  }

  function phoneDigitsBeforeCaret(raw, cursor) {
    return phoneDigits(String(raw || '').slice(0, cursor)).length;
  }

  function caretAfterPhoneDigits(formatted, count) {
    if (!count) {
      var at = formatted.indexOf('+55');
      return at >= 0 ? Math.min(formatted.length, at + 4) : 0;
    }
    var plus = formatted.indexOf('+55');
    var start = plus >= 0 ? plus + 3 : 0;
    var seen = 0;
    for (var i = start; i < formatted.length; i++) {
      var ch = formatted.charAt(i);
      if (ch >= '0' && ch <= '9') {
        seen++;
        if (seen === count) return i + 1;
      }
    }
    return formatted.length;
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

  function fold(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  }

  function enhanceSelect(el) {
    if (el.tagName !== 'SELECT' || el.multiple || el.dataset.spCombo === '1') return;
    if (el.dataset.cfg) return;
    if (!el.options.length) return;
    el.dataset.spCombo = '1';

    var box = document.createElement('div');
    box.className = 'sp-combo';
    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'sp-combo-input';
    input.autocomplete = 'off';
    input.setAttribute('autocapitalize', 'off');
    input.setAttribute('spellcheck', 'false');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.placeholder = 'Digite para buscar...';
    if (el.required) {
      input.required = true;
      el.required = false;
    }
    if (el.disabled) input.disabled = true;
    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'sp-combo-toggle';
    toggle.setAttribute('aria-label', 'Mostrar cadastrados');
    toggle.textContent = '▾';
    if (el.disabled) toggle.disabled = true;
    var list = document.createElement('ul');
    list.className = 'sp-combo-list';
    list.hidden = true;
    list.setAttribute('role', 'listbox');
    el.parentNode.insertBefore(box, el);
    box.appendChild(input);
    box.appendChild(toggle);
    box.appendChild(el);
    box.appendChild(list);
    el.classList.add('sp-combo-native');
    el.tabIndex = -1;
    if (el.id && !input.id) input.id = el.id + '-busca';
    var label = el.id ? document.querySelector('label[for="' + ((window.CSS && CSS.escape) ? CSS.escape(el.id) : el.id) + '"]') : null;
    if (label) label.htmlFor = input.id;

    var open = false;
    var active = 0;
    var shown = [];
    var valueDesc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');

    function options() {
      return Array.prototype.slice.call(el.options);
    }

    function paintInput() {
      var opt = el.options[el.selectedIndex];
      input.value = opt && opt.value !== '' ? (opt.textContent || '').trim() : '';
    }

    function place() {
      var rect = input.getBoundingClientRect();
      var below = window.innerHeight - rect.bottom;
      list.style.position = 'fixed';
      list.style.left = Math.max(8, rect.left) + 'px';
      list.style.width = rect.width + 'px';
      list.style.zIndex = '80';
      if (below < 200 && rect.top > below) {
        list.style.top = 'auto';
        list.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
        list.style.maxHeight = Math.max(120, rect.top - 12) + 'px';
      } else {
        list.style.bottom = 'auto';
        list.style.top = (rect.bottom + 4) + 'px';
        list.style.maxHeight = Math.max(120, Math.min(240, below - 12)) + 'px';
      }
    }

    function render(query) {
      var needle = fold(query);
      shown = options().filter(function (opt) {
        if (!needle) return true;
        return fold(opt.textContent).indexOf(needle) >= 0;
      });
      list.textContent = '';
      if (!shown.length) {
        var empty = document.createElement('li');
        empty.className = 'sp-combo-empty';
        empty.textContent = 'Nenhum cadastro encontrado.';
        list.appendChild(empty);
        active = 0;
        return;
      }
      if (active >= shown.length) active = 0;
      shown.forEach(function (opt, index) {
        var item = document.createElement('li');
        item.setAttribute('role', 'option');
        item.textContent = (opt.textContent || '').trim();
        if (index === active) item.className = 'is-active';
        if (opt.value === el.value) item.setAttribute('aria-selected', 'true');
        item.addEventListener('mousedown', function (event) {
          event.preventDefault();
          commit(opt);
        });
        list.appendChild(item);
      });
    }

    function openList(query) {
      if (input.disabled) return;
      open = true;
      input.setAttribute('aria-expanded', 'true');
      list.hidden = false;
      render(query);
      place();
    }

    function closeList() {
      open = false;
      input.setAttribute('aria-expanded', 'false');
      list.hidden = true;
    }

    function commit(opt) {
      if (!opt) return;
      var changed = el.value !== opt.value;
      valueDesc.set.call(el, opt.value);
      paintInput();
      closeList();
      if (changed) el.dispatchEvent(new Event('change', { bubbles: true }));
      input.setCustomValidity('');
    }

    function exact(query) {
      var needle = fold(query.trim());
      if (!needle) return options().filter(function (opt) { return opt.value === ''; })[0] || null;
      return options().filter(function (opt) { return fold((opt.textContent || '').trim()) === needle; })[0] || null;
    }

    function settle() {
      var match = exact(input.value);
      if (match) commit(match);
      else paintInput();
      closeList();
    }

    Object.defineProperty(el, 'value', {
      configurable: true,
      get: function () { return valueDesc.get.call(el); },
      set: function (next) {
        valueDesc.set.call(el, next);
        paintInput();
      }
    });

    input.addEventListener('focus', function () {
      active = 0;
      openList('');
      input.select();
    });
    input.addEventListener('input', function () {
      active = 0;
      openList(input.value);
    });
    input.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!open) openList(input.value);
        else if (shown.length) {
          active = (active + 1) % shown.length;
          render(input.value);
          place();
        }
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (!open) openList(input.value);
        else if (shown.length) {
          active = (active - 1 + shown.length) % shown.length;
          render(input.value);
          place();
        }
      } else if (event.key === 'Enter') {
        if (!open) return;
        event.preventDefault();
        if (shown[active]) commit(shown[active]);
      } else if (event.key === 'Escape') {
        event.preventDefault();
        paintInput();
        closeList();
      }
    });
    input.addEventListener('blur', function () {
      setTimeout(function () {
        if (document.activeElement === input) return;
        settle();
      }, 140);
    });
    toggle.addEventListener('mousedown', function (event) {
      event.preventDefault();
    });
    toggle.addEventListener('click', function () {
      if (open) {
        closeList();
        return;
      }
      input.focus();
      active = 0;
      openList('');
    });
    list.addEventListener('mousedown', function (event) {
      event.preventDefault();
    });
    window.addEventListener('resize', function () { if (open && input.isConnected) place(); });
    document.addEventListener('scroll', function () {
      if (!input.isConnected) { closeList(); return; }
      if (open) place();
    }, true);
    input._spSettle = settle;

    paintInput();
  }

  function enhanceField(el) {
    if (!(el instanceof HTMLElement)) return;
    if (el.dataset.spReady === '1') return;
    if (el.tagName === 'SELECT') {
      if (el.dataset.plain !== '1') enhanceSelect(el);
      el.dataset.spReady = '1';
      return;
    }
    if (!(el instanceof HTMLInputElement) && !(el instanceof HTMLTextAreaElement)) return;
    if (SKIP_TYPES[el.type]) {
      if (el.type === 'file' && !el.title) el.title = 'Selecione o arquivo a enviar.';
      el.dataset.spReady = '1';
      return;
    }
    if (el.type !== 'password' && el.type !== 'email') el.setAttribute('autocomplete', 'off');
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
        try { el.setSelectionRange(el.value.length, el.value.length); } catch (e) {}
        return;
      }
      var typed = phoneDigitsBeforeCaret(el.value, el.selectionStart);
      applyMask(el, mask);
      var pos = caretAfterPhoneDigits(el.value, typed);
      try { el.setSelectionRange(pos, pos); } catch (e) {}
      return;
    }
    applyMask(el, mask);
  }

  function onFocus(event) {
    var el = event.target;
    if (!(el instanceof HTMLInputElement)) return;
    if ((el.dataset.mask || inferMask(el)) === 'phone' && !el.value) {
      el.value = '+55 ';
      var end = el.value.length;
      try { el.setSelectionRange(end, end); } catch (e) {}
    }
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
    form.querySelectorAll('.sp-combo-input').forEach(function (field) {
      if (typeof field._spSettle === 'function') field._spSettle();
    });
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
  document.addEventListener('change', function (event) {
    var el = event.target;
    if (!(el instanceof HTMLSelectElement) || el.dataset.submitOnChange !== '1' || !el.form) return;
    if (typeof el.form.requestSubmit === 'function') el.form.requestSubmit();
    else el.form.submit();
  });

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
