<?php namespace Milkyway\SS\Events;

use League\Event\EmitterInterface;
use League\Event\PriorityEmitter;

/**
 * Milkyway Multimedia
 * Dispatcher.php
 *
 * @package milkyway-multimedia/ss-events-handler
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

class Dispatcher {
    protected $emitter;
	protected $configKey = 'Eventful';

    private $_booted = [];
	private $_allBooted = false;

    public function config() {
        return \Config::inst()->forClass($this->configKey);
    }

    public function __construct(EmitterInterface $emitter, $configKey = 'Eventful') {
        $this->emitter = $emitter;
        $this->configKey = $configKey;
    }

	public function listen($events, $listener, $once = false, $priority = EmitterInterface::P_NORMAL) {
		$events = (array) $events;

		foreach($events as $event) {
            $eventListener = is_callable($listener) || $listener instanceof ListenerInterface ? $listener : [$listener, end(explode('.',$event))];

			if($once)
				$this->emitter->addOneTimeListener($event, $eventListener, $priority);
			else
				$this->emitter->addListener($event, $eventListener, $priority);
		}
	}

	public function fire() {
		$args = func_get_args();

		$events = (array)array_shift($args);

		foreach($events as $event) {
			$this->boot($event);
			call_user_func_array([$this->emitter, 'emit'], array_merge([$event], $args));
		}
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

	protected function boot($event = '') {
		if($this->isBooted($event)) return;

		$disabled = (array) $this->config()->disable_default_namespaces;
		$listens = (array) $this->config()->listeners;

		if($event && isset($listens[$event])) {
			$this->bootEvent($event, (array)$listens[$event]);
			$this->_booted[$event];
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
			$priority = PriorityEmitter::P_LOW;

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

			if (is_array($listener)) {
				$injectListener = array_shift($listener);
				$listener       = [\Injector::inst()->create($injectListener)] + $listener;
			} else {
				$listener = \Injector::inst()->create($listener);
			}

			$this->listen($event, $listener, $once, $priority);
		}
	}
} 