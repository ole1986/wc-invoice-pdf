# WC Recurring

This project extends WooCommerce with billing and invoice functionality for recurring and one-off orders, including PDF invoices, email delivery, and support for digital invoice formats.

For the WordPress plugin metadata and the plugin description used in the WordPress directory, see [readme.txt](readme.txt). This README focuses on local development, Docker setup, and project workflows.

https://wordpress.org/plugins/wc-invoice-pdf/

## Requirements

- Docker
- Docker Compose
- Task (optional, but recommended for the automated setup steps)

## Starting the local environment

Start the environment from the project folder:

```bash
docker compose up --build -d
```

After that, WordPress is available at http://localhost.

The Compose setup includes the following services:

- `db`: MariaDB
- `wordpress`: local WordPress instance
- `wpcli`: WordPress CLI for installation and configuration tasks

If you want to reset the environment completely, including databases and volumes:

```bash
docker compose down -v
```

## Environment variables

The `.env` file is loaded automatically by Docker Compose. It contains values such as:

```env
WP_TITLE="WC Recurring Demo"
WP_USER=admin
WP_PASS=demo
WP_EMAIL=your@email.tld
```

These values are used during the WordPress setup process.

## Using the Taskfile

This project uses `Taskfile.yml` as the central entry point for recurring tasks.

### WordPress setup

```bash
task setup VITE_DEBUG=enable
```

or

```bash
task setup VITE_DEBUG=disable
```

This runs the following steps:

- install WordPress
- install and activate WooCommerce
- activate the plugin
- create legacy cart and checkout pages
- optionally enable or disable Vite development mode

### Run WP-CLI commands directly

```bash
task wp -- --info
```

or for example:

```bash
task wp -- plugin list
```

The `wp` task executes a WP-CLI command inside the `wpcli` container.

### Toggle Vite debug mode

```bash
task vite-debug-on
task vite-debug-off
```

These tasks set the WordPress constant `WCRECURRING_VITE_DEV`.

## Quick overview

```bash
# Start containers
docker compose up --build -d

# Run WordPress setup
task setup VITE_DEBUG=enable

# Run WP-CLI command
task wp -- plugin list

# Stop containers
docker compose down
```

> Note: This project is primarily intended for local development and demo installations. For production environments, additional security and deployment checks should be added.
