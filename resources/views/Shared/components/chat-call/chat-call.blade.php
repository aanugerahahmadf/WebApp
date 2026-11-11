@props(['inboxId', 'modalId'])
<div x-data="chatCall({{ (int) $inboxId }})" x-init="init()" x-on:close-modal.window="if (($event.detail?.id || '') === '{{ $modalId }}') cleanup(false)" class="flex flex-col items-center gap-4 py-6 text-center">
    <x-filament::icon icon="/images/icons/phone.svg" class="h-10 w-10" />
    <p class="text-sm text-gray-500" x-text="status"></p>
    <audio x-ref="remoteAudio" autoplay playsinline class="hidden"></audio>
    <div class="flex gap-2">
        <button type="button" x-show="incoming" x-on:click="accept()" class="rounded-lg bg-success-600 px-4 py-2 text-white">{{ __('Terima') }}</button>
        <button type="button" x-show="!active && !incoming && !token" x-on:click="start()" class="rounded-lg bg-primary-600 px-4 py-2 text-white">{{ __('Panggil') }}</button>
        <button type="button" x-show="active || incoming || token" x-on:click="end()" class="rounded-lg bg-danger-600 px-4 py-2 text-white">{{ __('Akhiri') }}</button>
        <button type="button" x-on:click="$dispatch('close-modal', { id: '{{ $modalId }}' }); cleanup(false)" class="rounded-lg border px-4 py-2">{{ __('Tutup') }}</button>
    </div>
</div>
@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('chatCall', (inboxId) => ({
        token: null,
        pc: null,
        localStream: null,
        timer: null,
        seen: new Set(),
        active: false,
        incoming: false,
        role: null,
        status: @js(__('Siap melakukan panggilan')),
        async request(path, options = {}) {
            const response = await fetch(path, {
                credentials: 'same-origin',
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    ...(options.headers || {})
                }
            });
            if (!response.ok) throw new Error(await response.text());
            return response.json();
        },
        async init() {
            if (this.timer) clearInterval(this.timer);
            this.timer = setInterval(() => this.refresh(), 1000);
            await this.refresh();
        },
        async refresh() {
            try {
                if (!this.token) {
                    const result = await this.request(`/chat-calls/${inboxId}/active`);
                    const call = result.call;
                    if (call) {
                        this.token = call.token;
                        this.role = call.role;
                        this.incoming = call.role === 'callee' && call.status === 'ringing';
                        this.status = this.incoming ? @js(__('Panggilan masuk')) : @js(__('Menghubungkan...'));
                    }
                }
                if (this.token) await this.sync();
            } catch (_) {}
        },
        async start() {
            try {
                this.status = @js(__('Memanggil…'));
                const result = await this.request('/chat-calls', { method: 'POST', body: JSON.stringify({ inbox_id: inboxId }) });
                this.token = result.call.token;
                this.role = 'caller';
                await this.setup(true);
            } catch (e) {
                this.status = @js(__('Panggilan gagal'));
                console.error(e);
            }
        },
        async accept() {
            try {
                if (!this.token) return;
                await this.request(`/chat-calls/${this.token}/accept`, { method: 'POST', body: '{}' });
                this.incoming = false;
                this.role = 'callee';
                this.status = @js(__('Menghubungkan...'));
                await this.setup(false);
                await this.sync();
            } catch (e) {
                this.status = @js(__('Gagal menerima panggilan'));
                console.error(e);
            }
        },
        async setup(caller) {
            if (this.pc) return;
            this.pc = new RTCPeerConnection({
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' }
                ]
            });
            this.pc.ontrack = (event) => {
                const [stream] = event.streams;
                if (stream && this.$refs.remoteAudio) {
                    this.$refs.remoteAudio.srcObject = stream;
                }
            };
            this.pc.onicecandidate = (e) => {
                if (e.candidate) {
                    this.signal('ice', {
                        candidate: e.candidate.candidate,
                        sdpMid: e.candidate.sdpMid,
                        sdpMLineIndex: e.candidate.sdpMLineIndex
                    }).catch(() => {});
                }
            };
            this.pc.onconnectionstatechange = () => {
                if (['connected', 'completed'].includes(this.pc.connectionState)) {
                    this.active = true;
                    this.status = @js(__('Tersambung'));
                }
                if (['failed', 'disconnected', 'closed'].includes(this.pc.connectionState) && this.active) {
                    this.status = @js(__('Koneksi terputus'));
                }
            };
            this.localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
            this.localStream.getTracks().forEach(track => this.pc.addTrack(track, this.localStream));
            if (caller) {
                const offer = await this.pc.createOffer();
                await this.pc.setLocalDescription(offer);
                await this.signal('offer', offer);
            }
        },
        async sync() {
            if (!this.token) return;
            const result = await this.request(`/chat-calls/${this.token}`);
            const call = result.call;
            if (!call) return;

            if (['ended', 'missed', 'declined'].includes(call.status)) {
                this.status = @js(__('Panggilan berakhir'));
                this.cleanup(false);
                return;
            }

            if (call.role === 'callee' && call.status === 'ringing' && !this.pc) {
                this.incoming = true;
                this.role = 'callee';
                this.status = @js(__('Panggilan masuk'));
            }

            if (call.role === 'caller' && call.status !== 'ringing') {
                this.status = this.active ? @js(__('Tersambung')) : @js(__('Menghubungkan...'));
            }

            if (this.pc) {
                if (call.answer && !this.pc.currentRemoteDescription && call.role === 'caller') {
                    await this.pc.setRemoteDescription(call.answer);
                }
                if (call.offer && call.role === 'callee' && !this.pc.currentRemoteDescription && !this.incoming) {
                    await this.pc.setRemoteDescription(call.offer);
                    const answer = await this.pc.createAnswer();
                    await this.pc.setLocalDescription(answer);
                    await this.signal('answer', answer);
                    this.active = true;
                    this.status = @js(__('Tersambung'));
                }
                for (const c of (call.ice_candidates || [])) {
                    const key = JSON.stringify(c);
                    if (!this.seen.has(key)) {
                        this.seen.add(key);
                        try { await this.pc.addIceCandidate(c); } catch (_) {}
                    }
                }
            }
        },
        async signal(type, value) {
            if (!this.token) return;
            await this.request(`/chat-calls/${this.token}/signal`, { method: 'POST', body: JSON.stringify({ type, value }) });
        },
        async end() {
            try {
                if (this.token) await this.request(`/chat-calls/${this.token}/end`, { method: 'POST', body: '{}' });
            } catch (_) {}
            this.status = @js(__('Panggilan berakhir'));
            this.cleanup(false);
        },
        cleanup(notify = false) {
            if (this.timer) {
                // keep polling to detect new incoming calls when modal reopened? better keep timer only if desired; stop for now
            }
            if (this.pc) { try { this.pc.close(); } catch (_) {} }
            if (this.localStream) { try { this.localStream.getTracks().forEach(t => t.stop()); } catch (_) {} }
            this.pc = null;
            this.localStream = null;
            this.token = null;
            this.active = false;
            this.incoming = false;
            this.role = null;
            this.seen = new Set();
        }
    }));
});
</script>
@endonce
