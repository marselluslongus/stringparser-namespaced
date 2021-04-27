<?php

namespace StringParser\BBCode\Node;

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

use \StringParser\StringParser;
use \StringParser\BBCode;
use \StringParser\Node\Node;


/**
 * BBCode String parser element node class
 *
 * @package stringparser
 */
class Element extends Node
{
    /**
     * The type of this node.
     *
     * This node is a bbcode element node.
     *
     * @access protected
     * @var int
     * @see BBCode::NODE_ELEMENT
     */
    var $_type = BBCode::NODE_ELEMENT;

    /**
     * Element name
     *
     * @access protected
     * @var string
     * @see StringParser_BBCode_Node_Element::name
     * @see StringParser_BBCode_Node_Element::setName
     * @see StringParser_BBCode_Node_Element::appendToName
     */
    var $_name = '';

    /**
     * Element flags
     *
     * @access protected
     * @var array
     */
    var $_flags = array();

    /**
     * Element attributes
     *
     * @access protected
     * @var array
     */
    var $_attributes = array();

    /**
     * Had a close tag
     *
     * @access protected
     * @var bool
     */
    var $_hadCloseTag = false;

    /**
     * Was processed by paragraph handling
     *
     * @access protected
     * @var bool
     */
    var $_paragraphHandled = false;


    /**
     * Duplicate this node (but without children / parents)
     *
     * @access public
     * @return object
     */
    public function &duplicate()
    {
        $newnode = new Element($this->occurredAt);//$newnode = new StringParser_BBCode_Node_Element ($this->occurredAt);
        $newnode->_name = $this->_name;
        $newnode->_flags = $this->_flags;
        $newnode->_attributes = $this->_attributes;
        $newnode->_hadCloseTag = $this->_hadCloseTag;
        $newnode->_paragraphHandled = $this->_paragraphHandled;
        $newnode->_codeInfo = $this->_codeInfo;
        return $newnode;
    }


    /**
     * Retreive name of this element
     *
     * @access public
     * @return string
     */
    public function name()
    {
        return $this->_name;
    }

    /**
     * Set name of this element
     *
     * @access public
     * @param string $name The new name of the element
     * @return true
     */
    public function setName($name)
    {
        $this->_name = $name;
        return true;
    }


    /**
     * Append to name of this element
     *
     * @access public
     * @param string $chars The chars to append to the name of the element
     * @return true
     */
    public function appendToName($chars)
    {
        $this->_name .= $chars;
        return true;
    }


    /**
     * Append to attribute of this element
     *
     * @access public
     * @param string $name The name of the attribute
     * @param string $chars The chars to append to the attribute of the element
     * @return true
     */
    public function appendToAttribute($name, $chars)
    {
        if (! isset($this->_attributes[$name])) {
            $this->_attributes[$name] = $chars;
            return true;
        }
        $this->_attributes[$name] .= $chars;
        return true;
    }

    /**
     * Set attribute
     *
     * @access public
     * @param string $name The name of the attribute
     * @param string $value The new value of the attribute
     * @return true
     */
    public function setAttribute($name, $value)
    {
        $this->_attributes[$name] = $value;
        return true;
    }


    /**
     * Set code info
     *
     * @access public
     * @param array $info The code info array
     * @return true
     */
    public function setCodeInfo($info)
    {
        $this->_codeInfo = $info;
        $this->_flags = $info['flags'];
        return true;
    }


    /**
     * Get attribute value
     *
     * @access public
     * @param string $name The name of the attribute
     * @return true
     */
    public function attribute($name)
    {
        if (! isset($this->_attributes[$name])) {
            return null;
        }
        return $this->_attributes[$name];
    }


    /**
     * Set flag that this element had a close tag
     *
     * @access public
     */
    public function setHadCloseTag()
    {
        $this->_hadCloseTag = true;
    }


    /**
     * Set flag that this element was already processed by paragraph handling
     *
     * @access public
     */
    public function setParagraphHandled()
    {
        $this->_paragraphHandled = true;
    }

    /**
     * Get flag if this element was already processed by paragraph handling
     *
     * @access public
     * @return bool
     */
    public function paragraphHandled()
    {
        return $this->_paragraphHandled;
    }


    /**
     * Get flag if this element had a close tag
     *
     * @access public
     * @return bool
     */
    public function hadCloseTag()
    {
        return $this->_hadCloseTag;
    }


