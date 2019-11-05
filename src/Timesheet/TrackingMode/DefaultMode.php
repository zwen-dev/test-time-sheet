<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Timesheet\TrackingMode;

class DefaultMode extends AbstractTrackingMode
{
    public function canEditBegin(): bool
    {
        return true;
    }

    public function canEditEnd(): bool
    {
        return true;
    }

    public function canEditDuration(): bool
    {
        return false;
    }

    public function canUpdateTimesWithAPI(): bool
    {
        return true;
    }

    public function getId(): string
    {
        return 'default';
    }

    public function canSeeBeginAndEndTimes(): bool
    {
        return true;
    }
}
