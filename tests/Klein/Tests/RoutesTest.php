<?php
/**
 * Klein (klein.php) - A lightning fast router for PHP
 *
 * @author      Chris O'Hara <cohara87@gmail.com>
 * @author      Trevor Suarez (Rican7) (contributor and v2 refactorer)
 * @copyright   (c) Chris O'Hara
 * @link        https://github.com/chriso/klein.php
 * @license     MIT
 */

namespace Klein\Tests;


use \Klein\Klein;

use \Klein\Tests\Mocks\HeadersEcho;
use \Klein\Tests\Mocks\HeadersSave;
use \Klein\Tests\Mocks\MockRequestFactory;

/**
 * RoutesTest 
 * 
 * @uses AbstractKleinTest
 * @package Klein\Tests
 */
class RoutesTest extends AbstractKleinTest
{

    protected function setUp()
    {
        parent::setUp();

        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
    }

    public function testBasic()
    {
        $this->expectOutputString('x');

        $this->klein_app->respond(
            '/',
            function () {
                echo 'x';
            }
        );
        $this->klein_app->respond(
            '/something',
            function () {
                echo 'y';
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/')
        );
    }

    public function testCallable()
    {
        $this->expectOutputString('okok');

        $this->klein_app->respond('/', array(__NAMESPACE__ . '\Mocks\TestClass', 'GET'));
        $this->klein_app->respond('/', __NAMESPACE__ . '\Mocks\TestClass::GET');

        $this->klein_app->dispatch(
            MockRequestFactory::create('/')
        );
    }

    public function testAppReference()
    {
        $this->expectOutputString('ab');

        $this->klein_app->respond(
            '/',
            function ($r, $r, $a) {
                $a->state = 'a';
            }
        );
        $this->klein_app->respond(
            '/',
            function ($r, $r, $a) {
                $a->state .= 'b';
            }
        );
        $this->klein_app->respond(
            '/',
            function ($r, $r, $a) {
                print $a->state;
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/')
        );
    }

    /**
     * TODO!!!
     *
     * Dispatch output buffering will work differently in the future
     * ... implement this!
     */
    public function testDispatchOutput()
    {
        // TODO
        $this->markTestIncomplete(
            'TODO!'
        );

        $expectedOutput = array(
            'echoed' => 'yup',
            'returned' => 'nope',
        );

        $this->klein_app->respond(
            function () use ($expectedOutput) {
                echo $expectedOutput['echoed'];
            }
        );
        $this->klein_app->respond(
            function () use ($expectedOutput) {
                return $expectedOutput['returned'];
            }
        );

        // $output = dispatch(null, null, null, true);
        $output = $this->klein_app->dispatch();

        // Should only capture ECHO'd output (returned values are lost)
        $this->assertSame($expectedOutput['echoed'], $output);
    }

    public function testRespondReturn()
    {
        $return_one = $this->klein_app->respond(
            function () {
                return 1337;
            }
        );
        $return_two = $this->klein_app->respond(
            function () {
                return 'dog';
            }
        );

        $this->klein_app->dispatch();

        $this->assertTrue(is_callable($return_one));
        $this->assertTrue(is_callable($return_two));
    }

    public function testCatchallImplicit()
    {
        $this->expectOutputString('b');

        $this->klein_app->respond(
            '/one',
            function () {
                echo 'a';
            }
        );
        $this->klein_app->respond(
            function () {
                echo 'b';
            }
        );
        $this->klein_app->respond(
            '/two',
            function () {

            }
        );
        $this->klein_app->respond(
            '/three',
            function () {
                echo 'c';
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/two')
        );
    }

    public function testCatchallAsterisk()
    {
        $this->expectOutputString('b');

        $this->klein_app->respond(
            '/one',
            function () {
                echo 'a';
            }
        );
        $this->klein_app->respond(
            '*',
            function () {
                echo 'b';
            }
        );
        $this->klein_app->respond(
            '/two',
            function () {

            }
        );
        $this->klein_app->respond(
            '/three',
            function () {
                echo 'c';
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/two')
        );
    }

    public function testCatchallImplicitTriggers404()
    {
        $this->expectOutputString("b404\n");

        $this->klein_app->respond(
            function () {
                echo 'b';
            }
        );
        $this->klein_app->respond(
            404,
            function () {
                echo "404\n";
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/')
        );
    }

    public function testRegex()
    {
        $this->expectOutputString('z');

        $this->klein_app->respond(
            '@/bar',
            function () {
                echo 'z';
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/bar')
        );
    }

    public function testRegexNegate()
    {
        $this->expectOutputString("y");

        $this->klein_app->respond(
            '!@/foo',
            function () {
                echo 'y';
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/bar')
        );
    }

    public function test404()
    {
        $this->expectOutputString("404\n");

        $this->klein_app->respond(
            '/',
            function () {
                echo 'a';
            }
        );
        $this->klein_app->respond(
            404,
            function () {
                echo "404\n";
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/foo')
        );
    }

    public function testParamsBasic()
    {
        $this->expectOutputString('blue');

        $this->klein_app->respond(
            '/[:color]',
            function ($request) {
                echo $request->param('color');
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/blue')
        );
    }

    public function testParamsIntegerSuccess()
    {
        $this->expectOutputString("string(3) \"987\"\n");

        $this->klein_app->respond(
            '/[i:age]',
            function ($request) {
                var_dump($request->param('age'));
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/987')
        );
    }

    public function testParamsIntegerFail()
    {
        $this->expectOutputString('404 Code');

        $this->klein_app->respond(
            '/[i:age]',
            function ($request) {
                var_dump($request->param('age'));
            }
        );
        $this->klein_app->respond(
            '404',
            function () {
                echo '404 Code';
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/blue')
        );
    }

    public function testParamsAlphaNum()
    {
        $this->klein_app->respond(
            '/[a:audible]',
            function ($request) {
                echo $request->param('audible');
            }
        );


        $this->assertOutputSame(
            'blue42',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/blue42')
                );
            }
        );
        $this->assertOutputSame(
            '',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/texas-29')
                );
            }
        );
        $this->assertOutputSame(
            '',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/texas29!')
                );
            }
        );
    }

    public function testParamsHex()
    {
        $this->klein_app->respond(
            '/[h:hexcolor]',
            function ($request) {
                echo $request->param('hexcolor');
            }
        );


        $this->assertOutputSame(
            '00f',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/00f')
                );
            }
        );
        $this->assertOutputSame(
            'abc123',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/abc123')
                );
            }
        );
        $this->assertOutputSame(
            '',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/876zih')
                );
            }
        );
        $this->assertOutputSame(
            '',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/00g')
                );
            }
        );
        $this->assertOutputSame(
            '',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/hi23')
                );
            }
        );
    }

    public function test404TriggersOnce()
    {
        $this->expectOutputString('d404 Code');

        $this->klein_app->respond(
            function () {
                echo "d";
            }
        );
        $this->klein_app->respond(
            '404',
            function () {
                echo '404 Code';
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/notroute')
        );
    }

    public function testMethodCatchAll()
    {
        $this->expectOutputString('yup!123');

        $this->klein_app->respond(
            'POST',
            null,
            function ($request) {
                echo 'yup!';
            }
        );
        $this->klein_app->respond(
            'POST',
            '*',
            function ($request) {
                echo '1';
            }
        );
        $this->klein_app->respond(
            'POST',
            '/',
            function ($request) {
                echo '2';
            }
        );
        $this->klein_app->respond(
            function ($request) {
                echo '3';
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/', 'POST')
        );
    }

    public function testLazyTrailingMatch()
    {
        $this->expectOutputString('this-is-a-title-123');

        $this->klein_app->respond(
            '/posts/[*:title][i:id]',
            function ($request) {
                echo $request->param('title')
                . $request->param('id');
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/posts/this-is-a-title-123')
        );
    }

    public function testFormatMatch()
    {
        $this->expectOutputString('xml');

        $this->klein_app->respond(
            '/output.[xml|json:format]',
            function ($request) {
                echo $request->param('format');
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/output.xml')
        );
    }

    public function testDotSeparator()
    {
        $this->expectOutputString('matchA:slug=ABCD_E--matchB:slug=ABCD_E--');

        $this->klein_app->respond(
            '/[*:cpath]/[:slug].[:format]',
            function ($rq) {
                echo 'matchA:slug='.$rq->param("slug").'--';
            }
        );
        $this->klein_app->respond(
            '/[*:cpath]/[:slug].[:format]?',
            function ($rq) {
                echo 'matchB:slug='.$rq->param("slug").'--';
            }
        );
        $this->klein_app->respond(
            '/[*:cpath]/[a:slug].[:format]?',
            function ($rq) {
                echo 'matchC:slug='.$rq->param("slug").'--';
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create("/category1/categoryX/ABCD_E.php")
        );

        $this->assertOutputSame(
            'matchA:slug=ABCD_E--matchB:slug=ABCD_E--',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/category1/categoryX/ABCD_E.php')
                );
            }
        );
        $this->assertOutputSame(
            'matchB:slug=ABCD_E--',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/category1/categoryX/ABCD_E')
                );
            }
        );
    }

    public function testControllerActionStyleRouteMatch()
    {
        $this->expectOutputString('donkey-kick');

        $this->klein_app->respond(
            '/[:controller]?/[:action]?',
            function ($request) {
                echo $request->param('controller')
                     . '-' . $request->param('action');
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/donkey/kick')
        );
    }

    public function testRespondArgumentOrder()
    {
        $this->expectOutputString('abcdef');

        $this->klein_app->respond(
            function () {
                echo 'a';
            }
        );
        $this->klein_app->respond(
            null,
            function () {
                echo 'b';
            }
        );
        $this->klein_app->respond(
            '/endpoint',
            function () {
                echo 'c';
            }
        );
        $this->klein_app->respond(
            'GET',
            null,
            function () {
                echo 'd';
            }
        );
        $this->klein_app->respond(
            array('GET', 'POST'),
            null,
            function () {
                echo 'e';
            }
        );
        $this->klein_app->respond(
            array('GET', 'POST'),
            '/endpoint',
            function () {
                echo 'f';
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/endpoint')
        );
    }

    public function testTrailingMatch()
    {
        $this->klein_app->respond(
            '/?[*:trailing]/dog/?',
            function ($request) {
                echo 'yup';
            }
        );


        $this->assertOutputSame(
            'yup',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/cat/dog')
                );
            }
        );
        $this->assertOutputSame(
            'yup',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/cat/cheese/dog')
                );
            }
        );
        $this->assertOutputSame(
            'yup',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/cat/ball/cheese/dog/')
                );
            }
        );
        $this->assertOutputSame(
            'yup',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/cat/ball/cheese/dog')
                );
            }
        );
        $this->assertOutputSame(
            'yup',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('cat/ball/cheese/dog/')
                );
            }
        );
        $this->assertOutputSame(
            'yup',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('cat/ball/cheese/dog')
                );
            }
        );
    }

    public function testTrailingPossessiveMatch()
    {
        $this->klein_app->respond(
            '/sub-dir/[**:trailing]',
            function ($request) {
                echo 'yup';
            }
        );


        $this->assertOutputSame(
            'yup',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/sub-dir/dog')
                );
            }
        );

        $this->assertOutputSame(
            'yup',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/sub-dir/cheese/dog')
                );
            }
        );

        $this->assertOutputSame(
            'yup',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/sub-dir/ball/cheese/dog/')
                );
            }
        );

        $this->assertOutputSame(
            'yup',
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create('/sub-dir/ball/cheese/dog')
                );
            }
        );
    }

    public function testNSDispatch()
    {
        $this->klein_app->with(
            '/u',
            function () {
                $this->klein_app->respond(
                    'GET',
                    '/?',
                    function ($request, $response) {
                        echo "slash";
                    }
                );
                $this->klein_app->respond(
                    'GET',
                    '/[:id]',
                    function ($request, $response) {
                        echo "id";
                    }
                );
            }
        );
        $this->klein_app->respond(
            404,
            function ($request, $response) {
                echo "404";
            }
        );


        $this->assertOutputSame(
            "slash",
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create("/u")
                );
            }
        );
        $this->assertOutputSame(
            "slash",
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create("/u/")
                );
            }
        );
        $this->assertOutputSame(
            "id",
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create("/u/35")
                );
            }
        );
        $this->assertOutputSame(
            "404",
            function () {
                $this->klein_app->dispatch(
                    MockRequestFactory::create("/35")
                );
            }
        );
    }

    public function testNSDispatchExternal()
    {
        $ext_namespaces = $this->loadExternalRoutes();

        $this->klein_app->respond(
            404,
            function ($request, $response) {
                echo "404";
            }
        );

        foreach ($ext_namespaces as $namespace) {

            $this->assertOutputSame(
                'yup',
                function () use ($namespace) {
                    $this->klein_app->dispatch(
                        MockRequestFactory::create($namespace . '/')
                    );
                }
            );

            $this->assertOutputSame(
                'yup',
                function () use ($namespace) {
                    $this->klein_app->dispatch(
                        MockRequestFactory::create($namespace . '/testing/')
                    );
                }
            );
        }
    }

    public function testNSDispatchExternalRerequired()
    {
        $ext_namespaces = $this->loadExternalRoutes();

        $this->klein_app->respond(
            404,
            function ($request, $response) {
                echo "404";
            }
        );

        foreach ($ext_namespaces as $namespace) {

            $this->assertOutputSame(
                'yup',
                function () use ($namespace) {
                    $this->klein_app->dispatch(
                        MockRequestFactory::create($namespace . '/')
                    );
                }
            );

            $this->assertOutputSame(
                'yup',
                function () use ($namespace) {
                    $this->klein_app->dispatch(
                        MockRequestFactory::create($namespace . '/testing/')
                    );
                }
            );
        }
    }

    public function test405DefaultRequest()
    {
        // Echo our headers
        $klein_app = new Klein(new HeadersEcho());

        $klein_app->respond(
            array('GET', 'POST'),
            null,
            function () {
                echo 'fail';
            }
        );

        $klein_app->dispatch(
            MockRequestFactory::create('/', 'DELETE')
        );

        $this->expectOutputString(
            'HTTP/1.1 405 Method Not Allowed' . "\n"
            . 'Allow: GET, POST' . "\n"
        );
    }

    public function test405Routes()
    {
        $resultArray = array();

        $this->expectOutputString('_');

        $this->klein_app->respond(
            function () {
                echo '_';
            }
        );
        $this->klein_app->respond(
            'GET',
            null,
            function () {
                echo 'fail';
            }
        );
        $this->klein_app->respond(
            array('GET', 'POST'),
            null,
            function () {
                echo 'fail';
            }
        );
        $this->klein_app->respond(
            405,
            function ($a, $b, $c, $d, $methods) use (&$resultArray) {
                $resultArray = $methods;
            }
        );

        $this->klein_app->dispatch(
            MockRequestFactory::create('/sure', 'DELETE')
        );

        $this->assertCount(2, $resultArray);
        $this->assertContains('GET', $resultArray);
        $this->assertContains('POST', $resultArray);
    }

    public function testOptionsDefaultRequest()
    {
        // Echo our headers
        $klein_app = new Klein(new HeadersEcho());

        $klein_app->respond(
            function ($request, $response) {
                $response->code(200);
            }
        );
        $klein_app->respond(
            array('GET', 'POST'),
            null,
            function () {
                echo 'fail';
            }
        );

        $klein_app->dispatch(
            MockRequestFactory::create('/', 'OPTIONS')
        );

        $this->expectOutputString(
            'HTTP/1.1 200 OK' . "\n"
            . 'Allow: GET, POST' . "\n"
        );
    }

    public function testOptionsRoutes()
    {
        $header_values = array();

        $klein_app = new Klein(new HeadersSave($header_values));

        $access_control_headers = array(
            array(
                'key' => 'Access-Control-Allow-Origin',
                'val' => 'http://example.com',
            ),
            array(
                'key' => 'Access-Control-Allow-Methods',
                'val' => 'POST, GET, DELETE, OPTIONS, HEAD',
            ),
        );

        $klein_app->respond(
            'GET',
            null,
            function () {
                echo 'fail';
            }
        );
        $klein_app->respond(
            array('GET', 'POST'),
            null,
            function () {
                echo 'fail';
            }
        );
        $klein_app->respond(
            'OPTIONS',
            null,
            function ($request, $response) use ($access_control_headers) {
                // Add access control headers
                foreach ($access_control_headers as $header) {
                    $response->header($header[ 'key' ], $header[ 'val' ]);
                }
            }
        );

        $klein_app->dispatch(
            MockRequestFactory::create('/', 'OPTIONS')
        );

        // Assert headers were passed
        $this->assertContains(
            'Allow: GET, POST, OPTIONS' . "\n",
            $header_values
        );
        foreach ($access_control_headers as $header) {
            $this->assertContains(
                sprintf('%s: %s', $header[ 'key' ], $header[ 'val' ]) . "\n",
                $header_values
            );
        }
    }

    public function testHeadDefaultRequest()
    {
        $header_values = array();

        // Echo our headers
        $klein_app = new Klein(new HeadersSave($header_values));

        $expected_headers = array(
            array(
                'key' => 'X-Some-Random-Header',
                'val' => 'This was a GET route',
            ),
        );

        $klein_app->respond(
            'GET',
            null,
            function ($request, $response) use ($expected_headers) {
                $response->code(200);

                // Add access control headers
                foreach ($expected_headers as $header) {
                    $response->header($header[ 'key' ], $header[ 'val' ]);
                }
            }
        );
        $klein_app->respond(
            'GET',
            '/',
            function () {
                echo 'GET!';
            }
        );
        $klein_app->respond(
            'POST',
            '/',
            function () {
                echo 'POST!';
            }
        );

        $klein_app->dispatch(
            MockRequestFactory::create('/', 'HEAD')
        );

        // Make sure we don't get a response body
        $this->expectOutputString('');

        // Assert headers were passed
        foreach ($expected_headers as $header) {
            $this->assertContains(
                sprintf('%s: %s', $header[ 'key' ], $header[ 'val' ]) . "\n",
                $header_values
            );
        }
    }
}
