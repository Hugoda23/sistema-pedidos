<?php

namespace App\Console\Commands;

use App\Services\Avisos;
use Illuminate\Console\Command;

class AvisarIngresos extends Command
{
    protected $signature = 'avisos:ingresos';

    protected $description = 'Avisa de las líneas cuya fecha aproximada de ingreso ya llegó';

    public function handle(Avisos $avisos): int
    {
        $n = $avisos->ingresosVencidos();
        $this->info("Líneas avisadas: {$n}");

        return self::SUCCESS;
    }
}
