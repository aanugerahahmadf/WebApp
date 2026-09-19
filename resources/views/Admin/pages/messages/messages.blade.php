<x-filament-panels::page>
    <style>
        /*
         * Initial CSS height — overridden immediately by JS after paint.
         * These are just fallback values to prevent layout flash.
         * Admin panel uses sidebar-based layout; no bottom-nav.
         */
        .messages-container {
            height: calc(100dvh - 13rem);
            min-height: 300px;
        }
        @media (max-width: 1023px) {
            .messages-container { height: calc(100dvh - 10rem); }
        }
        @media (max-width: 639px) {
            .messages-container { height: calc(100dvh - 8rem); }
        }

        /*
         * Admin panel: no bottom-nav, so the container manages its own height.
         * Remove generic .fi-main padding-bottom so the JS calculation is the
         * single source of truth.
         */
        @media (max-width: 1023px) {
            .fi-panel-admin .fi-main:has(#messages-container) {
                padding-bottom: 1rem !important;
            }
        }

        .chat-msg-flash {
            animation: chatMsgFlash 1.2s ease-in-out;
        }
        @keyframes chatMsgFlash {
            0%, 100% { background-color: transparent; }
            30% { background-color: rgba(250, 204, 21, 0.25); border-radius: 12px; }
        }
    </style>

    <div id="messages-container" class="messages-container flex flex-col lg:flex-row gap-6 w-full overflow-hidden">
        {{-- Inbox List --}}
        <div @class([
            'w-full h-full min-h-0',
            'hidden' => $selectedConversation,
            'block'  => !$selectedConversation,
        ])>
            <livewire:fm-admin-inbox :selectedConversation="$selectedConversation" />
        </div>

        {{-- Message Content --}}
        <div @class([
            'flex-1 min-w-0 h-full min-h-0',
            'block'  => $selectedConversation,
            'hidden' => !$selectedConversation,
        ])>
            <livewire:fm-admin-messages :selectedConversation="$selectedConversation" />
        </div>
    </div>

    <script>
        (function () {
            function fitMessagesContainer() {
                var container = document.getElementById('messages-container');
                if (!container) return;

                var viewportHeight = window.visualViewport
                    ? window.visualViewport.height
                    : window.innerHeight;

                var rect = container.getBoundingClientRect();

                var isDesktop = window.innerWidth >= 1024;

                var bottomClearance;
                if (isDesktop) {
                    var footer = document.querySelector('.fi-footer') || document.querySelector('footer');
                    var footerH = footer ? footer.getBoundingClientRect().height : 0;
                    bottomClearance = footerH + 16;
                } else {
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
                    bottomClearance = safeBottom + 8;
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

            setTimeout(fitMessagesContainer, 200);
            setTimeout(fitMessagesContainer, 600);
        })();
    </script>
</x-filament-panels::page>