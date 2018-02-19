<?php

namespace Tests;

use Illuminate\Database\Eloquent\Builder;
use LaraTest\Traits\AccessProtectedTraits;
use LaraTest\Traits\MockTraits;
use phpmock\MockBuilder;
use Tests\TestCase;

class ConterCacheTest extends TestCase
{
    use MockTraits, AccessProtectedTraits;
    /**
     * @var
     */
    protected $counterModel;

    /**
     *
     */
    public function setUp()
    {
        if (empty($this->counterModel)) {
            $this->counterModel = $this->newCounterModel();
        }
    }

    public function testBootCallParren()
    {

    }

    /**
     * @throws \ReflectionException
     */
    public function testRunCounter()
    {
        $counterModel = $this->newCounterModel(['addCounter', '_generateQueryCounter']);
        $this->methodWillReturnTrue('addCounter', $counterModel);
        $this->methodWillReturnTrue('_generateQueryCounter', $counterModel);
        $this->invokeMethod($counterModel, 'runCounter', [$counterModel]);
        $counterData = $this->getProtectedAttributeOf($counterModel, '_counterData');
        $this->assertTrue($counterData);
    }
    
    /**
     * @throws \ReflectionException
     */
    public function testGenerateQueryCounterWhenExistOnlyRelationName()
    {
        $data = ['order'];
        $counterModel = $this->newCounterModel(['_loadRelation', '_counterCaching']);
        $this->setProtectedAttributeOf($counterModel, '_counterData', $data);
        $this->methodWillReturnTrue('_counterCaching', $counterModel);
        $this->invokeMethod($counterModel, '_generateQueryCounter');
    }

    /**
     * @throws \ReflectionException
     */
    public function testGenerateQueryCounterWhenExistOnlyColumnForRelation()
    {
        $data = [
            'order' => [
                'order_item_count'
            ]
        ];
        $counterModel = $this->newCounterModel(['_loadRelation', '_counterCaching']);
        $this->setProtectedAttributeOf($counterModel, '_counterData', $data);
        $this->methodWillThrowExceptionWithArgument('_counterCaching', $counterModel);
        $this->expectExceptionMessage('method attribute is :["order_item_count",[]]');
        $this->invokeMethod($counterModel, '_generateQueryCounter');
    }

    /**
     * @throws \ReflectionException
     */
    public function testGenerateQueryCounterWhenExistOptionsForRelation()
    {
        $data = [
            'order' => [
                'order_item_count' => [
                    'methods' => [

                    ]
                ]
            ]
        ];
        $counterModel = $this->newCounterModel(['_loadRelation', '_counterCaching']);
        $this->setProtectedAttributeOf($counterModel, '_counterData', $data);
        $this->methodWillThrowExceptionWithArgument('_counterCaching', $counterModel);
        $this->expectExceptionMessage('method attribute is :["order_item_count",{"methods":[]}]');
        $this->invokeMethod($counterModel, '_generateQueryCounter');
    }

    /**
     * @throws \ReflectionException
     */
    public function testLoadRelation()
    {
        $std = new \stdClass();
        $std->table = 'table';
        $counterModel = $this->newCounterModel(['getKey', 'withTrashed', 'find', 'load']);
        $this->methodWillReturn($counterModel, 'withTrashed', $counterModel);
        $this->methodWillReturn($counterModel, 'find', $counterModel);
        $this->methodWillReturn($std, 'load', $counterModel);
        $this->invokeMethod($counterModel, '_loadRelation', ['table']);
        $returned = $this->getProtectedAttributeOf($counterModel, '_relationCounter');
        $this->assertEquals('table', $returned);
    }

    /**
     * @throws \ReflectionException
     */
    public function testSetQueryCounterWhenNotSoftDelete()
    {
        $counterModel = $this->newCounterModel(['newQueryWithoutScopes', 'where']);
        $str = $this->newInstance(\stdClass::class, [], ['getForeignKey', 'getKey']);
        $this->methodWillReturnTrue('getForeignKey', $str);
        $this->methodWillReturnTrue('getKey', $str);
        $this->setProtectedAttributeOf($counterModel, '_relationCounter', $str);
        $this->getProtectedAttributeOf($counterModel, '_queryCounter');
        $this->methodWillReturnTrue('where', $counterModel);
        $this->methodWillReturn($counterModel, 'newQueryWithoutScopes', $counterModel);
        $this->invokeMethod($counterModel, '_setQueryCounter');
    }

