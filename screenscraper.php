<?php
/**
 * Screenscraper 2
 *
 * @package ScreenScraper 2
 * @author Keith McGahey <jeffory@c0d.in>
 **/
class ScreenScraper
{
    /**
     * URL of the page (if any) requested
     *
     * @var string
     **/
    public $request_url;
    
    /**
     * Data from any requested page
     *
     * @var string
     **/
    public $request_data;

    /**
     * Request cache directory
     *
     * @var string
     **/
    public $request_cache_directory = './cache/';

    /**
     * Request cookie file
     *
     * @var string
     **/
    public $request_cookie = './cache/~$cookie.dat';

    /**
     * Request cache file expiry time
     *
     * @var string
     **/
    public $request_cache_expiry = 86400;

    /**
     * Request cookie file
     *
     * @var string
     **/
    public $request_useragent = 'Mozilla/5.0 (Windows; U; Windows NT 6.0; en-GB; rv:1.9.1.1) Gecko/20090715 Firefox/3.5.1 GTB5 (.NET CLR 4.0.20506)';

    /**
     * Request post variables
     *
     * @var string
     **/
    public $request_params;

    /**
     * Request header
     *
     * @var string
     **/
    public $request_header;

    /**
     * Request method
     *
     * @var string
     **/
    public $request_method;

    /**
     * Request referer
     *
     * @var string
     **/
    public $request_referer;

        /**
     * Returned HTTP code by request
     *
     * @var string
     **/
    public $request_log = './cache/~$last_request.log';

    /**
     * Actual URL of request page, after redirects
     *
     * @var string
     **/
    public $request_actual_url;

    /**
     * Returned HTTP code by request
     *
     * @var string
     **/
    public $request_http_code;
    /**
     * Returned HTTP content-type
     *
     * @var string
     **/
    public $request_content_type;

    /**
     * CURL arguments, for debugging
     *
     * @var string
     **/
    protected $curl_arguments;

    /**
     * Data after any processing
     *
     * @var string
     **/
    public $data;


