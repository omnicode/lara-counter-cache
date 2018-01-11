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
    public function runCounter($type)
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
            $this->relationCounter = $this->load([$table => function ($query) {
                $query->select('id');
            }])->$table;


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
        }
        if (!empty($attr['qty']) && is_numeric($attr['qty'])) {
            $count = $attr['qty'];
            unset($attr['qty']);
        }
        $this->counterWithConditions($name, $attr, $type);
        $keyName = $this->relationCounter->getKeyName();
        $query = $this->queryCounter->where($keyName, $this->relationCounter->$keyName);
        $query->{$type}($name, $count, $attr);
    }

    /**
     * @param $name
     * @param $attr
     * @param $type
     */
    private function counterWithConditions($name, &$attr, $type)
    {
        if (!empty($attr['conditions'])) {
            $conditions = $attr['conditions'];

            if (is_array($conditions)) {
                $this->addConditionByMethodName($conditions);
                $this->addBaseCondition(...$conditions);
            }

            if ($conditions instanceof \Closure) {
                $this->addCustomCondition($conditions);
            }
            unset($attr['conditions']);
        }
    }

    /**
     * @param $conditions
     */
    private function addConditionByMethodName($conditions)
    {
        foreach ($conditions as $key => $value) {
            if (is_string($key) && method_exists($this->queryCounter, $key)) {
                $this->queryCounter->{$key}(...$value);
                unset($conditions[$key]);
            }
        }
    }

    /**
     * @param $column
     * @param $value
     * @param string $cmp
     */
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
     */
    private function addCustomCondition(callable $func)
    {
        $func($this->queryCounter);
    }


}