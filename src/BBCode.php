<?php

namespace StringParser;

/**
 * BB code string parsing class
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

use StringParser\BBCode\Node\Element;
use StringParser\BBCode\Node\Paragraph;
use StringParser\Node\Node;
use StringParser\Node\Text as TextNode;


/**
 * BB code string parser class
 *
 * Parser statuses:
 *  0: normal text
 *  1: open tag name
 *  2: close tag name
 *  3: default attribute value
 *  4: attribute name
 *  5: attribute value
 *  7: raw content of a usecontent code, up to its close tag
 *
 * Callbacks are called as ($action, $attributes, $content, $params, $node) with
 * $action 'validate' (while parsing), 'validate_again' (setValidateAgain(true))
 * or 'output' (after parsing, children before parents, in document order).
 *
 * @package stringparser
 */
class BBCode extends StringParser
{
    public const CLOSETAG_FORBIDDEN = -1;
    public const CLOSETAG_OPTIONAL = 0;
    public const CLOSETAG_IMPLICIT = 1;
    public const CLOSETAG_IMPLICIT_ON_CLOSE_ONLY = 2;
    public const CLOSETAG_MUSTEXIST = 3;

    public const NEWLINE_PARSE = 0;
    public const NEWLINE_IGNORE = 1;
    public const NEWLINE_DROP = 2;

    public const PARAGRAPH_ALLOW_BREAKUP = 0;
    public const PARAGRAPH_ALLOW_INSIDE = 1;
    public const PARAGRAPH_BLOCK_ELEMENT = 2;

    /**
     * Node type: BBCode element node
     */
    public const NODE_ELEMENT = 32;

    /**
     * Node type: BBCode paragraph node
     */
    public const NODE_PARAGRAPH = 33;

    /**
     * Registered codes
     *
     * @var array<string, array{name: string, callback_type: string, callback_func: mixed, callback_params: array|null, content_type: string, allowed_within: list<string>, not_allowed_within: list<string>, flags: array<string, mixed>}>
     */
    protected array $codes = [];

    /**
     * Registered text parsers per content type
     *
     * @var array<string, list<callable>>
     */
    protected array $parsers = [];

    /**
     * Maximum occurrences per occurrence type
     *
     * @var array<string, int>
     */
    protected array $maxOccurrences = [];

    /**
     * Root content type
     */
    protected string $rootContentType = 'block';

    /**
     * Do not output but return the tree
     */
    protected bool $noOutput = false;

    /**
     * Global setting: case sensitive
     */
    protected bool $caseSensitive = true;

    /**
     * Root paragraph handling enabled
     */
    protected bool $rootParagraphHandling = false;

    /**
     * Paragraph handling parameters
     *
     * @var array{detect_string: string, start_tag: string, end_tag: string}
     */
    protected array $paragraphHandling = [
        'detect_string' => "\n\n",
        'start_tag' => '<p>',
        'end_tag' => "</p>\n",
    ];

    /**
     * Allow mixed attribute types (e.g. [code=bla attr=blub])
     */
    protected bool $mixedAttributeTypes = false;

    /**
     * Call validation again (with $action == 'validate_again') when the close tag comes
     */
    protected bool $validateAgain = false;

    /**
     * Close tag name (status 2) or attribute name (status 4/5) collected so far
     */
    private string $_savedName = '';

    /**
     * Attribute value collected so far (status 5)
     */
    private string $_savedValue = '';

    /**
     * Quoting character of the current attribute value, null if unquoted
     */
    private ?string $_quoting = null;

    /**
     * Close count of a usecontent element while its raw content is read (status 7)
     */
    private int $_savedCloseCount = 0;


    /**
     * Add a code
     *
     * @param string $name Name of the code: [a-zA-Z0-9*_!+-]+
     * @param string $callback_type simple_replace, simple_replace_single, callback_replace,
     *                              callback_replace_single, callback_replace?, usecontent, usecontent?
     * @param mixed $callback_func callable; unused for simple_replace*
     * @param array|null $callback_params start_tag/end_tag for simple_replace*, else passed to the callback
     * @param string $content_type
     * @param list<string> $allowed_within Content types this code may appear in
     * @param list<string> $not_allowed_within Content types this code may not appear in at any depth
     * @return bool false if the name exists or is invalid
     */
    public function addCode(string $name, string $callback_type, mixed $callback_func, ?array $callback_params, string $content_type, array $allowed_within, array $not_allowed_within): bool
    {
        if (isset($this->codes[$name])) {
            return false;
        }
        if (!preg_match('/^[a-zA-Z0-9*_!+-]+$/', $name)) {
            return false;
        }
        $this->codes[$name] = [
            'name' => $name,
            'callback_type' => $callback_type,
            'callback_func' => $callback_func,
            'callback_params' => $callback_params,
            'content_type' => $content_type,
            'allowed_within' => $allowed_within,
            'not_allowed_within' => $not_allowed_within,
            'flags' => [],
        ];
        return true;
    }


