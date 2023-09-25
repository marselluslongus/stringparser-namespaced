<?php

namespace StringParser;

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

use \StringParser\Node\Node;
use \StringParser\Node\Text as TextNode;


/**
 * BB code string parser class
 *
 * @package stringparser
 */
class BBCode extends StringParser
{

    const CLOSETAG_FORBIDDEN = -1;
    const CLOSETAG_OPTIONAL = 0;
    const CLOSETAG_IMPLICIT = 1;
    const CLOSETAG_IMPLICIT_ON_CLOSE_ONLY = 2;
    const CLOSETAG_MUSTEXIST = 3;

    const NEWLINE_PARSE = 0;
    const NEWLINE_IGNORE = 1;
    const NEWLINE_DROP = 2;

    const PARAGRAPH_ALLOW_BREAKUP = 0;
    const PARAGRAPH_ALLOW_INSIDE = 1;
    const PARAGRAPH_BLOCK_ELEMENT = 2;

    /**
     * Node type: BBCode Element node
     * @see Node::$_type
     */
    const NODE_ELEMENT = 32;

    /**
     * Node type: BBCode Paragraph node
     * @see Node::$_type
     */
    const NODE_PARAGRAPH = 33;


    /**
     * String parser mode
     *
     * The BBCode string parser works in search mode
     *
     * @access protected
     * @var int
     * @see StringParser::MODE_SEARCH, StringParser::MODE_LOOP
     */
    var $_parserMode = StringParser::MODE_SEARCH;

    /**
     * Defined BB Codes
     *
     * The registered BB codes
     *
     * @access protected
     * @var array
     */
    var $_codes = array();

    /**
     * Registered parsers
     *
     * @access protected
     * @var array
     */
    var $_parsers = array();

    /**
     * Defined maximum occurrences
     *
     * @access protected
     * @var array
     */
    var $_maxOccurrences = array();

    /**
     * Root content type
     *
     * @access protected
     * @var string
     */
    var $_rootContentType = 'block';

    /**
     * Do not output but return the tree
     *
     * @access protected
     * @var bool
     */
    var $_noOutput = false;

    /**
     * Global setting: case sensitive
     *
     * @access protected
     * @var bool
     */
    var $_caseSensitive = true;

    /**
     * Root paragraph handling enabled
     *
     * @access protected
     * @var bool
     */
    var $_rootParagraphHandling = false;

    /**
     * Paragraph handling parameters
     * @access protected
     * @var array
     */
    var $_paragraphHandling = array(
        'detect_string' => "\n\n",
        'start_tag' => '<p>',
        'end_tag' => "</p>\n"
    );

    /**
     * Allow mixed attribute types (e.g. [code=bla attr=blub])
     * @access private
     * @var bool
     */
    var $_mixedAttributeTypes = false;

    /**
     * Whether to call validation function again (with $action == 'validate_auto') when closetag comes
     * @access protected
     * @var bool
     */
    var $_validateAgain = false;


    /**
     * Add a code
     *
     * @access public
     * @param string $name The name of the code
     * @param string $callback_type See documentation
     * @param string $callback_func The callback function to call
     * @param array $callback_params The callback parameters
     * @param string $content_type See documentation
     * @param array $allowed_within See documentation
     * @param array $not_allowed_within See documentation
     * @return bool
     */
    public function addCode($name, $callback_type, $callback_func, $callback_params, $content_type, $allowed_within, $not_allowed_within)
    {
        if (isset($this->_codes[$name])) {
            return false; // already exists
        }
        if (! preg_match('/^[a-zA-Z0-9*_!+-]+$/', $name)) {
            return false; // invalid
        }
        $this->_codes[$name] = array(
            'name' => $name,
            'callback_type' => $callback_type,
            'callback_func' => $callback_func,
            'callback_params' => $callback_params,
            'content_type' => $content_type,
            'allowed_within' => $allowed_within,
            'not_allowed_within' => $not_allowed_within,
            'flags' => array()
        );
        return true;
    }


    /**
     * Remove a code
     *
     * @access public
     * @param $name The code to remove
     * @return bool
     */
    public function removeCode($name)
    {
        if (isset($this->_codes[$name])) {
            unset($this->_codes[$name]);
            return true;
        }
        return false;
    }


    /**
     * Remove all codes
     *
     * @access public
     */
    public function removeAllCodes()
    {
        $this->_codes = array();
    }


    /**
     * Set a code flag
     *
     * @access public
     * @param string $name The name of the code
     * @param string $flag The name of the flag to set
     * @param mixed $value The value of the flag to set
     * @return bool
     */
    public function setCodeFlag($name, $flag, $value)
    {
        if (! isset($this->_codes[$name])) {
            return false;
        }
        $this->_codes[$name]['flags'][$flag] = $value;
        return true;
    }


