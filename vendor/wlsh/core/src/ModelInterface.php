<?php


namespace Wlsh;


interface ModelInterface
{
    public static function getInstance(): static;

    public static function getDb(): object;
}
