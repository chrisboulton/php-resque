<?php

namespace Resque\Reserver;

class UnknownReserverException extends \UnexpectedValueException
{
    /**
     * @param string $reserver The name of the reserver.
     */
    public function __construct($reserver)
    {
        parent::__construct("Unknown reserver '$reserver'");
    }
}