    /**
     * Set occurrence type
     *
     * Example:
     *   $bbcode->setOccurrenceType ('url', 'link');
     *   $bbcode->setMaxOccurrences ('link', 4);
     * Would create the situation where a link may only occur four
     * times in the hole text.
     *
     * @access public
     * @param string $code The name of the code
     * @param string $type The name of the occurrence type to set
     * @return bool
     */
    public function setOccurrenceType($code, $type)
    {
        return $this->setCodeFlag($code, 'occurrence_type', $type);
    }


    /**
     * Set maximum number of occurrences
     *
     * @access public
     * @param string $type The name of the occurrence type
     * @param int $count The maximum number of occurrences
     * @return bool
     */
    public function setMaxOccurrences($type, $count)
    {
        settype($count, 'integer');
        if ($count < 0) { // sorry, does not make any sense
            return false;
        }
        $this->_maxOccurrences[$type] = $count;
        return true;
    }


    /**
     * Add a parser
     *
     * @access public
     * @param string $type The content type for which the parser is to add
     * @param mixed $parser The function to call
     * @return bool
     */
    public function addParser($type, $parser)
    {
        if (is_array($type)) {
            foreach ($type as $t) {
                $this->addParser($t, $parser);
            }
            return true;
        }
        if (! isset($this->_parsers[$type])) {
            $this->_parsers[$type] = array();
        }
        $this->_parsers[$type][] = $parser;
        return true;
    }


    /**
     * Set root content type
     *
     * @access public
     * @param string $content_type The new root content type
     */
    public function setRootContentType($content_type)
    {
        $this->_rootContentType = $content_type;
    }

    /**
     * Set paragraph handling on root element
     *
     * @access public
     * @param bool $enabled The new status of paragraph handling on root element
     */
    public function setRootParagraphHandling($enabled)
    {
        $this->_rootParagraphHandling = (bool)$enabled;
    }


    /**
     * Set paragraph handling parameters
     *
     * @access public
     * @param string $detect_string The string to detect
     * @param string $start_tag The replacement for the start tag (e.g. <p>)
     * @param string $end_tag The replacement for the start tag (e.g. </p>)
     */
    public function setParagraphHandlingParameters($detect_string, $start_tag, $end_tag)
    {
        $this->_paragraphHandling = array(
            'detect_string' => $detect_string,
            'start_tag' => $start_tag,
            'end_tag' => $end_tag
        );
    }


    /**
     * Set global case sensitive flag
     *
     * If this is set to true, the class normally is case sensitive, but
     * the case_sensitive code flag may override this for a single code.
     *
     * If this is set to false, all codes are case insensitive.
     *
     * @access public
     * @param bool $caseSensitive
     */
    public function setGlobalCaseSensitive($caseSensitive)
    {
        $this->_caseSensitive = (bool)$caseSensitive;
    }


    /**
     * Get global case sensitive flag
     *
     * @access public
     * @return bool
     */
    public function globalCaseSensitive()
    {
        return $this->_caseSensitive;
    }


    /**
     * Set mixed attribute types flag
     *
     * If set, [code=val1 attr=val2] will cause 2 attributes to be parsed:
     * 'default' will have value 'val1', 'attr' will have value 'val2'.
     * If not set, only one attribute 'default' will have the value
     * 'val1 attr=val2' (the default and original behaviour)
     *
     * @access public
     * @param bool $mixedAttributeTypes
     */
    public function setMixedAttributeTypes($mixedAttributeTypes)
    {
        $this->_mixedAttributeTypes = (bool)$mixedAttributeTypes;
    }


    /**
     * Get mixed attribute types flag
     *
     * @access public
     * @return bool
     */
    public function mixedAttributeTypes()
    {
        return $this->_mixedAttributeTypes;
    }


    /**
     * Set validate again flag
     *
     * If this is set to true, the class calls the validation function
     * again with $action == 'validate_again' when closetag comes.
     *
     * @access public
     * @param bool $validateAgain
     */
    public function setValidateAgain($validateAgain)
    {
        $this->_validateAgain = (bool)$validateAgain;
    }


    /**
     * Get validate again flag
     *
     * @access public
     * @return bool
     */
    public function validateAgain()
    {
        return $this->_validateAgain;
    }


    /**
     * Get a code flag
     *
     * @access public
     * @param string $name The name of the code
     * @param string $flag The name of the flag to get
     * @param string $type The type of the return value
     * @param mixed $default The default return value
     * @return bool
     */
    public function getCodeFlag($name, $flag, $type = 'mixed', $default = null)
    {
        if (! isset($this->_codes[$name])) {
            return $default;
        }
        if (! array_key_exists($flag, $this->_codes[$name]['flags'])) {
            return $default;
        }
        $return = $this->_codes[$name]['flags'][$flag];
        if ($type != 'mixed') {
            settype($return, $type);
        }
        return $return;
    }


