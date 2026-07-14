# AI crawler access notes

Live checks on 2026-07-14 showed that `www.zuzi.hr` is served through nginx/Engintron, not Cloudflare.

Current observed behavior:

- Host-side smoke tests returned HTTP 200 for `ChatGPT-User`, `OAI-SearchBot`, `Claude-SearchBot`, `Claude-User`, `PerplexityBot`, and `Googlebot`.
- Earlier off-host checks returned HTTP 403 for some AI user agents before Laravel handled the request, so hosting-level rules should remain the first place to check if this regresses.
- No matching user-agent block was found in `public/.htaccess`.

AI catalog discovery endpoints:

- `https://www.zuzi.hr/llms.txt` points AI tools to core catalog discovery URLs.
- `https://www.zuzi.hr/feeds/openai-products.jsonl` exposes the book catalog as an OpenAI-compatible JSONL product feed with title, description, canonical URL, publisher brand, ISBN/GTIN when available, image, price, availability, condition, and category path.

Future 403 responses should be checked in hosting-level rules, most likely Engintron, nginx, Apache, ModSecurity, or a server security plugin.

Recommended allowlist policy:

- Allow discovery/search user agents that may answer user product queries:
  - `OAI-SearchBot`
  - `ChatGPT-User`
  - `Claude-SearchBot`
  - `Claude-User`
  - `PerplexityBot`
  - `Googlebot`
  - `Bingbot`
- Decide separately whether training crawlers should be allowed:
  - `GPTBot`
  - `ClaudeBot`

Smoke-test commands after changing hosting rules:

```bash
curl -I -L -A 'ChatGPT-User/1.0' https://www.zuzi.hr/
curl -I -L -A 'OAI-SearchBot/1.0' https://www.zuzi.hr/
curl -I -L -A 'Claude-SearchBot/1.0' https://www.zuzi.hr/
curl -I -L -A 'Claude-User/1.0' https://www.zuzi.hr/
curl -I -L -A 'PerplexityBot/1.0' https://www.zuzi.hr/
curl -I -L -A 'Googlebot/2.1 (+http://www.google.com/bot.html)' https://www.zuzi.hr/
```

Expected result for allowed discovery crawlers: HTTP 200.
