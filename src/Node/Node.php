<?php

namespace StringParser\Node;

/**
 * Generic string parsing infrastructure: tree node
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
 *  marcel.pommer@flane.de 2026-11: PHP 8.4 rewrite: declared and typed properties,
 *   no object references, dead loop mode removed, tree-manipulation bugs fixed
 */


/**
 * Generic string parser node class
 *
 * Base class for any type of node used within the string parser.
 *
 * Objects are handles since PHP 5, so none of the reference juggling of the
 * PHP 4 original is needed any more; tree manipulation works on plain values.
 *
 * $_type and $_parent (and BBCode element's $_name) keep their legacy
 * underscore names because tag callbacks in consumer code read them directly.
 *
 * @package stringparser
 */
class Node
{
    /**
     * Node type: Unknown node
     */
    public const NODE_UNKNOWN = 0;

    /**
     * Node type: Root node
     */
    public const NODE_ROOT = 1;

    /**
     * Node type: Text node
     */
    public const NODE_TEXT = 2;

    /**
     * The type of this node. Node types of subclasses must be >= 32.
     */
    public int $_type = self::NODE_UNKNOWN;

    /**
     * Internal node ID; unique per request, used for identity comparisons only
     */
    public int $id = -1;

    /**
     * The parent of this node, null for root and detached nodes
     */
    public ?Node $_parent = null;

    /**
     * The children of this node, always a list (0..n-1)
     *
     * @var list<Node>
     */
    public array $children = [];

    /**
     * Position in the parsed text where this node occurred at, -1 if unknown
     */
    public int $occurredAt = -1;

    /**
     * Node ID sequence
     */
    private static int $_nodeId = 0;


    /**
     * @param int $occurredAt The position in the text where this node occurred at, -1 if unknown
     */
    public function __construct(int $occurredAt = -1)
    {
        $this->id = self::getNodeId();
        $this->occurredAt = $occurredAt;
    }


    /**
     * @return int
     */
    protected static function getNodeId(): int
    {
        return self::$_nodeId++;
    }


    /**
     * Type of the node
     *
     * @return int
     */
    public function type(): int
    {
        return $this->_type;
    }


    /**
     * Prepend a node to the children
     *
     * @param Node $node
     * @return bool
     */
    public function prependChild(Node $node): bool
    {
        if (!$this->_detachForInsertion($node)) {
            return false;
        }
        array_unshift($this->children, $node);
        $node->_parent = $this;
        return true;
    }


    /**
     * Append text to the last child if that is a text node, else append a new text node
     *
     * @param string $text
     * @return bool
     */
    public function appendToLastTextChild(string $text): bool
    {
        $last = $this->children[count($this->children) - 1] ?? null;
        if ((null === $last) || (self::NODE_TEXT !== $last->_type)) {
            return $this->appendChild(new Text($text));
        }
        $last->appendText($text);
        return true;
    }


    /**
     * Append a node to the children; removes it from its previous parent first
     *
     * @param Node $node
     * @return bool
     */
    public function appendChild(Node $node): bool
    {
        if (!$this->_detachForInsertion($node)) {
            return false;
        }
        $this->children[] = $node;
        $node->_parent = $this;
        return true;
    }


    /**
     * Insert a node before another child node
     *
     * @param Node $node The node to be inserted
     * @param Node $reference The child before which the node is inserted
     * @return bool
     */
    public function insertChildBefore(Node $node, Node $reference): bool
    {
        return $this->_insertRelative($node, $reference, 0);
    }


    /**
     * Insert a node after another child node
     *
     * @param Node $node The node to be inserted
     * @param Node $reference The child after which the node is inserted
     * @return bool
     */
    public function insertChildAfter(Node $node, Node $reference): bool
    {
        return $this->_insertRelative($node, $reference, 1);
    }


    /**
     * Remove a child node
     *
     * @param Node|int $child The child node or its index
     * @param bool $destroy Destroy the child afterwards
     * @return bool false if $child is not a child of this node
     */
    public function removeChild(Node|int $child, bool $destroy = false): bool
    {
        if ($child instanceof Node) {
            $index = $this->_findChild($child);
            if (false === $index) {
                return false;
            }
        } else {
            $index = $child;
            if (!isset($this->children[$index])) {
                return false;
            }
        }

        $object = $this->children[$index];

        // inconsistency
        if ((null === $object->_parent) || ($object->_parent->id !== $this->id)) {
            return false;
        }

        $object->_parent = null;
        array_splice($this->children, $index, 1);

        if ($destroy) {
            return self::destroyNode($object);
        }
        return true;
    }


