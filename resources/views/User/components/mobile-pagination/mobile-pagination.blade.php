<div id="fi-mobile-pagination-root"></div>

<style>
#fi-mobile-pagination-pill {
    display: flex;
    align-items: stretch;
    justify-content: center;
    background: #1a1a2e;
    border-radius: 1rem;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.08);
    width: fit-content;
    margin: 1.25rem auto 0.5rem;
    box-shadow: 0 4px 24px rgba(0,0,0,0.4);
}

#fi-mobile-pagination-pill .fi-mpag-btn,
#fi-mobile-pagination-pill .fi-mpag-page {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 3rem;
    height: 3rem;
    font-size: 1.1rem;
    font-weight: 700;
    cursor: pointer;
    border: none;
    background: transparent;
    color: #9ca3af;
    transition: background 0.15s, color 0.15s;
    border-right: 1px solid rgba(255,255,255,0.07);
    padding: 0 0.25rem;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

#fi-mobile-pagination-pill .fi-mpag-btn:last-child,
#fi-mobile-pagination-pill .fi-mpag-page:last-child {
    border-right: none;
}

#fi-mobile-pagination-pill .fi-mpag-btn:active,
#fi-mobile-pagination-pill .fi-mpag-page:active {
    background: rgba(255,255,255,0.08);
}

#fi-mobile-pagination-pill .fi-mpag-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

#fi-mobile-pagination-pill .fi-mpag-page.active {
    color: #f59e0b;
    background: rgba(245,158,11,0.08);
}

#fi-mobile-pagination-pill .fi-mpag-page:not(.active) {
    color: #60a5fa;
}

#fi-mobile-pagination-pill .fi-mpag-btn {
    color: #9ca3af;
    font-size: 1.2rem;
    min-width: 2.75rem;
}
</style>

<script>
(function () {
    function isMobileUA() {
        return /android|iphone|ipad|ipod|mobile|blackberry|windows phone|nativephp/i.test(navigator.userAgent) ||
               window.location.protocol === 'capacitor:' ||
               (window.location.hostname === 'localhost' && window.innerWidth < 768);
    }

    if (!isMobileUA()) return;

    function buildPagination() {
        const root = document.getElementById('fi-mobile-pagination-root');
        if (!root) return;

        // Remove old pill
        const old = document.getElementById('fi-mobile-pagination-pill');
        if (old) old.remove();

        // Find hidden Filament pagination
        const paginationEl = document.querySelector('.fi-ta-pagination');
        if (!paginationEl) return;

        // Get all page buttons from hidden pagination
        const allBtns = paginationEl.querySelectorAll('button, [role="button"], a');

        let prevBtn = null, nextBtn = null;
        const pageButtons = [];

        allBtns.forEach(btn => {
            const label = (btn.getAttribute('aria-label') || btn.textContent || '').trim();
            const rel = btn.getAttribute('rel') || '';
            const wireClick = btn.getAttribute('wire:click') || '';

            if (rel === 'prev' || wireClick.includes('previousPage') || label.toLowerCase().includes('previous') || label === '‹' || label === '«' || label === '<') {
                prevBtn = btn;
            } else if (rel === 'next' || wireClick.includes('nextPage') || label.toLowerCase().includes('next') || label === '›' || label === '»' || label === '>') {
                nextBtn = btn;
            } else if (/^\d+$/.test(label)) {
                pageButtons.push({ btn, label, active: btn.getAttribute('aria-current') === 'page' || btn.classList.contains('active') || btn.getAttribute('aria-disabled') === 'true' });
            }
        });

        // Need at least prev or next to render
        if (!prevBtn && !nextBtn && pageButtons.length === 0) return;

        // Build pill
        const pill = document.createElement('div');
        pill.id = 'fi-mobile-pagination-pill';

        // Prev button
        const prev = document.createElement('button');
        prev.className = 'fi-mpag-btn';
        prev.innerHTML = '&#8249;';
        prev.disabled = !prevBtn || prevBtn.disabled || prevBtn.getAttribute('aria-disabled') === 'true';
        prev.addEventListener('click', () => { if (prevBtn) prevBtn.click(); });
        pill.appendChild(prev);

        // Page number buttons
        pageButtons.forEach(({ btn, label, active }) => {
            const pg = document.createElement('button');
            pg.className = 'fi-mpag-page' + (active ? ' active' : '');
            pg.textContent = label;
            pg.addEventListener('click', () => btn.click());
            pill.appendChild(pg);
        });

        // Next button
        const next = document.createElement('button');
        next.className = 'fi-mpag-btn';
        next.innerHTML = '&#8250;';
        next.disabled = !nextBtn || nextBtn.disabled || nextBtn.getAttribute('aria-disabled') === 'true';
        next.addEventListener('click', () => { if (nextBtn) nextBtn.click(); });
        pill.appendChild(next);

        root.appendChild(pill);
    }

    // Initial build
    document.addEventListener('DOMContentLoaded', () => setTimeout(buildPagination, 400));

    // Rebuild after every Livewire update
    if (window.Livewire) {
        window.Livewire.hook('commit', ({ succeed }) => {
            succeed(() => setTimeout(buildPagination, 300));
        });
    }

    document.addEventListener('livewire:update', () => setTimeout(buildPagination, 300));
    document.addEventListener('livewire:navigated', () => setTimeout(buildPagination, 400));
})();
</script>
