# Selang
Write Golang.. BUT wait.. In PHP style 🤝🧒

## Getting Started
Install the dependency first

```sh
$ composer install
```

### Write PHP in the Playground
In this project folder, you can find `Playground` directory.

The PHP script can be written with namespace just like when you using this Scarlets Framework. You can only use Golang's built-in function, because **Selang** is just a helper to write easy and oriented programming style.

### Compile your code
When you want to compile, you need to specify the main script (ex: `Playground/test.php`)

```sh
$ scarlets selang Playground/test.php
```

### Usage Flow
The CLI is controlled from `routes/console.php` that will call the library on `app/Library/Selang/Loader.php`.