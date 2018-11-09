<?php

namespace AirPort;

use Exception;

class Session
{
    protected $host;
    protected $port;
    protected $password;

    protected $socket;

    protected $encryption_context;
    protected $encryption_method;
    protected $decryption_method;

    public function __construct($host, $port, $password)
    {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
    }

    public function connect($timeout = 10)
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        // Bind the source address
        socket_bind($this->socket, '192.168.2.78');

        // Connect to destination address
        socket_connect($this->socket, $this->host, $this->port);
    }

    public function close()
    {
        if (!$this->socket) return;

        socket_close($this->socket);

        $this->socket = null;
    }

    public function sendAndReceive($data, $size, $timeout = 10)
    {
        $this->send($data);

        $response = $this->receiveSize($size, $timeout);

        if ($this->decryption_method) {
            return call_user_func($this->decryption_method, $response);
        }

        return $response;
    }

    public function send($data)
    {
        if (!$this->socket) {
            throw new Exception('Socket not connected.');
        }

        if ($this->encryption_method) {
            $data = call_user_func($this->encryption_method, $data);
        }

        echo 'Sending data ' . $data . "\n";

        socket_write($this->socket, $data);
    }

    public function receiveSize($size, $timeout = 10)
    {
        $time = time();

        $data = '';

        while (true) {
            $read = socket_read($this->socket, $size - strlen($data), PHP_BINARY_READ);

            if (!is_string($read)) {
                $code = socket_last_error();
                $message = socket_strerror($code);

                throw new Exception('Error reading from socket: ' . $message, $code);
            }

            $data .= $read;

            // We have all the data we were waiting for
            if (strlen($data) >= $size) {
                return substr($data, 0, $size);
            }

            if (time() > ($time + $timeout)) {
                throw new Exception('Timeout reading from socket: Expected ' . $size . ' but only received ' . strlen($data) . ' in ' . $timeout . ' seconds.');
            }
        }
    }

    public function receive($size, $timeout = 10)
    {
        $data = $this->receiveSize($size, $timeout);

        if ($this->decryption_method) {
            return call_user_func($this->decryption_method, $data);
        }

        return $data;
    }
}
