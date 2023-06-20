# Monwoo Web Starter Symfony PDF Billings (Free)
<img src="https://miguel.monwoo.com/embedded-iframes/prod/embeddable-iframe/favicomatic/favicon-96x96.png" alt="" width="42"/> [Build by Miguel Monwoo, **Open Source Apache-2.0 with Copyright © MONWOO 2023**](https://moonkiosk.monwoo.com/en/categorie-produit/produced-solutions/mws_en/)

## Aim
Provide a PHP local tool for pre-fillable self signed PDF billing templates.

## Bonus
Basic controller ok for simple JWT authentification if needed.

## Build

```bash
# tested under php 8.1.2
# Other php versions might work, but it's not tested yet.
php -v

wget https://getcomposer.org/composer.phar
alias composer="php '$PWD/composer.phar'"                      
cd apps/mws-sf-pdf-billings/backend

mkdir config/jwt
openssl genrsa -out config/jwt/private.pem -aes256 4096 # pass : jwt_test
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem # pass : jwt_test

composer install

# bootstrap database
php bin/console doctrine:migrations:migrate

# use php builtin
# (TODO : php router and missing .htaccess checks ok ?)

# Use SYMFONY dev server (not same as php builtin or bin/console)
wget https://get.symfony.com/cli/installer -O - | bash
alias symfony="~/.symfony5/bin/symfony"
symfony server:start

# All is ready for your PDF edition (TODO : dev in progres...)
# TODO : Just fill the form or pre-fill by json POST request...
open http://localhost:8000
```

<div style="page-break-before: always;"></div>

## Going further

```bash
# usefull :
php bin/console debug:form
# Generate some formType based on entity model :
php bin/console make:form BillingConfig

# add users
php bin/console make:user
# list of available make commandes
symfony console list make

# other tools for models :
php bin/console make:entity BillingConfig

rm src/Form/BillingConfigType.php
mv src/Form/BillingConfigSubmitableType.php \
src/Form/_BillingConfigSubmitableType.php 
php bin/console make:form BillingConfigType BillingConfig
mv src/Form/_BillingConfigSubmitableType.php \
src/Form/BillingConfigSubmitableType.php

php bin/console make:entity --regenerate
php bin/console make:entity --help
# If you change your model,
# you need to generate the associated migrations :
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# add a new controller
php bin/console make:controller PdfBillings

# You can test the JWT feature with curl :
# Add a user (you will have to build up all other security aspects)
curl -X POST -H "Content-Type: application/json" \
 -d '{"username": "test", "password": "123", "email": "test@test.fr"}' \
 http://127.0.0.1:8000/api/register

# Get the JWT token from custom 'login_check' api url
curl -X POST -H "Content-Type: application/json" \
 -d '{"username": "test", "password": "123"}' \
 http://127.0.0.1:8000/api/login_check

# Load some client (if exist) :
open http://localhost:8000/?billing_config_submitable[clientSlug]=newClient

# Create empty quotation for new client slug if do not exist 
# (WRONG CSRF, but will create client with empty value if don't exist)
curl -X POST -d "billing_config_submitable[clientSlug]=test2" \
http://127.0.0.1:8000/

# Create or update quotation for new client slug (WRONG CSRF)
curl -X POST \
-H "application/x-www-form-urlencoded" \
-d "billing_config_submitable[clientSlug]=test3" \
-d "billing_config_submitable[clientName]=ClientTestedName" \
http://127.0.0.1:8000/

# Create or update quotation for new client slug (WRONG CSRF)
curl -F billing_config_submitable[clientSlug]=test3 \
-F billing_config_submitable[clientName]=ClientTestedName \
http://127.0.0.1:8000/

# HANDELING Csrf with curl :
rm cookies.txt
InSrc=$(curl -c cookies.txt -b cookies.txt http://127.0.0.1:8000/ \
2>/dev/null | tr '\n' ' ')
TokenSep='billing_config_submitable[_token]" value="'
HalfPart=$(echo "${InSrc//$TokenSep/$\n}" | tail -n 1)
CSRF=$(echo "${HalfPart//\"/$\n}" | head -n 1)

curl -c cookies.txt -b cookies.txt -F "billing_config_submitable[clientSlug]=test3" \
-F "billing_config_submitable[_token]=$CSRF" \
-F "billing_config_submitable[clientName]=ClientTestedName" \
http://127.0.0.1:8000/

# Similar (ok thanks to SF5 framework...)
curl -X POST -c cookies.txt -b cookies.txt \
-H "application/x-www-form-urlencoded" \
-d "billing_config_submitable[_token]=$CSRF" \
-d "billing_config_submitable[clientSlug]=test3" \
-d "billing_config_submitable[clientName]=ClientTestedName" \
http://127.0.0.1:8000/

# After some moment, you want to start back from fresh data.
# to clean and rebuild the database :
rm var/data.db.sqlite && php bin/console doctrine:migrations:migrate -n

```

## Useful Links

Learn more about the power of pdf billings:

- [Décret n° 2023-377 du 16 mai 2023 - PDF signing requirements for big FR buisiness](https://www.legifrance.gouv.fr/jorf/id/JORFTEXT000047558499)
- [Building an SPA (SF doc)](https://symfony.com/doc/current/the-fast-track/en/27-spa.html)
- [QipsiusTCPDFBundle (SF bundle for PDF)](https://github.com/Qipsius/QipsiusTCPDFBundle)
- [PHP PDF Library](https://github.com/tecnickcom/TCPDF)
- [Using simple TCPDF](https://tcpdf.org/examples/example_001/)
- [Using advanced TCPDF](https://tcpdf.org/examples/example_052/)
- [SF Form types](https://symfony.com/doc/current/form/create_custom_field_type.html)
- [Free PDF Document Importer](https://www.setasign.com/products/fpdi/about/)
- [Html table](https://www.w3schools.com/html/html_table_padding_spacing.asp)
- [Symfony debug forms errors](https://symfonycasts.com/blog/symfony-debugging-form-errors)

## Supports

- You can use regular features of : [github.com/Monwoo/web-starters-free/issues](https://github.com/Monwoo/web-starters-free/issues)

To support us and/or help us open more software, send a subvention with :
- [www.monwoo.com/don](https://www.monwoo.com/don)
