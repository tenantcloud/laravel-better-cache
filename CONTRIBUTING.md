# Commands

Install dependencies:
`docker run -it --rm -v $PWD:/app -w /app composer install`

Run tests:
`docker compose run cli vendor/bin/pest`

Run php-cs-fixer on self:
`docker run -it --rm -v $PWD:/app -w /app composer cs-fix`

Run phpstan on self:
`docker run -it --rm -v $PWD:/app -w /app composer phpstan`