    /**
     * Determines whether a criterium matches this node
     *
     * @access public
     * @param string $criterium The criterium that is to be checked
     * @param mixed $value The value that is to be compared
     * @return bool True if this node matches that criterium
     */
    public function matchesCriterium($criterium, $value)
    {
        if ($criterium == 'tagName') {
            return ($value == $this->_name);
        }
        if ($criterium == 'needsTextNodeModification') {
            return (($this->getFlag ('opentag.before.newline', 'integer', BBCode::NEWLINE_PARSE) != BBCode::NEWLINE_PARSE || $this->getFlag ('opentag.after.newline', 'integer', BBCode::NEWLINE_PARSE) != BBCode::NEWLINE_PARSE || ($this->_hadCloseTag && ($this->getFlag ('closetag.before.newline', 'integer', BBCode::NEWLINE_PARSE) != BBCode::NEWLINE_PARSE || $this->getFlag ('closetag.after.newline', 'integer', BBCode::NEWLINE_PARSE) != BBCode::NEWLINE_PARSE))) == (bool)$value);
        }
        if (substr($criterium, 0, 5) == 'flag:') {
            $criterium = substr($criterium, 5);
            return ($this->getFlag($criterium) == $value);
        }
        if (substr($criterium, 0, 6) == '!flag:') {
            $criterium = substr($criterium, 6);
            return ($this->getFlag($criterium) != $value);
        }
        if (substr($criterium, 0, 6) == 'flag=:') {
            $criterium = substr($criterium, 6);
            return ($this->getFlag($criterium) === $value);
        }
        if (substr($criterium, 0, 7) == '!flag=:') {
            $criterium = substr($criterium, 7);
            return ($this->getFlag($criterium) !== $value);
        }
        return parent::matchesCriterium($criterium, $value);
    }


    /**
     * Get first child if it is a text node
     *
     * @access public
     * @return mixed
     */
    public function &firstChildIfText()
    {
        $ret =& $this->firstChild();
        if (is_null($ret)) {
            return $ret;
        }
        if ($ret->_type != Node::NODE_TEXT) {
            // DON'T DO $ret = null WITHOUT unset BEFORE!
            // ELSE WE WILL ERASE THE NODE ITSELF! EVIL!
            unset($ret);
            $ret = null;
        }
        return $ret;
    }


    /**
     * Get last child if it is a text node AND if this element had a close tag
     *
     * @access public
     * @return mixed
     */
    public function &lastChildIfText()
    {
        $ret =& $this->lastChild();
        if (is_null($ret)) {
            return $ret;
        }
        if ($ret->_type != Node::NODE_TEXT || !$this->_hadCloseTag) {
            // DON'T DO $ret = null WITHOUT unset BEFORE!
            // ELSE WE WILL ERASE THE NODE ITSELF! EVIL!
            if ($ret->_type != Node::NODE_TEXT && !$ret->hadCloseTag()) {
                $ret2 =& $ret->findPrevAdjentTextNodeHelper();
                unset($ret);
                $ret =& $ret2;
                unset($ret2);
            } else {
                unset($ret);
                $ret = null;
            }
        }
        return $ret;
    }


    /**
     * Find next adjent text node after close tag
     *
     * returns the node or null if none exists
     *
     * @access public
     * @return mixed
     */
    public function &findNextAdjentTextNode()
    {
        $ret = null;
        if (is_null($this->_parent)) {
            return $ret;
        }
        if (! $this->_hadCloseTag) {
            return $ret;
        }
        $ccount = count($this->_parent->_children);
        $found = false;
        for ($i = 0; $i < $ccount; $i++) {
            if ($this->_parent->_children[$i]->equals($this)) {
                $found = $i;
                break;
            }
        }
        if ($found === false) {
            return $ret;
        }
        if ($found < $ccount - 1) {
            if ($this->_parent->_children[$found+1]->_type == Node::NODE_TEXT) {
                return $this->_parent->_children[$found+1];
            }
            return $ret;
        }
        if ($this->_parent->_type == BBCode::NODE_ELEMENT && !$this->_parent->hadCloseTag()) {
            $ret =& $this->_parent->findNextAdjentTextNode();
            return $ret;
        }
        return $ret;
    }


    /**
     * Find previous adjent text node before open tag
     *
     * returns the node or null if none exists
     *
     * @access public
     * @return mixed
     */
    public function &findPrevAdjentTextNode()
    {
        $ret = null;
        if (is_null($this->_parent)) {
            return $ret;
        }
        $ccount = count($this->_parent->_children);
        $found = false;
        for ($i = 0; $i < $ccount; $i++) {
            if ($this->_parent->_children[$i]->equals($this)) {
                $found = $i;
                break;
            }
        }
        if ($found === false) {
            return $ret;
        }
        if ($found > 0) {
            if ($this->_parent->_children[$found-1]->_type == Node::NODE_TEXT) {
                return $this->_parent->_children[$found-1];
            }
            if (! $this->_parent->_children[$found-1]->hadCloseTag()) {
                $ret =& $this->_parent->_children[$found-1]->findPrevAdjentTextNodeHelper();
            }
            return $ret;
        }
        return $ret;
    }


