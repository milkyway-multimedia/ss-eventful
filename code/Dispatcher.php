<?php namespace Milkyway\SS\Eventful;

use League\Event\EmitterInterface;
use League\Event\EventInterface;
use League\Event\ListenerInterface;

/**
 * Milkyway Multimedia
 * Milkyway\SS\Eventful\Dispatcher.php
 *
 * @package milkyway-multimedia/ss-eventful
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

class Dispatcher implements Contract {
    protected $emitter;
	protected $configKey = 'Eventful';

    private $_booted = [];
	private $_allBooted = false;

    public function __construct(EmitterInterface $emitter, $configKey = 'Eventful') {
        $this->emitter = $emitter;
        $this->configKey = $configKey;
    }

	public function listen($events, $listener, $once = false, $priority = EmitterInterface::P_NORMAL) {
		if(!is_array($events))
			$events = [$events];

		foreach($events as $event) {
			if(is_callable($listener) || ($listener instanceof ListenerInterface))
				$eventListener = $listener;
			else {
				$listenerFn = explode('.',$event);
				$listenerFn = array_pop($listenerFn);
				$eventListener = [$listener, $listenerFn];
			}

			if($once)
				$this->emitter->addOneTimeListener($event, $eventListener, $priority);
			else
				$this->emitter->addListener($event, $eventListener, $priority);
		}
	}

	public function fire() {
		$fired = [];
		$args = func_get_args();

		$events = array_shift($args);

		if(!is_array($events))
			$events = [$events];

		foreach($events as $event) {
			$eventName = ($event instanceof EventInterface) ? $event->getName() : $event;

			if($this->isDisabled($eventName)) continue;

			$this->boot($eventName);
			$fired[] = call_user_func_array([$this->emitter, 'emit'], array_merge([$event], $args));
		}

		return $fired;
	}

    public function __call($fn, $args = []) {
        if(method_exists($this->emitter, $fn))
            return call_user_func_array([$this->emitter, $fn], $args);
        else {
            $class = __CLASS__;
            throw new \Exception("Object->__call(): the method '$fn' does not exist on '$class'", 2175);
        }
    }

	protected function config() {
		return \Config::inst()->forClass($this->configKey);
	}

	protected function boot($event = '') {
		if($this->isBooted($event)) return;

		$listens = (array) $this->config()->listeners;

		if($event && isset($listens[$event])) {
			$this->bootEvent($event, (array)$listens[$event]);
			$this->_booted[$event] = true;
			return;
		}

		foreach ($listens as $event => $listeners) {
			$this->bootEvent($event, (array)$listeners);
		}

		$this->_allBooted = true;
	}

	protected function isBooted($event = '') {
		return $this->config()->disable_default_listeners || $this->_allBooted || ($event && isset($this->_booted[$event]));
	}

	protected function bootEvent($event, $listeners = []) {
		foreach($listeners as $listener => $options) {
			$once = false;
			$priority = EmitterInterface::P_LOW;

			if (is_array($options)) {
				$once  = isset($options['first_time_only']);

				if (isset($options['class'])) {
					$listener = $options['class'];
				}

				if (isset($options['priority'])) {
					$priority = $options['priority'];
				}
			}
			else {
				$listener = $options;
			}

			$listenerClass = is_array($listener) ? array_shift($listener) : $listener;

			if(in_array($listenerClass, (array)$this->config()->disabled_listeners))
				continue;

			if (is_array($listener)) {
				$listener = [\Injector::inst()->create($listenerClass)] + $listener;
			}
			else {
				$listener = \Injector::inst()->create($listener);
			}

			$this->listen($event, $listener, $once, $priority);
		}
	}

	protected function isDisabled($event = null) {
		list($namespace, $hook) = explode('.', $event);

		if(!$event) {
			$hook = $namespace;
			$namespace = '';
		}

		return !$event
		|| ($namespace && in_array($namespace, (array)$this->config()->disabled_namespaces))
		|| (in_array($event, (array)$this->config()->disabled_events))
		|| (in_array($hook, (array)$this->config()->disabled_hooks));
	}
} 