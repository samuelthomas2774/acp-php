<?php

namespace AirPort;

class Client
{
    protected $host;
    protected $port;
    protected $password;

    protected $session;

    public function __construct($host, $port, $password)
    {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;

        $this->session = new Session($host, $port, $password);
    }

    public function connect($timeout = 30)
    {
        return $this->session->connect($timeout);
    }

    public function disconnect()
    {
        return $this->session->close();
    }

    public function send($data)
    {
        return $this->session->send($data);
    }

    public function receive($size)
    {
        return $this->session->receive($size);
    }

    public function receiveMessageHeader()
    {
        return $this->receive(Message::HEADER_SIZE);
    }

    public function receivePropertyElementHeader()
    {
        return $this->receive(Property::ELEMENT_HEADER_SIZE);
    }

    public function getProperties($prop_names)
    {
        $payload = '';

        foreach ($prop_names as $name) {
            $payload .= Property::composeRawElement(0, new Property($name));
        }

        $request = Message::composeGetPropCommand(4, $this->password, $payload);
        $this->send($request);

        $reply = $this->receiveMessageHeader();
        $reply_header = Message::parseRaw($reply);

        if ($reply_header->error_code !== 0) {
            echo 'Client::getProperties error code:' . $reply_header->error_code . "\n";
            return [];
        }

        $props = [];

        while (true) {
            $prop_header = $this->receivePropertyElementHeader();

            $data = Property::unpackHeader($prop_header);
            $name = $data['name'];
            $flags = $data['flags'];
            $size = $data['size'];

            $prop_data = $this->receive($size);

            if ($flags & 1) {
                $data = unpack('N', $prop_data);
                $error_code = $data[1];

                echo 'Error requesting value for property ' . $name . ': ' . $error_code . "\n";
                continue;
            }

            $prop = new Property($name, $prop_data);

            if (!$prop->name && !$prop->value) {
                break;
            }

            array_push($props, $prop);
        }

        return $props;
    }

    public function setProperties($props)
    {
        $payload = '';
        foreach ($props as $data) {
            $name = $data['name'];
            $prop = $data['prop'];

            $payload .= Property::composeRawElement(0, $prop);
        }

        $request = Message::composeSetPropCommand(0, $this->password, $payload);
        $this->send($request);

        $raw_reply = $this->receiveMessageHeader();
        $reply_header = Message::parseRaw($raw_reply);

        if ($reply_header->error_code !== 0) {
            echo 'Set properties error code: ' . $reply_header->error_code . "\n";
            return;
        }

        $prop_header = $this->receivePropertyElementHeader();
        $data = Property::parseRawElementHeader($prop_header);
        $name = $data['name'];
        $flags = $data['flags'];
        $size = $data['size'];

        $prop_data = $this->receive($size);

        if ($flags) {
            $data = unpack('N', $prop_data);
            $error_code = $data[1];

            echo 'Error setting value for property ' . $name . ' - ' . $error_code . "\n";
        }
    }
}
