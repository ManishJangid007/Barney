<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('barney:check-alerts')->hourly();
