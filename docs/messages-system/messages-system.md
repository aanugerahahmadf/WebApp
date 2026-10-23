# Messages System — Technical Reference

> Berlaku untuk panel welcome (storefront/akun) dan admin panel

---

## Overview

The messages system is a real-time inbox built on top of Livewire polling.
It supports **authenticated users**, **guest users** (via GuestIdentity), and
**admin customer-service agents**.

---

## Database Schema

### `fm_inboxes`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_ids` | JSON | Array of user IDs in this conversation |
| `title` | string nullable | Group chat title |
| `meta` | JSON nullable | `cs_category`, `cs_rating`, `consultation_forms`, etc. |
| `updated_at` | timestamp | Bumped on every new message |

### `fm_messages`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `inbox_id` | FK → fm_inboxes | |
| `user_id` | FK → users | Sender |
| `message` | text nullable | Plain text body |
| `read_by` | JSON | Array of user IDs who have read |
| `read_at` | JSON | Array of timestamps |
| `notified` | JSON | Array of user IDs who were push-notified |
| `meta` | JSON nullable | See below |

### `meta` field keys

| Key | Type | Description |
|---|---|---|
| `type` | string | `'product'` or `'package'` — catalog context card. **Hanya untuk kartu katalog.** |
| `id` | int | Catalog item ID |
| `name` | string | Item name snapshot |
| `price` | int | Item price snapshot |
| `image` | string | Item image URL snapshot |
| `url` | string | Deep link to item detail page |
| `poll` | object | `{question, options: [{id, text, votes}]}` — polling |
| `sticker` | string | Emoji stiker |
| `is_order` | bool | Order context card |
| `is_cancellation` | bool | Cancellation notification |
| `is_payment_update` | bool | Payment status change notification |
| `is_refunded` | bool | Refund notification |
| `is_report` | bool | User report card |
| `is_bot` | bool | Bot reply card |
| `reply_to` | object | `{id, message, sender_name, sender_id}` |
| `reactions` | array | `[{user_id, emoji}, ...]` |
| `starred_by` | array | User ID strings |
| `deleted_by` | array | User ID ints (soft delete for specific users) |
| `forwarded_from` | object | `{message_id, sender_id, inbox_id}` |
| `consultation_form` | bool | Bot prompt flag |

---

## Livewire Components

### Inbox (`Welcome\Messages\Inbox\Inbox`)

Shows the list of conversations the current user is part of.

- Queries `Inbox::whereJsonContains('user_ids', $userId)`
- Renders unread badge: messages in the inbox where `read_by` does not contain `$userId`
- Dispatches `refresh-inbox` event to re-render after sends

### Messages (`Welcome\Messages\Messages\Messages`)

Shows the conversation thread for the selected inbox.

**Key properties:**

| Property | Type | Description |
|---|---|---|
| `$selectedConversation` | `?Inbox` | Currently open inbox |
| `$conversationMessages` | `?Collection` | Loaded messages (newest first) |
| `$currentPage` | int | Pagination cursor for load-more |
| `$replyTo` | `?array` | Reply threading payload |
| `$searchOpen` | bool | Search bar visibility |
| `$searchQuery` | string | Live search filter |
| `$otherUserIsTyping` | bool | Typing indicator |
| `$typingUserName` | string | Name of typing participant |

**Polling:** `wire:poll.visible.{interval}="pollMessages()"` — interval is
configurable via `config('messages.poll_interval', '5s')`.

**Computed property `visibleMessages`:**
Filters `$conversationMessages` by `$searchQuery` when the search bar is open.

---

## Unread Badge

`MessagesPage::getNavigationBadge()` counts inboxes where the current user has
unread messages, cached for 30 seconds:

```php
Cache::remember("user_{$userId}_unread_messages_count", now()->addSeconds(30), fn () =>
    Inbox::whereJsonContains('user_ids', $userId)
        ->whereHas('messages', fn ($q) => $q
            ->whereRaw('JSON_SEARCH(read_by, "one", ?) IS NULL', [$userId])
            ->where('user_id', '!=', $userId)
        )->count()
);
```

SQLite fallback uses `LIKE` instead of `JSON_SEARCH`.

---

## Lampiran: kamera, audio, kontak (alur yang sudah terverifikasi)

Field Filament `attachments` **tidak dipakai** untuk modality ini. Field itu
bergantung pada FilePond, dan di halaman Messages komponen FilePond-nya tidak
pernah selesai diinisialisasi (`file-upload.js` dimuat lewat `x-load-src` yang
menggantung), jadi input file pun tidak pernah punya `wire:model`. Akibatnya
lampiran tidak pernah sampai ke server: tombol "Kirim Audio" tampak berhasil,
tapi gelembung pesannya tidak pernah muncul.

Jalur yang dipakai sekarang:

```
browser (kamera / MediaRecorder / vCard)
  -> File
  -> $wire.upload('pendingAttachment', file)     (di bridge dalam komponen)
  -> sendPendingAttachment($tmpFilename)
  -> TemporaryUploadedFile::createFromLivewire($tmpFilename)
  -> sendMessage()
  -> $pending->store('messages-attachments', 'public')
  -> addMedia() ke koleksi FILAMENT_MESSAGES
  -> gelembung pesan merender <audio>/<video> inline
```

- Bridge-nya ada di `resources/views/*/livewire/messages/messages/messages.blade.php`
  (event window `messages-attach-file`), karena modal berada di luar akar Livewire.
- Modal (kamera / audio / kontak / polling / stiker) dirender di level HALAMAN,
  bukan di dalam komponen percakapan: komponennya polling tiap 2-3 detik, dan
  setiap render ulang menduplikasi isi `<template x-teleport>` sehingga dialog
  menumpuk di `<body>`.
- Polling disimpan di `meta['poll']`, stiker di `meta['sticker']`. **JANGAN**
  `meta['type']` untuk salah satu dari keduanya -- key itu berarti katalog/paket,
  dan memakainya untuk stiker memicu 500 `Undefined array key "id"`.
- Test pengunci: `tests/Feature/Shared/MessagesAttachmentPipelineTest/`
  (audio/foto/kontak x 3 panel).

---

## Known Bugs Fixed

| Date | Bug | Fix |
|---|---|---|
| 2026-10-01 | `hasRole() on null` — 500 on `/messages/{id}` for guests | Changed `chatUser()->hasRole()` → `chatUser()?->hasRole()` in Welcome blade |
| 2026-10-01 | Pola null-safe juga harus dipakai pada blade messages yang sudah dilebur ke panel welcome | Changed `auth()->user()->hasRole()` → `auth()->user()?->hasRole()` |
| 2026-10-01 | `chat_admin` 500 — `return redirect()` ignored by Filament | Changed to `$livewire->redirect(url, navigate: true)` |
| 2026-10-01 | `wishlist_detail` 419 — hidden form forced CSRF modal POST | Removed `->form([Hidden::make('confirm')])` from both resources |
| 2026-10-21 | Item Poll & New sticker di menu "+" tidak menutup menunya | `open = false` → `attachOpen = false`. `open` milik komponen global `messageBubble`, scope-nya lebih dekat, jadi yang ketutup gelembung pesan -- bukan menu lampiran |
| 2026-10-21 | Tombol "Kirim Audio" tampak berhasil tapi tidak ada baris media | Jalur lampiran diganti dari field FilePond ke `$wire.upload('pendingAttachment')` + `sendPendingAttachment()` |
