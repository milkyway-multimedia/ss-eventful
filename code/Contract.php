<?php
/**
 * Milkyway Multimedia
 * Contract.php
 *
 * @package milkywaymultimedia.com.au
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

namespace Milkyway\SS\Eventful;

use League\Event\EmitterInterface;

interface Contract {
	public function listen($events, $listener, $once = false, $priority = EmitterInterface::P_NORMAL);
	public function fire();
}