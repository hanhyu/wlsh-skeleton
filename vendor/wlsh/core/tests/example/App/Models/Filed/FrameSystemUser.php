<?php declare(strict_types=1);

 namespace Models\Filed;

Enum FrameSystemUser:string
{
    /** @var string 数据表名称 */
    const string Table = 'frame_system_user';

    /** @var string null  */
    const string CrtDt = 'crt_dt';

    /** @var string not null  */
    const string Id = 'id';

    /** @var string not null  */
    const string Name = 'name';

    /** @var string not null  */
    const string Pwd = 'pwd';

    /** @var string not null  */
    const string Remark = 'remark';

    /** @var string not null 用户状态：默认10启用、20禁用 */
    const string Status = 'status';

}