    /**
     * @param string $name
     * @return bool
     */
    public function removeCode(string $name): bool
    {
        if (!isset($this->codes[$name])) {
            return false;
        }
        unset($this->codes[$name]);
        return true;
    }


    /**
     * @return void
     */
    public function removeAllCodes(): void
    {
        $this->codes = [];
    }


    /**
     * Set a code flag; unknown flag names are stored and ignored
     *
     * @param string $name
     * @param string $flag
     * @param mixed $value
     * @return bool
     */
    public function setCodeFlag(string $name, string $flag, mixed $value): bool
    {
        if (!isset($this->codes[$name])) {
            return false;
        }
        $this->codes[$name]['flags'][$flag] = $value;
        return true;
    }


    /**
     * Set occurrence type; together with setMaxOccurrences() limits how often codes may occur:
     *   $bbcode->setOccurrenceType('url', 'link');
     *   $bbcode->setMaxOccurrences('link', 4);
     *
     * @param string $code
     * @param string $type
     * @return bool
     */
    public function setOccurrenceType(string $code, string $type): bool
    {
        return $this->setCodeFlag($code, 'occurrence_type', $type);
    }


    /**
     * @param string $type Occurrence type
     * @param int $count
     * @return bool
     */
    public function setMaxOccurrences(string $type, int $count): bool
    {
        if ($count < 0) {
            return false;
        }
        $this->maxOccurrences[$type] = $count;
        return true;
    }


    /**
     * Add a text parser for one or more content types
     *
     * The parser is checked for callability when it is applied, so a function
     * defined later is fine.
     *
     * @param string|list<string> $type
     * @param mixed $parser callable string => string
     * @return true
     */
    public function addParser(string|array $type, mixed $parser): bool
    {
        foreach ((array)$type as $t) {
            $this->parsers[$t][] = $parser;
        }
        return true;
    }


    /**
     * @param string $content_type
     * @return void
     */
    public function setRootContentType(string $content_type): void
    {
        $this->rootContentType = $content_type;
    }


    /**
     * @param mixed $enabled cast to bool
     * @return void
     */
    public function setRootParagraphHandling(mixed $enabled): void
    {
        $this->rootParagraphHandling = (bool)$enabled;
    }


    /**
     * @param string $detect_string
     * @param string $start_tag e.g. <p>
     * @param string $end_tag e.g. </p>
     * @return void
     */
    public function setParagraphHandlingParameters(string $detect_string, string $start_tag, string $end_tag): void
    {
        $this->paragraphHandling = [
            'detect_string' => $detect_string,
            'start_tag' => $start_tag,
            'end_tag' => $end_tag,
        ];
    }


    /**
     * If true, codes are case sensitive unless their case_sensitive flag is false;
     * if false, all codes are case insensitive.
     *
     * @param mixed $caseSensitive cast to bool
     * @return void
     */
    public function setGlobalCaseSensitive(mixed $caseSensitive): void
    {
        $this->caseSensitive = (bool)$caseSensitive;
    }


    /**
     * @return bool
     */
    public function globalCaseSensitive(): bool
    {
        return $this->caseSensitive;
    }


    /**
     * If set, [code=val1 attr=val2] yields the attributes default=val1 and attr=val2;
     * if not set, only default='val1 attr=val2'.
     *
     * @param mixed $mixedAttributeTypes cast to bool
     * @return void
     */
    public function setMixedAttributeTypes(mixed $mixedAttributeTypes): void
    {
        $this->mixedAttributeTypes = (bool)$mixedAttributeTypes;
    }


    /**
     * @return bool
     */
    public function mixedAttributeTypes(): bool
    {
        return $this->mixedAttributeTypes;
    }


    /**
     * @param mixed $validateAgain cast to bool
     * @return void
     */
    public function setValidateAgain(mixed $validateAgain): void
    {
        $this->validateAgain = (bool)$validateAgain;
    }


    /**
     * @return bool
     */
    public function validateAgain(): bool
    {
        return $this->validateAgain;
    }


    /**
     * @param string $name Code name
     * @param string $flag
     * @param string $type settype() type name or 'mixed'
     * @param mixed $default
     * @return mixed
     */
    public function getCodeFlag(string $name, string $flag, string $type = 'mixed', mixed $default = null): mixed
    {
        if (!isset($this->codes[$name]) || !array_key_exists($flag, $this->codes[$name]['flags'])) {
            return $default;
        }
        $return = $this->codes[$name]['flags'][$flag];
        if ('mixed' !== $type) {
            settype($return, $type);
        }
        return $return;
    }


