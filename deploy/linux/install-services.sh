#!/usr/bin/env bash
set -euo pipefail
root=/srv/hris/current
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
[[ -f "$root/artisan" ]] || { echo 'Expected application at /srv/hris/current' >&2; exit 1; }
id hris >/dev/null 2>&1 || { echo 'Create the dedicated hris OS account before installation.' >&2; exit 1; }
[[ -x /usr/bin/php ]] || { echo 'PHP CLI is missing at /usr/bin/php.' >&2; exit 1; }
cd "$root"
/usr/bin/php artisan hris:check --production
if [[ "${1:-}" != '--install' ]]; then
    echo 'Preflight complete. Use --install to enable only hris-worker and hris-scheduler.timer.'
    exit 0
fi
[[ "$EUID" == 0 ]] || { echo 'Installing systemd units requires root.' >&2; exit 1; }
for unit in hris-worker.service hris-scheduler.service hris-scheduler.timer; do
    if [[ -e "/etc/systemd/system/$unit" ]] && ! cmp -s "$script_dir/$unit" "/etc/systemd/system/$unit"; then
        echo "Existing $unit differs; review it before replacement." >&2
        exit 1
    fi
done
for unit in hris-worker.service hris-scheduler.service hris-scheduler.timer; do
    install -m 0644 "$script_dir/$unit" "/etc/systemd/system/$unit"
done
systemctl daemon-reload
systemctl enable --now hris-worker.service hris-scheduler.timer
echo 'Wait for a complete minute tick, then run php artisan hris:check --production --services.'
