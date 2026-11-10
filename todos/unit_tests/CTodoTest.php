<?php
/**
 * Tests for the Todos module's CTodo class.
 *
 * Runs with web2project's own test setup. From web2project's unit_tests
 *   directory, with the module installed in modules/todos:
 *
 *     ../vendor/bin/phpunit --bootstrap ./CommonSetup.php ../modules/todos/unit_tests
 *
 * The tests don't write to the database.
 *
 * LICENSE: This source file is subject to Clear BSD License. Please see the
 *   LICENSE file in root of site for further details
 *
 * @package    web2project
 * @subpackage modules\todos
 * @license    Clear BSD
 */

class CTodoTest extends CommonSetup
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->obj = new CTodo();
        $this->obj->overrideDatabase($this->mockDB);
        $_POST = array();
    }

    protected function tearDown(): void
    {
        $_POST = array();
        parent::tearDown();
    }

    /** Calls a protected method of the todo under test. */
    private function invoke($method, ...$args)
    {
        $reflection = new ReflectionMethod($this->obj, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($this->obj, ...$args);
    }

    private function userTimezone()
    {
        $timezone = $this->_AppUI->getPref('TIMEZONE');

        return new DateTimeZone($timezone ? $timezone : date_default_timezone_get());
    }

    /** The stored (UTC) value for the end of a day in the user's timezone. */
    private function storedEndOfDay(DateTimeImmutable $day)
    {
        return $day->setTime(23, 59, 59)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function testObjectProperties()
    {
        $this->assertInstanceOf('CTodo', $this->obj);
        foreach (array('todo_id', 'todo_name', 'todo_due_date', 'todo_project', 'todo_owner',
                     'todo_category', 'todo_contact', 'todo_status', 'todo_created', 'todo_updated') as $field) {
            $this->assertTrue(property_exists($this->obj, $field), $field);
        }
    }

    public function testNoDueDateIsNotADueDate()
    {
        $this->assertFalse(CTodo::hasDueDate(CTodo::NO_DUE_DATE));
        $this->assertFalse(CTodo::hasDueDate(''));
        $this->assertFalse(CTodo::hasDueDate(null));
        $this->assertTrue(CTodo::hasDueDate('2026-11-20 04:59:59'));
    }

    public function testLaterBecomesNoDueDate()
    {
        $this->obj->todo_due_date = 'later';
        $this->invoke('hook_preStore');

        $this->assertEquals(CTodo::NO_DUE_DATE, $this->obj->todo_due_date);
    }

    public function testStoredDueDateIsKeptWhenCompleting()
    {
        // complete() and delete() store a loaded record
        $this->obj->todo_due_date = '2026-11-20 04:59:59';
        $this->invoke('hook_preStore');

        $this->assertEquals('2026-11-20 04:59:59', $this->obj->todo_due_date);
    }

    public function testTodayIsTheEndOfTheUsersDay()
    {
        $this->obj->todo_due_date = 'today';
        $this->invoke('hook_preStore');

        $today = new DateTimeImmutable('today', $this->userTimezone());
        $this->assertEquals($this->storedEndOfDay($today), $this->obj->todo_due_date);
    }

    public function testPickedDateIsStoredAsTheEndOfThatDay()
    {
        $_POST['display_todo_date'] = '20261225';
        $this->obj->todo_due_date = 'other';
        $this->invoke('hook_preStore');

        $day = new DateTimeImmutable('2026-12-25', $this->userTimezone());
        $this->assertEquals($this->storedEndOfDay($day), $this->obj->todo_due_date);
    }

    public function testMissingOrInvalidPickedDateFailsValidation()
    {
        foreach (array(null, '20261345', '2026-12-25') as $picked) {
            $_POST = (null === $picked) ? array() : array('display_todo_date' => $picked);
            $todo = new CTodo();
            $todo->todo_name = 'Call Bob';
            $todo->todo_owner = 1;
            $todo->todo_due_date = 'other';
            $method = new ReflectionMethod($todo, 'hook_preStore');
            $method->setAccessible(true);
            $method->invoke($todo);

            $this->assertFalse($todo->isValid(), var_export($picked, true));
            $this->assertArrayHasKey('todo_due_date', $todo->getError());
        }
    }

    public function testNameAndOwnerAreRequired()
    {
        $this->obj->todo_name = ' ';
        $this->obj->todo_owner = 0;
        $this->obj->todo_due_date = CTodo::NO_DUE_DATE;

        $this->assertFalse($this->obj->isValid());
        $this->assertArrayHasKey('todo_name', $this->obj->getError());
        $this->assertArrayHasKey('todo_owner', $this->obj->getError());
    }

    public function testClosedLegacyTodoIsValidWithoutName()
    {
        $this->obj->todo_name = '';
        $this->obj->todo_owner = 0;
        $this->obj->todo_status = 0;
        $this->obj->todo_due_date = '2026-11-20 04:59:59';

        $this->assertTrue($this->obj->isValid());
    }

    public function testTimeframeBoundariesAreInOrder()
    {
        $b = $this->obj->getTimeframeBoundaries();

        $this->assertLessThan($b['tomorrow'], $b['today']);
        $this->assertLessThan($b['this-week'], $b['tomorrow']);
        $this->assertLessThanOrEqual($b['next-week'], $b['this-week']);
        $this->assertLessThan($b['later'], $b['next-week']);
        $this->assertLessThan(CTodo::NO_DUE_DATE, $b['later']);
    }

    public function testWeekEndsTheDayBeforeTheLocalesFirstDay()
    {
        $firstDay = defined('LOCALE_FIRST_DAY') ? (int) LOCALE_FIRST_DAY : 0;
        $tuesday = new DateTimeImmutable('2026-11-10', $this->userTimezone());
        $last = $this->invoke('_lastDayOfWeek', $tuesday);

        $this->assertEquals(($firstDay + 6) % 7, (int) $last->format('w'));
        $this->assertGreaterThanOrEqual($tuesday, $last);
        $this->assertLessThan($tuesday->modify('+7 day'), $last);
    }

    public function testEditFormKeepsAnOverdueDate()
    {
        $threeDaysAgo = new DateTimeImmutable('-3 day midnight', $this->userTimezone());
        $this->obj->todo_id = 1;
        $this->obj->todo_due_date = $this->storedEndOfDay($threeDaysAgo);

        $info = $this->obj->renderTimeframe();
        $this->assertEquals('other', $info['displayName']);
        $this->assertEquals($threeDaysAgo->format('Ymd'), $info['cleanFormat']);
    }

    public function testEditFormShowsLaterForNoDueDate()
    {
        $this->obj->todo_id = 1;
        $this->obj->todo_due_date = CTodo::NO_DUE_DATE;

        $info = $this->obj->renderTimeframe();
        $this->assertEquals('later', $info['displayName']);
    }

    public function testOutputIsEscaped()
    {
        $this->assertEquals('&lt;b&gt;&quot;x&quot;&lt;/b&gt;', CTodo::e('<b>"x"</b>'));
        $this->assertEquals('a1B2c3', CTodo::safeColor('a1B2c3'));
        $this->assertEquals('ffffff', CTodo::safeColor('fff;background:url(x)'));
    }

    public function testRenderContactHidesOtherPeoplesPrivateContacts()
    {
        $row = array('todo_contact' => 4, 'contact_display_name' => 'Ann <b>Lee</b>',
            'contact_email' => 'ann@example.com', 'contact_private' => 0, 'contact_owner' => 0);

        $html = $this->obj->renderContact($row);
        $this->assertStringContainsString('Ann &lt;b&gt;Lee&lt;/b&gt;', $html);
        $this->assertStringContainsString('mailto:ann@example.com', $html);

        $row['contact_private'] = 1;
        $row['contact_owner'] = (int) $this->_AppUI->user_id + 1;
        $this->assertEquals('', $this->obj->renderContact($row));
    }

    public function testOwnRowsCanBeChanged()
    {
        $this->assertTrue($this->obj->canChangeItem(array('todo_owner' => $this->_AppUI->user_id, 'todo_project' => 0)));
    }

    public function testSearchOnlyReturnsTheUsersOpenTodos()
    {
        $search = $this->obj->hook_search();

        $this->assertStringContainsString('t.todo_status = 1', $search['table_extra']);
        $this->assertStringContainsString('t.todo_owner = ' . (int) $this->_AppUI->user_id, $search['table_extra']);
    }
}
