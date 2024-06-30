<?php declare(strict_types=1);

 namespace Models\Filed;

Enum FrameSystemRouter:string
{
    /** @var string 数据表名称 */
    const string Table = 'frame_system_router';

    /** @var string not null  */
    const string Action = 'action';

    /** @var string not null  */
    const string Auth = 'auth';

    /** @var string not null  */
    const string Comment = 'comment';

    /** @var string not null  */
    const string Id = 'id';

    /** @var string not null 路由所属类别 */
    const string MenuId = 'menu_id';

    /** @var string not null  */
    const string Method = 'method';

    /** @var string not null 请求路由名称 */
    const string Name = 'name';

    /** @var string not null 路由类型，默认0后台路由，1前台路由 */
    const string Type = 'type';

    /** @var string not null  */
    const string Url = 'url';

}
