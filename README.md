# webcompro payment gateway plugin

## Available Scripts
`npm run env start` to start the dev environment

`npm run start` to run webpack

Backoffice reachble at: http://localhost:8888/wp-admin/

User: `admin`

Pass: `password`

## Make a production zip
`npm run build && npm run plugin-zip`

## Install woocommerce stubs

Install composer php

Windows: https://getcomposer.org/doc/00-intro.md#installation-windows

In most unix distros you can add it from the repository.

You can also run the following docker command: `docker run --rm -it -v "$(pwd):/app" composer/composer install`
