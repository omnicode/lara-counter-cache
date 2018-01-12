<?php

namespace LaraCounterCache\Traits;

/**
 * Trait CounterCache
 * @package LaraCounterCache\Traits
 */
trait CounterCache
{
    /**
     * @var bool
     */
    public $ignorCounterCache = false;

    /**
     * @var array
     */
    private $counterData = [];

    /**
     * @var
     */
    private $queryCounter;

    /**
     * @var
     */
    private $relationCounter;

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

    /**
     * @param $type
     */
    private function runCounter($type)
    {
        if ($type === 'up') {
            $this->generateQueryCounter('increment');
        }
        if ($type === 'down') {
            $this->generateQueryCounter('decrement');
        }
    }

    /**
     * @param $type
     */
    private function generateQueryCounter($type)
    {
        foreach ($this->counterData as $table => $datum) {
            $this->loadRelation($table);

            if (!is_array($datum)) {
                $datum = [$datum];
            }

            foreach ($datum as $key => $item) {
                $this->setQueryCounter();
                if (is_numeric($key)) {
                    $this->counterCaching($item, [], $type);
                } else {
                    $this->counterCaching($key, $item, $type);
                }
            }
        }
    }

    /**
     * @param $table
     */
    private function loadRelation($table)
    {
        $this->relationCounter = $this->load([$table => function ($query) {
            $query->select('id');
        }])->$table;
    }

    /**
     *
     */
    private function setQueryCounter()
    {
        $this->queryCounter = $this->relationCounter->newQueryWithoutScopes();
    }

    /**
     * @param $name
     * @param $attr
     * @param $type
     */
    private function counterCaching($name, $attr, $type)
    {
        $count = 1;
        if (is_numeric($attr)) {
            $count = $attr;
            $attr = [];
        } else {
            if (!empty($attr['qty']) && is_numeric($attr['qty'])) {
                $count = $attr['qty'];
                unset($attr['qty']);
            }

            $this->counterWithConditions($attr);

            $this->counterWithMethods($attr);
            $this->counterWithClosure($attr);
        }
        $keyName = $this->relationCounter->getKeyName();
        $query = $this->queryCounter->where($keyName, $this->relationCounter->$keyName);
       return $query->{$type}($name, $count, $attr);
    }

    /**
     * @param $name
     * @param $attr
     * @param $type
     */
    private function counterWithConditions(&$attr)
    {
        if (!empty($attr['conditions'])) {
            $this->addCondition(...$attr['conditions']);
            unset($attr['conditions']);
        }
    }

    /**
     * @param $attr
     */
    private function counterWithMethods(&$attr)
    {
        if (!empty($attr['methods']) && is_array($attr['methods'])) {
            foreach ($attr['methods'] as $method => $args) {
                if (is_string($method) && method_exists($this->queryCounter, $method)) {
                    $this->queryCounter->{$method}(...$args);
                }
            }
            unset($attr['methods']);
        }
    }

    /**
     * @param $attr
     */
    private function counterWithClosure(&$attr)
    {
        if (!empty($attr['closure']) && $attr['closure'] instanceof \Closure) {
            $func = $attr['closure'];
            $func($this->queryCounter);
            unset($attr['closure']);
        }
    }

    /**
     * @param $column
     * @param $value
     * @param string $cmp
     */
    private function addCondition($column, $value, $cmp = '=')
    {
        if (is_array($value)) {
            $this->queryCounter->whereIn($column, $value);
        } else {
            $this->queryCounter->where($column, $cmp, $value);
        }
    }

}