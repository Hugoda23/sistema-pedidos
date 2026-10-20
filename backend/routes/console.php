<?php

use Illuminate\Support\Facades\Schedule;

// Cada mañana: ¿ya ingresó lo que estaba sin existencia? (hora de Guatemala)
Schedule::command('avisos:ingresos')->dailyAt('08:00')->withoutOverlapping();
