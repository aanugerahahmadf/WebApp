<?php

namespace App\Models\Home;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HeroBanner extends Model
{
    use HasFactory;

    protected $table = 'hero_banners';

    protected $fillable = [
        'title',
        'subtitle',
        'image_url',
        'link_url',
        'link_type',
        'link_id',
        'position',
        'is_active',
    ];

    protected $casts = [
        'link_id' => 'integer',
        'position' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Return an URL that can be rendered by the storefront.
     *
     * Existing seed data may contain an absolute URL while the admin upload
     * stores a relative path on the public disk. Supporting both keeps old
     * banners visible after introducing the upload field.
     */
    public function publicImageUrl(): string
    {
        $value = trim((string) $this->image_url);

        if ($value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_URL) || str_starts_with($value, '/')) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }

    /**
     * Scope banners that are allowed to be shown to customers.
     */
    public function scopeVisible($query)
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->orderBy('position')
            ->orderBy('id');
    }

}
