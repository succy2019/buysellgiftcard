/**
 * DFQ Exchange — app shell enhancements for the user and admin dashboards.
 * Builds a native-style bottom tab bar with a "More" sheet on phones,
 * stacks data tables into cards, and adds a top progress bar on navigation.
 * All links and the logout form are reused from the existing sidebar so
 * routes, CSRF tokens and role restrictions are unchanged.
 */
(function () {
    'use strict';

    var body = document.body;
    if (!body || !body.classList.contains('dfq-app')) { return; }

    var isAdmin = body.classList.contains('dfq-admin');
    var menu = document.querySelector('.sidebar-menu, .admin-menu');
    if (!menu) { return; }

    var PRIMARY = isAdmin
        ? ['dashboard', 'users', 'transactions', 'payouts']
        : ['dashboard', 'wallet', 'trading', 'transactions'];
    var SHORT = { dashboard: 'Home', transactions: isAdmin ? 'Trades' : 'Activity', trading: 'Trade', users: 'Users', payouts: 'Payouts' };
    var CENTER = isAdmin ? null : 'trading';

    function sectionOf(a) {
        var m = /[?&]section=([a-z_-]+)/i.exec(a.getAttribute('href') || '');
        return m ? m[1] : '';
    }

    function iconOf(a) {
        var i = a.querySelector('i');
        return i ? i.className : 'fa fa-list';
    }

    function labelOf(a) {
        return (a.textContent || '').replace(/\s+/g, ' ').trim();
    }

    var links = Array.prototype.slice.call(menu.querySelectorAll('a[href*="section="]'));
    var logoutForm = menu.querySelector('form');
    var bySection = {};
    links.forEach(function (a) { bySection[sectionOf(a)] = a; });

    var primary = PRIMARY.map(function (s) { return bySection[s]; }).filter(Boolean);
    var rest = links.filter(function (a) { return primary.indexOf(a) === -1; });

    // ---- Tab bar -------------------------------------------------------
    var bar = document.createElement('nav');
    bar.className = 'dfq-tabbar';
    bar.setAttribute('aria-label', 'Primary');

    primary.forEach(function (a) {
        var s = sectionOf(a);
        var t = document.createElement('a');
        t.href = a.getAttribute('href');
        t.className = 'dfq-tab' + (a.classList.contains('active') ? ' is-active' : '') + (s === CENTER ? ' is-center' : '');
        if (a.classList.contains('active')) { t.setAttribute('aria-current', 'page'); }
        t.innerHTML = '<span class="dfq-tab__icon"><i class="' + iconOf(a) + '"></i></span><span class="dfq-tab__label"></span>';
        t.querySelector('.dfq-tab__label').textContent = SHORT[s] || labelOf(a);
        bar.appendChild(t);
    });

    var moreActive = rest.some(function (a) { return a.classList.contains('active'); });
    var more = document.createElement('button');
    more.type = 'button';
    more.className = 'dfq-tab dfq-tab--more' + (moreActive ? ' is-active' : '');
    more.setAttribute('aria-haspopup', 'dialog');
    more.setAttribute('aria-expanded', 'false');
    more.innerHTML = '<span class="dfq-tab__icon"><i class="fa fa-more"></i></span><span class="dfq-tab__label">More</span>';
    bar.appendChild(more);

    // Keep the centre tab in the middle slot regardless of how many tabs exist.
    var centerTab = bar.querySelector('.is-center');
    if (centerTab && bar.children.length === 5) {
        bar.insertBefore(centerTab, bar.children[2]);
    }

    // ---- More sheet ----------------------------------------------------
    var overlay = document.createElement('div');
    overlay.className = 'dfq-sheet-overlay';
    overlay.hidden = true;

    var sheet = document.createElement('div');
    sheet.className = 'dfq-sheet';
    sheet.setAttribute('role', 'dialog');
    sheet.setAttribute('aria-modal', 'true');
    sheet.setAttribute('aria-label', 'More options');
    sheet.tabIndex = -1;

    var grabber = document.createElement('div');
    grabber.className = 'dfq-sheet__grabber';
    sheet.appendChild(grabber);

    var info = document.querySelector('.user-info, .admin-info');
    if (info) {
        var who = info.cloneNode(true);
        who.className = 'dfq-sheet__who';
        sheet.appendChild(who);
    }

    var grid = document.createElement('div');
    grid.className = 'dfq-sheet__grid';
    rest.forEach(function (a) {
        var g = document.createElement('a');
        g.href = a.getAttribute('href');
        g.className = 'dfq-sheet__item' + (a.classList.contains('active') ? ' is-active' : '');
        g.innerHTML = '<span class="dfq-sheet__ico"><i class="' + iconOf(a) + '"></i></span><span></span>';
        g.lastChild.textContent = labelOf(a);
        grid.appendChild(g);
    });
    sheet.appendChild(grid);

    if (logoutForm) {
        var lf = logoutForm.cloneNode(true);
        lf.className = 'dfq-sheet__logout';
        lf.removeAttribute('style');
        var btn = lf.querySelector('button');
        if (btn) {
            btn.removeAttribute('style');
            btn.className = 'dfq-sheet__logout-btn';
        }
        sheet.appendChild(lf);
    }

    overlay.appendChild(sheet);
    document.body.appendChild(bar);
    document.body.appendChild(overlay);
    body.classList.add('has-tabbar');

    var lastFocus = null;
    function openSheet() {
        lastFocus = document.activeElement;
        overlay.hidden = false;
        window.requestAnimationFrame(function () { overlay.classList.add('is-open'); });
        more.setAttribute('aria-expanded', 'true');
        body.classList.add('dfq-noscroll');
        sheet.focus();
    }
    function closeSheet() {
        overlay.classList.remove('is-open');
        more.setAttribute('aria-expanded', 'false');
        body.classList.remove('dfq-noscroll');
        window.setTimeout(function () { overlay.hidden = true; }, 220);
        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
    }
    more.addEventListener('click', openSheet);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) { closeSheet(); } });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hidden) { closeSheet(); }
    });

    // Swipe the sheet down to dismiss.
    var startY = null;
    sheet.addEventListener('touchstart', function (e) { startY = e.touches[0].clientY; }, { passive: true });
    sheet.addEventListener('touchend', function (e) {
        if (startY !== null && e.changedTouches[0].clientY - startY > 70) { closeSheet(); }
        startY = null;
    }, { passive: true });

    // ---- Header avatar (user dashboard -> profile) ---------------------
    var headerRight = document.querySelector('.header-area .text-right');
    var nameEl = info && info.querySelector('h5');
    if (headerRight && nameEl) {
        var initials = nameEl.textContent.trim().split(/\s+/).map(function (p) { return p.charAt(0); }).join('').slice(0, 2).toUpperCase();
        var chip = document.createElement(isAdmin ? 'span' : 'a');
        chip.className = 'dfq-header-avatar';
        chip.textContent = initials || 'U';
        if (!isAdmin) {
            chip.href = '?section=profile';
            chip.setAttribute('aria-label', 'Open profile');
        }
        headerRight.appendChild(chip);
    }

    // ---- Page title for the app bar -----------------------------------
    var active = menu.querySelector('a.active');
    if (active) {
        var title = document.createElement('span');
        title.className = 'dfq-page-title';
        title.textContent = labelOf(active);
        var brand = document.querySelector('.dfq-brand');
        if (brand && brand.parentNode) { brand.parentNode.appendChild(title); }
    }

    // ---- Stack tables into cards on small screens ----------------------
    Array.prototype.forEach.call(document.querySelectorAll('table'), function (table) {
        var heads = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
            return th.textContent.replace(/\s+/g, ' ').trim();
        });
        if (!heads.length) { return; }
        table.classList.add('dfq-stack');
        Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (tr) {
            Array.prototype.forEach.call(tr.children, function (td, i) {
                if (td.colSpan > 1) { td.classList.add('dfq-stack-empty'); return; }
                if (heads[i] && !td.hasAttribute('data-label')) { td.setAttribute('data-label', heads[i]); }
            });
        });
    });

    // ---- Navigation progress bar --------------------------------------
    var progress = document.createElement('div');
    progress.className = 'dfq-progress';
    progress.setAttribute('aria-hidden', 'true');
    document.body.appendChild(progress);
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href]');
        if (!a || a.target === '_blank' || e.metaKey || e.ctrlKey || e.defaultPrevented) { return; }
        var h = a.getAttribute('href') || '';
        if (h.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(h)) { return; }
        body.classList.add('dfq-navigating');
    });
    window.addEventListener('pageshow', function () { body.classList.remove('dfq-navigating'); });
})();
