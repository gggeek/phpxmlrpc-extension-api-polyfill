<?php

/**
 * Try to implement the same API of the PHP built-in XMLRPC extension, so that
 * projects relying on it can be ported to php installs where the extension is
 * missing.
 *
 * @author Gaetano Giunta
 * @copyright (c) 2020 G. Giunta
 * @license code licensed under the BSD License: see license.txt
 *
 * Known differences from the observed behaviour of the PHP extension:
 * - php arrays indexed with integer keys starting above zero or whose keys are
 *   not in a strict sequence will be converted into xmlrpc structs, not arrays
 * - php arrays indexed with mixed string/integer keys will preserve the integer
 *   keys in the generated structs
 * - base64 and datetime values are converted (by set_type(), decode(), decode_request())
 *   into slightly different php objects - but std object members are preserved
 * - a single NULL value passed to xmlrpc_encode_req(null, $val) will be decoded as '', not NULL
 *   (the extension generates an invalid xmlrpc response in this case)
 * - the native extension truncates double values to 6 decimal digits, we do not
 * -  xmlrpc_server_create returns an object instead of a resource
 *
 * @todo finish implementation of 3 missing functions
 */

namespace PhpXmlRpc\Polyfill\XmlRpc;

use PhpXmlRpc\Encoder;
use PhpXmlRpc\Request;
use PhpXmlRpc\Response;
use PhpXmlRpc\Server;
use PhpXmlRpc\Value;

final class XmlRpc
{
    /**
     * Decode the xml generated by xmlrpc_encode() into native php values
     * @param string $xml
     * @param string $encoding
     * @return mixed
     *
     * @todo implement usage of $encoding
     * @todo test against upstream: is default encoding really latin-1 ?
     */
    public static function xmlrpc_decode($xml, $encoding = "iso-8859-1")
    {
        $encoder = new Encoder();
        // strip out unnecessary xml in case we're deserializing a single param.
        // in case of a complete response, we do not have to strip anything
        // please note that the test below has LARGE space for improvement (eg it might trip on xml comments...)
        if (strpos($xml, '<methodResponse>') === false)
            $xml = preg_replace(array('!\s*<params>\s*<param>\s*!', '!\s*</param>\s*</params>\s*$!'), array('', ''), $xml);
        $val = $encoder->decodeXml($xml);
        if (!$val) {
            return null; // instead of false
        }
        if (is_a($val, 'xmlrpcresp')) {
            if ($fc = $val->faultCode()) {
                return array('faultCode' => $fc, 'faultString' => $val->faultString());
            } else {
                return $encoder->decode($val->value(), array('extension_api'));
            }
        } else
            return $encoder->decode($val, array('extension_api'));
    }

    /**
     * Decode an xmlrpc request (or response) into php values
     * @param string $xml
     * @param string $method (will not be set when decoding responses)
     * @param string $encoding not yet used
     * @return mixed
     *
     * @todo implement usage of $encoding
     * @todo test against upstream: is default encoding really null ?
     */
    public static function xmlrpc_decode_request($xml, &$method, $encoding = null)
    {
        $encoder = new Encoder();
        $val = $encoder->decodeXml($xml);
        if (!$val) {
            return null; // instead of false
        }
        if (is_a($val, 'xmlrpcresp')) {
            if ($fc = $val->faultCode()) {
                $out = array('faultCode' => $fc, 'faultString' => $val->faultString());
            } else {
                $out = $encoder->decode($val->value(), array('extension_api'));
            }
        } else if (is_a($val, 'xmlrpcmsg')) {
            $method = $val->method();
            $out = array();
            $pn = $val->getNumParams();
            for ($i = 0; $i < $pn; $i++)
                $out[] = $encoder->decode($val->getParam($i), array('extension_api'));
        } else
            return null; /// @todo test lib behaviour in this case

        return $out;
    }

    /**
     * Given a PHP val, convert it to xmlrpc code (wrapped up in params/param elements).
     * @param mixed $val
     * @return string
     */
    public static function xmlrpc_encode($val)
    {
        $encoder = new Encoder();
        $val = $encoder->encode($val, array('extension_api'));
        return "<?xml version=\"1.0\" encoding=\"utf-8\"?" . ">\n<params>\n<param>\n " . $val->serialize('UTF-8') . "</param>\n</params>";
    }

    /**
     * Given a method name and array of php values, create an xmlrpc request out
     * of them. If method name === null, will create an xmlrpc response
     * @param string $method
     * @param array $params
     * @param array $output_options options array
     * @return string
     *
     * @todo implement parsing/usage of options
     */
    public static function xmlrpc_encode_request($method, $params, $output_options = array())
    {
        $encoder = new Encoder();

        $output_options = array_merge($output_options, array('extension_api'));

        if ($method !== null) {
            // mimic EPI behaviour: if ($val === NULL) then send NO parameters
            if (!is_array($params)) {
                if ($params === NULL) {
                    $params = array();
                } else {
                    $params = array($params);
                }
            } else {
                // if given a 'hash' array, encode it as a single param
                $i = 0;
                $ok = true;
                foreach ($params as $key => $value)
                    if ($key !== $i) {
                        $ok = false;
                        break;
                    } else
                        $i++;
                if (!$ok) {
                    $params = array($params);
                }
            }
            $values = array();
            foreach ($params as $key => $value) {
                $values[] = $encoder->encode($value, $output_options);
            }

            // create request
            $req = new Request($method, $values);
            $resp = $req->serialize();
        } else {
            // create response
            if (is_array($params) && xmlrpc_is_fault($params))
                $req = new Response(0, (integer)$params['faultCode'], (string)$params['faultString']);
            else
                $req = new Response($encoder->encode($params, $output_options));
            $resp = "<?xml version=\"1.0\"?" . ">\n" . $req->serialize();
        }
        return $resp;
    }

