# LearnFlow · Cloudflare 配置（同步自 OpenFlow）

> 部署形态为**子路径** `nownexts.com/learnflow`（非独立子域名），因此与 OpenFlow 共用同一个
> DNS 记录与 SSL 配置，无需为 LearnFlow 单独做任何边缘配置。

## 已为本项目做好的配置

| 项 | 值 |
|---|---|
| 域名 | 复用 `nownexts.com`（A 记录 → 172.96.253.73，已代理 proxied: true） |
| 边缘证书 | `nownexts.com` 的 Universal SSL（`*.nownexts.com` 通配符无需单独申请） |
| SSL 模式 | 继承 zone 配置 `full`（非 strict——源站证书主机名不匹配也可接受） |
| 回源 | `nownexts.com/learnflow/*` → Apache docroot 下 `learnflow/` 子目录（沿用 OpenFlow vhost） |

## Zone 信息（与 OpenFlow 同 zone）

- Zone ID：`8135597542c2723a06a91a7e14a6e747`（账号 NowX，账户 ID 与 R2 endpoint 前缀一致）
- API Token：见 `docs/secrets-local.md`（gitignored）

## 常用操作（与 OpenFlow 一致）

```bash
# 清理指定 URL 缓存
curl -s -X POST -H "Authorization: Bearer $CF_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{"files":["https://nownexts.com/learnflow/","https://nownexts.com/learnflow/assets/xxx.css"]}' \
  "https://api.cloudflare.com/client/v4/zones/$ZONE/purge_cache"

# 全站清缓存（慎用）
curl -s -X POST -H "Authorization: Bearer $CF_TOKEN" \
  -H "Content-Type: application/json" --data '{"purge_everything":true}' \
  "https://api.cloudflare.com/client/v4/zones/$ZONE/purge_cache"
```

## 静态资源（R2 + Workers）约定

OpenFlow 的静态资产走 `r2-assets` Worker（`assets/*`）、视频走 `r2-media` Worker
（`media/*`，支持 Range/206 分段）。LearnFlow 若需要同样的资产链路：

1. 复用现有桶 `nownexts-static`（键前缀建议 `learnflow/assets/`），或自建桶
2. 复用 r2-media Worker 逻辑：键前缀已固定为 `media/`，视频放 `media/learnflow/xxx.mp4`
3. Workers 部署脚本在 OpenFlow 仓 `deploy/`（`deploy-r2-media.py`、`upload-r2-video.py`）

## 注意

- 高频 curl 会被 CF 挑战页拦截（浏览器正常）——自动化验证时注意
- `.env`、`data/` 已被服务器 Apache 拒绝直连（沿用 OpenFlow .htaccess 规则）
