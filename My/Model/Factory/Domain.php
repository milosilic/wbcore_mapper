<?php

namespace Bgw\Core;


abstract class My_Model_Factory_Domain
{

    abstract public function createObject(array $data);
}