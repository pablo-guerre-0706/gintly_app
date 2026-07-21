<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Receivable\ReceivableService;
use Illuminate\Console\Command;

class MarkOverdueReceivables extends Command
{
    protected $signature = 'receivables:mark-overdue';
    protected $description = 'Marca CxC vencidas y dispara alertas (RF-08-05).';

    public function handle(ReceivableService $service): int
    {
        Business::query()->chunkById(100, function ($businesses) use ($service) {
            foreach ($businesses as $business) {
                $count = $service->marcarVencidas($business->id);
                if ($count > 0) $this->info("Negocio {$business->id}: {$count} CxC marcadas vencidas.");
            }
        });
        return self::SUCCESS;
    }
}
