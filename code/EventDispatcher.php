<?php namespace Milkyway\SS;

use Milkyway\Events\Manager;

/**
 * Milkyway Multimedia
 * EventDispatcher.php
 *
 * @package relatewell.org.au
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

class EventDispatcher {
    private static $singleton;

    protected $manager;

    public static function inst() {
        if(!static::$singleton)
            static::$singleton = \Injector::inst()->createWithArgs(__CLASS__, array(new Manager()));

        return static::$singleton;
    }

    public static function config() {
        return \Config::inst()->forClass('EventDispatcher');
    }

    public function __construct(Manager $manager) {
        $this->manager = $manager;
    }

    public function boot() {
        if(static::config()->disable_default_listeners)
            return;

        $disabled = (array) static::config()->disabled_namespaces;
        $events = (array) static::config()->events;

        if(count($events)) {
            foreach($events as $namespace => $listeners) {
                if(in_array($namespace, $disabled))
                    continue;

                foreach ($listeners as $listener => $options) {
                    $once = false;

                    if (is_array($options)) {
                        $hooks = isset($options['events']) ? $options['events'] : user_error(
                            'The listener: ' . $listener . ' requires an events key to establish which events this listener will hook into'
                        );
                        $once  = isset($options['first_time_only']);

                        if (isset($options['inject'])) {
                            $listener = $options['inject'];
                        }
                    } else {
                        $hooks = $options;
                    }

                    if (is_array($listener)) {
                        $injectListener = array_shift($listener);
                        $listener       = [\Injector::inst()->create($injectListener)] + $listener;
                    } else {
                        $listener = \Injector::inst()->create($listener);
                    }

                    $this->manager->listen($namespace, $hooks, $listener, $once);
                }
            }
        }
    }

    public static function __callStatic($name, $args = []) {
        if(method_exists(static::inst(), $name))
            return call_user_func_array(array(static::inst(), $name), $args);
    }
} 