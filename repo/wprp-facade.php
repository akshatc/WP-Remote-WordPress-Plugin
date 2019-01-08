<?php

/**
 * Class WPRP_Facade
 *
 ** This class exists because WordPress throws a non-static warning when trying to call these classes directly
 ** We also use it to log all requests and log update history
 ** Yes, to the first point, we could initiate the class first then use that in the callback but that means every time a page is loaded these classes are initiated, running any __construct code. Not very practical either.
 ** If you have a better solution, tell me.
 *
 */
class WPRP_Facade {

    use WPRP_Request_Actions;

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param  string  $method
     * @param  array   $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $self = self::createSelf($method, $args);

        $self->beforeAction();

        $self->handleAction(
            $return = $self->doCall()
        );

        return $return;
    }

    /**
     * @return mixed
     */
    protected function doCall()
    {
        $method = $this->method;
        switch (count($this->args)) {
            case 0:
                return $this->instance->$method();

            case 1:
                return $this->instance->$method($this->args[0]);

            case 2:
                return $this->instance->$method($this->args[0], $this->args[1]);

            case 3:
                return $this->instance->$method($this->args[0], $this->args[1], $this->args[2]);

            case 4:
                return $this->instance->$method($this->args[0], $this->args[1], $this->args[2], $this->args[3]);

            default:
                return call_user_func_array([$this->instance, $this->method], $this->args);
        }
    }

    /**
     * @param $method
     * @param $args
     * @return WPRP_Facade
     */
    protected static function createSelf($method, $args): WPRP_Facade
    {
        $self = new self();

        $self->method = $method;
        $self->args = $args;

        $self->class_name = self::getClassName();
        $self->class_type = strtolower(
            str_replace('WPRP_', '', $self->class_name)
        );

        $self->instance = new $self->class_name();
        $self->setRequest($args);

        return $self;
    }

    /**
     * Find the Request Arg and set
     *
     * @param $args
     */
    protected function setRequest($args)
    {
        foreach ($args as $arg) {
            if ($arg instanceof WP_REST_Request) {
                $this->request = $arg;
                break;
            }
        }
    }

    /**
     * @return mixed|string
     */
    protected static function getClassName()
    {
        $class = get_called_class();

        return str_replace('Facade', '', $class);
    }

}

class WPRP_PluginFacade extends WPRP_Facade {}

class WPRP_ThemeFacade extends WPRP_Facade {}

class WPRP_BackupFacade extends WPRP_Facade {}

class WPRP_CoreFacade extends WPRP_Facade {}

class WPRP_HistoryFacade extends WPRP_Facade {}