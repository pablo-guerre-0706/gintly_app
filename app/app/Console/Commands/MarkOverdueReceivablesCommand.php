<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\Receivable\ReceivableService;
use Illuminate\Console\Command;

final class MarkOverdueReceivablesCommand extends Command
{
    protected $signature = 'receivables:mark-overdue';

    protected $description = 'Marca como vencidas las CxC con plazo expirado y saldo vivo (RF-08-05).';

    public function handle(ReceivableService $receivables): int
    {
        $total = 0;

        Business::query()->each(function (Business $business) use ($receivables, &$total): void {
            $marked = $receivables->marcarVencidas($business->id);
            $total += $marked;

            if ($marked > 0) {
                $this->info("Negocio #{$business->id}: {$marked} cuenta(s) marcada(s) vencida(s).");
            }
        });

        $this->info("Total de cuentas marcadas vencidas: {$total}.");

        return self::SUCCESS;
    }
}
