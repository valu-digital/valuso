<?php

namespace ValuSoTest\Mock;

class MockIdentity extends \ArrayObject
{
    public $identity;

    public function __construct($identity)
    {
        parent::__construct($identity);
    }

    public function toArray()
    {
        return $this->getArrayCopy();
    }
}