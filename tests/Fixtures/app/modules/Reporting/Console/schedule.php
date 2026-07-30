<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reporting:ping')->daily();
