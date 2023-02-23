# wlsh-skeleton

**1.** docker模式运行

```shell
cd docker
docker-compose up -d
curl http://127.0.0.1:9770/api/example/getList
```

**2.** swoole-cli模式运行（不依赖本地php环境）
> https://github.com/swoole/swoole-cli 下载对应系统的二进制包

启动：swoole-cli service.php start dev
