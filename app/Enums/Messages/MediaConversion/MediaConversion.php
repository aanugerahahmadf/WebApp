<?php

namespace App\Enums\Messages\MediaConversion;

enum MediaConversion: string
{
    case ORIGINAL = 'original';
    case SM = 'small';
    case MD = 'medium';
    case LG = 'large';
}
