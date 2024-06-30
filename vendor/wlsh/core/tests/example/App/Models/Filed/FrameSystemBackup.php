<?php declare(strict_types=1);

 namespace Models\Filed;

Enum FrameSystemBackup:string
{
    /** @var string 数据表名称 */
    const string Table = 'frame_system_backup';

    /** @var string not null  */
    const string CrtDt = 'crt_dt';

    /** @var string not null  */
    const string FileMd5 = 'file_md5';

    /** @var string not null  */
    const string FileName = 'file_name';

    /** @var string not null  */
    const string FileSize = 'file_size';

    /** @var string not null  */
    const string Id = 'id';

}
