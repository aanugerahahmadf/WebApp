<?php

namespace App\Forms\Components\ReviewPhotoUpload;

use Filament\Forms\Components\Field;

class ReviewPhotoUpload extends Field
{
    protected string $view = 'Welcome.components.review-photo-upload.review-photo-upload';

    protected string $disk = 'public';
    protected ?string $directory = null;
    protected string $visibility = 'public';

    public function disk(string $disk): static
    {
        $this->disk = $disk;
        return $this;
    }

    public function directory(?string $directory): static
    {
        $this->directory = $directory;
        return $this;
    }

    public function visibility(string $visibility): static
    {
        $this->visibility = $visibility;
        return $this;
    }
}
