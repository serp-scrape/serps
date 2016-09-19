<?php
/**
 * @license see LICENSE
 */
namespace Serps\Test\TDD\Core;

use Serps\Core\Cookie\Cookie;
use Serps\Core\Url;
use Serps\Core\UrlArchive;

/**
 * @covers Serps\Core\Url
 * @covers Serps\Core\UrlArchive
 * @covers Serps\Core\Url\AlterableUrlTrait
 * @covers Serps\Core\Url\UrlArchiveTrait
 */
class UrlTest extends \PHPUnit_Framework_TestCase
{



    public function testGetUrl()
    {
        $builder = new Url('example.com');
        $this->assertEquals('https://example.com', $builder->buildUrl());

        $builder->setHash('foo');
        $this->assertEquals('https://example.com#foo', $builder->buildUrl());

        $builder->setParam('foo', 'bar');
        $builder->setParam('foobar', 'foo bar');
        $this->assertEquals('https://example.com?foo=bar&foobar=foo+bar#foo', $builder->buildUrl());

        $builder->setPath('some/path');
        $this->assertEquals('https://example.com/some/path?foo=bar&foobar=foo+bar#foo', $builder->buildUrl());

        $builder->setScheme('http');
        $this->assertEquals('http://example.com/some/path?foo=bar&foobar=foo+bar#foo', $builder->buildUrl());
    }

    public function testSetParam()
    {
        $builder = new Url('example');
        $this->assertEquals('', $builder->getQueryString());

        $builder->setParam('foo', 'bar');
        $this->assertEquals('foo=bar', $builder->getQueryString());

        $builder->setParam('foo', 'baz');
        $this->assertEquals('foo=baz', $builder->getQueryString());

        $builder->setParam('foobar', 'foo bar');
        $this->assertEquals('foo=baz&foobar=foo+bar', $builder->getQueryString());

        $builder->setParam('foobar', 'foo bar', true);
        $this->assertEquals('foo=baz&foobar=foo bar', $builder->getQueryString());
    }

    public function testGetParamValue()
    {
        $builder = new Url('example');

        $this->assertNull($builder->getParamValue('q'));
        $this->assertEquals('foo', $builder->getParamValue('q', 'foo'));

        $builder->setParam('q', 'bar');
        $this->assertEquals('bar', $builder->getParamValue('q', 'foo'));
    }

    public function testRemoveParam()
    {
        $builder = new Url('example');
        $this->assertEquals('', $builder->getQueryString());

        $builder->setParam('foo', 'bar');
        $builder->setParam('foobar', 'foo bar');
        $this->assertEquals('foo=bar&foobar=foo+bar', $builder->getQueryString());

        $builder->removeParam('foo');
        $this->assertEquals('foobar=foo+bar', $builder->getQueryString());
    }

    public function testSetHost()
    {
        $builder = new Url('example');
        $this->assertEquals('example', $builder->getHost());
        $builder->setHost('google.com');
        $this->assertEquals('google.com', $builder->getHost());
    }

    public function testGetParams()
    {
        $builder = new Url('example');
        $this->assertEquals([], $builder->getParams());

        $builder->setParam('foo', 'bar');
        $this->assertCount(1, $builder->getParams());
        $this->assertArrayHasKey('foo', $builder->getParams());
        $this->assertEquals('bar', $builder->getParams()['foo']->getValue());
    }

    public function testFromString()
    {
        $url = Url::fromString('https://foo/bar?qux=baz');

        $this->assertInstanceOf(Url::class, $url);
        $this->assertEquals('https://foo/bar?qux=baz', $url->buildUrl());
    }


    public function testResolve()
    {
        $url = Url::fromString('https://foo/bar?qux=baz');
        $urlArchive = UrlArchive::fromString('https://foo/bar?qux=baz');

        $newUrl = $url->resolve('//bar');
        $this->assertEquals('https://bar', $newUrl->buildUrl());
        $this->assertInstanceOf(Url::class, $newUrl);

        $newUrl = $urlArchive->resolve('//bar');
        $this->assertEquals('https://bar', $newUrl->buildUrl());
        $this->assertInstanceOf(UrlArchive::class, $newUrl);

        $newUrl = $url->resolve('/baz');
        $this->assertEquals('https://foo/baz', $newUrl->buildUrl());

        $newUrl = $url->resolve('http://baz/foo');
        $this->assertEquals('http://baz/foo', $newUrl->buildUrl());
    }

