<?php

namespace App\Library\Box\Spout\Reader\XLSX;

use App\Library\Box\Spout\Reader\SheetInterface;

/**
 * Class Sheet
 * Represents a sheet within a XLSX file
 *
 * @package App\Library\Box\Spout\Reader\XLSX
 */
class Sheet implements SheetInterface
{
    /** @var \App\Library\Box\Spout\Reader\XLSX\RowIterator To iterate over sheet's rows */
    protected $rowIterator;

    /** @var int Index of the sheet, based on order in the workbook (zero-based) */
    protected $index;

    /** @var string Name of the sheet */
    protected $name;

    /**
     * @param string $filePath Path of the XLSX file being read
     * @param string $sheetDataXMLFilePath Path of the sheet data XML file as in [Content_Types].xml
     * @param Helper\SharedStringsHelper Helper to work with shared strings
     * @param bool $shouldFormatDates Whether date/time values should be returned as PHP objects or be formatted as strings
     * @param int $sheetIndex Index of the sheet, based on order in the workbook (zero-based)
     * @param string $sheetName Name of the sheet
     */
    public function __construct($filePath, $sheetDataXMLFilePath, $sharedStringsHelper, $shouldFormatDates, $sheetIndex, $sheetName)
    {
        $this->rowIterator = new RowIterator($filePath, $sheetDataXMLFilePath, $sharedStringsHelper, $shouldFormatDates);
        $this->index = $sheetIndex;
        $this->name = $sheetName;
    }

    /**
     * @api
     * @return \App\Library\Box\Spout\Reader\XLSX\RowIterator
     */
    public function getRowIterator()
    {
        return $this->rowIterator;
    }

    /**
     * @api
     * @return int Index of the sheet, based on order in the workbook (zero-based)
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * @api
     * @return string Name of the sheet
     */
    public function getName()
    {
        return $this->name;
    }
}
