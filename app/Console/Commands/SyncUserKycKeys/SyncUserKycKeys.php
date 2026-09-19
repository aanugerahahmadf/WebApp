<?php

namespace App\Console\Commands\SyncUserKycKeys;

use App\Models\ReferenceOption\ReferenceOption;
use App\Models\User\User;
use Illuminate\Console\Command;

class SyncUserKycKeys extends Command
{
    protected $signature = 'user:sync-kyc-keys
                            {--dry-run : Only show what would change, without updating}';

    protected $description = 'Migrate existing user KYC values from localized strings to keys';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fields = ['gender', 'religion', 'marital_status', 'occupation', 'income_range', 'source_of_funds'];

        // Build reverse lookup: label → key for all locales
        $reverseMap = [];
        ReferenceOption::active()->each(function (ReferenceOption $opt) use (&$reverseMap) {
            $labels = $opt->label;
            foreach ($labels as $locale => $label) {
                $normalized = trim(mb_strtolower($label));
                $reverseMap[$opt->type][$normalized] = $opt->key;
            }
        });

        $totalUpdated = 0;
        $totalSkipped = 0;

        User::chunk(100, function ($users) use ($fields, $reverseMap, $dryRun, &$totalUpdated, &$totalSkipped) {
            foreach ($users as $user) {
                $dirty = false;
                foreach ($fields as $field) {
                    $value = $user->$field;
                    if (empty($value)) continue;

                    $normalized = trim(mb_strtolower($value));

                    // Already a key? (alphanumeric + underscore only, no spaces)
                    if (preg_match('/^[a-z_]+$/', $normalized)) {
                        // Check if it's a valid key
                        if (isset($reverseMap[$field][$normalized])) {
                            continue; // Already correct key
                        }
                    }

                    // Try reverse lookup
                    if (isset($reverseMap[$field][$normalized])) {
                        $correctKey = $reverseMap[$field][$normalized];
                        if ($dryRun) {
                            $this->line("  [DRY-RUN] User #{$user->id}: {$field} '{$value}' → '{$correctKey}'");
                        }
                        $user->$field = $correctKey;
                        $dirty = true;
                    } else {
                        $this->warn("  [WARN] User #{$user->id}: no mapping for {$field}='{$value}'");
                    }
                }

                if ($dirty) {
                    if (!$dryRun) {
                        $user->save();
                    }
                    $totalUpdated++;
                } else {
                    $totalSkipped++;
                }
            }
        });

        $this->line('');
        if ($dryRun) {
            $this->info("Dry-run complete. {$totalUpdated} users would be updated, {$totalSkipped} already correct.");
        } else {
            $this->info("Sync complete. {$totalUpdated} users updated, {$totalSkipped} already correct.");
        }

        return Command::SUCCESS;
    }
}
