<?php
/**
 * This file is part of Handlebars-php
 * Base on mustache-php https://github.com/bobthecow/mustache.php
 * re-write to use with handlebars
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
 * Handlebars parser (infact its a mustache parser)
 * This class is responsible for turning raw template source into a set of Mustache tokens.
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

class Parser
{
    /**
     * Process array of tokens and convert them into parse tree
     *
     * @param array $tokens Set of
     *
     * @return array Token parse tree
     */
    public function parse(array $tokens = array())
    {
        return $this->_buildTree(new \ArrayIterator($tokens));
    }

    /**
     * Helper method for recursively building a parse tree.
     *
     * @param ArrayIterator $tokens Stream of  tokens
     *
     * @return array Token parse tree
     *
     * @throws LogicException when nesting errors or mismatched section tags are encountered.
     */
    private function _buildTree(\ArrayIterator $tokens)
    {
        $stack = array();

        do {
            $token = $tokens->current();
            $tokens->next();

            if ($token === null) {
                continue;
            } else {
                switch ($token[Tokenizer::TYPE]) {
                case Tokenizer::T_END_SECTION:
                    $newNodes = array ();
                    $continue = true;
                    do {
                        $result = array_pop($stack);
                        if ($result === null) {
                            throw new \LogicException('Unexpected closing tag: /'. $token[Tokenizer::NAME]);
                        }

                        if (!array_key_exists(Tokenizer::NODES, $result)
                            && isset($result[Tokenizer::NAME])
                            && $result[Tokenizer::NAME] == $token[Tokenizer::NAME]
                        ) {
                            $result[Tokenizer::NODES] = $newNodes;
                            $result[Tokenizer::END]   = $token[Tokenizer::INDEX];
                            array_push($stack, $result);
                            break 2;
                        } else {
                            array_unshift($newNodes, $result);
                        }
                    } while (true);
                    break;
                default:
                    array_push($stack, $token);
                }
            }

        } while ($tokens->valid());

        return $stack;

    }
}
