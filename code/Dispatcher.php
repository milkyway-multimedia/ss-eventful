<?php namespace Milkyway\SS\Eventful;

/**
 * Milkyway Multimedia
 * Dispatcher.php
 *
 * @package milkyway-multimedia/ss-eventful
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use League\Event\EmitterInterface;
use League\Event\EventInterface;
use League\Event\ListenerInterface;
use Exception;
use Config;
use Object;

class Dispatcher implements Contract
{
    protected $emitter;
    protected $configKey = 'Eventful';

    private $_booted = [];
    private $_namespaced = [];
    private $_allBooted = false;

    public function __construct(EmitterInterface $emitter, $configKey = 'Eventful')
    {
        $this->emitter = $emitter;
        $this->configKey = $configKey;
    }

    public function listen($events, $listener, $once = false, $priority = EmitterInterface::P_NORMAL)
    {
        if (!is_array($events)) {
            $events = [$events];
        }

        foreach ($events as $event) {
            if (is_callable($listener) || ($listener instanceof ListenerInterface)) {
                $eventListener = $listener;
            } else {
                $listenerFn = explode('.', $event);
                $listenerFn = array_shift($listenerFn);

                if (strpos($listenerFn, ':') !== false) {
                    $listenerFn = explode(':', $listenerFn);
                    $listenerFn = array_pop($listenerFn);
                }

                $eventListener = [$listener, $listenerFn];
            }

            if ($once) {
                $this->emitter->addOneTimeListener($event, $eventListener, $priority);
            } else {
                $this->emitter->addListener($event, $eventListener, $priority);
            }
        }
    }

    public function fire()
    {
        $fired = [];
        $args = func_get_args();

        $events = array_shift($args);

        if (!is_array($events)) {
            $events = [$events];
        }

        foreach ($events as $event) {
            $eventName = ($event instanceof EventInterface) ? $event->getName() : $event;

            if ($this->isDisabled($eventName)) {
                continue;
            }

            $this->boot($eventName);
            $fired[] = call_user_func_array([$this->emitter, 'emit'], array_merge([$event], $args));
        }

        return $fired;
    }

    public function __call($fn, $args = [])
    {
        if (method_exists($this->emitter, $fn)) {
            return call_user_func_array([$this->emitter, $fn], $args);
        } else {
            $class = __CLASS__;
            throw new Exception("Object->__call(): the method '$fn' does not exist on '$class'", 2175);
        }
    }

    protected function config()
    {
        return Config::inst()->forClass($this->configKey);
    }

    protected function boot($event = '')
    {
        if ($this->isBooted($event)) {
            return;
        }

        $listens = (array)$this->config()->listeners;

        if ($event) {
            $events = $this->getAllNamespacedEvents($event);

            array_walk($listens, function ($listener, $event) use ($events) {
                if (isset($events['events'][$event])) {
                    $this->bootEvent($event, $listener);

                    return;
                }

                $myEvents = explode('.', $event);

                if (in_array($myEvents[0], $events['events'])) {
                    $this->bootEvent($event, $listener);

                    return;
                }
            });
//			if(($listens = array_intersect_key($listens, array_flip($events['events']))) && !empty($listens))
//				$this->bootEvent($event, $listens);
            return;
        }

        foreach ($listens as $event => $listeners) {
            $this->bootEvent($event, (array)$listeners);
        }

        $this->_allBooted = true;
    }

    protected function isBooted($event = '')
    {
        return $this->config()->disable_default_listeners || $this->_allBooted || ($event && isset($this->_booted[$event]));
    }

    protected function bootEvent($event, $listeners = [])
    {
        $events = $this->getAllNamespacedEvents($event);

        foreach ($listeners as $listener => $options) {
            foreach ($events['events'] as $event) {
                $this->addListenerForEvent($event, $listener, $options);
            }
        }
    }

    protected function addListenerForEvent($event, $listener, $options)
    {
        $once = false;
        $priority = EmitterInterface::P_LOW;

        if (is_array($options)) {
            $once = isset($options['first_time_only']);

            if (isset($options['class'])) {
                $listener = $options['class'];
            }

            if (isset($options['priority'])) {
                $priority = $options['priority'];
            }
        } else {
            $listener = $options;
        }

        $listenerClass = is_array($listener) ? array_shift($listener) : $listener;

        if (in_array($listenerClass, (array)$this->config()->disabled_listeners)) {
            $this->_booted[$event] = true;

            return;
        }

        if (is_array($listener)) {
            $listener = [Object::create($listenerClass)] + $listener;
        } else {
            $listener = Object::create($listener);
        }

        $this->listen($event, $listener, $once, $priority);

        foreach ((array)$event as $e) {
            $this->_booted[$e] = true;
        }
    }

    protected function isDisabled($event = null)
    {
        $events = $this->getAllNamespacedEvents($event);

        return !$event
        || in_array($event, (array)$this->config()->disabled_fully_qualified_events)
        || !empty(array_intersect($events['namespaces'], (array)$this->config()->disabled_namespaces))
        || !empty(array_intersect($events['events'], (array)$this->config()->disabled_hooks));
    }

    protected function getAllNamespacedEvents($event)
    {
        if (isset($this->_namespaced[$event])) {
            return $this->_namespaced[$event];
        }

        $namespaces = explode('.', $event);
        $hook = array_shift($namespaces);

        $this->_namespaced[$event]['events'] = [$event];

        if ($hook != $event) {
            $this->_namespaced[$event]['events'][] = $hook;
        }

        if (!empty($namespaces)) {
            $this->_namespaced[$event]['namespaces'] = array_unique(array_map(function ($value) {
                return $this->permuteArray($value);
//				return $value;
            }, $this->getUniqueCombinations($namespaces)));

            $this->_namespaced[$event]['events'] = array_unique(array_merge($this->_namespaced[$event]['events'],
                array_map(function ($value) use ($hook) {
                    return $hook . '.' . implode('.', array_pop($value));
                }, $this->_namespaced[$event]['namespaces'])));
        } else {
            $this->_namespaced[$event]['namespaces'] = [];
        }

        return $this->_namespaced[$event];
    }

    protected function getUniqueCombinations($array)
    {
        // initialize by adding the empty set
        $results = [[array_pop($array)]];

        foreach ($array as $element) {
            foreach ($results as $combination) {
                $results[] = array_merge([$element], $combination);
            }
        }

        return $results;
    }

    protected function permuteArray($items, $perms = [])
    {
        $results = [];

        if (empty($items)) {
            $results[] = $perms;
        } else {
            for ($i = count($items) - 1; $i >= 0; --$i) {
                $newItems = $items;
                $newPerms = $perms;
                list($foo) = array_splice($newItems, $i, 1);
                array_unshift($newPerms, $foo);
                $results = array_merge($results, $this->permuteArray($newItems, $newPerms));
            }
        }

        return $results;
    }
}
