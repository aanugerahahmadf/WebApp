<?php

namespace App\Models\DatabaseStudio;

use Illuminate\Database\Eloquent\Model;

class Migration extends Model
{
    protected $table = 'migrations';

    public $timestamps = false;

    protected $fillable = [
        'migration',
        'batch',
    ];

    protected $casts = [
        'batch' => 'integer',
    ];

    public function getFilePath(): ?string
    {
        $direct = database_path("migrations/{$this->migration}.php");
        if (file_exists($direct)) {
            return $direct;
        }

        $matches = glob(database_path("migrations/*{$this->migration}.php"));
        return $matches[0] ?? null;
    }

    public function fileExists(): bool
    {
        return $this->getFilePath() !== null;
    }

    public function getFileContent(): string
    {
        $path = $this->getFilePath();
        if ($path && file_exists($path)) {
            return file_get_contents($path);
        }

        return __('File fisik migrasi tidak ditemukan di server.');
    }

    public function getFileSize(): string
    {
        $path = $this->getFilePath();
        if (! $path || ! file_exists($path)) {
            return '-';
        }

        $bytes = filesize($path);
        return number_format($bytes / 1024, 2) . ' KB';
    }

    public function getFileModifiedAt(): string
    {
        $path = $this->getFilePath();
        if (! $path || ! file_exists($path)) {
            return '-';
        }

        return date('d/m/Y H:i:s', filemtime($path));
    }
}