    /**
     * @throws \PHPUnit_Framework_Constraint
     * @throws \ReflectionException
     * @throws \phpmock\MockEnabledException
     */
    public function testSetQueryCounterWhenExistSoftDelete()
    {
        $builder = new MockBuilder();
        $builder->setNamespace("LaraCounterCache\Traits");
        $builder->setName('method_exists');
        $builder->setFunction(function () {
            return true;
        });
        $mock = $builder->build();
        $mock->enable();
        $counterModel = $this->newCounterModel(['newQueryWithoutScopes', 'where', 'trashed', 'getDeletedAtColumn']);
        $str = $this->newInstance(\stdClass::class, [], ['getForeignKey', 'getKey']);
        $this->methodWillReturnTrue('getForeignKey', $str);
        $this->methodWillReturnTrue('getKey', $str);
        $this->setProtectedAttributeOf($counterModel, '_relationCounter', $str);
        $this->getProtectedAttributeOf($counterModel, '_queryCounter');
        $counterModel->expects($this->any(2))->method('where')->willReturn(true);
        $this->methodWillReturnTrue('trashed', $counterModel);
        $this->methodWillReturn($counterModel, 'newQueryWithoutScopes', $counterModel);
        $this->invokeMethod($counterModel, '_setQueryCounter');
        $mock->disable();
    }

    /**
     * @throws \ReflectionException
     */
    public function testCounterCachingWhenEmptyRelation()
    {
        $returned = $this->invokeMethod($this->counterModel, '_counterCaching', ['name', ['attr']]);
        $this->assertFalse($returned);
    }

    /**
     * @throws \ReflectionException
     */
    public function testCounterCaching()
    {
        $std = $this->newInstance(\stdClass::class, [], ['update']);
        $this->methodWillReturnTrue('update', $std);
        $std->name = 5;
        $counterModel = $this->newCounterModel(['_setQueryCounter', '_counterLogic', '_getQueryCounter']);
        $this->methodWillReturn(4, '_getQueryCounter', $counterModel);
        $this->setProtectedAttributeOf($counterModel, '_relationCounter', $std);
        $this->invokeMethod($counterModel, '_counterCaching', ['name', ['attr']]);
        $counterSize = $this->getProtectedAttributeOf($counterModel, '_counterSize');
        $_queryCounter = $this->getProtectedAttributeOf($counterModel, '_queryCounter');
        $this->assertEquals(0, $counterSize);
        $this->assertNull($_queryCounter);
    }

