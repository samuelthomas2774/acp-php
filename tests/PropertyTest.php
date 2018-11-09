<?php

namespace AirPort\Tests;

use AirPort\Property;

use PHPUnit\Framework\TestCase;

final class PropertyTest extends TestCase
{
    /**
     * Test packing property headers.
     *
     * This tries to compose a raw element header for the property "dbug" with no flags and the size 4.
     */
    public function testComposeRawElementHeader()
    {
        $expected_hex = '646275670000000000000004';

        $header = Property::composeRawElementHeader('dbug', 0, 4);
        $header_hex = bin2hex($header);

        $this->assertEquals($expected_hex, $header_hex);
    }

    /**
     * Test composing a raw element.
     *
     * This tries to compose a raw element for the property "dbug" with no value or flags.
     */
    public function testComposeRawElement()
    {
        $expected_hex = '64627567000000000000000400000000';

        $raw_element = Property::composeRawElement(0, new Property('dbug'));
        $raw_element_hex = bin2hex($raw_element);

        $this->assertEquals($expected_hex, $raw_element_hex);
    }

    /**
     * Test parsing a raw element.
     *
     * This tries to compose a raw element for the property "dbug" with no value or flags.
     */
    public function testParseRawElement()
    {
        $element_hex = '64627567000000000000000400003000';

        $property = Property::parseRawElement(hex2bin($element_hex));

        $this->assertEquals('dbug', $property->name);
        $this->assertEquals(0x3000, $property->value);
    }
}
