<?php

namespace App\Livewire\Traits\HasPollInterval;

trait HasPollInterval
{
    public $pollInterval = '5s';

    public function setPollInterval(): void
    {
        $this->pollInterval = config('messages.poll_interval', '5s');
    }
}