    /**
     * Downloads a webpage
     *
     * @return object ScreenScraper
     **/
    public function retrievePage($request_url=null, $arguments=null)
    {
        // Set variables from arguments array
        $this->request_url = (!empty($request_url)) ? $request_url : $this->request_url;
        $this->request_params = isset($arguments['vars']) ? $arguments['vars'] : $this->request_params;
        $this->request_header = isset($arguments['header']) ? $arguments['header'] : $this->request_header;
        $this->request_method = isset($arguments['method']) ? $arguments['method'] : $this->request_method;
        $this->request_referer = isset($arguments['referer']) ? $arguments['referer'] : $this->request_referer;

        // Set defaults
        $this->request_method = empty($this->request_method) ? 'GET' : $this->request_method;

        // If no cache exists, use system temp
        if (!file_exists($this->request_cache_directory) || !is_writable($this->request_cache_directory))
        {
            $this->request_cache_directory = sys_get_temp_dir();
        }

        $cache = new ScreenScraperCache(realpath($this->request_cache_directory), $this->request_cache_expiry);

        // Check the Cookie file, create a blank file if it does not exist
        if (!file_exists($this->request_cookie))
        {
            if (is_writable($this->request_cookie))
            {
                file_put_contents($this->request_cookie, '');
            }
            else
            {
                $this->request_cookie = false;
            }
        }

        if (!empty($this->request_url))
        {
            $cache_key = sha1($this->request_url. $this->request_cookie. serialize($this->request_params). $this->request_useragent. $this->request_method. $this->request_referer. serialize($arguments));

            // Assume urls that start with '//' are http protocal requests
            if (strpos($this->request_url, '//', 0) === 0)
            {
                $this->request_url = 'http:'. $this->request_url;
            }

            // Sort out the url and parameters
            if (!empty($this->request_params))
            {
                if (is_array($this->request_params))
                {
                    if (strcasecmp($this->request_method, 'get') == 0)
                    {
                        $url_parts = parse_url($this->request_url);

                        if (isset($url_parts['query']))
                        {
                            $url_parts['query'] .= '&'. http_build_query($this->request_params);
                        }
                        else
                        {
                            $url_parts['query'] = $this->request_params;
                        }
                    }
                    elseif (strcasecmp($this->request_method, 'post') == 0 && is_array($this->request_method))
                    {
                        $this->request_params = http_build_query($this->request_params);
                    }
                }
            }

            // Cache the page...
            if ((!$this->request_data = $cache->get($cache_key)) || (isset($arguments['cache']) && $arguments['cache'] === false))
            {
                $curl = curl_init($this->request_url);

                $curl_opts = array(
                        CURLOPT_URL => $this->request_url,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_MAXREDIRS => 5,
                        CURLOPT_COOKIEFILE => $this->request_cookie,
                        CURLOPT_COOKIEJAR => $this->request_cookie,
                        CURLOPT_USERAGENT => $this->request_useragent,
                        CURLOPT_COOKIESESSION => true
                    );

                // Bad code, only temporary
                if (!strncmp($this->request_url, 'https', strlen('https')))
                    $curl_opts += array(CURLOPT_HTTPAUTH => CURLAUTH_ANY, CURLOPT_SSL_VERIFYPEER => false);

                if (!empty($this->request_referer))
                    $curl_opts += array(CURLOPT_REFERER => $this->request_referer);

                if (in_array(strtoupper($this->request_method), array('GET','POST','PUT','HEAD','DELETE')))
                    $curl_opts += array(CURLOPT_CUSTOMREQUEST => strtoupper($this->request_method));

                if (!empty($this->request_params))
                    $curl_opts += array(CURLOPT_POSTFIELDS => $this->request_params);

                if (!empty($this->cookie_data))
                    $curl_opts += array(CURLOPT_COOKIE => $this->cookie_data);

                // Request logging (Only logs last request)
                if (!empty($this->request_log))
                {
                    if (is_writable(dirname($this->request_log)))
                    {
                        $request_log_file = fopen($this->request_log, 'w');
                        $curl_opts += array(
                            CURLOPT_VERBOSE => 1,
                            CURLOPT_STDERR => $request_log_file
                            );
                    }
                    else
                    {
                        @trigger_error('Check request_log directory/filename, it appears to be unwritable.');
                    }
                }

                // For debugging
                $this->curl_arguments = $curl_opts;
                curl_setopt_array($curl, $curl_opts);

                $this->request_data = curl_exec($curl);

                // Inflate if gzip compression
                if (bin2hex(substr($this->request_data, 0, 3)) == '1f8b08')
                    $this->request_data = gzinflate(substr($this->request_data, 10, -8));
                
                $this->request_actual_url = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
                $this->request_http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                $this->request_content_type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);

                $this->request_error = curl_error($curl);

                curl_close ($curl);

                if (!isset($arguments['cache']) || $arguments['cache'] === true)
                {
                    $cache->set($cache_key, $this->request_data);
                }
            }
        }

