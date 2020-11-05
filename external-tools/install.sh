#/bin/bash
GIT_TRACE=1 git submodule update --init
#GIT_TRACE=1 git submodule update --remote --merge
git submodule foreach 'git fetch origin; git checkout $(git rev-parse --abbrev-ref HEAD); git reset --hard origin/$(git rev-parse --abbrev-ref HEAD); git submodule update --recursive; git clean -dfx'
composer install || /usr/local/bin/composer install || php composer.phar install || echo "Composer not installed!"
composer update || /usr/local/bin/composer update || php composer.phar update || echo "Composer not installed!"
