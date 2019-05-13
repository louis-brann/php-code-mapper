#!/bin/bash
vendor/bin/phpunit
bash <(curl -s https://codecov.io/bash) -t @.cc_token
