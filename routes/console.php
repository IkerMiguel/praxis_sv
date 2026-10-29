<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('praxis:vencer-aclaraciones')->dailyAt('06:00')->timezone(config('praxis.zona_horaria'))->withoutOverlapping();
