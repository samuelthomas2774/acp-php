<?php

namespace AirPort\Tests;

use AirPort\Message;
use AirPort\Keystream;

use PHPUnit\Framework\TestCase;

final class KeystreamTest extends TestCase
{
    public function testGenerateMessageHeaderKey()
    {
        $expected_hex = '7a5c8b71ad6f324f0cac857d868ab5173e09c835f431657f3c9cb56d969aa507';

        $key = Message::generateAcpHeaderKey('testing');
        $key_hex = bin2hex($key);

        $this->assertEquals($expected_hex, $key_hex);
    }
}
