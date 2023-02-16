<?php


namespace Wlsh;


interface ModelInterface
{
    public static function getInstance(): static;

    public static function setDb(): string;

    public static function getDb(): object;
}
