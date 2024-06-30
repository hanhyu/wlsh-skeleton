<?php declare(strict_types=1);

 namespace Models\Filed;

Enum FrameSystemMenu:string
{
    /** @var string 数据表名称 */
    const string Table = 'frame_system_menu';

    /** @var string not null  */
    const string Icon = 'icon';

    /** @var string not null  */
    const string Id = 'id';

    /** @var string not null  */
    const string Level = 'level';

    /** @var string not null  */
    const string Name = 'name';

    /** @var string not null  */
    const string UpId = 'up_id';

    /** @var string not null  */
    const string Url = 'url';

}
