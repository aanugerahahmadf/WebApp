(function () {
    'use strict';

    /**
     * Returns true if the current page is the user panel.
     * Admin panel uses fi-panel-admin on <body>, user panel uses fi-panel-user.
     */
    function isUserPanel() {
        return document.body.classList.contains('fi-panel-user');
    }

    /**
     * Expand a single navigation group button if it's collapsed.
     * @param {HTMLElement} btn
     */
    function expandGroup(btn) {
        var expanded = btn.getAttribute('aria-expanded');
        if (expanded === 'false' || expanded === null) {
            btn.click();
        }
    }

    /**
     * Expand ALL navigation groups in the sidebar (user panel only).
     */
    function expandAllGroups() {
        if (!isUserPanel()) return;
        document.querySelectorAll('.fi-sidebar-group-button').forEach(expandGroup);
    }

    /**
     * Prevent groups from being collapsed when clicked (user panel only).
     * Admin panel allows normal collapse/expand behavior.
     */
    function lockGroupsOpen() {
        if (!isUserPanel()) return;
        document.querySelectorAll('.fi-sidebar-group-button').forEach(function (btn) {
            if (!btn.dataset.lockBound) {
                btn.dataset.lockBound = '1';
                btn.addEventListener('click', function (e) {
                    var expanded = btn.getAttribute('aria-expanded');
                    // If already expanded, prevent collapsing
                    if (expanded === 'true') {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                }, true); // capture phase so we intercept before Livewire/Alpine
            }
        });
    }

    function init() {
        expandAllGroups();
        lockGroupsOpen();
    }

    // Run on initial page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(init, 300);
        });
    } else {
        setTimeout(init, 300);
    }

    // Run when sidebar opens — watch for class/attribute changes on .fi-sidebar
    var sidebarObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            var sidebar = document.querySelector('.fi-sidebar');
            if (sidebar && (
                sidebar.classList.contains('fi-sidebar-open') ||
                sidebar.dataset.open === 'true' ||
                sidebar.getAttribute('aria-expanded') === 'true'
            )) {
                setTimeout(init, 150);
            }
        });
    });

    function observeSidebar() {
        var sidebar = document.querySelector('.fi-sidebar');
        if (sidebar) {
            sidebarObserver.observe(sidebar, { attributes: true, childList: false });
        } else {
            setTimeout(observeSidebar, 500);
        }
    }

    observeSidebar();

    // Re-run after Livewire SPA navigation
    document.addEventListener('livewire:navigated', function () {
        setTimeout(init, 400);
        setTimeout(observeSidebar, 500);
    });

    // Also watch for new group buttons rendered dynamically (user panel only)
    var domObserver = new MutationObserver(function () {
        if (isUserPanel()) {
            lockGroupsOpen();
        }
    });

    domObserver.observe(document.body, { childList: true, subtree: true });
})();
