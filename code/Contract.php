<?php namespace Milkyway\SS\Eventful;

/**
 * Milkyway Multimedia
 * Contract.php
 *
 * @package milkyway-multimedia/ss-eventful
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use League\Event\EmitterInterface;

interface Contract
{
    public function listen($events, $listener, $once = false, $priority = EmitterInterface::P_NORMAL);

    public function fire();
}
