{{--
    Komponen Pemutar Suara Notifikasi (Notification Sound Player)
    Mendukung Web Audio API Chime Synthesizer & Audio File Fallback (/sounds/notification.wav).
--}}
<audio id="filament-notification-sound" preload="auto" src="{{ asset('sounds/notification.wav') }}" class="hidden" aria-hidden="true"></audio>

<script>
(function () {
    let audioCtx = null;
    let isAudioUnlocked = false;

    function getAudioContext() {
        if (!audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                audioCtx = new AudioContextClass();
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    // Unlock audio context saat ada interaksi pengguna pertama kali
    function unlockAudio() {
        if (isAudioUnlocked) return;
        const ctx = getAudioContext();
        if (ctx) {
            if (ctx.state === 'suspended') {
                ctx.resume();
            }
            isAudioUnlocked = true;
        }
    }

    ['click', 'keydown', 'touchstart'].forEach(evt => {
        window.addEventListener(evt, unlockAudio, { once: true, passive: true });
    });

    // Fungsi pemutar suara notifikasi yang lembut dan jernih (Dual-tone bell chime)
    window.playNotificationSound = function () {
        try {
            const ctx = getAudioContext();
            if (ctx && ctx.state !== 'suspended') {
                const now = ctx.currentTime;

                // Nada 1: 659.25 Hz (E5)
                const osc1 = ctx.createOscillator();
                const gain1 = ctx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(659.25, now);
                gain1.gain.setValueAtTime(0.25, now);
                gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.35);
                osc1.connect(gain1);
                gain1.connect(ctx.destination);
                osc1.start(now);
                osc1.stop(now + 0.35);

                // Nada 2: 880.00 Hz (A5) - Harmonisasi lonceng
                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(880.00, now + 0.09);
                gain2.gain.setValueAtTime(0.0001, now);
                gain2.gain.setValueAtTime(0.3, now + 0.09);
                gain2.gain.exponentialRampToValueAtTime(0.0001, now + 0.55);
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.start(now + 0.09);
                osc2.stop(now + 0.55);
                return;
            }
        } catch (e) {
            // fallback ke HTML5 audio
        }

        // Fallback ke elemen <audio>
        try {
            const audioElem = document.getElementById('filament-notification-sound');
            if (audioElem) {
                audioElem.currentTime = 0;
                audioElem.play().catch(() => {});
            }
        } catch (err) {}
    };

    // Event listener global
    window.addEventListener('play-notification-sound', () => window.playNotificationSound());
    window.addEventListener('database-notifications-sent', () => window.playNotificationSound());
    window.addEventListener('notification-sent', () => window.playNotificationSound());

    if (window.Livewire) {
        window.Livewire.on('databaseNotificationsSent', () => window.playNotificationSound());
        window.Livewire.on('notificationSent', () => window.playNotificationSound());
    } else {
        document.addEventListener('livewire:init', () => {
            window.Livewire.on('databaseNotificationsSent', () => window.playNotificationSound());
            window.Livewire.on('notificationSent', () => window.playNotificationSound());
        });
    }
})();
</script>
