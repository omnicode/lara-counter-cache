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
     * @var queryCounter
     */
    private $queryCounter;

    /**
     * @var relationCounter
     */
    private $relationCounter;

    /**
     * @var int
     */
    private $counterSize = 1;

    /**
     * @var
     */
    private $counterType;

    /**
     *
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            if (method_exists($model, 'addCounter')) {
                $model->addCounter();
                $model->counterType = 'increment';
                $model->generateQueryCounter();
            }
        });

        static::deleted(function ($model) {
            if (method_exists($model, 'addCounter')) {
                $model->addCounter();
                $model->counterType = 'decrement';
                $model->generateQueryCounter();
            }
        });

    }

    /**
     * @param $type
     */
    private function generateQueryCounter()
    {
        foreach ($this->counterData as $table => $datum) {
            $this->loadRelation($table);

            if (!is_array($datum)) {
                $datum = [$datum];
            }

            foreach ($datum as $key => $item) {
                $this->setQueryCounter();
                if (is_numeric($key)) {
                    $this->counterCaching($item, []);
                } else {
                    $this->counterCaching($key, $item);
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
     * @return mixed
     */
    private function counterCaching($name, $attr)
    {
        if (is_numeric($attr)) {
            $this->counterSize = $attr;
            $attr = [];
        } elseif (is_array($attr)) {
            $this->runCounterLogic($attr);
            if (isset($attr['saved'])) {
                $this->runCounterLogic($attr['saved']);
                unset($attr['saved']);
            }
            if (isset($attr['deleted'])) {
                $this->runCounterLogic($attr['deleted']);
                unset($attr['deleted']);
            }
        }
      
        $keyName = $this->relationCounter->getKeyName();
        $query = $this->queryCounter->where($keyName, $this->relationCounter->$keyName);
        return $query->{$this->counterType}($name, $this->counterSize, $attr);
    }

    /**
     * @param $attr
     */
    private function runCounterLogic(&$attr)
    {
        $this->counterWithConditions($attr);
        $this->counterWithMethods($attr);
        $this->counterWithClosure($attr);
        $this->setCounterSize($attr);
        $this->setCounterType($attr);
    }

    /**
     * @param $attr
     */
    private function setCounterSize(&$attr)
    {
        if (!empty($attr['size']) && is_numeric($attr['size'])) {
            $this->counterSize = $attr['size'];
            unset($attr['size']);
        }
    }

    /**
     * @param $attr
     */
    private function setCounterType(&$attr)
    {
        if (!empty($attr['type'])) {
            $types = [
                'up' => 'increment',
                'down' => 'decrement',
                '+' => 'increment',
                '-' => 'decrement'
            ];
            if (isset($types[$attr['type']])) {
                $this->counterType = $types[$attr['type']];
            } else {
                $this->counterType = $attr['type'];
            }
            unset($attr['type']);
        }
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
            $func($this->queryCounter, $this->counterType, $this->counterSize);
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