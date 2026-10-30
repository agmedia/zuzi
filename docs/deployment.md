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
