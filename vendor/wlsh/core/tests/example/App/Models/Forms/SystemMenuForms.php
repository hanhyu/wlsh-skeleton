<?php
declare(strict_types=1);


namespace Models\Forms;

class SystemMenuForms
{
    public static array $getMenuList = [
        'curr_page' => 'Required|IntGe:1|Alias:当前页',
        'page_size' => 'Required|IntGe:1|Alias:每页显示多少条',
    ];

}