<?php

namespace LaraCounterCache\Traits;

use DB;

/**
 * Trait CounterCache
 * @package CounterCache\Traits
 */
trait CounterCache
{
    public $ignorCounterCache = false;

    private $counterData = [];

    private $queryCounter;

    private $tableCounter;

    /**
     *
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            if (method_exists($model, 'addCounter')) {
                $model->addCounter();
                $model->runCounter('up');
            }
        });

        static::deleted(function ($model) {
            if (method_exists($model, 'addCounter')) {
                $model->addCounter();
                $model->runCounter('down');
            }
        });

    }

    public function runCounter($type)
    {
        if ($type === 'up') {
            $this->generateQueryCounter('increment');
        }
        if ($type === 'down') {
            $this->generateQueryCounter('decrement');
        }
    }

    private function generateQueryCounter()
    {
        foreach ($this->counterData as $table => $datum) {
            $this->queryCounter = $this->load([$table => function ($query) {
                $query->select('id');
            }])->$table;

            if (!is_array($datum)) {
                $datum = [$datum];
            }

            foreach ($datum as $key => $item) {
                if (is_numeric($key)) {
                    $this->queryCounter->increment($item);
                } else {
                    $this->checkByParams($key, $item);
                }
            }

        }
    }

    private function checkByParams($name, $attr)
    {
        if (!empty($attr['conditions'])) {
            $conditions = $attr['conditions'];
            if (is_array($conditions)) {
                $this->addBaseCondition(...$conditions);
            }
            if ($conditions instanceof \Closure) {
                $query = $this->addCustomCondition($conditions);
            }

            unset($attr['conditions']);
            $query->where('id',$this->queryCounter->id)->increment($name);
        }
    }


    private function addBaseCondition($column, $value, $cmp = '=')
    {
        if (is_array($value)) {
            $this->queryCounter->whereIn($column, $value);
        } else {
            $this->queryCounter->where($column, $cmp, $value);
        }
    }

    /**
     * @param callable $func
     * @return mixed
     */
    private function addCustomCondition(callable $func)
    {
        $query = $this->queryCounter->newQueryWithoutScopes();
        $func($query);
        return $query->getQuery();
    }


}