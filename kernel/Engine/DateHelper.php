<?php
namespace Manomite\Engine;

use \Carbon\{
    Carbon
};

include __DIR__ . '/../../autoload.php';
class DateHelper
{
    protected $carbon;

    public function __construct($timezone = TIMEZONE)
    {
        date_default_timezone_set($timezone);
        $this->carbon = Carbon::now($timezone);
    }
    public function now($format = 'Y-m-d H:i:s', $timestamp = false)
    {
        $date = $this->carbon->now();
        if ($timestamp === false) {
            return $date->format($format);
        }
        return $date->timestamp;

    }

    public function addMinute($value)
    {
        return $this->carbon->addMinutes($value)->timestamp;
    }

    public function addDays($value)
    {
        $date = $this->carbon->addDays($value);
        return $date->format('Y-m-d H:i:s');
    }

    public function addYears($value, $format = 'Y-m-d H:i:s')
    {
        $date = $this->carbon->now()->addYears($value);
        return $date->format($format);
    }

    public function addDaysTimestamp($value)
    {
        return $this->carbon->addDays($value)->timestamp;
    }

    public function timestampTimeNow()
    {
        return $this->carbon->timestamp;
    }

    public function today()
    {
        return $this->carbon->today()->toDateString();
    }

    public function daysAgo($days)
    {
        return $this->carbon->today()->subDays($days)->toDateString();
    }

    public function minuteAgo($min)
    {
        return $this->carbon->now()->subMinutes($min)->toDateTimeString();
    }

    public function yesterday()
    {
        return $this->carbon->yesterday()->toDateString();
    }

    public function format($value)
    {
        return $this->carbon->parse($value)->toDateTimeString();
    }

    public function dateToFormat($date, $format = 'd-m-Y g:ia')
    {
        if (!empty($date) and !is_null($date)) {
            if (str_contains($date, '/')) {
                return $this->carbon->createFromFormat('d/m/Y', $date)->format($format);
            }
            $date = $this->carbon->parse($date);
            return $date->format($format);
        }
        return '--';
    }
    public function formatDateFromTimestamp($timestamp, $format = 'd-m-Y g:ia')
    {
        if (!is_null($timestamp)) {
            $date = $this->carbon->createFromTimestamp($timestamp);
            return $date->format($format);
        }
        return '--';
    }

    public function formatToTimestamp($date = null)
    {
        if (!is_null($date)) {
            return $this->carbon->createFromTimestamp(strtotime($date))->timestamp;
        }
        return '--';
    }

    public function timesAgo($date)
    {
        return $this->carbon->createFromTimeStamp($date)->diffForHumans();
    }
    
    public function getAllTimezone()
    {
        $zones = timezone_identifiers_list();
        foreach ($zones as $zone) {
            $zoneExploded = explode('/', $zone); // 0 => Continent, 1 => City
            // Only use "friendly" continent names
            if ($zoneExploded[0] == 'Africa' || $zoneExploded[0] == 'America' || $zoneExploded[0] == 'Antarctica' || $zoneExploded[0] == 'Arctic' || $zoneExploded[0] == 'Asia' || $zoneExploded[0] == 'Atlantic' || $zoneExploded[0] == 'Australia' || $zoneExploded[0] == 'Europe' || $zoneExploded[0] == 'Indian' || $zoneExploded[0] == 'Pacific') {
                if (isset($zoneExploded[1]) != '') {
                    $area = str_replace('_', ' ', $zoneExploded[1]);

                    if (!empty($zoneExploded[2])) {
                        $area = $area . ' (' . str_replace('_', ' ', $zoneExploded[2]) . ')';
                    }

                    $locations[$zoneExploded[0]][$zone] = $area; // Creates array(DateTimeZone => 'Friendly name')
                }
            }
        }
        return $locations;
    }

    public function diffInMonths($from, $to)
    {
        $from = $this->carbon->parse($from);
        $to = $this->carbon->parse($to);
        return $from->diffInMonths($to);
    }

    public function diffInDays($from, $to)
    {
        $from = $this->carbon->parse($from);
        $to = $this->carbon->parse($to);
        return $from->diffInDays($to);
    }

    public function diffInSeconds($from, $to)
    {
        $from = $this->carbon->parse($from);
        $to = $this->carbon->parse($to);
        return $from->diffInSeconds($to);
    }

    public function hoursLeftUntilMidnight()
    {
        $tomorrow = $this->carbon::tomorrow()->startOfDay();
        return $this->diffInHours($tomorrow);
    }

    public function addHours($hours, $format = 'd-m-Y')
    {
        $add = $this->carbon::parse($this->now());
        $date = $add->addHours($hours);
        return $date->format($format);
    }

    public function nextMonth($current_date)
    {
        $datetime = $this->carbon->createFromFormat('Y-m-d H:i:s', $current_date)->addMonthsNoOverflow();
        return $datetime->format('Y-m-d H:i:s');
    }

    public function lastWeekTimestamp()
    {
        return $this->carbon->subWeek()->timestamp;
    }

    public function getCurrentDateTimeByCountry($countryName) {
        $timezoneIdentifier = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, strtoupper($countryName));
        
        if (empty($timezoneIdentifier)) {
            return false;
        }
        
        $timezone = new \DateTimeZone($timezoneIdentifier[0]);
        $datetime = new \DateTime('now', $timezone);
        
        return $datetime->format('Y-m-d H:i:s');
    }

    public function canStart($nigeriaDateTime, $targetCountry) {
        // Get Nigeria's timezone
        $nigeriaTimezoneIdentifier = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, 'NG');
        $nigeriaTimezone = new \DateTimeZone($nigeriaTimezoneIdentifier[0]);
        
        // Create Nigeria datetime object
        $nigeriaDatetime = new \DateTime($nigeriaDateTime, $nigeriaTimezone);
        
        // Get target country's timezone
        $targetTimezoneIdentifier = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, strtoupper($targetCountry));
        if (empty($targetTimezoneIdentifier)) {
            return false;
        }
        $targetTimezone = new \DateTimeZone($targetTimezoneIdentifier[0]);
        
        // Convert Nigeria datetime to target country's timezone
        $nigeriaDatetime->setTimezone($targetTimezone);
        
        // Get current datetime in target country
        $currentDatetime = new \DateTime('now', $targetTimezone);
        
        // Compare Nigeria datetime with current datetime in target country
        if ($currentDatetime >= $nigeriaDatetime) {
            return true;
        } else {
            return false;
        }
    }

    public function convertDatetimeToCountryTime($nigeriaDateTime, $targetCountry) {
        // Get Nigeria's timezone
        $nigeriaTimezoneIdentifier = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, 'NG');
        $nigeriaTimezone = new \DateTimeZone($nigeriaTimezoneIdentifier[0]);
        
        // Create Nigeria datetime object
        $nigeriaDatetime = new \DateTime($nigeriaDateTime, $nigeriaTimezone);
        
        // Get target country's timezone
        $targetTimezoneIdentifier = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, strtoupper($targetCountry));
        if (empty($targetTimezoneIdentifier)) {
            return false;
        }
        $targetTimezone = new \DateTimeZone($targetTimezoneIdentifier[0]);
        
        // Convert Nigeria datetime to target country's timezone
        $nigeriaDatetime->setTimezone($targetTimezone);
        
        // Format the datetime for display
        return $nigeriaDatetime->format('Y-m-d H:i:s');
    }
}