    /**
     * @param int $status
     * @return bool
     */
    protected function setStatus(int $status): bool
    {
        switch ($status) {
            case 0:
                $this->charactersSearch = ['[/', '['];
                break;
            case 1:
                $this->charactersSearch = [']', ' = "', '="', ' = \'', '=\'', ' = ', '=', ': ', ':', ' '];
                break;
            case 2:
                $this->charactersSearch = [']'];
                $this->_savedName = '';
                break;
            case 3:
                if (null !== $this->_quoting) {
                    $this->charactersSearch = $this->mixedAttributeTypes
                        ? ['\\\\', '\\' . $this->_quoting, $this->_quoting . ' ', $this->_quoting . ']', $this->_quoting]
                        : ['\\\\', '\\' . $this->_quoting, $this->_quoting . ']', $this->_quoting];
                } else {
                    $this->charactersSearch = $this->mixedAttributeTypes ? [' ', ']'] : [']'];
                }
                break;
            case 4:
                $this->charactersSearch = [' ', ']', '="', '=\'', '='];
                $this->_savedName = '';
                $this->_savedValue = '';
                break;
            case 5:
                $this->charactersSearch = (null !== $this->_quoting)
                    ? ['\\\\', '\\' . $this->_quoting, $this->_quoting . ' ', $this->_quoting . ']', $this->_quoting]
                    : [' ', ']'];
                $this->_savedValue = '';
                break;
            case 7:
                $this->charactersSearch = ['[/' . $this->topNode('name') . ']'];
                if (!$this->topNode('getFlag', 'case_sensitive', 'boolean', true) || !$this->caseSensitive) {
                    $this->charactersSearch[] = '[/';
                }
                break;
            default:
                return false;
        }
        $this->status = $status;
        return true;
    }


    /**
     * @param string $text
     * @return bool
     */
    protected function appendText(string $text): bool
    {
        if ('' === $text) {
            return true;
        }
        switch ($this->status) {
            case 0:
            case 7:
                return $this->appendToLastTextChild($text);
            case 1:
                return $this->topNode('appendToName', $text);
            case 2:
            case 4:
                $this->_savedName .= $text;
                return true;
            case 3:
                return $this->topNode('appendToAttribute', 'default', $text);
            case 5:
                $this->_savedValue .= $text;
                return true;
            default:
                return false;
        }
    }


    /**
     * @return bool
     */
    protected function reparseAfterCurrentBlock(): bool
    {
        if (2 !== $this->status) {
            return parent::reparseAfterCurrentBlock();
        }
        // status 2 never calls reparseAfterCurrentBlock() itself, so this happens
        // when the text ends inside a close tag: emit the '[/' as text
        $this->cpos -= strlen($this->_savedName);
        $this->_savedName = '';
        $this->status = 0;
        $this->appendText('[/');
        return true;
    }


    /**
     * @param int $status
     * @param string $needle
     * @return bool
     */
    protected function handleStatus(int $status, string $needle): bool
    {
        switch ($status) {
            case 0:
                return $this->_handleText($needle);
            case 1:
                return $this->_handleOpenTag($needle);
            case 2:
                return $this->_handleCloseTag($needle);
            case 3:
                return $this->_handleDefaultAttribute($needle);
            case 4:
                return $this->_handleAttributeName($needle);
            case 5:
                return $this->_handleAttributeValue($needle);
            case 7:
                return $this->_handleUseContent($needle);
            default:
                return false;
        }
    }


    /**
     * Close all blocks still open at the end of the text; fails on a CLOSETAG_MUSTEXIST element
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
            if (self::CLOSETAG_MUSTEXIST === $this->topNode('getFlag', 'closetag', 'integer', self::CLOSETAG_IMPLICIT)) {
                return false;
            }
            if (!$this->popNode()) {
                return false;
            }
        }
        return true;
    }


    /**
     * @return bool
     */
    protected function outputTree(): bool
    {
        if ($this->noOutput) {
            return true;
        }
        $output = $this->_outputNode($this->root);
        if (!is_string($output)) {
            return false;
        }
        $this->output = $this->applyPostfilters($output);
        return true;
    }


