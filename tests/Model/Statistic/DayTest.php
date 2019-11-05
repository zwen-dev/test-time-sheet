<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Model\Statistic;

use App\Model\Statistic\Day;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Model\Statistic\Day
 */
class DayTest extends TestCase
{
    public function testConstruct()
    {
        $date = new DateTime('-8 hours');
        $sut = new Day($date, 12340, 197.25956);

        $this->assertSame($date, $sut->getDay());
        $this->assertEquals(12340, $sut->getTotalDuration());
        $this->assertEquals(197.25956, $sut->getTotalRate());
    }

    public function testAllowedMonths()
    {
        $date = new DateTime('-8 hours');
        $sut = new Day($date, 12340, 197.25956);

        $sut->setTotalDuration(999.27);
        $sut->setTotalRate(0.123456789);

        $this->assertEquals(999, $sut->getTotalDuration());
        $this->assertEquals(0.123456789, $sut->getTotalRate());
    }
}
