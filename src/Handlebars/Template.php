<?php
/**
 * This file is part of Handlebars-php
 * Base on mustache-php https://github.com/bobthecow/mustache.php
 *
 * PHP version 5.3
 *
 * @category  Xamin
 * @package   Handlebars
 * @author    fzerorubigd <fzerorubigd@gmail.com>
 * @copyright 2012 (c) ParsPooyesh Co
 * @license   GPLv3 <http://www.gnu.org/licenses/gpl-3.0.html>
 * @version   GIT: $Id$
 * @link      http://xamin.ir
 */


/**
 * Handlebars base template
 * contain some utility method to get context and helpers
 *
 * @category  Xamin
 * @package   Handlebars
 * @author    fzerorubigd <fzerorubigd@gmail.com>
 * @copyright 2012 (c) ParsPooyesh Co
 * @license   GPLv3 <http://www.gnu.org/licenses/gpl-3.0.html>
 * @version   Release: @package_version@
 * @link      http://xamin.ir
 */
namespace Handlebars;

class Template
{
    /**
     * @var Handlebars
     */
    protected $handlebars;


    protected $tree = array();

    protected $source = '';

    /**
     * @var array Run stack
     */
    private $_stack = array();

    /**
     * Handlebars template constructor
     *
     * @param Handlebars $engine handlebar engine
     * @param array             $tree   Parsed tree
     * @param string            $source Handlebars source
     */
    public function __construct(Handlebars $engine, $tree, $source)
    {
        $this->handlebars = $engine;
        $this->tree = $tree;
        $this->source = $source;
        array_push($this->_stack, array (0, $this->getTree(), false));
    }

    /**
     * Get current tree
     *
     * @return array
     */
    public function getTree()
    {
        return $this->tree;
    }

    /**
     * Get current source
     *
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Get current engine associated with this object
     *
     * @return Handlebars
     */
    public function getEngine()
    {
        return $this->handlebars;
    }

    /**
     * set stop token for render and discard method
     *
     * @param string $token token to set as stop token or false to remove
     *
     * @return void
     */

    public function setStopToken($token)
    {
        $topStack = array_pop($this->_stack);
        $topStack[2] = $token;
        array_push($this->_stack, $topStack);
    }

    /**
     * get current stop token
     *
     * @return string|false
     */

    public function getStopToken()
    {
        $topStack = end($this->_stack);
        return $topStack[2];
    }
    /**
     * Render top tree
     *
     * @param mixed $context current context
     *
     * @return string
     */
    public function render($context)
    {
        if (!$context instanceof Context) {
            $context = new Context($context);
        }
        $topTree = end($this->_stack); //This method (render) never pop a value from stack
        list($index ,$tree, $stop) = $topTree;

        $buffer = '';
        while (array_key_exists($index, $tree)) {
            $current = $tree[$index];
            $index++;
            //if the section is exactly like waitFor
            if (is_string($stop)
                && $current[Tokenizer::TYPE] == Tokenizer::T_ESCAPED
                && $current[Tokenizer::NAME] === $stop
            ) {
                break;
            }
            switch ($current[Tokenizer::TYPE]) {
            case Tokenizer::T_SECTION :
                $newStack = isset($current[Tokenizer::NODES]) ? $current[Tokenizer::NODES] : array();
                array_push($this->_stack, array(0, $newStack, false));
                $buffer .= $this->_section($context, $current);
                array_pop($this->_stack);
                break;
            case Tokenizer::T_INVERTED :
                $newStack = isset($current[Tokenizer::NODES]) ? $current[Tokenizer::NODES] : array();
                array_push($this->_stack, array(0, $newStack, false));
                $buffer .= $this->_inverted($context, $current);
                array_pop($this->_stack);
                break;
            case Tokenizer::T_COMMENT :
                $buffer .= '';
                break;
            case Tokenizer::T_PARTIAL:
            case Tokenizer::T_PARTIAL_2:
                $buffer .= $this->_partial($context, $current);
                break;
            case Tokenizer::T_UNESCAPED:
            case Tokenizer::T_UNESCAPED_2:
                $buffer .= $this->_variables($context, $current, false);
                break;
            case Tokenizer::T_ESCAPED:
                $buffer .= $this->_variables($context, $current, true);
                break;
            case Tokenizer::T_TEXT:
                $buffer .= $current[Tokenizer::VALUE];
                break;
            default:
                throw new \RuntimeException('Invalid node type : ' . json_encode($current));
            }
        }
        if ($stop) {
            //Ok break here, the helper should be aware of this.
            $newStack = array_pop($this->_stack);
            $newStack[0] = $index;
            $newStack[2] = false; //No stop token from now on
            array_push($this->_stack, $newStack);
        }
        return $buffer;
    }

