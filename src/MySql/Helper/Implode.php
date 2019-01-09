<?php

namespace NRich\MySql\Helper;

/**
 * Class NoEscape
 * @package Njee\MySql
 */
class Implode extends Escape {

    public $array;

    public $delimiter;

    public function __construct(array $array, $delimiter = ',')
    {
        $this->delimiter = $delimiter;
        $this->array = $array;
    }

}