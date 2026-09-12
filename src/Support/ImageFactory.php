<?php

namespace TCG\Voyager\Support;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\DriverInterface;

/**
 * Builds the Intervention ImageManager Voyager works with.
 *
 * Voyager deliberately does not go through the `image` container binding: Laravel 13
 * ships its own image abstraction and claims that same binding, so resolving the
 * facade can hand back the framework's manager instead of Intervention's. Building
 * the manager here keeps Voyager working the same on Laravel 12, 13 and 14 while
 * still honouring the driver configured in config/image.php.
 */
class ImageFactory
{
    public static function make(): ImageManager
    {
        return new ImageManager(static::driver());
    }

    protected static function driver(): DriverInterface
    {
        $driver = config('image.driver');

        if ($driver instanceof DriverInterface) {
            return $driver;
        }

        if (is_string($driver)) {
            // Either a driver FQCN (intervention/image-laravel) or a short name.
            if (is_a($driver, DriverInterface::class, true)) {
                return new $driver();
            }

            $short = '\\Intervention\\Image\\Drivers\\'.ucfirst(strtolower($driver)).'\\Driver';

            if (is_a($short, DriverInterface::class, true)) {
                return new $short();
            }
        }

        return new GdDriver();
    }
}