        if (empty($this->request_error))
        {
            return $this;
        }
        else
        {
            return false;
        }
    }

    /**
     * Select data from content via xpath queries
     *
     * @return void
     **/
    public function findByXpath($query, $data=null)
    {
        $ret = array();

        if (empty($data))
        {
            $data = $this;
        }
        
        $doc = new DomDocument();

        if (@$doc->loadHTML($data))
        {
            $domXPath = new DomXPath($doc);
            //$domXPath->registerNamespace("php", "http://php.net/xpath");

            if (is_string($query))
            {
                $items = $domXPath->query($query);

                foreach ($items as $item)
                {
                    // print_r($item);
                    $item = $this->__xpathHTML($item);
                    $ret[] = $item;
                }
            }
            elseif (is_array($query))
            {
                
            }

            return $ret;
        }
        else
        {
            @trigger_error('Document could not be loaded, please check that it is a valid HTML or XML document.');
            return array();
        }
    }

    /**
     * Return the classes processed json data as array
     *
     * @return string
     **/
    public function jsonDecode()
    {
        return json_decode($this);
    }

    /**
     * Handle the protected class variables
     *
     * @return mixed
     **/
    public function __get($name)
    {
        // Return the curl_arguments array with the curl constant names instead of decimal values
        if ($name == 'curl_arguments')
        {
            if (!empty($this->curl_arguments))
            {
                $constants = get_defined_constants(true);

                foreach ($constants['curl'] as $constant_key => $constant_value)
                {
                    foreach ($this->curl_arguments as $key => $value)
                    {
                        if ($constant_value == $key)
                        {
                            $ret[$constant_key] = $value;
                        }
                    }
                }

                return $ret;
            }
            else
            {
                return $this->curl_arguments;
            }
        }
    }

    /**
     * Dump currently selected data out as a string
     *
     * @return string
     **/
    public function __toString()
    {
        // Return processed data if available, otherwise raw page data
        if (isset($this->data) && !empty($this->data))
        {
            return $this->data;
        }
        elseif (isset($this->request_data) && !empty($this->request_data))
        {
            return $this->request_data;
        }
        else
        {
            return '';
        }
    }

    /**
     * Get HTML content of an xpath node
     *
     * @return string
     **/
    private function __xpathHTML($element)
    {
        $inner_html = '';

        $children = $element->childNodes;
        foreach ($children as $child)
        {
            $dom_doc = new DOMDocument();
            $dom_doc->appendChild($dom_doc->importNode($child, true));
            $inner_html .= trim($dom_doc->saveHTML());
        }
        return $inner_html;
    }

    /**
     * Save the data to file
     *
     * @return boolean
     **/
    public function save($filename)
    {
        return file_put_contents($filename, $this);
    }
} // END class ScreenScraper



/**
 * Caching class
 *
 * @package ScreenScraper
 * @author Keith McGahey <jeffory@c0d.in>
 **/
class ScreenScraperCache
{
    /**
     * Cache directory
     *
     * @var string
     **/
    protected $directory;

    /**
     * Cache file expiry
     *
     * @var integer
     **/
    protected $expiry;

    /**
     * We need a prefix to determine what files belong to the cache for the delete all function
     * Dropbox doesn't sync these files if it starts with ~$, see: https://www.dropbox.com/help/145/en
     *
     * @var string
     **/
    protected $file_prefix = '~$cache_';

    /**
     * Constructor, set directory
     *
     * @return void
     **/
    public function __construct($directory='.', $expiry=0)
    {
        $this->directory = realpath($directory);
        $this->expiry = $expiry;
    }

    /**
     * Get a value from the cache by key
     *
     * @return mixed string/boolean
     **/
    public function get($key)
    {
        $cache_file = $this->directory. DIRECTORY_SEPARATOR. $this->file_prefix. $key;

        if (file_exists($cache_file) && ($this->expiry == 0 || time() - @filemtime($cache_file) < $this->expiry))
        {
            if ($value = unserialize(file_get_contents($cache_file)))
            {
                return $value;
            }
            else
            {
                return false;
            }
        }
        else
        {
            return false;
        }
    }

    /**
     * Set a value in the cache by key
     *
     * @return boolean if set 
     **/
    public function set($key, $value)
    {
        $cache_file = $this->directory. DIRECTORY_SEPARATOR. $this->file_prefix. $key;

        if (is_writable($this->directory))
        {
            return file_put_contents($cache_file, serialize($value));
        }
        else
        {
            return false;
        }
    }

    /**
     * Delete a value from the cache by key
     *
     * @return boolean if deleted
     **/
    public function del($key)
    {
        $cache_file = $this->directory. DIRECTORY_SEPARATOR. $this->file_prefix. $key;

        if (file_exists($cache_file))
        {
            return unlink($cache_file);
        }
        else
        {
            return false;
        }
    }

    /**
     * Delete a value from the cache by key
     *
     * @return boolean if deleted
     **/
    public function delAll()
    {
        $cache_files = glob($this->directory. DIRECTORY_SEPARATOR. '/'. $this->file_prefix. '*');

        foreach($cache_files as $cache_file)
        {
            if (is_file($cache_file)) unlink($cache_file);
        }
    }
} // END class Cache