    /**
     * Newline handling, then paragraph handling, then removal of empty paragraphs
     *
     * @return bool
     */
    protected function modifyTree(): bool
    {
        // first pass: newline handling
        foreach ($this->root->getNodesByCriterium('needsTextNodeModification', true) as $node) {
            $this->_setNewlineMode($node->getFlag('opentag.before.newline', 'integer', self::NEWLINE_PARSE), $node->findPrevAdjentTextNode(), 'newlinemode.end');
            $this->_setNewlineMode($node->getFlag('opentag.after.newline', 'integer', self::NEWLINE_PARSE), $node->firstChildIfText(), 'newlinemode.begin');
            $this->_setNewlineMode($node->getFlag('closetag.before.newline', 'integer', self::NEWLINE_PARSE), $node->lastChildIfText(), 'newlinemode.end');
            $this->_setNewlineMode($node->getFlag('closetag.after.newline', 'integer', self::NEWLINE_PARSE), $node->findNextAdjentTextNode(), 'newlinemode.begin');
        }

        // second pass a: paragraph handling on the root element
        if ($this->rootParagraphHandling && !$this->_handleParagraphs($this->root)) {
            return false;
        }

        // second pass b: paragraph handling on elements with the paragraphs flag
        foreach ($this->root->getNodesByCriterium('flag:paragraphs', true) as $node) {
            if (!$this->_handleParagraphs($node)) {
                return false;
            }
        }

        // second pass c: remove empty paragraphs
        foreach ($this->root->getNodesByCriterium('empty', true) as $node) {
            if ((self::NODE_PARAGRAPH === $node->_type) && (null !== $node->_parent)) {
                $node->_parent->removeChild($node, true);
            }
        }

        return true;
    }


    /**
     * Status 0: normal text
     *
     * @param string $needle
     * @return bool
     */
    private function _handleText(string $needle): bool
    {
        if ('[' === $needle) {
            if (!$this->pushNode(new Element($this->cpos))) {
                return false;
            }
            $this->setStatus(1);
            return true;
        }
        if ('[/' === $needle) {
            if (count($this->stack) <= 1) {
                $this->appendText($needle);
                return true;
            }
            $this->setStatus(2);
            return true;
        }
        $this->appendText($needle);
        return true;
    }


    /**
     * Status 1: open tag name
     *
     * @param string $needle
     * @return bool
     */
    private function _handleOpenTag(string $needle): bool
    {
        if (']' === $needle) {
            return $this->_openElement(0);
        }
        $trimmed = trim($needle);
        if ((':' === $trimmed) || ('=' === $trimmed)) {
            $this->_quoting = null;
            $this->setStatus(3);
            return true;
        }
        if (in_array($trimmed, ['="', '= "', '=\'', '= \''], true)) {
            $this->_quoting = substr($trimmed, -1);
            $this->setStatus(3);
            return true;
        }
        if (' ' === $needle) {
            $this->setStatus(4);
            return true;
        }
        $this->appendText($needle);
        return true;
    }


    /**
     * Status 2: close tag name
     *
     * @param string $needle
     * @return bool
     */
    private function _handleCloseTag(string $needle): bool
    {
        if (']' !== $needle) {
            $this->appendText($needle);
            return true;
        }
        $closecount = 0;
        if (!$this->_isCloseable($this->_savedName, $closecount)) {
            $this->setStatus(0);
            $this->appendText('[/' . $this->_savedName . $needle);
            return true;
        }
        // revalidate the codes to be closed now that their content trees are built;
        // reparseAfterCurrentBlock() does not work in status 2, hence status 0 first
        if (!$this->_validateCloseTags($closecount)) {
            $this->setStatus(0);
            return $this->reparseAfterCurrentBlock();
        }
        $this->setStatus(0);
        for ($i = 0; $i < $closecount; $i++) {
            if ($i === ($closecount - 1)) {
                $this->topNode('setHadCloseTag');
            }
            if (!$this->popNode()) {
                return false;
            }
        }
        return true;
    }


    /**
     * Status 3: default attribute value
     *
     * @param string $needle
     * @return bool
     */
    private function _handleDefaultAttribute(string $needle): bool
    {
        if (null !== $this->_quoting) {
            switch ($needle) {
                case '\\\\':
                    $this->appendText('\\');
                    return true;
                case '\\' . $this->_quoting:
                    $this->appendText($this->_quoting);
                    return true;
                case $this->_quoting . ' ':
                    $this->setStatus(4);
                    return true;
                case $this->_quoting . ']':
                    return $this->_openElement(2);
                case $this->_quoting:
                    // only ']' and ' ' are allowed after the quoting character
                    return $this->reparseAfterCurrentBlock();
                default:
                    $this->appendText($needle);
                    return true;
            }
        }
        if (' ' === $needle) {
            $this->setStatus(4);
            return true;
        }
        if (']' === $needle) {
            return $this->_openElement(2);
        }
        $this->appendText($needle);
        return true;
    }


