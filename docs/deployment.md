# Git deployment

All source changes must be reviewed, tested, committed and pushed before deployment. Do not copy or edit application source directly on production, including through cPanel, FTP or a shell. General permission to improve the site does not waive this rule.

Verified on 2026-10-30:

- GitHub repository: `agmedia/zuzi`.
- Local and production branches: `master`, tracking `origin/master`.
- Production checkout: `/home/newzuzi/public_html`; public web root is its `public` directory.
- Production uses cPanel with a standard cPanel `post-receive` hook. A push to GitHub alone was not observed to update the production checkout.

The verified deployment sequence is:

1. Review the exact diff and run checks appropriate to the change. Preserve unrelated staged, untracked and production files.
2. Commit only intended source and tests, then push `origin/master`. Do not force-push.
3. Verify production's branch, remote identity, current commit and local changes before updating it. Never expose credentials contained in a remote URL.
4. In the production checkout run `git pull --ff-only origin master`. Stop on a conflict or divergence; never resolve it by discarding server changes.
5. If Blade templates changed, run `php artisan view:clear` from that checkout. New controllers use existing PSR-4 autoloading. Do not run dependency updates or migrations unless the change requires them.
6. Verify the production commit, relevant working-tree diff and actual public behavior. A successful push is not proof that deployment finished.

Production had unrelated changes in `.htaccess`, media/spreadsheets and the logs directory on 2026-10-30. Leave them intact. Production has no tracked `.cpanel.yml` or project-specific deploy pipeline in this revision.

Rollbacks must also use Git (normally a reviewed revert followed by push and the same deployment sequence). A direct restoration is allowed only when the user explicitly authorizes that specific exception.

For AI discovery, the host previously cached the old static `/llms.txt` and added a month-long expiry to plain text. Verify both the advertised versioned discovery URL and its response headers after updates. The controller must return `no-store`, `no-cache`, `max-age=0` and an explicit expiry. Do not assume a source update purges a server cache.

## Abandoned checkout reminders (2026-11-06)

After the reviewed commit is pushed and pulled using the sequence above:

1. Run only `php artisan migrate --path=database/migrations/2026_11_06_120000_add_abandoned_cart_reminders.php --force`. Do not run all pending historical migrations. This adds the delivery ledger and activity timestamp, and records the activation time in `settings` (`order` / `abandoned_cart_reminders_started_at`). Existing orders are excluded from automatic backfill.
2. Run `php artisan config:clear`, `php artisan route:clear` and `php artisan view:clear` to load the new config, signed route and email. No dependency changes are required.
3. Inspect the existing crontab and confirm exactly one once-per-minute `artisan schedule:run` entry. Reuse it; do not add another scheduler or a separate reminder cron. The Git-tracked schedule runs `orders:send-abandoned-cart-reminders` every five minutes with overlap protection.
4. Check `php artisan schedule:list` and run `php artisan orders:send-abandoned-cart-reminders --dry-run`. The dry-run does not send mail or reserve deliveries. Do not use `schedule:run` as a deployment test because it can start unrelated due jobs.

The feature defaults to enabled after migration; `ABANDONED_CART_EMAILS_ENABLED=false` disables future sending. Leave `ABANDONED_CART_EMAILS_STARTS_AT` unset so the original activation marker remains effective. Reminders are due after 60 and 1440 minutes of checkout inactivity, with at least 23 hours between deliveries if the scheduler was delayed. Orders older than three days are excluded. Only the latest unfinished checkout per normalized email/account is considered; attempts on another checkout suppress new series for the same customer for 72 hours.

Automatic and manual reminders share the permanent unique `(order_id, sequence)` reservation. Existing manual promo sends are coordinated with it, and earlier reminder/promo history is respected. A failed or interrupted SMTP attempt is deliberately not retried automatically, because delivery may already have succeeded. Investigate `abandoned_cart_reminders.last_error` and transport logs rather than deleting the reservation or replaying the command. Both the reservation and activation marker survive migration rollback to prevent duplicate sends.

The signed recovery link lasts seven days, merges only active available books without increasing existing quantities, preserves an existing checkout/coupon and never binds the abandoned order for later updates. Purchase status, successful transactions and accepted sibling purchases are rechecked before sending. The existing ZUZI20 expiry remains **2026-11-15 00:00 Europe/Zagreb**; subsequent reminders omit the expired promotion.
