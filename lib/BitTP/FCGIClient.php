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

/**
 * Handles communication with a FastCGI application
 *
 * @author      Pierrick Charron <pierrick@webstart.fr>
 * @author      Daniel Aharon <dan@danielaharon.com>
 * @version     2.0
 */
class FCGIClient
{
    const VERSION_1            = 1;

    const BEGIN_REQUEST        = 1;
    const ABORT_REQUEST        = 2;
    const END_REQUEST          = 3;
    const PARAMS               = 4;
    const STDIN                = 5;
    const STDOUT               = 6;
    const STDERR               = 7;
    const DATA                 = 8;
    const GET_VALUES           = 9;
    const GET_VALUES_RESULT    = 10;
    const UNKNOWN_TYPE         = 11;
    const MAXTYPE              = self::UNKNOWN_TYPE;

    const RESPONDER            = 1;
    const AUTHORIZER           = 2;
    const FILTER               = 3;

    const REQUEST_COMPLETE     = 0;
    const CANT_MPX_CONN        = 1;
    const OVERLOADED           = 2;
    const UNKNOWN_ROLE         = 3;

    const MAX_CONNS            = 'MAX_CONNS';
    const MAX_REQS             = 'MAX_REQS';
    const MPXS_CONNS           = 'MPXS_CONNS';

    const HEADER_LEN           = 8;

    /**
     * Socket
     * @var Resource
     */
    private $_sock = null;

    /**
     * Host
     * @var String
     */
    private $_host = null;

    /**
     * Port
     * @var Integer
     */
    private $_port = null;

    /**
     * Keep Alive
     * @var Boolean
     */
    private $_keepAlive = false;

    /**
     * A request has been sent.
     * @var Boolean
     */
    private $_awaitingResponse = false;

    /**
     * Constructor
     *
     * @param String $host Host of the FastCGI application
     * @param Integer $port Port of the FastCGI application
     */
    public function __construct($host, $port)
    {
        $this->_host = $host;
        $this->_port = $port;
    }

    /**
     * Destructor
     */
    public function __destruct() {
        socket_close($this->_sock);
    }

    /**
     * Define whether or not the FastCGI application should keep the connection
     * alive at the end of a request
     *
     * @param Boolean $b true if the connection should stay alive, false otherwise
     */
    public function setKeepAlive($b)
    {
        $this->_keepAlive = (boolean)$b;
        if (!$this->_keepAlive && $this->_sock) {
            socket_close($this->_sock);
        }
    }

    /**
     * Get the keep alive status
     *
     * @return Boolean true if the connection should stay alive, false otherwise
     */
    public function getKeepAlive()
    {
        return $this->_keepAlive;
    }

    /**
     * Create a connection to the FastCGI application
     */
    private function connect()
    {
        if (!$this->_sock) {

            $this->_sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            socket_set_nonblock($this->_sock);
            @socket_connect($this->_sock, $this->_host, $this->_port);  // Block the "Operation now in progress" warning.

            if (!$this->_sock) {
                throw new Exception(
                    'Unable to connect to FastCGI application - ' .
                    socket_strerror(socket_last_error($this->_sock))
                );
            }
        }
    }

    /**
     * Build a FastCGI packet
     *
     * @param Integer $type Type of the packet
     * @param String $content Content of the packet
     * @param Integer $requestId RequestId
     */
    private function buildPacket($type, $content, $requestId = 1)
    {
        $clen = strlen($content);
        return chr(self::VERSION_1)         /* version */
            . chr($type)                    /* type */
            . chr(($requestId >> 8) & 0xFF) /* requestIdB1 */
            . chr($requestId & 0xFF)        /* requestIdB0 */
            . chr(($clen >> 8 ) & 0xFF)     /* contentLengthB1 */
            . chr($clen & 0xFF)             /* contentLengthB0 */
            . chr(0)                        /* paddingLength */
            . chr(0)                        /* reserved */
            . $content;                     /* content */
    }

    /**
     * Build an FastCGI Name value pair
     *
     * @param String $name Name
     * @param String $value Value
     * @return String FastCGI Name value pair
     */
    private function buildNvpair($name, $value)
    {
        $nlen = strlen($name);
        $vlen = strlen($value);
        if ($nlen < 128) {
            /* nameLengthB0 */
            $nvpair = chr($nlen);
        } else {
            /* nameLengthB3 & nameLengthB2 & nameLengthB1 & nameLengthB0 */
            $nvpair = chr(($nlen >> 24) | 0x80) . chr(($nlen >> 16) & 0xFF) . chr(($nlen >> 8) & 0xFF) . chr($nlen & 0xFF);
        }
        if ($vlen < 128) {
            /* valueLengthB0 */
            $nvpair .= chr($vlen);
        } else {
            /* valueLengthB3 & valueLengthB2 & valueLengthB1 & valueLengthB0 */
            $nvpair .= chr(($vlen >> 24) | 0x80) . chr(($vlen >> 16) & 0xFF) . chr(($vlen >> 8) & 0xFF) . chr($vlen & 0xFF);
        }
        /* nameData & valueData */
        return $nvpair . $name . $value;
    }

    /**
     * Read a set of FastCGI Name value pairs
     *
     * @param String $data Data containing the set of FastCGI NVPair
     * @return array of NVPair
     */
    private function readNvpair($data, $length = null)
    {
        $array = array();

        if ($length === null) {
            $length = strlen($data);
        }

        $p = 0;

        while ($p != $length) {

            $nlen = ord($data{$p++});
            if ($nlen >= 128) {
                $nlen = ($nlen & 0x7F << 24);
                $nlen |= (ord($data{$p++}) << 16);
                $nlen |= (ord($data{$p++}) << 8);
                $nlen |= (ord($data{$p++}));
            }
            $vlen = ord($data{$p++});
            if ($vlen >= 128) {
                $vlen = ($nlen & 0x7F << 24);
                $vlen |= (ord($data{$p++}) << 16);
                $vlen |= (ord($data{$p++}) << 8);
                $vlen |= (ord($data{$p++}));
            }
            $array[substr($data, $p, $nlen)] = substr($data, $p+$nlen, $vlen);
            $p += ($nlen + $vlen);
        }

        return $array;
    }

