<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Panggilan Internal') }}</title>
    @vite(['resources/css/Shared/Shared.css'])
</head>
<body class="min-h-screen bg-gray-950 text-white">
    <main class="mx-auto flex min-h-screen max-w-5xl flex-col gap-6 p-6">
        <header class="flex items-center justify-between">
            <div>
                <p class="text-sm text-emerald-300">{{ __('Panggilan di dalam aplikasi') }}</p>
                <h1 class="text-2xl font-bold">{{ __('Panggilan Internal') }}</h1>
            </div>
            <a href="{{ url('/messages/'.$inbox->id) }}" class="rounded-lg bg-white/10 px-4 py-2 hover:bg-white/20">
                {{ __('Kembali ke Pesan') }}
            </a>
        </header>

        <section class="grid flex-1 gap-4 md:grid-cols-2">
            <div class="relative min-h-72 overflow-hidden rounded-2xl bg-gray-900 ring-1 ring-white/10">
                <video id="local-video" class="h-full min-h-72 w-full object-cover" autoplay muted playsinline></video>
                <span class="absolute bottom-3 left-3 rounded bg-black/60 px-3 py-1 text-sm">{{ __('Anda') }}</span>
            </div>
            <div class="flex min-h-72 items-center justify-center rounded-2xl bg-gray-900 ring-1 ring-white/10">
                <div class="text-center">
                    <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-600 text-3xl">☎</div>
                    <p id="call-status" class="text-lg">{{ __('Menyiapkan kamera dan mikrofon...') }}</p>
                    <p class="mt-2 text-sm text-gray-400">{{ __('Panggilan menggunakan koneksi internal aplikasi.') }}</p>
                </div>
            </div>
        </section>

        <footer class="flex justify-center gap-3">
            <button id="mute-button" type="button" class="rounded-lg bg-white/10 px-5 py-3" disabled>{{ __('Mikrofon') }}</button>
            <button id="camera-button" type="button" class="rounded-lg bg-white/10 px-5 py-3" disabled>{{ __('Kamera') }}</button>
            <a href="{{ url('/messages/'.$inbox->id) }}" class="rounded-lg bg-red-600 px-5 py-3">{{ __('Akhiri') }}</a>
        </footer>
    </main>
    <script>
        (async () => {
            const status = document.getElementById('call-status');
            const video = document.getElementById('local-video');
            const mute = document.getElementById('mute-button');
            const camera = document.getElementById('camera-button');
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
                video.srcObject = stream;
                mute.disabled = false;
                camera.disabled = false;
                status.textContent = @json(__('Kamera dan mikrofon siap.'));
                mute.onclick = () => stream.getAudioTracks().forEach(track => track.enabled = !track.enabled);
                camera.onclick = () => stream.getVideoTracks().forEach(track => track.enabled = !track.enabled);
                window.addEventListener('beforeunload', () => stream.getTracks().forEach(track => track.stop()));
            } catch (error) {
                status.textContent = @json(__('Izin kamera atau mikrofon diperlukan untuk panggilan.'));
            }
        })();
    </script>
</body>
</html>