    /**
     * Set a specific status
     * @access protected
     */
    protected function setStatus($status)
    {
        switch ($status) {
            case 0:
                $this->_charactersSearch = array('[/', '[');
                $this->_status = $status;
                break;
            case 1:
                $this->_charactersSearch = array(']', ' = "', '="', ' = \'', '=\'', ' = ', '=', ': ', ':', ' ');
                $this->_status = $status;
                break;
            case 2:
                $this->_charactersSearch = array(']');
                $this->_status = $status;
                $this->_savedName = '';
                break;
            case 3:
                if ($this->_quoting !== null) {
                    if ($this->_mixedAttributeTypes) {
                        $this->_charactersSearch = array('\\\\', '\\' . $this->_quoting, $this->_quoting.' ', $this->_quoting.']', $this->_quoting);
                    } else {
                        $this->_charactersSearch = array('\\\\', '\\' . $this->_quoting, $this->_quoting.']', $this->_quoting);
                    }
                    $this->_status = $status;
                    break;
                }
                if ($this->_mixedAttributeTypes) {
                    $this->_charactersSearch = array(' ', ']');
                } else {
                    $this->_charactersSearch = array(']');
                }
                $this->_status = $status;
                break;
            case 4:
                $this->_charactersSearch = array(' ', ']', '="', '=\'', '=');
                $this->_status = $status;
                $this->_savedName = '';
                $this->_savedValue = '';
                break;
            case 5:
                if ($this->_quoting !== null) {
                    $this->_charactersSearch = array('\\\\', '\\' . $this->_quoting, $this->_quoting.' ', $this->_quoting.']', $this->_quoting);
                } else {
                    $this->_charactersSearch = array(' ', ']');
                }
                $this->_status = $status;
                $this->_savedValue = '';
                break;
            case 7:
                $this->_charactersSearch = array('[/' . $this->topNode ('name').']');
                if (! $this->topNode ('getFlag', 'case_sensitive', 'boolean', true) || !$this->_caseSensitive) {
                    $this->_charactersSearch[] = '[/';
                }
                $this->_status = $status;
                break;
            default:
                return false;
        }
        return true;
    }


