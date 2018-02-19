<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use LaraCounterCache\Traits\CounterCache;

class ModelExample extends Model
{
    use CounterCache;
}