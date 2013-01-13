<?php
/**
 * Note : Code is released under the GNU LGPL
 *
 * Please do not change the header of this file
 *
 * This library is free software; you can redistribute it and/or modify it under the terms of the GNU
 * Lesser General Public License as published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * See the GNU Lesser General Public License for more details.
 */

require_once dirname(__FILE__).'/Exception.php';

/**
 * Handles communication with a FastCGI application
 *
 * @author      Erik Bernhardson <bernhardsonerik@gmail.com>
 * @version     2.0
 */
class BitTP_Exception
{
    static public function socketCreate()
    {
        $err = socket_last_error();
        return new self("Couldn't create socket - $err - ".socket_strerror($err));
    }

    static public function socketConnect($socket, $host, $port)
    {
        if ($port) {
            $host .= ":$port";
        }
        return self::socketError("Failure connecting to $host", $socket);
    }

    static public function socketRead($socket)
    {
        if ($socket === null) {
            return self::requestAborted();
        }

        return self::socketError('Failure reading socket', $socket);
    }

    static public function socketWrite($socket)
    {
        if ($socket === null) {
            return self::requestAborted();
        }

        return self::socketError('Failure writing socket', $socket);
    }

    static public function socketError($message, $socket)
    {
        $err = socket_last_error($socket);
        return new self("$message - $err - ".socket_strerror($err));
    }
}
