// jugi-rms – Übersicht: Suche, Trainingstag, «nur mit Lücken» und Fortschritt «erfasst x von y» pro Disziplin.
(function () {
    'use strict';
    var table = document.getElementById('ov');
    if (!table) return;
    var rows = Array.prototype.slice.call(table.tBodies[0].rows);
    var cards = document.querySelectorAll('#prog .prog');
    var q = document.getElementById('q'), gaps = document.getElementById('gaps'), days = document.getElementById('days');
    var state = { q: '', day: '', gaps: false };

    function apply() {
        var visible = 0, total = [], done = [];
        cards.forEach(function (_, i) { total[i] = 0; done[i] = 0; });
        rows.forEach(function (tr) {
            var cells = Array.prototype.slice.call(tr.cells, 1);
            var hasGap = cells.some(function (td) { return td.classList.contains('miss'); });
            var show = (!state.q || tr.dataset.name.indexOf(state.q) >= 0)
                && (!state.day || (' ' + tr.dataset.days + ' ').indexOf(' ' + state.day + ' ') >= 0)
                && (!state.gaps || hasGap);
            tr.hidden = !show;
            if (!show) return;
            visible++;
            cells.forEach(function (td, i) {
                if (td.classList.contains('na') || td.classList.contains('ng')) return; // zählt in dieser Kategorie nicht
                total[i]++; if (!td.classList.contains('miss')) done[i]++;
            });
        });
        cards.forEach(function (card, i) {
            var n = total[i], d = done[i];
            card.querySelector('.c').innerHTML = '<b>' + d + '</b> von ' + n + (n ? (d < n ? ' · ' + (n - d) + ' fehlen' : ' · komplett') : '');
            card.querySelector('.bar i').style.width = (n ? d / n * 100 : 0) + '%';
            card.classList.toggle('full', n > 0 && d === n);
        });
        document.getElementById('none').hidden = visible > 0;
    }

    q.addEventListener('input', function () { state.q = q.value.trim().toLowerCase(); apply(); });
    gaps.addEventListener('change', function () { state.gaps = gaps.checked; apply(); });
    if (days) days.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        days.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
        state.day = b.dataset.day; apply();
    });
    apply();
})();
