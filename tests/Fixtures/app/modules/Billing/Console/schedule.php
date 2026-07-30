<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('billing:ping')->daily();
