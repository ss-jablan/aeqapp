## Install
Go inside the folder and:
- docker-compose build
- docker-compose up
- docker-compose exec aeqapp composer install
- docker-compose exec aeqapp php artisan migrate:fresh --seed

## Execute
Just open:
- http://localhost:8000 (Laravel main page)
- http://localhost:8000/dbtest (Show places page)