<?php

namespace AirPort\Tests;

use AirPort\Message;

use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    use CreatesClient;

    public function testComposeGetPropCommand()
    {
        // Property::composeRawElement(0, new Property('dbug'))
        $payload = hex2bin('64627567000000000000000400000000');

        $expected_hex = '61637070000300011bef117b17c301a700000010000000040000000000000014000000000000000000000000000000007a5c8b71ad6f324f0cac857d868ab5173e09c835f431657f3c9cb56d969aa50700000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000064627567000000000000000400000000';

        $message = Message::composeGetPropCommand(4, 'testing', $payload);
        $message_hex = bin2hex($message);

        $this->assertEquals($expected_hex, $message_hex);
    }

    public function testParseRawCommand()
    {
        $message_hex = '61637070000300011bef117b17c301a700000010000000040000000000000014000000000000000000000000000000007a5c8b71ad6f324f0cac857d868ab5173e09c835f431657f3c9cb56d969aa50700000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000064627567000000000000000400000000';

        $message = Message::parseRaw(hex2bin($message_hex), false);

        $this->assertEquals(196609, $message->version);
        $this->assertEquals(4, $message->flags);
        $this->assertEquals(0, $message->unused);
        $this->assertEquals(20, $message->command);
        $this->assertEquals(0, $message->error_code);
        $this->assertEquals(hex2bin('7a5c8b71ad6f324f0cac857d868ab5173e09c835f431657f3c9cb56d969aa507'), $message->key);
        $this->assertEquals(hex2bin('64627567000000000000000400000000'), $message->body);
        $this->assertEquals(16, $message->body_size);
        $this->assertEquals(398655911, $message->body_checksum);
    }
}
