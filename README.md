# WhenIWork Code Challenge:
To run the code, assuming you have php installed, just run the following from the root directory:
```php
composer install
php ./bin/Console.php
```

## Running tests:
To run the tests, from the root directory:
```php
composer install
./vendor/bin/phpunit tests/ShiftControllerTest.php
```
I used symfony's JSON response to mimic as if I was coding a API route without using the full framework to make it easier to review.

## If I had more time:
- Would add more error logging and visibility around the code paths
- Would add more testing, instead I opted to use the time to manually time edge cases
- Would test DST as well as overlapping shifts to prevent future bugs
- Remove the dependency on the existing file to enable more testing