    /**
     * Status 4: attribute name
     *
     * @param string $needle
     * @return bool
     */
    private function _handleAttributeName(string $needle): bool
    {
        switch ($needle) {
            case ' ':
                if ('' !== $this->_savedName) {
                    $this->topNode('setAttribute', $this->_savedName, true);
                }
                // continue in the same status with reset parameters
                $this->setStatus(4);
                return true;
            case ']':
                if ('' !== $this->_savedName) {
                    $this->topNode('setAttribute', $this->_savedName, true);
                }
                return $this->_openElement(2);
            case '=':
                $this->_quoting = null;
                $this->setStatus(5);
                return true;
            case '="':
                $this->_quoting = '"';
                $this->setStatus(5);
                return true;
            case '=\'':
                $this->_quoting = '\'';
                $this->setStatus(5);
                return true;
            default:
                $this->appendText($needle);
                return true;
        }
    }


    /**
     * Status 5: attribute value
     *
     * @param string $needle
     * @return bool
     */
    private function _handleAttributeValue(string $needle): bool
    {
        if (null !== $this->_quoting) {
            switch ($needle) {
                case '\\\\':
                    $this->appendText('\\');
                    return true;
                case '\\' . $this->_quoting:
                    $this->appendText($this->_quoting);
                    return true;
                case $this->_quoting . ' ':
                    $this->topNode('setAttribute', $this->_savedName, $this->_savedValue);
                    $this->setStatus(4);
                    return true;
                case $this->_quoting . ']':
                    $this->topNode('setAttribute', $this->_savedName, $this->_savedValue);
                    return $this->_openElement(2);
                case $this->_quoting:
                    // only ']' and ' ' are allowed after the quoting character
                    return $this->reparseAfterCurrentBlock();
                default:
                    $this->appendText($needle);
                    return true;
            }
        }
        if (' ' === $needle) {
            $this->topNode('setAttribute', $this->_savedName, $this->_savedValue);
            $this->setStatus(4);
            return true;
        }
        if (']' === $needle) {
            $this->topNode('setAttribute', $this->_savedName, $this->_savedValue);
            return $this->_openElement(2);
        }
        $this->appendText($needle);
        return true;
    }


    /**
     * Status 7: raw content of a usecontent code; the needle is its close tag or (case insensitive) '[/'
     *
     * @param string $needle
     * @return bool
     */
    private function _handleUseContent(string $needle): bool
    {
        if ('[/' === $needle) {
            $name = $this->topNode('name');
            if (strtolower(substr($this->text, $this->cpos + strlen($needle), strlen($name) + 1)) !== strtolower($name . ']')) {
                $this->appendText($needle);
                return true;
            }
            // this matched case insensitively
            $this->cpos += strlen($name) + 1;
        }
        if (!$this->topNode('validate')) {
            return $this->reparseAfterCurrentBlock();
        }
        if (!$this->_closeSubnodes($this->_savedCloseCount)) {
            return false;
        }
        $this->setStatus(0);
        $this->popNode();
        return true;
    }


    /**
     * Open the element on top of the stack once its open tag is complete
     *
     * @param int $type 0: tag without attributes, 2: tag with attributes
     * @return bool
     */
    private function _openElement(int $type = 0): bool
    {
        $name = $this->_getCanonicalName($this->topNode('name'));
        if (false === $name) {
            return $this->reparseAfterCurrentBlock();
        }
        $occType = $this->getCodeFlag($name, 'occurrence_type', 'string');
        if ((null !== $occType) && isset($this->maxOccurrences[$occType])) {
            $occs = $this->root->getNodeCountByCriterium('flag:occurrence_type', $occType);
            if ($occs >= $this->maxOccurrences[$occType]) {
                return $this->reparseAfterCurrentBlock();
            }
        }
        $closecount = 0;
        $this->topNode('setCodeInfo', $this->codes[$name]);
        if (!$this->_isOpenable($name, $closecount)) {
            return $this->reparseAfterCurrentBlock();
        }
        $this->setStatus(0);
        $useContent = match ($type) {
            0 => $this->_isUseContent($this->stack[count($this->stack) - 1], false),
            1, 2 => $this->_isUseContent($this->stack[count($this->stack) - 1], true),
            default => false,
        };
        if ($useContent) {
            $this->_savedCloseCount = $closecount;
            $this->setStatus(7);
            return true;
        }
        if (!$this->topNode('validate')) {
            return $this->reparseAfterCurrentBlock();
        }
        if (!$this->_closeSubnodes($closecount)) {
            return false;
        }
        $callbackType = $this->codes[$name]['callback_type'];
        if (('simple_replace_single' === $callbackType) || ('callback_replace_single' === $callbackType)) {
            if (!$this->popNode()) {
                return false;
            }
        }
        return true;
    }


    /**
     * Implicitly close $closecount elements below the top node and re-attach the top node
     * to the new top of the stack
     *
     * @param int $closecount
     * @return bool
     */
    private function _closeSubnodes(int $closecount): bool
    {
        if (!$closecount) {
            return true;
        }
        $mynode = $this->stack[count($this->stack) - 1];
        for ($i = 0; $i <= $closecount; $i++) {
            if (!$this->popNode()) {
                return false;
            }
        }
        return $this->pushNode($mynode);
    }


