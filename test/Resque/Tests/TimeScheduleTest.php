<?php

/**
 * Resque time expression / time scheduling tests.
 *
 * @package		Resque/Tests
 * @author		Roy de Jong <roy@softwarepunt.nl>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Tests_TimeScheduleTest extends Resque_Tests_TestCase
{
    // -----------------------------------------------------------------------------------------------------------------
    // Resque_Time_Schedule - logic

    public function testScheduleCheck()
    {
        // Test expression: 10pm-6am
        $schedule = new Resque_Time_Schedule(
            new Resque_Time_Expression(22, 0),
            new Resque_Time_Expression(6, 0),
        );

        $this->assertTrue(
            $schedule->isInSchedule(new DateTime('2023-08-01 06:00:00')),
            "Schedule: 6am should be accepted for a 10pm-6am schedule"
        );
        $this->assertTrue(
            $schedule->isInSchedule(new DateTime('2023-08-01 00:00:00')),
            "Schedule: 12am should be accepted for a 10pm-6am schedule"
        );
        $this->assertTrue(
            $schedule->isInSchedule(new DateTime('2023-08-01 22:00:00')),
            "Schedule: 10pm should be accepted for a 10pm-6am schedule"
        );

        $this->assertFalse(
            $schedule->isInSchedule(new DateTime('2023-08-01 06:01:00')),
            "Schedule: 6:01am should be rejected for a 10pm-6am schedule"
        );
        $this->assertFalse(
            $schedule->isInSchedule(new DateTime('2023-08-01 12:00:00')),
            "Schedule: 12pm should be rejected for a 10pm-6am schedule"
        );
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Resque_Time_Expression - expression/format

    public function testParseTimeExpression()
    {
        $this->assertEquals(
            new Resque_Time_Expression(12, 34),
            Resque_Time_Expression::tryParse("12:34"),
            "Valid time expression - tryParse should return parsed result"
        );
        $this->assertEquals(
            new Resque_Time_Expression(12, 34),
            Resque_Time_Expression::tryParse("12:34:56"),
            "Valid time expression, with seconds - tryParse should return parsed result discarding seconds"
        );

        $this->assertNull(
            Resque_Time_Expression::tryParse("not_valid"),
            "Invalid time expression string, bad format - tryParse should return null"
        );
        $this->assertNull(
            Resque_Time_Expression::tryParse("-1:99"),
            "Invalid time expression, impossible values - tryParse should return null"
        );
    }

    public function testFormatTimeExpression()
    {
        $this->assertSame(
            "20:19",
            (string)(new Resque_Time_Expression(20, 19))
        );
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Resque_Time_Schedule - expression/format

    public function testParseScheduleExpression()
    {
        $this->assertEquals(
            new Resque_Time_Schedule(
                new Resque_Time_Expression(22, 0),
                new Resque_Time_Expression(6, 0),
            ),
            Resque_Time_Schedule::tryParse("22:00-06:00"),
            "Valid schedule expression - tryParse should return parsed result"
        );
        $this->assertEquals(
            new Resque_Time_Schedule(
                new Resque_Time_Expression(22, 12),
                new Resque_Time_Expression(6, 34),
            ),
            Resque_Time_Schedule::tryParse(" 22:12 - 06:34 "),
            "Valid schedule expression - tryParse should return parsed result, trimming excess spaces"
        );
        $this->assertEquals(
            new Resque_Time_Schedule(
                new Resque_Time_Expression(22, 34),
                new Resque_Time_Expression(6, 56),
            ),
            Resque_Time_Schedule::tryParse("22:34:12.999999 - 06:56:12.999999"),
            "Valid schedule expression - tryParse should return parsed result, discarding seconds"
        );

        $this->assertNull(
            Resque_Time_Schedule::tryParse("not valid"),
            "Invalid schedule expression - tryParse should return null"
        );
        $this->assertNull(
            Resque_Time_Schedule::tryParse("456 - 123"),
            "Invalid schedule expression - tryParse should return null"
        );
    }

    public function testFormatScheduleExpression()
    {
        $this->assertSame(
            "22:34 - 06:56",
            (string)new Resque_Time_Schedule(
                new Resque_Time_Expression(22, 34),
                new Resque_Time_Expression(6, 56),
            )
        );
    }
}