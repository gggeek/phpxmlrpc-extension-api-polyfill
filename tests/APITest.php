<?php
/**
 * @todo test xmlrpc_encode_request(NULL, array())
 *
 * @author Gaetano Giunta
 * @copyright (c) 2020 G. Giunta
 * @license code licensed under the BSD License: see license.txt
 */

include_once __DIR__ . '/PolyfillTestCase.php';

use PhpXmlRpc\PhpXmlRpc;
use PhpXmlRpc\Polyfill\XmlRpc\XmlRpc as p;

class ApiTest extends PolyfillTestCase
{
    /**
     * @dataProvider getGetTypeValues
     */
    function testGetType($value)
    {
        $ok = xmlrpc_get_type($value);
        $ok1 = p::xmlrpc_get_type($value);
        $this->assertEquals($ok, $ok1, "xmlrpc_get_type failed for ".var_export($value, true));
    }

    /**
     * @dataProvider getSetTypeValues
     */
    function testSetType($value)
    {
        $value1 = $value;
        $value2 = $value;
        $ok1 = xmlrpc_set_type($value1, 'base64');
        $ok2 = p::xmlrpc_set_type($value2, 'base64');
        $this->assertEquals($value1, $value2, "xmlrpc_set_type convert failed for base64 of ".var_export($value, true));
        $this->assertEquals($ok1, $ok2, "xmlrpc_set_type return failed for base64 of ".var_export($value, true));

        $value1 = $value;
        $value2 = $value;
        $ok1 = xmlrpc_set_type($value1, 'datetime');
        $ok2 = p::xmlrpc_set_type($value2, 'datetime');
        $this->assertEquals($value1, $value2, "xmlrpc_set_type convert failed for datetime of ".var_export($value, true));
        $this->assertEquals($ok1, $ok2, "xmlrpc_set_type return failed for datetime of ".var_export($value, true));

        /* @todo test this
        $value1 = $value;
        $value2 = $value;
        $ok1 = xmlrpc_set_type($value1, 'any');
        $ok2 = p::xmlrpc_set_type($value2, 'any');
        $this->assertEquals($ok1, $ok2, "xmlrpc_set_type failed for ".var_export($value, true));
        $this->assertEquals($value1, $value2, "xmlrpc_set_type failed for ".var_export($value, true));
        */
    }

    /**
     * @dataProvider getIsFaultValues
     */
    function testIsFault($value)
    {
        $ok = xmlrpc_is_fault($value);
        $ok1 = p::xmlrpc_is_fault($value);
        $this->assertEquals($ok, $ok1, "xmlrpc_is_fault failed for ".var_export($value, true));
    }

    /**
     * @dataProvider getEncodeValues
     */
    function testEncode($value)
    {
        $defaultPrecision = PhpXmlRpc::$xmlpc_double_precision;
        PhpXmlRpc::$xmlpc_double_precision = 6;
        $defaultEncoding = PhpXmlRpc::$xmlrpc_internalencoding;
        PhpXmlRpc::$xmlrpc_internalencoding = 'ISO-8859-1';

        $ko = $this->normalizeXmlFormatting(xmlrpc_encode($value));
        $ko1 = $this->normalizeXmlFormatting(p::xmlrpc_encode($value));
        $this->assertEquals($ko, $ko1, "xmlrpc_encode failed for ".var_export($value, true));

        $ok = xmlrpc_decode($ko);
        $ok1 = p::xmlrpc_decode($ko1);
        $this->assertEquals($ok, $ok1, "xmlrpc_decode failed for ".var_export($value, true));

        $ok2 = xmlrpc_decode($ko1);
        $ok3 = p::xmlrpc_decode($ko);
        $this->assertEquals($ok3, $ok2, "xmlrpc_decode failed for ".var_export($value, true));

        PhpXmlRpc::$xmlrpc_internalencoding = $defaultEncoding;
        PhpXmlRpc::$xmlpc_double_precision = $defaultPrecision;
    }

    /**
     * @dataProvider getEncodeValues
     */
    function testEncodeRequest($value)
    {
        $defaultPrecision = PhpXmlRpc::$xmlpc_double_precision;
        PhpXmlRpc::$xmlpc_double_precision = 6;
        $defaultEncoding = PhpXmlRpc::$xmlrpc_internalencoding;
        PhpXmlRpc::$xmlrpc_internalencoding = 'ISO-8859-1';

        $ok = $this->normalizeXmlFormatting(xmlrpc_encode_request('hello', $value));
        $ok1 = $this->normalizeXmlFormatting(p::xmlrpc_encode_request('hello', $value));
        $this->assertEquals($ok, $ok1, "xmlrpc_encode_request failed for ".var_export($value, true));

        $methodName = '';
        $ko = xmlrpc_decode_request($ok, $methodName);
        $ko1 = p::xmlrpc_decode_request($ok1, $methodName);
        $this->assertEquals($ko, $ko1, "xmlrpc_decode_request failed for ".var_export($ok, true));

        //$ko = xmlrpc_decode_request('zzz'.$ok, $methodname);
        //echo  'DECODED BAD  : '; var_dump($ko);

        // methodresponse generated
        $ok = $this->normalizeXmlFormatting(xmlrpc_encode_request(null, $value));
        $ok1 = $this->normalizeXmlFormatting(p::xmlrpc_encode_request(null, $value));
        $this->assertEquals($ok, $ok1, "xmlrpc_encode_request failed for ".var_export($value, true));

        $methodName = '***';
        $methodName1 = '***';
        $ko = $this->normalizeXmlFormatting(xmlrpc_decode_request($ok, $methodName));
        $ko1 = $this->normalizeXmlFormatting(xmlrpc_decode_request($ok1, $methodName1));
        $this->assertEquals($ko, $ko1, "xmlrpc_decode_request failed for ".var_export($value, true));

        PhpXmlRpc::$xmlrpc_internalencoding = $defaultEncoding;
        PhpXmlRpc::$xmlpc_double_precision = $defaultPrecision;

        //@fclose($v3);
    }