    /**
     * Abstract method Append text depending on current status
     * @access protected
     * @param string $text The text to append
     * @return bool On success, the function returns true, else false
     */
    protected function appendText($text)
    {
        if (!strlen($text)) {
            return true;
        }
        switch ($this->_status) {
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
     * Restart parsing after current block
     *
     * To achieve this the current top stack object is removed from the
     * tree. Then the current item
     *
     * @access protected
     * @return bool
     */
    protected function reparseAfterCurrentBlock()
    {
        if ($this->_status == 2) {
            // this status will *never* call reparseAfterCurrentBlock itself
            // so this is called if the loop ends
            // therefore, just add the [/ to the text

            // _savedName should be empty but just in case
            $this->_cpos -= strlen($this->_savedName);
            $this->_savedName = '';
            $this->_status = 0;
            $this->appendText ('[/');
            return true;
        } else {
            return parent::reparseAfterCurrentBlock();
        }
    }


    /**
     * Apply parsers
     */
    private function _applyParsers($type, $text)
    {
        if (! isset($this->_parsers[$type])) {
            return $text;
        }
        foreach ($this->_parsers[$type] as $parser) {
            if (is_callable($parser)) {
                $ntext = call_user_func($parser, $text);
                if (is_string($ntext)) {
                    $text = $ntext;
                }
            }
        }
        return $text;
    }


    /**
     * Handle status
     * @access protected
     * @param int $status The current status
     * @param string $needle The needle that was found
     * @return bool
     */
    protected function handleStatus($status, $needle)
    {
        switch ($status) {
            case 0: // NORMAL TEXT
                if ($needle != '[' && $needle != '[/') {
                    $this->appendText ($needle);
                    return true;
                }
                if ($needle == '[') {
                    $node = new BBCode\Node\Element($this->_cpos);//$node = new StringParser_BBCode_Node_Element ($this->_cpos);
                    $res = $this->pushNode($node);
                    if (! $res) {
                        return false;
                    }
                    $this->setStatus(1);
                } else if ($needle == '[/') {
                    if (count($this->_stack) <= 1) {
                        $this->appendText($needle);
                        return true;
                    }
                    $this->setStatus(2);
                }
                break;
            case 1: // OPEN TAG
                if ($needle == ']') {
                    return $this->_openElement(0);
                } else if (trim($needle) == ':' || trim($needle) == '=') {
                    $this->_quoting = null;
                    $this->setStatus(3); // default value parser
                    break;
                } else if (trim($needle) == '="' || trim($needle) == '= "' || trim($needle) == '=\'' || trim($needle) == '= \'') {
                    $this->_quoting = substr(trim($needle), -1);
                    $this->setStatus(3); // default value parser with quotation
                    break;
                } else if ($needle == ' ') {
                    $this->setStatus(4); // attribute parser
                    break;
                } else {
                    $this->appendText($needle);
                    return true;
                }
            // break not necessary because every if clause contains return
            case 2: // CLOSE TAG
                if ($needle != ']') {
                    $this->appendText($needle);
                    return true;
                }
                $closecount = 0;
                if (! $this->_isCloseable($this->_savedName, $closecount)) {
                    $this->setStatus(0);
                    $this->appendText('[/' . $this->_savedName . $needle);
                    return true;
                }
                // this validates the code(s) to be closed after the content tree of
                // that code(s) are built - if the second validation fails, we will have
                // to reparse. note that as reparseAfterCurrentBlock will not work correctly
                // if we're in $status == 2, we will have to set our status to 0 manually
                if (! $this->_validateCloseTags($closecount)) {
                    $this->setStatus(0);
                    return $this->reparseAfterCurrentBlock();
                }
                $this->setStatus(0);
                for ($i = 0; $i < $closecount; $i++) {
                    if ($i == $closecount - 1) {
                        $this->topNode('setHadCloseTag');
                    }
                    if (! $this->popNode()) {
                        return false;
                    }
                }
                break;
            case 3: // DEFAULT ATTRIBUTE
                if ($this->_quoting !== null) {
                    if ($needle == '\\\\') {
                        $this->appendText('\\');
                        return true;
                    } else if ($needle == '\\' . $this->_quoting) {
                        $this->appendText($this->_quoting);
                        return true;
                    } else if ($needle == $this->_quoting.' ') {
                        $this->setStatus(4);
                        return true;
                    } else if ($needle == $this->_quoting.']') {
                        return $this->_openElement (2);
                    } else if ($needle == $this->_quoting) {
                        // can't be, only ']' and ' ' allowed after quoting char
                        return $this->reparseAfterCurrentBlock();
                    } else {
                        $this->appendText($needle);
                        return true;
                    }
                } else {
                    if ($needle == ' ') {
                        $this->setStatus(4);
                        return true;
                    } else if ($needle == ']') {
                        return $this->_openElement(2);
                    } else {
                        $this->appendText($needle);
                        return true;
                    }
                }
            // break not needed because every if clause contains return!
            case 4: // ATTRIBUTE NAME
                if ($needle == ' ') {
                    if (strlen($this->_savedName)) {
                        $this->topNode ('setAttribute', $this->_savedName, true);
                    }
                    // just ignore and continue in same mode
                    $this->setStatus(4); // reset parameters
                    return true;
                } else if ($needle == ']') {
                    if (strlen($this->_savedName)) {
                        $this->topNode ('setAttribute', $this->_savedName, true);
                    }
                    return $this->_openElement (2);
                } else if ($needle == '=') {
                    $this->_quoting = null;
                    $this->setStatus(5);
                    return true;
                } else if ($needle == '="') {
                    $this->_quoting = '"';
                    $this->setStatus(5);
                    return true;
                } else if ($needle == '=\'') {
                    $this->_quoting = '\'';
                    $this->setStatus(5);
                    return true;
                } else {
                    $this->appendText ($needle);
                    return true;
                }
            // break not needed because every if clause contains return!
            case 5: // ATTRIBUTE VALUE
                if ($this->_quoting !== null) {
                    if ($needle == '\\\\') {
                        $this->appendText ('\\');
                        return true;
                    } else if ($needle == '\\' . $this->_quoting) {
                        $this->appendText ($this->_quoting);
                        return true;
                    } else if ($needle == $this->_quoting.' ') {
                        $this->topNode ('setAttribute', $this->_savedName, $this->_savedValue);
                        $this->setStatus(4);
                        return true;
                    } else if ($needle == $this->_quoting.']') {
                        $this->topNode ('setAttribute', $this->_savedName, $this->_savedValue);
                        return $this->_openElement(2);
                    } else if ($needle == $this->_quoting) {
                        // can't be, only ']' and ' ' allowed after quoting char
                        return $this->reparseAfterCurrentBlock();
                    } else {
                        $this->appendText($needle);
                        return true;
                    }
                } else {
                    if ($needle == ' ') {
                        $this->topNode('setAttribute', $this->_savedName, $this->_savedValue);
                        $this->setStatus(4);
                        return true;
                    } else if ($needle == ']') {
                        $this->topNode('setAttribute', $this->_savedName, $this->_savedValue);
                        return $this->_openElement(2);
                    } else {
                        $this->appendText($needle);
                        return true;
                    }
                }
            // break not needed because every if clause contains return!
            case 7:
                if ($needle == '[/') {
                    // this was case insensitive match
                    if (strtolower(substr($this->_text, $this->_cpos + strlen($needle), strlen($this->topNode ('name')) + 1)) == strtolower($this->topNode ('name').']')) {
                        // this matched
                        $this->_cpos += strlen($this->topNode('name')) + 1;
                    } else {
                        // it didn't match
                        $this->appendText($needle);
                        return true;
                    }
                }
                $closecount = $this->_savedCloseCount;
                if (! $this->topNode ('validate')) {
                    return $this->reparseAfterCurrentBlock();
                }
                // do we have to close subnodes?
                if ($closecount) {
                    // get top node
                    $mynode =& $this->_stack[count($this->_stack)-1];
                    // close necessary nodes
                    for ($i = 0; $i <= $closecount; $i++) {
                        if (! $this->popNode()) {
                            return false;
                        }
                    }
                    if (! $this->pushNode($mynode)) {
                        return false;
                    }
                }
                $this->setStatus(0);
                $this->popNode();
                return true;
            default:
                return false;
        }
        return true;
    }

    /**
     * Open the next element
     *
     * @access protected
     * @return bool
     */
    private function _openElement($type = 0)
    {
        $name = $this->_getCanonicalName($this->topNode('name'));
        if ($name === false) {
            return $this->reparseAfterCurrentBlock();
        }
        $occ_type = $this->getCodeFlag($name, 'occurrence_type', 'string');
        if ($occ_type !== null && isset($this->_maxOccurrences[$occ_type])) {
            $max_occs = $this->_maxOccurrences[$occ_type];
            $occs = $this->_root->getNodeCountByCriterium('flag:occurrence_type', $occ_type);
            if ($occs >= $max_occs) {
                return $this->reparseAfterCurrentBlock();
            }
        }
        $closecount = 0;
        $this->topNode ('setCodeInfo', $this->_codes[$name]);
        if (! $this->_isOpenable ($name, $closecount)) {
            return $this->reparseAfterCurrentBlock();
        }
        $this->setStatus(0);
        switch ($type) {
            case 0:
                $cond = $this->_isUseContent($this->_stack[count($this->_stack)-1], false);
                break;
            case 1:
                $cond = $this->_isUseContent($this->_stack[count($this->_stack)-1], true);
                break;
            case 2:
                $cond = $this->_isUseContent($this->_stack[count($this->_stack)-1], true);
                break;
            default:
                $cond = false;
                break;
        }
        if ($cond) {
            $this->_savedCloseCount = $closecount;
            $this->setStatus(7);
            return true;
        }
        if (! $this->topNode ('validate')) {
            return $this->reparseAfterCurrentBlock();
        }
        // do we have to close subnodes?
        if ($closecount) {
            // get top node
            $mynode =& $this->_stack[count($this->_stack)-1];
            // close necessary nodes
            for ($i = 0; $i <= $closecount; $i++) {
                if (! $this->popNode()) {
                    return false;
                }
            }
            if (! $this->pushNode ($mynode)) {
                return false;
            }
        }

        if ($this->_codes[$name]['callback_type'] == 'simple_replace_single' || $this->_codes[$name]['callback_type'] == 'callback_replace_single') {
            if (! $this->popNode())  {
                return false;
            }
        }

        return true;
    }

    /**
     * Is a node closeable?
     *
     * @access protected
     * @return bool
     */
    private function _isCloseable($name, &$closecount)
    {
        $node =& $this->_findNamedNode ($name, false);
        if ($node === false) {
            return false;
        }
        $scount = count($this->_stack);
        for ($i = $scount - 1; $i > 0; $i--) {
            $closecount++;
            if ($this->_stack[$i]->equals($node)) {
                return true;
            }
            if ($this->_stack[$i]->getFlag('closetag', 'integer', self::CLOSETAG_IMPLICIT) == self::CLOSETAG_MUSTEXIST) {
                return false;
            }
        }
        return false;
    }


    /**
     * Revalidate codes when close tags appear
     *
     * @access protected
     * @return bool
     */
    private function _validateCloseTags($closecount)
    {
        $scount = count($this->_stack);
        for ($i = $scount - 1; $i >= $scount - $closecount; $i--) {
            if ($this->_validateAgain) {
                if (! $this->_stack[$i]->validate ('validate_again')) {
                    return false;
                }
            }
        }
        return true;
    }


    /**
     * Is a node openable?
     *
     * @access protected
     * @return bool
     */
    private function _isOpenable($name, &$closecount)
    {
        if (! isset($this->_codes[$name])) {
            return false;
        }

        $closecount = 0;

        $allowed_within = $this->_codes[$name]['allowed_within'];
        $not_allowed_within = $this->_codes[$name]['not_allowed_within'];

        $scount = count($this->_stack);
        if ($scount == 2) { // top level element
            if (!in_array($this->_rootContentType, $allowed_within)) {
                return false;
            }
        } else {
            if (!in_array($this->_stack[$scount-2]->_codeInfo['content_type'], $allowed_within)) {
                return $this->_isOpenableWithClose ($name, $closecount);
            }
        }

        for ($i = 1; $i < $scount - 1; $i++) {
            if (in_array($this->_stack[$i]->_codeInfo['content_type'], $not_allowed_within)) {
                return $this->_isOpenableWithClose ($name, $closecount);
            }
        }

        return true;
    }

    /**
     * Is a node openable by closing other nodes?
     *
     * @access protected
     * @return bool
     */
    private function _isOpenableWithClose($name, &$closecount)
    {
        $tnname = $this->_getCanonicalName($this->topNode ('name'));
        if (!in_array($this->getCodeFlag($tnname, 'closetag', 'integer', self::CLOSETAG_IMPLICIT), array(self::CLOSETAG_FORBIDDEN, self::CLOSETAG_OPTIONAL))) {
            return false;
        }
        $node =& $this->_findNamedNode($name, true);
        if ($node === false) {
            return false;
        }
        $scount = count($this->_stack);
        if ($scount < 3) {
            return false;
        }
        for ($i = $scount - 2; $i > 0; $i--) {
            $closecount++;
            if ($this->_stack[$i]->equals($node)) {
                return true;
            }
            if (in_array($this->_stack[$i]->getFlag('closetag', 'integer', self::CLOSETAG_IMPLICIT), array(self::CLOSETAG_IMPLICIT_ON_CLOSE_ONLY, self::CLOSETAG_MUSTEXIST))) {
                return false;
            }
            if ($this->_validateAgain) {
                if (! $this->_stack[$i]->validate('validate_again')) {
                    return false;
                }
            }
        }

        return false;
    }


    /**
     * Abstract method: Close remaining blocks
     * @access protected
     */
    protected function closeRemainingBlocks()
    {
        // everything closed
        if (count($this->_stack) == 1) {
            return true;
        }
        // not everything close
        if ($this->strict) {
            return false;
        }
        while (count($this->_stack) > 1) {
            if ($this->topNode ('getFlag', 'closetag', 'integer', self::CLOSETAG_IMPLICIT) == self::CLOSETAG_MUSTEXIST) {
                return false; // sorry
            }
            $res = $this->popNode();
            if (! $res) {
                return false;
            }
        }
        return true;
    }


    /**
     * Find a node with a specific name in stack
     *
     * @access protected
     * @return mixed
     */
    private function & _findNamedNode($name, $searchdeeper = false)
    {
        $lname = $this->_getCanonicalName($name);
        $case_sensitive = $this->_caseSensitive && $this->getCodeFlag($lname, 'case_sensitive', 'boolean', true);
        if ($case_sensitive) {
            $name = strtolower($name);
        }
        $scount = count($this->_stack);
        if ($searchdeeper) {
            $scount--;
        }
        for ($i = $scount - 1; $i > 0; $i--) {
            if (! $case_sensitive) {
                $cmp_name = strtolower($this->_stack[$i]->name());
            } else {
                $cmp_name = $this->_stack[$i]->name();
            }
            if ($cmp_name == $lname) {
                return $this->_stack[$i];
            }
        }
        $result = false;
        return $result;
    }


    /**
     * Abstract method: Output tree
     * @access protected
     * @return bool
     */
    protected function outputTree()
    {
        if ($this->_noOutput) {
            return true;
        }
        $output = $this->_outputNode($this->_root);
        if (is_string($output)) {
            $this->_output = $this->applyPostfilters($output);
            unset($output);
            return true;
        }

        return false;
    }


    /**
     * Output a node
     * @access protected
     * @return bool
     */
    private function _outputNode(&$node)
    {
        $output = '';
        if ($node->_type == self::NODE_PARAGRAPH || $node->_type == self::NODE_ELEMENT || $node->_type == Node::NODE_ROOT) {
            $ccount = count($node->_children);
            for ($i = 0; $i < $ccount; $i++) {
                $suboutput = $this->_outputNode($node->_children[$i]);
                if (! is_string($suboutput)) {
                    return false;
                }
                $output .= $suboutput;
            }
            if ($node->_type == self::NODE_PARAGRAPH) {
                return $this->_paragraphHandling['start_tag'] . $output . $this->_paragraphHandling['end_tag'];
            }
            if ($node->_type == self::NODE_ELEMENT) {
                return $node->getReplacement ($output);
            }
            return $output;
        } else if ($node->_type == Node::NODE_TEXT) {
            $output = $node->content;
            $before = '';
            $after = '';
            $ol = strlen($output);
            switch ($node->getFlag('newlinemode.begin', 'integer', self::NEWLINE_PARSE)) {
                case self::NEWLINE_IGNORE:
                    if ($ol && $output[0] == "\n") {
                        $before = "\n";
                    }
                // don't break!
                case self::NEWLINE_DROP:
                    if ($ol && $output[0] == "\n") {
                        $output = substr($output, 1);
                        $ol--;
                    }
                    break;
            }
            switch ($node->getFlag('newlinemode.end', 'integer', self::NEWLINE_PARSE)) {
                case self::NEWLINE_IGNORE:
                    if ($ol && $output[$ol-1] == "\n") {
                        $after = "\n";
                    }
                // don't break!
                case self::NEWLINE_DROP:
                    if ($ol && $output[$ol-1] == "\n") {
                        $output = substr($output, 0, -1);
                        $ol--;
                    }
                    break;
            }
            // can't do anything
            if ($node->_parent === null) {
                return $before . $output . $after;
            }
            if ($node->_parent->_type == self::NODE_PARAGRAPH)  {
                $parent =& $node->_parent;
                unset($node);
                $node =& $parent;
                unset($parent);
                // if no parent for this paragraph
                if ($node->_parent === null) {
                    return $before . $output . $after;
                }
            }
            if ($node->_parent->_type == Node::NODE_ROOT) {
                return $before . $this->_applyParsers($this->_rootContentType, $output) . $after;
            }
            if ($node->_parent->_type == self::NODE_ELEMENT) {
                return $before . $this->_applyParsers($node->_parent->_codeInfo['content_type'], $output) . $after;
            }
            return $before . $output . $after;
        }
    }


    /**
     * Abstract method: Manipulate the tree
     * @access protected
     * @return bool
     */
    protected function modifyTree()
    {
        // first pass: try to do newline handling
        $nodes =& $this->_root->getNodesByCriterium('needsTextNodeModification', true);
        $nodes_count = count($nodes);
        for ($i = 0; $i < $nodes_count; $i++) {
            $v = $nodes[$i]->getFlag('opentag.before.newline', 'integer', self::NEWLINE_PARSE);
            if ($v != self::NEWLINE_PARSE) {
                $n =& $nodes[$i]->findPrevAdjentTextNode();
                if (! is_null ($n)) {
                    $n->setFlag('newlinemode.end', $v);
                }
                unset($n);
            }
            $v = $nodes[$i]->getFlag('opentag.after.newline', 'integer', self::NEWLINE_PARSE);
            if ($v != self::NEWLINE_PARSE) {
                $n =& $nodes[$i]->firstChildIfText();
                if (! is_null ($n)) {
                    $n->setFlag('newlinemode.begin', $v);
                }
                unset($n);
            }
            $v = $nodes[$i]->getFlag('closetag.before.newline', 'integer', self::NEWLINE_PARSE);
            if ($v != self::NEWLINE_PARSE) {
                $n =& $nodes[$i]->lastChildIfText();
                if (! is_null($n)) {
                    $n->setFlag('newlinemode.end', $v);
                }
                unset($n);
            }
            $v = $nodes[$i]->getFlag('closetag.after.newline', 'integer', self::NEWLINE_PARSE);
            if ($v != self::NEWLINE_PARSE) {
                $n =& $nodes[$i]->findNextAdjentTextNode();
                if (! is_null ($n)) {
                    $n->setFlag('newlinemode.begin', $v);
                }
                unset($n);
            }
        }

        // second pass a: do paragraph handling on root element
        if ($this->_rootParagraphHandling) {
            $res = $this->_handleParagraphs($this->_root);
            if (! $res) {
                return false;
            }
        }

        // second pass b: do paragraph handling on other elements
        unset($nodes);
        $nodes =& $this->_root->getNodesByCriterium('flag:paragraphs', true);
        $nodes_count = count($nodes);
        for ($i = 0; $i < $nodes_count; $i++) {
            $res = $this->_handleParagraphs($nodes[$i]);
            if (! $res) {
                return false;
            }
        }

        // second pass c: search for empty paragraph nodes and remove them
        unset($nodes);
        $nodes =& $this->_root->getNodesByCriterium('empty', true);
        $nodes_count = count($nodes);
        if (isset($parent)) {
            unset($parent); $parent = null;
        }
        for ($i = 0; $i < $nodes_count; $i++) {
            if ($nodes[$i]->_type != self::NODE_PARAGRAPH) {
                continue;
            }
            unset($parent);
            $parent =& $nodes[$i]->_parent;
            $parent->removeChild($nodes[$i], true);
        }

        return true;
    }


    /**
     * Handle paragraphs
     * @access protected
     * @param object $node The node to handle
     * @return bool
     */
    private function _handleParagraphs(&$node)
    {
        // if this node is already a subnode of a paragraph node, do NOT
        // do paragraph handling on this node!
        if ($this->_hasParagraphAncestor($node)) {
            return true;
        }
        $dest_nodes = array();
        $last_node_was_paragraph = false;
        $prevtype = Node::NODE_TEXT;
        $paragraph = null;
        while (count($node->_children)) {
            $mynode =& $node->_children[0];
            $node->removeChild($mynode);
            $subprevtype = $prevtype;
            $sub_nodes =& $this->_breakupNodeByParagraphs($mynode);
            for ($i = 0; $i < count($sub_nodes); $i++) {
                if (! $last_node_was_paragraph ||  ($prevtype == $sub_nodes[$i]->_type && ($i != 0 || $prevtype != self::NODE_ELEMENT))) {
                    unset($paragraph);
                    $paragraph = new BBCode\Node\Paragraph();
                }
                $prevtype = $sub_nodes[$i]->_type;
                if ($sub_nodes[$i]->_type != self::NODE_ELEMENT || $sub_nodes[$i]->getFlag('paragraph_type', 'integer', self::PARAGRAPH_ALLOW_BREAKUP) != self::PARAGRAPH_BLOCK_ELEMENT) {
                    $paragraph->appendChild($sub_nodes[$i]);
                    $dest_nodes[] =& $paragraph;
                    $last_node_was_paragraph = true;
                } else {
                    $dest_nodes[] =& $sub_nodes[$i];
                    $last_onde_was_paragraph = false;
                    unset($paragraph);
                    $paragraph = new BBCode\Node\Paragraph();
                }
            }
        }
        $count = count($dest_nodes);
        for ($i = 0; $i < $count; $i++) {
            $node->appendChild($dest_nodes[$i]);
        }
        unset($dest_nodes);
        unset($paragraph);
        return true;
    }


    /**
     * Search for a paragraph node in tree in upward direction
     * @access protected
     * @param object $node The node to analyze
     * @return bool
     */
    private function _hasParagraphAncestor(&$node)
    {
        if ($node->_parent === null) {
            return false;
        }
        $parent =& $node->_parent;
        if ($parent->_type == self::NODE_PARAGRAPH) {
            return true;
        }
        return $this->_hasParagraphAncestor ($parent);
    }


    /**
     * Break up nodes
     * @access protected
     * @param object $node The node to break up
     * @return array
     */
    private function &_breakupNodeByParagraphs(&$node)
    {
        $detect_string = $this->_paragraphHandling['detect_string'];
        $dest_nodes = array();
        // text node => no problem
        if ($node->_type == Node::NODE_TEXT) {
            $cpos = 0;
            while (($npos = strpos($node->content, $detect_string, $cpos)) !== false) {
                $subnode = new TextNode(substr($node->content, $cpos, $npos - $cpos), $node->occurredAt + $cpos);
                // copy flags
                foreach ($node->_flags as $flag => $value) {
                    if ($flag == 'newlinemode.begin') {
                        if ($cpos == 0) {
                            $subnode->setFlag($flag, $value);
                        }
                    } else if ($flag == 'newlinemode.end') {
                        // do nothing
                    } else {
                        $subnode->setFlag($flag, $value);
                    }
                }
                $dest_nodes[] =& $subnode;
                unset($subnode);
                $cpos = $npos + strlen($detect_string);
            }
            $subnode = new TextNode(substr($node->content, $cpos), $node->occurredAt + $cpos);
            if ($cpos == 0) {
                $value = $node->getFlag('newlinemode.begin', 'integer', null);
                if ($value !== null) {
                    $subnode->setFlag('newlinemode.begin', $value);
                }
            }
            $value = $node->getFlag('newlinemode.end', 'integer', null);
            if ($value !== null) {
                $subnode->setFlag('newlinemode.end', $value);
            }
            $dest_nodes[] =& $subnode;
            unset($subnode);
            return $dest_nodes;
        }
        // not a text node or an element node => no way
        if ($node->_type != self::NODE_ELEMENT) {
            $dest_nodes[] =& $node;
            return $dest_nodes;
        }
        if ($node->getFlag('paragraph_type', 'integer', self::PARAGRAPH_ALLOW_BREAKUP) != self::PARAGRAPH_ALLOW_BREAKUP || !count($node->_children)) {
            $dest_nodes[] =& $node;
            return $dest_nodes;
        }
        $dest_node =& $node->duplicate();
        $nodecount = count($node->_children);
        // now this node allows breakup - do it
        for ($i = 0; $i < $nodecount; $i++) {
            $firstnode =& $node->_children[0];
            $node->removeChild($firstnode);
            $sub_nodes =& $this->_breakupNodeByParagraphs($firstnode);
            for ($j = 0; $j < count($sub_nodes); $j++) {
                if ($j != 0) {
                    $dest_nodes[] =& $dest_node;
                    unset($dest_node);
                    $dest_node =& $node->duplicate();
                }
                $dest_node->appendChild($sub_nodes[$j]);
            }
            unset($sub_nodes);
        }
        $dest_nodes[] =& $dest_node;
        return $dest_nodes;
    }


    /**
     * Is this node a usecontent node
     * @access protected
     * @param object $node The node to check
     * @param bool $check_attrs Also check whether 'usecontent?'-attributes exist
     * @return bool
     */
    private function _isUseContent(&$node, $check_attrs = false)
    {
        $name = $this->_getCanonicalName ($node->name());
        // this should NOT happen
        if ($name === false) {
            return false;
        }
        if ($this->_codes[$name]['callback_type'] == 'usecontent') {
            return true;
        }
        $result = false;
        if ($this->_codes[$name]['callback_type'] == 'callback_replace?') {
            $result = true;
        } else if ($this->_codes[$name]['callback_type'] != 'usecontent?') {
            return false;
        }
        if ($check_attrs === false) {
            return !$result;
        }
        $attributes = array_keys($this->topNodeVar('_attributes'));
        $p = @$this->_codes[$name]['callback_params']['usecontent_param'];
        if (is_array($p)) {
            foreach ($p as $param) {
                if (in_array($param, $attributes)) {
                    return $result;
                }
            }
        } else {
            if (in_array($p, $attributes)) {
                return $result;
            }
        }
        return !$result;
    }


    /**
     * Get canonical name of a code
     *
     * @access protected
     * @param string $name
     * @return string
     */
    private function _getCanonicalName($name)
    {
        if (isset($this->_codes[$name])) {
            return $name;
        }
        $found = false;
        // try to find the code in the code list
        foreach (array_keys($this->_codes) as $rname) {
            // match
            if (strtolower($rname) == strtolower($name)) {
                $found = $rname;
                break;
            }
        }
        if ($found === false || ($this->_caseSensitive && $this->getCodeFlag($found, 'case_sensitive', 'boolean', true))) {
            return false;
        }
        return $rname;
    }
}
