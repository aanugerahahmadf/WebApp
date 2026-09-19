<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 38px 42px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 10.5px; line-height: 1.45; }
        .header { border-bottom: 3px solid #d4a017; padding-bottom: 14px; margin-bottom: 22px; }
        .brand { color: #a16207; font-size: 12px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        h1 { font-size: 22px; margin: 5px 0 3px; color: #111827; }
        .muted { color: #6b7280; }
        h2 { font-size: 12px; margin: 22px 0 8px; color: #92400e; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        td { border: 1px solid #e5e7eb; padding: 9px 10px; vertical-align: top; }
        td.label { width: 34%; background: #fffbeb; color: #78350f; font-weight: bold; }
        .notes { white-space: pre-line; min-height: 45px; }
        .footer { margin-top: 28px; border-top: 1px solid #e5e7eb; padding-top: 10px; color: #6b7280; font-size: 9px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">Wedding Organizer</div>
        <h1>Formulir Kebutuhan Acara</h1>
        <div class="muted">Ringkasan konsultasi untuk {{ $inbox->inbox_title }}</div>
    </div>
    <h2>Data Pemesan</h2>
    <table>
        <tr><td class="label">Nama Lengkap</td><td>{{ $form['customer_name'] ?? '-' }}</td></tr>
        <tr><td class="label">Nomor WhatsApp</td><td>{{ $form['phone'] ?? '-' }}</td></tr>
        <tr><td class="label">Email</td><td>{{ $form['email'] ?? '-' }}</td></tr>
    </table>
    <h2>Detail Acara</h2>
    <table>
        <tr><td class="label">Tanggal Acara</td><td>{{ !empty($form['event_date']) ? \Carbon\Carbon::parse($form['event_date'])->translatedFormat('d F Y') : '-' }}</td></tr>
        <tr><td class="label">Lokasi / Venue</td><td>{{ $form['venue'] ?? '-' }}</td></tr>
        <tr><td class="label">Perkiraan Tamu</td><td>{{ !empty($form['guest_count']) ? number_format((int) $form['guest_count'], 0, ',', '.') . ' orang' : '-' }}</td></tr>
        <tr><td class="label">Tema Pernikahan</td><td>{{ $form['theme'] ?? '-' }}</td></tr>
        <tr><td class="label">Warna Dominan</td><td>{{ $form['dominant_colors'] ?? '-' }}</td></tr>
        <tr><td class="label">Perkiraan Anggaran</td><td>{{ isset($form['budget']) && $form['budget'] !== null && $form['budget'] !== '' ? 'Rp '.number_format((float) $form['budget'], 0, ',', '.') : '-' }}</td></tr>
        <tr><td class="label">Catatan Khusus</td><td class="notes">{{ $form['notes'] ?? '-' }}</td></tr>
    </table>
    <div class="footer">Dibuat pada {{ !empty($form['submitted_at']) ? \Carbon\Carbon::parse($form['submitted_at'])->translatedFormat('d F Y, H:i') : now()->translatedFormat('d F Y, H:i') }}. Dokumen ini merupakan ringkasan kebutuhan awal dan dapat diperbarui melalui halaman Pesan.</div>
</body>
</html>
