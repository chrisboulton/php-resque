<?php

/**
 * Resque hour:minute time expression for scheduling.
 *
 * @package		Resque/Time
 * @author		Roy de Jong <roy@softwarepunt.nl>
 * @license		http://www.opensource.org/licenses/mit-license.php
 *
 * @see Resque_Time_Schedule
 */
class Resque_Time_Expression
{
    public int $hour;
    public int $minute;

    public function __construct(int $hour, int $minute = 0)
    {
        $this->hour = $hour;
        $this->minute = $minute;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Format

    public function __toString(): string
    {
        return self::pad2($this->hour) . ':' . self::pad2($this->minute);
    }

    public static function pad2(int $number): string
    {
        $strVal = strval($number);
        if (strlen($strVal) === 1)
            return "0{$strVal}";
        return $strVal;
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Parse

    /**
     * Tries to parse a time expression, e.g. "12:34" into a Resque_TimeExpression.
     *
     * @param string $input Time expression with hours and minutes.
     * @return Resque_Time_Expression|null Parsed time expression, or NULL if parsing failed.
     */
    public static function tryParse(string $input): ?Resque_Time_Expression
    {
        $parts = explode(':', $input);

        if (count($parts) < 2)
            return null;

        $hours = intval($parts[0]);
        $minutes = intval($parts[1]);

        if ($hours < 0 || $hours >= 24 || $minutes < 0 || $minutes >= 60)
            return null;

        return new Resque_Time_Expression($hours, $minutes);
    }
}