<?php

namespace App\Models\Traits\LegacyMorphClass;

trait LegacyMorphClass
{
    /**
     * Return the legacy flat fully-qualified class name for polymorphic
     * storage.
     *
     * Models live in the App\Models\X\X structure, but every existing database
     * row in polymorphic columns (model_has_roles, model_has_permissions,
     * media.model_type, etc.) was written with the original App\Models\X name.
     * Keeping getMorphClass() stable preserves those lookups and keeps new
     * writes consistent without requiring a data migration.
     */
    public function getMorphClass(): string
    {
        return 'App\Models\\'.class_basename(static::class);
    }
}
