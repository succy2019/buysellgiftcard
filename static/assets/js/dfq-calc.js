/* Live NGN quotes for buy / sell / gift card forms. Server remains the source of truth. */
(function () {
    var cfg = window.DFQ_RATES || { ngn: 1500, fee: 1 };
    var ngn = Number(cfg.ngn) || 1500;
    var fee = Number(cfg.fee) || 0;

    function naira(v, d) {
        return '\u20A6' + Number(v).toLocaleString('en-NG', { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 });
    }
    function usd(v) {
        return '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function both(v) { return naira(v * ngn, 2) + ' (' + usd(v) + ')'; }

    document.querySelectorAll('form[data-calc]').forEach(function (form) {
        var type = form.getAttribute('data-calc');
        var select = form.querySelector('select');
        var input = form.querySelector('input[name="amount"], input[name="card_value"]');
        var out = function (k) { return form.querySelector('[data-q="' + k + '"]'); };
        var set = function (k, v) { var el = out(k); if (el) { el.textContent = v; } };

        function update() {
            var opt = select && select.options[select.selectedIndex];
            var rate = opt ? parseFloat(opt.getAttribute('data-rate')) : NaN;
            var val = input ? parseFloat(input.value) : NaN;

            if (!opt || isNaN(rate) || !opt.value) {
                ['rate', 'fee', 'total', 'receive', 'gross', 'usd'].forEach(function (k) { set(k, '-'); });
                return;
            }
            if (type === 'buy') {
                set('rate', '1 ' + opt.value.toUpperCase() + ' = ' + naira(rate * ngn, 0));
                if (isNaN(val) || val <= 0) { set('fee', '-'); set('total', '-'); set('receive', '-'); return; }
                var f = val * fee / 100;
                set('fee', both(f));
                set('total', both(val + f));
                set('receive', (val / rate).toFixed(8).replace(/0+$/, '').replace(/\.$/, '') + ' ' + opt.value.toUpperCase());
            } else if (type === 'sell') {
                set('rate', '1 ' + opt.value.toUpperCase() + ' = ' + naira(rate * ngn, 0));
                if (isNaN(val) || val <= 0) { set('gross', '-'); set('fee', '-'); set('receive', '-'); return; }
                var g = val * rate, sf = g * fee / 100;
                set('gross', both(g));
                set('fee', both(sf));
                set('receive', both(g - sf));
            } else if (type === 'gift') {
                set('rate', rate + '% of card value');
                if (isNaN(val) || val <= 0) { set('usd', '-'); set('receive', '-'); return; }
                var p = val * rate / 100;
                set('usd', usd(p));
                set('receive', naira(p * ngn, 2));
            }
        }

        if (select) { select.addEventListener('change', update); }
        if (input) { input.addEventListener('input', update); }
        var maxBtn = form.querySelector('[data-max-btn]');
        if (maxBtn) {
            maxBtn.addEventListener('click', function () {
                var opt = select.options[select.selectedIndex];
                if (opt && opt.getAttribute('data-max')) { input.value = opt.getAttribute('data-max'); update(); }
            });
        }
        update();
    });

    // Admin: live NGN previews next to rate inputs.
    var ngnField = document.querySelector('[data-ngn-rate]');
    function adminNgn() { return ngnField ? (parseFloat(ngnField.value) || ngn) : ngn; }
    function refreshPreview(input) {
        var target = input.parentElement.querySelector('[data-ngn-preview]');
        var v = parseFloat(input.value);
        if (target) { target.textContent = isNaN(v) ? '-' : naira(v * adminNgn(), 0) + ' per coin'; }
    }
    var previews = document.querySelectorAll('input[data-ngn-input]');
    previews.forEach(function (i) { i.addEventListener('input', function () { refreshPreview(i); }); refreshPreview(i); });
    if (ngnField) { ngnField.addEventListener('input', function () { previews.forEach(refreshPreview); }); }
})();
