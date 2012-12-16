<?php
    require_once __DIR__. 'simpletest/autorun.php';
	require_once __DIR__. '../screenscraper.php';

    class ScreenscraperTest extends UnitTestCase
    {
        private $scraper;
        private $cache;

        // This testing relies on an internet connection
        // The websites:  example.com  and  jsontest.com  are used for testing

        function __construct()
        {
            $this->scraper = New ScreenScraper;
            $this->cache = New ScreenScraperCache(realpath('../cache'), 60);

            $this->scraper->request_cache_directory = '../cache';
            $this->scraper->request_cookie = '../cache/~$cookie.dat';
            $this->scraper->request_log = '../cache/~$last_request.log';
        }

        public function testCache()
        {
            $testString = 'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor.';

            $this->cache->delAll();

            $this->cache->set('test-key-01', $testString);
            $this->assertTrue($this->cache->get('test-key-01') === $testString);
            $this->assertTrue($this->cache->del('test-key-01'));
        }

        public function testPageRetrieval()
        {
            $data = $this->scraper->retrievePage('http://example.com/', array('cache' => false));
            $this->assertTrue($data);
        }

        public function testPageRedirect()
        {
            $data = $this->scraper->retrievePage('http://example.com/', array('cache' => false));
            $this->assertEqual($this->scraper->request_actual_url, 'http://www.iana.org/domains/example/');
        }

        public function testPageCaching()
        {
            $data = $this->scraper->retrievePage('http://example.com/', array('cache' => false));

            $this->cache->set('test-key-02', $data);
            $this->assertNotEqual($this->cache->get('test-key-02'), false);
            $this->assertTrue($this->cache->del('test-key-02'));
        }

        public function testJSONDecoding()
        {
            $data = $this->scraper->retrievePage('http://httpbin.org/ip', array('cache' => false))->jsonDecode();

            $this->assertTrue(isset($data->origin));
        }

        public function testUserAgent()
        {
            $data = $this->scraper->retrievePage('http://httpbin.org/user-agent', array('cache' => false))->jsonDecode();

            $this->assertEqual($data->{'user-agent'}, $this->scraper->request_useragent);
        }

        public function testReferer()
        {
            $this->scraper->request_referer = 'http://httpbin.org';
            $data = $this->scraper->retrievePage('http://httpbin.org/headers', array('cache' => false))->jsonDecode();

            $this->assertEqual($data->headers->Referer, $this->scraper->request_referer);
        }

        public function testGETdata()
        {
            $data = $this->scraper->retrievePage('http://httpbin.org/headers', array('cache' => false));

            $this->assertEqual($this->scraper->request_http_code, 200);
        }

        public function testPOSTdata()
        {
            $this->scraper->request_method = 'post';
            $this->scraper->request_params = array(
                'item1' => 'blah'
                );

            $data = $this->scraper->retrievePage('http://httpbin.org/post', array('cache' => false))->jsonDecode();

            // If a different request method (like GET) is send by mistake it will return a 405 HTTP error code
            // See: http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.6
            $this->assertNotEqual($this->scraper->request_http_code, 405);

            $this->assertEqual($data->form->item1, 'blah');
        }



    }