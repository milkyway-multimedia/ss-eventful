<?php namespace Milkyway\SS\Events;

use League\Event\EmitterInterface;
use League\Event\PriorityEmitter;

/**
 * Milkyway Multimedia
 * EventDispatcher.php
 *
 * @package relatewell.org.au
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

class Dispatcher {
    protected $emitter;

    public static function config() {
        return \Config::inst()->forClass('EventDispatcher');
    }

    public function __construct(EmitterInterface $emitter) {
        $this->emitter = $emitter;
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
                    $priority = PriorityEmitter::P_LOW;

                    if (is_array($options)) {
                        $hooks = isset($options['events']) ? $options['events'] : user_error(
                            'The listener: ' . $listener . ' requires an events key to establish which events this listener will hook into'
                        );

                        $once  = isset($options['first_time_only']);

	                    if (isset($options['inject'])) {
		                    $listener = $options['inject'];
	                    }

	                    if (isset($options['priority'])) {
		                    $priority = $options['priority'];
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

	                $this->listen($namespace, $hooks, $listener, $once, $priority);
                }
            }
        }
    }

	public function listen($namespace, $hooks, $listener, $once = false, $priority = PriorityEmitter::P_NORMAL) {
		$hooks = (array) $hooks;

		foreach($hooks as $hook) {
			$event = $this->addNamespaceToHook($hook, $namespace);

			if($once)
				$this->emitter->addOneTimeListener($event, $listener, $priority);
			else
				$this->emitter->addListener($event, $listener, $priority);
		}
	}

	public function fire($namespace, $hooks) {
		$hooks = (array) $hooks;
		$events = [];

		foreach($hooks as $hook) {
			$events[] = $this->addNamespaceToHook($hook, $namespace);
		}

		$this->emitter->emitBatch($events);
	}

	public function emitter() {
		return $this->emitter;
	}

    public function __call($fn, $args = []) {
        if(method_exists($this->emitter, $fn))
            return call_user_func_array([$this->emitter, $fn], $args);
        else {
            $class = __CLASS__;
            throw new \Exception("Object->__call(): the method '$fn' does not exist on '$class'", 2175);
        }
    }

	protected function addNamespaceToHook($hook, $namespace = '') {
		return trim($namespace) ? "$namespace.$hook" : $hook;
	}
} 