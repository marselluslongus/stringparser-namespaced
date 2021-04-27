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
 * String parser root node class
 *
 * @package stringparser
 */
class Root extends Node
{
    /**
     * The type of this node.
     *
     * This node is a root node.
     *
     * @access protected
     * @var int
     * @see StringParser::NODE_ROOT
     */
    var $_type = Node::NODE_ROOT;
}

