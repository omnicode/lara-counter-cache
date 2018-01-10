<?php

namespace CounterCache\Traits;

/**
 * Trait CounterCache
 * @package CounterCache\Traits
 */
trait CounterCache
{
    /**
     *
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {

        });

        static::saved(function ($model) {

        });

        static::deleted(function ($model) {

        });

        static::updated(function ($model){

        });
    }

}