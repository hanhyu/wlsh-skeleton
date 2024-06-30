<?php
declare(strict_types=1);

namespace Models\Mysql;

use Models\Filed\FrameSystemUser;
use Wlsh\AbstractPdo;

class SystemUserMysql extends AbstractPdo
{
    /**
     * @param array $post
     * @return int
     */
    public function setUser(array $post): int
    {
        return self::getDb()->from(FrameSystemUser::Table)
            ->insert([
                FrameSystemUser::Name   => $post['name'],
                FrameSystemUser::Pwd    => password_hash($post['pwd'], PASSWORD_DEFAULT),
                FrameSystemUser::Status => 10,
                FrameSystemUser::CrtDt  => date('y-m-d H:i:s'),
                FrameSystemUser::Remark => $post['remark'] ?? '',
            ]);
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function getUserList(array $data): array
    {
        return self::getDb()->from(FrameSystemUser::Table)
            ->where($data['where'])
            ->orderBy(FrameSystemUser::Id, 'DESC')
            ->offset($data['curr_data'])
            ->limit($data['page_size'])
            ->fetchAll();
    }

    /**
     * UserDomain: hanhyu
     * Date: 19-6-16
     * Time: 下午9:00
     * @return int
     * @todo mysql count 性能下降100倍
     */
    public function getListCount(): int
    {
        return self::getDb()->from(FrameSystemUser::Table)->select('count(*)')->fetchColumn();
        /*return (int)self::getDb()
            ->from('information_schema.`TABLES`')
            ->where('TABLE_NAME', $this->table)
            ->select('TABLE_ROWS', true)
            ->fetchColumn();*/
    }

    public function delUser(int $id): int
    {
        return self::getDb()->from(FrameSystemUser::Table)->where([FrameSystemUser::Id => $id])->delete();
    }

    /**
     * @param int $id
     *
     * @return array|false
     */
    public function getUser(int $id): array|false
    {
        return self::getDb()->from(FrameSystemUser::Table)
            ->where(FrameSystemUser::Id, '=', $id)
            ->select([
                FrameSystemUser::Id,
                FrameSystemUser::Status,
                FrameSystemUser::Remark,
            ])
            ->fetchOne();
    }

    /**
     * @param array $post
     *
     * @return int
     */
    public function editUser(array $post): int
    {
        return self::getDb()->from(FrameSystemUser::Table)
            ->where(FrameSystemUser::Id, '=', $post['id'])
            ->update([FrameSystemUser::Status => $post['status'], FrameSystemUser::Remark => $post['remark']]);
    }

    /**
     * 获取用户基本信息
     *
     * @param string $name 用户名
     *
     * @return array|false ['id','name','status','pwd']
     */
    public function getInfo(string $name): array|false
    {
        return self::getDb()->from(FrameSystemUser::Table)
            ->where([FrameSystemUser::Name => $name])
            ->limit(1)
            ->select([
                FrameSystemUser::Id,
                FrameSystemUser::Status,
                FrameSystemUser::Name,
                FrameSystemUser::Pwd,
            ])
            ->fetchOne();
    }

    /**
     * UserDomain: hanhyu
     * Date: 19-6-16
     * Time: 下午9:04
     *
     * @param array $uid
     *
     * @return array
     */
    public function getNameById(array $uid): array
    {
        return self::getDb()->from(FrameSystemUser::Table)
            ->whereIn(FrameSystemUser::Id, $uid)
            ->select([
                FrameSystemUser::Id,
                FrameSystemUser::Name,
            ])
            ->fetchAll();
    }

    public function testNameById(int $id): string
    {
        return self::getDb()->from(FrameSystemUser::Table)
            ->where(FrameSystemUser::Id, '=', $id)
            ->select(FrameSystemUser::Name)
            ->fetchColumn();
    }

    /**
     * 根据用户uid获取密码
     * UserDomain: hanhyu
     * Date: 2019/8/14
     * Time: 下午4:10
     *
     * @param int $uid
     *
     * @return string
     */
    public function getPwdByUid(int $uid): string
    {
        return self::getDb()->from(FrameSystemUser::Table)
            ->where(FrameSystemUser::Id, '=', $uid)
            ->select(FrameSystemUser::Pwd)
            ->fetchColumn();
    }

    /**
     * 用户修改密码
     * UserDomain: hanhyu
     * Date: 2019/8/14
     * Time: 下午4:11
     *
     * @param array $data
     *
     * @return int
     */
    public function editPwd(array $data): int
    {
        return self::getDb()->from(FrameSystemUser::Table)
            ->where(FrameSystemUser::Id, '=', $data['uid'])
            ->update([FrameSystemUser::Pwd => password_hash($data['new_pwd'], PASSWORD_DEFAULT)]);
    }

    /**
     * 判断用户名是否存在
     * UserDomain: hanhyu
     * Date: 2019/8/18
     * Time: 下午7:58
     *
     * @param string $name
     *
     * @return bool true已存在，false不存在
     */
    public function existName(string $name): bool
    {
        $id = self::getDb()->from(FrameSystemUser::Table)
            ->where([FrameSystemUser::Name => $name])
            ->limit(1)
            ->select(FrameSystemUser::Id)
            ->fetchOne();

        return $id !== false;
    }

}
