<?php

namespace TCG\Voyager\Tests\Unit\Actions;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use TCG\Voyager\Actions\AbstractAction;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Models\User;
use TCG\Voyager\Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AbstractActionTest extends TestCase
{
    /**
     * The users DataType instance.
     *
     * @var \TCG\Voyager\Models\DataType
     */
    protected $userDataType;

    /**
     * A dummy user instance.
     *
     * @var \TCG\Voyager\Models\User
     */
    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->userDataType = Voyager::model('DataType')->where('name', 'users')->first();
        $this->user = \TCG\Voyager\Models\User::factory()->create();
    }

    /**
     * This test checks that `getRoute` method calls the `getDefaultRoute`
     * method if the given key is empty.
     */
    public function testGetRouteWithEmptyKey()
    {
        $stub = $this->getMockBuilder(AbstractAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDefaultRoute', 'getTitle', 'getIcon'])
            ->getMock();

        // The `getDefaultRoute` method is called as default inside the
        // `getRoute` method to retrieve the route.
        $stub->method('getDefaultRoute')
             ->willReturn(true);

        $this->assertTrue($stub->getRoute($this->userDataType->name));
    }

    /**
     * This test checks that `getRoute` method calls the expected method when a
     * key is given.
     */
    public function testGetRouteWithCustomKey()
    {
        // The key passed to `getRoute` is capitalized and placed between 'get'
        // and 'Route', so `getRoute('custom')` calls `getCustomRoute()` when the
        // action defines it. PHPUnit 13 dropped addMethods(), so declare it for real.
        $stub = new class($this->userDataType, $this->user) extends AbstractAction
        {
            public function getTitle()
            {
                return 'custom';
            }

            public function getIcon()
            {
                return 'voyager-plus';
            }

            public function getDefaultRoute()
            {
                return false;
            }

            public function getCustomRoute()
            {
                return true;
            }
        };

        $this->assertTrue($stub->getRoute('custom'));
    }

    /**
     * This test checks that `getAttributes` method will give us the expected
     * output.
     */
    public function testConvertAttributesToHtml()
    {
        $stub = $this->getMockBuilder(AbstractAction::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttributes', 'getTitle', 'getIcon', 'getDefaultRoute'])
            ->getMock();

        $stub->method('getAttributes')
             ->willReturn([
                 'class'   => 'class1 class2',
                 'data-id' => 5,
                 'id'      => 'delete-5',
             ]);

        $this->assertEquals('class="class1 class2" data-id="5" id="delete-5"', $stub->convertAttributesToHtml());
    }

    /**
     * This test checks that `shouldActionDisplayOnDataType` method returns true
     * if the action should be displayed for every data type.
     */
    public function testShouldActionDisplayOnDataTypeWithDefaultDataType()
    {
        $stub = $this->getMockBuilder(AbstractAction::class)
            ->setConstructorArgs([$this->userDataType, $this->user])
            ->onlyMethods(['getTitle', 'getIcon', 'getDefaultRoute'])
            ->getMock();

        $this->assertTrue($stub->shouldActionDisplayOnDataType());
    }

    /**
     * This test checks that `shouldActionDisplayOnDataType` method returns true
     * if the action should only be displayed for a specific data type.
     */
    public function testTrueIsReturnedIfDataTypeMatchesTheOneWhereTheActionWasCreatedFor()
    {
        $stub = $this->getMockBuilder(AbstractAction::class)
            ->setConstructorArgs([$this->userDataType, $this->user])
            ->onlyMethods(['getDataType', 'getTitle', 'getIcon', 'getDefaultRoute'])
            ->getMock();

        $stub->method('getDataType')
             ->willReturn($this->userDataType->name);

        $this->assertTrue($stub->shouldActionDisplayOnDataType());
    }

    /**
     * This test checks that `shouldActionDisplayOnDataType` method returns false
     * if the action should only be displayed for a specific data type.
     */
    public function testFalseIsReturnedIfDataTypeDoesNotMatchesTheOneWhereTheActionWasCreatedFor()
    {
        $stub = $this->getMockBuilder(AbstractAction::class)
            ->setConstructorArgs([$this->userDataType, $this->user])
            ->onlyMethods(['getDataType', 'getTitle', 'getIcon', 'getDefaultRoute'])
            ->getMock();

        $stub->method('getDataType')
             ->willReturn('not users'); // different data type

        $this->assertFalse($stub->shouldActionDisplayOnDataType());
    }
}
