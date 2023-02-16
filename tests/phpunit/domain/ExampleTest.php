<?php
declare(strict_types=1);

namespace domain;

use Domain\Api\ExampleDomain;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testGetList()
    {
        $res = (new ExampleDomain())->getList();
        $this->assertEquals('hello world', $res);
        $this->assertEquals('hello world1', $res);
    }

}