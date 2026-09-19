<?php

namespace App\Traits\HasTranslations;

trait HasTranslations
{
    public function trans(string $field, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();

        $translationsField = $field . '_translations';

        $translations = $this->getRawOriginal($translationsField) ?? $this->$translationsField ?? null;

        if (is_string($translations)) {
            $translations = json_decode($translations, true) ?? [];
        }

        if (is_array($translations) && isset($translations[$locale])) {
            return $translations[$locale];
        }

        if (is_array($translations) && isset($translations['en'])) {
            return $translations['en'];
        }

        return $this->getRawOriginal($field) ?? $this->$field ?? null;
    }

    public function setTranslation(string $field, string $locale, mixed $value): static
    {
        $translationsField = $field . '_translations';
        $translations = $this->getRawOriginal($translationsField) ?? $this->$translationsField ?? [];

        if (is_string($translations)) {
            $translations = json_decode($translations, true) ?? [];
        }

        if (!is_array($translations)) {
            $translations = [];
        }

        $translations[$locale] = $value;
        $this->$translationsField = $translations;

        return $this;
    }

    public function toTranslatedArray(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $data = $this->toArray();
        $translatable = $this->translatable ?? [];

        foreach ($translatable as $field) {
            $data[$field] = $this->trans($field, $locale);
        }

        return $data;
    }
}
