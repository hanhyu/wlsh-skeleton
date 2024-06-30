<?php declare(strict_types=1);

 namespace Models\Filed;

Enum UserLogView:string
{
    /** @var string 数据表名称 */
    const string Table = 'user_log_view';

    /** @var string not null  */
    const string Id = 'id';

    /** @var string null 用户登录时间 */
    const string LoginDt = 'login_dt';

    /** @var string null  */
    const string LoginIp = 'login_ip';

    /** @var string null 用户退出时间 */
    const string LogoutDt = 'logout_dt';

    /** @var string not null 用户ID */
    const string UserId = 'user_id';

    /** @var string null  */
    const string UserName = 'user_name';

}
