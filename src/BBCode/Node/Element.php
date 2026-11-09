<?php

namespace StringParser\BBCode\Node;

/**
 * BB code string parsing: element node
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
use StringParser\Traits\Flags;


/**
 * BBCode string parser element node class
 *
 * @package stringparser
 */
final class Element extends Node
{
    use Flags;

    public int $_type = BBCode::NODE_ELEMENT;

    /**
     * Element (tag) name as written in the text; legacy name, read by consumer callbacks
     */
    public string $_name = '';

    /**
     * Element attributes; bare attributes (mixed attribute types) have the value true
     *
     * @var array<string|int, string|true>
     */
    public array $attributes = [];

    /**
     * Code definition as registered by BBCode::addCode()
     *
     * @var array<string, mixed>
     */
    public array $codeInfo = [];

    /**
     * Whether the element was closed by an explicit close tag
     */
    public bool $hasCloseTag = false;

    /**
     * Whether the element was processed by paragraph handling
     */
    public bool $isParagraphHandled = false;


    /**
     * Duplicate this node without children and parent
     *
     * @return Element
     */
    public function duplicate(): Element
    {
        $newnode = new Element($this->occurredAt);
        $newnode->_name = $this->_name;
        $newnode->flags = $this->flags;
        $newnode->attributes = $this->attributes;
        $newnode->hasCloseTag = $this->hasCloseTag;
        $newnode->isParagraphHandled = $this->isParagraphHandled;
        $newnode->codeInfo = $this->codeInfo;
        return $newnode;
    }


    /**
     * @return string
     */
    public function name(): string
    {
        return $this->_name;
    }


    /**
     * @param string $name
     * @return true
     */
    public function setName(string $name): bool
    {
        $this->_name = $name;
        return true;
    }


    /**
     * @param string $chars
     * @return true
     */
    public function appendToName(string $chars): bool
    {
        $this->_name .= $chars;
        return true;
    }


    /**
     * @param string $name
     * @param string $chars
     * @return true
     */
    public function appendToAttribute(string $name, string $chars): bool
    {
        $this->attributes[$name] = ($this->attributes[$name] ?? '') . $chars;
        return true;
    }


    /**
     * @param string $name
     * @param string|true $value
     * @return true
     */
    public function setAttribute(string $name, string|bool $value): bool
    {
        $this->attributes[$name] = $value;
        return true;
    }


    /**
     * Set the code definition; replaces the flags with the code's flags
     *
     * @param array<string, mixed> $info
     * @return true
     */
    public function setCodeInfo(array $info): bool
    {
        $this->codeInfo = $info;
        $this->flags = $info['flags'];
        return true;
    }


    /**
     * @param string $name
     * @return string|true|null
     */
    public function attribute(string $name): string|bool|null
    {
        return $this->attributes[$name] ?? null;
    }


    /**
     * @return void
     */
    public function setHadCloseTag(): void
    {
        $this->hasCloseTag = true;
    }


    /**
     * @return void
     */
    public function setParagraphHandled(): void
    {
        $this->isParagraphHandled = true;
    }


    /**
     * @return bool
     */
    public function paragraphHandled(): bool
    {
        return $this->isParagraphHandled;
    }


    /**
     * @return bool
     */
    public function hadCloseTag(): bool
    {
        return $this->hasCloseTag;
    }


    /**
     * Determines whether a criterium matches this node
     *
     * Supported: 'tagName', 'needsTextNodeModification', 'flag:<name>', '!flag:<name>',
     * 'flag=:<name>', '!flag=:<name>' (the latter two compare strictly)
     *
     * @param string $criterium
     * @param mixed $value
     * @return bool
     */
    public function matchesCriterium(string $criterium, mixed $value): bool
    {
        if ('tagName' === $criterium) {
            return $value == $this->_name;
        }
        if ('needsTextNodeModification' === $criterium) {
            $needs = (BBCode::NEWLINE_PARSE !== $this->_newlineFlag('opentag.before.newline'))
                || (BBCode::NEWLINE_PARSE !== $this->_newlineFlag('opentag.after.newline'))
                || (
                    $this->hasCloseTag
                    && (
                        (BBCode::NEWLINE_PARSE !== $this->_newlineFlag('closetag.before.newline'))
                        || (BBCode::NEWLINE_PARSE !== $this->_newlineFlag('closetag.after.newline'))
                    )
                );
            return $needs === (bool)$value;
        }
        if (str_starts_with($criterium, 'flag:')) {
            return $this->getFlag(substr($criterium, 5)) == $value;
        }
        if (str_starts_with($criterium, '!flag:')) {
            return $this->getFlag(substr($criterium, 6)) != $value;
        }
        if (str_starts_with($criterium, 'flag=:')) {
            return $this->getFlag(substr($criterium, 6)) === $value;
        }
        if (str_starts_with($criterium, '!flag=:')) {
            return $this->getFlag(substr($criterium, 7)) !== $value;
        }
        return parent::matchesCriterium($criterium, $value);
    }


    /**
     * Get the first child if it is a text node
     *
     * @return Node|null
     */
    public function firstChildIfText(): ?Node
    {
        $ret = $this->firstChild();
        if ((null === $ret) || (Node::NODE_TEXT !== $ret->_type)) {
            return null;
        }
        return $ret;
    }


    /**
     * Get the last child if it is a text node and this element had a close tag;
     * descends into a last child element that had no close tag
     *
     * @return Node|null
     */
    public function lastChildIfText(): ?Node
    {
        $ret = $this->lastChild();
        if (null === $ret) {
            return null;
        }
        if ((Node::NODE_TEXT === $ret->_type) && $this->hasCloseTag) {
            return $ret;
        }
        if ((Node::NODE_TEXT !== $ret->_type) && !$ret->hadCloseTag()) {
            return $ret->findPrevAdjentTextNodeHelper();
        }
        return null;
    }


