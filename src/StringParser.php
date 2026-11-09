<?php

namespace StringParser;

/**
 * Generic string parsing infrastructure
 *
 * These classes provide the means to parse any kind of string into a tree-like
 * memory structure. It would e.g. be possible to create an HTML parser based
 * upon this class.
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
 *  marcel.pommer@flane.de 2026-11-09: PHP 8.4 rewrite: declared and typed properties,
 *   no object references, dead loop mode removed, tree-manipulation bugs fixed
 */

use StringParser\Node\Node;
use StringParser\Node\Root;


/**
 * Generic string parser class
 *
 * Base class for string parsers working in search mode: the subclass defines a
 * list of needles per status (setStatus()) and handles each needle found
 * (handleStatus()); text in between is appended via appendText().
 *
 * @package stringparser
 */
class StringParser
{
    /**
     * Parser mode: search for the next needle (the only implemented mode)
     */
    public const MODE_SEARCH = 1;

    /**
     * Parser mode: look at each character; never implemented, parse() returns false
     */
    public const MODE_LOOP = 2;

    /**
     * Filter type: prefilter
     */
    public const FILTER_PRE = 1;

    /**
     * Filter type: postfilter
     */
    public const FILTER_POST = 2;

    /**
     * Strict mode: stop parsing (parse() returns false) on a parse error
     */
    public bool $strict = false;

    /**
     * Parser mode
     */
    protected int $parserMode = self::MODE_SEARCH;

    /**
     * Raw text (after prefilters)
     */
    protected string $text = '';

    /**
     * Parse stack; index 0 is the root node
     *
     * @var list<Node>
     */
    protected array $stack = [];

    /**
     * Current position in the raw text
     */
    protected int $cpos = -1;

    /**
     * Root node
     */
    protected ?Root $root = null;

    /**
     * Output of outputTree(); null means "return the tree"
     */
    protected ?string $output = null;

    /**
     * Guard against recursive parse() calls
     */
    protected bool $parsing = false;

    /**
     * Needles to search for in the current status
     *
     * @var list<string>
     */
    protected array $charactersSearch = [];

    /**
     * Current parser status
     */
    protected int $status = 0;

    /**
     * @var list<callable>
     */
    protected array $prefilters = [];

    /**
     * @var list<callable>
     */
    protected array $postfilters = [];

    /**
     * Set by reparseAfterCurrentBlock() so that the search loop does not advance past the needle
     */
    protected bool $recentlyReparsed = false;


    /**
     * Add a filter
     *
     * @param int $type self::FILTER_PRE or self::FILTER_POST
     * @param mixed $callback callable string => string
     * @return bool false if not callable (in this scope) or the type is unknown
     */
    public function addFilter(int $type, mixed $callback): bool
    {
        if (!is_callable($callback)) {
            return false;
        }
        switch ($type) {
            case self::FILTER_PRE:
                $this->prefilters[] = $callback;
                return true;
            case self::FILTER_POST:
                $this->postfilters[] = $callback;
                return true;
            default:
                return false;
        }
    }


    /**
     * Remove filters
     *
     * @param int $type self::FILTER_PRE, self::FILTER_POST or 0 for all
     * @return bool
     */
    public function clearFilters(int $type = 0): bool
    {
        switch ($type) {
            case 0:
                $this->prefilters = [];
                $this->postfilters = [];
                return true;
            case self::FILTER_PRE:
                $this->prefilters = [];
                return true;
            case self::FILTER_POST:
                $this->postfilters = [];
                return true;
            default:
                return false;
        }
    }


    /**
     * Parse a text
     *
     * @param mixed $text The text to parse; cast to string
     * @return string|Root|false The output, the tree if the parser produces no output,
     *                           false on an internal error, a parse error in strict mode
     *                           or a recursive call
     */
    public function parse(mixed $text): string|Root|false
    {
        if ($this->parsing) {
            return false;
        }
        $this->parsing = true;
        try {
            return $this->_parse((string)$text);
        } finally {
            $this->parsing = false;
            $this->stack = [];
        }
    }


