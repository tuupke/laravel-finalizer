#Introduction

Laravels implementation of finalizers (through middleware) are still synchronous with the request and will therefor increase the responsetime of your application. There are many usecases - logging for instance - for which this is not desired.

Normally speaking, standard Laravel practices dictate that a job should be used in these cases. When this is overkill, this package can be used. It provides a small wrapper around PHP's `register_shutdown_function`, to help with these use-cases.

#Installation
Installation of this packages is done through composer.

```composer require "tuupke/laravel-finalizer"```

Then open `config/app.php` and append to the `prodivders` array:

```Tuupke\Finalizer\FinalizerServiceProvider::class,```

and add to the `aliasses` array:

```'Finalizer' => Tuupke\Finalizer\FinalizerFacade::class,```

#Usage
From somewhere within your application you can now call:

```Finalizer::register($closure))``` with $closure some closure executing some action. Optionally, you can provide an integer as the second parameter, which will server as the priority. The lower this priority, the earlier it is executed. When 2 closures are registered with the same priority. The closure which was registered first, will be executed first.

##Example
A minimalist example which stores some contents in a file is listed below. Note that this should not be used in an actual application. When logging is required, use the Log facade.
```
Finalizer::register(function(){
    file_put_contents('/tmp/finalizer-test', "Second String", FILE_APPEND);
}, 2);

Finalizer::register(function(){
    file_put_contents('/tmp/finalizer-test', "First String\n", FILE_APPEND);
}, 1);
```

After a request has been executed which contained this codeblock, the contents of `/tmp/finalizer-test` is:

```
First String
Second String
```


#Use in non-Laravel applications
Strictly speaking, this package can also be used in non-Laravel PHP applications which use composer. But it is not recommended and defeats the purpose of this package. If you want, you can still use it. A minimal example is provided below.

```
// include composer autoload
require 'vendor/autoload.php';
 
// import the Finalizer Class
use Tuupke\Finalizer;
 
// Create a new instance of the Finalizer. The class itself does not provide late static binding (yet).
$finalizer = new Finalizer;
 
// register some closure.
$finalizer->register(function(){
    ... <YOUR CODE GOES HERE> ...
});
```
