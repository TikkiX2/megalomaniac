<?php

namespace App\Exceptions;

use App\Models\Workout;
use Exception;

class WorkoutAlreadyActiveException extends Exception
{
    public function __construct(public Workout $workout)
    {
        parent::__construct('There is already an active workout.');
    }
}
