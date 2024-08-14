<<<<<<<< Update Guide >>>>>>>>>>>  

Immediate Older Version: 2.1.0
Current Version: 2.2.0

Feature Update:
1. Issue Fixed
2. Money Out Automatic
3. Language File Update
4. Product Feature Add


Please User Those Command On Your Terminal To Update Full System
1. To Run project Please Run This Command On Your Terminal
    composer update && composer dumpautoload && php artisan migrate

2. To Update Database Please Run The command On Your Terminal
    -> php artisan db:seed --class=Database\\Seeders\\V2_2_0\\PaymentGateWaySeeder

2. To Update Product Database Please Run The command On Your Terminal
    -> php artisan db:seed --class=Database\\Seeders\\V2_2_0\\ProductSeeder

3. To Update Feature
    -> php artisan db:seed --class=Database\\Seeders\\UpdateFeatureSeeder


    <!-- composer require doctrine/dbal -->

