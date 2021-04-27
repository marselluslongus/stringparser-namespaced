<?php

namespace StringParser\BBCode\Node;

/**
 * BB code string parsing class
 *
 * Version: 0.3.3
 *
 * @author Christian Seiler <spam@christian-seiler.de>
 * @copyright Christian Seiler 2004-2008
 * @package stringparser
 *
 * The MIT License
 *
 * Copyright (c) 2004-2008 Christian Seiler
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
 */

/**
 * marcel.pommer@flane.de 2012-09-24:
 *  removed assignments of return value of new by reference
 * marcel.pommer@flane.de 2021-03-23:
 *  PHP7 compliance, namespaces
 */

use \StringParser\StringParser;
use \StringParser\BBCode;
use \StringParser\Node\Node;


/**
 * BBCode String parser paragraph node class
 *
 * @package stringparser
 */
class Paragraph extends Node
{
    /**
     * The type of this node.
     *
     * This node is a bbcode paragraph node.
     *
     * @access protected
     * @var int
     * @see StringParser::BBCode::NODE_PARAGRAPH
     */
    var $_type = BBCode::NODE_PARAGRAPH;

    /**
     * Determines whether a criterium matches this node
     *
     * @access public
     * @param string $criterium The criterium that is to be checked
     * @param mixed $value The value that is to be compared
     * @return bool True if this node matches that criterium
     */
    public function matchesCriterium($criterium, $value)
    {
        if ($criterium == 'empty') {
            if (!count($this->_children)) {
                return true;
            }
            if (count($this->_children) > 1) {
                return false;
            }
            if ($this->_children[0]->_type != Node::NODE_TEXT) {
                return false;
            }
            if (!strlen($this->_children[0]->content)) {
                return true;
            }
            if (strlen($this->_children[0]->content) > 2) {
                return false;
            }
            $f_begin = $this->_children[0]->getFlag('newlinemode.begin', 'integer', BBCode::NEWLINE_PARSE);
            $f_end = $this->_children[0]->getFlag('newlinemode.end', 'integer', BBCode::NEWLINE_PARSE);
            $content = $this->_children[0]->content;
            if ($f_begin != BBCode::NEWLINE_PARSE && $content{0} == "\n") {
                $content = substr($content, 1);
            }
            if ($f_end != BBCode::NEWLINE_PARSE && $content{strlen($content)-1} == "\n") {
                $content = substr($content, 0, -1);
            }
            if (!strlen($content)) {
                return true;
            }
            return false;
        }
    }
}
