<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice\Calculator;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\InvoiceTemplate;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Invoice\CalculatorInterface;
use App\Invoice\InvoiceModel;
use App\Repository\Query\InvoiceQuery;
use PHPUnit\Framework\TestCase;

abstract class AbstractCalculatorTest extends TestCase
{
    protected function assertEmptyModel(CalculatorInterface $sut)
    {
        $customer = new Customer();
        $template = new InvoiceTemplate();
        $query = new InvoiceQuery();

        $model = new InvoiceModel();
        $model->setCustomer($customer);
        $model->setTemplate($template);
        $model->setQuery($query);

        $sut->setModel($model);

        $this->assertEquals(0, $sut->getTotal());
        $this->assertEquals(0, $sut->getVat());
        $this->assertEquals('EUR', $sut->getCurrency());
        $this->assertEquals(0, $sut->getSubtotal());
        $this->assertEquals(0, $sut->getTimeWorked());
        $this->assertEquals(0, count($sut->getEntries()));
        $this->assertEquals(0, $sut->getTax());
    }

    protected function assertDescription(CalculatorInterface $sut, $addProject = false, $addActivity = false)
    {
        $customer = new Customer();
        $template = new InvoiceTemplate();
        $template->setVat(19);

        $user = $this->getMockBuilder(User::class)->setMethods(['getId'])->disableOriginalConstructor()->getMock();
        $user->method('getId')->willReturn(1);

        $project = $this->getMockBuilder(Project::class)->setMethods(['getId', 'getCustomer', 'getName'])->disableOriginalConstructor()->getMock();
        $project->method('getId')->willReturn(1);
        $project->method('getCustomer')->willReturn($customer);
        $project->method('getName')->willReturn('project description');

        $activity = $this->getMockBuilder(Activity::class)->setMethods(['getId', 'getProject', 'getName'])->disableOriginalConstructor()->getMock();
        $activity->method('getId')->willReturn(1);
        $activity->method('getProject')->willReturn($project);
        $activity->method('getName')->willReturn('activity description');

        $query = new InvoiceQuery();
        if ($addProject === true) {
            $query->setProject($project);
        } elseif ($addActivity === true) {
            $query->setActivity($activity);
        }

        $timesheet = new Timesheet();
        $timesheet
            ->setDescription('timesheet description')
            ->setBegin(new \DateTime())
            ->setDuration(3600)
            ->setRate(293.27)
            ->setUser($user)
            ->setActivity($activity)
            ->setProject($project);

        $model = new InvoiceModel();
        $model->setCustomer($customer);
        $model->setTemplate($template);
        $model->setEntries([$timesheet]);
        $model->setQuery($query);

        $sut->setModel($model);
        $this->assertEquals(1, count($sut->getEntries()));

        /** @var Timesheet $result */
        $result = $sut->getEntries()[0];
        if ($addProject === true) {
            $this->assertEquals('project description', $result->getDescription());
        } elseif ($addActivity === true) {
            $this->assertEquals('activity description', $result->getDescription());
        } else {
            $this->assertEquals('timesheet description', $result->getDescription());
        }
    }
}
