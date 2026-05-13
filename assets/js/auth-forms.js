/**
 * Cotlas Auth Forms – Frontend JS
 *
 * Intercepts all [data-cotlas-form] forms, submits via fetch to admin-ajax.php,
 * and handles the JSON response:
 *
 *   Success with redirect  → window.location.href = data.redirect
 *   Success with message   → show inline success message, reset form
 *   Error                  → show inline error message, re-enable button,
 *                            reset Cloudflare Turnstile if present
 *
 * No jQuery dependency. Works with any page builder.
 */
(function () {
    'use strict';

    // The wp_localize_script data object
    var cfg = window.cotlasAuth || { ajaxurl: '', nonce: '', i18n: {} };

    // ── Init ─────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('[data-cotlas-form]');
        forms.forEach(bindForm);
    });

    // Also bind forms that are injected into the DOM later (e.g. inside a
    // modal/overlay that renders after DOMContentLoaded).
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    var forms = node.querySelectorAll
                        ? node.querySelectorAll('[data-cotlas-form]')
                        : [];
                    forms.forEach(function (f) {
                        if (!f.dataset.cotlasbound) bindForm(f);
                    });
                    // The node itself might be the form
                    if (node.matches && node.matches('[data-cotlas-form]') && !node.dataset.cotlasbound) {
                        bindForm(node);
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // ── Bind a single form ───────────────────────────────────────────────
    function bindForm(form) {
        if (form.dataset.cotlasbound) return;
        form.dataset.cotlasbound = '1';

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            handleSubmit(form);
        });
    }

    // ── Handle submit ────────────────────────────────────────────────────
    function handleSubmit(form) {
        var wrap    = form.closest('[data-cotlas-wrap]') || form.parentElement;
        var msgBox  = wrap ? wrap.querySelector('.cotlas-auth-messages') : null;
        var btn     = form.querySelector('.cotlas-btn-primary');
        var btnText = btn ? btn.querySelector('.cotlas-btn-text') : null;
        var spinner = btn ? btn.querySelector('.cotlas-btn-spinner') : null;

        // Loading state
        if (btn)     btn.disabled = true;
        if (btnText) btnText.hidden = true;
        if (spinner) spinner.hidden = false;
        clearMsg(msgBox);

        // Build form data; refresh the nonce value from the localized object
        // in case the page has been open a long time (nonces expire after 12h).
        var data = new FormData(form);

        // Update nonce with the one from wp_localize_script (most recent)
        if (cfg.nonce) {
            data.set('cotlas_nonce', cfg.nonce);
        }

        fetch(cfg.ajaxurl, {
            method:      'POST',
            credentials: 'same-origin',
            body:        data,
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (res) {
                restoreBtn(btn, btnText, spinner);

                if (res.success) {
                    var payload = res.data || {};

                    if (payload.redirect) {
                        // Redirect (login success)
                        window.location.href = payload.redirect;
                        return; // keep loading state until page changes
                    }

                    if (payload.message) {
                        // Non-redirect success (register, forgot password)
                        showMsg(msgBox, payload.message, 'success');
                        form.reset();
                        resetTurnstile(form);
                    }
                } else {
                    var msg = (res.data && res.data.message) ? res.data.message : 'An error occurred.';
                    showMsg(msgBox, msg, 'error');
                    resetTurnstile(form);
                }
            })
            .catch(function () {
                restoreBtn(btn, btnText, spinner);
                showMsg(msgBox, cfg.i18n.connectionError || 'Connection error. Please try again.', 'error');
            });
    }

    // ── UI helpers ───────────────────────────────────────────────────────
    function restoreBtn(btn, btnText, spinner) {
        if (btn)     btn.disabled = false;
        if (btnText) btnText.hidden = false;
        if (spinner) spinner.hidden = true;
    }

    function showMsg(box, text, type) {
        if (!box) return;
        box.hidden    = false;
        box.className = 'cotlas-auth-messages cotlas-msg-' + type;
        box.textContent = text;
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function clearMsg(box) {
        if (!box) return;
        box.hidden    = true;
        box.textContent = '';
        box.className = 'cotlas-auth-messages';
    }

    // ── Panel switching for [cotlas_auth_panel] ──────────────────────────
    // Uses event delegation so it works in modals injected after DOMContentLoaded.
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-cotlas-switch]');
        if (!link) return;
        var root = link.closest('[data-cotlas-panel-root]');
        if (!root) return;
        e.preventDefault();
        var target = link.dataset.cotlasSwitch;
        root.querySelectorAll('[data-cotlas-panel]').forEach(function (panel) {
            var isTarget = panel.dataset.cotlasPanel === target;
            panel.hidden = !isTarget;
            // Clear messages on the panel we're leaving
            if (!isTarget) {
                var msgBox = panel.querySelector('.cotlas-auth-messages');
                clearMsg(msgBox);
            }
        });
    });

    function resetTurnstile(form) {
        if (typeof window.turnstile === 'undefined') return;
        var widget = form.querySelector('.cf-turnstile');
        if (widget) {
            window.turnstile.reset(widget);
        }
    }
})();
