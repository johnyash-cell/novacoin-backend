#!/usr/bin/env bash
# One-shot: push CPANEL_* secrets to johnyash-cell/novacoin-backend.
# Prereq: gh auth login (repo admin on that GitHub repo).
# Usage:
#   export CPANEL_SSH_PRIVATE_KEY_FILE="/path/to/unlocked/id_rsa"
#   ./scripts/deploy/set-github-cpanel-secrets.sh

set -euo pipefail

REPO="${GITHUB_REPO:-johnyash-cell/novacoin-backend}"
KEY_FILE="${CPANEL_SSH_PRIVATE_KEY_FILE:-}"

if ! command -v gh >/dev/null 2>&1; then
  echo "ERROR: gh CLI missing. Install: brew install gh"
  exit 1
fi

if ! gh auth status >/dev/null 2>&1; then
  echo "ERROR: not logged in. Run: gh auth login"
  exit 1
fi

if [[ -z "$KEY_FILE" || ! -f "$KEY_FILE" ]]; then
  echo "ERROR: set CPANEL_SSH_PRIVATE_KEY_FILE to unlocked OpenSSH private key path"
  exit 1
fi

if grep -q "ENCRYPTED" "$KEY_FILE"; then
  echo "ERROR: key is still passphrase-encrypted. Unlock first (ssh-keygen -p)."
  exit 1
fi

gh secret set CPANEL_SSH_HOST --repo "$REPO" --body "server367.web-hosting.com"
gh secret set CPANEL_SSH_PORT --repo "$REPO" --body "21098"
gh secret set CPANEL_SSH_USER --repo "$REPO" --body "novamdrw"
gh secret set CPANEL_DEPLOY_PATH --repo "$REPO" --body "/home/novamdrw/api.novacoinsholdings.com"
gh secret set CPANEL_PHP_BIN --repo "$REPO" --body "/opt/alt/php84/usr/bin/php"
gh secret set CPANEL_SSH_PRIVATE_KEY --repo "$REPO" < "$KEY_FILE"

echo "OK: CPANEL_* secrets set on $REPO"
echo "Re-run failed workflow: gh run rerun \$(gh run list --repo $REPO --workflow=deploy-cpanel.yml --limit 1 --json databaseId -q '.[0].databaseId')"