    /**
     * Apply prefilters
     *
     * @param string $text
     * @return string
     */
    protected function applyPrefilters(string $text): string
    {
        return $this->_applyFilters($this->prefilters, $text);
    }


    /**
     * Apply postfilters
     *
     * @param string $text
     * @return string
     */
    protected function applyPostfilters(string $text): string
    {
        return $this->_applyFilters($this->postfilters, $text);
    }


    /**
     * Hook: manipulate the tree after parsing
     *
     * @return bool
     */
    protected function modifyTree(): bool
    {
        return true;
    }


    /**
     * Hook: generate output from the tree into $this->output; leave it null to return the tree
     *
     * @return bool
     */
    protected function outputTree(): bool
    {
        return true;
    }


    /**
     * Restart parsing after the start of the current block
     *
     * Removes the top stack node from the tree, emits the character at which it
     * occurred as text and continues parsing right after that character.
     *
     * @return bool
     */
    protected function reparseAfterCurrentBlock(): bool
    {
        // this should definitely not happen!
        if (count($this->stack) < 2) {
            return false;
        }
        $topelem = $this->stack[count($this->stack) - 1];

        if (!$topelem->_parent->removeChild($topelem, false)) {
            return false;
        }
        if (!$this->popNode()) {
            return false;
        }
        if ($topelem->occurredAt < 0) {
            return false;
        }
        $this->setStatus(0);
        if (!$this->appendText($this->text[$topelem->occurredAt])) {
            return false;
        }

        $this->cpos = $topelem->occurredAt + 1;
        $this->recentlyReparsed = true;

        return true;
    }


    /**
     * Close all blocks still open at the end of the text
     *
     * @return bool
     */
    protected function closeRemainingBlocks(): bool
    {
        if (1 === count($this->stack)) {
            return true;
        }
        if ($this->strict) {
            return false;
        }
        while (count($this->stack) > 1) {
            if (!$this->popNode()) {
                return false;
            }
        }
        return true;
    }


    /**
     * Set a status and its needles
     *
     * @param int $status
     * @return bool
     */
    protected function setStatus(int $status): bool
    {
        if (0 !== $status) {
            return false;
        }
        $this->charactersSearch = [];
        $this->status = $status;
        return true;
    }


    /**
     * Handle a needle found in the current status
     *
     * @param int $status The current status
     * @param string $needle The needle found at $this->cpos
     * @return bool
     */
    protected function handleStatus(int $status, string $needle): bool
    {
        $this->appendText($needle);
        $this->cpos += strlen($needle);
        return true;
    }


    /**
     * Append text depending on the current status
     *
     * @param string $text
     * @return bool
     */
    protected function appendText(string $text): bool
    {
        if ('' === $text) {
            return true;
        }
        return $this->appendToLastTextChild($text);
    }


    /**
     * Append text to the last text child of the top stack node
     *
     * @param string $text
     * @return bool
     */
    protected function appendToLastTextChild(string $text): bool
    {
        $top = $this->_topOfStack();
        if (null === $top) {
            return false;
        }
        return $top->appendToLastTextChild($text);
    }


    /**
     * Append a node to the top stack node and push it onto the stack
     *
     * @param Node $node
     * @return bool
     */
    protected function pushNode(Node $node): bool
    {
        if (!$this->_topOfStack()->appendChild($node)) {
            return false;
        }
        $this->stack[] = $node;
        return true;
    }


    /**
     * Pop the top node from the stack (it stays in the tree)
     *
     * @return bool
     */
    protected function popNode(): bool
    {
        array_pop($this->stack);
        return true;
    }


