<?php declare(strict_types=1);

 namespace Models\Filed;

Enum FrameSystemUserLog:string
{
    /** @var string 数据表名称 */
    const string Table = 'frame_system_user_log';

    /** @var string not null  */
    const string Id = 'id';

    /** @var string null 用户登录时间 */
    const string LoginDt = 'login_dt';

    /** @var string not null  */
    const string LoginIp = 'login_ip';

    /** @var string null 用户退出时间 */
    const string LogoutDt = 'logout_dt';

    /** @var string not null 用户ID */
    const string UserId = 'user_id';

}
