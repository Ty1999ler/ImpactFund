#!/bin/sh
# STAGING auto-deploy — run by cron on the hosting account, from the clone
# that is checked out on `main`:
#
#   */5 * * * * /bin/sh /home/<cpanel-user>/repos/<staging clone>/_deploy/auto-deploy.sh >/dev/null 2>&1
#
# Every run: fetch origin/main; if it has moved, fast-forward this clone to it
# and deploy, exactly as "Update from Remote" + "Deploy HEAD Commit" in cPanel's
# Git Version Control would. Nothing new = nothing done, nothing logged.
#
# STAGING ONLY, by construction: it refuses to run in a clone that is not on
# `main`. Production (the `release` clone) keeps its two cPanel buttons.
#
# Nothing here holds or needs a credential: the repository is public, so the
# fetch is anonymous, and the deploy runs as the account that owns the files.
#
# It only ever moves FORWARD (git merge --ff-only). If this clone has been
# changed by hand, or main was rewritten, it stops and says so in the log
# instead of forcing anything.
#
# How it deploys, first that works:
#   1. cPanel's own deployment (uapi VersionControlDeployment create), so
#      cPanel's "Last Deployment" stays true;
#   2. if that tool is missing or refuses, or staging does not show the new
#      version afterwards: the tasks of .cpanel.yml themselves, read from the
#      file and run in order — the same commands cPanel runs, not a copy of
#      them, so the two can never drift apart.
#
# What is deployed is written to version.txt (in this clone, git-ignored, and
# from there to the staging site): http://staging.alumoimpact.ca/version.txt
#
# Log: ~/impactfund-auto-deploy.log (last 300 lines kept). Override for tests:
#   AUTO_DEPLOY_LOG, AUTO_DEPLOY_DOCROOT, AUTO_DEPLOY_WAIT (seconds).

set -u

REPO=$(cd "$(dirname "$0")/.." && pwd) || exit 1
LOG="${AUTO_DEPLOY_LOG:-$HOME/impactfund-auto-deploy.log}"
# Where `main` deploys to — keep in step with DEPLOYPATH in .cpanel.yml.
DOCROOT="${AUTO_DEPLOY_DOCROOT:-$HOME/staging}"
WAIT="${AUTO_DEPLOY_WAIT:-90}"
GIT=/usr/bin/git
[ -x "$GIT" ] || GIT=git

log() {
    printf '%s %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$*" >> "$LOG"
}

cd "$REPO" || exit 1

branch=$("$GIT" rev-parse --abbrev-ref HEAD 2>/dev/null)
if [ "$branch" != "main" ]; then
    log "REFUSED: $REPO is on '$branch', not main. This job deploys staging only."
    exit 1
fi

# One run at a time. A lock older than 20 minutes is a run that died.
LOCK="$REPO/.git/auto-deploy.lock"
if ! mkdir "$LOCK" 2>/dev/null; then
    if [ -n "$(find "$LOCK" -maxdepth 0 -mmin +20 2>/dev/null)" ]; then
        rmdir "$LOCK" 2>/dev/null
        mkdir "$LOCK" 2>/dev/null || exit 0
    else
        exit 0
    fi
fi
trap 'rmdir "$LOCK" 2>/dev/null' EXIT
trap 'exit 1' INT TERM

if ! "$GIT" fetch --quiet origin main 2>>"$LOG"; then
    log "fetch from origin failed; nothing changed. Will try again next run."
    exit 1
fi

local_sha=$("$GIT" rev-parse HEAD)
remote_sha=$("$GIT" rev-parse origin/main)
deployed_sha=$(cat "$REPO/.git/auto-deployed-sha" 2>/dev/null || true)

# Nothing new on main, and what is here has been deployed: done.
if [ "$local_sha" = "$remote_sha" ] && [ "$deployed_sha" = "$local_sha" ]; then
    exit 0
fi

if [ "$local_sha" != "$remote_sha" ]; then
    if ! "$GIT" merge --ff-only --quiet origin/main 2>>"$LOG"; then
        log "NOT DEPLOYED: this clone cannot fast-forward to origin/main ($remote_sha)." \
            "It was changed by hand, or main was rewritten. Fix it in cPanel > Git Version Control."
        exit 1
    fi
