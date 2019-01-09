<?php

namespace NRich\MySql\Helper;

/**
 * Class NoEscape
 * @package Njee\MySql
 */
class NoEscape extends Escape {

    public $string;

    public function __construct($string)
    {
        $this->string = $string;
    }

}