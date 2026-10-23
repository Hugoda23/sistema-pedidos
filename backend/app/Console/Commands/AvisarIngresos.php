<?php

namespace App\Console\Commands;

use App\Services\Avisos;
use Illuminate\Console\Command;

class AvisarIngresos extends Command
{
    protected $signature = 'avisos:ingresos';

    protected $description = 'Avisa de los repuestos cuya fecha aproximada de ingreso ya llegó';

    public function handle(Avisos $avisos): int
    {
        $n = $avisos->ingresosVencidos();
        $this->info("Repuestos avisados: {$n}");

        return self::SUCCESS;
    }
}
