# UpdateBee

Take complete control over your automatic WordPress updates.

## Requirements

- PHP 8.0+
- WordPress 6.0+
- Node.js 20+ (for asset builds and JS tests)
- Composer

## Setup

```bash
composer install
npm install
npm run build
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