    /**
     * Call a method on the top stack node
     *
     * @param string $method
     * @param mixed ...$args
     * @return mixed null if the method does not exist
     */
    protected function topNode(string $method, mixed ...$args): mixed
    {
        $top = $this->_topOfStack();
        if ((null === $top) || !is_callable([$top, $method])) {
            return null;
        }
        return $top->$method(...$args);
    }


    /**
     * Read a property of the top stack node
     *
     * @param string $var
     * @return mixed
     */
    protected function topNodeVar(string $var): mixed
    {
        return $this->_topOfStack()?->$var;
    }


    /**
     * @param string $text
     * @return string|Root|false
     */
    private function _parse(string $text): string|Root|false
    {
        $this->text = $this->applyPrefilters($text);
        $this->output = null;
        $this->cpos = 0;
        if (null !== $this->root) {
            Node::destroyNode($this->root);
        }
        $this->root = new Root();
        $this->stack = [$this->root];

        $this->setStatus(0);

        while (true) {
            if (self::MODE_SEARCH !== $this->parserMode) {
                return false;
            }
            if (!$this->_searchLoop()) {
                return false;
            }
            if ($this->closeRemainingBlocks()) {
                break;
            }
            if ($this->strict || !$this->reparseAfterCurrentBlock()) {
                return false;
            }
        }

        if (!$this->modifyTree() || !$this->outputTree()) {
            return false;
        }

        $root = $this->root;
        $this->root = null;

        if (null === $this->output) {
            return $root;
        }
        if (!Node::destroyNode($root)) {
            return false;
        }
        return $this->output;
    }


    /**
     * Search mode loop
     *
     * @return bool
     */
    private function _searchLoop(): bool
    {
        while (true) {
            $this->recentlyReparsed = false;

            [$needle, $offset] = $this->_strpos($this->charactersSearch, $this->cpos);

            if (null === $needle) {
                // original status 0 => no problem
                if (0 === $this->status) {
                    break;
                }
                if ($this->strict) {
                    return false;
                }
                // break up parsing operation of the current node
                if (!$this->reparseAfterCurrentBlock()) {
                    return false;
                }
                continue;
            }

            if (!$this->appendText(substr($this->text, $this->cpos, $offset - $this->cpos))) {
                return false;
            }
            $this->cpos = $offset;

            if (!$this->handleStatus($this->status, $needle)) {
                if ($this->strict) {
                    return false;
                }
                if (!$this->appendText($this->text[$this->cpos])) {
                    return false;
                }
                $this->cpos++;
                continue;
            }
            if ($this->recentlyReparsed) {
                $this->recentlyReparsed = false;
                continue;
            }
            $this->cpos += strlen($needle);
        }

        if ($this->cpos < strlen($this->text)) {
            return $this->appendText(substr($this->text, $this->cpos));
        }
        return true;
    }


    /**
     * Find the needle occurring first at or after $offset; on equal offsets the earlier needle in the list wins
     *
     * @param list<string> $needles
     * @param int $offset
     * @return array{0: string|null, 1: int}
     */
    private function _strpos(array $needles, int $offset): array
    {
        $curNeedle = null;
        $curOffset = -1;

        if ($offset < strlen($this->text)) {
            foreach ($needles as $needle) {
                $nOffset = strpos($this->text, $needle, $offset);
                if ((false !== $nOffset) && (($curOffset < 0) || ($nOffset < $curOffset))) {
                    $curNeedle = $needle;
                    $curOffset = $nOffset;
                }
            }
        }

        return [$curNeedle, $curOffset];
    }


    /**
     * @param list<callable> $filters
     * @param string $text
     * @return string
     */
    private function _applyFilters(array $filters, string $text): string
    {
        foreach ($filters as $filter) {
            $ntext = $filter($text);
            if (is_string($ntext)) {
                $text = $ntext;
            }
        }
        return $text;
    }


    /**
     * @return Node|null
     */
    private function _topOfStack(): ?Node
    {
        return $this->stack[count($this->stack) - 1] ?? null;
    }
}
