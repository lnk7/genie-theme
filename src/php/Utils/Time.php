<?php

namespace Theme\Utils;

use Carbon\Carbon;
use DateTimeZone;

class Time
{


    /**
     * @param Carbon[] $array
     *
     * @return Carbon
     */
    public static function latest(array $array)
    {
        usort($array, [static::class, 'sortDates']);
        return $array[count($array) - 1];
    }


    /**
     * Now in our timezone
     *
     * @return Carbon
     */
    public static function now()
    {
        return Carbon::now()->setTimezone(static::tz());

    }


    /**
     * @param Carbon[] $array
     *
     * @return Carbon
     */
    public static function soonest(array $array)
    {
        usort($array, [static::class, 'sortDates']);
        return $array[0];
    }


    /**
     * @param Carbon $date1
     * @param Carbon $date2
     *
     * @return int
     */
    public static function sortDates(Carbon $date1, Carbon $date2)
    {
        if ($date1->isAfter($date2)) {
            return 1;
        } elseif ($date1->isbefore($date2)) {
            return -1;
        }
        return 0;
    }


    /**
     * Return a new London Timezone Object
     *
     * @return DateTimeZone
     */
    public static function tz()
    {
        return new DateTimeZone('Europe/London');
    }


    /**
     * Utc Time
     *
     * @return Carbon
     */
    public static function utcNow()
    {
        return Carbon::now()->setTimezone('UTC');
    }


    /**
     * Return the current UTC timestamp
     *
     * @return int
     */
    public static function utcTimestamp()
    {
        return static::utcNow()->getTimestamp();
    }

}
