<?php declare(strict_types=1);

 namespace Models\Filed;

Enum FrameSystemMsg:string
{
    /** @var string 数据表名称 */
    const string Table = 'frame_system_msg';

    /** @var string not null  */
    const string Content = 'content';

    /** @var string not null  */
    const string CrtDt = 'crt_dt';

    /** @var string not null  */
    const string Id = 'id';

    /** @var string not null  */
    const string UptDt = 'upt_dt';

    /** @var string not null  */
    const string UptId = 'upt_id';

}
