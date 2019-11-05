<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Timesheet\TrackingMode;

use App\Entity\Timesheet;
use Symfony\Component\HttpFoundation\Request;

class DurationOnlyMode extends AbstractTrackingMode
{
    public function canEditBegin(): bool
    {
        return true;
    }

    public function canEditEnd(): bool
    {
        return false;
    }

    public function canEditDuration(): bool
    {
        return true;
    }

    public function canUpdateTimesWithAPI(): bool
    {
        return true;
    }

    public function getId(): string
    {
        return 'duration_only';
    }

    public function canSeeBeginAndEndTimes(): bool
    {
        return false;
    }

    public function create(Timesheet $timesheet, Request $request): void
    {
        if (null === $timesheet->getBegin()) {
            $timesheet->setBegin($this->dateTime->createDateTime());
        }

        $timesheet->getBegin()->modify($this->configuration->getDefaultBeginTime());

        parent::create($timesheet, $request);
    }
}
