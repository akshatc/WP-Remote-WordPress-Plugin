<?php

/**
 * Class WPRP_Facade
 *
 ** This class exists because WordPress throws a non-static warning when trying to call these classes directly
 ** Yes, we could initiate the class first then use that in the callback but that means every time a page is loaded these classes are initiated, running any __construct code. Not very practical either.
 ** If you have a better solution, tell me.
 *
 */
class WPRP_Facade {

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param  string  $method
     * @param  array   $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $class = get_called_class();

        $class = str_replace('Facade', '', $class);

        $instance = new $class();

        switch (count($args)) {
            case 0:
                return $instance->$method();

            case 1:
                return $instance->$method($args[0]);

            case 2:
                return $instance->$method($args[0], $args[1]);

            case 3:
                return $instance->$method($args[0], $args[1], $args[2]);

            case 4:
                return $instance->$method($args[0], $args[1], $args[2], $args[3]);

            default:
                return call_user_func_array([$instance, $method], $args);
        }
    }
}

class WPRP_PluginFacade extends WPRP_Facade {}

class WPRP_ThemeFacade extends WPRP_Facade {}

class WPRP_BackupFacade extends WPRP_Facade {}

class WPRP_CoreFacade extends WPRP_Facade {}