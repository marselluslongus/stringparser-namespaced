<?php declare(strict_types=1);
/**
 * Regression tests for the BBCode string parser (src/StringParser)
 *
 * Expected values were recorded from vendor/mpommer/stringparser-namespaced 0.3.3.2,
 * so these tests pin the PHP 8.3 rewrite to the behaviour of the original,
 * including its quirks (see testUpperCaseCodeIsNotClosedCaseInsensitively).
 *
 * @package stringparser
 * @filesource
 *
 * @copyright Copyright (c) 2026-2026, Fast Lane Institute for Knowledge Transfer
 * @since 2026
 *
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StringParser\BBCode;
use StringParser\StringParser;


final class BBCodeTest extends TestCase
{
    /**
     * callback actions in call order, "<action>:<tag name>"
     *
     * @var list<string>
     */
    private array $log = [];


    /**
     * @return array<string, array{string, bool, string|false, list<string>}>
     */
    public static function fixtures(): array
    {
        return [
            'inline and implicit close (para)' => [
                "Hello [b]world[/b]\n\n[url=http://x]x[/url] [b]open",
                true,
                "<p>Hello <strong>world</strong></p>\n<p><url default=\"http://x\">x[/url] <strong>open</strong></url></p>\n",
                ["validate:url", "output:url"],
            ],
            'inline and implicit close (nopara)' => [
                "Hello [b]world[/b]\n\n[url=http://x]x[/url] [b]open",
                false,
                "Hello <strong>world</strong>\n\n<url default=\"http://x\">x[/url] <strong>open</strong></url>",
                ["validate:url", "output:url"],
            ],
            'list with optional close tags (para)' => [
                "[list]\n[*] one\n[*] two [b]bold[/b]\n[/list]",
                true,
                "<list>\n<*> one\n</*><*> two <strong>bold</strong>\n</*></list>",
                ["validate:list", "validate:*", "validate:*", "output:*", "output:*", "output:list"],
            ],
            'list with optional close tags (nopara)' => [
                "[list]\n[*] one\n[*] two [b]bold[/b]\n[/list]",
                false,
                "<list>\n<*> one\n</*><*> two <strong>bold</strong>\n</*></list>",
                ["validate:list", "validate:*", "validate:*", "output:*", "output:*", "output:list"],
            ],
            'paragraphs and block element (para)' => [
                "Para one\n\nPara two\n\n[h2]Head[/h2]\nafter",
                true,
                "<p>Para one</p>\n<p>Para two</p>\n<h2>Head</h2><p>\nafter</p>\n",
                ["validate:h2", "output:h2"],
            ],
            'paragraphs and block element (nopara)' => [
                "Para one\n\nPara two\n\n[h2]Head[/h2]\nafter",
                false,
                "Para one\n\nPara two\n\n<h2>Head</h2>\nafter",
                ["validate:h2", "output:h2"],
            ],
            'table, bare and valued attributes (para)' => [
                "[table w=50% border][tr][td left top colspan=2]C\n\nD[/td][/tr][/table]",
                true,
                "<table border=true w=\"50%\"><tr><td colspan=\"2\" left=true top=true><p>C</p>\n<p>D</p>\n</td></tr></table>",
                ["validate:table", "validate:tr", "validate:td", "output:td", "output:tr", "output:table"],
            ],
            'table, bare and valued attributes (nopara)' => [
                "[table w=50% border][tr][td left top colspan=2]C\n\nD[/td][/tr][/table]",
                false,
                "<table border=true w=\"50%\"><tr><td colspan=\"2\" left=true top=true><p>C</p>\n<p>D</p>\n</td></tr></table>",
                ["validate:table", "validate:tr", "validate:td", "output:td", "output:tr", "output:table"],
            ],
            'mixed attribute types (para)' => [
                "[page=search #downhere linkmark title=\"T\"]Search[/page]",
                true,
                "<p><page #downhere=true default=\"search\" linkmark=true title=\"T\">Search</page></p>\n",
                ["validate:page", "output:page"],
            ],
            'mixed attribute types (nopara)' => [
                "[page=search #downhere linkmark title=\"T\"]Search[/page]",
                false,
                "<page #downhere=true default=\"search\" linkmark=true title=\"T\">Search</page>",
                ["validate:page", "output:page"],
            ],
            'link not allowed within link (para)' => [
                "[link=a][page=b]nested[/page][/link] [link=https://x [b]y[/b]]z[/link]",
                true,
                "<p><link default=\"a\">[page=b]nested[/page]</link> <link [b=true default=\"https://x\">y[/b]]z</link></p>\n",
                ["validate:link", "validate:link", "output:link", "output:link"],
            ],
            'link not allowed within link (nopara)' => [
                "[link=a][page=b]nested[/page][/link] [link=https://x [b]y[/b]]z[/link]",
                false,
                "<link default=\"a\">[page=b]nested[/page]</link> <link [b=true default=\"https://x\">y[/b]]z</link>",
                ["validate:link", "validate:link", "output:link", "output:link"],
            ],
            'usecontent keeps markup raw (para)' => [
                "[literal][b]raw[/b] <i>[/literal]",
                true,
                "<p><literal>[b]raw[/b] <i></literal></p>\n",
                ["validate:literal", "output:literal"],
            ],
            'usecontent keeps markup raw (nopara)' => [
                "[literal][b]raw[/b] <i>[/literal]",
                false,
                "<literal>[b]raw[/b] <i></literal>",
                ["validate:literal", "output:literal"],
            ],
            'usecontent? with usecontent_param (para)' => [
                "[media=1 hascaption]cap[caption]Fig[/caption][/media] [media=1]ALT[/media] [media]1[/media]",
                true,
                "<p><media default=\"1\" hascaption=true>cap<caption>Fig</caption></media> <media default=\"1\">ALT</media> <media>1</media></p>\n",
                ["validate:media", "validate:caption", "validate:media", "validate:media", "output:caption", "output:media", "output:media", "output:media"],
            ],
            'usecontent? with usecontent_param (nopara)' => [
                "[media=1 hascaption]cap[caption]Fig[/caption][/media] [media=1]ALT[/media] [media]1[/media]",
                false,
                "<media default=\"1\" hascaption=true>cap<caption>Fig</caption></media> <media default=\"1\">ALT</media> <media>1</media>",
                ["validate:media", "validate:caption", "validate:media", "validate:media", "output:caption", "output:media", "output:media", "output:media"],
            ],
            'case insensitive incl. upper-case code (para)' => [
                "[B]x[/b] [Url=q]u[/URL] [LITERAL]l[/literal]",
                true,
                "<p><strong>x</strong> <Url default=\"q\">u[/URL] <LITERAL>l</LITERAL></Url></p>\n",
                ["validate:Url", "validate:LITERAL", "output:LITERAL", "output:Url"],
            ],
            'case insensitive incl. upper-case code (nopara)' => [
                "[B]x[/b] [Url=q]u[/URL] [LITERAL]l[/literal]",
                false,
                "<strong>x</strong> <Url default=\"q\">u[/URL] <LITERAL>l</LITERAL></Url>",
                ["validate:Url", "validate:LITERAL", "output:LITERAL", "output:Url"],
            ],
            'prefilter comment (para)' => [
                "[-- comment --]visible[-- second\nline]",
                true,
                "<p>visible</p>\n",
                [],
            ],
            'prefilter comment (nopara)' => [
                "[-- comment --]visible[-- second\nline]",
                false,
                "visible",
                [],
            ],
            'paragraphs inside block with newline flag (para)' => [
                "[box]in box\n\npara2[/box]\nafter box",
                true,
                "<box><p>in box</p>\n<p>para2</p>\n</box><p>\nafter box</p>\n",
                ["validate:box", "output:box"],
            ],
            'paragraphs inside block with newline flag (nopara)' => [
                "[box]in box\n\npara2[/box]\nafter box",
                false,
                "<box><p>in box</p>\n<p>para2</p>\n</box>\nafter box",
                ["validate:box", "output:box"],
            ],
            'nested paragraph handling (para)' => [
                "[indent]one\n\ntwo [b]b\n\nc[/b] three[/indent]",
                true,
                "<blockquote><p>one</p>\n<p>two <strong>b</strong></p>\n<p><strong>c</strong> three</p>\n</blockquote>",
                [],
            ],
            'nested paragraph handling (nopara)' => [
                "[indent]one\n\ntwo [b]b\n\nc[/b] three[/indent]",
                false,
                "<blockquote><p>one</p>\n<p>two <strong>b</strong></p>\n<p><strong>c</strong> three</p>\n</blockquote>",
                [],
            ],
            'failed validation reparses as text (para)' => [
                "[color=red bad]no[/color] [color=red]yes[/color] [literal]BAD[/literal]",
                true,
                "<p>[color=red bad]no[/color] <color default=\"red\">yes</color> [literal]BAD[/literal]</p>\n",
                ["validate:color", "validate:color", "validate:literal", "output:color"],
            ],
            'failed validation reparses as text (nopara)' => [
                "[color=red bad]no[/color] [color=red]yes[/color] [literal]BAD[/literal]",
                false,
                "[color=red bad]no[/color] <color default=\"red\">yes</color> [literal]BAD[/literal]",
                ["validate:color", "validate:color", "validate:literal", "output:color"],
            ],
            'misnested tags (para)' => [
                "[b][color=x]x[/b] y[/color]",
                true,
                "<p><strong><color default=\"x\">x</color></strong> y[/color]</p>\n",
                ["validate:color", "output:color"],
            ],
            'misnested tags (nopara)' => [
                "[b][color=x]x[/b] y[/color]",
                false,
                "<strong><color default=\"x\">x</color></strong> y[/color]",
                ["validate:color", "output:color"],
            ],
            'stray and unknown tags (para)' => [
                "[/b] stray [/unknown] [unknown]x[/unknown] [] ]]][[[",
                true,
                "<p>[/b] stray [/unknown] [unknown]x[/unknown] [] ]]][[[</p>\n",
                [],
            ],
            'stray and unknown tags (nopara)' => [
                "[/b] stray [/unknown] [unknown]x[/unknown] [] ]]][[[",
                false,
                "[/b] stray [/unknown] [unknown]x[/unknown] [] ]]][[[",
                [],
            ],
            'close tag forbidden (para)' => [
                "[anchor=top] [anchor=top]x[/anchor]",
                true,
                "<p><anchor default=\"top\"> </anchor><anchor default=\"top\">x</anchor></p>\n",
                ["validate:anchor", "validate:anchor", "output:anchor", "output:anchor"],
            ],
            'close tag forbidden (nopara)' => [
                "[anchor=top] [anchor=top]x[/anchor]",
                false,
                "<anchor default=\"top\"> </anchor><anchor default=\"top\">x</anchor>",
                ["validate:anchor", "validate:anchor", "output:anchor", "output:anchor"],
            ],
            'single tags (para)' => [
                "[hr][hr=x][br][br]x[/br]",
                true,
                "<p><hr></hr><hr default=\"x\"></hr><br/><br/>x[/br]</p>\n",
                ["validate:hr", "validate:hr", "output:hr", "output:hr"],
            ],
            'single tags (nopara)' => [
                "[hr][hr=x][br][br]x[/br]",
                false,
                "<hr></hr><hr default=\"x\"></hr><br/><br/>x[/br]",
                ["validate:hr", "validate:hr", "output:hr", "output:hr"],
            ],
            'quoted values with escapes (para)' => [
                "[color=\"a \\\"q\\\" \\\\ b\"]esc[/color] [color='s q']s[/color] [color = \"sp\"]a[/color][color:x]c[/color]",
                true,
                "<p><color default=\"a \"q\" \\ b\">esc</color> <color default=\"s q\">s</color> <color default=\"sp\">a</color><color default=\"x\">c</color></p>\n",
                ["validate:color", "validate:color", "validate:color", "validate:color", "output:color", "output:color", "output:color", "output:color"],
            ],
            'quoted values with escapes (nopara)' => [
                "[color=\"a \\\"q\\\" \\\\ b\"]esc[/color] [color='s q']s[/color] [color = \"sp\"]a[/color][color:x]c[/color]",
                false,
                "<color default=\"a \"q\" \\ b\">esc</color> <color default=\"s q\">s</color> <color default=\"sp\">a</color><color default=\"x\">c</color>",
                ["validate:color", "validate:color", "validate:color", "validate:color", "output:color", "output:color", "output:color", "output:color"],
            ],
            'unterminated quote (para)' => [
                "[color x=\"unterminated]oops",
                true,
                "<p>[color x=\"unterminated]oops</p>\n",
                [],
            ],
            'unterminated quote (nopara)' => [
                "[color x=\"unterminated]oops",
                false,
                "[color x=\"unterminated]oops",
                [],
            ],
            'text ends inside close tag (para)' => [
                "[b]text [/",
                true,
                "<p><strong>text [/</strong></p>\n",
                [],
            ],
            'text ends inside close tag (nopara)' => [
                "[b]text [/",
                false,
                "<strong>text [/</strong>",
                [],
            ],
            'empty input (para)' => [
                "",
                true,
                "",
                [],
            ],
            'empty input (nopara)' => [
                "",
                false,
                "",
                [],
            ],
        ];
    }


    #[DataProvider('fixtures')]
    public function testOutputAndCallbackOrderMatchOriginal(string $input, bool $rootParagraphs, string|false $expected, array $expectedLog): void
    {
        $p = $this->_engine();
        $p->setRootParagraphHandling($rootParagraphs);

        $this->assertSame($expected, $p->parse($input));
        $this->assertSame($expectedLog, $this->log);
    }


    /**
     * The original declared none of _output, _quoting, _savedName, _savedValue,
     * _savedCloseCount and Element::_codeInfo: every parse raised E_DEPRECATED.
     */
    public function testParsingRaisesNoDeprecations(): void
    {
        $raised = [];
        set_error_handler(function (int $no, string $str) use (&$raised) {
            $raised[] = $str;
            return true;
        });
        try {
            $p = $this->_engine();
            $p->setRootParagraphHandling(true);
            foreach (self::fixtures() as [$input]) {
                $p->parse($input);
            }
        } finally {
            restore_error_handler();
        }
        $this->assertSame([], $raised);
    }


    /**
     * A callback returning a non-string replacement makes the whole parse fail.
     */
    public function testNonStringReplacementFailsTheParse(): void
    {
        $p = new BBCode();
        $p->addCode('x', 'callback_replace', fn($action) => ('validate' === $action) ? true : null, null, 'inline', ['block'], []);

        $this->assertFalse($p->parse('a [x]b[/x] c'));
        // and the engine is reusable afterwards
        $this->assertSame('plain', $p->parse('plain'));
    }


    public function testCallbackReceivesNodeWithLegacyMembers(): void
    {
        $seen = [];
        $p = new BBCode();
        $p->addCode('outer', 'simple_replace', null, ['start_tag' => '', 'end_tag' => ''], 'block', ['block'], []);
        $p->addCode('in', 'callback_replace', function ($action, $attrs, $content, $params, $node) use (&$seen) {
            if ('output' === $action) {
                $seen[] = [
                    $node->_name,
                    $node->_type,
                    $node->_parent->_type,
                    $node->_parent->name(),
                    $node->_parent->lastChild()->equals($node),
                    $params,
                ];
            }
            return ('validate' === $action) ? true : (string)$content;
        }, ['k' => 'v'], 'inline', ['block'], []);

        $this->assertSame('ab', $p->parse('[outer][in]a[/in][in]b[/in][/outer]'));
        $this->assertSame(
            [
                ['in', BBCode::NODE_ELEMENT, BBCode::NODE_ELEMENT, 'outer', false, ['k' => 'v']],
                ['in', BBCode::NODE_ELEMENT, BBCode::NODE_ELEMENT, 'outer', true, ['k' => 'v']],
            ],
            $seen
        );
    }


    /**
     * Pinned quirk of the original: with global case insensitivity, a code registered
     * in upper case (the deprecated tags of CMS\Parser\BBcode\Parser) opens, but no
     * close tag matches it, so it extends to the end of its parent.
     */
    public function testUpperCaseCodeIsNotClosedCaseInsensitively(): void
    {
        $p = $this->_engine();

        $this->assertSame('<URL>a[/URL] b</URL>', $p->parse('[URL]a[/URL] b'));
    }


    public function testParseIsNotReentrant(): void
    {
        $p = new BBCode();
        $inner = null;
        $p->addCode('x', 'callback_replace', function ($action) use (&$p, &$inner) {
            $inner ??= $p->parse('[x]nested[/x]');
            return ('validate' === $action) ? true : 'X';
        }, null, 'inline', ['block'], []);

        $this->assertSame('X', $p->parse('[x]y[/x]'));
        $this->assertFalse($inner);
    }


    /**
     * ArticleParser sets 'starttag.before.newline', which the engine never reads.
     */
    public function testUnknownFlagsAreAcceptedAndIgnored(): void
    {
        $p = $this->_engine();
        $this->assertTrue($p->setCodeFlag('b', 'starttag.before.newline', BBCode::NEWLINE_IGNORE));

        $this->assertSame("a\n<strong>b</strong>", $p->parse("a\n[b]b[/b]"));
    }


    /**
     * Engine configured like a reduced CMS\Parser\Article\ArticleParser:
     * case insensitive, mixed attribute types, pre/postfilter, text parsers
     *
     * @return BBCode
     */
    private function _engine(): BBCode
    {
        $this->log = [];
        $cb = function ($action, $attributes, $content, $params, $node) {
            $this->log[] = $action . ':' . $node->_name;
            if ('output' !== $action) {
                return !isset($attributes['bad']) && !str_contains((string)$content, 'BAD');
            }
            ksort($attributes);
            $a = '';
            foreach ($attributes as $k => $v) {
                $a .= ' ' . $k . '=' . (true === $v ? 'true' : '"' . $v . '"');
            }
            return '<' . $node->_name . $a . '>' . $content . '</' . $node->_name . '>';
        };
        $inline = ['alt', 'block', 'inline', 'listitem', 'link'];

        $p = new BBCode();
        // registered before switching off case sensitivity, like BBcode\Parser's deprecated tags
        $p->addCode('URL', 'callback_replace', $cb, null, 'inline', ['block', 'inline', 'listitem'], []);
        $p->setGlobalCaseSensitive(false);
        $p->setMixedAttributeTypes(true);
        $p->addFilter(StringParser::FILTER_PRE, fn($s) => preg_replace('/\[--.*?\]/s', '', $s));
        $p->addFilter(StringParser::FILTER_POST, fn($s) => preg_replace('/<p>\s*<\/p>/', '', $s));

        $p->addCode('b', 'simple_replace', null, ['start_tag' => '<strong>', 'end_tag' => '</strong>'], 'inline', $inline, []);
        $p->addCode('br', 'simple_replace_single', null, ['start_tag' => '<br/>'], 'inline', $inline, []);
        $p->addCode('hr', 'callback_replace_single', $cb, null, 'inline', $inline, []);
        $p->addCode('color', 'callback_replace', $cb, null, 'inline', $inline, []);
        $p->addCode('h2', 'callback_replace', $cb, null, 'inline', ['alt', 'block', 'inline', 'listitem'], []);
        $p->setCodeFlag('h2', 'paragraph_type', BBCode::PARAGRAPH_BLOCK_ELEMENT);
        $p->addCode('indent', 'simple_replace', null, ['start_tag' => '<blockquote>', 'end_tag' => '</blockquote>'], 'block', ['block'], []);
        $p->setCodeFlag('indent', 'paragraphs', true);
        $p->setCodeFlag('indent', 'paragraph_type', BBCode::PARAGRAPH_BLOCK_ELEMENT);
        $p->addCode('list', 'callback_replace', $cb, null, 'list', ['alt', 'block', 'listitem'], []);
        $p->setCodeFlag('list', 'opentag.before.newline', BBCode::NEWLINE_IGNORE);
        $p->setCodeFlag('list', 'closetag.before.newline', BBCode::NEWLINE_IGNORE);
        $p->setCodeFlag('list', 'paragraph_type', BBCode::PARAGRAPH_BLOCK_ELEMENT);
        $p->addCode('*', 'callback_replace', $cb, null, 'listitem', ['list'], []);
        $p->setCodeFlag('*', 'closetag', BBCode::CLOSETAG_OPTIONAL);
        $p->setCodeFlag('*', 'paragraph_type', BBCode::PARAGRAPH_ALLOW_INSIDE);
        $p->addCode('table', 'callback_replace', $cb, null, 'table', ['block', 'inline'], []);
        $p->setCodeFlag('table', 'paragraph_type', BBCode::PARAGRAPH_BLOCK_ELEMENT);
        $p->addCode('tr', 'callback_replace', $cb, null, 'tr', ['table'], []);
        $p->setCodeFlag('tr', 'closetag', BBCode::CLOSETAG_OPTIONAL);
        $p->addCode('td', 'callback_replace', $cb, null, 'block', ['tr'], []);
        $p->setCodeFlag('td', 'paragraphs', true);
        $p->setCodeFlag('td', 'paragraph_type', BBCode::PARAGRAPH_ALLOW_INSIDE);
        $p->setCodeFlag('td', 'closetag', BBCode::CLOSETAG_OPTIONAL);
        $p->addCode('literal', 'usecontent', $cb, null, 'literal', ['block', 'inline', 'listitem', 'link'], []);
        $p->addCode('media', 'usecontent?', $cb, ['usecontent_param' => ['previewoverride', 'hascaption']], 'media', ['block', 'inline', 'listitem', 'link'], []);
        $p->setCodeFlag('media', 'closetag.after.newline', BBCode::NEWLINE_IGNORE);
        $p->setCodeFlag('media', 'paragraph_type', BBCode::PARAGRAPH_ALLOW_INSIDE);
        $p->addCode('caption', 'callback_replace', $cb, null, 'alt', ['media'], []);
        $p->addCode('box', 'callback_replace', $cb, null, 'block', ['block'], []);
        $p->setCodeFlag('box', 'paragraphs', true);
        $p->setCodeFlag('box', 'paragraph_type', BBCode::PARAGRAPH_BLOCK_ELEMENT);
        $p->setCodeFlag('box', 'closetag.after.newline', BBCode::NEWLINE_IGNORE);
        $p->addCode('page', 'callback_replace', $cb, null, 'link', ['listitem', 'block', 'inline'], ['link']);
        $p->addCode('link', 'callback_replace', $cb, null, 'link', ['listitem', 'block', 'inline'], ['link']);
        $p->addCode('anchor', 'callback_replace', $cb, null, 'anchor', ['block', 'inline'], ['link']);
        $p->setCodeFlag('anchor', 'closetag', BBCode::CLOSETAG_FORBIDDEN);
        $p->addParser(['block', 'inline', 'listitem', 'link'], fn($s) => htmlspecialchars($s, ENT_NOQUOTES));
        return $p;
    }
}
