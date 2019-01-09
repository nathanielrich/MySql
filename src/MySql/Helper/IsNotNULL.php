<?php

namespace NRich\MySql\Helper;

/**
 * Class IsNotNULL
 * @package Njee\MySql
 */
class IsNotNULL extends Escape {

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