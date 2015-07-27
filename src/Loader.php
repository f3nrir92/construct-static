<?php

namespace ConstructStatic;

use Composer\Autoload\ClassLoader;

/**
 * Class loader wrapper to implement static constructors
 * Method __constructStatic called just after class autoloaded (if loaded after wrapper)
 * or on wrapper init (if loaded earlier)
 */
class Loader
{
    /**
     * Wrapped Composer object
     *
     * @var ClassLoader
     */
    private $loader;

    /**
     * Call static constructor for class if exists
     *
     * @param string $className
     */
    private function callConstruct($className)
    {
        $reflectionClass = new \ReflectionClass($className);
        if ($reflectionClass->hasMethod('__constructStatic')) {
            $reflectionMethod = $reflectionClass->getMethod('__constructStatic');
            if ($reflectionMethod->isStatic()) {
                $reflectionMethod->setAccessible(true);
                $reflectionMethod->invoke(null);
            }
        }
    }

    /**
     * @param ClassLoader $loader Composer loader object
     * @param bool $processLoaded Invoke static constructors on previously loaded classes
     */
    public function __construct(ClassLoader $loader, $processLoaded = false)
    {
        $this->loader = $loader;

        //unregister composer
        $loaders = spl_autoload_functions();
        foreach ($loaders as $loader) {
            spl_autoload_unregister($loader);
        }

        //register wrapper
        spl_autoload_register([$this, 'loadClass'], true, true);

        if ($processLoaded) {
            //call constructor on previously loaded classes
            $classes = get_declared_classes();
            foreach ($classes as $className) {
                $this->callConstruct($className);
            }
        }
    }

    /**
     * Proxy all method calls to Composer loader
     *
     * @param string $name
     * @param mixed $arguments
     */
    public function __call($name, $arguments)
    {
        call_user_func_array([$this->loader, $name], $arguments);
    }

    /**
     * Loads the given class or interface and invokes static constructor on it
     *
     * @param string $className The name of the class
     * @return bool|null True if loaded, null otherwise
     */
    public function loadClass($className)
    {
        $result = $this->loader->loadClass($className);
        if($result === true) {
            //class loaded successfully
            $this->callConstruct($className);
            return true;
        }
        return null;
    }
}