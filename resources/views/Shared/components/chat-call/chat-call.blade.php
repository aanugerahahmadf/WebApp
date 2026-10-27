@props(['inboxId', 'modalId'])
<div x-data="chatCall({{ (int) $inboxId }})" x-init="init()" class="flex flex-col items-center gap-4 py-6 text-center">
    <x-filament::icon icon="/images/icons/phone.svg" class="h-10 w-10" />
    <p class="text-sm text-gray-500" x-text="status"></p>
    <div class="flex gap-2">
        <button type="button" x-show="incoming" x-on:click="accept()" class="rounded-lg bg-success-600 px-4 py-2 text-white">{{ __('Terima') }}</button>
        <button type="button" x-show="!active && !incoming" x-on:click="start()" class="rounded-lg bg-primary-600 px-4 py-2 text-white">{{ __('Panggil') }}</button>
        <button type="button" x-show="active || incoming" x-on:click="end()" class="rounded-lg bg-danger-600 px-4 py-2 text-white">{{ __('Akhiri') }}</button>
        <button type="button" x-on:click="$dispatch('close-modal', { id: '{{ $modalId }}' })" class="rounded-lg border px-4 py-2">{{ __('Tutup') }}</button>
    </div>
</div>
@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('chatCall', (inboxId) => ({
        token: null, pc: null, timer: null, seen: new Set(), active: false, incoming: false,
        status: @js(__('Siap melakukan panggilan')),
        async request(path, options = {}) {
            const response = await fetch(path, { credentials: 'same-origin', ...options, headers: {
                'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', ...(options.headers || {})
            }});
            if (!response.ok) throw new Error(await response.text());
            return response.json();
        },
        async init() { this.timer = setInterval(() => this.refresh(), 1000); await this.refresh(); },
        async refresh() {
            try { const result = await this.request(`/chat-calls/${inboxId}/active`); const call = result.call;
                if (call && !this.token) { this.token = call.token; this.incoming = call.role === 'callee'; this.status = @js(__('Panggilan masuk')); }
                if (this.token) await this.sync();
            } catch (_) {}
        },
        async start() {
            try { const result = await this.request('/chat-calls', {method:'POST', body: JSON.stringify({inbox_id: inboxId})}); this.token = result.call.token; await this.setup(true); this.status = @js(__('Memanggil…')); } catch (_) { this.status = @js(__('Panggilan gagal')); }
        },
        async accept() { try { await this.request(`/chat-calls/${this.token}/accept`, {method:'POST', body:'{}'}); await this.setup(false); this.active = true; this.incoming = false; this.status = @js(__('Tersambung')); } catch (_) {} },
        async setup(caller) {
            this.pc = new RTCPeerConnection({iceServers: []});
            const stream = await navigator.mediaDevices.getUserMedia({audio:true, video:false});
            stream.getTracks().forEach(track => this.pc.addTrack(track, stream));
            this.pc.onicecandidate = e => e.candidate && this.signal('ice', {candidate:e.candidate.candidate, sdpMid:e.candidate.sdpMid, sdpMLineIndex:e.candidate.sdpMLineIndex});
            this.pc.onconnectionstatechange = () => { if (['connected','completed'].includes(this.pc.connectionState)) { this.active = true; this.status = @js(__('Tersambung')); } };
            if (caller) { const offer = await this.pc.createOffer(); await this.pc.setLocalDescription(offer); await this.signal('offer', offer); }
        },
        async sync() { const result = await this.request(`/chat-calls/${this.token}`); const call = result.call;
            if (call.answer && this.pc && !this.pc.currentRemoteDescription) await this.pc.setRemoteDescription(call.answer);
            if (call.offer && this.incoming && this.pc && !this.pc.currentRemoteDescription) { await this.pc.setRemoteDescription(call.offer); const answer = await this.pc.createAnswer(); await this.pc.setLocalDescription(answer); await this.signal('answer', answer); this.active = true; }
            for (const c of (call.ice_candidates || [])) { const key = JSON.stringify(c); if (!this.seen.has(key)) { this.seen.add(key); await this.pc?.addIceCandidate(c); } }
        },
        async signal(type, value) { await this.request(`/chat-calls/${this.token}/signal`, {method:'POST', body:JSON.stringify({type, value})}); },
        async end() { if (this.token) await this.request(`/chat-calls/${this.token}/end`, {method:'POST',body:'{}'}); clearInterval(this.timer); this.pc?.close(); this.active=false; this.incoming=false; this.status=@js(__('Panggilan berakhir')); }
    }));
});
</script>
@endonce
