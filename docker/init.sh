#!/bin/bash
pushd /usr/share/nginx/html/
composer install
php artisan migrate 
popd
exit 0