    /**
     * Discard top tree
     *
     * @param mixed $context current context
     *
     * @return string
     */
    public function discard($context)
    {
        if (!$context instanceof Context) {
            $context = new Context($context);
        }
        $topTree = end($this->_stack); //This method never pop a value from stack
        list($index ,$tree, $stop) = $topTree;
        while (array_key_exists($index, $tree)) {
            $current = $tree[$index];
            $index++;
            //if the section is exactly like waitFor
            if (is_string($stop)
                && $current[Tokenizer::TYPE] == Tokenizer::T_ESCAPED
                && $current[Tokenizer::NAME] === $stop
            ) {
                break;
            }
        }
        if ($stop) {
            //Ok break here, the helper should be aware of this.
            $newStack = array_pop($this->_stack);
            $newStack[0] = $index;
            $newStack[2] = false;
            array_push($this->_stack, $newStack);
        }
        return '';
    }

    /**
     * Provide tag parsing method for helpers
     */
    
    public function htmlArgsToArray ($text) {
        $attributes = [];
        $pattern = '#(?(DEFINE)
            (?<name>[a-zA-Z][a-zA-Z0-9-:]*)
            (?<value_double>"[^"]+")
            (?<value_single>\'[^\']+\')
            (?<value_none>[^\s>]+)
            (?<value>((?&value_double)|(?&value_single)|(?&value_none)))
        )
        (?<n>(?&name))(=(?<v>(?&value)))?#xs';
 
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[$match['n']] = isset($match['v'])
                    ? trim($match['v'], '\'"')
                    : null;
            }
        }
        return $attributes;
    }

    /**
     * Process section nodes
     *
     * @param Context $context current context
     * @param array              $current section node data
     *
     * @return string the result
     */
    private function _section(Context $context, $current)
    {
        $helpers = $this->handlebars->getHelpers();
        $sectionName = $current[Tokenizer::NAME];
        if ($helpers->has($sectionName)) {
            if (isset($current[Tokenizer::END])) {
                $source = substr(
                    $this->getSource(),
                    $current[Tokenizer::INDEX],
                    $current[Tokenizer::END] - $current[Tokenizer::INDEX]
                );
            } else {
                $source = '';
            }
            $params = array(
                $this,  //First argument is this template
                $context, //Secound is current context
                $current[Tokenizer::ARGS],  //Arguments
                $source
                );
            $return = call_user_func_array($helpers->$sectionName, $params);
            if ($return instanceof String) {
                return $this->handlebars->loadString($return)->render($context);
            } else {
                return $return;
            }
        } elseif (trim($current[Tokenizer::ARGS]) == '') {
            //Fallback for mustache style each/with/for just if there is no argument at all.
            try {
                $sectionVar = $context->get($sectionName, true);
            } catch (InvalidArgumentException $e) {
                throw new \RuntimeException($sectionName . ' is not registered as a helper');
            }
            $buffer = '';
            if (is_array($sectionVar) || $sectionVar instanceof Traversable) {
                foreach ($sectionVar as $d) {
                    $context->push($d);
                    $buffer .= $this->render($context);
                    $context->pop();
                }
            } elseif (is_object($sectionVar)) {
                //Act like with
                $context->push($sectionVar);
                $buffer = $this->render($context);
                $context->pop();
            } elseif ($sectionVar) {
                $buffer = $this->render($context);
            }
            return $buffer;
        } else {
            throw new \RuntimeException($sectionName . ' is not registered as a helper');
        }
    }

    /**
     * Process inverted section
     *
     * @param Context $context current context
     * @param array              $current section node data
     *
     * @return string the result
     */
    private function _inverted(Context $context, $current)
    {
        $sectionName = $current[Tokenizer::NAME];
        $data = $context->get($sectionName);
        if (!$data) {
            return $this->render($context);
        } else {
            //No need to disacard here, since itshas no else
            return '';
        }
    }

    /**
     * Process partial section
     *
     * @param Context $context current context
     * @param array              $current section node data
     *
     * @return string the result
     */
    private function _partial($context, $current)
    {
        $partial = $this->handlebars->loadPartial($current[Tokenizer::NAME]);

        if ( $current[Tokenizer::ARGS] ) {
            $context = $context->get($current[Tokenizer::ARGS]);
        }

        return $partial->render($context);
    }

    /**
     * Process partial section
     *
     * @param Context $context current context
     * @param array              $current section node data
     * @param boolean            $escaped escape result or not
     *
     * @return string the result
     */
    private function _variables($context, $current, $escaped)
    {
        $value = $context->get($current[Tokenizer::NAME]);
        if ($escaped) {
            $args = $this->handlebars->getEscapeArgs();
            array_unshift($args, $value);
            $values = array_values($args);
            if (is_array($values[0])) {
                return '';
            }
            $value = call_user_func_array($this->handlebars->getEscape(), $values);
        }
        return $value;
    }


}