fi

new_sha=$("$GIT" rev-parse HEAD)
short_sha=$("$GIT" rev-parse --short HEAD)
stamp="main $short_sha deployed $(date -u '+%Y-%m-%dT%H:%M:%SZ') (auto)"
# In the clone, so the deploy's own rsync carries it to the site (and its
# --delete cannot remove it). Git-ignored: the working tree stays clean.
printf '%s\n' "$stamp" > "$REPO/version.txt"

# Is the version just stamped the one the site serves?
site_shows_it() {
    [ "$(cat "$DOCROOT/version.txt" 2>/dev/null)" = "$stamp" ]
}

deploy_with_cpanel() {
    for tool in /usr/bin/uapi /usr/local/cpanel/bin/uapi; do
        [ -x "$tool" ] || continue
        out=$("$tool" --output=json VersionControlDeployment create repository_root="$REPO" 2>&1) || true
        case "$out" in
            *'"status":1'*) ;;
            *)
                log "cPanel did not accept the deployment: $(printf '%s' "$out" | tr '\n' ' ' | cut -c1-300)"
                return 1
                ;;
        esac
        # cPanel queues the deployment and runs it in the background.
        waited=0
        while [ "$waited" -lt "$WAIT" ]; do
            site_shows_it && return 0
            sleep 5
            waited=$((waited + 5))
        done
        log "cPanel accepted the deployment, but staging does not show $short_sha after ${WAIT}s."
        return 1
    done
    return 1
}

# The tasks of .cpanel.yml, one command per line, in order. The file is ours
# and its shape is fixed: each task is "    - <command>" on one line, except
# the folded one ("    - >" then deeper-indented lines, joined by spaces).
cpanel_tasks() {
    awk '
        /^    - >[ \t]*$/ { folding = 1; command = ""; next }
        folding && /^      [^ ]/ {
            sub(/^ +/, "")
            command = command (command == "" ? "" : " ") $0
            next
        }
        folding { print command; folding = 0 }
        /^    - / { sub(/^    - /, ""); print }
        END { if (folding) print command }
    ' "$REPO/.cpanel.yml"
}

deploy_directly() {
    tasks=$(mktemp) || return 1
    cpanel_tasks > "$tasks"
    # Refuse to run anything that is not recognisably the staging deploy: a
    # change to .cpanel.yml that this reader does not follow must stop here,
    # not rsync --delete with the wrong arguments.
    if ! grep -q '^export DEPLOYPATH=\$HOME/staging$' "$tasks" \
        || ! grep -q "^/usr/bin/rsync -a \$RSYNC_DELETE .*--exclude='api/config.php' .*\./ \$DEPLOYPATH/\$" "$tasks" \
        || [ "$(grep -c '' "$tasks")" -lt 10 ]; then
        log "NOT DEPLOYED: could not read the deploy tasks from .cpanel.yml as expected. Deploy from cPanel."
        rm -f "$tasks"
        return 1
    fi
    ( cd "$REPO" && /bin/sh -e "$tasks" ) >>"$LOG" 2>&1
    rc=$?
    rm -f "$tasks"
    [ "$rc" -eq 0 ] || return 1
    # The tasks ran to the end. rsync leaves a file alone when its size and
    # its time, to the second, are unchanged — which a stamp rewritten within
    # the same second is — so the stamp is placed explicitly, then read back.
    cp "$REPO/version.txt" "$DOCROOT/version.txt" 2>/dev/null
    site_shows_it
}

method=cpanel
if ! deploy_with_cpanel; then
    method=direct
    if ! deploy_directly; then
        log "NOT DEPLOYED: $short_sha is checked out but staging was not updated. Deploy from cPanel."
        exit 1
    fi
fi

printf '%s\n' "$new_sha" > "$REPO/.git/auto-deployed-sha"
log "deployed $short_sha to staging ($method)"

# Keep the log short.
if [ "$(wc -l < "$LOG")" -gt 300 ]; then
    tail -n 300 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
fi
exit 0
