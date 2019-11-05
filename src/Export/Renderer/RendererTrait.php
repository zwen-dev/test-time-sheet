<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Export\Renderer;

use App\Entity\Timesheet;

trait RendererTrait
{
    /**
     * @param Timesheet[] $timesheets
     * @return array
     */
    protected function calculateSummary(array $timesheets)
    {
        $summary = [];

        foreach ($timesheets as $timesheet) {
            $id = $timesheet->getProject()->getCustomer()->getId() . '_' . $timesheet->getProject()->getId();
            $activityId = $timesheet->getActivity()->getId();

            if (!isset($summary[$id])) {
                $summary[$id] = [
                    'customer' => $timesheet->getProject()->getCustomer()->getName(),
                    'project' => $timesheet->getProject()->getName(),
                    'activities' => [],
                    'currency' => $timesheet->getProject()->getCustomer()->getCurrency(),
                    'rate' => 0,
                    'duration' => 0,
                ];
            }

            if (!isset($summary[$id]['activities'][$activityId])) {
                $summary[$id]['activities'][$activityId] = [
                    'activity' => $timesheet->getActivity()->getName(),
                    'currency' => $timesheet->getProject()->getCustomer()->getCurrency(),
                    'rate' => 0,
                    'duration' => 0,
                ];
            }

            $duration = $timesheet->getDuration();
            if (null === $duration) {
                $duration = 0;
            }

            $summary[$id]['rate'] += $timesheet->getRate();
            $summary[$id]['duration'] += $duration;
            $summary[$id]['activities'][$activityId]['rate'] += $timesheet->getRate();
            $summary[$id]['activities'][$activityId]['duration'] += $duration;
        }

        asort($summary);

        return $summary;
    }
}
