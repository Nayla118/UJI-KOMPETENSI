<?php

namespace App\Console\Commands;

use App\Services\VisitorIdentityService;
use Illuminate\Console\Command;

class PurgeIdentityDocuments extends Command
{
    protected $signature = 'travelo:purge-identity-documents';

    protected $description = 'Delete booking visitor identity documents after the configured retention period';

    public function handle(VisitorIdentityService $documents): int
    {
        $days = (int) config('travelo.identity.retention_days', 90);
        $purged = $documents->purgeExpired();

        // Counts only: never print visitor names, numbers or file paths.
        $this->info("Purged {$purged} booking identity document(s) for visits older than {$days} day(s). Account-level saved copies are kept until the traveller removes them.");

        return self::SUCCESS;
    }
}
