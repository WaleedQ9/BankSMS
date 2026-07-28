<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Archive cycle automatically on the 27th (new cycle starts, old one should be archived)
Schedule::command('cycle:archive')->monthlyOn(27, '01:00');
