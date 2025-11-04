# NGC Omeka S Distribution

Intro here...

## Installation

Clone the repository and rename the directory to your project name:

```bash
git clone https://github.com/Systemik-Solutions/ngc-omeka.git YOUR_PROJECT_NAME
```

The distribution requires [Composer](https://getcomposer.org/) to manage dependencies. Run the following command in 
the project directory:

```bash
composer install
```

## Configuration

### Distribution Configuration

The distribution requires some configuration before installation. In the `config` directory, create a copy of the
`config-example.json` file and rename it to `config.json`. Open the `config.json` file and update the configurations
as needed.

The configuration file include the following settings:

- `db`: the database connection information for the Omeka S instance.
  - `host`: the database host (e.g., `localhost`).
  - `port`: the database port (e.g., `3306`).
  - `database`: the name of the database.
  - `username`: the database username.
  - `password`: the database password.
- `apache_user`: The linux user that runs the web server (e.g., `www-data` or `httpd`). This is used to set the correct
  permissions on certain directories.
- `admin`: The initial Omeka S user information.
  - `name`: the name of the user.
  - `email`: the user email address.
  - `password`: the user password.
- `title`: The title of the Omeka S instance.
- `timezone`: The timezone for the Omeka S instance (e.g., `Australia/Sydney`).
- `site`: the [Omeka S site](https://omeka.org/s/docs/user-manual/sites/) to create during installation. This is
  optional if you want to create the site later via the Omeka S admin interface.
  - `name`: the name of the site.
  - `slug`: the URL slug for the site.
  - `summary`: a brief summary of the site.
  - `theme`: the theme to use for the site (e.g., `default`). Note that this is the theme ID (normally the same as the
    theme folder name).

### Omeka S Configuration

You can put a file named `local.config.php` in the `config` directory to override default Omeka S configurations during
installation. This is optional if you want to keep the default Omeka S configurations. For more information about
the Omeka S configurations, refer to the [Omeka S documentation](https://omeka.org/s/docs/user-manual/configuration/).

## Distribution Installation

To install the NGC Omeka S distribution, run the `install` command from the project root:

```bash
php console install
```

This will create the `public` directory under the project root. Set your web server's document root to the `public` 
directory or configure a virtual host accordingly.

Once it's done, you can access the Omeka S site by navigating to your server's URL or the configured host name 
in a web browser.
