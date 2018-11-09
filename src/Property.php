<?php

namespace AirPort;

use Exception;

class Property
{
    const ELEMENT_HEADER_FORMAT = 'a4N2';
    const ELEMENT_HEADER_UNPACK_FORMAT = 'a4name/Nflags/Nsize';
    const ELEMENT_HEADER_SIZE = 12;

    public $name;
    public $value;

    protected static $properties;

    public function __construct($name, $value = null)
    {
        // Handle null property packed name and value first
        if ($name === "\0\0\0\0" && $value === "\0\0\0\0") {
            $name = null;
            $value = null;
        }

        if ($name && !in_array($name, self::getSupportedPropertyNames())) {
            throw new Exception('Invalid property name passed to initializer: ' . $name . '.');
        }

        $this->name = $name;

        if ($value !== null) {
            $prop = self::getProperty($name);
            $prop_type = $prop['type'];

            if (!method_exists($this, '__init_' . $prop_type)) {
                throw new Exception('Missing init handler for "' . $prop_type . '" property type.');
            }

            $value = $this->{'__init_' . $prop_type}($value);

            if ($prop['validator'] && !call_user_func($prop['validator'], $value)) {
                throw new Exception('Invalid value passed to initializer for property "' . $name . '": "' . print_r($value, true) . '".');
            }
        }

        $this->value = $value;
    }

    public function __init_dec($value)
    {
        if (is_int($value)) {
            return $value;
        } elseif (is_string($value)) {
            return unpack('N', $value)[1];
        } else {
            throw new Exception('Invalid built in type');
        }
    }

    public function __init_hex($value)
    {
        if (is_int($value)) {
            return $value;
        } elseif (is_string($value)) {
            return unpack('N', $value)[1];
        }
    }

    public function __init_mac($value)
    {
        if (is_string($value)) {
            if (strlen($value) === 6) {
                return $value;
            }

            $mac_bytes = explode(':', $value);

            if (count($mac_bytes) === 6) {
                return hex2bin(implode('', $mac_bytes));
            }
        }
    }

    public function __init_bin($value)
    {
        return $value;
    }

    public function __init_cfb($value)
    {
        return $value;
    }

    public function __init_log($value)
    {
        return $value;
    }

    public function __init_str($value)
    {
        return $value;
    }

    public function __toString()
    {
        return $this->name . ': ' . $this->format();
    }

    public function format()
    {
        $prop = self::getProperty($this->name);
        $prop_type = $prop['type'];

        if (!method_exists($this, '__format_' . $prop_type)) {
            throw new Exception('Missing format handler for "' . $prop_type . '" property type.');
        }

        return $this->{'__format_' . $prop_type}($this->value);
    }

    public function __format_dec($value)
    {
        return (string)$value;
    }

    public function __format_hex($value)
    {
        return '0x' . dechex($value);
    }

    public function __format_mac($value)
    {
        $mac_bytes = [];

        for ($i = 0; $i < 6; $i++) {
            array_push($mac_bytes, substr($value, $i, 1));
        }

        return implode(':', $mac_bytes);
    }

    public function __format_bin($value)
    {
        return $value;
    }

    public function __format_cfb($value)
    {
        return $value;
    }

    public function __format_log($value)
    {
        $s = '';

        foreach (explode("\0", trim($value)) as $line) {
            $s .= $line . "\n";
        }

        return $s;
    }

    /**
     * Returns the supported properties.
     *
     * @return array
     */
    public static function getSupportedProperties()
    {
        if (is_array(self::$properties)) {
            return self::$properties;
        }

        $props = [];
        $types = ['str', 'dec', 'hex', 'log', 'mac', 'cfb', 'bin'];

        foreach (require __DIR__ . '/properties.php' as $prop) {
            $name = $prop[0];
            $type = $prop[1];
            $description = $prop[2];
            $validator = $prop[3];

            if (strlen($name) !== 4) {
                throw new Exception('Bad name in ACP properties list: ' . $name . '.');
            }

            if (!in_array($type, $types)) {
                throw new Exception('Bad type in ACP properties list: ' . $type . ' (' . $name . ').');
            }

            if (!is_string($description)) {
                throw new Exception('Missing description in ACP properties list for ' . $name . '.');
            }

            array_push($props, [
                'name' => $name, 'type' => $type,
                'description' => $description,
                'validator' => $validator,
            ]);
        }

        return self::$properties = $props;
    }

    /**
     * Returns the names of the supported ACP properties.
     *
     * @return array
     */
    public static function getSupportedPropertyNames()
    {
        return array_map(function ($prop) {
            return $prop['name'];
        }, self::getSupportedProperties());
    }

    /**
     * Returns an ACP property.
     *
     * @param string $name
     * @return array
     */
    public static function getProperty($name)
    {
        foreach (self::getSupportedProperties() as $prop) {
            if ($prop['name'] === $name) return $prop;
        }
    }

    /**
     * Creates a Property from a packed binary string.
     *
     * @param string $data
     * @return \AirPort\Property
     */
    public static function parseRawElement($data)
    {
        $parsed = self::unpackHeader(substr($data, 0, self::ELEMENT_HEADER_SIZE));

        return new Property($parsed['name'], substr($data, self::ELEMENT_HEADER_SIZE));
    }

    /**
     * Compose a raw element binary string.
     *
     * @param int $flags
     * @param \AirPort\Property $prop
     * @return string
     */
    public static function composeRawElement($flags, Property $prop)
    {
        $name = $prop->name ? $prop->name : "\0\0\0\0";
        $value = $prop->value ? $prop->value : "\0\0\0\0";

        if (is_numeric($value)) {
            return self::composeRawElementHeader($name, $flags, 4) . pack('I', $value);
        } elseif (is_string($value)) {
            return self::composeRawElementHeader($name, $flags, strlen($value)) . $value;
        } else {
            throw new Exception('Unhandled property type for raw element composition.');
        }
    }

    /**
     * Compose the header of a raw element binary string.
     *
     * @param string $name
     * @param int $flags
     * @param int $size
     * @return string
     */
    public static function composeRawElementHeader($name, $flags, $size)
    {
        return self::packHeader(['name' => $name, 'flags' => $flags, 'size' => $size]);
    }

    /**
     * Unpacks a binary string.
     *
     * @param string $data
     * @return array $data
     * @return string $data['name']
     * @return int $data['flags']
     * @return int $data['size']
     */
    public static function unpackHeader($data)
    {
        if (strlen($data) !== self::ELEMENT_HEADER_SIZE) {
            throw new Exception('Header data must be 12 characters.');
        }

        $unpacked = unpack(self::ELEMENT_HEADER_UNPACK_FORMAT, $data);

        return $unpacked;
    }

    /**
     * Packs data into a binary string.
     *
     * @param array $data
     * @param string $data['name']
     * @param int $data['flags']
     * @param int $data['size']
     * @return string
     */
    public static function packHeader($data)
    {
        $packed = pack(self::ELEMENT_HEADER_FORMAT, $data['name'], $data['flags'], $data['size']);

        return $packed;
    }
}
