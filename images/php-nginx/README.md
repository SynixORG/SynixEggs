# php-nginx image

Minimal image for the `egg-nginx-php.json` egg: official `php:<version>-fpm-alpine`
plus nginx from the signed nginx.org **stable** repository. Runs fully unprivileged.

| Tag | Contents |
|---|---|
| `ghcr.io/<owner>/php-nginx:php8.5` | PHP 8.5, nginx 1.30.x (rolling, used by the egg) |
| `ghcr.io/<owner>/php-nginx:php8.4` | PHP 8.4, nginx 1.30.x |
| `...:php8.5-YYYYMMDD` | Fixed weekly snapshot, for rollbacks |

Built weekly (Mondays 03:00 UTC) by `.github/workflows/php-nginx.yml` for amd64 and arm64.

## Publishing

1. Push this repository to GitHub (default branch `main`).
2. Actions → **build php-nginx** → Run workflow (or push a change under `images/php-nginx/`).
3. GitHub → your profile → Packages → `php-nginx` → Package settings → **Change visibility → Public**,
   otherwise Wings cannot pull it.

## Maintenance

- **New nginx stable branch** (each April, e.g. 1.32): update `NGINX_BRANCH` in the Dockerfile.
  The pin prevents surprise branch upgrades, but the old branch stops receiving fixes, so don't skip this.
- **New PHP release**: add it to the workflow matrix and the egg's Docker images.
- **phpredis**: bump `PHPREDIS_VERSION` when a new release supports the latest PHP.

## Layout inside a server

```
/home/container
├── public/                 web root (panel variable WEBROOT)
├── nginx/conf.d/*.conf     site configs (never overwritten by reinstall)
├── nginx/default.conf.example
├── php/conf.d/*.ini        PHP overrides
└── .cache/                 sessions, upload temp, nginx temp (managed)
```

Console commands: `reload` (apply config edits without downtime), `test`, `help`.
