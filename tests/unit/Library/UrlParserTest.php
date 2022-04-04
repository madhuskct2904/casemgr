<?php

namespace App\Tests\Unit\Service;

use App\Service\UrlParser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UrlParserTest extends KernelTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
    }

    public function testNormalizeWithProductionUrl()
    {
        $frontendDomain = 'casemgr.org';
        $urlParser = $this->_getUrlParser($frontendDomain);
        $url = 'http://test-site.casemgr.org?myvar=1&other_var=2';
        $expected = 'test-site.casemgr.org';
        $output = $urlParser->normalize($url);

        $this->assertEquals($expected, $output);
    }

    public function testNormalizeWithProductionUrlWithoutProtocol()
    {
        $frontendDomain = 'casemgr.org';
        $urlParser = $this->_getUrlParser($frontendDomain);
        $url = 'test-site.casemgr.org';
        $expected = 'test-site.casemgr.org';
        $output = $urlParser->normalize($url);

        $this->assertEquals($expected, $output);
    }

    public function testNormalizeWithDevUrl()
    {
        $frontendDomain = 'casemgr.io';
        $urlParser = $this->_getUrlParser($frontendDomain);
        $url = 'https://devurl.casemgr.io';
        $expected = 'devurl.casemgr.io';
        $output = $urlParser->normalize($url);

        $this->assertEquals($expected, $output);
    }

    public function testNormalizeWithLocalUrl()
    {
        $frontendDomain = 'casemgr.local';
        $urlParser = $this->_getUrlParser($frontendDomain);
        $url = 'http://dev-url.casemgr.local?query_var=my%20val&other_var=4';
        $expected = 'dev-url.casemgr.local';
        $output = $urlParser->normalize($url);

        $this->assertEquals($expected, $output);
    }

    public function testNormalizeWithLocalhostUrlWithQueryString()
    {
        $frontendDomain = 'localhost';
        $urlParser = $this->_getUrlParser($frontendDomain);
        $url = 'http://devurl.localhost?test=1';
        $expected = 'devurl.localhost';
        $output = $urlParser->normalize($url);

        $this->assertEquals($expected, $output);
    }

    public function testNormalizeWithLocalhostUrl()
    {
        $frontendDomain = 'localhost';
        $urlParser = $this->_getUrlParser($frontendDomain);
        $url = 'http://devurl.localhost';
        $expected = 'devurl.localhost';
        $output = $urlParser->normalize($url);

        $this->assertEquals($expected, $output);
    }

    public function testNormalizeWithWwwSubdomainReturnsNull()
    {
        $frontendDomain = 'casemgr.org';
        $urlParser = $this->_getUrlParser($frontendDomain);
        $url = 'http://www.casemgr.org/';
        $expected = null;
        $output = $urlParser->normalize($url);

        $this->assertEquals($expected, $output);
    }

    public function testNormalizeWithNonUrlTextStringAppendsFrontendUrl()
    {
        $frontendDomain = 'casemgr.org';
        $urlParser = $this->_getUrlParser($frontendDomain);
        $url = 'not-a-url';
        $expected = 'not-a-url.casemgr.org';
        $output = $urlParser->normalize($url);

        $this->assertEquals($expected, $output);
    }

    public function testNormalizeWithNoSubdomainReturnsNull()
    {
        $frontendDomain = 'casemgr.org';
        $urlParser = $this->_getUrlParser($frontendDomain);
        $url = $frontendDomain;
        $expected = null;
        $output = $urlParser->normalize($url);

        $this->assertEquals($expected, $output);
    }

    private function _getUrlParser($frontendDomain) {
        putenv("FRONTEND_DOMAIN=$frontendDomain");
        $container = self::$container;
        $urlParser = $container->get(UrlParser::class);
        return $urlParser;
    }
}
