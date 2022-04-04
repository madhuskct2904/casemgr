<?php

namespace App\Library\Box\Spout\Reader;

/**
 * Interface SheetInterface
 *
 * @package App\Library\Box\Spout\Reader
 */
interface SheetInterface
{
    /**
     * Returns an iterator to iterate over the sheet's rows.
     *
     * @return \Iterator
     */
    public function getRowIterator();
}
