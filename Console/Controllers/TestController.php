<?php declare(strict_types=1);

namespace Console\Controllers;


use Domain\Api\ExampleDomain;

class TestController
{
    public function addAction($a, $b): int
    {
        return $a + $b;
    }

    public function getListAction(): string
    {
        return (new ExampleDomain())->getList();
    }

}
