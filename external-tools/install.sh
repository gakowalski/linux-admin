#/bin/bash
git submodule update --init
git submodule update --remote --merge
composer install || /usr/local/bin/composer install || php composer.phar install || echo "Composer not installed!"
composer update || /usr/local/bin/composer update || php composer.phar update || echo "Composer not installed!"
