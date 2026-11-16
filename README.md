# Newsletter Publisher

A WordPress plugin for publishing multilingual newsletter issues on the website and sending them as email. Issues and events are WordPress content, rendered through a shortcode or the Newsletter block.

## Features

- Multilingual issues, events, categories and interface strings.
- Site-language or back-to-back language display, configurable per content type.
- Native accordion layout, category ordering, date ranges and attachments.
- Per-issue email composition from **Newsletter → Email**.
- Choose email languages, primary language, title/description separators and optional language labels.
- Plain-text email by default, with optional minimal HTML.
- Preview, copy and download the rendered email for manual sending.
- Sends with WordPress `wp_mail()`. Install and configure an SMTP or email delivery plugin for reliable transport; the plugin does not configure mail transport.

## Local WordPress test server

The repository includes a Docker Compose stack with WordPress 6.8, PHP 8.5, MySQL 8.4 and WP-CLI. WordPress is installed automatically and the plugin is activated on first start.

### Requirements

- Docker Engine with Compose v2
- Port `8090` available locally

### Start the server

```bash
cp .env.example .env
docker compose up -d
docker compose logs -f wp-setup
```

The defaults work without copying `.env`, but copying it makes local overrides explicit.

Once the setup log reports `Ready: http://localhost:8090/wp-admin`, open:

- Site: <http://localhost:8090>
- Admin: <http://localhost:8090/wp-admin>
- Username: `admin`
- Password: `adminpass`

The plugin is installed from the repository and activated automatically.

### Hot reload

The repository is bind-mounted read-only at:

```text
/var/www/html/wp-content/plugins/newsletter-wp-plugin
```

Changes to PHP, CSS, JavaScript and templates on the host are visible in the container immediately. PHP changes apply on the next request. Refresh the browser after changing CSS or JavaScript; use a hard refresh if the browser has cached an asset.

No container restart or asset build step is required.

### WP-CLI

Run WP-CLI inside the test environment with the setup service:

```bash
docker compose run --rm --entrypoint wp wp-setup plugin list
docker compose run --rm --entrypoint wp wp-setup option get siteurl
```

### Smoke test

The repository includes a real-WordPress smoke test. It resets plugin test content and creates fixtures in the local test site, so run it only against the Docker environment:

```bash
bin/test-docker.sh
```

The command starts the stack, runs the idempotent setup, and executes `tests/nlwp-smoke.php` through `wp eval-file`. The stack is left running afterward.

### Logs and debugging

WordPress debug logging is enabled and written inside the container:

```bash
docker compose exec wordpress tail -f wp-content/debug.log
```

Container logs:

```bash
docker compose logs -f wordpress
docker compose logs -f db
```

WordPress 6.8 can log PHP 8.5 deprecation notices from WordPress core itself. When diagnosing plugin issues, filter the log for `newsletter-wp-plugin` paths.

### Stop and reset

Stop the containers while keeping the database and uploads:

```bash
docker compose down
```

Start them again:

```bash
docker compose up -d
```

Reset the site completely, including the database, WordPress files and uploads:

```bash
docker compose down -v
docker compose up -d
docker compose logs -f wp-setup
```

The named volumes are `nlwp-dev_db_data` and `nlwp-dev_wp_data`. `docker compose down -v` deletes both.

### Change the port

Edit `WP_PORT` in `.env`, then restart:

```bash
docker compose up -d
```

The setup service updates WordPress `home` and `siteurl` to match the new port. The admin credentials and existing content are preserved.

### Configuration notes

- Initial site and admin details are read from `.env` only when WordPress is first installed.
- Changing `WP_ADMIN_USER` or `WP_ADMIN_PASSWORD` after installation does not change the existing account. Reset the volumes or update the user through WordPress/WP-CLI.
- WordPress core and uploads are stored in the `wp_data` volume, so changing the WordPress image tag does not replace an existing core install. Use `docker compose down -v` for a clean install.
- The plugin mount is read-only, so WordPress cannot modify the repository files.
