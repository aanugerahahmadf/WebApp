@php
    $notifications = $this->getNotifications();
    $unreadNotificationsCount = $this->getUnreadNotificationsCount();
@endphp

<div
    @if ($pollingInterval = $this->getPollingInterval())
        wire:poll.{{ $pollingInterval }}
    @endif
    x-data="{
        currentUnread: {{ (int) $unreadNotificationsCount }},
        init() {
            $watch('currentUnread', (newVal, oldVal) => {
                if (oldVal !== undefined && newVal > oldVal) {
                    window.playNotificationSound?.();
                }
            });
        }
    }"
    x-effect="
        const count = {{ (int) $unreadNotificationsCount }};
        if (currentUnread !== undefined && count > currentUnread) {
            window.playNotificationSound?.();
        }
        currentUnread = count;
    "
    class="flex"
>
    @if ($trigger = $this->getTrigger())
        <x-filament-notifications::database.trigger>
            {{ $trigger->with(['unreadNotificationsCount' => $unreadNotificationsCount]) }}
        </x-filament-notifications::database.trigger>
    @endif

    <x-filament-notifications::database.modal
        :notifications="$notifications"
        :unread-notifications-count="$unreadNotificationsCount"
    />

    @if ($broadcastChannel = $this->getBroadcastChannel())
        <x-filament-notifications::database.echo
            :channel="$broadcastChannel"
        />
    @endif
</div>
