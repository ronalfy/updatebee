# UpdateBee

Take complete control over your automatic WordPress updates.

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

## Quality checks

```bash
composer test
composer phpcs
composer phpstan
npm run test:js
npm run lint:js
```

Auto-fix PHP coding standards with `composer phpcbf`.
