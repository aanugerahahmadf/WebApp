{{--
    Lonceng notifikasi database — SATU partial untuk ketiga panel.

    Permintaan (2026-10-26): notifikasi harus berada di SIDEBAR, di samping
    language switcher.

      - Panel Welcome/User (mobile responsive & aplikasi mobile): lonceng
        tinggal di baris `fi-sidebar-switchers` bersama tema + bahasa.
        Topbar hanya memegangnya di tablet/desktop (lihat override topbar
        per panel), memakai predikat AppPlatform yang sama dengan switcher
        supaya tidak pernah muncul dua kali.
      - Panel Admin: lonceng SELALU di sidebar, di samping language
        switcher; lonceng topbar dinonaktifkan.

    Kenapa memakai `filament()->auth()->user()` dan bukan `auth()->user()`:
    panel Admin memakai guard `admin`, bukan guard default `web`. Versi lama
    di topbar Admin membaca guard `web` yang selalu null di sana, jadi badge
    unread selalu 0.

    Tombolnya membuka modal DatabaseNotifications bawaan Filament (dirender
    di BODY_END oleh `->databaseNotifications()`), dengan event `open-modal`
    yang sama seperti tombol lonceng topbar.
--}}
@php
    $notificationUser = filament()->auth()->user();

    $unreadNotificationCount = 0;

    try {
        $unreadNotificationCount = $notificationUser?->unreadNotifications()->count() ?? 0;
    } catch (\Throwable) {
        // Tabel notifikasi belum dimigrasi / guard tidak tersedia: badge 0,
        // jangan sampai seluruh sidebar gagal dirender.
    }
@endphp

@if (filament()->hasDatabaseNotifications() && $notificationUser)
    <div
        class="fi-sidebar-notif-wrap relative inline-flex shrink-0"
        x-data="{
            notifOpen: false,
            unreadCount: {{ (int) $unreadNotificationCount }},
        }"
        x-on:open-modal.window="if ($event.detail.id === 'database-notifications') notifOpen = true"
        x-on:close-modal.window="if ($event.detail.id === 'database-notifications') notifOpen = false"
        x-on:filament-notifications-database-unread-count-updated.window="
            let prevCount = unreadCount;
            if (typeof $event.detail === 'number') unreadCount = $event.detail;
            else if ($event.detail?.count !== undefined) unreadCount = $event.detail.count;
            if (unreadCount > prevCount) {
                window.playNotificationSound?.();
            }
        "
    >
        <button
            type="button"
            x-bind:class="notifOpen
                ? 'fi-icon-btn fi-topbar-notif-active fi-topbar-database-notifications-btn relative flex items-center justify-center rounded-md outline-none transition duration-75 h-10 w-10 shrink-0 ring-1 ring-gray-950/10 dark:ring-white/20'
                : 'fi-icon-btn fi-topbar-database-notifications-btn relative flex items-center justify-center rounded-md outline-none transition duration-75 h-10 w-10 shrink-0 text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 ring-1 ring-gray-950/10 dark:ring-white/20'"
            x-on:click="window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'database-notifications' }, bubbles: true }))"
            aria-label="{{ __('filament-panels::layout.actions.open_database_notifications.label') }}"
        >
            <x-filament::icon icon="/images/icons/bell.svg" class="fi-icon-btn-icon h-5 w-5" />

            <span
                x-show="unreadCount > 0"
                x-text="unreadCount > 99 ? '99+' : unreadCount"
                x-cloak
                class="fi-topbar-notif-badge"
                aria-hidden="true"
            ></span>
        </button>
    </div>
@endif
