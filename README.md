# WC Recurring

This project extends WooCommerce with billing and invoice functionality for recurring and one-off orders, including PDF invoices, email delivery, and support for digital invoice formats.

For the WordPress plugin metadata and the plugin description used in the WordPress directory, see [readme.txt](readme.txt). This README focuses on local development, Docker setup, and project workflows.

https://wordpress.org/plugins/wc-invoice-pdf/

## Requirements

- Docker
- Docker Compose

## Starting the local environment

Start the environment from the project folder:

```bash
docker compose up --build -d
```

After that, WordPress is available at http://localhost.

The Compose setup includes the following services:

- `db`: MariaDB
- `wordpress`: local WordPress instance
- `setup`: one-time WordPress and WooCommerce initialization

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

## WordPress setup

```bash
docker compose run --rm setup
```

This runs the following steps:

- install WordPress
- install and activate WooCommerce
- activate the plugin
- create legacy cart and checkout pages

> Note: This project is primarily intended for local development and demo installations. For production environments, additional security and deployment checks should be added.
