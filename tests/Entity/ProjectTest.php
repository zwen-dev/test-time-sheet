<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\ProjectMeta;
use App\Entity\Team;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Entity\Project
 */
class ProjectTest extends TestCase
{
    public function testDefaultValues()
    {
        $sut = new Project();
        self::assertNull($sut->getId());
        self::assertNull($sut->getCustomer());
        self::assertNull($sut->getName());
        self::assertNull($sut->getOrderNumber());
        self::assertNull($sut->getOrderDate());
        self::assertNull($sut->getComment());
        self::assertTrue($sut->getVisible());
        self::assertTrue($sut->isVisible());
        self::assertNull($sut->getFixedRate());
        self::assertNull($sut->getHourlyRate());
        self::assertNull($sut->getColor());
        self::assertEquals(0.0, $sut->getBudget());
        self::assertEquals(0, $sut->getTimeBudget());
        self::assertInstanceOf(Collection::class, $sut->getMetaFields());
        self::assertEquals(0, $sut->getMetaFields()->count());
        self::assertNull($sut->getMetaField('foo'));
        self::assertInstanceOf(Collection::class, $sut->getTeams());
        self::assertEquals(0, $sut->getTeams()->count());
    }

    public function testSetterAndGetter()
    {
        $sut = new Project();

        $customer = (new Customer())->setName('customer');
        self::assertInstanceOf(Project::class, $sut->setCustomer($customer));
        self::assertSame($customer, $sut->getCustomer());

        self::assertInstanceOf(Project::class, $sut->setName('123456789'));
        self::assertEquals('123456789', (string) $sut);

        self::assertInstanceOf(Project::class, $sut->setOrderNumber('123456789'));
        self::assertEquals('123456789', $sut->getOrderNumber());

        $dateTime = new \DateTime('-1 year');
        self::assertInstanceOf(Project::class, $sut->setOrderDate($dateTime));
        self::assertSame($dateTime, $sut->getOrderDate());
        self::assertInstanceOf(Project::class, $sut->setOrderDate(null));
        self::assertNull($sut->getOrderDate());

        self::assertInstanceOf(Project::class, $sut->setComment('a comment'));
        self::assertEquals('a comment', $sut->getComment());

        self::assertInstanceOf(Project::class, $sut->setColor('#fffccc'));
        self::assertEquals('#fffccc', $sut->getColor());

        self::assertInstanceOf(Project::class, $sut->setVisible(false));
        self::assertFalse($sut->getVisible());

        self::assertInstanceOf(Project::class, $sut->setFixedRate(13.47));
        self::assertEquals(13.47, $sut->getFixedRate());

        self::assertInstanceOf(Project::class, $sut->setHourlyRate(99));
        self::assertEquals(99, $sut->getHourlyRate());

        self::assertInstanceOf(Project::class, $sut->setBudget(12345.67));
        self::assertEquals(12345.67, $sut->getBudget());

        self::assertInstanceOf(Project::class, $sut->setTimeBudget(937321));
        self::assertEquals(937321, $sut->getTimeBudget());
    }

    public function testMetaFields()
    {
        $sut = new Project();
        $meta = new ProjectMeta();
        $meta->setName('foo')->setValue('bar')->setType('test');
        self::assertInstanceOf(Project::class, $sut->setMetaField($meta));
        self::assertEquals(1, $sut->getMetaFields()->count());
        $result = $sut->getMetaField('foo');
        self::assertSame($result, $meta);
        self::assertEquals('test', $result->getType());

        $meta2 = new ProjectMeta();
        $meta2->setName('foo')->setValue('bar')->setType('test2');
        self::assertInstanceOf(Project::class, $sut->setMetaField($meta2));
        self::assertEquals(1, $sut->getMetaFields()->count());
        self::assertCount(0, $sut->getVisibleMetaFields());

        $result = $sut->getMetaField('foo');
        self::assertSame($result, $meta);
        self::assertEquals('test2', $result->getType());

        $sut->setMetaField((new ProjectMeta())->setName('blub')->setIsVisible(true));
        $sut->setMetaField((new ProjectMeta())->setName('blab')->setIsVisible(true));
        self::assertEquals(3, $sut->getMetaFields()->count());
        self::assertCount(2, $sut->getVisibleMetaFields());
    }

    public function testTeams()
    {
        $sut = new Project();
        $team = new Team();
        self::assertEmpty($sut->getTeams());
        self::assertEmpty($team->getProjects());

        $sut->addTeam($team);
        self::assertCount(1, $sut->getTeams());
        self::assertCount(1, $team->getProjects());
        self::assertSame($team, $sut->getTeams()[0]);
        self::assertSame($sut, $team->getProjects()[0]);

        $sut->removeTeam($team);
        self::assertCount(0, $sut->getTeams());
        self::assertCount(0, $team->getProjects());
    }
}