    /**
     * Helper function for findPrevAdjentTextNode
     *
     * Looks at the last child node; if it's a text node, it returns it,
     * if the element node did not have an open tag, it calls itself
     * recursively.
     */
    public function & findPrevAdjentTextNodeHelper()
    {
        $lastnode =& $this->lastChild();
        if ($lastnode === null || $lastnode->_type == Node::NODE_TEXT) {
            return $lastnode;
        }
        if (! $lastnode->hadCloseTag()) {
            $ret =& $lastnode->findPrevAdjentTextNodeHelper();
        } else {
            $ret = null;
        }
        return $ret;
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
     * Validate code
     *
     * @access public
     * @param string $action The action which is to be called ('validate'
     *                       for first validation, 'validate_again' for
     *                       second validation (optional))
     * @return bool
     */
    public function validate($action = 'validate')
    {
        if ($action != 'validate' && $action != 'validate_again') {
            return false;
        }
        if ($this->_codeInfo['callback_type'] != 'simple_replace' && $this->_codeInfo['callback_type'] != 'simple_replace_single') {
            if (! is_callable($this->_codeInfo['callback_func'])) {
                return false;
            }

            if (($this->_codeInfo['callback_type'] == 'usecontent' || $this->_codeInfo['callback_type'] == 'usecontent?' || $this->_codeInfo['callback_type'] == 'callback_replace?') && count($this->_children) == 1 && $this->_children[0]->_type == Node::NODE_TEXT) {
                // we have to make sure the object gets passed on as a reference
                // if we do call_user_func(..., &$this) this will clash with PHP5
                $callArray = array($action, $this->_attributes, $this->_children[0]->content, $this->_codeInfo['callback_params']);
                $callArray[] =& $this;
                $res = call_user_func_array($this->_codeInfo['callback_func'], $callArray);
                if ($res) {
                    // ok, now, if we've got a usecontent type, set a flag that
                    // this may not be broken up by paragraph handling!
                    // but PLEASE do NOT change if already set to any other setting
                    // than BBCode::PARAGRAPH_ALLOW_BREAKUP because we could
                    // override e.g. BBCode::PARAGRAPH_BLOCK_ELEMENT!
                    $val = $this->getFlag('paragraph_type', 'integer', BBCode::PARAGRAPH_ALLOW_BREAKUP);
                    if ($val == BBCode::PARAGRAPH_ALLOW_BREAKUP) {
                        $this->_flags['paragraph_type'] = BBCode::PARAGRAPH_ALLOW_INSIDE;
                    }
                }
                return $res;
            }

            // we have to make sure the object gets passed on as a reference
            // if we do call_user_func(..., &$this) this will clash with PHP5
            $callArray = array($action, $this->_attributes, null, $this->_codeInfo['callback_params']);
            $callArray[] =& $this;
            return call_user_func_array($this->_codeInfo['callback_func'], $callArray);
        }
        return (bool)(!count($this->_attributes));
    }


    /**
     * Get replacement for this code
     *
     * @access public
     * @param string $subcontent The content of all sub-nodes
     * @return string
     */
    public function getReplacement($subcontent)
    {
        if ($this->_codeInfo['callback_type'] == 'simple_replace' || $this->_codeInfo['callback_type'] == 'simple_replace_single') {
            if ($this->_codeInfo['callback_type'] == 'simple_replace_single') {
                if (strlen($subcontent)) { // can't be!
                    return false;
                }
                return $this->_codeInfo['callback_params']['start_tag'];
            }
            return $this->_codeInfo['callback_params']['start_tag'] . $subcontent . $this->_codeInfo['callback_params']['end_tag'];
        }
        // else usecontent, usecontent? or callback_replace or callback_replace_single
        // => call function (the function is callable, determined in validate()!)

        // we have to make sure the object gets passed on as a reference
        // if we do call_user_func(..., &$this) this will clash with PHP5
        $callArray = array('output', $this->_attributes, $subcontent, $this->_codeInfo['callback_params']);
        $callArray[] =& $this;
        return call_user_func_array($this->_codeInfo['callback_func'], $callArray);
    }


    /**
     * Dump this node to a string
     *
     * @access protected
     * @return string
     */
    protected function dumpToString()
    {
        $str = 'bbcode "' . substr(preg_replace('/\s+/', ' ', $this->_name), 0, 40) . '"';
        if (count($this->_attributes)) {
            $attribs = array_keys($this->_attributes);
            sort($attribs);
            $str .= ' (';
            $i = 0;
            foreach ($attribs as $attrib) {
                if ($i != 0) {
                    $str .= ', ';
                }
                $str .= $attrib . '="';
                $str .= substr(preg_replace('/\s+/', ' ', $this->_attributes[$attrib]), 0, 10);
                $str .= '"';
                $i++;
            }
            $str .= ')';
        }
        return $str;
    }
}