    /**
     * "Normalize" xml so that we can make tests pass, which are based on string comparison.
     * NB: normalizes 'double' values as well, as we consider the difference for their serialization ok
     * @param string $text
     * @return string
     */
    protected function normalizeXmlFormatting($text)
    {
        return preg_replace(
            array(
                '/^<\\?xml +version="1\\.0" +encoding="([^"]*)" \\?/',
                '#<string></string>#',
                '#<double>(-)?([0-9]+)\\.0{6}</double>#',
                '#<double>(-)?([0-9]+)\\.([1-9]+)0{1,5}</double>#',
                '/^ +/m',
                '/\\n/s',
                '#<params></params>#',
                '#<data></data>#',
            ),
            array(
                '<?xml version="1.0" encoding="$1"?',
                '<string/>',
                '<double>$1$2</double>',
                '<double>$1$2.$3</double>',
                '',
                '',
                '<params/>',
                '<data/>',
            ),
            $text);
    }

    public function getGetTypeValues()
    {
        return $this->getScalarValues();
    }

    public function getSetTypeValues()
    {
        return $this->getScalarValues();
    }

    /// @todo add more cases with wrong type for faultCode & faultString: null, float, object, resource
    public function getIsFaultValues()
    {
        $vals = array(
            array(array('faultCode' => 666, 'faultString' => 'hello world')),

            array(array()),
            array(array(true)),
            array(array(false)),
            array(array(0)),
            array(array(1)),
            array(array(2.1)),
            array(array('NotAFault')),
            array(array(fopen(__FILE__, 'r'))),
            array(array('faultCode' => 666)),
            array(array('faultString' => 'hello world')),
            array(array('faultCode' => 'hello world')),
            array(array('faultString' => 666)),
            array(array('faultCode' => 'hello world', 'faultString' => 666)),
            array(array('faultCode' => 666, 'faultString' => 'hello world', 'faultWhat?' => 'dunno')),
            array(array('faultCode' => array(666), 'faultString' => 'hello world')),
            array(array('faultCode' => 666, 'faultString' => array('hello world'))),
        );
        return $vals;
    }

    public function getEncodeValues()
    {
        $vals = $this->getScalarValues();

        $v1 = '20060707T12:00:00';
        p::xmlrpc_set_type($v1, 'datetime');
        $v2 = 'hello world';
        p::xmlrpc_set_type($v2, 'base64');
        $vals[] = array($v1);
        $vals[] = array($v2);

        $vals[] = array(array('hello' => true, 'hello', 'world')); // mixed - encode KO (2 members with null name) but decode will be fine!!!
        $vals[] = array(array('methodname' => 'hello', 'params' => array())); // struct

        $vals = array_merge($vals, $this->getIsFaultValues());

        return $vals;
    }

    /**
     * A set of values used in most tests
     * @todo add more values: Object, DateTime, function, Latin1 text, more nested arrays...
     */
    protected function getScalarValues()
    {
        $vals = array(
            array(true),
            array(false),
            array(0),
            array(1),
            array(-1),
            array(2.0),
            array(2.1),
            array(-2.1),
            array(2.123456789),
            array(-2.123456789),
            array(null), // base 64 type???, encoded as empty string
            array(''),
            array('1'),
            array('-1'),
            array(' 1 '),
            array('2.1'),
            array(' 2.1 '),
            array('20060101T12:00:00'),
            array('20060101T99:99:99'),
            array('a.b.c.å.ä.ö.€.'), /// @todo replace with latin-1 stuff
            array('Τὴ γλῶσσα μοῦ ἔδωσαν ἑλληνικὴ'), /// @todo replace with latin-1 stuff
            array(base64_encode('hello')), // string
            array(fopen(__FILE__, 'r')),
            array(array()),
            array(array('a')),
            array(array(true, false, 0, 1, -1, 2.0, -2.1, '', ' 1 ', ' 2.1 ', 'hello', fopen(__FILE__, 'r'))),
            array(array(array(1))),
            array(array('hello' => 'world')), // struct
            array(array('2' => true, false)), // array - when decoded array keys will be reset
            array(array('hello' => true, 'world')), // mixed
            //new apitests() // CRASH!!!,
        );

        return $vals;
    }
}
