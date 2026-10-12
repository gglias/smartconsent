/* SmartConsent core. Printed inline at the top of <head>, so it runs before any tag on the page. */
(function (w, d) {
	'use strict';
	var C = w.smartConsentCore || {};
	var LETTER = { preferences: 'p', statistics: 's', marketing: 'm' };
	var dl = w.dataLayer = w.dataLayer || [];
	function gtag() { dl.push(arguments); }
	if (typeof w.gtag !== 'function') { w.gtag = gtag; }

	// Google Consent Mode v2: nothing is allowed until the visitor says so.
	gtag('consent', 'default', {
		ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied',
		personalization_storage: 'denied', functionality_storage: 'granted', security_storage: 'granted', wait_for_update: 500
	});
	gtag('set', 'ads_data_redaction', true);

	/* Saved choice: "<rev>.<letters or ->.<unix time>.<16 hex id>". Anything else, an older revision or an expired
	   choice counts as no choice. */
	function read() {
		var m = d.cookie.match(/(?:^|;\s*)smartconsent=([^;]*)/);
		if (!m) { return null; }
		var p;
		try { p = decodeURIComponent(m[1]).split('.'); } catch (e) { return null; }
		if (p.length !== 4 || !/^\d{1,9}$/.test(p[0]) || !/^(-|[psm]{1,3})$/.test(p[1]) || !/^\d{9,11}$/.test(p[2]) || !/^[a-f0-9]{16}$/.test(p[3])) { return null; }
		if (+p[0] !== C.rev || Date.now() / 1000 - (+p[2]) > C.months * 2629746) { return null; }
		var cats = [];
		for (var k in LETTER) {
			if (p[1].indexOf(LETTER[k]) !== -1) { cats.push(k); }
		}
		return { cats: cats, ts: +p[2], id: p[3] };
	}

	var S = w.SmartConsent = { core: C, consent: null, loaded: {}, ran: {}, letter: LETTER };
	S.has = function (cat) {
		return cat === 'necessary' || !!(S.consent && S.consent.cats.indexOf(cat) !== -1);
	};

	function state(on) { return on ? 'granted' : 'denied'; }
	S.update = function () {
		var mk = S.has('marketing');
		gtag('consent', 'update', {
			analytics_storage: state(S.has('statistics')), ad_storage: state(mk), ad_user_data: state(mk), ad_personalization: state(mk),
			personalization_storage: state(S.has('preferences'))
		});
		dl.push({ event: 'smartconsent_update', smartconsent: { preferences: S.has('preferences'), statistics: S.has('statistics'), marketing: S.has('marketing') } });
	};

	/* ---------- Tags set up in the admin ---------- */
	function addScript(src) {
		var s = d.createElement('script');
		s.async = true;
		s.src = src;
		(d.head || d.documentElement).appendChild(s);
	}
	var LOAD = {
		google: function (t) {
			if (/^GTM-/.test(t.id)) {
				dl.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
				addScript('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(t.id));
			} else {
				addScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(t.id));
				gtag('js', new Date());
				gtag('config', t.id);
			}
		},
		meta: function (t) {
			if (!w.fbq) {
				var n = w.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
				if (!w._fbq) { w._fbq = n; }
				n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
				addScript('https://connect.facebook.net/en_US/fbevents.js');
			}
			w.fbq('init', t.id);
			w.fbq('track', 'PageView');
		},
		clarity: function (t) {
			w.clarity = w.clarity || function () { (w.clarity.q = w.clarity.q || []).push(arguments); };
			addScript('https://www.clarity.ms/tag/' + encodeURIComponent(t.id));
		},
		hotjar: function (t) {
			w.hj = w.hj || function () { (w.hj.q = w.hj.q || []).push(arguments); };
			w._hjSettings = { hjid: +t.id, hjsv: 6 };
			addScript('https://static.hotjar.com/c/hotjar-' + encodeURIComponent(t.id) + '.js?sv=6');
		}
	};
	S.loadTags = function () {
		(C.tags || []).forEach(function (t) {
			var advanced = t.svc === 'google' && t.mode === 'advanced'; // loads at once, Consent Mode keeps it cookieless
			if (S.loaded[t.svc] || !LOAD[t.svc] || !(advanced || S.has(t.cat))) { return; }
			S.loaded[t.svc] = 1;
			if (S.has(t.cat)) { S.ran[t.cat] = 1; }
			LOAD[t.svc](t);
		});
	};

	/* ---------- Scripts held back on the page (blocker) and the admin's own scripts (<template>) ---------- */
	function fresh(old) {
		var s = d.createElement('script');
		for (var i = 0; i < old.attributes.length; i++) {
			var a = old.attributes[i];
			if (a.name !== 'type' && a.name.indexOf('data-smc-') !== 0) { s.setAttribute(a.name, a.value); }
		}
		var type = old.getAttribute('data-smc-type');
		if (type) { s.setAttribute('type', type); }
		if (old.getAttribute('src')) { s.async = false; } else { s.text = old.text; } // keep the page's order
		return s;
	}
	S.activate = function () {
		// Embeds: the address goes back, the placeholder (front.js) goes away.
		if (!/^[a-z0-9]{8,32}$/.test(C.n || '')) { return; } // without this page's mark nothing is ours to run
		var mark = '[data-smc-n="' + C.n + '"]';
		var frames = d.querySelectorAll('iframe[data-smc-src][data-smc-cat]' + mark);
		for (var f = 0; f < frames.length; f++) {
			var fr = frames[f], src = fr.getAttribute('data-smc-src');
			if (!S.has(fr.getAttribute('data-smc-cat')) || !/^(https?:)?\/\//i.test(src)) { continue; }
			S.ran[fr.getAttribute('data-smc-cat')] = 1;
			var ph = fr.previousElementSibling;
			if (ph && ph.hasAttribute('data-smc-ph')) { ph.parentNode.removeChild(ph); }
			if (fr.hasAttribute('data-smc-display')) { fr.style.display = fr.getAttribute('data-smc-display'); }
			['data-smc-src', 'data-smc-cat', 'data-smc-embed', 'data-smc-display', 'data-smc-hidden', 'data-smc-n'].forEach(function (a) { fr.removeAttribute(a); });
			fr.src = src;
		}
		var list = d.querySelectorAll('script[type="text/plain"][data-smc-cat]' + mark + ', template[data-smc-cat]' + mark);
		for (var i = 0; i < list.length; i++) {
			var el = list[i], cat = el.getAttribute('data-smc-cat');
			if (cat === 'necessary' || !S.has(cat) || el.getAttribute('data-smc-done') || !el.parentNode) { continue; }
			S.ran[cat] = 1;
			if (el.tagName === 'TEMPLATE') {
				el.setAttribute('data-smc-done', '1');
				var frag = el.content.cloneNode(true), inner = frag.querySelectorAll('script');
				for (var j = 0; j < inner.length; j++) { inner[j].parentNode.replaceChild(fresh(inner[j]), inner[j]); }
				el.parentNode.insertBefore(frag, el);
			} else {
				el.parentNode.replaceChild(fresh(el), el);
			}
		}
	};

	/* ---------- Start ---------- */
	if (C.scan === 'all') {
		S.consent = { cats: (C.cats || []).filter(function (c) { return c !== 'necessary'; }), ts: 0, id: '', scan: 1 };
	} else if (C.scan !== 'deny') {
		S.consent = read();
		// Global Privacy Control: the browser already said no. front.js saves and logs it.
		if (!S.consent && C.gpc && w.navigator && w.navigator.globalPrivacyControl === true) {
			S.consent = { cats: [], ts: 0, id: '', gpc: 1 };
		}
	}
	if (S.consent) { S.update(); }
	S.loadTags();

	var html = d.documentElement;
	if (!S.consent && C.show) { html.className += ' smc-pending'; }
	if (C.ho) { html.className += ' smc-ho'; }

	if (d.readyState === 'loading') {
		d.addEventListener('DOMContentLoaded', S.activate);
	} else {
		S.activate();
	}
})(window, document);
