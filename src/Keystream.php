<?php

namespace AirPort;

class Keystream
{
    public static function getStaticKey()
    {
        return hex2bin('5b6faf5d9d5b0e1351f2da1de7e8d673');
    }

    public static function generateAcpKeystream($length)
    {
        $key = '';
        $idx = 0;

        $static_key = self::getStaticKey();

        while ($idx < $length) {
            $key .= chr(($idx + 0x55 & 0xff) ^ ord(substr($static_key, $idx % strlen($static_key), 1)));

            $idx++;
        }

        return $key;
    }
}
