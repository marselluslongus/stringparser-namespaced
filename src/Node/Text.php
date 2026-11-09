<?php

namespace StringParser\Node;

/**
 * Generic string parsing infrastructure: text node
 *
 * Version: 0.4.0
 *
 * @author Christian Seiler <spam@christian-seiler.de>
 * @copyright Christian Seiler 2004-2009
 * @package stringparser
 *
 * The MIT License
 *
 * Copyright (c) 2004-2009 Christian Seiler
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * Changes:
 *  marcel.pommer@flane.de 2012-09-24: removed assignments of return value of new by reference
 *  marcel.pommer@flane.de 2021-03-23: PHP7 compliance, namespaces
 *  marcel.pommer@flane.de 2026-11: PHP 8.4 rewrite: declared and typed properties,
 *   no object references, dead loop mode removed, tree-manipulation bugs fixed
 */

use StringParser\Traits\Flags;


/**
 * String parser text node class
 *
 * @package stringparser
 */
final class Text extends Node
{
    use Flags;

    public int $_type = Node::NODE_TEXT;

    /**
     * The content of this node
     */
    public string $content = '';


    /**
     * @param string $content The initial content
     * @param int $occurredAt The position in the text where this node occurred at, -1 if unknown
     */
    public function __construct(string $content, int $occurredAt = -1)
    {
        parent::__construct($occurredAt);
        $this->content = $content;
    }


    /**
     * Append text to the content
     *
     * @param string $text
     * @return void
     */
    public function appendText(string $text): void
    {
        $this->content .= $text;
    }


    /**
     * @return string
     */
    protected function dumpToString(): string
    {
        return 'text "' . substr(preg_replace('/\s+/', ' ', $this->content), 0, 40) . '" [f:' . preg_replace('/\s+/', ' ', implode(':', array_keys($this->flags))) . ']';
    }
}
