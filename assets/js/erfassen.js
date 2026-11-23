// jugi-rms – Erfassen (Handy zuerst). Daten kommen als JSON aus index.php (#erf-data).
// Ablauf: 2 Buchstaben → Treffer (eindeutig = automatisch) → Resultat → Speichern.
// - Suche im Browser (Anfang von Vor-/Nachname oder Initialen, ä = a = ae), Kinder vom heutigen Trainingstag zuerst
// - Speichern per fetch ohne Neuladen; Fokus springt synchron zurück aufs Namensfeld → Tastatur bleibt offen
// - Zeiten (Einheit s): 80 oder 1:20; «:»-Taste im Feld, weil die iPhone-Zahlentastatur keinen Doppelpunkt hat
// - Plausibilität mit Vorschlägen («Komma vergessen?», «1:20 gemeint?»)
// - ohne Netz: Einträge in localStorage, werden automatisch nachgeschickt
(function () {
    'use strict';
    var root = document.getElementById('erf');
    var dataEl = document.getElementById('erf-data');
    if (!root || !dataEl) return;
    var DATA = JSON.parse(dataEl.textContent);
    var SPRITE = (document.currentScript && document.currentScript.dataset.icons) || 'assets/img/icons.svg';
    var DAYS = { montag: 'Mo', dienstag: 'Di', mittwoch: 'Mi', donnerstag: 'Do', freitag: 'Fr' };
    var Q_KEY = 'jrms-queue', D_KEY = 'jrms-disziplin';

    function ico(n, cls) { return '<svg class="' + (cls || 'ico') + '" aria-hidden="true"><use href="' + SPRITE + '#' + n + '"></use></svg>'; }
    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function store(key, val) { try { if (val === undefined) return JSON.parse(localStorage.getItem(key) || 'null'); localStorage.setItem(key, JSON.stringify(val)); } catch (e) { return null; } }

    // ---------- Daten ----------
    var kids = DATA.kids.map(function (k) { return { id: k.id, first: k.v, name: k.v + ' ' + k.n, jg: k.jg, days: k.d, cat: k.c }; });
    var kidById = {}; kids.forEach(function (k) { kidById[k.id] = k; });
    var discs = DATA.disciplines;
    var best = DATA.best || {};                 // best[kidId][disziplin] = Bestresultat im Semester
    var log = [];                               // eigene Einträge heute (neueste zuerst) {id|tmp, kid, d, v, old, pb, state}
    var doneToday = {};                         // doneToday[disziplin][kidId] = letzter Wert heute (alle Prüfer)
    DATA.today.slice().reverse().forEach(function (t) {
        (doneToday[t.disz] = doneToday[t.disz] || {})[t.pid] = t.v;
        var d = discByName(t.disz), k = kidById[t.pid];
        if (t.own && d) log.unshift({ id: t.id, kid: k || { id: t.pid, name: t.name, first: t.name }, d: d, v: t.v, state: 'saved' });
    });
    function discByName(n) { for (var i = 0; i < discs.length; i++) if (discs[i].name === n) return discs[i]; return null; }
    // Zählt die Disziplin für die Kategorie des Kindes in der Rangliste? (Spalte «kategorien», null = überall)
    function counts(kid, d) { return !d.cats || !kid.cat || d.cats.indexOf(kid.cat) >= 0; }
    function bestOf(kidId, d) { var b = best[kidId]; return b && b[d.name] != null ? b[d.name] : null; }

    // ---------- Format & Eingabe ----------
    function trimNum(v) { var s = v.toFixed(2); return s.indexOf('.') >= 0 ? s.replace(/0+$/, '').replace(/\.$/, '') : s; }
    function fmt(d, v, withUnit) {
        if (v == null) return '–';
        withUnit = withUnit !== false;
        if (d.kind === 'time') {
            if (v >= 60) { var m = Math.floor(v / 60), s = Math.round((v - m * 60) * 100) / 100; if (s >= 60) { m++; s = 0; } return m + ':' + (s < 10 ? '0' : '') + trimNum(s); }
            return trimNum(v) + (withUnit ? ' s' : '');
        }
        if (d.kind === 'm') return (v >= 100 ? String(Math.round(v)) : v.toFixed(2)) + (withUnit ? ' m' : '');
        if (d.kind === 'stufe') return (withUnit ? 'Stufe ' : '') + trimNum(v);
        if (d.kind === 'other') return trimNum(v) + (withUnit ? ' ' + d.unit : '');
        return trimNum(v);
    }
    function decimals(d) { return d.kind === 'stufe' ? 1 : (d.kind === 'm' || d.kind === 'time' ? 2 : 0); }
    function parse(raw, d) {
        raw = String(raw).trim().replace(/,/g, '.');
        var v = NaN, m;
        if (d.kind === 'time' && (m = raw.match(/^(\d{1,2}):(\d{1,2}(?:\.\d*)?)$/))) { if (parseFloat(m[2]) < 60) v = parseInt(m[1], 10) * 60 + parseFloat(m[2]); }
        else if (/^(\d+\.?\d*|\.\d+)$/.test(raw)) v = parseFloat(raw);
        return isNaN(v) ? NaN : Math.round(v * 100) / 100;
    }
    function toInput(d, v) { return d.kind === 'time' && v >= 60 ? fmt(d, v) : trimNum(v); }
    function maxObserved(d) {
        var max = null;
        Object.keys(best).forEach(function (id) { var v = best[id][d.name]; if (v != null && (max === null || v > max)) max = v; });
        return max;
    }
    function longTime(d) { if (d.kind !== 'time') return false; var mx = maxObserved(d); return mx === null || mx >= 45; }
    function plausible(v, kid, d) {
        if (!(v > 0)) return false;
        if (d.kind === 'stufe') { var mx = maxObserved(d); return Math.abs(v * 2 - Math.round(v * 2)) < 1e-9 && v <= Math.max(10, (mx || 0) + 2); }
        var b = kid && bestOf(kid.id, d);
        if (!b) return true;
        return d.kind === 'time' ? (v <= b * 1.3 && v >= b * 0.75) : (v <= b * 1.6 && v >= b * 0.6);
    }
    function candidates(raw, kid, d) {
        raw = String(raw).trim().replace(/,/g, '.'); var out = [], m, n = parseFloat(raw), dec = decimals(d);
        if (/^\d+$/.test(raw) && dec) out.push(n / Math.pow(10, dec));
        if (d.kind === 'time' && /^\d{3,4}$/.test(raw) && n % 100 < 60) out.push(Math.floor(n / 100) * 60 + n % 100);
        if (d.kind === 'time' && (m = raw.match(/^(\d{1,2})\.(\d{2})$/)) && +m[2] < 60) out.push(+m[1] * 60 + +m[2]);
        return out.filter(function (c, i) { return plausible(c, kid, d) && out.indexOf(c) === i; });
    }

    // ---------- Suche ----------
    function norm(s) { return s.toLowerCase().replace(/ä/g, 'a').replace(/ö/g, 'o').replace(/ü/g, 'u').normalize('NFD').replace(/[̀-ͯ]/g, ''); }
    function normQ(s) { return norm(s).replace(/ae/g, 'a').replace(/oe/g, 'o').replace(/ue/g, 'u'); }
    kids.forEach(function (k) { k.parts = norm(k.name).split(' '); });
    // Jedes getippte Wort muss am Anfang eines Namensteils passen («lu», «an ny»); 2 Buchstaben auch als Initialen («lj»).
    function search(q) {
        var tokens = normQ(q).trim().split(/\s+/).filter(Boolean);
        if (tokens.join('').length < 2) return [];
        var done = st.d ? (doneToday[st.d.name] || {}) : {};
        return kids.map(function (k) {
            var hl = {}, ok = tokens.every(function (tok) {
                for (var i = 0; i < k.parts.length; i++) {
                    if (hl[i] == null && normQ(k.parts[i]).indexOf(tok) === 0) { hl[i] = tok.length; return true; }
                }
                return false;
            });
            if (!ok && tokens.length === 1 && tokens[0].length === 2 && k.parts.length > 1 && k.parts[0][0] === tokens[0][0] && k.parts[k.parts.length - 1][0] === tokens[0][1]) {
                hl = { ini: true }; ok = true;
            }
            return ok ? { k: k, hl: hl, today: k.days.indexOf(DATA.todayKey) >= 0, done: done[k.id] != null } : null;
        }).filter(Boolean).sort(function (a, b) { return (b.today - a.today) || (a.done - b.done) || a.k.name.localeCompare(b.k.name, 'de'); });
    }
    function mark(name, hl) {
        var parts = name.split(' ');
        return parts.map(function (p, i) {
            if (hl.ini) return (i === 0 || i === parts.length - 1) ? '<mark>' + esc(p[0]) + '</mark>' + esc(p.slice(1)) : esc(p);
            return hl[i] ? '<mark>' + esc(p.slice(0, hl[i])) + '</mark>' + esc(p.slice(hl[i])) : esc(p);
        }).join(' ');
    }
    function daysShort(k) { return k.days.map(function (d) { return DAYS[d] || ''; }).join('/'); }
    function initials(name) { return name.split(' ').map(function (p) { return p[0] || ''; }).join('').slice(0, 2); }

    // ---------- Zustand Disziplin ----------
    var st = { d: null, kid: null, warnOk: false, stale: null };
    (function () {
        var saved = store(D_KEY), d = saved && discByName(saved.name);
        if (d) { st.d = d; if (saved.day !== DATA.date) st.stale = saved.day; }
    })();
    function rememberDisc() { st.stale = null; store(D_KEY, { name: st.d.name, day: DATA.date }); }

    // ---------- Aufbau ----------
    root.innerHTML =
        '<button class="e-disc" type="button" aria-haspopup="dialog"></button>' +
        '<div class="e-kid"></div>' +
        '<div class="e-res"><div class="e-field"><input class="e-val" inputmode="decimal" autocomplete="off" enterkeyhint="done" aria-label="Resultat" disabled>' +
        '<button type="button" class="e-colon" title="Doppelpunkt für Minuten:Sekunden" hidden>:</button><span class="e-u"></span></div>' +
        '<button class="e-save" type="button" disabled>' + ico('check') + '<span>Speichern</span></button></div>' +
        '<div class="e-line" aria-live="polite"></div><div class="e-toast-box" aria-live="polite"></div>' +
        '<details class="e-today"><summary>Heute erfasst <small></small><span class="e-netbox"></span>' + ico('chev-down') + '</summary><ul></ul></details>';
    var sheet = document.createElement('div');
    sheet.className = 'e-sheet'; sheet.hidden = true; sheet.setAttribute('role', 'dialog'); sheet.setAttribute('aria-label', 'Disziplin wählen');
    sheet.innerHTML = '<div class="e-in"><div class="e-grab"></div><h3>Disziplin wählen</h3><div class="e-dgrid"></div></div>';
    document.body.appendChild(sheet);
    var $ = function (s) { return root.querySelector(s); };
    var val = $('.e-val'), save = $('.e-save'), line = $('.e-line'), colon = $('.e-colon');

    function renderDisc() {
        var d = st.d, b = $('.e-disc');
        if (!d) {
            b.innerHTML = '<span class="e-di">' + ico('list') + '</span><span class="e-dn"><b>Disziplin wählen</b><small>einmal antippen, bleibt dann gewählt</small></span><span class="e-chev">' + ico('chev-down') + '</span>';
            b.classList.add('stale');
        } else {
            b.innerHTML = '<span class="e-di">' + ico(d.symbol) + '</span><span class="e-dn"><b>' + esc(d.name) + '</b><small>' + (d.label ? esc(d.label) + ' · ' : '') + (d.hoch ? '↑ höher besser' : '↓ tiefer besser') + '</small>' +
                (st.stale ? '<small class="e-stale-t">gewählt am ' + st.stale.split('-').reverse().slice(0, 2).join('.') + '. – noch richtig?</small>' : '') + '</span><span class="e-chev">wechseln ' + ico('chev-down') + '</span>';
            b.classList.toggle('stale', !!st.stale);
        }
        var lt = d && longTime(d);
        $('.e-u').textContent = d ? (d.kind === 'time' ? 's' : d.kind === 'stufe' ? '' : d.unit) : '';
        val.placeholder = !d ? '' : lt ? 'Sek. oder m:ss' : d.kind === 'stufe' ? 'Stufe' : (decimals(d) ? '0.' + '0'.repeat(decimals(d)) : '0');
        colon.hidden = !lt;
        $('.e-res').classList.toggle('time', !!lt);
        sheet.querySelector('.e-dgrid').innerHTML = discs.map(function (x) { return '<button type="button" data-name="' + esc(x.name) + '" aria-pressed="' + (x === d) + '">' + ico(x.symbol) + esc(x.name) + '</button>'; }).join('');
    }
    function kidInput() {
        $('.e-kid').innerHTML = ico('search') + '<input class="e-q" placeholder="' + (st.d ? '2 Buchstaben …' : 'zuerst Disziplin wählen') + '" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="next" aria-label="Kind suchen"' + (st.d ? '' : ' disabled') + '><ul class="e-sugg" hidden></ul>';
        var inp = $('.e-q');
        inp.addEventListener('input', function () { suggest(inp.value); });
        inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); var f = search(inp.value)[0]; if (f) pick(f.k); } });
        return inp;
    }
    function suggest(q) {
        var ul = $('.e-sugg'), hits = search(q);
        if (normQ(q).replace(/\s+/g, '').length < 2) { ul.hidden = true; return; }
        if (hits.length === 1) { pick(hits[0].k); return; }
        var html = '', sep = false, done = doneToday[st.d.name] || {};
        hits.slice(0, 6).forEach(function (h, i) {
            if (!h.today && !sep && i > 0) { html += '<li class="e-sep">andere Trainingstage</li>'; sep = true; }
            html += '<li data-id="' + h.k.id + '" class="' + (i === 0 ? 'first' : '') + (h.today ? '' : ' other') + '"><span class="e-av">' + esc(initials(h.k.name)) + '</span><span class="e-nm">' + mark(h.k.name, h.hl) + '</span><span class="e-meta">' + h.k.jg + (h.k.cat ? ' · ' + esc(h.k.cat) : '') + ' · ' + daysShort(h.k) +
                (h.done ? '<br><span class="e-done">heute ' + esc(fmt(st.d, done[h.k.id])) + '</span>' : '') + '</span></li>';
        });
        if (hits.length > 6) html += '<li class="e-sep">… ' + (hits.length - 6) + ' weitere – mehr Buchstaben tippen</li>';
        ul.innerHTML = hits.length ? html : '<li class="e-none">Kein aktives Kind gefunden</li>';
        ul.hidden = false;
    }
    root.addEventListener('click', function (e) { var li = e.target.closest('.e-sugg li[data-id]'); if (li) pick(kidById[li.dataset.id]); });
    function pick(k) {
        st.kid = k; st.warnOk = false;
        $('.e-kid').innerHTML = '<div class="e-chosen"><span class="e-av">' + esc(initials(k.name)) + '</span><span><b>' + esc(k.name) + '</b><br><small>' + k.jg + (k.cat ? ' · ' + esc(k.cat) : '') + ' · ' + daysShort(k) + '</small></span><button type="button" class="e-clr" title="Anderes Kind">' + ico('x') + '</button></div>';
        $('.e-clr').onclick = function () { resetKid(true); };
        val.disabled = false; val.value = ''; val.focus();
        info(); check();
    }
    function info() {
        var k = st.kid, d = st.d; line.className = 'e-line';
        if (!k) { line.textContent = ''; return; }
        var b = bestOf(k.id, d), t = (doneToday[d.name] || {})[k.id];
        line.innerHTML = (b != null ? 'Bestleistung ' + esc(k.first) + ': <b>' + esc(fmt(d, b)) + '</b>' : 'noch kein Resultat in diesem Semester') +
            (t != null ? ' · heute schon ' + esc(fmt(d, t)) : '') + (longTime(d) ? '<br>Eingabe: 80 oder 1:20 (Taste «:» im Feld)' : '') +
            (counts(k, d) ? '' : '<br><span class="e-nocount">' + esc(d.name) + ' zählt für ' + esc(k.cat) + ' nicht in der Rangliste – wird trotzdem gespeichert.</span>');
    }
    function resetKid(focus) {
        st.kid = null; var inp = kidInput();
        val.value = ''; val.disabled = true; line.textContent = ''; line.className = 'e-line'; check();
        if (focus && !inp.disabled) inp.focus();
    }
    function check() {
        var raw = val.value, d = st.d, v = d ? parse(raw, d) : NaN;
        save.disabled = !st.kid || isNaN(v);
        var bad = st.kid && !isNaN(v) && !plausible(v, st.kid, d) && !st.warnOk;
        save.classList.toggle('warn', !!bad);
        save.querySelector('span').textContent = bad ? 'Trotzdem' : 'Speichern';
        if (bad) {
            line.className = 'e-line warn';
            var cands = candidates(raw, st.kid, d);
            if (cands.length) {
                line.innerHTML = 'Gemeint? ' + cands.map(function (c) { return '<button type="button" class="e-fix" data-v="' + c + '">' + esc(fmt(d, c)) + ' speichern</button>'; }).join('');
                line.querySelectorAll('.e-fix').forEach(function (fx) {
                    fx.addEventListener('pointerdown', function (e) { e.preventDefault(); });
                    fx.onclick = function () { val.value = toInput(d, +fx.dataset.v); st.warnOk = false; check(); doSave(); };
                });
            } else {
                var b = bestOf(st.kid.id, d);
                line.textContent = d.kind === 'stufe' ? 'Sicher? Stufe ' + trimNum(v) + ' ist ungewöhnlich (halbe oder ganze Stufen).' : 'Sicher? ' + fmt(d, v) + ' ist weit weg von der Bestleistung (' + fmt(d, b) + ').';
            }
        } else if (st.kid && d.kind === 'time' && !isNaN(v) && (raw.indexOf(':') >= 0 || v >= 60)) {
            line.className = 'e-line';
            line.innerHTML = 'wird gespeichert als <b>' + esc(fmt(d, v)) + '</b>' + (raw.indexOf(':') >= 0 && v >= 60 ? ' = ' + trimNum(v) + ' s' : '');
        } else if (st.kid && d.kind === 'time' && raw.trim() && isNaN(v) && !/[:.,]$/.test(raw.trim())) {
            line.className = 'e-line warn'; line.textContent = 'Zeit als Sekunden (80) oder Minuten:Sekunden (1:20) eingeben.';
        } else if (st.kid && raw.trim() && isNaN(v) && !/[:.,]$/.test(raw.trim()) && d.kind !== 'time') {
            line.className = 'e-line warn'; line.textContent = 'Bitte eine Zahl eingeben.';
        } else if (st.kid && (line.classList.contains('warn') || line.textContent.indexOf('wird gespeichert') === 0)) info();
    }
    val.addEventListener('input', function () { st.warnOk = false; check(); });
    val.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); doSave(); } });
    colon.addEventListener('pointerdown', function (e) { e.preventDefault(); });
    colon.addEventListener('click', function () {
        if (val.disabled || !val.value || val.value.indexOf(':') >= 0) return;
        val.value += ':'; val.focus(); st.warnOk = false; check();
    });
    save.addEventListener('pointerdown', function (e) { e.preventDefault(); }); // Fokus/Tastatur nicht verlieren
    save.addEventListener('click', doSave);

    // ---------- Speichern, Warteschlange, Rückgängig ----------
    function doSave() {
        if (save.disabled) return;
        if (save.classList.contains('warn')) { st.warnOk = true; check(); return; }
        var d = st.d, k = st.kid, v = parse(val.value, d), old = bestOf(k.id, d);
        var entry = { tmp: 't' + Date.now() + Math.random().toString(36).slice(2, 6), kid: k, d: d, v: v, old: old, pb: old == null || (d.hoch ? v > old : v < old), state: 'pending' };
        log.unshift(entry);
        (doneToday[d.name] = doneToday[d.name] || {})[k.id] = v;
        if (entry.pb) (best[k.id] = best[k.id] || {})[d.name] = v;
        resetKid(true);           // zuerst Fokus zurück (im selben Tipp-Ereignis), dann Netz
        toast(entry); renderToday();
        send(entry);
    }
    function api(body) {
        var ctrl = 'AbortController' in window ? new AbortController() : null;
        var timer = ctrl && setTimeout(function () { ctrl.abort(); }, 12000);
        return fetch('erfassen_api.php', {
            method: 'POST', credentials: 'same-origin', signal: ctrl && ctrl.signal,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': DATA.csrf, 'Accept': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json().catch(function () { return { ok: false, error: 'Serverfehler (' + r.status + ')' }; }).then(function (j) { j.status = r.status; return j; });
        }).finally(function () { if (timer) clearTimeout(timer); });
    }
    function send(entry) {
        return api({ action: 'save', teilnehmer_id: entry.kid.id, disziplin: entry.d.name, resultat: entry.v }).then(function (j) {
            if (j.ok) {
                entry.id = j.id; entry.state = 'saved'; unqueue(entry);
                if (entry.cancel) { api({ action: 'delete', id: j.id }); return; } // während des Speicherns zurückgenommen
            }
            else if (j.status === 401 || j.status === 403) { entry.state = 'queued'; entry.msg = j.error; enqueue(entry); }
            else { entry.state = 'error'; entry.msg = j.error || 'Nicht gespeichert.'; unqueue(entry); rollback(entry); }
            refresh(entry);
        }, function () { entry.state = 'queued'; entry.msg = ''; enqueue(entry); refresh(entry); });
    }
    function refresh(entry) { if (entry.state === 'undone') return; if (log[0] === entry || entry.state === 'error') toast(entry); renderToday(); }
    function rollback(entry) {
        var i = log.indexOf(entry); if (i >= 0 && entry.state !== 'error') log.splice(i, 1);
        var dt = doneToday[entry.d.name]; if (dt && dt[entry.kid.id] === entry.v) delete dt[entry.kid.id];
        if (entry.pb && best[entry.kid.id]) { if (entry.old == null) delete best[entry.kid.id][entry.d.name]; else best[entry.kid.id][entry.d.name] = entry.old; }
    }
    function queue() { return store(Q_KEY) || []; }
    function enqueue(e) {
        var q = queue().filter(function (x) { return x.tmp !== e.tmp; });
        q.push({ tmp: e.tmp, kid: e.kid.id, name: e.kid.name, disz: e.d.name, v: e.v });
        store(Q_KEY, q); netInfo();
    }
    function unqueue(e) { store(Q_KEY, queue().filter(function (x) { return x.tmp !== e.tmp; })); netInfo(); }
    var flushing = false;
    function flush() {
        var q = queue(); if (flushing || !q.length) return;
        flushing = true;
        var next = q[0], entry = null;
        log.forEach(function (x) { if (x.tmp === next.tmp) entry = x; });
        if (!entry) {
            var d = discByName(next.disz);
            if (!d) { store(Q_KEY, q.slice(1)); flushing = false; return flush(); }
            entry = { tmp: next.tmp, kid: kidById[next.kid] || { id: next.kid, name: next.name, first: next.name }, d: d, v: next.v, state: 'queued' };
            log.unshift(entry);
        }
        send(entry).then(function () { flushing = false; if (entry.state === 'saved') flush(); });
    }
    function netInfo() {
        var n = queue().length;
        $('.e-netbox').innerHTML = n ? '<span class="e-net"><i></i>' + n + ' wartet auf Netz</span>' : '';
    }
    // Andere offene Seite (zweiter Tab) hat die Warteschlange abgearbeitet → hier nachführen
    addEventListener('storage', function (ev) {
        if (ev.key !== Q_KEY) return;
        var tmps = queue().map(function (x) { return x.tmp; });
        log.forEach(function (x) { if (x.state === 'queued' && tmps.indexOf(x.tmp) < 0) { x.state = 'saved'; x.msg = 'gespeichert (über anderes Fenster)'; if (log[0] === x) toast(x); } });
        netInfo(); renderToday();
    });
    addEventListener('online', flush);
    setInterval(flush, 20000);

    function toast(e) {
        var cls = e.state === 'error' ? ' err' : (e.state === 'queued' ? ' wait' : '');
        var status = e.state === 'saved' ? (e.msg || 'gespeichert') : e.state === 'pending' ? 'wird gespeichert …' : e.state === 'queued' ? (e.msg || 'kein Netz – auf dem Handy gespeichert, wird nachgeschickt') : (e.msg || 'nicht gespeichert');
        $('.e-toast-box').innerHTML = '<div class="e-toast' + cls + '">' + ico(e.state === 'error' ? 'x' : 'check') +
            '<span class="e-txt">' + esc(e.kid.name) + ' · <b>' + esc(fmt(e.d, e.v)) + '</b>' + (e.pb && e.state !== 'error' ? ' <span class="e-pb">' + ico('star') + ' Bestleistung</span>' : '') + '<br><small>' + esc(status) + '</small></span>' +
            (e.state === 'error' ? '' : '<button type="button" class="e-undo">' + ico('undo') + 'Rückgängig</button>') + '</div>';
        var u = $('.e-undo'); if (u) u.onclick = function () { undo(e); };
    }
    function undo(e) {
        var finish = function () {
            rollback(e); var i = log.indexOf(e); if (i >= 0) log.splice(i, 1);
            $('.e-toast-box').innerHTML = ''; renderToday();
            if (e.kid && kidById[e.kid.id] && st.d === e.d) { pick(kidById[e.kid.id]); val.value = toInput(e.d, e.v); check(); }
        };
        if (e.state === 'queued') { unqueue(e); e.state = 'undone'; finish(); return; }
        if (e.state === 'pending') { e.cancel = true; e.state = 'undone'; finish(); return; }
        api({ action: 'delete', id: e.id }).then(function (j) {
            if (j.ok) finish(); else { e.msg = j.error; e.state = 'error'; toast(e); }
        }, function () { e.msg = 'Kein Netz – Rückgängig nicht möglich.'; toast(e); });
    }
    function renderToday() {
        var own = log.filter(function (x) { return x.state !== 'error' && x.state !== 'undone'; });
        $('.e-today small').textContent = own.length;
        $('.e-today ul').innerHTML = own.length ? own.map(function (r) {
            return '<li>' + ico(r.d.symbol) + '<span>' + esc(r.kid.name) + ' <small>' + esc(r.d.name) + (r.state === 'queued' ? ' · wartet' : '') + '</small></span><b>' + esc(fmt(r.d, r.v)) + '</b></li>';
        }).join('') : '<li><small>Noch nichts erfasst.</small></li>';
    }

    // ---------- Disziplin-Blatt ----------
    $('.e-disc').addEventListener('click', function () { sheet.hidden = false; });
    sheet.addEventListener('click', function (e) {
        var b = e.target.closest('[data-name]');
        if (b) {
            st.d = discByName(b.dataset.name); rememberDisc(); renderDisc();
            if (st.kid) { info(); check(); } else { resetKid(true); }
        }
        if (b || e.target === sheet) sheet.hidden = true;
    });
    addEventListener('keydown', function (e) { if (e.key === 'Escape') sheet.hidden = true; });

    renderDisc(); renderToday(); resetKid(false); netInfo();
    if (!st.d) sheet.hidden = false; else $('.e-q').focus();
    flush();
})();
