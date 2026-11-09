<?php declare(strict_types=1);
/**
 * Tests for the string parser tree nodes (src/StringParser/Node)
 *
 * @package stringparser
 * @filesource
 *
 * @copyright Copyright (c) 2026-2026, Fast Lane Institute for Knowledge Transfer
 * @since 2026
 *
 */

use PHPUnit\Framework\TestCase;
use StringParser\BBCode\Node\Element;
use StringParser\BBCode\Node\Paragraph;
use StringParser\Node\Node;
use StringParser\Node\Root;
use StringParser\Node\Text;


final class NodeTest extends TestCase
{
    /**
     * @param Node $node
     * @return list<string>
     */
    private function _contents(Node $node): array
    {
        return array_map(fn(Node $n) => $n->content, $node->children);
    }


    public function testAppendChildSetsParentAndMovesFromPreviousParent(): void
    {
        $a = new Root();
        $b = new Element();
        $t = new Text('x');

        $this->assertTrue($a->appendChild($t));
        $this->assertSame($a, $t->_parent);

        $this->assertTrue($b->appendChild($t));
        $this->assertSame($b, $t->_parent);
        $this->assertSame([], $a->children);
        $this->assertSame([$t], $b->children);
    }


    public function testRootCannotBecomeAChild(): void
    {
        $this->assertFalse((new Element())->appendChild(new Root()));
    }


    /**
     * The original compared $_parent with false instead of null, so prepending a
     * fresh node called removeChild() on null: fatal error under PHP 8.
     */
    public function testPrependChildOnDetachedNode(): void
    {
        $e = new Element();
        $e->appendChild(new Text('b'));

        $this->assertTrue($e->prependChild(new Text('a')));
        $this->assertSame(['a', 'b'], $this->_contents($e));
        $this->assertSame($e, $e->firstChild()->_parent);
    }


    /**
     * The original did not set $_parent on the inserted node (and crashed in
     * insertChildAfter() like prependChild()).
     */
    public function testInsertChildBeforeAndAfterSetParent(): void
    {
        $e = new Element();
        $b = new Text('b');
        $e->appendChild($b);
        $a = new Text('a');
        $c = new Text('c');

        $this->assertTrue($e->insertChildBefore($a, $b));
        $this->assertTrue($e->insertChildAfter($c, $b));
        $this->assertSame(['a', 'b', 'c'], $this->_contents($e));
        $this->assertSame($e, $a->_parent);
        $this->assertSame($e, $c->_parent);
    }


    public function testInsertChildAfterSiblingMovesWithinParent(): void
    {
        $e = new Element();
        [$a, $b, $c] = [new Text('a'), new Text('b'), new Text('c')];
        $e->appendChild($a);
        $e->appendChild($b);
        $e->appendChild($c);

        $this->assertTrue($e->insertChildAfter($a, $c));
        $this->assertSame(['b', 'c', 'a'], $this->_contents($e));
    }


    public function testInsertRelativeToNonChildFails(): void
    {
        $e = new Element();

        $this->assertFalse($e->insertChildBefore(new Text('a'), new Text('b')));
    }


    public function testRemoveChildByIndexAndByNode(): void
    {
        $e = new Element();
        [$a, $b, $c] = [new Text('a'), new Text('b'), new Text('c')];
        $e->appendChild($a);
        $e->appendChild($b);
        $e->appendChild($c);

        $this->assertTrue($e->removeChild(1));
        $this->assertTrue($e->removeChild($c));
        $this->assertFalse($e->removeChild($c));
        $this->assertFalse($e->removeChild(5));
        $this->assertSame(['a'], $this->_contents($e));
        $this->assertNull($b->_parent);
    }


    public function testDestroyNodeBreaksUpTheSubtree(): void
    {
        $root = new Root();
        $e = new Element();
        $t = new Text('x');
        $root->appendChild($e);
        $e->appendChild($t);

        $this->assertTrue(Node::destroyNode($e));
        $this->assertSame([], $root->children);
        $this->assertSame([], $e->children);
        $this->assertNull($t->_parent);
        $this->assertFalse(Node::destroyNode(null));
    }


    public function testFirstAndLastChild(): void
    {
        $e = new Element();
        $this->assertNull($e->firstChild());
        $this->assertNull($e->lastChild());

        $e->appendChild($a = new Text('a'));
        $e->appendChild($b = new Text('b'));
        $this->assertTrue($e->firstChild()->equals($a));
        $this->assertTrue($e->lastChild()->equals($b));
    }


    public function testAppendToLastTextChildMergesText(): void
    {
        $e = new Element();
        $e->appendToLastTextChild('a');
        $e->appendToLastTextChild('b');
        $e->appendChild(new Element());
        $e->appendToLastTextChild('c');

        $this->assertCount(3, $e->children);
        $this->assertSame('ab', $e->children[0]->content);
        $this->assertSame('c', $e->children[2]->content);
    }


    public function testNodesByCriteriumArePreOrder(): void
    {
        $root = new Root();
        $outer = new Element();
        $outer->setName('outer');
        $outer->setFlag('mark', true);
        $inner = new Element();
        $inner->setName('inner');
        $inner->setFlag('mark', true);
        $root->appendChild($outer);
        $outer->appendChild($inner);

        $this->assertSame(
            ['outer', 'inner'],
            array_map(fn(Element $n) => $n->name(), $root->getNodesByCriterium('flag:mark', true))
        );
        $this->assertSame(2, $root->getNodeCountByCriterium('flag:mark', true));
        $this->assertSame(1, $root->getNodeCountByCriterium('tagName', 'inner'));
    }


    public function testEmptyParagraphCriterium(): void
    {
        $p = new Paragraph();
        $this->assertTrue($p->matchesCriterium('empty', true));
        $this->assertFalse($p->matchesCriterium('other', true));

        $p->appendChild($t = new Text("\n"));
        $this->assertFalse($p->matchesCriterium('empty', true));

        $t->setFlag('newlinemode.begin', \StringParser\BBCode::NEWLINE_IGNORE);
        $this->assertTrue($p->matchesCriterium('empty', true));
    }


    public function testTypedFlags(): void
    {
        $t = new Text('x');
        $t->setFlag('n', '3');

        $this->assertSame(3, $t->getFlag('n', 'integer'));
        $this->assertSame('3', $t->getFlag('n'));
        $this->assertSame('d', $t->getFlag('missing', 'integer', 'd'));
    }


    public function testDuplicateCopiesStateButNotTree(): void
    {
        $e = new Element(7);
        $e->setName('x');
        $e->setAttribute('default', 'v');
        $e->setFlag('f', 1);
        $e->setHadCloseTag();
        $e->appendChild(new Text('child'));

        $d = $e->duplicate();
        $this->assertNotSame($e->id, $d->id);
        $this->assertFalse($d->equals($e));
        $this->assertSame(['x', 'v', 1, true, 7, []], [$d->name(), $d->attribute('default'), $d->getFlag('f'), $d->hadCloseTag(), $d->occurredAt, $d->children]);
    }
}
