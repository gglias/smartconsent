/* SmartConsent banner. Texts come from the settings and are only ever set as text, never as HTML. */
(function (w, d) {
	'use strict';
	var S = w.SmartConsent, F = w.smartConsent;
	if (!S || !F || !d.body) { return; }
	var C = S.core, T = F.texts || {}, html = d.documentElement;
	var root = null, view = 'main', draft = {}, opener = null;
	var ORDER = ['necessary', 'preferences', 'statistics', 'marketing'];
	var added = false; // a category this page brought that the server didn't know yet

	/* A category held back on this page but not known to the banner yet (an embed seen for the first time)
	   joins the choices, so "Accept all" and the preferences cover it too. */
	Array.prototype.forEach.call(d.querySelectorAll('[data-smc-cat][data-smc-n="' + (C.n || '-') + '"]'), function (e) {
		var c = e.getAttribute('data-smc-cat');
		if (ORDER.indexOf(c) !== -1 && F.cats.indexOf(c) === -1) {
			F.cats.push(c);
			added = true;
			F.cats.sort(function (a, b) { return ORDER.indexOf(a) - ORDER.indexOf(b); });
		}
	});

	/* The stylesheet is loaded only when something needs it (the banner or a placeholder). */
	var cssWaiting = null;
	function css(ready) {
		var link = d.getElementById('smartconsent-css');
		if (link && (link.sheet || !cssWaiting)) { ready(); return; }
		if (!cssWaiting) {
			cssWaiting = [];
			link = d.createElement('link');
			link.id = 'smartconsent-css';
			link.rel = 'stylesheet';
			link.href = F.css;
			var done = function () {
				var q = cssWaiting || [];
				cssWaiting = null;
				q.forEach(function (fn) { fn(); });
			};
			link.onload = done;
			link.onerror = done;
			setTimeout(done, 3000); // never leave the visitor without the banner
			d.head.appendChild(link);
		}
		cssWaiting.push(ready);
	}

	/* ---------- Saving ---------- */
	function newId() {
		var a = new Uint8Array(8), s = '';
		if (w.crypto && w.crypto.getRandomValues) {
			w.crypto.getRandomValues(a);
		} else {
			for (var i = 0; i < 8; i++) { a[i] = Math.floor(Math.random() * 256); }
		}
		for (var j = 0; j < 8; j++) { s += (a[j] < 16 ? '0' : '') + a[j].toString(16); }
		return s;
	}

	function writeCookie(c) {
		var letters = '';
		['preferences', 'statistics', 'marketing'].forEach(function (k) {
			if (c.cats.indexOf(k) !== -1) { letters += S.letter[k]; }
		});
		d.cookie = C.cookie + '=' + C.rev + '.' + (letters || '-') + '.' + c.ts + '.' + c.id +
			'; path=/; max-age=' + Math.floor(C.months * 2629746) + '; SameSite=Lax' + (w.location.protocol === 'https:' ? '; Secure' : '');
	}

	/* Cookies of a category the visitor no longer allows, on this host and every parent domain. */
	function purge(cats) {
		var names = d.cookie.split(';').map(function (p) { return p.split('=')[0].trim(); });
		var host = w.location.hostname.split('.'), domains = [''];
		for (var i = 0; i < host.length - 1; i++) { domains.push(host.slice(i).join('.')); }
		cats.forEach(function (cat) {
			(C.purge[cat] || []).forEach(function (prefix) {
				names.forEach(function (n) {
					if (n && n.indexOf(prefix) === 0) {
						domains.forEach(function (dom) {
							d.cookie = n + '=; path=/; max-age=0' + (dom ? '; domain=.' + dom : '');
						});
					}
				});
			});
		});
	}

	function log(action, cats, id) {
		if (!F.rest || C.scan || F.preview || !w.fetch) { return; }
		try {
			w.fetch(F.rest, {
				method: 'POST', credentials: 'omit', keepalive: true,
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ id: id, action: action, cats: cats, lang: F.lang, rev: C.rev })
			}).catch(function () {});
		} catch (e) { /* logging must never get in the way */ }
	}

	function save(action, cats) {
		var prev = S.consent && !S.consent.gpc ? S.consent.cats.slice() : [];
		var c = { cats: cats, ts: Math.floor(Date.now() / 1000), id: (S.consent && S.consent.id) || newId() };
		var revoked = prev.filter(function (k) { return cats.indexOf(k) === -1; });
		var reload = revoked.some(function (k) { return S.ran[k]; });
		S.consent = c;
		if (!C.scan) { writeCookie(c); }
		S.update();
		S.loadTags();
		S.activate();
		log(action, cats, c.id);
		html.className = html.className.replace(/\s*smc-(pending|open)\b/g, '');
		close();
		if (revoked.length && C.purge) { purge(revoked); }
		// Scripts that already ran can't be unloaded; a fresh page is the only way to really stop them.
		if (reload) { setTimeout(function () { w.location.reload(); }, 150); }
	}

	function choices() {
		return F.cats.filter(function (k) { return k !== 'necessary' && draft[k]; });
	}
	function all() {
		return F.cats.filter(function (k) { return k !== 'necessary'; });
	}

	/* ---------- Banner ---------- */
	/* "{purposes}" in the text: the purposes in use here, e.g. "for traffic statistics and for marketing". */
	function bannerText() {
		var list = F.cats.filter(function (c) { return c !== 'necessary' && T['purpose_' + c]; }).map(function (c) { return T['purpose_' + c]; });
		var joined = list.length > 1 ? list.slice(0, -1).join(', ') + ' ' + (T.and || 'and') + ' ' + list[list.length - 1] : (list[0] || '');
		return String(T.text || '').replace(/\s*\{purposes\}/, joined ? ' ' + joined : '');
	}
	function el(tag, cls, text) {
		var e = d.createElement(tag);
		if (cls) { e.className = cls; }
		if (text) { e.textContent = text; }
		return e;
	}
	function btn(cls, act, text) {
		var b = el('button', 'smc__btn ' + cls, text);
		b.type = 'button';
		b.setAttribute('data-act', act);
		return b;
	}

	function build() {
		var L = F.look || {};
		root = el('div', 'smc smc--' + (L.position || 'left') + (L.equal ? ' smc--equal' : ''));
		root.id = 'smartconsent';
		root.setAttribute('role', 'dialog');
		root.setAttribute('aria-modal', 'false');
		root.setAttribute('aria-labelledby', 'smc-title');
		root.setAttribute('aria-describedby', 'smc-text');
		root.setAttribute('data-lenis-prevent', '');
		root.hidden = true;
		['fill', 'fill_text', 'line', 'bg', 'text'].forEach(function (k) {
			if (L[k] && /^#[0-9a-f]{3,6}$/i.test(L[k])) { root.style.setProperty('--smc-' + k.replace('_', '-'), L[k]); }
		});

		var panel = el('div', 'smc__panel');
		var title = el('h2', 'smc__title', T.title);
		title.id = 'smc-title';
		title.tabIndex = -1;
		var body = el('div', 'smc__body');
		var text = el('p', 'smc__text', bannerText());
		text.id = 'smc-text';
		if (F.policy && /^https?:\/\//.test(F.policy)) {
			var a = el('a', '', T.policy);
			a.href = F.policy;
			text.appendChild(d.createTextNode(' '));
			text.appendChild(a);
			text.appendChild(d.createTextNode('.'));
		}
		body.appendChild(text);

		var cats = el('div', 'smc__cats');
		cats.hidden = true;
		F.cats.forEach(function (k) {
			var row = el('div', 'smc__cat');
			var top = el('div', 'smc__cat-row');
			var name = el('button', 'smc__cat-name');
			name.type = 'button';
			name.setAttribute('aria-expanded', 'false');
			name.setAttribute('aria-controls', 'smc-more-' + k);
			name.innerHTML = '<svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M4 2l4 4-4 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
			name.appendChild(d.createTextNode(T['cat_' + k] || k));
			top.appendChild(name);
			if (k === 'necessary') {
				top.appendChild(el('span', 'smc__always', T.always));
			} else {
				var tog = el('label', 'smc__tog');
				var input = el('input');
				input.type = 'checkbox';
				input.id = 'smc-cat-' + k;
				input.setAttribute('data-cat', k);
				input.setAttribute('aria-label', T['cat_' + k] || k);
				tog.appendChild(input);
				tog.appendChild(el('span'));
				top.appendChild(tog);
			}
			var more = el('div', 'smc__cat-more');
			more.id = 'smc-more-' + k;
			more.hidden = true;
			more.appendChild(el('p', '', T['cat_' + k + '_d']));
			var list = (F.cookies && F.cookies[k]) || [];
			if (list.length) { more.appendChild(el('p', 'smc__ck', list.join(', '))); }
			name.addEventListener('click', function () {
				var open = more.hidden;
				more.hidden = !open;
				name.setAttribute('aria-expanded', open ? 'true' : 'false');
				row.classList.toggle('is-open', open);
			});
			row.appendChild(top);
			row.appendChild(more);
			cats.appendChild(row);
		});
		var idLine = el('p', 'smc__id');
		idLine.hidden = true;
		cats.appendChild(idLine);
		body.appendChild(cats);

		var actions = el('div', 'smc__actions');
		actions.appendChild(btn('smc__btn--line smc__btn--wide', 'prefs', T.prefs));
		actions.appendChild(btn('smc__btn--line smc__btn--reject', 'reject', T.reject));
		actions.appendChild(btn('smc__btn--fill', 'accept', T.accept));

		panel.appendChild(title);
		panel.appendChild(body);
		panel.appendChild(actions);
		root.appendChild(panel);

		root.addEventListener('click', function (e) {
			var b = e.target.closest ? e.target.closest('[data-act]') : null;
			if (!b) { return; }
			var act = b.getAttribute('data-act');
			if (act === 'prefs') { show('prefs'); return; }
			if (act === 'accept') { save('accept', all()); return; }
			if (act === 'reject') { save('reject', []); return; }
			if (act === 'save') {
				var c = choices();
				save(c.length === all().length && c.length ? 'accept' : (c.length ? 'custom' : 'reject'), c);
			}
		});
		root.addEventListener('change', function (e) {
			var k = e.target.getAttribute && e.target.getAttribute('data-cat');
			if (k) { draft[k] = e.target.checked; }
		});
		root.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && S.consent && !S.consent.gpc) { close(); }
		});
		// Page scrollers (Lenis, Ohio's smooth scroll) must not take the wheel inside the panel.
		['wheel', 'touchmove'].forEach(function (t) {
			root.addEventListener(t, function (e) { e.stopPropagation(); }, { passive: true });
		});
		d.body.insertBefore(root, d.body.firstChild);
	}

	function show(v) {
		if (!root) { build(); }
		view = v;
		var prefs = v === 'prefs';
		root.querySelector('.smc__cats').hidden = !prefs;
		root.classList.toggle('smc--prefs', prefs);
		var wide = root.querySelector('.smc__btn--wide');
		wide.textContent = prefs ? T.save : T.prefs;
		wide.setAttribute('data-act', prefs ? 'save' : 'prefs');
		if (prefs) {
			F.cats.forEach(function (k) {
				if (k === 'necessary') { return; }
				draft[k] = S.has(k) && !(S.consent && S.consent.gpc);
				var input = root.querySelector('#smc-cat-' + k);
				if (input) { input.checked = draft[k]; }
			});
			var idLine = root.querySelector('.smc__id');
			if (S.consent && S.consent.id) {
				idLine.textContent = (T.consent_id || 'ID') + ': ' + S.consent.id;
				idLine.hidden = false;
			} else {
				idLine.hidden = true;
			}
		}
		css(function () { root.hidden = false; });
	}

	/* ---------- Placeholders for embeds held back by the blocker ---------- */
	function placeholders() {
		Array.prototype.forEach.call(d.querySelectorAll('iframe[data-smc-src][data-smc-cat][data-smc-n="' + (C.n || '-') + '"]'), function (fr) {
			var cat = fr.getAttribute('data-smc-cat');
			if (S.has(cat) || fr.hasAttribute('data-smc-hidden')) { return; }
			var name = (F.embeds && F.embeds[fr.getAttribute('data-smc-embed')]) || '';
			var ph = el('div', 'smc-ph');
			ph.setAttribute('data-smc-ph', '');
			ph.setAttribute('role', 'region');
			ph.setAttribute('aria-label', (T.ph_title || '%s').replace('%s', name));
			// Same place and size as the frame: absolutely placed inside a responsive wrapper, or its own width/height.
			var cs = w.getComputedStyle(fr);
			// Only plain pixel numbers count: width="100%" is not 100 pixels.
			var px = function (v) { return /^\s*\d+(\.\d+)?(px)?\s*$/i.test(v || '') ? parseFloat(v) : 0; };
			var fw = px(fr.getAttribute('width')), fh = px(fr.getAttribute('height'));
			if (cs.position === 'absolute') {
				ph.style.position = 'absolute';
				['top', 'left', 'width', 'height'].forEach(function (k) { ph.style[k] = cs[k]; });
			} else {
				var r = fr.getBoundingClientRect();
				ph.style.width = r.width ? r.width + 'px' : (fw ? fw + 'px' : '100%');
				if (fw && fh) { ph.style.aspectRatio = fw + ' / ' + fh; } else { ph.style.height = (r.height || fh || 300) + 'px'; }
			}
			ph.appendChild(el('p', 'smc-ph__title', (T.ph_title || '%s').replace('%s', name)));
			ph.appendChild(el('p', 'smc-ph__text', T.ph_text));
			var acts = el('div', 'smc-ph__acts');
			var ok = el('button', 'smc-ph__btn', T.ph_btn);
			ok.type = 'button';
			ok.addEventListener('click', function () {
				var cats = (S.consent && !S.consent.gpc ? S.consent.cats.slice() : []);
				if (cats.indexOf(cat) === -1) { cats.push(cat); }
				save(cats.length === all().length ? 'accept' : 'custom', cats);
			});
			var more = el('button', 'smc-ph__link', T.prefs);
			more.type = 'button';
			more.addEventListener('click', function () { opener = more; show('prefs'); });
			acts.appendChild(ok);
			acts.appendChild(more);
			ph.appendChild(acts);
			fr.setAttribute('data-smc-hidden', '1');
			fr.setAttribute('data-smc-display', fr.style.display || '');
			fr.style.display = 'none';
			fr.parentNode.insertBefore(ph, fr);
		});
	}

	function close() {
		if (!root) { return; }
		root.hidden = true;
		html.className = html.className.replace(/\s*smc-open\b/g, '');
		if (opener && opener.focus) {
			try { opener.focus(); } catch (e) { /* the link may be gone */ }
		}
		opener = null;
	}

	/* "Cookies" links (shortcode, #smartconsent, .smartconsent-open): open the preferences. Capture phase, so
	   themes that take over # links (smooth scroll, one-page menus) don't get it first. */
	d.addEventListener('click', function (e) {
		var a = e.target.closest ? e.target.closest('a[href$="#smartconsent"], .smartconsent-open, .js-smartconsent') : null;
		if (!a) { return; }
		e.preventDefault();
		e.stopPropagation();
		opener = a;
		if (!/\bsmc-open\b/.test(html.className)) { html.className += ' smc-open'; }
		show('prefs');
		var title = root.querySelector('.smc__title');
		if (title) { title.focus(); }
	}, true);

	/* ---------- Start ---------- */
	var waiting = Array.prototype.some.call(d.querySelectorAll('iframe[data-smc-src][data-smc-cat][data-smc-n="' + (C.n || '-') + '"]'), function (fr) {
		return !S.has(fr.getAttribute('data-smc-cat'));
	});
	if (waiting) {
		css(placeholders); // measured and drawn once the styles are there
	}
	if (S.consent && S.consent.gpc) {
		save('gpc', []); // stored, so the banner doesn't come back; the visitor can still change it from the link
	} else if (F.preview || ((C.show || added) && !S.consent)) {
		if (!/\bsmc-pending\b/.test(html.className)) { html.className += ' smc-pending'; }
		show('main');
	}
})(window, document);
