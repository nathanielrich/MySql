<?php

namespace NRich\MySql\Helper;

/**
 * Class IsNULL
 * @package Njee\MySql
 */
class IsNULL extends Escape {

    public $empty = false;

    public $datetimeZero = false;

    /**
     * @param bool|false $empty
     * @param bool|false $datetimeZero
     */
    public function __construct($empty = false, $datetimeZero = false)
    {
        $this->empty = $empty;
        $this->datetimeZero = $datetimeZero;
    }

}