    /**
     * Get the first child of this node
     *
     * @return Node|null
     */
    public function firstChild(): ?Node
    {
        return $this->children[0] ?? null;
    }


    /**
     * Get the last child of this node
     *
     * @return Node|null
     */
    public function lastChild(): ?Node
    {
        return $this->children[count($this->children) - 1] ?? null;
    }


    /**
     * Destroy a node: detach it from its parent and break up its subtree
     *
     * Breaking the parent/child cycles lets refcounting free the tree at once
     * instead of waiting for the cycle collector.
     *
     * @param Node|null $node
     * @return bool
     */
    public static function destroyNode(?Node $node): bool
    {
        if (null === $node) {
            return false;
        }
        // removeChild() calls destroyNode() again once the node is detached
        if (null !== $node->_parent) {
            return $node->_parent->removeChild($node, true);
        }
        while (count($node->children)) {
            if (!$node->removeChild(0, true)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Checks identity of this node and another node
     *
     * @param Node $node
     * @return bool
     */
    public function equals(Node $node): bool
    {
        return $this->id === $node->id;
    }


    /**
     * Determines whether a criterium matches this node
     *
     * @param string $criterium
     * @param mixed $value
     * @return bool
     */
    public function matchesCriterium(string $criterium, mixed $value): bool
    {
        return false;
    }


    /**
     * Search the subtree (depth-first, pre-order, excluding this node) for nodes matching a criterium
     *
     * @param string $criterium
     * @param mixed $value
     * @return list<Node>
     */
    public function getNodesByCriterium(string $criterium, mixed $value): array
    {
        $nodes = [];
        foreach ($this->children as $child) {
            if ($child->matchesCriterium($criterium, $value)) {
                $nodes[] = $child;
            }
            array_push($nodes, ...$child->getNodesByCriterium($criterium, $value));
        }
        return $nodes;
    }


    /**
     * Count the nodes in the subtree (excluding this node) matching a criterium
     *
     * @param string $criterium
     * @param mixed $value
     * @return int
     */
    public function getNodeCountByCriterium(string $criterium, mixed $value): int
    {
        $count = 0;
        foreach ($this->children as $child) {
            if ($child->matchesCriterium($criterium, $value)) {
                $count++;
            }
            $count += $child->getNodeCountByCriterium($criterium, $value);
        }
        return $count;
    }


    /**
     * Dump the subtree
     *
     * @param string $prefix Indentation unit
     * @param string $linesep Line separator
     * @param int $level Initial indentation level
     * @return string
     */
    public function dump(string $prefix = ' ', string $linesep = "\n", int $level = 0): string
    {
        $str = str_repeat($prefix, $level) . $this->id . ': ' . $this->dumpToString() . $linesep;
        foreach ($this->children as $child) {
            $str .= $child->dump($prefix, $linesep, $level + 1);
        }
        return $str;
    }


    /**
     * Dump this node to a string
     *
     * @return string
     */
    protected function dumpToString(): string
    {
        if (self::NODE_ROOT === $this->_type) {
            return 'root';
        }
        return (string)$this->_type;
    }


    /**
     * Detach a node from its current parent so that it can be inserted here
     *
     * @param Node $node
     * @return bool false if the node may not be inserted
     */
    private function _detachForInsertion(Node $node): bool
    {
        // root nodes may not be children of other nodes, and a node may not contain itself
        if ((self::NODE_ROOT === $node->_type) || ($node === $this)) {
            return false;
        }
        if (null !== $node->_parent) {
            return $node->_parent->removeChild($node, false);
        }
        return true;
    }


    /**
     * Insert a node relative to a child node
     *
     * @param Node $node
     * @param Node $reference
     * @param int $offset 0: before, 1: after
     * @return bool
     */
    private function _insertRelative(Node $node, Node $reference, int $offset): bool
    {
        if ((false === $this->_findChild($reference)) || ($node === $reference)) {
            return false;
        }
        if (!$this->_detachForInsertion($node)) {
            return false;
        }
        // look up again: detaching may have shifted the reference if both were siblings
        $index = $this->_findChild($reference);
        array_splice($this->children, $index + $offset, 0, [$node]);
        $node->_parent = $this;
        return true;
    }


    /**
     * Find the index of a child node
     *
     * @param Node $child
     * @return int|false
     */
    private function _findChild(Node $child): int|false
    {
        foreach ($this->children as $i => $c) {
            if ($c->id === $child->id) {
                return $i;
            }
        }
        return false;
    }
}