    /**
     * Whether a close tag closes an open element; counts the elements to pop
     *
     * @param string $name
     * @param int $closecount
     * @return bool
     */
    private function _isCloseable(string $name, int &$closecount): bool
    {
        $node = $this->_findNamedNode($name, false);
        if (null === $node) {
            return false;
        }
        for ($i = count($this->stack) - 1; $i > 0; $i--) {
            $closecount++;
            if ($this->stack[$i]->equals($node)) {
                return true;
            }
            if (self::CLOSETAG_MUSTEXIST === $this->stack[$i]->getFlag('closetag', 'integer', self::CLOSETAG_IMPLICIT)) {
                return false;
            }
        }
        return false;
    }


    /**
     * Revalidate the codes to be closed when their close tag appears (setValidateAgain(true) only)
     *
     * @param int $closecount
     * @return bool
     */
    private function _validateCloseTags(int $closecount): bool
    {
        if (!$this->validateAgain) {
            return true;
        }
        $scount = count($this->stack);
        for ($i = $scount - 1; $i >= ($scount - $closecount); $i--) {
            if (!$this->stack[$i]->validate('validate_again')) {
                return false;
            }
        }
        return true;
    }


    /**
     * Whether the element on top of the stack may be opened at this position
     *
     * @param string $name Canonical code name
     * @param int $closecount
     * @return bool
     */
    private function _isOpenable(string $name, int &$closecount): bool
    {
        if (!isset($this->codes[$name])) {
            return false;
        }

        $closecount = 0;

        $allowedWithin = $this->codes[$name]['allowed_within'];
        $notAllowedWithin = $this->codes[$name]['not_allowed_within'];

        $scount = count($this->stack);
        if (2 === $scount) {
            // top level element
            if (!in_array($this->rootContentType, $allowedWithin)) {
                return false;
            }
        } elseif (!in_array($this->stack[$scount - 2]->codeInfo['content_type'], $allowedWithin)) {
            return $this->_isOpenableWithClose($name, $closecount);
        }

        for ($i = 1; $i < ($scount - 1); $i++) {
            if (in_array($this->stack[$i]->codeInfo['content_type'], $notAllowedWithin)) {
                return $this->_isOpenableWithClose($name, $closecount);
            }
        }

        return true;
    }


    /**
     * Whether the element on top of the stack may be opened by implicitly closing other elements
     *
     * @param string $name
     * @param int $closecount
     * @return bool
     */
    private function _isOpenableWithClose(string $name, int &$closecount): bool
    {
        $tnname = $this->_getCanonicalName($this->topNode('name'));
        $closetag = (false === $tnname) ? self::CLOSETAG_IMPLICIT : $this->getCodeFlag($tnname, 'closetag', 'integer', self::CLOSETAG_IMPLICIT);
        if (!in_array($closetag, [self::CLOSETAG_FORBIDDEN, self::CLOSETAG_OPTIONAL], true)) {
            return false;
        }
        $node = $this->_findNamedNode($name, true);
        if (null === $node) {
            return false;
        }
        $scount = count($this->stack);
        if ($scount < 3) {
            return false;
        }
        for ($i = $scount - 2; $i > 0; $i--) {
            $closecount++;
            if ($this->stack[$i]->equals($node)) {
                return true;
            }
            if (in_array($this->stack[$i]->getFlag('closetag', 'integer', self::CLOSETAG_IMPLICIT), [self::CLOSETAG_IMPLICIT_ON_CLOSE_ONLY, self::CLOSETAG_MUSTEXIST], true)) {
                return false;
            }
            if ($this->validateAgain && !$this->stack[$i]->validate('validate_again')) {
                return false;
            }
        }

        return false;
    }


    /**
     * Find the topmost open element with a given name
     *
     * @param string $name
     * @param bool $searchdeeper Skip the top of the stack
     * @return Element|null
     */
    private function _findNamedNode(string $name, bool $searchdeeper = false): ?Element
    {
        $lname = $this->_getCanonicalName($name);
        if (false === $lname) {
            return null;
        }
        $caseSensitive = $this->caseSensitive && $this->getCodeFlag($lname, 'case_sensitive', 'boolean', true);
        $scount = count($this->stack);
        if ($searchdeeper) {
            $scount--;
        }
        for ($i = $scount - 1; $i > 0; $i--) {
            $cmpName = $caseSensitive ? $this->stack[$i]->name() : strtolower($this->stack[$i]->name());
            if ($cmpName == $lname) {
                return $this->stack[$i];
            }
        }
        return null;
    }


