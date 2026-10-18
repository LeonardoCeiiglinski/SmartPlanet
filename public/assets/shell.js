(function () {
  'use strict';

  const progress = document.getElementById('shell-progress');
  const main = () => document.querySelector('.main-content');
  let inflight = 0;

  function samePath(url) {
    return url.pathname + url.search === location.pathname + location.search;
  }

  function isShellUrl(url) {
    if (url.origin !== location.origin) return false;
    if (url.pathname.startsWith('/legacy/') || url.pathname.startsWith('/assets/')) return false;
    if (url.pathname === '/login' || url.pathname === '/logout' || url.pathname === '/forgot' || url.pathname === '/reset') return false;
    if (/\/(export|template)$/.test(url.pathname)) return false;
    return true;
  }

  function setActive(href) {
    const current = new URL(href, location.origin);
    document.querySelectorAll('.sidebar a').forEach((link) => {
      const target = new URL(link.getAttribute('href'), location.origin);
      let on = false;
      if (target.pathname === '/people') {
        on = current.pathname === '/people' && target.search === current.search;
      } else if (target.pathname === '/') {
        on = current.pathname === '/';
      } else {
        on = current.pathname === target.pathname || current.pathname.startsWith(target.pathname + '/');
      }
      link.classList.toggle('active', on);
    });
  }

  function showProgress() {
    if (!progress) return;
    progress.classList.remove('done');
    progress.classList.add('on');
  }

  function hideProgress() {
    if (!progress) return;
    progress.classList.add('done');
    setTimeout(() => progress.classList.remove('on', 'done'), 220);
  }

  function extraHead(doc) {
    const nodes = [];
    doc.querySelectorAll('head link[rel="stylesheet"], head style').forEach((node) => {
      if (node.tagName === 'LINK' && (node.getAttribute('href') || '').includes('/assets/erp.css')) return;
      nodes.push(node);
    });
    return nodes;
  }

  function afterShell(doc) {
    const end = doc.getElementById('shell-end');
    return end ? [...end.children] : [];
  }

  async function runScripts(root) {
    const scripts = [...root.querySelectorAll('script')];
    for (const old of scripts) {
      const type = (old.getAttribute('type') || 'text/javascript').toLowerCase();
      if (type !== 'text/javascript' && type !== 'application/javascript' && type !== '') continue;
      if (old.src) {
        await new Promise((resolve, reject) => {
          const next = document.createElement('script');
          next.src = old.src;
          next.async = false;
          next.onload = () => resolve();
          next.onerror = () => reject(new Error('Falha ao carregar script'));
          old.replaceWith(next);
        });
      } else {
        const next = document.createElement('script');
        next.textContent = old.textContent;
        old.replaceWith(next);
      }
    }
  }

  async function visit(href, push) {
    const url = new URL(href, location.origin);
    if (!isShellUrl(url)) {
      location.href = url.href;
      return;
    }
    if (push && samePath(url)) return;

    inflight += 1;
    const token = inflight;
    showProgress();
    document.body.classList.remove('menu-open');

    try {
      const response = await fetch(url.href, {
        credentials: 'same-origin',
        headers: { Accept: 'text/html', 'X-Shell': '1' },
      });
      if (token !== inflight) return;
      const html = await response.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      if (!doc.querySelector('.app-shell') || doc.body.classList.contains('guest-page')) {
        location.href = response.url || url.href;
        return;
      }

      window.__posAbort && window.__posAbort.abort();
      window.__labelsAbort && window.__labelsAbort.abort();
      window.__labelsPageAbort && window.__labelsPageAbort.abort();

      const nextMain = doc.querySelector('.main-content');
      const currentMain = main();
      if (!nextMain || !currentMain) {
        location.href = url.href;
        return;
      }

      currentMain.replaceWith(nextMain);
      document.title = doc.title;
      document.body.className = doc.body.className;
      const csrf = doc.querySelector('meta[name="csrf-token"]');
      const localCsrf = document.querySelector('meta[name="csrf-token"]');
      if (csrf && localCsrf) localCsrf.setAttribute('content', csrf.getAttribute('content') || '');

      document.querySelectorAll('[data-shell-extra]').forEach((node) => node.remove());
      extraHead(doc).forEach((node) => {
        const copy = document.importNode(node, true);
        copy.setAttribute('data-shell-extra', '1');
        document.head.appendChild(copy);
      });

      const end = document.getElementById('shell-end');
      if (end) {
        end.replaceChildren();
        afterShell(doc).forEach((node) => {
          end.appendChild(document.importNode(node, true));
        });
      }

      await runScripts(document.querySelector('.main-content'));
      if (end) await runScripts(end);
      if (window.SmartPlanetForms) window.SmartPlanetForms.enhance(document);

      if (push) history.pushState({ shell: true }, '', url.href);
      setActive(url.href);
      window.scrollTo(0, 0);
    } catch (error) {
      location.href = url.href;
      return;
    } finally {
      if (token === inflight) hideProgress();
    }
  }

  document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download')) return;
    if (link.closest('.top-logout')) return;
    const url = new URL(link.href, location.origin);
    if (url.hash && url.pathname === location.pathname && url.search === location.search) return;
    if (!isShellUrl(url)) return;
    event.preventDefault();
    visit(url.href, true);
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') return;
    if (form.target === '_blank') return;
    const url = new URL(form.getAttribute('action') || location.href, location.origin);
    if (!isShellUrl(url)) return;
    event.preventDefault();
    url.search = new URLSearchParams(new FormData(form)).toString();
    visit(url.href, true);
  });

  window.addEventListener('popstate', () => {
    visit(location.href, false);
  });

  history.replaceState({ shell: true }, '', location.href);
  setActive(location.href);
  document.querySelectorAll('head link[rel="stylesheet"], head style').forEach((node) => {
    if (node.tagName === 'LINK' && (node.getAttribute('href') || '').includes('/assets/erp.css')) return;
    node.setAttribute('data-shell-extra', '1');
  });
})();
