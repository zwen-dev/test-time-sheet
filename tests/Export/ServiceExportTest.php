<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Export;

use App\Export\Renderer\HtmlRenderer;
use App\Export\ServiceExport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;

/**
 * @covers \App\Export\ServiceExport
 */
class ServiceExportTest extends TestCase
{
    public function testEmptyObject()
    {
        $sut = new ServiceExport([]);
        $this->assertEmpty($sut->getRenderer());
    }

    public function testUnknownRendererReturnsNull()
    {
        $sut = new ServiceExport([]);
        $this->assertNull($sut->getRendererById('default'));
    }

    public function testAdd()
    {
        $sut = new ServiceExport([]);

        $sut->addRenderer(new HtmlRenderer(
            $this->getMockBuilder(Environment::class)->disableOriginalConstructor()->getMock(),
            new EventDispatcher()
        ));

        $this->assertEquals(1, count($sut->getRenderer()));
    }

    public function testAddViaConstruct()
    {
        $renderer = [new HtmlRenderer(
            $this->getMockBuilder(Environment::class)->disableOriginalConstructor()->getMock(),
            new EventDispatcher()
        )];
        $sut = new ServiceExport($renderer);

        $this->assertEquals(1, count($sut->getRenderer()));
    }
}