    /**
     * Find the text node adjacent to the close tag
     *
     * @return Node|null
     */
    public function findNextAdjentTextNode(): ?Node
    {
        if ((null === $this->_parent) || !$this->hasCloseTag) {
            return null;
        }
        $siblings = $this->_parent->children;
        $found = $this->_indexInParent();
        if (null === $found) {
            return null;
        }
        if ($found < count($siblings) - 1) {
            return (Node::NODE_TEXT === $siblings[$found + 1]->_type) ? $siblings[$found + 1] : null;
        }
        if ((BBCode::NODE_ELEMENT === $this->_parent->_type) && !$this->_parent->hadCloseTag()) {
            return $this->_parent->findNextAdjentTextNode();
        }
        return null;
    }


    /**
     * Find the text node adjacent to the open tag
     *
     * @return Node|null
     */
    public function findPrevAdjentTextNode(): ?Node
    {
        if (null === $this->_parent) {
            return null;
        }
        $found = $this->_indexInParent();
        if ((null === $found) || (0 === $found)) {
            return null;
        }
        $prev = $this->_parent->children[$found - 1];
        if (Node::NODE_TEXT === $prev->_type) {
            return $prev;
        }
        if (!$prev->hadCloseTag()) {
            return $prev->findPrevAdjentTextNodeHelper();
        }
        return null;
    }


    /**
     * Helper for findPrevAdjentTextNode(): the last child if it is text, recursing
     * into a last child element without close tag
     *
     * @return Node|null
     */
    public function findPrevAdjentTextNodeHelper(): ?Node
    {
        $lastnode = $this->lastChild();
        if ((null === $lastnode) || (Node::NODE_TEXT === $lastnode->_type)) {
            return $lastnode;
        }
        if (!$lastnode->hadCloseTag()) {
            return $lastnode->findPrevAdjentTextNodeHelper();
        }
        return null;
    }


    /**
     * Validate the code by calling its callback
     *
     * @param string $action 'validate' or 'validate_again'
     * @return bool
     */
    public function validate(string $action = 'validate'): bool
    {
        if (('validate' !== $action) && ('validate_again' !== $action)) {
            return false;
        }
        $callbackType = $this->codeInfo['callback_type'];
        if (('simple_replace' === $callbackType) || ('simple_replace_single' === $callbackType)) {
            return !count($this->attributes);
        }
        if (!is_callable($this->codeInfo['callback_func'])) {
            return false;
        }

        $usesContent = in_array($callbackType, ['usecontent', 'usecontent?', 'callback_replace?'], true);
        if ($usesContent && (1 === count($this->children)) && (Node::NODE_TEXT === $this->children[0]->_type)) {
            $res = $this->_callback($action, $this->children[0]->content);
            // content-using codes may not be broken up by paragraph handling, unless
            // already set to anything other than the default (e.g. PARAGRAPH_BLOCK_ELEMENT)
            if ($res && (BBCode::PARAGRAPH_ALLOW_BREAKUP === $this->getFlag('paragraph_type', 'integer', BBCode::PARAGRAPH_ALLOW_BREAKUP))) {
                $this->flags['paragraph_type'] = BBCode::PARAGRAPH_ALLOW_INSIDE;
            }
            return (bool)$res;
        }

        return (bool)$this->_callback($action, null);
    }


    /**
     * Get the replacement for this code
     *
     * @param string $subcontent Output of all child nodes
     * @return mixed string on success; anything else makes the whole parse fail
     */
    public function getReplacement(string $subcontent): mixed
    {
        $callbackType = $this->codeInfo['callback_type'];
        if ('simple_replace_single' === $callbackType) {
            return ('' === $subcontent) ? $this->codeInfo['callback_params']['start_tag'] : false;
        }
        if ('simple_replace' === $callbackType) {
            return $this->codeInfo['callback_params']['start_tag'] . $subcontent . $this->codeInfo['callback_params']['end_tag'];
        }
        // usecontent, usecontent?, callback_replace(?), callback_replace_single;
        // the callback is callable, determined in validate()
        return $this->_callback('output', $subcontent);
    }


    /**
     * @return string
     */
    protected function dumpToString(): string
    {
        $str = 'bbcode "' . substr(preg_replace('/\s+/', ' ', $this->_name), 0, 40) . '"';
        if (count($this->attributes)) {
            $attribs = array_keys($this->attributes);
            sort($attribs);
            $parts = [];
            foreach ($attribs as $attrib) {
                $parts[] = $attrib . '="' . substr(preg_replace('/\s+/', ' ', (string)$this->attributes[$attrib]), 0, 10) . '"';
            }
            $str .= ' (' . implode(', ', $parts) . ')';
        }
        return $str;
    }


    /**
     * Call the code's callback: ($action, $attributes, $content, $params, $node)
     *
     * @param string $action
     * @param string|null $content
     * @return mixed
     */
    private function _callback(string $action, ?string $content): mixed
    {
        return call_user_func(
            $this->codeInfo['callback_func'],
            $action,
            $this->attributes,
            $content,
            $this->codeInfo['callback_params'],
            $this
        );
    }


    /**
     * @param string $flag
     * @return int
     */
    private function _newlineFlag(string $flag): int
    {
        return $this->getFlag($flag, 'integer', BBCode::NEWLINE_PARSE);
    }


    /**
     * @return int|null
     */
    private function _indexInParent(): ?int
    {
        foreach ($this->_parent->children as $i => $sibling) {
            if ($sibling->equals($this)) {
                return $i;
            }
        }
        return null;
    }
}
