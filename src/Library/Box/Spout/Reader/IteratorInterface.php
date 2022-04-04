<?php

namespace App\Library\Box\Spout\Reader;

/**
 * Interface IteratorInterface
 *
 * @package App\Library\Box\Spout\Reader
 */
interface IteratorInterface extends \Iterator
{
    /**
     * Cleans up what was created to iterate over the object.
     *
     * @return void
     */
    public function end();
}
