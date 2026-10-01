<x-filament-panels::page>
    <div id="messages-container" class="messages-container flex flex-col lg:flex-row gap-6 w-full overflow-hidden">
        <div class="flex-1 min-w-0 h-full min-h-0">
            @if ($selectedConversation)
                <livewire:fm-user-messages :selectedConversation="$selectedConversation" />
            @else
                <livewire:fm-user-inbox />
            @endif
        </div>
    </div>

    <script>
        (function () {
            function fitMessagesContainer() {
                var container = document.getElementById('messages-container');
                if (!container) return;

                // visualViewport is the only reliable height on mobile — it
                // accounts for the on-screen keyboard, browser chrome, and
                // safe-area insets shrinking the visible area.
                var viewportHeight = window.visualViewport
                    ? window.visualViewport.height
                    : window.innerHeight;

                // rect.top = distance from the TOP of the visible viewport to
                // the top of the container — already includes topbar height,
                // page-heading height, and any Filament wrapper padding.
                // Works correctly even with position:fixed topbar because the
                // fixed topbar is NOT in the document flow and the Filament
                // layout adds padding-top equal to its own height.
                var rect = container.getBoundingClientRect();

                var isDesktop = window.innerWidth >= 1024;

                var bottomClearance;
                if (isDesktop) {
                    // Footer height (~40px) + a little breathing room
                    var footer = document.querySelector('.fi-footer') || document.querySelector('footer');
                    var footerH = footer ? footer.getBoundingClientRect().height : 0;
                    bottomClearance = footerH + 16;
                } else {
                    // Bottom-nav (64px) + CSS safe-area-inset-bottom
                    // Read the actual safe-area value from CSS env() via a dummy element
                    var safeBottom = 0;
                    try {
                        var probe = document.getElementById('_sai_probe');
                        if (!probe) {
                            probe = document.createElement('div');
                            probe.id = '_sai_probe';
                            probe.style.cssText = 'position:fixed;bottom:0;height:env(safe-area-inset-bottom,0px);width:0;pointer-events:none;visibility:hidden;';
                            document.body.appendChild(probe);
                        }
                        safeBottom = probe.getBoundingClientRect().height || 0;
                    } catch(e) {}
                    bottomClearance = 64 + safeBottom + 8; // bottom-nav + safe-area + gap
                }

                var height = viewportHeight - rect.top - bottomClearance;
                container.style.height = Math.max(height, 280) + 'px';
            }

            function schedulefit() {
                requestAnimationFrame(function () {
                    requestAnimationFrame(fitMessagesContainer);
                });
            }

            document.addEventListener('DOMContentLoaded', schedulefit);
            window.addEventListener('resize', fitMessagesContainer);

            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', fitMessagesContainer);
                window.visualViewport.addEventListener('scroll', fitMessagesContainer);
            }

            document.addEventListener('livewire:navigated', schedulefit);

            // Fallback for slow hydration
            setTimeout(fitMessagesContainer, 200);
            setTimeout(fitMessagesContainer, 600);
        })();
    </script>
</x-filament-panels::page>
