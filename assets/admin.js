/* SmartConsent admin. Built from window.smcAdmin; anything typed in the admin is only ever set as text. */
(function () {
	'use strict';
	var B = window.smcAdmin;
	var root = document.getElementById('smc-admin');
	if (!B || !root) { return; }
	var I = B.i18n || {};

	/* ---------- Helpers ---------- */
	function t(k) { return Object.prototype.hasOwnProperty.call(I, k) ? I[k] : k; }
	function fmt(s) {
		var args = Array.prototype.slice.call(arguments, 1), n = 0;
		return String(s).replace(/%(\d)\$s|%s/g, function (m, i) { return String(i ? args[i - 1] : args[n++]); });
	}
	function clone(o) { return JSON.parse(JSON.stringify(o)); }
	/* A unix time as a date in the screen language: 28/09/2026, 22:49. */
	function when(ts) {
		try {
			return new Date(ts * 1000).toLocaleString(B.uiLang === 'el' ? 'el-GR' : 'en-GB', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
		} catch (e) { return new Date(ts * 1000).toISOString().slice(0, 16).replace('T', ' '); }
	}
	function own(o, k) { return !!o && Object.prototype.hasOwnProperty.call(o, k); }

	var ICONS = {
		home: '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
		log: '<path d="M9 5h11M9 12h11M9 19h11M4 5h.01M4 12h.01M4 19h.01"/>',
		svc: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M17.5 14v7M14 17.5h7"/>',
		ck: '<circle cx="12" cy="12" r="9"/><circle cx="9" cy="9.5" r="1"/><circle cx="14.5" cy="14" r="1"/><circle cx="10" cy="15.5" r=".8"/><circle cx="15" cy="8.5" r=".8"/>',
		txt: '<path d="M4 6h16M4 12h10M4 18h13"/>',
		look: '<path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.5-.8 1.5-1.6 0-1.3-1-1.6-1-2.9 0-.9.7-1.5 1.6-1.5H16a5 5 0 0 0 5-5c0-4-4-7-9-7z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10.5" cy="7" r="1"/><circle cx="15" cy="7.5" r="1"/>',
		page: '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M8.5 13h7M8.5 17h5"/>',
		set: '<path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2"/><circle cx="10" cy="17" r="2"/>',
		ok: '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
		warn: '<path d="M12 7.5v6M12 16.5h.01"/>',
		info: '<path d="M12 11v5M12 7.5h.01"/>',
		ext: '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a1 1 0 0 1 1-1h10"/>',
		plus: '<path d="M12 5v14M5 12h14"/>',
		dl: '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
		refresh: '<path d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6"/>',
		trash: '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
		chev: '<path d="M9 6l6 6-6 6"/>'
	};
	var MARK = '<svg viewBox="0 0 25 31" aria-hidden="true"><g fill="#042727"><rect x="6.26" width="18.74" height="6.21"/><rect y="6.21" width="6.26" height="6.21"/><rect x="6.26" y="12.37" width="12.48" height="6.21"/><rect x="18.74" y="18.58" width="6.26" height="6.21"/><rect y="24.79" width="18.74" height="6.21"/></g></svg>';
	function icon(n, size) {
		var s = size || 18;
		return '<svg viewBox="0 0 24 24" width="' + s + '" height="' + s + '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONS[n] + '</svg>';
	}

	/* h('div.cls', { attr, on: { click } }, children…): children are nodes or text; `html` is only for our own icons. */
	function h(sel, attrs) {
		var parts = sel.split('.'), el = document.createElement(parts[0] || 'div');
		if (parts.length > 1) { el.className = parts.slice(1).join(' '); }
		var kids = Array.prototype.slice.call(arguments, 2);
		if (attrs && (attrs.nodeType || typeof attrs !== 'object' || Array.isArray(attrs))) {
			kids.unshift(attrs);
			attrs = null;
		}
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				var v = attrs[k];
				if (v === null || v === undefined || v === false) { return; }
				if (k === 'on') { Object.keys(v).forEach(function (ev) { el.addEventListener(ev, v[ev]); }); }
				else if (k === 'html') { el.innerHTML = v; }
				else if (k === 'text') { el.textContent = v; }
				else if (k === 'value') { el.value = v; }
				else if (k === 'checked' || k === 'disabled' || k === 'selected' || k === 'hidden') { el[k] = !!v; }
				else if (k === 'class') { el.className += (el.className ? ' ' : '') + v; }
				else { el.setAttribute(k, v === true ? '' : v); }
			});
		}
		add(el, kids);
		return el;
	}
	function add(el, kids) {
		kids.forEach(function (c) {
			if (c === null || c === undefined || c === false) { return; }
			if (Array.isArray(c)) { add(el, c); return; }
			el.appendChild(c.nodeType ? c : document.createTextNode(String(c)));
		});
	}
	function ic(n, size) { return h('span.smc-ic', { html: icon(n, size), 'aria-hidden': 'true' }); }

	function ajax(action, data) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', B.nonce);
		Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
		return fetch(B.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json().catch(function () { return { success: false }; }); })
			.then(function (j) {
				if (!j || !j.success) { throw new Error((j && j.data && j.data.message) || t('errGeneric')); }
				return j.data;
			});
	}

	var toastEl = null, toastTimer = null;
	function toast(msg, bad) {
		if (!toastEl) {
			toastEl = h('div.smc-toast', { role: 'status', 'aria-live': 'polite' });
			document.body.appendChild(toastEl);
		}
		toastEl.textContent = msg;
		toastEl.classList.toggle('is-bad', !!bad);
		toastEl.hidden = false;
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { toastEl.hidden = true; }, Math.min(9000, 2600 + msg.length * 35));
	}
	function copy(text) {
		var done = function () { toast(fmt(t('copied'), text)); };
		try {
			navigator.clipboard.writeText(text).then(done, function () { toast(text); });
		} catch (e) { toast(text); }
	}
	function savedMsg(caches) {
		return caches && caches.length ? fmt(t('savedCaches'), caches.join(', ')) : t('saved');
	}

	/* ---------- State ---------- */
	/* PHP sends an empty map as []; keys set on an array would vanish from JSON.stringify. */
	function norm(s) {
		['services', 'cookie_text'].forEach(function (k) { if (!s[k] || Array.isArray(s[k])) { s[k] = {}; } });
		if (!s.policy || typeof s.policy !== 'object') { s.policy = {}; }
		if (!s.policy.pages || Array.isArray(s.policy.pages)) { s.policy.pages = {}; }
		['scripts', 'cookies'].forEach(function (k) { if (!Array.isArray(s[k])) { s[k] = []; } });
		return s;
	}
	var S = norm(clone(B.settings));
	var savedJson = JSON.stringify(S);
	var LANGS = B.langs.map(function (l) { return l.code; });
	var VIEWS = ['home', 'log', 'svc', 'ck', 'txt', 'look', 'page', 'set'];
	var st = {
		view: VIEWS.indexOf(location.hash.slice(1)) !== -1 ? location.hash.slice(1) : 'home',
		lang: LANGS.indexOf(B.siteLang) !== -1 ? B.siteLang : LANGS[0],
		stats: null, check: null, log: null, logAct: '', logQ: '', logPage: 1,
		scan: B.scan, scanning: null, lib: false, shown: {}, edit: null, confirm: '', busy: '', loading: {}, unk: {}
	};
	function dirty() { return JSON.stringify(S) !== savedJson; }
	var LIB = {};
	B.library.forEach(function (s) { LIB[s.id] = s; });

	function catName(c) { return t('cat_' + c); }
	function langName(code) {
		for (var i = 0; i < B.langs.length; i++) { if (B.langs[i].code === code) { return B.langs[i].name; } }
		return code.toUpperCase();
	}

	/* ---------- Frame ---------- */
	var NAV = [
		['home', 'home', 'view_home'], ['log', 'log', 'view_log'],
		['svc', 'svc', 'view_svc', 1], ['ck', 'ck', 'view_ck', 1], ['txt', 'txt', 'view_txt', 1], ['look', 'look', 'view_look', 1], ['page', 'page', 'view_page', 1], ['set', 'set', 'view_set', 1]
	];
	var main = null;

	function go(v) {
		st.view = v;
		st.edit = null;
		st.confirm = '';
		if (history.replaceState) { history.replaceState(null, '', '#' + v); }
		render();
		if (main) { main.scrollTop = 0; }
		window.scrollTo(0, 0);
	}

	function navItem(n) {
		var needs = n[0] === 'page' && !policyReady();
		return h('li', h('button.smc-nav__item', { type: 'button', 'aria-current': st.view === n[0] ? 'page' : null, on: { click: function () { go(n[0]); } } },
			ic(n[1]), h('span.t', t(n[2])),
			n[0] === 'log' && st.stats ? h('span.smc-nav__count', String(st.stats.total.all)) : null,
			needs ? h('span.smc-nav__dot', { title: t('needsAction') }) : null));
	}

	function uiLangSwitch() {
		return h('div.smc-uilang', { role: 'group', 'aria-label': t('screenLang') }, ['el', 'en'].map(function (l) {
			return h('button', {
				type: 'button', 'aria-pressed': B.uiLang === l ? 'true' : 'false', text: l === 'el' ? 'ΕΛ' : 'EN',
				on: { click: function () {
					if (B.uiLang === l) { return; }
					if (dirty() && !window.confirm(t('leaveUnsaved'))) { return; }
					ajax('smc_ui_lang', { lang: l }).then(function () { location.reload(); }, function (e) { toast(e.message, true); });
				} }
			});
		}));
	}

	function render() {
		var y = window.scrollY;
		var focusId = document.activeElement && document.activeElement.id;
		root.textContent = '';
		main = h('main.smc-main', { id: 'smc-main' });
		var app = h('div.smc-app',
			h('nav.smc-nav', { 'aria-label': 'SmartConsent' },
				h('div.smc-nav__brand', h('div.smc-brand', h('span.smc-brand__mark', { html: MARK }), 'SmartConsent'), uiLangSwitch()),
				h('div', h('div.smc-nav__label', t('navGeneral')), h('ul.smc-nav__list', NAV.filter(function (n) { return !n[3]; }).map(navItem))),
				h('div', h('div.smc-nav__label', t('navSetup')), h('ul.smc-nav__list', NAV.filter(function (n) { return n[3]; }).map(navItem))),
				h('div.smc-nav__foot', h('span.smc-avatar', { html: MARK }), h('div', h('b', B.user || 'Smartup'), 'SmartConsent ' + B.version))),
			main);
		root.appendChild(app);
		add(main, [VIEW[st.view]()]);
		if (dirty() || st.view === 'svc' || st.view === 'ck' || st.view === 'txt' || st.view === 'look' || st.view === 'page' || st.view === 'set') {
			main.appendChild(saveBar());
		}
		if (focusId) {
			var f = document.getElementById(focusId);
			if (f && f.focus) { f.focus({ preventScroll: true }); }
		}
		window.scrollTo(0, y);
	}

	function head(title, sub, actions) {
		return h('div.smc-head', h('div', h('h2', title), sub ? h('p', sub) : null), actions ? h('div.smc-head__actions', actions) : null);
	}

	function saveBar() {
		var d = dirty();
		return h('div.smc-savebar' + (d ? '.is-dirty' : ''),
			h('span', d ? t('unsaved') : t('allSaved')),
			h('button.smc-btn.smc-btn--primary', { type: 'button', id: 'smc-save', disabled: !d || st.busy === 'save', text: st.busy === 'save' ? t('saving') : t('save'), on: { click: save } }));
	}

	function save() {
		st.busy = 'save';
		render();
		ajax('smc_save', { settings: JSON.stringify(S) }).then(function (r) {
			applyServer(r);
			st.busy = '';
			render();
			toast(savedMsg(r.caches));
		}, function (e) {
			st.busy = '';
			render();
			toast(e.message, true);
		});
	}
	function applyServer(r) {
		if (r.settings) {
			S = norm(clone(r.settings));
			savedJson = JSON.stringify(S);
		}
		['cookieList', 'usedCats', 'policy', 'policyOk', 'policyUrls', 'privacy'].forEach(function (k) { if (own(r, k)) { B[k] = r[k]; } });
	}

	/* ---------- Small parts ---------- */
	function sw(id, on, label, change, disabled) {
		return h('label.smc-switch',
			h('input', { type: 'checkbox', id: id, checked: on, disabled: !!disabled, 'aria-label': label, on: { change: function (e) { change(e.target.checked); } } }),
			h('span'));
	}
	function opt(id, title, text, on, change) {
		return h('div.smc-opt', h('div', h('b', title), text ? h('span', text) : null), sw(id, on, title, change));
	}
	function field(id, label, input, hint) {
		return h('div.smc-field', h('label', { 'for': id }, label), input, hint ? h('span.smc-hint', hint) : null);
	}
	function input(id, value, change, attrs) {
		return h('input.smc-input', Object.assign({ id: id, type: 'text', value: value, on: { input: function (e) { change(e.target.value); onEdit(); } } }, attrs || {}));
	}
	function area(id, value, change, rows) {
		return h('textarea.smc-input', { id: id, rows: rows || 4, value: value, on: { input: function (e) { change(e.target.value); onEdit(); } } });
	}
	function select(id, value, options, change, disabled) {
		return h('select.smc-input', { id: id, disabled: !!disabled, on: { change: function (e) { change(e.target.value); render(); } } }, options.map(function (o) {
			return h('option', { value: o[0], selected: String(o[0]) === String(value), text: o[1] });
		}));
	}
	/* Typing must not redraw the whole screen (the cursor would jump); only the save bar and the preview follow. */
	function onEdit() {
		var bar = main.querySelector('.smc-savebar');
		if (bar) { bar.parentNode.replaceChild(saveBar(), bar); }
		var pv = main.querySelector('.smc-preview');
		if (pv) { pv.parentNode.replaceChild(preview(), pv); }
	}
	function pill(cls, text) { return h('span.smc-pill.smc-pill--' + cls, text); }
	function catPill(c) { return pill(c, catName(c)); }
	function check(kind, title, text, actions) {
		return h('div.smc-check', h('span.smc-check__ic.is-' + kind, { html: icon(kind === 'ok' ? 'ok' : kind === 'warn' ? 'warn' : 'info', 13) }),
			h('div', h('b', title), text ? h('p', text) : null, actions ? h('div.smc-check__act', actions) : null));
	}
	function callout(kind, children) { return h('div.smc-callout.smc-callout--' + kind, children); }
	function btn(label, attrs, iconName) {
		return h('button.smc-btn', Object.assign({ type: 'button' }, attrs || {}), iconName ? ic(iconName, 16) : null, label);
	}
	function code(text) { return h('code', text); }
	function langTabs() {
		if (LANGS.length < 2) { return null; }
		return h('div.smc-tabs', { role: 'tablist' }, LANGS.map(function (l) {
			return h('button', { type: 'button', role: 'tab', 'aria-selected': st.lang === l ? 'true' : 'false', text: langName(l), on: { click: function () { st.lang = l; st.edit = null; render(); } } });
		}));
	}
	/* A button that asks once more before doing something that can't be undone. */
	function confirmBtn(key, label, sure, run, iconName) {
		var asking = st.confirm === key;
		return btn(asking ? sure : label, { 'class': asking ? 'smc-btn--danger' : '', disabled: st.busy === key, on: { click: function () {
			if (!asking) { st.confirm = key; render(); return; }
			st.confirm = '';
			run();
		} } }, iconName);
	}

	function policyReady() {
		return Object.keys(B.policy || {}).some(function (l) { return B.policy[l].status === 'publish'; });
	}

	/* ---------- Banner preview (same markup and look as the site) ---------- */
	function preview() {
		var L = S.look, tx = function (k) { return (S.texts[k] && S.texts[k][st.lang]) || ''; };
		var box = h('div.smc-pv' + (L.equal ? '.smc-pv--equal' : '') + '.smc-pv--' + L.position);
		['fill', 'fill_text', 'line', 'bg', 'text'].forEach(function (k) {
			if (/^#[0-9a-f]{3,6}$/i.test(L[k])) { box.style.setProperty('--pv-' + k.replace('_', '-'), L[k]); }
		});
		// Same as the site: {purposes} lists the purposes in use (per the last save).
		var purposes = (B.usedCats || []).filter(function (c) { return c !== 'necessary' && tx('purpose_' + c); }).map(function (c) { return tx('purpose_' + c); });
		var joined = purposes.length > 1 ? purposes.slice(0, -1).join(', ') + ' ' + (tx('and') || 'and') + ' ' + purposes[purposes.length - 1] : (purposes[0] || '');
		var p = h('p.smc-pv__text', tx('text').replace(/\s*\{purposes\}/, joined ? ' ' + joined : ''));
		if (B.policyUrls && B.policyUrls[st.lang]) { add(p, [' ', h('a', { href: '#', on: { click: function (e) { e.preventDefault(); } } }, tx('policy')), '.']); }
		add(box, [h('div.smc-pv__title', tx('title')), p,
			h('div.smc-pv__acts', h('span.smc-pv__b.smc-pv__b--w', tx('prefs')), h('span.smc-pv__b.smc-pv__b--rej', tx('reject')), h('span.smc-pv__b.smc-pv__b--f', tx('accept')))]);
		return h('div.smc-preview', h('span.smc-preview__label', t('preview')), box);
	}

	/* ---------- Overview ---------- */
	function loadStats() {
		if (st.stats || st.loading.stats) { return; }
		st.loading.stats = 1;
		ajax('smc_stats').then(function (r) { st.stats = r; st.loading.stats = 0; render(); }, function () { st.stats = { total: { all: 0, accept: 0, reject: 0, custom: 0, gpc: 0 }, langs: {} }; st.loading.stats = 0; render(); });
	}
	function loadCheck(force) {
		if ((st.check && !force) || st.loading.check) { return; }
		st.loading.check = 1;
		if (force) { st.check = null; render(); }
		ajax('smc_check').then(function (r) { st.check = r; st.loading.check = 0; render(); }, function () { st.check = { ok: false }; st.loading.check = 0; render(); });
	}
	function pct(n, all) { return all ? Math.round(n * 100 / all) : 0; }

	function vHome() {
		loadStats();
		loadCheck();
		var on = !!S.enabled;
		var total = st.stats ? st.stats.total : null;
		function tile(label, key) {
			var v = total ? total[key] : null;
			return h('div.smc-stat', h('span.smc-stat__label', label),
				h('span.smc-stat__value.num', v === null ? '…' : (key === 'all' ? String(v) : pct(v, total.all) + '%'), key !== 'all' && v !== null ? h('small', ' ' + v) : null));
		}

		var checks = [];
		var g = S.services.google || {};
		if (g.on && g.id) {
			checks.push(check('ok', fmt(t('chkTagOk'), g.id), g.mode === 'advanced' ? t('chkTagAdvanced') : t('chkTagBasic')));
		} else {
			checks.push(check('warn', t('chkTagNone'), t('chkTagNoneText'), btn(t('setUpTag'), { 'class': 'smc-btn--sm', on: { click: function () { go('svc'); } } })));
		}
		var foreign = (st.check && st.check.foreign) || [];
		if (st.check === null || st.loading.check) {
			checks.push(check('info', t('chkRunning'), null));
		} else if (!st.check.ok) {
			checks.push(check('info', t('chkFailed'), t('chkFailedText'), btn(t('checkAgain'), { 'class': 'smc-btn--sm', on: { click: function () { loadCheck(true); } } })));
		} else {
			if (foreign.length) {
				checks.push(check('warn', fmt(t('chkForeign'), foreign.map(function (f) { return LIB[f] ? LIB[f].name : f; }).join(', ')), S.consent.autoblock ? t('chkForeignBlocked') : t('chkForeignOpen'),
					btn(t('checkAgain'), { 'class': 'smc-btn--sm', on: { click: function () { loadCheck(true); } } })));
			}
			if (st.check.gated) {
				checks.push(check('warn', t('chkGated'), t('chkGatedText')));
			}
			// The "Cookies" link, per language (footers are often one per language).
			var L = st.check.links || {};
			var langsOf = function (want) { return Object.keys(L).filter(function (l) { return L[l] === want; }).map(langName); };
			var yes = langsOf('yes'), no = langsOf('no'), text = langsOf('text'), failed = langsOf('failed');
			var again = btn(t('checkAgain'), { 'class': 'smc-btn--sm', on: { click: function () { loadCheck(true); } } });
			var copyBtn = btn(t('copyShortcode'), { 'class': 'smc-btn--sm', on: { click: function () { copy('[smartconsent]'); } } }, 'copy');
			if (!no.length && !text.length && yes.length) {
				checks.push(check('ok', t('chkLinkOk'), Object.keys(L).length > 1 ? fmt(t('chkLinkOkIn'), yes.join(', ')) : t('chkLinkOkText')));
			} else if (!no.length && !text.length && !yes.length) {
				checks.push(check('info', t('chkLinkUnknown'), t('chkFailedText'), again));
			} else {
				if (text.length) {
					checks.push(check('warn', fmt(t('chkLinkText'), text.join(', ')), t('chkLinkTextText'), [btn(t('copyLink'), { 'class': 'smc-btn--sm', on: { click: function () { copy('#smartconsent'); } } }, 'copy'), again]));
				}
				if (no.length) {
					checks.push(check('warn', Object.keys(L).length > 1 ? fmt(t('chkLinkNoneIn'), no.join(', ')) : t('chkLinkNone'),
						t('chkLinkNoneText') + (yes.length ? ' ' + fmt(t('chkLinkFoundIn'), yes.join(', ')) : ''), [copyBtn, again]));
				}
			}
			if (failed.length && (yes.length || no.length || text.length)) {
				checks.push(check('info', fmt(t('chkLinkFailed'), failed.join(', ')), null));
			}
		}
		checks.push(policyReady() ? check('ok', t('chkPolicyOk'), t('chkPolicyOkText'))
			: check('warn', t('chkPolicyNone'), t('chkPolicyNoneText'), btn(Object.keys(B.policy || {}).length ? t('openPolicy') : t('createNow'), { 'class': 'smc-btn--sm smc-btn--primary', on: { click: function () { go('page'); } } })));
		if (st.scan) {
			var leaks = scanLeaks(st.scan);
			checks.push(leaks.length ? check('warn', fmt(t('chkScanLeak'), leaks.join(', ')), t('chkScanLeakText'), btn(t('openScan'), { 'class': 'smc-btn--sm', on: { click: function () { go('svc'); } } }))
				: check('ok', t('chkScanOk'), fmt(t('chkScanWhen'), when(st.scan.time), st.scan.pages)));
		} else {
			checks.push(check('info', t('chkScanNever'), t('chkScanNeverText'), btn(t('scanNow'), { 'class': 'smc-btn--sm', on: { click: function () { go('svc'); runScan(); } } })));
		}
		checks.push(S.consent.gpc ? check('ok', t('chkGpcOk'), t('chkGpcOkText')) : check('info', t('chkGpcOff'), t('chkGpcOffText')));
		if (on && (B.usedCats || []).length < 2) {
			checks.unshift(check('info', t('chkNothingOptional'), t('chkNothingOptionalText')));
		}
		if (B.blockError && B.blockError.time) {
			checks.unshift(check('warn', t('chkBlockError'), fmt(t('chkBlockErrorText'), when(B.blockError.time), String(B.blockError.error || ''))));
		}

		var langRows = [];
		if (st.stats) {
			Object.keys(st.stats.langs).forEach(function (l) {
				var r = st.stats.langs[l];
				langRows.push(h('div.smc-langbar', h('div.smc-langbar__top', h('b', langName(l)), h('span.num', fmt(t('nAnswers'), r.all))),
					h('div.smc-bar',
						h('i', { style: 'width:' + pct(r.accept, r.all) + '%;background:#46e9d4' }),
						h('i', { style: 'width:' + pct(r.reject + r.gpc, r.all) + '%;background:#98a2b3' }),
						h('i', { style: 'width:' + pct(r.custom, r.all) + '%;background:#f79009' }))));
			});
		}

		return [
			head(t('view_home'), t('homeSub'), h('a.smc-btn', { href: B.previewUrl, target: '_blank', rel: 'noopener' }, ic('ext', 16), t('previewSite'))),
			h('div.smc-master' + (on ? '.is-on' : ''),
				h('label.smc-switch.smc-switch--lg', h('input', { type: 'checkbox', id: 'smc-master', checked: on, 'aria-label': t('masterLabel'), disabled: st.busy === 'enable', on: { change: function (e) {
					var want = e.target.checked;
					st.busy = 'enable';
					ajax('smc_enable', { on: want ? '1' : '0' }).then(function (r) {
						S.enabled = r.enabled;
						var saved = JSON.parse(savedJson);
						saved.enabled = r.enabled;
						savedJson = JSON.stringify(saved);
						st.busy = '';
						st.check = null;
						render();
						toast((r.enabled ? t('enabledOn') : t('enabledOff')) + (r.caches && r.caches.length ? ' ' + fmt(t('cachesCleared'), r.caches.join(', ')) : ''));
					}, function (err) { st.busy = ''; render(); toast(err.message, true); });
				} } }), h('span')),
				h('div.smc-master__txt', h('b', on ? t('masterOn') : t('masterOff')), h('span', on ? t('masterOnText') : t('masterOffText')))),
			h('div.smc-stats', tile(t('statAll'), 'all'), tile(t('statAccept'), 'accept'), tile(t('statReject'), 'reject'), tile(t('statCustom'), 'custom')),
			h('div.smc-two',
				h('div.smc-card', h('div.smc-card__head', h('div', h('h3', t('checksTitle')), h('p.smc-sub', t('checksSub')))), h('div.smc-card__body', checks)),
				h('div.smc-card', h('div.smc-card__head', h('div', h('h3', t('byLang')), h('p.smc-sub', t('last30')))),
					h('div.smc-card__body.smc-stack',
						langRows.length ? langRows : h('p.smc-sub', st.stats ? t('noAnswers') : t('loading')),
						h('div.smc-legend', h('span', { style: '--c:#46e9d4' }, t('statAccept')), h('span', { style: '--c:#98a2b3' }, t('statReject')), h('span', { style: '--c:#f79009' }, t('statCustom'))),
						h('hr.smc-sep'),
						h('div', h('b', t('reaskTitle')), h('p.smc-sub', t('reaskText')),
							h('div.smc-row-actions', confirmBtn('reask', t('reaskBtn'), t('reaskSure'), function () {
								st.busy = 'reask';
								render();
								ajax('smc_reask').then(function (r) {
									B.rev = r.rev;
									st.busy = '';
									render();
									toast(t('reaskDone') + (r.caches && r.caches.length ? ' ' + fmt(t('cachesCleared'), r.caches.join(', ')) : ''));
								}, function (e) { st.busy = ''; render(); toast(e.message, true); });
							}, 'refresh'), h('span.smc-sub', fmt(t('revNow'), B.rev)))))))
		];
	}

	/* ---------- Consent log ---------- */
	var logTimer = null;
	function loadLog() {
		st.loading.log = 1;
		ajax('smc_log', { action_f: st.logAct, q: st.logQ, page: st.logPage }).then(function (r) { st.log = r; st.loading.log = 0; render(); }, function (e) { st.loading.log = 0; toast(e.message, true); });
	}
	function actName(a) { return t('act_' + a); }
	function vLog() {
		if (!st.log && !st.loading.log) { loadLog(); }
		var L = st.log, counts = L ? L.counts : {};
		function tab(key, label) {
			return h('button', { type: 'button', role: 'tab', 'aria-selected': st.logAct === key ? 'true' : 'false', on: { click: function () { st.logAct = key; st.logPage = 1; loadLog(); } } },
				label, ' ', h('span.c', String(counts[key || 'all'] || 0)));
		}
		var pages = L ? Math.max(1, Math.ceil(L.total / B.perPage)) : 1;
		var form = h('form', { method: 'post', action: B.postUrl, target: '_blank' },
			h('input', { type: 'hidden', name: 'action', value: 'smc_export' }), h('input', { type: 'hidden', name: 'nonce', value: B.nonce }),
			h('input', { type: 'hidden', name: 'action_f', value: st.logAct }), h('input', { type: 'hidden', name: 'q', value: st.logQ }),
			h('button.smc-btn', { type: 'submit' }, ic('dl', 16), t('exportCsv')));
		return [
			head(t('view_log'), fmt(t('logSub'), S.consent.retention), form),
			h('div.smc-toolbar',
				h('div.smc-tabs', { role: 'tablist' }, tab('', t('all')), tab('accept', t('act_accept')), tab('reject', t('act_reject')), tab('custom', t('act_custom')), tab('gpc', t('act_gpc'))),
				h('input.smc-input.smc-search', { type: 'search', id: 'smc-logq', placeholder: t('searchId'), value: st.logQ, 'aria-label': t('searchId'), on: { input: function (e) {
					st.logQ = e.target.value.replace(/[^a-f0-9]/gi, '').toLowerCase();
					st.logPage = 1;
					clearTimeout(logTimer);
					logTimer = setTimeout(loadLog, 350);
				} } })),
			h('div.smc-table-wrap', h('table.smc-table',
				h('thead', h('tr', [t('date'), t('consentId'), t('language'), t('choice'), t('categories'), t('revision')].map(function (c) { return h('th', c); }))),
				h('tbody', !L ? h('tr', h('td', { colspan: 6 }, t('loading'))) : !L.rows.length ? h('tr', h('td', { colspan: 6 }, t('logEmpty'))) : L.rows.map(function (r) {
					return h('tr', h('td.num', r.date), h('td', h('span.smc-mono', r.id)), h('td', r.lang.toUpperCase()),
						h('td', pill(r.act, actName(r.act))), h('td.smc-muted', r.cats.map(catName).join(', ')), h('td.num', 'v' + r.rev));
				})))),
			h('div.smc-pager', h('span', fmt(t('pageOf'), st.logPage, pages)),
				h('div.smc-row-actions',
					btn(t('prev'), { 'class': 'smc-btn--sm', disabled: st.logPage <= 1, on: { click: function () { st.logPage--; loadLog(); } } }),
					btn(t('next'), { 'class': 'smc-btn--sm', disabled: st.logPage >= pages, on: { click: function () { st.logPage++; loadLog(); } } }))),
			callout('info', t('logIdNote'))
		];
	}

	/* ---------- Services & scripts ---------- */
	function svcRow(s, body, side) {
		return h('div.smc-svc', h('div.smc-svc__logo', s.name.replace(/[^A-Z]/g, '').slice(0, 3) || s.name.slice(0, 2)),
			h('div.smc-svc__main', body), side ? h('div.smc-svc__side', side) : null);
	}
	function svcTitle(s, badges) {
		return h('div.smc-svc__top', h('b', s.name), badges);
	}
	function tagRow(s) {
		var row = S.services[s.id] || (S.services[s.id] = { on: 0, id: '' });
		var valid = !row.id || new RegExp(s.pattern, 'i').test(row.id);
		var extra = [
			h('div.smc-grid2',
				field('smc-tag-' + s.id, t('tagId'), input('smc-tag-' + s.id, row.id, function (v) { row.id = v.trim(); if (!row.id) { row.on = 0; } }, { placeholder: s.placeholder, 'class': valid ? '' : 'is-bad', spellcheck: 'false', autocomplete: 'off' }), valid ? t('tagIdHint_' + s.id) : t('tagIdBad')),
				s.id === 'google' ? field('smc-tag-cat', t('category'), select('smc-tag-cat', row.cat || 'statistics', [['statistics', catName('statistics')], ['marketing', catName('marketing')]], function (v) { row.cat = v; })) : h('div'))
		];
		if (s.id === 'google') {
			extra.push(h('div.smc-flabel', t('whenLoads')), h('div.smc-grid2', ['basic', 'advanced'].map(function (m) {
				return h('label.smc-radio' + ((row.mode || 'basic') === m ? '.is-on' : ''),
					h('input', { type: 'radio', name: 'smc-gmode', value: m, checked: (row.mode || 'basic') === m, on: { change: function () { row.mode = m; render(); } } }),
					h('div', h('b', t('mode_' + m)), h('span', t('mode_' + m + '_d'))));
			})));
			if (row.mode === 'advanced') { extra.push(callout('warn', t('modeAdvancedWarn'))); }
		}
		return svcRow(s, [svcTitle(s, catPill(row.cat || s.cat)), h('p', s.desc), h('div.smc-svc__extra', extra)],
			sw('smc-on-' + s.id, !!row.on && !!row.id, s.name, function (v) {
				if (v && !row.id) { toast(t('tagNeedsId'), true); render(); return; }
				row.on = v ? 1 : 0;
				render();
			}));
	}
	function autoRow(s) {
		var row = S.services[s.id];
		var on = row ? !!row.on : s.detected;
		return svcRow(s, [svcTitle(s, [s.detected ? h('span.smc-badge.smc-badge--auto', t('detected')) : h('span.smc-badge', t('addedByHand')), s.cookies.length ? catPill(s.cat) : h('span.smc-badge', t('noCookies'))]), h('p', s.desc)],
			sw('smc-on-' + s.id, on, s.name, function (v) { S.services[s.id] = { on: v ? 1 : 0 }; render(); }));
	}
	function scriptRow(sc, i) {
		var ro = !B.canScripts;
		return h('div.smc-script',
			h('div.smc-script__top', h('b', sc.name || t('untitledScript')), catPill(sc.cat),
				h('span.smc-grow'),
				sw('smc-sc-on-' + i, !!sc.on, sc.name || t('untitledScript'), function (v) { sc.on = v ? 1 : 0; render(); }, ro),
				ro ? null : btn(t('delete'), { 'class': 'smc-btn--sm smc-btn--ghost', on: { click: function () { S.scripts.splice(i, 1); render(); } } }, 'trash')),
			h('div.smc-grid3',
				field('smc-sc-name-' + i, t('scriptName'), input('smc-sc-name-' + i, sc.name, function (v) { sc.name = v; }, { disabled: ro })),
				field('smc-sc-cat-' + i, t('category'), select('smc-sc-cat-' + i, sc.cat, B.cats.filter(function (c) { return c !== 'necessary'; }).map(function (c) { return [c, catName(c)]; }), function (v) { sc.cat = v; }, ro)),
				field('smc-sc-where-' + i, t('scriptWhere'), select('smc-sc-where-' + i, sc.where, [['head', t('where_head')], ['footer', t('where_footer')]], function (v) { sc.where = v; }, ro))),
			field('smc-sc-code-' + i, t('scriptCode'), h('textarea.smc-input.smc-mono', { id: 'smc-sc-code-' + i, rows: 5, value: sc.code, disabled: ro, spellcheck: 'false', on: { input: function (e) { sc.code = e.target.value; onEdit(); } } }), t('scriptCodeHint')));
	}

	function vSvc() {
		var tags = B.library.filter(function (s) { return s.kind === 'tag'; });
		var autos = B.library.filter(function (s) { return s.kind === 'auto'; });
		var shownTags = tags.filter(function (s) { return s.id === 'google' || (S.services[s.id] && (S.services[s.id].on || S.services[s.id].id)) || st.shown[s.id]; });
		var shownAutos = autos.filter(function (s) { return s.detected || own(S.services, s.id) || st.shown[s.id]; });
		var hiddenLib = tags.filter(function (s) { return shownTags.indexOf(s) === -1; }).concat(autos.filter(function (s) { return shownAutos.indexOf(s) === -1; }));
		return [
			head(t('view_svc'), t('svcSub'), [
				btn(st.scanning ? t('scanning') : t('scanSite'), { id: 'smc-scan-btn', disabled: !!st.scanning, on: { click: runScan } }, 'refresh'),
				btn(t('addService'), { 'class': 'smc-btn--primary', on: { click: function () { st.lib = !st.lib; render(); } } }, 'plus')]),
			scanCard(),
			st.lib ? h('div.smc-card.smc-mb', h('div.smc-card__head', h('div', h('h3', t('library')), h('p.smc-sub', t('librarySub')))),
				h('div.smc-card__body.smc-chips', hiddenLib.map(function (s) {
					return btn(s.name, { 'class': 'smc-btn--sm', on: { click: function () {
						st.shown[s.id] = 1;
						if (s.kind === 'auto') { S.services[s.id] = { on: 1 }; }
						st.lib = false;
						render();
					} } }, 'plus');
				}), B.canScripts ? btn(t('ownScript'), { 'class': 'smc-btn--sm', on: { click: function () {
					S.scripts.push({ id: 's' + Date.now().toString(36), on: 1, name: '', cat: 'statistics', where: 'footer', code: '' });
					st.lib = false;
					render();
				} } }, 'plus') : null)) : null,
			h('div.smc-card', shownTags.map(tagRow), shownAutos.map(autoRow)),
			S.scripts.length || !B.canScripts ? h('div.smc-card.smc-mt', h('div.smc-card__head', h('div', h('h3', t('ownScripts')), h('p.smc-sub', B.canScripts ? t('ownScriptsSub') : t('ownScriptsLocked')))),
				h('div.smc-card__body', S.scripts.map(scriptRow))) : null
		];
	}

	/* ---------- Site scan ---------- */
	function wild(pattern) {
		return new RegExp('^' + pattern.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*') + '$');
	}
	function cookieNames(doc) {
		return (doc || document).cookie.split(';').map(function (p) { return p.split('=')[0].trim(); }).filter(Boolean);
	}
	// The admin's own browser cookies are not the site's: sign-in, screen settings, our own consent cookie.
	var ADMIN = [/^wordpress_/, /^wp-settings/, /^wp_lang$/, /^comment_author/, /^wp-postpass/, /^smartconsent$/];
	function inList(name, cats) {
		return (B.cookieList || []).some(function (c) { return (!cats || cats.indexOf(c.cat) !== -1) && wild(c.name).test(name); });
	}
	function scanLeaks(scan) {
		return (scan.before || []).filter(function (n) { return !inList(n, ['necessary']) && !ADMIN.some(function (r) { return r.test(n); }); });
	}
	function dropCookie(n) {
		var host = location.hostname.split('.');
		var doms = [''];
		for (var i = 0; i < host.length - 1; i++) { doms.push(host.slice(i).join('.')); }
		doms.forEach(function (d) { document.cookie = n + '=; path=/; max-age=0' + (d ? '; domain=.' + d : ''); });
	}

	function runScan() {
		if (st.scanning) { return; }
		st.scanning = { done: 0, total: 0, pass: 'deny' };
		if (st.view !== 'svc') { go('svc'); } else { render(); }
		ajax('smc_scan_start').then(function (r) {
			var urls = r.urls || [];
			st.scanning.total = urls.length * 2;
			// Cookies this browser already has from visiting the site would look like new ones: those not needed go.
			cookieNames().forEach(function (n) {
				if (!ADMIN.some(function (x) { return x.test(n); }) && !inList(n, ['necessary'])) { dropCookie(n); }
			});
			var start = cookieNames();
			var before = [], after = [], hosts = {}, foreign = {}, failed = 0;
			var frame = h('iframe', { title: 'scan', 'aria-hidden': 'true', tabindex: '-1', style: 'position:fixed;left:-10000px;top:0;width:1280px;height:900px;border:0;visibility:hidden' });
			document.body.appendChild(frame);

			function visit(url, mode) {
				return new Promise(function (resolve) {
					var done = false;
					var finish = function (ok) {
						if (done) { return; }
						done = true;
						clearTimeout(timer);
						if (!ok) { failed++; resolve(); return; }
						setTimeout(function () {
							try {
								var win = frame.contentWindow, doc = frame.contentDocument;
								if (mode === 'deny') {
									(win.performance.getEntriesByType('resource') || []).forEach(function (e) {
										try {
											var u = new URL(e.name);
											if (u.hostname && u.hostname !== location.hostname && /^https?:$/.test(u.protocol)) { hosts[u.hostname] = 1; }
										} catch (x) { /* not a URL */ }
									});
									Array.prototype.forEach.call(doc.querySelectorAll('script[data-smc-blocked]'), function (s) { foreign[s.getAttribute('data-smc-blocked')] = 1; });
								}
							} catch (x) { failed++; }
							resolve();
						}, 2500); // time for tags to write their cookies
					};
					var timer = setTimeout(function () { finish(false); }, 20000);
					frame.onload = function () {
						try {
							if (frame.contentWindow.location.href === 'about:blank') { return; } // the empty frame's own load
						} catch (x) { /* another origin after a redirect: read nothing */ }
						finish(true);
					};
					frame.src = url + (url.indexOf('?') === -1 ? '?' : '&') + 'smc_scan=' + encodeURIComponent(r.token) + '&smc_mode=' + mode;
				}).then(function () {
					st.scanning.done++;
					var bar = document.querySelector('.smc-scanbar i');
					if (bar) { bar.style.width = Math.round(st.scanning.done * 100 / st.scanning.total) + '%'; }
					var lbl = document.querySelector('.smc-scan-progress');
					if (lbl) { lbl.textContent = scanLabel(); }
				});
			}
			function pass(mode) {
				st.scanning.pass = mode;
				return urls.reduce(function (p, u) { return p.then(function () { return visit(u, mode); }); }, Promise.resolve());
			}
			return pass('deny').then(function () {
				before = cookieNames().filter(function (n) { return start.indexOf(n) === -1; });
				return pass('all');
			}).then(function () {
				after = cookieNames().filter(function (n) { return start.indexOf(n) === -1 && before.indexOf(n) === -1; });
				frame.parentNode.removeChild(frame);
				// The admin never consented to what "accept all" wrote in this browser.
				after.forEach(dropCookie);
				before.forEach(function (n) { if (!inList(n, ['necessary'])) { dropCookie(n); } });
				var unknown = before.concat(after).filter(function (n) { return !inList(n) && !ADMIN.some(function (x) { return x.test(n); }); });
				var res = { pages: urls.length, failed: failed, before: before, after: after, unknown: unknown, hosts: Object.keys(hosts), foreign: Object.keys(foreign) };
				return ajax('smc_scan_save', { result: JSON.stringify(res) });
			});
		}).then(function (saved) {
			st.scan = saved;
			st.scanning = null;
			render();
			toast(t('scanDone'));
		}, function (e) {
			st.scanning = null;
			render();
			toast(e.message || t('errGeneric'), true);
		});
	}
	function scanLabel() {
		var s = st.scanning;
		return fmt(t('scanProgress'), Math.min(s.total, s.done + 1), s.total || '…', s.pass === 'deny' ? t('passDeny') : t('passAll'));
	}
	function hostNote(hn) {
		if (/(^|\.)fonts\.(googleapis|gstatic)\.com$/.test(hn)) { return t('hostFonts'); }
		if (/(^|\.)(gravatar\.com|wp\.com|w\.org)$/.test(hn)) { return t('hostWp'); }
		if (/(^|\.)challenges\.cloudflare\.com$/.test(hn)) { return t('hostTurnstile'); }
		return t('hostOther');
	}
	function scanCard() {
		if (st.scanning) {
			var s = st.scanning;
			return h('div.smc-card.smc-mb', h('div.smc-card__head', h('div', h('h3', t('scanRunning')), h('p.smc-sub.smc-scan-progress', scanLabel()))),
				h('div.smc-card__body', h('div.smc-bar.smc-scanbar', h('i', { style: 'width:' + (s.total ? Math.round(s.done * 100 / s.total) : 0) + '%;background:#46e9d4' })), h('p.smc-sub', t('scanStay'))));
		}
		var r = st.scan;
		if (!r) { return null; }
		var leaks = scanLeaks(r);
		var rows = [];
		var necBefore = (r.before || []).filter(function (n) { return inList(n, ['necessary']); });
		rows.push(leaks.length ? check('warn', fmt(t('scanLeak'), leaks.join(', ')), t('scanLeakText'))
			: check('ok', t('scanNoLeak'), necBefore.length ? fmt(t('scanOnlyNec'), necBefore.join(', ')) : t('scanNothing')));
		var known = (r.after || []).filter(function (n) { return inList(n); });
		if (known.length) { rows.push(check('ok', t('scanAfterOk'), known.join(', '))); }
		(r.foreign || []).forEach(function (f) {
			rows.push(check('warn', fmt(t('scanForeign'), LIB[f] ? LIB[f].name : f), S.consent.autoblock ? t('scanForeignText') : t('chkForeignOpen')));
		});
		(r.hosts || []).forEach(function (hn) { rows.push(check(/fonts\.|challenges\.cloudflare|gravatar|w\.org|wp\.com/.test(hn) ? 'info' : 'warn', fmt(t('scanHost'), hn), hostNote(hn))); });
		(r.unknown || []).forEach(function (n) {
			rows.push(check('warn', fmt(t('scanUnknown'), n), t('scanUnknownText'), [
				select('smc-unk-' + n, st.unk[n] || 'necessary', B.cats.map(function (c) { return [c, catName(c)]; }), function (v) { st.unk[n] = v; }),
				btn(t('addToTable'), { 'class': 'smc-btn--sm smc-btn--primary', on: { click: function () {
					var cat = st.unk[n] || 'necessary';
					if (!S.cookies.some(function (c) { return c.name === n; })) {
						var empty = {};
						LANGS.forEach(function (l) { empty[l] = ''; });
						S.cookies.push({ name: n, provider: '', cat: cat, purpose: clone(empty), duration: clone(empty) });
					}
					r.unknown = r.unknown.filter(function (x) { return x !== n; });
					st.edit = { name: n, custom: true };
					go('ck');
					toast(t('addedDescribe'));
				} } })
			]));
		});
		if (r.failed) { rows.push(check('info', fmt(t('scanFailed'), r.failed), t('scanFailedText'))); }
		return h('div.smc-card.smc-mb', h('div.smc-card__head', h('div', h('h3', t('scanResult')), h('p.smc-sub', fmt(t('scanWhen'), r.pages, when(r.time)))),
			btn(t('scanAgain'), { 'class': 'smc-btn--sm', on: { click: runScan } }, 'refresh')), h('div.smc-card__body', rows));
	}

	/* ---------- Cookies ---------- */
	function srcBadge(c) {
		if (c.source === 'custom') { return h('span.smc-badge', t('src_custom')); }
		var s = LIB[c.source];
		return h('span.smc-badge' + (s && s.kind !== 'tag' ? '.smc-badge--auto' : ''), s ? s.name : c.source);
	}
	function rowsForTable() {
		var list = (B.cookieList || []).map(function (c) { return clone(c); });
		// Pending changes (not saved yet) show at once.
		list.forEach(function (c) {
			var o = S.cookie_text[c.name];
			if (o) {
				['purpose', 'duration'].forEach(function (k) { if (o[k] && o[k][st.lang]) { c[k][st.lang] = o[k][st.lang]; } });
			}
			var cu = c.source === 'custom' ? S.cookies.filter(function (x) { return x.name === c.name; })[0] : null;
			if (c.source === 'custom' && !cu) { c.gone = true; }
			if (cu) { Object.assign(c, clone(cu)); }
		});
		S.cookies.forEach(function (cu) {
			if (!list.some(function (c) { return c.name === cu.name; })) { list.push(Object.assign(clone(cu), { source: 'custom', pending: true })); }
		});
		return list.filter(function (c) { return !c.gone; });
	}
	function editRow(c) {
		var custom = c.source === 'custom';
		var L = st.lang;
		var target;
		if (custom) {
			target = S.cookies.filter(function (x) { return x.name === c.name; })[0];
		} else {
			// A library cookie gets its own texts only once something is typed (opening the editor changes nothing).
			var ov = S.cookie_text[c.name];
			var shown = { purpose: {}, duration: {} };
			shown.purpose[L] = (ov && ov.purpose && ov.purpose[L]) || c.purpose[L] || '';
			shown.duration[L] = (ov && ov.duration && ov.duration[L]) || c.duration[L] || '';
			target = {
				name: c.name,
				purpose: new Proxy(shown.purpose, { set: function (o, k, v) { o[k] = v; writeOv(c.name, 'purpose', k, v); return true; } }),
				duration: new Proxy(shown.duration, { set: function (o, k, v) { o[k] = v; writeOv(c.name, 'duration', k, v); return true; } })
			};
		}
		return h('tr.smc-edit', h('td', { colspan: 6 }, h('div.smc-editbox',
			custom ? h('div.smc-grid3',
				field('smc-ck-name', t('cookieName'), input('smc-ck-name', target.name, function (v) { target.name = v.trim(); st.edit.name = target.name; }, { spellcheck: 'false' }), t('cookieNameHint')),
				field('smc-ck-prov', t('provider'), input('smc-ck-prov', target.provider, function (v) { target.provider = v; }, { placeholder: t('thisSite') })),
				field('smc-ck-cat', t('category'), select('smc-ck-cat', target.cat, B.cats.map(function (x) { return [x, catName(x)]; }), function (v) { target.cat = v; }))) : null,
			h('div.smc-grid2',
				field('smc-ck-pur', fmt(t('purposeIn'), langName(L)), area('smc-ck-pur', target.purpose[L] || '', function (v) { target.purpose[L] = v; }, 2)),
				field('smc-ck-dur', fmt(t('durationIn'), langName(L)), input('smc-ck-dur', target.duration[L] || '', function (v) { target.duration[L] = v; }))),
			h('div.smc-row-actions',
				btn(t('done'), { 'class': 'smc-btn--sm smc-btn--primary', on: { click: function () { st.edit = null; render(); } } }),
				custom ? btn(t('delete'), { 'class': 'smc-btn--sm smc-btn--ghost', on: { click: function () { S.cookies = S.cookies.filter(function (x) { return x !== target; }); st.edit = null; render(); } } }, 'trash')
					: btn(t('resetText'), { 'class': 'smc-btn--sm smc-btn--ghost', on: { click: function () { delete S.cookie_text[c.name]; st.edit = null; render(); toast(t('resetNote')); } } }),
				h('span.smc-sub', t('editNote'))))));
	}
	function writeOv(name, key, lang, value) {
		var o = S.cookie_text[name] || (S.cookie_text[name] = { purpose: {}, duration: {} });
		o[key] = o[key] || {};
		o[key][lang] = value;
	}
	function vCk() {
		var rows = rowsForTable();
		var n = {};
		rows.forEach(function (c) { n[c.cat] = (n[c.cat] || 0) + 1; });
		var summary = Object.keys(n).map(function (c) { return n[c] + ' ' + catName(c).toLowerCase(); }).join(' · ');
		var trs = [];
		rows.forEach(function (c) {
			var editing = st.edit && st.edit.name === c.name;
			trs.push(h('tr' + (editing ? '.is-editing' : ''),
				h('td.smc-td-name', h('span.smc-mono', c.name), h('div.smc-td-badges', srcBadge(c), c.edited || S.cookie_text[c.name] ? h('span.smc-badge', t('edited')) : null)),
				h('td', c.provider || t('thisSite')),
				h('td.smc-muted.smc-td-wide', (c.purpose && c.purpose[st.lang]) || h('em', t('noText'))), h('td.smc-td-dur', (c.duration && c.duration[st.lang]) || '—'),
				h('td.smc-nw', catPill(c.cat)),
				h('td.smc-nw', btn(t('edit'), { 'class': 'smc-btn--sm', on: { click: function () { st.edit = editing ? null : { name: c.name }; render(); } } }))));
			if (editing) { trs.push(editRow(c)); }
		});
		return [
			head(t('view_ck'), t('ckSub'), btn(t('addCookie'), { on: { click: function () {
				var empty = {};
				LANGS.forEach(function (l) { empty[l] = ''; });
				var name = 'cookie_' + (S.cookies.length + 1);
				S.cookies.push({ name: name, provider: '', cat: 'necessary', purpose: clone(empty), duration: clone(empty) });
				st.edit = { name: name };
				render();
			} } }, 'plus')),
			h('div.smc-toolbar', langTabs(), h('span.smc-sub', fmt(t('ckCount'), rows.length) + (summary ? ' · ' + summary : ''))),
			dirty() ? callout('info', t('ckDirty')) : null,
			h('div.smc-table-wrap', h('table.smc-table',
				h('thead', h('tr', [t('cookie'), t('provider'), t('purpose'), t('duration'), t('category'), ''].map(function (x) { return h('th', x); }))),
				h('tbody', trs))),
			callout('info', [t('ckTableNote'), ' ', code('[smartconsent_cookies]')])
		];
	}

	/* ---------- Texts ---------- */
	var TEXT_MAIN = [['title', 'txtTitle'], ['text', 'txtText', 1, 'txtTextHint'], ['prefs', 'txtPrefs'], ['save', 'txtSave'], ['reject', 'txtReject'], ['accept', 'txtAccept'], ['policy', 'txtPolicy'], ['link', 'txtLink', 0, 'txtLinkHint'], ['always', 'txtAlways'], ['consent_id', 'txtConsentId']];
	var TEXT_PURPOSE = [['purpose_statistics', 'txtPurposeStatistics'], ['purpose_marketing', 'txtPurposeMarketing'], ['purpose_preferences', 'txtPurposePreferences'], ['and', 'txtAnd']];
	var TEXT_PH = [['ph_title', 'txtPhTitle', 0, 'txtPhTitleHint'], ['ph_btn', 'txtPhBtn'], ['ph_text', 'txtPhText', 1]];
	var TEXT_TABLE = [['th_cookie', 'txtThCookie'], ['th_provider', 'txtThProvider'], ['th_purpose', 'txtThPurpose'], ['th_duration', 'txtThDuration'], ['this_site', 'txtThisSite']];
	function textField(def) {
		var k = def[0], id = 'smc-t-' + k;
		var map = S.texts[k] || (S.texts[k] = {});
		var ph = B.defaults.texts[k] ? B.defaults.texts[k][st.lang] || '' : '';
		var inp = def[2] ? area(id, map[st.lang] || '', function (v) { map[st.lang] = v; }, 5) : input(id, map[st.lang] || '', function (v) { map[st.lang] = v; });
		inp.setAttribute('placeholder', ph);
		return field(id, t(def[1]), inp, def[3] ? t(def[3]) : null);
	}
	function vTxt() {
		var used = B.usedCats || ['necessary'];
		var catFields = [];
		B.cats.forEach(function (c) {
			catFields.push(h('div.smc-grid2', textField(['cat_' + c, fmt(t('txtCatName'), catName(c))].concat(used.indexOf(c) === -1 ? [0, 'txtCatUnused'] : [])), textField(['cat_' + c + '_d', fmt(t('txtCatDesc'), catName(c)), 1])));
		});
		return [
			head(t('view_txt'), t('txtSub')),
			h('div.smc-edit-grid',
				h('div.smc-form',
					langTabs(),
					h('div.smc-grid1', TEXT_MAIN.slice(0, 2).map(textField)),
					h('div.smc-grid2', TEXT_MAIN.slice(2).map(textField)),
					h('details.smc-details', h('summary', t('txtPurposes')), h('div.smc-form', h('p.smc-sub', t('txtPurposesSub')), h('div.smc-grid2', TEXT_PURPOSE.map(textField)))),
					h('details.smc-details', h('summary', t('txtCats')), h('div.smc-form', catFields)),
					h('details.smc-details', h('summary', t('txtPh')), h('div.smc-form', h('p.smc-sub', t('txtPhSub')), h('div.smc-grid2', TEXT_PH.slice(0, 2).map(textField)), textField(TEXT_PH[2]))),
					h('details.smc-details', h('summary', t('txtTable')), h('div.smc-grid2', TEXT_TABLE.map(textField)))),
				preview())
		];
	}

	/* ---------- Appearance ---------- */
	function colorField(k, label) {
		var id = 'smc-c-' + k;
		var chip = h('i', { style: 'background:' + S.look[k] });
		return h('div.smc-field', h('label', { 'for': id }, label), h('div.smc-swatch', h('input', { type: 'color', value: S.look[k], 'aria-label': label, on: { input: function (e) {
			S.look[k] = e.target.value;
			document.getElementById(id).value = e.target.value;
			onEdit();
		} } }), chip, h('input', { id: id, value: S.look[k], maxlength: 7, spellcheck: 'false', on: { input: function (e) {
			if (/^#[0-9a-f]{6}$/i.test(e.target.value)) { S.look[k] = e.target.value.toLowerCase(); chip.style.background = S.look[k]; onEdit(); }
		} } })));
	}
	function vLook() {
		var L = S.look;
		return [
			head(t('view_look'), t('lookSub')),
			h('div.smc-edit-grid',
				h('div.smc-form',
					field('smc-pos', t('position'), select('smc-pos', L.position, [['left', t('pos_left')], ['right', t('pos_right')], ['center', t('pos_center')]], function (v) { L.position = v; }), t('positionHint')),
					h('div.smc-grid2', colorField('fill', t('cFill')), colorField('fill_text', t('cFillText'))),
					h('div.smc-grid2', colorField('line', t('cLine')), colorField('bg', t('cBg'))),
					h('div.smc-grid2', colorField('text', t('cText')), h('div.smc-field', h('span.smc-flabel', ' '), btn(t('resetColors'), { on: { click: function () {
						['fill', 'fill_text', 'line', 'bg', 'text'].forEach(function (k) { L[k] = B.defaults.look[k]; });
						render();
					} } }))),
					h('div.smc-card.smc-card--pad',
						opt('smc-equal', t('equalTitle'), t('equalText'), !!L.equal, function (v) { L.equal = v ? 1 : 0; render(); }),
						opt('smc-ho', t('hideTitle'), t('hideText'), !!L.hide_others, function (v) { L.hide_others = v ? 1 : 0; render(); }))),
				preview())
		];
	}

	/* ---------- Policy page ---------- */
	function vPage() {
		var P = B.policy || {};
		var PL = B.policyLangs && B.policyLangs.length ? B.policyLangs : LANGS; // the site's languages only
		var made = Object.keys(P);
		var missing = PL.filter(function (l) { return !P[l]; });
		var pageOpts = [['0', t('choosePage')]].concat(B.pages.map(function (p) { return [String(p.id), p.title]; }));
		return [
			head(t('view_page'), t('pageSub')),
			h('div.smc-two',
				h('div.smc-card.smc-card--pad.smc-stack',
					h('div', h('h3', t('cookiePolicy')), h('p.smc-sub', made.length ? t('policyHas') : t('policyNone'))),
					made.length ? h('div.smc-links', made.map(function (l) {
						var p = P[l];
						return h('div', h('span', langName(l) + ' · ' + p.title), pill(p.status === 'publish' ? 'accept' : 'custom', p.status === 'publish' ? t('published') : t('draft')),
							h('span.smc-grow'), h('a', { href: p.edit, target: '_blank', rel: 'noopener' }, t('editPage')), p.status === 'publish' ? h('a', { href: p.view, target: '_blank', rel: 'noopener' }, t('viewPage')) : null);
					})) : null,
					made.length && made.some(function (l) { return P[l].status !== 'publish'; }) ? callout('warn', t('policyDraft')) : null,
					made.filter(function (l) { return B.wpml && P[l].wpml !== l; }).map(function (l) {
						return callout('warn', P[l].wpml ? fmt(t('policyWrongLang'), P[l].title, langName(l), langName(P[l].wpml)) : fmt(t('policyNoLang'), P[l].title, langName(l)));
					}),
					B.wpml && B.policyOk === false ? h('div.smc-row-actions', btn(st.busy === 'policy' ? t('creating') : t('policyRepair'), { 'class': 'smc-btn--primary', disabled: st.busy === 'policy' || dirty(), on: { click: function () {
						st.busy = 'policy';
						render();
						ajax('smc_policy_create').then(function (r) {
							applyServer(r);
							st.busy = '';
							render();
							toast(B.policyOk ? t('policyRepaired') : t('errGeneric'), !B.policyOk);
						}, function (e) { st.busy = ''; render(); toast(e.message, true); });
					} } }, 'refresh'), h('span.smc-sub', t('policyRepairText'))) : null,
					B.wpml && made.length ? h('p.smc-sub', t('policyWpmlNote')) : null,
					missing.length ? h('div', h('p.smc-sub', made.length ? fmt(t('policyMissing'), missing.map(langName).join(', ')) : t('policyCreateText')),
						h('div.smc-row-actions', btn(st.busy === 'policy' ? t('creating') : (made.length ? t('createMissing') : t('createPages')), { 'class': 'smc-btn--primary', disabled: st.busy === 'policy' || dirty(), on: { click: function () {
							st.busy = 'policy';
							render();
							ajax('smc_policy_create').then(function (r) {
								applyServer(r);
								st.busy = '';
								render();
								toast(t('policyCreated'));
							}, function (e) { st.busy = ''; render(); toast(e.message, true); });
						} } }, 'page'), dirty() ? h('span.smc-sub', t('saveFirst')) : null)) : null,
					h('details.smc-details', h('summary', t('useExisting')), h('div.smc-form', PL.map(function (l) {
						var cur = S.policy.pages[l] ? String(S.policy.pages[l]) : '0';
						return field('smc-pp-' + l, langName(l), select('smc-pp-' + l, cur, pageOpts, function (v) {
							if (v === '0') { delete S.policy.pages[l]; } else { S.policy.pages[l] = +v; }
						}));
					})))),
				h('div.smc-card.smc-card--pad.smc-stack',
					h('div', h('h3', t('privacyPolicy')), h('p.smc-sub', t('privacySub'))),
					h('div.smc-links', PL.map(function (l) {
						var u = B.privacy && B.privacy[l];
						return h('div', h('span', langName(l)), u ? h('a', { href: u, target: '_blank', rel: 'noopener' }, t('viewPage')) : h('span.smc-sub', t('noPrivacy')));
					})),
					h('div', h('a.smc-btn.smc-btn--sm', { href: B.privacyEdit, target: '_blank', rel: 'noopener' }, ic('ext', 14), t('privacySettings'))))),
			h('h3.smc-h3', t('shortcodes')),
			h('div.smc-sclist', [['[smartconsent]', 'sc1'], ['[smartconsent label="…"]', 'sc2'], ['#smartconsent', 'sc3'], ['[smartconsent_cookies]', 'sc4']].map(function (x) {
				return h('div.smc-scitem', h('div', code(x[0]), h('p', t(x[1]))), btn(t('copy'), { 'class': 'smc-btn--sm', on: { click: function () { copy(x[0]); } } }, 'copy'));
			}))
		];
	}

	/* ---------- Settings ---------- */
	function vSet() {
		var c = S.consent;
		return [
			head(t('view_set'), t('setSub')),
			h('div.smc-card.smc-card--pad.smc-narrow',
				h('div.smc-opt', h('div', h('b', t('months')), h('span', t('monthsText'))), select('smc-months', c.months, [[6, fmt(t('nMonths'), 6)], [12, fmt(t('nMonths'), 12)]], function (v) { c.months = +v; })),
				h('div.smc-opt', h('div', h('b', t('retention')), h('span', t('retentionText'))), select('smc-ret', c.retention, [[6, fmt(t('nMonths'), 6)], [12, fmt(t('nMonths'), 12)], [24, fmt(t('nMonths'), 24)]], function (v) { c.retention = +v; })),
				opt('smc-gpc', t('gpcTitle'), t('gpcText'), !!c.gpc, function (v) { c.gpc = v ? 1 : 0; render(); }),
				opt('smc-purge', t('purgeTitle'), t('purgeText'), !!c.purge, function (v) { c.purge = v ? 1 : 0; render(); }),
				opt('smc-autoblock', t('blockTitle'), t('blockText'), !!c.autoblock, function (v) { c.autoblock = v ? 1 : 0; render(); }),
				opt('smc-logon', t('logTitle'), t('logText'), !!c.log, function (v) { c.log = v ? 1 : 0; render(); })),
			h('div.smc-narrow.smc-mt', callout('info', [t('previewNote'), ' ', h('a', { href: B.previewUrl, target: '_blank', rel: 'noopener' }, '?smartconsent_preview=1')]))
		];
	}

	var VIEW = { home: vHome, log: vLog, svc: vSvc, ck: vCk, txt: vTxt, look: vLook, page: vPage, set: vSet };

	window.addEventListener('beforeunload', function (e) {
		if (dirty()) { e.preventDefault(); e.returnValue = ''; }
	});
	render();
})();
