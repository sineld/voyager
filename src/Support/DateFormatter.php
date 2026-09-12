<?php

namespace TCG\Voyager\Support;

use Carbon\CarbonInterface;

/**
 * Formats BREAD date/timestamp values.
 *
 * Carbon 3 (Laravel 11+) removed formatLocalized(), which accepted strftime-style
 * formats such as "%B %e, %Y". BREAD rows created before that still carry those
 * formats, so accept both those and plain PHP date() formats and render either
 * through Carbon's localised translatedFormat().
 */
class DateFormatter
{
    /**
     * strftime token => PHP date() token.
     */
    protected const STRFTIME_MAP = [
        '%a' => 'D',
        '%A' => 'l',
        '%d' => 'd',
        '%e' => 'j',
        '%u' => 'N',
        '%w' => 'w',
        '%b' => 'M',
        '%h' => 'M',
        '%B' => 'F',
        '%m' => 'm',
        '%y' => 'y',
        '%Y' => 'Y',
        '%H' => 'H',
        '%k' => 'G',
        '%I' => 'h',
        '%l' => 'g',
        '%M' => 'i',
        '%p' => 'A',
        '%P' => 'a',
        '%S' => 's',
        '%s' => 'U',
        '%z' => 'O',
        '%Z' => 'T',
        '%D' => 'm/d/y',
        '%F' => 'Y-m-d',
        '%T' => 'H:i:s',
        '%R' => 'H:i',
        '%r' => 'h:i:s A',
        '%n' => "\n",
        '%t' => "\t",
        '%%' => '%',
    ];

    public static function format(CarbonInterface $date, ?string $format = null): string
    {
        if ($format === null || $format === '') {
            return (string) $date;
        }

        return $date->translatedFormat(static::toDateFormat($format));
    }

    /**
     * Convert a strftime format to a PHP date() format, leaving plain date()
     * formats untouched.
     */
    public static function toDateFormat(string $format): string
    {
        if (!str_contains($format, '%')) {
            return $format;
        }

        return strtr($format, static::STRFTIME_MAP);
    }
}