    public function testResolveAs()
    {
        $url = Url::fromString('https://foo/bar?qux=baz');

        // Resolve as other class
        $newUrl = $url->resolve('//bar', UrlArchive::class);
        $this->assertEquals('https://bar', $newUrl->buildUrl());
        $this->assertInstanceOf(Url\UrlArchiveInterface::class, $newUrl);

        $newUrl = $url->resolve('//bar', Url::class);
        $this->assertEquals('https://bar', $newUrl->buildUrl());
        $this->assertInstanceOf(Url\UrlArchiveInterface::class, $newUrl);

        // Resolve as string
        $newUrl = $url->resolve('//bar', 'string');
        $this->assertInternalType('string', $newUrl);
        $this->assertEquals('https://bar', $newUrl);
    }

    public function testResolveAsBadType()
    {
        $url = Url::fromString('https://foo/bar?qux=baz');

        $this->setExpectedException(\InvalidArgumentException::class);
        $url->resolve('//bar', []);
    }

    public function testResolveAsBadClass()
    {
        $url = Url::fromString('https://foo/bar?qux=baz');

        $this->setExpectedException(\InvalidArgumentException::class);
        $url->resolve('//bar', Cookie::class);
    }

    /**
     * @dataProvider RFC3986ResolveDataProvider
     */
    public function testRFC3986Resolve($relUri, $mustResolved)
    {
        $url = Url::fromString('http://a/b/c/d;p?q');
        $this->assertEquals($mustResolved, $url->resolve($relUri, 'string'));
    }

    public function RFC3986ResolveDataProvider()
    {
        return [
            ['https:'        ,  'https:'],

            // Examples from https://tools.ietf.org/html/rfc3986#section-5.4.1
            ['g'             ,  'http://a/b/c/g'],
            ['./g'           ,  'http://a/b/c/g'],
            ['g/'            ,  'http://a/b/c/g/'],
            ['/g'            ,  'http://a/g'],
            ['//g'           ,  'http://g'],
            ['?y'            ,  'http://a/b/c/d;p?y'],
            ['g?y'           ,  'http://a/b/c/g?y'],
            ['#s'            ,  'http://a/b/c/d;p?q#s'],
            ['g#s'           ,  'http://a/b/c/g#s'],
            ['g?y#s'         ,  'http://a/b/c/g?y#s'],
            [';x'            ,  'http://a/b/c/;x'],
            ['g;x'           ,  'http://a/b/c/g;x'],
            ['g;x?y#s'       ,  'http://a/b/c/g;x?y#s'],
            [''              ,  'http://a/b/c/d;p?q'],
            ['.'             ,  'http://a/b/c/'],
            ['./'            ,  'http://a/b/c/'],
            ['..'            ,  'http://a/b/'],
            ['../'           ,  'http://a/b/'],
            ['../g'          ,  'http://a/b/g'],
            ['../..'         ,  'http://a/'],
            ['../../'        ,  'http://a/'],
            ['../../g'       ,  'http://a/g'],

            // Examples from https://tools.ietf.org/html/rfc3986#section-5.4.2
            ['../../../g'    ,  'http://a/g'],
            ['../../../../g' ,  'http://a/g'],
            ['/./g'          ,  'http://a/g'],
            ['/../g'         ,  'http://a/g'],
            ['g.'            ,  'http://a/b/c/g.'],
            ['.g'            ,  'http://a/b/c/.g'],
            ['g..'           ,  'http://a/b/c/g..'],
            ['..g'           ,  'http://a/b/c/..g'],
            ['./../g'        ,  'http://a/b/g'],
            ['./g/.'         ,  'http://a/b/c/g/'],
            ['g/./h'         ,  'http://a/b/c/g/h'],
            ['g/../h'        ,  'http://a/b/c/h'],
            ['g;x=1/./y'     ,  'http://a/b/c/g;x=1/y'],
            ['g;x=1/../y'    ,  'http://a/b/c/y'],
            ['g?y/./x'       ,  'http://a/b/c/g?y/./x'],
            ['g?y/../x'      ,  'http://a/b/c/g?y/../x'],
            ['g#s/./x'       ,  'http://a/b/c/g#s/./x'],
            ['g#s/../x'      ,  'http://a/b/c/g#s/../x'],
        ];
    }
}
