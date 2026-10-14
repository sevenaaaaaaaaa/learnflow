#!/usr/bin/env bash
# LearnFlow 双轨同步（唯一入口）
#   GitHub 轨：gitleaks 扫描 → git add -A（.gitignore 排除 data/ uploads/ .env* secrets*）→ commit → push origin main
#   服务器轨：rsync 工作区 → /www/wwwroot/learnflow/（排除运行时数据与密钥，服务器为源）→ 清缓存 + chown www:www
# 用法：bash deploy/sync.sh [-m "提交信息"] [--server-only] [--dry-run]
#   --server-only  跳过 git，只同步服务器
#   --dry-run      rsync -n 预演；不 commit/push、不清缓存/chown（任意分支可跑，非 main 仅警告）
#   正式部署只允许在 main 分支；ops/（gitignored）若非空，另行 rsync --update 到 /www/ops/learnflow/（Web 根之外，不删远端）
# 参数来源：docs/DEPLOY.md「二、代码同步（rsync）」
set -euo pipefail
cd "$(dirname "$0")/.."

HOST=172.96.253.73
SSH_PORT=28766
APP_DIR=/www/wwwroot/learnflow
OPS_DIR=/www/ops/learnflow   # tool-usage workspace; must stay outside the web root
SITE=https://nownexts.com/learnflow
GITLEAKS="${GITLEAKS:-$HOME/.local/bin/gitleaks}"

SERVER_ONLY=0; DRY_RUN=0
MSG="chore: sync $(date '+%Y-%m-%d %H:%M')"
while [ $# -gt 0 ]; do
  case "$1" in
    --server-only) SERVER_ONLY=1 ;;
    --dry-run) DRY_RUN=1 ;;
    -m) shift; MSG="${1:?-m 需要提交信息}" ;;
    -h|--help) sed -n '2,9p' "$0"; exit 0 ;;
    *) echo "未知参数: $1（用法见 --help）"; exit 1 ;;
  esac
  shift
done

step() { printf "\n\033[1;36m=== %s ===\033[0m\n" "$*"; }

# ─── 0. 只允许从 main 部署（--dry-run 任意分支可跑，仅警告） ───
CUR_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [ "$CUR_BRANCH" != main ]; then
  if [ "$DRY_RUN" = 1 ]; then
    echo "⚠ 当前分支 ${CUR_BRANCH}，非 main：dry-run 仅预演，正式部署只允许从 main 进行"
  else
    echo "✗ 当前分支 ${CUR_BRANCH}，部署只允许从 main 进行；功能分支请先合并"; exit 1
  fi
fi

# ─── 1. GitHub 轨 ───
if [ "$SERVER_ONLY" = 1 ]; then
  echo "--server-only：跳过 git"
elif [ "$DRY_RUN" = 1 ]; then
  step "git（dry-run：只展示待提交变更，不 commit/push）"
  git status --short
  "$GITLEAKS" git --pre-commit --redact --no-banner || { echo "gitleaks 发现泄漏（未暂存变更），中止"; exit 1; }
else
  step "gitleaks 扫描 + git 提交推送"
  if [ -n "$(git status --porcelain)" ]; then
    git add -A
    if ! "$GITLEAKS" git --pre-commit --staged --redact --no-banner; then
      echo "gitleaks 发现泄漏，已中止（变更仍在暂存区，请修复或 git reset 后重试）"; exit 1
    fi
    git commit -m "$MSG" | head -2
  else
    echo "无未提交变更（跳过 commit）"
  fi
  git push origin main
fi

# ─── 2. 服务器轨：rsync（排除项与 docs/DEPLOY.md 一致；data/ uploads/ .env 为服务器运行时数据，永不覆盖/删除） ───
step "rsync → ${HOST}:${APP_DIR}/"
RSYNC_FLAGS="-az"; [ "$DRY_RUN" = 1 ] && RSYNC_FLAGS="-azn --itemize-changes"
rsync $RSYNC_FLAGS --delete -e "ssh -p ${SSH_PORT}" \
  --exclude='.git/' --exclude='data/' --exclude='uploads/' \
  --exclude='vendor/' --exclude='node_modules/' \
  --exclude='.env*' --exclude='.user.ini' \
  --exclude='secrets*' --exclude='docs/secrets-local.md' \
  --exclude='.DS_Store' --exclude='*.bak*' \
  --exclude='deploy/' \
  --exclude='CLAUDE.md' --exclude='AGENTS.md' --exclude='.gitleaksignore' \
  --exclude='tests/' --exclude='docs/' \
  --exclude=/ops/ \
  ./ "root@${HOST}:${APP_DIR}/"

# ─── 2b. ops/ → 服务器 Web 根之外（--update，只增不删；空则跳过） ───
if [ -d ops ] && [ -n "$(find ops -mindepth 1 ! -name .DS_Store -print -quit)" ]; then
  step "ops/ → ${HOST}:${OPS_DIR}/（--update，只增不删）"
  OPS_FLAGS="-az"; [ "$DRY_RUN" = 1 ] && OPS_FLAGS="-azn --itemize-changes"
  rsync $OPS_FLAGS --update --exclude='.DS_Store' -e "ssh -p ${SSH_PORT}" \
    --rsync-path="mkdir -p ${OPS_DIR} && rsync" \
    ops/ "root@${HOST}:${OPS_DIR}/"
fi

if [ "$DRY_RUN" = 1 ]; then
  echo "dry-run 完成：未清缓存、未 chown"; exit 0
fi

# ─── 3. 服务器收尾：清缓存 + 属主回 www:www（docs/DEPLOY.md 约定） ───
step "清缓存 + chown"
ssh -p "$SSH_PORT" "root@${HOST}" "rm -f ${APP_DIR}/data/cache/*.cache; chown -R www:www ${APP_DIR}"

# ─── 4. 线上验收 ───
step "线上验收"
CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "${SITE}/" || true)
echo "${SITE}/ → HTTP ${CODE}"
# TODO: 确认根入口（后台登录）期望状态码；目前 200/302 视为通过
case "$CODE" in 200|302) echo "同步完成" ;; *) echo "线上验收未通过，请人工检查"; exit 1 ;; esac
