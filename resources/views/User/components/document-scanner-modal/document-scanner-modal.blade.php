@php
    $modalId = 'document-scanner-modal';
@endphp

{{--
    Document Scanner — KTP / Passport / SIM / NPWP.
    Scans a physical document using the camera:
      • Live camera preview (WebRTC desktop / native camera mobile)
      • Automatic border detection + highlight overlay
      • Auto-crop + perspective correction
      • Manual corner adjustment for precision
      • "Ambil Foto / Gunakan" flow

    Usage: dispatch Livewire event 'open-document-scanner' to scan into ktp_photo field.
--}}
<div
    x-data="{
        isOpen: false,
        scannerState: 'pick',   // 'pick' | 'live' | 'editNative' | 'preview'
        scanning: false,
        hasDocument: false,

        stream: null,
        facingMode: 'environment',
        videoReady: false,

        // Card fixed aspect guide (KTP 85.6x54 ≈ 1.585) — normalized within the preview box
        cardGuide: { w: 0.62, h: 0.62 / 1.585, cx: 0.5, cy: 0.5 },

        corners: [ {x:0.1,y:0.1}, {x:0.9,y:0.1}, {x:0.9,y:0.9}, {x:0.1,y:0.9} ],  // normalized TL,TR,BR,BL
        dragCorner: null,
        resultDataUrl: null,

        open() {
            this.isOpen = true;
            this.scannerState = 'pick';
            this.scanning = false;
            this.hasDocument = false;
            this.cardGuide = { w: 0.62, h: 0.62 / 1.585, cx: 0.5, cy: 0.5 };
            document.body.classList.add('overflow-hidden');
        },

        close() {
            this.stopCamera();
            this.isOpen = false;
            this.scannerState = 'pick';
            this.resultDataUrl = null;
            document.body.classList.remove('overflow-hidden');
        },

        isMobile() { return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent); },

        /* ── native camera (mobile) ── */
        useNativeCamera() {
            this.close();
            setTimeout(() => {
                const el = document.getElementById('docscan-native-input');
                if (el) el.click();
            }, 150);
        },

        useGallery() {
            this.close();
            setTimeout(() => {
                const el = document.getElementById('docscan-gallery-input');
                if (el) el.click();
            }, 150);
        },

        onNativePicked(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (file) { this.open(); this.processFile(file); }
        },

        /* ── WebRTC live camera (desktop + web) ── */
        async startLiveCamera() {
            this.scannerState = 'live';
            this.videoReady = false;
            await this.$nextTick();
            const video = this.$refs.scanVideo;
            if (!video) return;
            try {
                if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); }
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: this.facingMode, width: { ideal: 1920 }, height: { ideal: 1080 } },
                    audio: false,
                });
                video.srcObject = this.stream;
                await video.play();
                this.videoReady = true;
            } catch (e) {
                alert('{{ __('Tidak dapat mengakses kamera. Pastikan izin kamera diberikan.') }}');
                this.scannerState = 'pick';
            }
        },

        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
            this.videoReady = false;
        },

        /* ── Edge detection ── */
        detectLoop() {
            if (!this.isOpen || this.scannerState !== 'live' || this.scanning) return;
            requestAnimationFrame(() => this.detectFrame());
        },

        detectFrame() {
            if (!this.isOpen || this.scannerState !== 'live') return;
            const video = this.$refs.scanVideo;
            const canvas = this.$refs.detectCanvas;
            if (video && canvas && video.readyState >= 2 && video.videoWidth > 0) {
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                const det = this.findDocumentCorners(ctx, canvas.width, canvas.height);
                if (det) {
                    this.corners = det;
                    this.hasDocument = true;
                } else {
                    this.hasDocument = false;
                }
            }
            if (this.isOpen && this.scannerState === 'live') {
                requestAnimationFrame(() => this.detectFrame());
            }
        },

        // Return normalized corner array [TL,TR,BR,BL] mapped to the video, or null.
        findDocumentCorners(ctx, w, h) {
            try {
                const down = 4;
                const dw = Math.floor(w / down), dh = Math.floor(h / down);
                const tmp = document.createElement('canvas');
                tmp.width = dw; tmp.height = dh;
                const tctx = tmp.getContext('2d');
                tctx.drawImage(ctx.canvas, 0, 0, w, h, 0, 0, dw, dh);
                const id = tctx.getImageData(0, 0, dw, dh);
                const data = id.data;

                // Grayscale
                const gray = new Float32Array(dw * dh);
                for (let i = 0, p = 0; i < data.length; i += 4, p++) {
                    gray[p] = 0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2];
                }

                // Brightness map (large box blur) to subtract lighting gradient
                const blur = this.brightnessMap(gray, dw, dh);
                for (let p = 0; p < gray.length; p++) gray[p] -= blur[p];

                // Sobel magnitude
                const mag = new Float32Array(dw * dh);
                let maxMag = 0;
                for (let y = 1; y < dh - 1; y++) {
                    for (let x = 1; x < dw - 1; x++) {
                        const i = y * dw + x;
                        const gx = -gray[i-dw-1] - 2*gray[i-1] - gray[i+dw-1]
                                 + gray[i-dw+1] + 2*gray[i+1] + gray[i+dw+1];
                        const gy = -gray[i-dw-1] - 2*gray[i-dw] - gray[i-dw+1]
                                 + gray[i+dw-1] + 2*gray[i+dw] + gray[i+dw+1];
                        const m = Math.sqrt(gx*gx + gy*gy);
                        mag[i] = m;
                        if (m > maxMag) maxMag = m;
                    }
                }
                if (maxMag < 1) return null;

                // Hough-like: find strongest large quadrilateral using corner scoring.
                // Simplify: find the extreme contour points (approx largest quadr edge).
                // Use a coarse approach: sample border pixels and fit lines.

                // Threshold
                const thresh = maxMag * 0.25;
                // Accumulate points of each of 4 edges (outer ~60% region)
                const pts = { top: [], bottom: [], left: [], right: [] };
                const margin = Math.floor(dw * 0.08), m2 = Math.floor(dw * 0.05);
                for (let y = margin; y < dh - margin; y++) {
                    for (let x = margin; x < dw - margin; x++) {
                        const i = y * dw + x;
                        if (mag[i] < thresh) continue;
                        const nx = x / dw, ny = y / dh;
                        // classify which side is most likely
                        let best = 99, side = null;
                        const dTop = ny, dBot = 1 - ny, dL = nx, dR = 1 - nx;
                        if (dTop < best) { best = dTop; side = 'top'; }
                        if (dBot < best) { best = dBot; side = 'bottom'; }
                        if (dL < best) { best = dL; side = 'left'; }
                        if (dR < best) { best = dR; side = 'right'; }
                        if (side && pts[side].length < 4000) pts[side].push([nx, ny]);
                    }
                }

                // If not enough edge points in a side, fall back to that side's screen edge.
                const edge = (arr, minLen, fallbackN) => (arr.length >= minLen)
                    ? this.fitLine(arr)
                    : { x0: fallbackN === 0 ? 0 : 1, y0: fallbackN === 2 ? 0 : 0, x1: fallbackN === 0 ? 0 : 1, y1: fallbackN === 2 ? 1 : 0 };

                const topL = this.fitLine(pts.top);   // approximate line a*x + b*y + c = 0
                // We'll just compute intersections of fitted lines.
                const ls = {
                    top: this.fitLine(pts.top),
                    bottom: this.fitLine(pts.bottom),
                    left: this.fitLine(pts.left),
                    right: this.fitLine(pts.right),
                };

                // if any side lacks enough points, return null (not detected yet)
                if (pts.top.length < 5 || pts.bottom.length < 5 || pts.left.length < 5 || pts.right.length < 5) {
                    return null;
                }

                // Intersections of lines (a,b,c in normalized space)
                const inter = (L1, L2) => {
                    const detn = L1.a * L2.b - L2.a * L1.b;
                    if (Math.abs(detn) < 1e-9) return null;
                    const x = (L1.b * L2.c - L2.b * L1.c) / detn;
                    const y = (L2.a * L1.c - L1.a * L2.c) / detn;
                    return { x, y };
                };
                const tl = inter(ls.top, ls.left);
                const tr = inter(ls.top, ls.right);
                const br = inter(ls.bottom, ls.right);
                const bl = inter(ls.bottom, ls.left);

                if (!tl || !tr || !br || !bl) return null;

                // Sanity: corners should be roughly within screen with small overflow
                const ok = (p) => p && Number.isFinite(p.x) && Number.isFinite(p.y);
                if (![tl, tr, br, bl].every(ok)) return null;

                // Clamp
                const clamp = (p) => ({ x: Math.min(1, Math.max(0, p.x)), y: Math.min(1, Math.max(0, p.y)) });
                return [clamp(tl), clamp(tr), clamp(br), clamp(bl)];
            } catch (e) {
                return null;
            }
        },

        // Fit a line a*x + b*y + c = 0 to normalized points via least squares (total least squares / eigen)
        fitLine(pts) {
            // normalize
            let cx = 0, cy = 0;
            for (const p of pts) { cx += p[0]; cy += p[1]; }
            cx /= pts.length; cy /= pts.length;
            let xx = 0, yy = 0, xy = 0;
            for (const p of pts) {
                const dx = p[0] - cx, dy = p[1] - cy;
                xx += dx*dx; yy += dy*dy; xy += dx*dy;
            }
            // covariance matrix [[xx,xy],[xy,yy]], eigenvector for smallest eigenvalue = line normal
            const trace = xx + yy;
            const det = xx * yy - xy * xy;
            const disc = Math.max(0, trace*trace - 4*det);
            const l1 = (trace + Math.sqrt(disc)) / 2;  // largest eigenvalue? we want smallest => l2
            const l2 = (trace - Math.sqrt(disc)) / 2;  // smallest
            // eigenvector for l2:
            let nx, ny;
            if (Math.abs(xy) > 1e-9) {
                nx = l2 - yy;
                ny = xy;
            } else {
                nx = 1; ny = 0;
            }
            const nlen = Math.hypot(nx, ny) || 1;
            nx /= nlen; ny /= nlen;
            const c = -(nx * cx + ny * cy);
            return { a: nx, b: ny, c };
        },

        // Large box blur (separable) to estimate background illumination.
        brightnessMap(gray, w, h) {
            const radius = Math.max(4, Math.round(w / 12));
            const out = new Float32Array(gray.length);
            const tmp = new Float32Array(gray.length);
            const norm = 2 * radius + 1;
            // horizontal
            for (let y = 0; y < h; y++) {
                const row = y * w;
                let acc = 0;
                for (let x = -radius; x <= radius; x++) acc += gray[row + Math.min(w-1, Math.max(0, x))];
                tmp[row] = acc / norm;
                for (let x = 1; x < w; x++) {
                    const add = gray[row + Math.min(w-1, x + radius)];
                    const sub = gray[row + Math.max(0, x - radius - 1)];
                    acc += add - sub;
                    tmp[row + x] = acc / norm;
                }
            }
            // vertical
            for (let x = 0; x < w; x++) {
                let acc = 0;
                for (let y = -radius; y <= radius; y++) acc += tmp[Math.min(h-1, Math.max(0, y)) * w + x];
                out[x] = acc / norm;
                for (let y = 1; y < h; y++) {
                    const add = tmp[Math.min(h-1, y + radius) * w + x];
                    const sub = tmp[Math.max(0, y - radius - 1) * w + x];
                    acc += add - sub;
                    out[y * w + x] = acc / norm;
                }
            }
            return out;
        },

        flipCamera() {
            this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';
            this.startLiveCamera();
        },

        /* ── Capture ── */
        async capture() {
            if (this.scannerState === 'editNative') {
                this.applyPerspective();
                return;
            }
            this.scanning = true;
            const video = this.$refs.scanVideo;
            if (video && video.readyState >= 2) {
                const cc = this.$refs.captureCanvas;
                cc.width = video.videoWidth;
                cc.height = video.videoHeight;
                cc.getContext('2d').drawImage(video, 0, 0, cc.width, cc.height);
                // Use the fixed on-screen card guide as the crop region
                this.corners = this.guideCorners();
                this.applyPerspective();
            }
            this.scanning = false;
        },

        // Map the fixed card guide (normalized within preview box) to card corners.
        guideCorners() {
            const g = this.cardGuide;
            const l = g.cx - g.w / 2, r = g.cx + g.w / 2, t = g.cy - g.h / 2, b = g.cy + g.h / 2;
            return [ {x:l, y:t}, {x:r, y:t}, {x:r, y:b}, {x:l, y:b} ];
        },

        // map corners (normalized full frame) → pixel corners, warp + show preview
        applyPerspective() {
            const cc = this.$refs.captureCanvas;
            const cw = cc.width, ch = cc.height;
            if (!cw || !ch) return;
            const src = [
                { x: this.corners[0].x * cw, y: this.corners[0].y * ch },
                { x: this.corners[1].x * cw, y: this.corners[1].y * ch },
                { x: this.corners[2].x * cw, y: this.corners[2].y * ch },
                { x: this.corners[3].x * cw, y: this.corners[3].y * ch },
            ];

            // Force a clean horizontal rectangle crop (card geometry), no perspective skew.
            const xL = Math.max(0, src[0].x), xR = Math.min(cw, src[2].x);
            const yT = Math.max(0, src[0].y), yB = Math.min(ch, src[2].y);
            const rectW = xR - xL, rectH = yB - yT;
            if (rectW <= 0 || rectH <= 0) return;

            const outW = 1000;
            const outH = Math.round(outW * rectH / rectW);
            const srcRect = { x: xL, y: yT, w: rectW, h: rectH };

            const out = this.$refs.previewCanvas;
            out.width = outW; out.height = outH;
            const octx = out.getContext('2d');
            octx.fillStyle = '#ffffff';
            octx.fillRect(0, 0, outW, outH);
            this.copyRect(this.$refs.captureCanvas, srcRect, octx, outW, outH);

            this.enhance(out);
            this.scannerState = 'preview';
            this.resultDataUrl = out.toDataURL('image/jpeg', 0.92);
        },

        // Straight (non-perspective) scaled crop of a rect region.
        copyRect(srcCanvas, srcRect, ctx, outW, outH) {
            ctx.drawImage(
                srcCanvas,
                srcRect.x, srcRect.y, srcRect.w, srcRect.h,
                0, 0, outW, outH
            );
        },

        warpPerspective(srcCanvas, srcQuad, ctx, dstQuad) {
            const H = this.computeHomography(srcQuad, dstQuad);
            const inv = this.invertHomography(H);
            const W = Math.round(dstQuad[1].x), Hh = Math.round(dstQuad[2].y);
            const outImg = ctx.createImageData(W, Hh);
            const od = outImg.data;
            const srcW = srcCanvas.width, srcH = srcCanvas.height;
            const sctx = srcCanvas.getContext('2d');
            const srcImg = sctx.getImageData(0, 0, srcW, srcH).data;

            for (let y = 0; y < Hh; y++) {
                for (let x = 0; x < W; x++) {
                    const sw = inv[0]*x + inv[1]*y + inv[2];
                    const sh = inv[3]*x + inv[4]*y + inv[5];
                    const sz = inv[6]*x + inv[7]*y + inv[8] || 1;
                    const sx = sw / sz, sy = sh / sz;
                    const c = this.bilinear(srcImg, srcW, srcH, sx, sy);
                    const o = (y * W + x) * 4;
                    od[o] = c[0]; od[o+1] = c[1]; od[o+2] = c[2]; od[o+3] = 255;
                }
            }
            ctx.putImageData(outImg, 0, 0);
        },

        bilinear(srcData, w, h, sx, sy) {
            const x = Math.floor(sx), y = Math.floor(sy);
            const fx = sx - x, fy = sy - y;
            const x0 = Math.min(w-1, Math.max(0, x)), y0 = Math.min(h-1, Math.max(0, y));
            const x1 = Math.min(w-1, x0+1), y1 = Math.min(h-1, y0+1);
            const i00 = (y0 * w + x0) * 4, i10 = (y0 * w + x1) * 4;
            const i01 = (y1 * w + x0) * 4, i11 = (y1 * w + x1) * 4;
            const r = (srcData[i00]*(1-fx) + srcData[i10]*fx)*(1-fy) + (srcData[i01]*(1-fx) + srcData[i11]*fx)*fy;
            const g = (srcData[i00+1]*(1-fx) + srcData[i10+1]*fx)*(1-fy) + (srcData[i01+1]*(1-fx) + srcData[i11+1]*fx)*fy;
            const b = (srcData[i00+2]*(1-fx) + srcData[i10+2]*fx)*(1-fy) + (srcData[i01+2]*(1-fx) + srcData[i11+2]*fx)*fy;
            return [r, g, b];
        },

        computeHomography(src, dst) {
            // Solve for 3x3 homography using 4 point pairs. Build 8x8 system.
            const A = [], B = [];
            for (let i = 0; i < 4; i++) {
                const x = src[i].x, y = src[i].y, u = dst[i].x, v = dst[i].y;
                A.push([x, y, 1, 0, 0, 0, -u*x, -u*y]);
                A.push([0, 0, 0, x, y, 1, -v*x, -v*y]);
                B.push(u); B.push(v);
            }
            const X = this.solve8x8(A, B);  // 8 unknowns h0..h7, h8=1
            return [X[0], X[1], X[2], X[3], X[4], X[5], X[6], X[7], 1];
        },

        invertHomography(H) {
            // inverse of [[a,b,c],[d,e,f],[g,h,i]] scaled by determinant
            const a=H[0],b=H[1],c=H[2],d=H[3],e=H[4],f=H[5],g=H[6],h=H[7],i=H[8];
            const det = a*(e*i-f*h) - b*(d*i-f*g) + c*(d*h-e*g);
            if (Math.abs(det) < 1e-12) return [1,0,0,0,1,0,0,0,1];
            const A = [( e*i-f*h)/det, (c*h-b*i)/det, (b*f-c*e)/det,
                       (f*g-d*i)/det, (a*i-c*g)/det, (c*d-a*f)/det,
                       (d*h-e*g)/det, (b*g-a*h)/det, (a*e-b*d)/det];
            return A;
        },

        solve8x8(A, B) {
            // Gaussian elimination
            const n = 8;
            const M = [];
            for (let r = 0; r < n; r++) M.push([...A[r], B[r]]);
            for (let col = 0; col < n; col++) {
                let piv = col;
                for (let r = col+1; r < n; r++) if (Math.abs(M[r][col]) > Math.abs(M[piv][col])) piv = r;
                [M[col], M[piv]] = [M[piv], M[col]];
                const pv = M[col][col];
                if (Math.abs(pv) < 1e-12) continue;
                for (let c = col; c <= n; c++) M[col][c] /= pv;
                for (let r = 0; r < n; r++) {
                    if (r === col) continue;
                    const f = M[r][col];
                    if (Math.abs(f) < 1e-12) continue;
                    for (let c = col; c <= n; c++) M[r][c] -= f * M[col][c];
                }
            }
            return M.map(row => row[n]);
        },

        enhance(canvas) {
            const ctx = canvas.getContext('2d');
            const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const d = img.data;
            let min = 255, max = 0;
            for (let i = 0; i < d.length; i += 4) {
                const g = 0.299*d[i] + 0.587*d[i+1] + 0.114*d[i+2];
                if (g < min) min = g;
                if (g > max) max = g;
            }
            const range = (max - min) || 1;
            for (let i = 0; i < d.length; i += 4) {
                d[i]   = Math.round((d[i] - min) / range * 255);
                d[i+1] = Math.round((d[i+1] - min) / range * 255);
                d[i+2] = Math.round((d[i+2] - min) / range * 255);
                d[i+3] = 255;
            }
            ctx.putImageData(img, 0, 0);
        },

        /* ── process picked file into scanner ── */
        processFile(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const cc = this.$refs.captureCanvas;
                    cc.width = img.width; cc.height = img.height;
                    cc.getContext('2d').drawImage(img, 0, 0);

                    // run a one-shot detection on the image
                    const dc = this.$refs.detectCanvas;
                    dc.width = img.width; dc.height = img.height;
                    const dctx = dc.getContext('2d');
                    dctx.drawImage(img, 0, 0);
                    const det = this.findDocumentCorners(dctx, img.width, img.height);
                    if (det) this.corners = det;
                    else this.corners = this.guideCorners();

                    // show the image in the edit canvas
                    const ec = this.$refs.editCanvas;
                    ec.width = img.width; ec.height = img.height;
                    ec.getContext('2d').drawImage(img, 0, 0, ec.width, ec.height);
                    this.scannerState = 'editNative';
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        sendResult() {
            if (!this.resultDataUrl) return;
            fetch(this.resultDataUrl).then(r => r.blob()).then(blob => {
                const file = new File([blob], 'ktp-scan-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                this.injectFile(file);
                this.close();
            });
        },

        injectFile(file) {
            if (!file) return;
            let target = null;
            document.querySelectorAll('[wire\\\\:key]').forEach(el => {
                const key = el.getAttribute('wire\\\\:key') || '';
                if (key.includes('ktp_photo')) target = el;
            });
            if (target && window.FilePond) {
                const pondElement = target.querySelector('.filepond--root');
                if (pondElement) {
                    const inst = window.FilePond.find(pondElement);
                    if (inst) {
                        inst.removeFile();
                        inst.addFile(file);
                        return;
                    }
                }
            }
            if (target) {
                const fp = target.querySelector('input[type=file]');
                if (fp) {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    fp.files = dt.files;
                    fp.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        },

        /* ── corner drag (editing view) ── */
        startDrag(i, event) {
            if (this.scannerState !== 'editNative') return;
            this.dragCorner = i;
            this.onDragMove(event);
        },
        onDragMove(event) {
            if (this.dragCorner === null) return;
            const el = this.$refs.editWrap.getBoundingClientRect();
            const x = (event.clientX - el.left) / el.width;
            const y = (event.clientY - el.top) / el.height;
            this.corners[this.dragCorner] = { x: Math.min(1, Math.max(0, x)), y: Math.min(1, Math.max(0, y)) };
        },
        endDrag() { this.dragCorner = null; },
    }"
    x-on:open-document-scanner.window="open()"
    x-on:keydown.escape.window="if (isOpen) { if (scannerState === 'live' || scannerState === 'editNative') { stopCamera(); scannerState = 'pick'; } else { close(); } }"
    x-on:livewire:navigating.window="close()"
    class="contents"
>
    {{-- Hidden file inputs --}}
    <input type="file" accept="image/*" capture="environment" class="sr-only" id="docscan-native-input" x-on:change="onNativePicked($event)">
    <input type="file" accept="image/*" class="sr-only" id="docscan-gallery-input" x-on:change="onNativePicked($event)">

    <template x-teleport="body">
        <div
            x-show="isOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click.self="scannerState === 'preview' ? null : close()"
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-gray-950/80 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom,0px))] dark:bg-gray-950/90"
            role="dialog"
            aria-modal="true"
            aria-labelledby="docscan-title"
            style="display:none;"
        >
            <div
                x-show="isOpen"
                x-on:click.stop
                class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
            >
                {{-- Header --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/10">
                    <h3 id="docscan-title" class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                        <span x-show="scannerState === 'pick'">{{ __('Scan Kartu Identitas') }}</span>
                        <span x-show="scannerState === 'live'" style="display:none;">{{ __('Posisikan Kartu dalam Bingkai') }}</span>
                        <span x-show="scannerState === 'editNative'" style="display:none;">{{ __('Sesuaikan Posisi Kartu') }}</span>
                        <span x-show="scannerState === 'preview'" style="display:none;">{{ __('Hasil Scan') }}</span>
                    </h3>
                    <button
                        type="button"
                        x-on:click="close()"
                        class="relative -m-1.5 flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
                        aria-label="{{ __('Tutup') }}"
                    >
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                    </button>
                </div>

                {{-- Source picker --}}
                <div x-show="scannerState === 'pick'" class="space-y-1 px-4 py-3">
                    <p class="mb-2 px-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Posisikan kartu KTP di dalam bingkai hijau yang sudah menyesuaikan ukuran kartu, lalu ambil foto.') }}
                    </p>

                    <button
                        type="button"
                        x-on:click="startLiveCamera()"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 active:bg-gray-100 dark:hover:bg-white/5 dark:active:bg-white/10"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20">
                            <x-filament::icon icon="heroicon-o-camera" class="h-5 w-5" />
                        </span>
                        <span class="flex-1 text-left text-gray-700 dark:text-gray-200">{{ __('Pindai Kartu') }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300" />
                    </button>

                    <button
                        type="button"
                        x-on:click="useGallery()"
                        class="group flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 active:bg-gray-100 dark:hover:bg-white/5 dark:active:bg-white/10"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20">
                            <x-filament::icon icon="heroicon-o-photo" class="h-5 w-5" />
                        </span>
                        <span class="flex-1 text-left text-gray-700 dark:text-gray-200">{{ __('Pilih dari Galeri') }}</span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300" />
                    </button>

                    <div class="my-1 border-t border-gray-100 dark:border-white/10"></div>
                    <button
                        type="button"
                        x-on:click="close()"
                        class="w-full rounded-lg px-3 py-2.5 text-center text-sm font-semibold text-danger-600 outline-none transition duration-75 hover:bg-danger-50 active:bg-danger-100 dark:text-danger-400 dark:hover:bg-danger-400/10"
                    >
                        {{ __('Batal') }}
                    </button>
                </div>

                {{-- Live camera view --}}
                <div x-show="scannerState === 'live'" style="display:none;">
                    <div class="relative mx-4 mt-4 overflow-hidden rounded-lg bg-black" style="aspect-ratio:4/3;">
                        <video
                            x-ref="scanVideo"
                            autoplay
                            playsinline
                            muted
                            class="h-full w-full object-fill"
                        ></video>
                        {{-- Card guide overlay --}}
                        <div
                            class="pointer-events-none absolute inset-0"
                        >
                            <svg class="h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                                <rect
                                    :x="(cardGuide.cx - cardGuide.w/2)*100"
                                    :y="(cardGuide.cy - cardGuide.h/2)*100"
                                    :width="cardGuide.w*100"
                                    :height="cardGuide.h*100"
                                    fill="rgba(255,255,255,0.06)"
                                    stroke="#22c55e"
                                    stroke-width="0.5"
                                />
                            </svg>
                        </div>
                        {{-- Hint overlay --}}
                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                            <span class="rounded-full bg-gray-900/60 px-4 py-1.5 text-xs font-medium text-white">
                                {{ __('Posisikan kartu di dalam bingkai hijau') }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-center gap-4 px-4 py-4">
                        <button
                            type="button"
                            x-on:click="flipCamera()"
                            class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-700 transition hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200"
                            aria-label="{{ __('Balik Kamera') }}"
                        >
                            <x-filament::icon icon="heroicon-m-arrow-path" class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            x-on:click="capture()"
                            x-bind:disabled="!videoReady"
                            class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition hover:bg-primary-500 active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed"
                            aria-label="{{ __('Ambil Foto') }}"
                        >
                            <x-filament::icon icon="heroicon-m-camera" class="h-8 w-8" />
                        </button>
                        <button
                            type="button"
                            x-on:click="stopCamera(); scannerState = 'pick'"
                            class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-700 transition hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200"
                            aria-label="{{ __('Kembali') }}"
                        >
                            <x-filament::icon icon="heroicon-m-arrow-left" class="h-5 w-5" />
                        </button>
                    </div>
                </div>

                {{-- Manual edit view (from camera or gallery image) --}}
                <div x-show="scannerState === 'editNative'" style="display:none;">
                    <div
                        x-ref="editWrap"
                        class="relative mx-4 mt-4 overflow-hidden rounded-lg bg-black touch-none"
                    >
                        <canvas x-ref="editCanvas" class="block w-full h-auto"></canvas>
                        <svg class="absolute inset-0 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                            <polygon
                                :points="corners.map(c => (c.x*100)+','+(c.y*100)).join(' ')"
                                fill="rgba(255,255,255,0.10)"
                                stroke="#22c55e"
                                stroke-width="0.6"
                            />
                            <template x-for="(c,i) in corners" :key="i">
                                <circle
                                    :cx="c.x*100" :cy="c.y*100" r="3" fill="#22ff88"
                                    class="cursor-grab"
                                    x-on:mousedown.prevent="startDrag(i, $event)"
                                    x-on:touchstart.prevent="startDrag(i, $event)"
                                    x-on:mousemove="onDragMove($event)"
                                    x-on:touchmove="onDragMove($event)"
                                    x-on:mouseup="endDrag()"
                                    x-on:touchend="endDrag()"
                                />
                            </template>
                        </svg>
                    </div>

                    <div class="flex items-center justify-between gap-3 px-4 py-4">
                        <button
                            type="button"
                            x-on:click="stopCamera(); scannerState = 'pick'"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                        >
                            {{ __('Batal') }}
                        </button>
                        <button
                            type="button"
                            x-on:click="capture()"
                            class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 active:bg-primary-700"
                        >
                            {{ __('Crop & Deteksi ') }}
                        </button>
                    </div>
                </div>

                {{-- Preview / result --}}
                <div x-show="scannerState === 'preview'" style="display:none;">
                    <div class="mx-4 mt-4 overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                        <img :src="resultDataUrl" class="max-h-96 w-full object-contain bg-white" alt="{{ __('Hasil scan') }}" />
                    </div>
                    <div class="flex items-center justify-between gap-3 px-4 py-4">
                        <button
                            type="button"
                            x-on:click="scannerState = 'live'; startLiveCamera()"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                        >
                            {{ __('Scan Ulang') }}
                        </button>
                        <button
                            type="button"
                            x-on:click="sendResult()"
                            class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 active:bg-primary-700"
                        >
                            {{ __('Gunakan Foto') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- Hidden working canvases --}}
    <canvas x-ref="captureCanvas" class="hidden"></canvas>
    <canvas x-ref="previewCanvas" class="hidden"></canvas>
    <canvas x-ref="detectCanvas" class="hidden"></canvas>
</div>
