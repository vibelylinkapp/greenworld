/* GreenWorld Wellness front-end runtime - vanilla, dependency-free, deferred. */
(function () {
  'use strict';
  var cfg = window.GreenWorld || {};
  var body = document.body;
  var mq = function (q) { return window.matchMedia(q).matches; };
  var isMobile = function () { return mq('(max-width: 900px)'); };
  var reduce = mq('(prefers-reduced-motion: reduce)');

  /* ---------- sticky header + back to top ---------- */
  var sticky = document.querySelector('[data-gw-sticky]');
  var utility = document.querySelector('.gw-utility');
  var toTop = document.querySelector('[data-gw-backtotop]');
  var onScroll = function () {
    var y = window.scrollY;
    if (sticky) { sticky.classList.toggle('is-stuck', y > (utility ? utility.offsetHeight : 20)); }
    if (toTop) { toTop.classList.toggle('is-visible', y > 600); }
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  if (toTop) {
    toTop.addEventListener('click', function (e) {
      e.preventDefault();
      window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    });
  }

  /* ---------- mobile nav drawer ---------- */
  function openNav() { body.classList.add('gw-nav-open'); setExpanded('[data-gw-nav-toggle]', true); }
  function closeNav() { body.classList.remove('gw-nav-open'); setExpanded('[data-gw-nav-toggle]', false); }
  function setExpanded(sel, v) { document.querySelectorAll(sel).forEach(function (b) { b.setAttribute('aria-expanded', v ? 'true' : 'false'); }); }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-gw-nav-toggle]')) {
      e.preventDefault();
      body.classList.contains('gw-nav-open') ? closeNav() : openNav();
    } else if (e.target.closest('[data-gw-nav-scrim]')) {
      closeNav();
    }
  });

  /* ---------- mega menu (hover on desktop, click to expand) ---------- */
  document.querySelectorAll('[data-gw-mega-item]').forEach(function (item) {
    var trigger = item.querySelector('.gw-mega-trigger');
    if (!trigger) { return; }
    trigger.addEventListener('click', function (e) {
      if (isMobile()) {
        e.preventDefault();
        var open = item.classList.toggle('is-open');
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      } else if (!item.classList.contains('is-open')) {
        // First click opens; second follows the link.
        e.preventDefault();
        document.querySelectorAll('[data-gw-mega-item].is-open').forEach(function (o) { if (o !== item) { o.classList.remove('is-open'); } });
        item.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
      }
    });
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest('[data-gw-mega-item]')) {
      document.querySelectorAll('[data-gw-mega-item].is-open').forEach(function (o) { o.classList.remove('is-open'); o.querySelector('.gw-mega-trigger') && o.querySelector('.gw-mega-trigger').setAttribute('aria-expanded', 'false'); });
    }
  });

  /* ---------- mini-cart drawer ---------- */
  var drawer = document.querySelector('[data-gw-minicart]');
  function openCart() { if (drawer) { drawer.hidden = false; body.classList.add('gw-minicart-open'); } }
  function closeCart() { if (drawer) { drawer.hidden = true; body.classList.remove('gw-minicart-open'); } }
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-gw-minicart-open]')) { e.preventDefault(); openCart(); }
    else if (e.target.closest('[data-gw-minicart-close]')) { e.preventDefault(); closeCart(); }
  });
  if (window.jQuery) { window.jQuery(document.body).on('added_to_cart', function () { openCart(); }); }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeCart(); closeNav(); shutQv();
      var f = document.querySelector('[data-gw-filters].is-open'); if (f) { f.classList.remove('is-open'); }
    }
  });

  /* ---------- AJAX search ---------- */
  var input = document.querySelector('[data-gw-search-input]');
  var panel = document.querySelector('[data-gw-search-panel]');
  if (input && panel && cfg.ajaxUrl) {
    var RKEY = 'gw-recent-searches', timer = null;
    var popular = ['Immunity', 'Detox', 'Weight Management', 'Digestion', 'Energy', 'Fertility'];
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var getRecent = function () { try { return JSON.parse(localStorage.getItem(RKEY) || '[]'); } catch (e) { return []; } };
    var pushRecent = function (q) { try { var l = getRecent().filter(function (x) { return x !== q; }); l.unshift(q); localStorage.setItem(RKEY, JSON.stringify(l.slice(0, 5))); } catch (e) {} };
    var show = function () { panel.hidden = false; input.setAttribute('aria-expanded', 'true'); };
    var hide = function () { panel.hidden = true; input.setAttribute('aria-expanded', 'false'); };
    var chips = function (title, arr) {
      if (!arr.length) { return ''; }
      var h = '<div class="gw-sr-group__title">' + esc(title) + '</div><div class="gw-sr-cats">';
      arr.forEach(function (q) { h += '<a class="gw-sr-chip" href="#" data-gw-term="' + esc(q) + '">' + esc(q) + '</a>'; });
      return h + '</div>';
    };
    var renderIdle = function () { panel.innerHTML = chips('Recent searches', getRecent()) + chips('Popular', popular); show(); };
    var render = function (d) {
      var p = (d && d.products) || [], c = (d && d.categories) || [];
      if (!p.length && !c.length) { panel.innerHTML = '<div class="gw-sr-empty">No matches. Press Enter to search everything.</div>'; show(); return; }
      var h = '';
      if (c.length) { h += '<div class="gw-sr-group__title">Categories</div><div class="gw-sr-cats">'; c.forEach(function (cat) { h += '<a class="gw-sr-chip" href="' + esc(cat.url) + '">' + esc(cat.name) + ' (' + esc(cat.count) + ')</a>'; }); h += '</div>'; }
      if (p.length) { h += '<div class="gw-sr-group__title">Products</div>'; p.forEach(function (pr) { h += '<a class="gw-sr-item" href="' + esc(pr.url) + '"><img class="gw-sr-item__img" src="' + esc(pr.img) + '" alt="" loading="lazy" /><span class="gw-sr-item__t">' + esc(pr.title) + '</span><span class="gw-sr-item__p">' + esc(pr.price) + '</span></a>'; }); }
      panel.innerHTML = h; show();
    };
    var doSearch = function (q) {
      fetch(cfg.ajaxUrl + '?action=gw_search&nonce=' + encodeURIComponent(cfg.nonce || '') + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); }).then(function (res) { render(res && res.data ? res.data : {}); }).catch(function () {});
    };
    input.addEventListener('input', function () {
      var q = input.value.trim();
      if (timer) { clearTimeout(timer); }
      if (q.length < 2) { renderIdle(); return; }
      timer = setTimeout(function () { doSearch(q); }, 220);
    });
    input.addEventListener('focus', function () { if (input.value.trim().length < 2) { renderIdle(); } });
    var form = input.closest('form');
    if (form) { form.addEventListener('submit', function () { var q = input.value.trim(); if (q) { pushRecent(q); } }); }
    panel.addEventListener('click', function (e) {
      var chip = e.target.closest('[data-gw-term]');
      if (chip) { e.preventDefault(); input.value = chip.getAttribute('data-gw-term'); input.focus(); if (input.value.trim().length >= 2) { doSearch(input.value.trim()); } }
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('.gw-search')) { hide(); } });
  }
  // Mobile bottom-nav search shortcut.
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-gw-search-focus]') && input) {
      e.preventDefault();
      input.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
      setTimeout(function () { input.focus(); }, 250);
    }
  });

  /* ---------- quick view modal ---------- */
  var qv = null;
  function buildQv() {
    if (qv) { return qv; }
    qv = document.createElement('div');
    qv.className = 'gw-qv'; qv.hidden = true;
    qv.innerHTML = '<div class="gw-qv__overlay" data-gw-qv-close></div><div class="gw-qv__panel" role="dialog" aria-modal="true"><button class="gw-qv__close" type="button" data-gw-qv-close aria-label="Close">&times;</button><div class="gw-qv__body"></div></div>';
    body.appendChild(qv);
    return qv;
  }
  function shutQv() { if (qv) { qv.hidden = true; body.classList.remove('gw-qv-open'); } }
  function openQv(id) {
    if (!cfg.ajaxUrl) { return; }
    var m = buildQv(), b = m.querySelector('.gw-qv__body');
    b.innerHTML = '<p class="gw-qv__loading">Loading...</p>'; m.hidden = false; body.classList.add('gw-qv-open');
    fetch(cfg.ajaxUrl + '?action=gw_quickview&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.success !== true) { b.innerHTML = '<p>Unable to load product.</p>'; return; }
        var d = res.data, buyable = (d.purchasable === true && d.in_stock === true);
        var atc = buyable ? '<a href="' + d.add_url + '" class="button add_to_cart_button ajax_add_to_cart" data-product_id="' + d.id + '" data-quantity="1" rel="nofollow">' + (d.add_text || 'Add to cart') + '</a>' : '<a href="' + d.permalink + '" class="button">' + (d.add_text || 'Read more') + '</a>';
        b.innerHTML = '<div class="gw-qv__grid"><div class="gw-qv__media"><img src="' + d.image + '" alt="" /></div><div class="gw-qv__info"><h2>' + d.title + '</h2><div class="gw-qv__price">' + (d.price || '') + '</div><div class="gw-qv__desc">' + (d.excerpt || '') + '</div><div class="gw-qv__actions">' + atc + ' <a class="button button-ghost" href="' + d.permalink + '">View details</a></div></div></div>';
      }).catch(function () { b.innerHTML = '<p>Unable to load product.</p>'; });
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-gw-quickview]');
    if (t) { e.preventDefault(); openQv(t.getAttribute('data-gw-quickview')); return; }
    if (e.target.closest('[data-gw-qv-close]')) { shutQv(); }
  });

  /* ---------- filters drawer ---------- */
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-gw-filters-toggle]')) {
      var p = document.querySelector('[data-gw-filters]');
      if (p) { var open = p.classList.toggle('is-open'); body.classList.toggle('gw-filters-open', open); }
    }
  });

  /* ---------- load more ---------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-gw-loadmore]');
    if (!btn) { return; }
    e.preventDefault();
    var next = btn.getAttribute('data-next');
    if (!next) { return; }
    var label = btn.textContent; btn.textContent = 'Loading...';
    fetch(next, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var list = document.querySelector('ul.products');
      var incoming = doc.querySelectorAll('ul.products li.product');
      if (list && incoming.length) { incoming.forEach(function (li) { list.appendChild(document.importNode(li, true)); }); revealScan(); }
      var nb = doc.querySelector('[data-gw-loadmore]');
      if (nb) { btn.setAttribute('data-next', nb.getAttribute('data-next')); btn.textContent = label; }
      else if (btn.parentNode) { btn.parentNode.removeChild(btn); }
    }).catch(function () { btn.textContent = label; });
  });

  /* ---------- sticky add-to-cart on single ---------- */
  var stickyAtc = document.querySelector('.gw-sticky-atc');
  var mainBtn = document.querySelector('form.cart button[type="submit"], form.cart .single_add_to_cart_button');
  if (stickyAtc && mainBtn && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (en) { stickyAtc.classList.toggle('is-visible', !en[0].isIntersecting); }, { threshold: 0 });
    io.observe(mainBtn);
    stickyAtc.addEventListener('click', function (e) { if (e.target.closest('[data-add-to-cart]')) { e.preventDefault(); mainBtn.click(); } });
  }

  /* ---------- AJAX add-to-cart on single product ---------- */
  (function () {
    var jq = window.jQuery;
    function endpoint() { return (cfg.wcAjax && cfg.wcAjax.length) ? cfg.wcAjax : '/?wc-ajax=add_to_cart'; }
    function apply(fragments) { if (!fragments) { return; } Object.keys(fragments).forEach(function (sel) { document.querySelectorAll(sel).forEach(function (n) { var t = document.createElement('div'); t.innerHTML = fragments[sel]; var r = t.firstElementChild; if (r && n.parentNode) { n.parentNode.replaceChild(r, n); } }); }); }
    document.querySelectorAll('.single-product form.cart').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (form.classList.contains('variations_form') || form.classList.contains('grouped_form')) { return; }
        var addBtn = form.querySelector('button[name="add-to-cart"], input[name="add-to-cart"]');
        var pid = addBtn && addBtn.value ? addBtn.value : '';
        if (!pid) { return; }
        e.preventDefault();
        var data = new FormData(form); data.append('product_id', pid);
        var btn = form.querySelector('.single_add_to_cart_button'); if (btn) { btn.classList.add('loading'); }
        fetch(endpoint(), { method: 'POST', body: data, credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (btn) { btn.classList.remove('loading'); btn.classList.add('added'); }
            if (res && res.error === true && res.product_url) { window.location = res.product_url; return; }
            apply(res ? res.fragments : null);
            if (jq) { jq(document.body).trigger('added_to_cart', [res ? res.fragments : {}, res ? res.cart_hash : '', jq(btn || form)]); } else { openCart(); }
          }).catch(function () { if (btn) { btn.classList.remove('loading'); } form.submit(); });
      });
    });
  })();

  /* ---------- wishlist (localStorage) ---------- */
  var WKEY = 'gw-wishlist';
  var wish = function () { try { return JSON.parse(localStorage.getItem(WKEY) || '[]'); } catch (e) { return []; } };
  function paintWish() {
    var list = wish();
    document.querySelectorAll('[data-gw-wishlist]').forEach(function (b) { b.setAttribute('aria-pressed', list.indexOf(b.getAttribute('data-gw-wishlist')) > -1 ? 'true' : 'false'); });
    document.querySelectorAll('.gw-action--wish .gw-cart__count').forEach(function (c) { c.textContent = String(list.length); });
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-gw-wishlist]');
    if (!b) { return; }
    e.preventDefault();
    var id = b.getAttribute('data-gw-wishlist'), list = wish(), i = list.indexOf(id);
    if (i > -1) { list.splice(i, 1); } else { list.push(id); }
    try { localStorage.setItem(WKEY, JSON.stringify(list)); } catch (err) {}
    paintWish();
  });
  paintWish();

  /* ---------- consultation form ---------- */
  document.querySelectorAll('[data-gw-consult-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var status = form.querySelector('.gw-consult__status');
      var submit = form.querySelector('.gw-consult__submit');
      var data = new FormData(form); data.append('action', 'gw_consult'); data.append('nonce', cfg.nonce || '');
      if (status) { status.className = 'gw-consult__status'; status.textContent = ''; }
      if (submit) { submit.disabled = true; }
      fetch(cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (submit) { submit.disabled = false; }
          var ok = res && res.success === true;
          if (status) { status.className = 'gw-consult__status ' + (ok ? 'is-ok' : 'is-err'); status.textContent = (res && res.data && res.data.message) ? res.data.message : (ok ? 'Received.' : 'Something went wrong.'); }
          if (ok) { form.reset(); }
        }).catch(function () { if (submit) { submit.disabled = false; } if (status) { status.className = 'gw-consult__status is-err'; status.textContent = 'Network error. Please try again or call us.'; } });
    });
  });

  /* ---------- subtle reveal on scroll ---------- */
  function revealScan() {
    if (reduce || !('IntersectionObserver' in window)) { return; }
    var sel = '.gw-cat-card, .gw-collection, .gw-why__card, .gw-journal__card, .gw-join__card, ul.products li.product';
    var els = [].slice.call(document.querySelectorAll(sel)).filter(function (el) { return !el.classList.contains('gw-reveal'); });
    var obs = new IntersectionObserver(function (entries, o) {
      entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); o.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -8%' });
    els.forEach(function (el) { el.classList.add('gw-reveal'); obs.observe(el); });
  }
  if (document.readyState !== 'loading') { revealScan(); } else { document.addEventListener('DOMContentLoaded', revealScan); }
})();
