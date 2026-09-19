<?php

namespace App\Livewire\Traits\CanValidateFiles;

trait CanValidateFiles
{
    protected $validAudioExtensions = [
        'm4a', 'wav', 'mp3', 'ogg', 'aac', 'flac', 'midi',
    ];

    protected $validDocumentExtensions = [
        'pdf', 'doc', 'docx', 'csv', 'txt', 'xls', 'xlsx', 'ppt', 'pptx',
    ];

    protected $validImageExtensions = [
        'png', 'jpeg', 'jpg', 'gif',
    ];

    protected $validVideoExtensions = [
        'mp4', 'avi', 'mov', 'webm', 'mkv', 'flv', 'mpeg', 'mpg',
    ];

    public function validateAudio(string $imagePath): bool
    {
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        return in_array($extension, $this->validAudioExtensions);
    }

    public function validateDocument(string $documentPath): bool
    {
        $extension = strtolower(pathinfo($documentPath, PATHINFO_EXTENSION));

        return in_array($extension, $this->validDocumentExtensions);
    }

    public function validateImage(string $imagePath): bool
    {
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        return in_array($extension, $this->validImageExtensions);
    }

    public function validateVideo(string $imagePath): bool
    {
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));

        return in_array($extension, $this->validVideoExtensions);
    }
}
