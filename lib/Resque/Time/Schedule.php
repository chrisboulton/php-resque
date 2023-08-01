<?php

/**
 * Resque time schedule expression.
 *
 * @package		Resque/Time
 * @author		Roy de Jong <roy@softwarepunt.nl>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Time_Schedule
{
    public Resque_Time_Expression $from;
    public Resque_Time_Expression $until;

    public function __construct(Resque_Time_Expression $from, Resque_Time_Expression $until)
    {
        $this->from = $from;
        $this->until = $until;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Schedule checking

    public function isInSchedule(DateTime $now): bool
    {
        $todayFrom = $this->getFromDateTime($now);

        if ($todayFrom > $now) {
            // Outside of start range, check if we are in yesterday's range
            $yesterdayFrom = (clone $todayFrom)->modify('-1 day');
            $yesterdayUntil = $this->getUntilDateTime($yesterdayFrom);

            return $now >= $yesterdayFrom && $now <= $yesterdayUntil;
        }

        $todayUntil = $this->getUntilDateTime($todayFrom);
        return $now <= $todayUntil;
    }

    public function getFromDateTime(?DateTime $now = null): DateTime
    {
        if (!$now)
            $now = new DateTime('now');

        $dt = clone $now;
        $dt->setTime($this->from->hour, $this->from->minute, 0, 0);

        return $dt;
    }

    public function getUntilDateTime(?DateTime $fromDateTime = null): DateTime
    {
        if (!$fromDateTime)
            $fromDateTime = new DateTime('now');

        $dt = clone $fromDateTime;
        $dt->setTime($this->until->hour, $this->until->minute, 59, 999999);

        if ($dt < $fromDateTime)
            // Midnight rollover
            $dt->modify('+1 day');

        return $dt;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Expression parsing

    public static function tryParse(string $input): ?Resque_Time_Schedule
    {
        $parts = explode('-', $input, 2);

        if (count($parts) !== 2)
            return null;

        $from = Resque_Time_Expression::tryParse(trim($parts[0]));
        $until = Resque_Time_Expression::tryParse(trim($parts[1]));

        if ($from === null || $until === null)
            return null;

        return new Resque_Time_Schedule($from, $until);
    }
}