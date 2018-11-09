<?php

namespace AirPort;

use Exception;

class Message
{
    const HEADER_FORMAT = 'a4N8x12a32x48';
    const HEADER_UNPACK_FORMAT = 'a4magic/Nversion/Nheader_checksum/Nbody_checksum/Nbody_size/Nflags/Nunused/Ncommand/Nerror_code/x12/a32key/x48';
    const HEADER_MAGIC = 'acpp';
    const HEADER_SIZE = 128;

    public $version;
    public $flags;
    public $unused;
    public $command;
    public $error_code;

    public $body_size;
    public $body_checksum;

    public $key;
    public $body;

    /**
     * Create a new Message.
     *
     * @param int $version
     * @param int $flags
     * @param int $unused
     * @param int $command
     * @param int $error_code
     * @param string $key
     * @param string $body
     * @param int $body_size
     */
    public function __construct($version, $flags, $unused, $command, $error_code, $key, $body = null, $body_size = null)
    {
        $this->version = $version;
        $this->flags = $flags;
        $this->unused = $unused;
        $this->command = $command;
        $this->error_code = $error_code;

        // Body is not specified, this is a stream header
        if ($body === null) {
            // The body size is already specified, don't override it
            $this->body_size = $body_size !== null ? $body_size : -1;
            $this->body_checksum = 1;
        } else {
            $this->body_size = $body_size !== null ? $body_size : strlen($body);
            $this->body_checksum = hexdec(hash('adler32', $body));
        }

        $this->key = $key;
        $this->body = $body;
    }

    public function __toString()
    {
        return 'ACP message:' . "\n"
            . 'Body checksum: ' . $this->body_checksum
            . 'Body size:     ' . $this->body_size
            . 'Flags:         ' . $this->flags
            . 'Unused:        ' . $this->unused
            . 'Command:       ' . $this->command
            . 'Error code:    ' . $this->error_code
            . 'Key:           ' . $this->key;
    }

    public static function unpackHeader($data)
    {
        if (strlen($data) !== self::HEADER_SIZE) {
            throw new Exception('Header data must be 128 characters.');
        }

        $unpacked = $raw = unpack(self::HEADER_UNPACK_FORMAT, $data);

        // All values are unpacked as unsigned integers
        $unpacked['version'] = self::toSignedInt($unpacked['version']);
        $unpacked['header_checksum'] = self::toSignedInt($unpacked['header_checksum']);
        $unpacked['body_checksum'] = self::toSignedInt($unpacked['body_checksum']);
        $unpacked['body_size'] = self::toSignedInt($unpacked['body_size']);
        $unpacked['flags'] = self::toSignedInt($unpacked['flags']);
        $unpacked['unused'] = self::toSignedInt($unpacked['unused']);
        $unpacked['command'] = self::toSignedInt($unpacked['command']);
        $unpacked['error_code'] = self::toSignedInt($unpacked['error_code']);

        return $unpacked;
    }

    protected static function toSignedInt($value)
    {
        if ($value & 0x80000000) // is negative
            return $value - 0x100000000;

        return $value;
    }

    public static function packHeader($data)
    {
        $packed = pack(
            self::HEADER_FORMAT,
            $data['magic'],
            $data['version'],
            $data['header_checksum'],
            $data['body_checksum'],
            $data['body_size'],
            $data['flags'],
            $data['unused'],
            $data['command'],
            $data['error_code'],
            $data['key']
        );

        return $packed;
    }

    public static function parseRaw($data, $validate = true)
    {
        if (strlen($data) < self::HEADER_SIZE) {
            throw new Exception('Header data must be 128 characters.');
        }

        $header_data = substr($data, 0, self::HEADER_SIZE);
        $body_data = substr($data, self::HEADER_SIZE);

        $unpacked = self::unpackHeader($header_data);

        if ($validate) {
            self::validateHeader($body_data, $unpacked);
        }

        return new Message($unpacked['version'], $unpacked['flags'], $unpacked['unused'], $unpacked['command'], $unpacked['error_code'], $unpacked['key'], $body_data, $unpacked['body_size']);
    }

    public static function validateHeader($body, $data)
    {
        if ($data['magic'] !== self::HEADER_MAGIC) {
            throw new Exception('Bad header magic.');
        }

        $versions = [0x00000001, 0x00030001, 16777984];
        if (!in_array($data['version'], $versions)) {
            throw new Exception('Unknown version ' . $data['version'] . '.');
        }

        if ($body && $data['body_size'] === -1) {
            throw new Exception('Cannot handle stream header with data attached.');
        }

        if ($body && $data['body_size'] !== strlen($body)) {
            throw new Exception('Message body size does not match available data.');
        }

        if ($body && $data['body_checksum'] !== ($expected_body_checksum = hexdec(hash('adler32', $body)))) {
            throw new Exception('Body checksum does not match: Expected ' . $expected_body_checksum . ' got ' . $data['body_checksum'] . '.');
        }

        // TODO: check flags
        // TODO: check status

        $commands = [1, 3, 4, 5, 6, 0x14, 0x15, 0x16, 0x17, 0x18, 0x19, 0x1a, 0x1b];
        if (!in_array($data['command'], $commands)) {
            throw new Exception('Unknown command ' . $data['command'] . '.');
        }

        // TODO: check error code
    }

    public static function generateAcpHeaderKey($password)
    {
        $length = 0x20;
        $key = Keystream::generateAcpKeystream($length);

        $buffer = str_pad(substr($password, 0, $length), $length, "\0");
        $encrypted = '';

        for ($i = 0; $i < $length; $i++) {
            $encrypted .= chr(ord(substr($key, $i, 1)) ^ ord(substr($buffer, $i, 1)));
        }

        return $encrypted;
    }

    public static function composeGetPropCommand($flags, $password, $payload)
    {
        $message = new Message(0x00030001, $flags, 0, 0x14, 0, self::generateAcpHeaderKey($password), $payload);
        return $message->composeRawPacket();
    }

    public function composeRawPacket()
    {
        $data = $this->composeHeader();

        $data .= $this->body;

        return $data;
    }

    public function composeHeader()
    {
        $tmphdr = self::packHeader([
            'magic' => self::HEADER_MAGIC,
            'version' => $this->version,
            'header_checksum' => 0,
            'body_checksum' => $this->body_checksum,
            'body_size' => $this->body_size,
            'flags' => $this->flags,
            'unused' => $this->unused,
            'command' => $this->command,
            'error_code' => $this->error_code,
            'key' => $this->key,
        ]);

        $data = self::packHeader([
            'magic' => self::HEADER_MAGIC,
            'version' => $this->version,
            'header_checksum' => hexdec(hash('adler32', $tmphdr)),
            'body_checksum' => $this->body_checksum,
            'body_size' => $this->body_size,
            'flags' => $this->flags,
            'unused' => $this->unused,
            'command' => $this->command,
            'error_code' => $this->error_code,
            'key' => $this->key,
        ]);

        return $data;
    }
}