    /**
     * Apply the text parsers of a content type
     *
     * @param string $type
     * @param string $text
     * @return string
     */
    private function _applyParsers(string $type, string $text): string
    {
        foreach ($this->parsers[$type] ?? [] as $parser) {
            if (!is_callable($parser)) {
                continue;
            }
            $ntext = call_user_func($parser, $text);
            if (is_string($ntext)) {
                $text = $ntext;
            }
        }
        return $text;
    }


    /**
     * @param int $mode NEWLINE_* mode
     * @param Node|null $textNode
     * @param string $flag
     * @return void
     */
    private function _setNewlineMode(int $mode, ?Node $textNode, string $flag): void
    {
        if ((self::NEWLINE_PARSE !== $mode) && (null !== $textNode)) {
            $textNode->setFlag($flag, $mode);
        }
    }


    /**
     * Output a node
     *
     * @param Node $node
     * @return string|false
     */
    private function _outputNode(Node $node): string|false
    {
        if (Node::NODE_TEXT === $node->_type) {
            return $this->_outputTextNode($node);
        }
        if (!in_array($node->_type, [self::NODE_PARAGRAPH, self::NODE_ELEMENT, Node::NODE_ROOT], true)) {
            return false;
        }
        $output = '';
        foreach ($node->children as $child) {
            $suboutput = $this->_outputNode($child);
            if (false === $suboutput) {
                return false;
            }
            $output .= $suboutput;
        }
        if (self::NODE_PARAGRAPH === $node->_type) {
            return $this->paragraphHandling['start_tag'] . $output . $this->paragraphHandling['end_tag'];
        }
        if (self::NODE_ELEMENT === $node->_type) {
            $replacement = $node->getReplacement($output);
            return is_string($replacement) ? $replacement : false;
        }
        return $output;
    }


    /**
     * Output a text node: newline handling, then the parsers of the enclosing content type
     *
     * @param TextNode $node
     * @return string
     */
    private function _outputTextNode(TextNode $node): string
    {
        $output = $node->content;
        $before = '';
        $after = '';

        switch ($node->getFlag('newlinemode.begin', 'integer', self::NEWLINE_PARSE)) {
            case self::NEWLINE_IGNORE:
                if (str_starts_with($output, "\n")) {
                    $before = "\n";
                }
                // fall through
            case self::NEWLINE_DROP:
                if (str_starts_with($output, "\n")) {
                    $output = substr($output, 1);
                }
                break;
        }
        switch ($node->getFlag('newlinemode.end', 'integer', self::NEWLINE_PARSE)) {
            case self::NEWLINE_IGNORE:
                if (str_ends_with($output, "\n")) {
                    $after = "\n";
                }
                // fall through
            case self::NEWLINE_DROP:
                if (str_ends_with($output, "\n")) {
                    $output = substr($output, 0, -1);
                }
                break;
        }

        // the content type is determined by the enclosing element, skipping a paragraph
        $container = $node->_parent;
        if ((null !== $container) && (self::NODE_PARAGRAPH === $container->_type)) {
            $container = $container->_parent;
        }
        if (null === $container) {
            return $before . $output . $after;
        }
        if (Node::NODE_ROOT === $container->_type) {
            return $before . $this->_applyParsers($this->rootContentType, $output) . $after;
        }
        if (self::NODE_ELEMENT === $container->_type) {
            return $before . $this->_applyParsers($container->codeInfo['content_type'], $output) . $after;
        }
        return $before . $output . $after;
    }


    /**
     * Distribute the children of a node into paragraphs
     *
     * @param Node $node
     * @return bool
     */
    private function _handleParagraphs(Node $node): bool
    {
        // no paragraph handling inside paragraphs
        if ($this->_hasParagraphAncestor($node)) {
            return true;
        }
        $destNodes = [];
        $lastNodeWasParagraph = false;
        $prevtype = Node::NODE_TEXT;
        $paragraph = null;
        while (count($node->children)) {
            $mynode = $node->children[0];
            $node->removeChild($mynode);
            $subNodes = $this->_breakupNodeByParagraphs($mynode);
            foreach ($subNodes as $i => $subNode) {
                if (
                    !$lastNodeWasParagraph
                    || (($prevtype === $subNode->_type) && ((0 !== $i) || (self::NODE_ELEMENT !== $prevtype)))
                ) {
                    $paragraph = new Paragraph();
                }
                $prevtype = $subNode->_type;
                if ((self::NODE_ELEMENT !== $subNode->_type) || (self::PARAGRAPH_BLOCK_ELEMENT !== $subNode->getFlag('paragraph_type', 'integer', self::PARAGRAPH_ALLOW_BREAKUP))) {
                    $paragraph->appendChild($subNode);
                    $destNodes[] = $paragraph;
                    $lastNodeWasParagraph = true;
                } else {
                    $destNodes[] = $subNode;
                    $lastNodeWasParagraph = false;
                    $paragraph = new Paragraph();
                }
            }
        }
        foreach ($destNodes as $destNode) {
            $node->appendChild($destNode);
        }
        return true;
    }


