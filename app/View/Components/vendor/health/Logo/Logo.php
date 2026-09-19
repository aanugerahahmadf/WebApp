<?php

namespace App\View\Components\vendor\health\Logo;

use Illuminate\View\Component;
use Illuminate\View\View;

class Logo extends Component
{
    public function render(): View
    {
        return view('health::logo');
    }
}
