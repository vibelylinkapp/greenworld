/**
 * GreenWorld Setup Wizard runtime.
 * Progressive step navigation + plugin install / demo import / finish AJAX.
 * Vanilla JS, no dependencies. Localized data arrives on window.GreenWorldWizard.
 */
(function () {
	'use strict';

	var cfg = window.GreenWorldWizard || {};
	var root = document.getElementById('gw-wizard');
	if (!root) {
		return;
	}

	var panels = Array.prototype.slice.call(root.querySelectorAll('.gw-wizard__panel'));
	var steps = Array.prototype.slice.call(root.querySelectorAll('.gw-wizard__steps li'));
	var total = panels.length;
	var current = 1;

	function show(step) {
		if (step < 1) { step = 1; }
		if (step > total) { step = total; }
		current = step;
		panels.forEach(function (p) {
			var n = parseInt(p.getAttribute('data-panel'), 10);
			var active = (n === step);
			p.hidden = !active;
			p.setAttribute('aria-hidden', active ? 'false' : 'true');
		});
		steps.forEach(function (li) {
			var n = parseInt(li.getAttribute('data-step'), 10);
			li.classList.toggle('is-active', n === step);
			li.classList.toggle('is-done', n < step);
		});
		if (step === 2) { renderPlugins(); }
	}

	function next() { show(current + 1); }

	function post(action, data) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', cfg.nonce || '');
		if (data) {
			Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
		}
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (r) { return r.json(); });
	}

	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
		});
	}

	function renderPlugins() {
		var list = document.getElementById('gw-plugin-list');
		if (!list || list.getAttribute('data-rendered') === '1') { return; }
		var plugins = cfg.plugins || [];
		list.innerHTML = '';
		plugins.forEach(function (pl) {
			var li = document.createElement('li');
			li.className = 'gw-plugin';
			li.setAttribute('data-slug', pl.slug);
			var label = pl.required ? ' (required)' : '';
			li.innerHTML =
				'<span class="gw-plugin__name">' + escapeHtml(pl.name) + label + '</span>' +
				'<span class="gw-plugin__status" data-status>Pending</span>';
			list.appendChild(li);
		});
		list.setAttribute('data-rendered', '1');
	}

	function setStatus(slug, text, state) {
		var el = document.querySelector('.gw-plugin[data-slug="' + slug + '"] [data-status]');
		if (el) {
			el.textContent = text;
			el.setAttribute('data-state', state || '');
		}
	}

	function installAll(btn) {
		renderPlugins();
		var plugins = cfg.plugins || [];
		btn.disabled = true;
		var i = 0;
		function step() {
			if (i >= plugins.length) {
				btn.disabled = false;
				show(3);
				var log = document.getElementById('gw-activate-log');
				if (log) { log.textContent = 'All done — required plugins installed and activated.'; }
				return;
			}
			var pl = plugins[i];
			setStatus(pl.slug, 'Installing…', 'busy');
			post('greenworld_install_plugin', { slug: pl.slug }).then(function (res) {
				if (res && res.success) {
					setStatus(pl.slug, 'Active', 'ok');
				} else {
					setStatus(pl.slug, (res && res.data && res.data.message) ? res.data.message : 'Skipped', 'warn');
				}
				i += 1;
				step();
			}).catch(function () {
				setStatus(pl.slug, 'Failed', 'error');
				i += 1;
				step();
			});
		}
		step();
	}

	function importDemo(btn) {
		var log = document.getElementById('gw-import-log');
		btn.disabled = true;
		if (log) { log.textContent = 'Importing demo content — this can take a moment…'; }
		post('greenworld_import_demo', { part: 'all' }).then(function (res) {
			btn.disabled = false;
			if (res && res.success) {
				if (log) { log.textContent = 'Demo content imported successfully.'; }
			} else {
				if (log) { log.textContent = (res && res.data && res.data.message) ? res.data.message : 'Import finished with warnings.'; }
			}
		}).catch(function () {
			btn.disabled = false;
			if (log) { log.textContent = 'Import failed. You can import manually from Tools → Import.'; }
		});
	}

	function finish(link) {
		var target = cfg.finishUrl || link.getAttribute('href') || '#';
		post('greenworld_finish_wizard', {}).then(function () {
			window.location.href = target;
		}).catch(function () {
			window.location.href = target;
		});
	}

	root.addEventListener('click', function (e) {
		var t = e.target && e.target.closest ? e.target.closest('button, a') : null;
		if (!t || !root.contains(t)) { return; }
		if (t.classList.contains('gw-next')) {
			e.preventDefault();
			next();
		} else if (t.classList.contains('gw-install-all')) {
			e.preventDefault();
			installAll(t);
		} else if (t.classList.contains('gw-import')) {
			e.preventDefault();
			importDemo(t);
		} else if (t.id === 'gw-finish') {
			e.preventDefault();
			finish(t);
		}
	});

	show(1);
})();
