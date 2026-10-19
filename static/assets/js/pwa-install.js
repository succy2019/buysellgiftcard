/**
 * DFQ Exchange — PWA install prompt.
 *
 * Android / Chrome / Edge / desktop Chrome:
 *   shows a branded "Download" prompt that opens the browser's native
 *   install dialog (beforeinstallprompt).
 *
 * iPhone / iPad Safari:
 *   Apple does not allow sites to trigger installation programmatically,
 *   so the prompt shows the required Share -> Add to Home Screen steps.
 *
 * The prompt is never shown when the app is already installed (standalone
 * display mode) or within the dismissal window.
 */
(function () {
    'use strict';

    var DISMISS_KEY = 'dfq_install_dismissed_until';
    var DISMISS_DAYS = 7;
    var SHOW_DELAY_MS = 2200;
    var ICON_URL = 'static/assets/img/app-icon-192.png';

    var deferredPrompt = null;
    var overlay = null;
    var shown = false;

    function isStandalone() {
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
            window.navigator.standalone === true;
    }

    function isDismissed() {
        try {
            var until = parseInt(window.localStorage.getItem(DISMISS_KEY) || '0', 10);
            return until > Date.now();
        } catch (e) {
            return false;
        }
    }

    function dismiss() {
        try {
            window.localStorage.setItem(
                DISMISS_KEY,
                String(Date.now() + DISMISS_DAYS * 24 * 60 * 60 * 1000)
            );
        } catch (e) {
            /* localStorage unavailable — ignore */
        }
        hide();
    }

    function isIos() {
        var ua = window.navigator.userAgent || '';
        var iOSDevice = /iphone|ipad|ipod/i.test(ua);
        // iPadOS 13+ reports itself as Macintosh but exposes touch points.
        var iPadOs = window.navigator.platform === 'MacIntel' &&
            (window.navigator.maxTouchPoints || 0) > 1;
        return iOSDevice || iPadOs;
    }

    function isSafari() {
        var ua = window.navigator.userAgent || '';
        return /safari/i.test(ua) && !/crios|fxios|chrome|android/i.test(ua);
    }

    function buildOverlay() {
        if (overlay) {
            return overlay;
        }

        overlay = document.createElement('div');
        overlay.className = 'fex-install-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'fex-install-title');

        var card = document.createElement('div');
        card.className = 'fex-install-card';

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'fex-install-close';
        close.setAttribute('aria-label', 'Close');
        close.innerHTML = '&times;';
        close.addEventListener('click', dismiss);

        var icon = document.createElement('img');
        icon.className = 'fex-install-icon';
        icon.src = ICON_URL;
        icon.alt = 'DFQ Exchange';
        icon.width = 72;
        icon.height = 72;

        var title = document.createElement('h2');
        title.className = 'fex-install-title';
        title.id = 'fex-install-title';
        title.textContent = 'Download DFQ Exchange';

        var text = document.createElement('p');
        text.className = 'fex-install-text';
        text.textContent = 'Get the full app experience — faster loading, one-tap access and an app-style interface.';

        card.appendChild(close);
        card.appendChild(icon);
        card.appendChild(title);

        if (isIos()) {
            text.textContent = 'Add DFQ Exchange to your device for faster loading and one-tap access.';
            card.appendChild(text);

            var steps = document.createElement('ol');
            steps.className = 'fex-install-steps';
            steps.innerHTML =
                '<li>Tap the Share button ' +
                '<svg class="fex-share-icon" viewBox="0 0 24 24" fill="none" stroke="#32d583" stroke-width="2" ' +
                'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
                '<path d="M12 16V4"/><path d="M8 8l4-4 4 4"/>' +
                '<path d="M5 12v7a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-7"/></svg>' +
                ' at the bottom of Safari.</li>' +
                '<li>Scroll down and choose <strong>Add to Home Screen</strong>.</li>' +
                '<li>Tap <strong>Add</strong> in the top corner.</li>';

            var iosNote = document.createElement('p');
            iosNote.className = 'fex-install-text';
            iosNote.style.fontSize = '12px';
            iosNote.style.marginBottom = '18px';
            iosNote.textContent = 'This only works in Safari on iPhone and iPad.';

            card.appendChild(steps);
            card.appendChild(iosNote);

            var iosActions = document.createElement('div');
            iosActions.className = 'fex-install-actions';
            var gotIt = document.createElement('button');
            gotIt.type = 'button';
            gotIt.className = 'fex-install-btn fex-install-btn-primary';
            gotIt.textContent = 'Got it';
            gotIt.addEventListener('click', dismiss);
            iosActions.appendChild(gotIt);
            card.appendChild(iosActions);
        } else {
            card.appendChild(text);

            var actions = document.createElement('div');
            actions.className = 'fex-install-actions';

            var notNow = document.createElement('button');
            notNow.type = 'button';
            notNow.className = 'fex-install-btn fex-install-btn-secondary';
            notNow.textContent = 'Not now';
            notNow.addEventListener('click', dismiss);

            var install = document.createElement('button');
            install.type = 'button';
            install.className = 'fex-install-btn fex-install-btn-primary';
            install.textContent = 'Download';
            install.addEventListener('click', function () {
                if (!deferredPrompt) {
                    dismiss();
                    return;
                }

                install.disabled = true;
                deferredPrompt.prompt();

                deferredPrompt.userChoice.then(function (choice) {
                    if (choice && choice.outcome === 'accepted') {
                        try {
                            window.localStorage.setItem(DISMISS_KEY, String(Date.now() + 365 * 24 * 60 * 60 * 1000));
                        } catch (e) { /* ignore */ }
                        hide();
                    } else {
                        install.disabled = false;
                    }
                    deferredPrompt = null;
                }).catch(function () {
                    install.disabled = false;
                });
            });

            actions.appendChild(notNow);
            actions.appendChild(install);
            card.appendChild(actions);
        }

        overlay.appendChild(card);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                dismiss();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && overlay.classList.contains('is-visible')) {
                dismiss();
            }
        });

        document.body.appendChild(overlay);
        return overlay;
    }

    function show() {
        if (shown || isStandalone() || isDismissed()) {
            return;
        }
        if (isIos() && !isSafari()) {
            return; // Chrome/Firefox on iOS cannot add to the home screen.
        }
        shown = true;
        var el = buildOverlay();
        // Let the browser paint the hidden state before animating in.
        window.requestAnimationFrame(function () {
            el.classList.add('is-visible');
        });
    }

    function hide() {
        if (overlay) {
            overlay.classList.remove('is-visible');
        }
    }

    // Android / desktop Chromium: capture the native install event.
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        window.setTimeout(show, SHOW_DELAY_MS);
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        try {
            window.localStorage.setItem(DISMISS_KEY, String(Date.now() + 365 * 24 * 60 * 60 * 1000));
        } catch (e) { /* ignore */ }
        hide();
    });

    // iOS Safari never fires beforeinstallprompt — show guidance manually.
    if (isIos() && isSafari() && !isStandalone()) {
        if (document.readyState === 'complete') {
            window.setTimeout(show, SHOW_DELAY_MS);
        } else {
            window.addEventListener('load', function () {
                window.setTimeout(show, SHOW_DELAY_MS);
            });
        }
    }

    // Register the service worker (required for installability).
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('service-worker.js').catch(function () {
                /* https/localhost requirement — fail silently */
            });
        });
    }

    // Exposed so a "Download app" link can open the prompt on demand.
    window.DFQInstall = {
        show: show,
        dismiss: dismiss
    };
})();
