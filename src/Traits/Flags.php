<?php

namespace StringParser\Traits;

/**
 * Generic string parsing infrastructure: node flags
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


/**
 * Named flags of a node (shared by text and BBCode element nodes)
 *
 * @package stringparser
 */
trait Flags
{
    /**
     * Node flags
     *
     * public: BBCode copies flags between nodes when breaking up paragraphs
     *
     * @var array<string, mixed>
     */
    public array $flags = [];


    /**
     * Get a flag
     *
     * @param string $flag The requested flag
     * @param string $type The requested type of the return value (settype() type name or 'mixed')
     * @param mixed $default Returned if the flag is not set or null
     * @return mixed
     */
    public function getFlag(string $flag, string $type = 'mixed', mixed $default = null): mixed
    {
        if (!isset($this->flags[$flag])) {
            return $default;
        }
        $return = $this->flags[$flag];
        if ('mixed' !== $type) {
            settype($return, $type);
        }
        return $return;
    }


    /**
     * Set a flag
     *
     * @param string $name The name of the flag
     * @param mixed $value The value of the flag
     * @return true
     */
    public function setFlag(string $name, mixed $value): bool
    {
        $this->flags[$name] = $value;
        return true;
    }
}
