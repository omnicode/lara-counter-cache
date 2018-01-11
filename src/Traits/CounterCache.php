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
        $this->generateQueryCounter();
        if ($type === 'up') {
            $this->counterUp();
        }
        if ($type === 'down') {
            $this->counterDown();
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
            if (is_array($attr['conditions'])) {
                $this->addBaseCondition(...$attr['conditions']);
            }
            if ($attr['conditions'] instanceof \Closure) {
                $query = $this->addCustomCondition($attr['conditions']);
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

    private function checkQueryColumns($data, $event = '+', $i = 1)
    {
        $updateColumns = [];
        foreach ($data as $key => $item) {
            if (is_numeric($key)) {
                $updateColumns[$item] = DB::raw($item . ' ' . $event . ' ' . $i);
            } else {
                $updateColumns[$key] = DB::raw($key . ' ' . $event . ' ' . $item);
            }
        }
        return $updateColumns;
    }

    private function counterUp()
    {

    }

    private function counterDown()
    {

    }

}