# UpdateBee

<img width="1544" height="500" alt="UpdateBee Social (1544 x 500 px)" src="https://github.com/user-attachments/assets/675cd94e-c665-4acd-8bb5-e5b9d5e185e8" />


Take complete control over your automatic WordPress updates.

- Website: https://updatebee.com
- Source: https://github.com/ronalfy/updatebee/

## Requirements

- PHP 8.0+
- WordPress 6.0+
- Node.js 20+ (for asset builds and JS tests)
- Composer

UpdateBee is a hard fork of <a href="https://wordpress.org/plugins/stops-core-theme-and-plugin-updates/">Easy Updates Manager</a>.

## Setup

```bash
composer install
npm install
npm run build
```

## Generate Production ZIP

```bash
npm run build
grunt
```

## Translations

Translation files are built at release time and are not committed. Only the translated `.po` files in `languages/` are tracked.

```bash
npm run build
WP_CLI="php /path/to/wp-cli.phar" npm run i18n   # WP_CLI is optional when `wp` is on your PATH
```

`npm run i18n` (also run by `grunt`) writes `languages/updatebee.pot`, then compiles every `languages/*.po` into a `.mo` file for PHP strings and one `.json` file per script for JavaScript strings. React strings are extracted from `build/`, so run the build first.

To add a language, copy `languages/updatebee.pot` to `languages/updatebee-<locale>.po` (for example `updatebee-de_DE.po`), translate it, and commit the `.po`.

## Quality checks

```bash
composer test
composer phpcs
composer phpstan
npm run test:js
npm run lint:js
```

Auto-fix PHP coding standards with `composer phpcbf`.