    /**
     * Given a php value, return its corresponding xmlrpc type
     * @param mixed $value
     * @return string
     */
    public static function xmlrpc_get_type($value)
    {
        switch (strtolower(gettype($value))) {
            case 'string':
                return Value::$xmlrpcString;
            case 'integer':
            case 'resource':
                return Value::$xmlrpcInt;
            case 'double':
                return Value::$xmlrpcDouble;
            case 'boolean':
                return Value::$xmlrpcBoolean;
            case 'array':
                $i = 0;
                $ok = true;
                foreach ($value as $key => $valueue)
                    if ($key !== $i) {
                        $ok = false;
                        break;
                    } else
                        $i++;

                return $ok ? Value::$xmlrpcArray : Value::$xmlrpcStruct;
            case 'object':
                if (is_a($value, 'xmlrpcval')) {
/// @todo fixme
                    list($type, $value) = each($value->me);
                    return str_replace(array('i4', 'dateTime.iso8601'), array('int', 'datetime'), $type);
                }
                return Value::$xmlrpcStruct;
            case 'null':
                return Value::$xmlrpcBase64; // go figure why...
        }
    }

    /**
     * Checks if a given php array corresponds to an xmlrpc fault response
     * @param array $arg
     * @return boolean
     */
    public static function xmlrpc_is_fault($arg)
    {
        return is_array($arg) && array_key_exists('faultCode', $arg) && array_key_exists('faultString', $arg);
    }

    /**
     * @param string $xml
     * @return array
     * @todo implement
     */
    public static function xmlrpc_parse_method_descriptions($xml)
    {
        return array();
    }

    /** Server side ***************************************************************/

    /**
     * @param Server $server
     * @param array $desc
     * @return int
     * @todo implement
     */
    public static function xmlrpc_server_add_introspection_data($server, $desc)
    {
        return 0;
    }

    /**
     * Parses XML request and calls corresponding method
     * @param Server $server
     * @param string $xml
     * @param mixed $user_data
     * @param array $output_options
     * @return string
     */
    public static function xmlrpc_server_call_method($server, $xml, $user_data, $output_options = array())
    {
        $server->user_data = $user_data;
        return $server->service($xml, true);
    }

    /**
     * Create a new xmlrpc server instance
     * @return Server
     */
    public static function xmlrpc_server_create()
    {
        $s = new Server();
        $s->functions_parameters_type = 'epivals';
        $s->compress_response = false; // since we will not be outputting any http headers to go with it
        return $s;
    }

    /**
     * This function actually does nothing, but it is kept for compatibility.
     * To destroy a server object, just unset() it, or send it out of scope...
     * @param Server $server
     * @return integer
     */
    public static function xmlrpc_server_destroy($server)
    {
        if ($server instanceof Server)
            return 1;
        else
            return 0;
    }

    /**
     * @param Server $server
     * @param string $function
     * @return bool
     *
     * @todo implement
     */
    public static function xmlrpc_server_register_introspection_callback($server, $function)
    {
        return false;
    }

    /**
     * Add a php function as xmlrpc method handler to an existing server.
     * PHP function sig: f(string $methodname, array $params, mixed $extra_data)
     * @param Server $server
     * @param string $method_name
     * @param string $function
     * @return boolean true on success or false
     */
    public static function xmlrpc_server_register_method($server, $method_name, $function)
    {
        if ($server instanceof Server) {
            $server->add_to_map($method_name, $function);
            return true;
        } else
            return false;
    }

    /**
     * Set string $val to a known xmlrpc type (base64 or datetime only), for serializing it later
     * (NB: this will turn the string into an object!).
     * @param string $val
     * @param string $type
     * @return boolean false if conversion did not take place
     */
    public static function xmlrpc_set_type(&$val, $type)
    {
        if (is_string($val)) {
            if ($type == 'base64') {
                $val = new Value($val, 'base64');
                // add two object members to make it more compatible to user code
                $val->scalar = $val->me['base64'];
                $val->xmlrpc_type = 'base64';
            } elseif ($type == 'datetime') {
                if (preg_match('/([0-9]{8})T([0-9]{2}):([0-9]{2}):([0-9]{2})/', $val)) {
                    $val = new Value($val, 'dateTime.iso8601');
                    // add 3 object members to make it more compatible to user code
                    $val->scalar = $val->me['dateTime.iso8601'];
                    $val->xmlrpc_type = 'datetime';
                    $val->timestamp = \PhpXmlRpc\Helper\Date::iso8601Decode($val->scalar);
                } else {
                    return false;
                }
            } else {
                // @todo EPI will NOT raise a warning for good type names, eg. 'boolean', etc...
                trigger_error("invalid type '$type' passed to xmlrpc_set_type()");
                return false;
            }
            return true;
        } else {
            return false;
        }
    }
}
