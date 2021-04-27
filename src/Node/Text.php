<?php

namespace StringParser\Node;

/**
 * Generic string parsing infrastructure
 *
 * These classes provide the means to parse any kind of string into a tree-like
 * memory structure. It would e.g. be possible to create an HTML parser based
 * upon this class.
 *
 * Version: 0.3.3
 *
 * @author Christian Seiler <spam@christian-seiler.de>
 * @copyright Christian Seiler 2004-2008
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
 */

/**
 * marcel.pommer@flane.de 2012-09-24:
 *  removed assignments of return value of new by reference
 * marcel.pommer@flane.de 2021-03-23:
 *  PHP7 compliance, namespaces
 */


/**
 * Generic string parser node class
 *
 * This is an abstract class for any type of node that is used within the
 * string parser. General warning: This class contains code regarding references
 * that is very tricky. Please do not touch this code unless you exactly know
 * what you are doing. Incorrect handling of references may cause PHP to crash
 * with a segmentation fault! You have been warned.
 *
 * @package stringparser
 */

use \StringParser\StringParser;


/**
 * String parser text node class
 *
 * @package stringparser
 */
class Text extends Node
{
    /**
     * The type of this node.
     *
     * This node is a text node.
     *
     * @access protected
     * @var int
     * @see StringParser::NODE_TEXT
     */
    var $_type = Node::NODE_TEXT;

    /**
     * Node flags
     *
     * @access protected
     * @var array
     */
    var $_flags = array();

    /**
     * The content of this node
     * @access public
     * @var string
     */
    var $content = '';

    /**
     * Constructor
     *
     * @access public
     * @param string $content The initial content of this element
     * @param int $occurredAt The position in the text where this node
     *                        occurred at. If not determinable, it is -1.
     * @see Text::content
     */
    public function __construct($content, $occurredAt = -1)
    {
        parent::__construct($occurredAt);
        $this->content = $content;
    }


    /**
     * Append text to content
     *
     * @access public
     * @param string $text The text to append
     * @see Text::content
     */
    public function appendText($text)
    {
        $this->content .= $text;
    }


    /**
     * Set a flag
     *
     * @access public
     * @param string $name The name of the flag
     * @param mixed $value The value of the flag
     * @return true
     */
    public function setFlag($name, $value)
    {
        $this->_flags[$name] = $value;
        return true;
    }

    /**
     * Get Flag
     *
     * @access public
     * @param string $flag The requested flag
     * @param string $type The requested type of the return value
     * @param mixed $default The default return value
     * @return mixed
     */
    public function getFlag($flag, $type = 'mixed', $default = null)
    {
        if (! isset($this->_flags[$flag])) {
            return $default;
        }
        $return = $this->_flags[$flag];
        if ($type != 'mixed') {
            settype($return, $type);
        }
        return $return;
    }


    /**
     * Dump this node to a string
     */
    protected function dumpToString()
    {
        return 'text "' . substr(preg_replace('/\s+/', ' ', $this->content), 0, 40) . '" [f:' . preg_replace('/\s+/', ' ', join(':', array_keys($this->_flags))) . ']';
    }
}
