<?php

namespace App\Traits\InteractsWithLanguages;

use Illuminate\Database\Eloquent\Relations\MorphOne;

trait InteractsWithLanguages
{
    public function lang(): MorphOne
    {
        return $this->morphOne('App\Models\UserLanguage\UserLanguage', 'model');
    }

    public function getLangAttribute()
    {
        return $this->lang()->first(['*'])?->lang ?? 'en';
    }
}