    /**
     * Decode a FastCGI Packet
     *
     * @param String $data String containing all the packet
     * @return array
     */
    private function decodePacketHeader($data)
    {
        $ret = array();
        $ret['version']       = ord($data{0});
        $ret['type']          = ord($data{1});
        $ret['requestId']     = (ord($data{2}) << 8) + ord($data{3});
        $ret['contentLength'] = (ord($data{4}) << 8) + ord($data{5});
        $ret['paddingLength'] = ord($data{6});
        $ret['reserved']      = ord($data{7});
        return $ret;
    }

    /**
     * Read a FastCGI Packet
     *
     * @return array
     */
    private function readPacket()
    {
        if ($packet = socket_read($this->_sock, self::HEADER_LEN)) {

            $resp = $this->decodePacketHeader($packet);

            if ($len = $resp['contentLength'] + $resp['paddingLength']) {
                $resp['content'] = substr(socket_read($this->_sock, $len), 0, $resp['contentLength']);
            } else {
                $resp['content'] = '';
            }
            return $resp;
        } else {
            return false;
        }
    }

    /**
     * Get Informations on the FastCGI application
     *
     * @param array $requestedInfo information to retrieve
     * @return array
     */
    public function getValues(array $requestedInfo)
    {
        $this->connect();

        $request = '';
        foreach ($requestedInfo as $info) {
            $request .= $this->buildNvpair($info, '');
        }

        socket_write($this->_sock, $this->buildPacket(self::GET_VALUES, $request, 0));
        $this->_awaitingResponse = true;

        // Block on this call.
        while ($this->_awaitingResponse) {

            $resp = $this->readPacket();

            if ($resp) {
                $this->_awaitingResponse = false;
            } else {
                usleep(10);
            }
        }

        if ($resp['type'] == self::GET_VALUES_RESULT) {
            return $this->readNvpair($resp['content'], $resp['length']);
        } else {
            throw new Exception('Unexpected response type, expecting GET_VALUES_RESULT');
        }
    }

    /**
     * Execute a request to the FastCGI application
     *
     * @param array $params Array of parameters
     * @param String $stdin Content
     * @return Boolean Return true on success, false on failure.
     */
    public function request(array $params, $stdin)
    {
        $this->connect();

        $request = $this->buildPacket(self::BEGIN_REQUEST, chr(0) . chr(self::RESPONDER) . chr((int) $this->_keepAlive) . str_repeat(chr(0), 5));

        $paramsRequest = '';
        foreach ($params as $key => $value) {
            $paramsRequest .= $this->buildNvpair($key, $value);
        }
        if ($paramsRequest) {
            $request .= $this->buildPacket(self::PARAMS, $paramsRequest);
        }
        $request .= $this->buildPacket(self::PARAMS, '');

        if ($stdin) {
            $request .= $this->buildPacket(self::STDIN, $stdin);
        }
        $request .= $this->buildPacket(self::STDIN, '');

        // Write the request and break.
        $this->_awaitingResponse = (boolean) socket_write($this->_sock, $request);
        return $this->_awaitingResponse;

    }

    /**
     * FCGIClient::formatResponse()
     *
     * Format the response into an array with separate headers and body.
     *
     * @param $response The plain, unformatted response.
     *
     * @return array An array containing the headers and body content.
     */
    private static function formatResponse($response) {

        // Split the header from the body.  Split on \n\n.
        $doubleCr = strpos($response, "\r\n\r\n");
        $rawHeader = substr($response, 0, $doubleCr);
        $rawBody = substr($response, $doubleCr, strlen($response));

        // Format the header.
        $header = array();
        $headerLines = explode("\n", $rawHeader);

        foreach ($headerLines as $line) {
            if (preg_match('/([\w-]+):\s*(.*)$/', $line, $matches)) {
                // ['Content-type'] => 'text/plain'
                $header[$matches[1]] = $matches[2];
            }
        }

        return array(
            'headers' => $header,
            'body'    => trim($rawBody)
        );
    }

    /**
     * Collect the response from a FastCGI request.
     *
     * @return String Return response.
     */
    public function response() {

        $response = '';

        while ($this->_awaitingResponse) {

            $resp = $this->readPacket();

            if ($resp) {
                // Check for the end of the response.
                if ($resp['type'] == self::END_REQUEST) {
                    $this->_awaitingResponse = false;
                // Check for response content.
                } elseif ($resp['type'] == self::STDOUT || $resp['type'] == self::STDERR) {
                    $response .= $resp['content'];
                }
            } else {
                usleep(10);
            }
        }

        if (!is_array($resp)) {
            throw new Exception('Bad request');
        }

        switch (ord($resp['content']{4})) {
            case self::CANT_MPX_CONN:
                throw new Exception('This app can\'t multiplex [CANT_MPX_CONN]');
                break;
            case self::OVERLOADED:
                throw new Exception('New request rejected; too busy [OVERLOADED]');
                break;
            case self::UNKNOWN_ROLE:
                throw new Exception('Role value not known [UNKNOWN_ROLE]');
                break;
            case self::REQUEST_COMPLETE:
                return static::formatResponse($response);
        }
    }
}
