<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Utils;

/**
 * A simple class to parse markdown syntax and return HTML.
 */
class Markdown
{
    /**
     * @var ParsedownExtension
     */
    private $parser;

    /**
     * Markdown constructor.
     */
    public function __construct()
    {
        $this->parser = new ParsedownExtension();
    }

    /**
     * @param string $text
     * @param bool $safe
     * @return string
     */
    public function toHtml(string $text, bool $safe = true): string
    {
        $this->parser->setSafeMode($safe);

        return $this->parser->text($text);
    }
}
