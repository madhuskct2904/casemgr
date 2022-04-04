<?php

namespace App\Library\Box\Spout\Common\Escaper;

/**
 * Interface EscaperInterface
 *
 * @package App\Library\Box\Spout\Common\Escaper
 */
interface EscaperInterface
{
    /**
     * Escapes the given string to make it compatible with PHP
     *
     * @param string $string The string to escape
     * @return string The escaped string
     */
    public function escape($string);

    /**
     * Unescapes the given string to make it compatible with PHP
     *
     * @param string $string The string to unescape
     * @return string The unescaped string
     */
    public function unescape($string);
}
