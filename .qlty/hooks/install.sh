#!/bin/sh
#
# Link the tracked hooks into the repository's hook directory. Git never clones
# .git/hooks, so without this a fresh checkout silently runs no hooks at all.

set -e

# Automated environments have no qlty binary, so an installed hook would only
# break whatever git command ran next.
[ -z "$CI" ] || exit 0

root=$(git rev-parse --show-toplevel 2>/dev/null) || exit 0

if [ -n "$(git config --get core.hooksPath || true)" ]; then
    echo "core.hooksPath is set; leaving git hooks alone." >&2
    exit 0
fi

# Worktrees share the hook directory of the main checkout.
git_dir=$(git rev-parse --git-common-dir)

case "$git_dir" in
    /*) ;;
    *) git_dir="$root/$git_dir" ;;
esac

target="$git_dir/hooks"

mkdir -p "$target"

for hook in pre-commit pre-push; do

    script="$root/.qlty/hooks/$hook.sh"

    [ -f "$script" ] || continue

    if [ -e "$target/$hook" ] && [ ! -L "$target/$hook" ]; then
        echo "Skipping $hook: a non-symlink hook is already installed." >&2
        continue
    fi

    chmod +x "$script"
    ln -sf "$script" "$target/$hook"
done

echo "Git hooks installed."