    /**
     * @throws \ReflectionException
     */
    public function testGetQueryCounterByCustomSize()
    {
        $builder = $this->getMockBuilder(Builder::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->setMethods(['count'])
            ->getMock();

        $this->methodWillReturn(5, 'count', $builder);
        $this->setProtectedAttributeOf($this->counterModel, '_customResult', true);
        $this->setProtectedAttributeOf($this->counterModel, '_queryCounter', $builder);
        $returned = $this->invokeMethod($this->counterModel, '_getQueryCounter');
        $this->assertEquals(5, $returned);
    }

    /**
     * @throws \Exception
     * @expectedException Exception
     */
    public function testGetQueryCounterInvalidBuilderInstance()
    {
        $this->setProtectedAttributeOf($this->counterModel, '_customResult', true);
        $this->setProtectedAttributeOf($this->counterModel, '_queryCounter', new \stdClass());
        $this->invokeMethod($this->counterModel, '_getQueryCounter');

    }

    /**
     * @throws \ReflectionException
     */
    public function testCounterLogic()
    {
        $counterModel = $this->newCounterModel(['_counterWithClosure', '_counterWithMethods', '_counterWithConditions']);
        $this->methodWillReturnTrue('_counterWithClosure', $counterModel);
        $this->methodWillReturnTrue('_counterWithMethods', $counterModel);
        $this->methodWillReturnTrue('_counterWithConditions', $counterModel);
        $this->invokeMethod($counterModel, '_counterLogic', [['order']]);
    }

    /**
     * @throws \ReflectionException
     */
    public function testCounterWithConditions()
    {
        $data = [
            'conditions' => ['column' => 'value']
        ];
        $std = $this->newInstance(\stdClass::class, [], 'where');
        $this->methodWillThrowExceptionWithArgument('where', $std);
        $this->expectExceptionMessage('method attribute is :["column","value"]');
        $this->setProtectedAttributeOf($this->counterModel, '_queryCounter', $std);
        $this->invokeMethod($this->counterModel, '_counterWithConditions', [&$data]);
        $this->assertEquals([], $data);
    }

    /**
     * @throws \ReflectionException
     */
    public function testCounterWithConditionsByArray()
    {
        $data = [
            'conditions' => ['column' => ['value1', 'value2']]
        ];
        $std = $this->newInstance(\stdClass::class, [], 'whereIn');
        $this->methodWillThrowExceptionWithArgument('whereIn', $std);
        $this->expectExceptionMessage('method attribute is :["column",["value1","value2"]]');
        $this->setProtectedAttributeOf($this->counterModel, '_queryCounter', $std);
        $this->invokeMethod($this->counterModel, '_counterWithConditions', [&$data]);
        $this->assertEquals([], $data);
    }

    /**
     * @throws \ReflectionException
     * @throws \phpmock\MockEnabledException
     */
    public function testCounterWithMethods()
    {
        $data = [
            'methods' => [
                'where' => ['column', 'condition', 'value']
            ]
        ];
        $mock = $this->mockGlobalFunction("LaraCounterCache\Traits", 'method_exists');
        $std = $this->newInstance(\stdClass::class, [], 'where');
        $this->methodWillThrowExceptionWithArgument('where', $std);
        $this->expectExceptionMessage('method attribute is :["column","condition","value"]');
        $this->setProtectedAttributeOf($this->counterModel, '_queryCounter', $std);
        $this->invokeMethod($this->counterModel, '_counterWithMethods', [&$data]);
        $mock->disable();
    }

    /**
     * @throws \ReflectionException
     */
    public function testCounterWithClosureWhenReturnNum()
    {
        $data = function () {
            return 123;
        };

        $this->invokeMethod($this->counterModel, '_counterWithClosure', [&$data]);
        $counterSize = $this->getProtectedAttributeOf($this->counterModel, '_counterSize');
        $this->assertEquals(123, $counterSize);
        $this->assertEquals([], $data);
    }

    /**
     * @throws \ReflectionException
     */
    public function testCounterWithClosureWhenReturnCustomVal()
    {
        $data = function () {
            return true;
        };
        $this->invokeMethod($this->counterModel, '_counterWithClosure', [&$data]);
        $counterSize = $this->getProtectedAttributeOf($this->counterModel, '_customResult');
        $this->assertEquals(true, $counterSize);
        $this->assertEquals([], $data);
    }


    /**
     * @param null $methods
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function newCounterModel($methods = null)
    {
        return $this->newInstance(ModelExample::class, [], $methods);
    }

    /**
     * @param $className
     * @param array $args
     * @param null $methods
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function newInstance($className, $args = [], $methods = null)
    {
        if (!empty($methods) && !is_array($methods)) {
            $methods = [$methods];
        }
        $instance = $this->getMockBuilder($className)
            ->setConstructorArgs($args)
            ->setMethods($methods)
            ->getMock();
        return $instance;
    }

    /**
     * @param $namespace
     * @param $functionName
     * @param bool $value
     * @return \phpmock\Mock
     * @throws \phpmock\MockEnabledException
     */
    protected function mockGlobalFunction($namespace, $functionName, $value = true)
    {
        $builder = new MockBuilder();
        $builder->setNamespace($namespace);
        $builder->setName($functionName);
        $builder->setFunction(function () use ($value) {
            return $value;
        });
        $mock = $builder->build();
        $mock->enable();
        return $mock;
    }
}