    /**
     * @param Node $node
     * @return bool
     */
    private function _hasParagraphAncestor(Node $node): bool
    {
        for ($parent = $node->_parent; null !== $parent; $parent = $parent->_parent) {
            if (self::NODE_PARAGRAPH === $parent->_type) {
                return true;
            }
        }
        return false;
    }


    /**
     * Break up a node at paragraph boundaries (detect_string) into a list of nodes
     *
     * @param Node $node
     * @return list<Node>
     */
    private function _breakupNodeByParagraphs(Node $node): array
    {
        $detectString = $this->paragraphHandling['detect_string'];
        $destNodes = [];

        if (Node::NODE_TEXT === $node->_type) {
            $cpos = 0;
            while (false !== ($npos = strpos($node->content, $detectString, $cpos))) {
                $subnode = new TextNode(substr($node->content, $cpos, $npos - $cpos), $node->occurredAt + $cpos);
                foreach ($node->flags as $flag => $value) {
                    if ('newlinemode.begin' === $flag) {
                        if (0 === $cpos) {
                            $subnode->setFlag($flag, $value);
                        }
                    } elseif ('newlinemode.end' !== $flag) {
                        $subnode->setFlag($flag, $value);
                    }
                }
                $destNodes[] = $subnode;
                $cpos = $npos + strlen($detectString);
            }
            $subnode = new TextNode(substr($node->content, $cpos), $node->occurredAt + $cpos);
            if (0 === $cpos) {
                $value = $node->getFlag('newlinemode.begin', 'integer', null);
                if (null !== $value) {
                    $subnode->setFlag('newlinemode.begin', $value);
                }
            }
            $value = $node->getFlag('newlinemode.end', 'integer', null);
            if (null !== $value) {
                $subnode->setFlag('newlinemode.end', $value);
            }
            $destNodes[] = $subnode;
            return $destNodes;
        }

        // neither text nor a breakable element with children => keep as is
        if (
            (self::NODE_ELEMENT !== $node->_type)
            || (self::PARAGRAPH_ALLOW_BREAKUP !== $node->getFlag('paragraph_type', 'integer', self::PARAGRAPH_ALLOW_BREAKUP))
            || !count($node->children)
        ) {
            return [$node];
        }

        $destNode = $node->duplicate();
        while (count($node->children)) {
            $firstnode = $node->children[0];
            $node->removeChild($firstnode);
            foreach ($this->_breakupNodeByParagraphs($firstnode) as $j => $subNode) {
                if (0 !== $j) {
                    $destNodes[] = $destNode;
                    $destNode = $node->duplicate();
                }
                $destNode->appendChild($subNode);
            }
        }
        $destNodes[] = $destNode;
        return $destNodes;
    }


    /**
     * Whether an element reads its content raw (usecontent / usecontent? / callback_replace?)
     *
     * @param Element $node
     * @param bool $checkAttrs Also evaluate the usecontent_param attributes
     * @return bool
     */
    private function _isUseContent(Element $node, bool $checkAttrs = false): bool
    {
        $name = $this->_getCanonicalName($node->name());
        if (false === $name) {
            return false;
        }
        $callbackType = $this->codes[$name]['callback_type'];
        if ('usecontent' === $callbackType) {
            return true;
        }
        if ('callback_replace?' === $callbackType) {
            $result = true;
        } elseif ('usecontent?' === $callbackType) {
            $result = false;
        } else {
            return false;
        }
        if (!$checkAttrs) {
            return !$result;
        }
        $attributes = array_keys($node->attributes);
        $params = $this->codes[$name]['callback_params']['usecontent_param'] ?? null;
        foreach ((array)$params as $param) {
            if (in_array($param, $attributes)) {
                return $result;
            }
        }
        if ((null === $params) && in_array(null, $attributes)) {
            return $result;
        }
        return !$result;
    }


    /**
     * Get the registered name of a code, honouring case sensitivity
     *
     * @param string $name
     * @return string|false
     */
    private function _getCanonicalName(string $name): string|false
    {
        if (isset($this->codes[$name])) {
            return $name;
        }
        $lname = strtolower($name);
        foreach (array_keys($this->codes) as $rname) {
            if (strtolower($rname) === $lname) {
                if ($this->caseSensitive && $this->getCodeFlag($rname, 'case_sensitive', 'boolean', true)) {
                    return false;
                }
                return $rname;
            }
        }
        return false;
    }
}
