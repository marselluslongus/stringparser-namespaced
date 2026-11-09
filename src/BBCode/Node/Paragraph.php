<?php

namespace StringParser\BBCode\Node;

/**
 * BB code string parsing: paragraph node
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

use StringParser\BBCode;
use StringParser\Node\Node;


/**
 * BBCode string parser paragraph node class
 *
 * @package stringparser
 */
final class Paragraph extends Node
{
    public int $_type = BBCode::NODE_PARAGRAPH;


    /**
     * Supports the criterium 'empty': no children, or a single text child that is
     * empty apart from newlines dropped/ignored by newline handling
     *
     * @param string $criterium
     * @param mixed $value
     * @return bool
     */
    public function matchesCriterium(string $criterium, mixed $value): bool
    {
        if ('empty' !== $criterium) {
            return false;
        }
        if (!count($this->children)) {
            return true;
        }
        if (count($this->children) > 1) {
            return false;
        }
        $child = $this->children[0];
        if (Node::NODE_TEXT !== $child->_type) {
            return false;
        }
        $content = $child->content;
        if (strlen($content) > 2) {
            return false;
        }
        if ((BBCode::NEWLINE_PARSE !== $child->getFlag('newlinemode.begin', 'integer', BBCode::NEWLINE_PARSE)) && str_starts_with($content, "\n")) {
            $content = substr($content, 1);
        }
        if ((BBCode::NEWLINE_PARSE !== $child->getFlag('newlinemode.end', 'integer', BBCode::NEWLINE_PARSE)) && str_ends_with($content, "\n")) {
            $content = substr($content, 0, -1);
        }
        return '' === $content;
    }
}
