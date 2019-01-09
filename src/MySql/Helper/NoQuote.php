<?php

namespace NRich\MySql\Helper;

/**
 * Class NoQuote
 * @package Njee\MySql
 */
class NoQuote extends Escape {

    public $string;

    public function __construct($string)
    {
        $this->string = $string;
    }

}