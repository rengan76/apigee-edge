<?php

namespace Apigee\Util;

use Apigee\Exceptions\ResponseException;
use Apigee\Exceptions\IllegalMethodException;
use Guzzle\Http\Client as GuzzleClient;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Exception\CurlException;
use Guzzle\Http\Message\EntityEnclosingRequestInterface;
use Guzzle\Http\EntityBodyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Base class for API object classes. Handles some of the OrgConfig
 * invocation, which makes the actual HTTP calls.
 *
 * @author djohnson
 */
class APIObject
{

    /**
     * The OrgConfig object.
     * @var \Apigee\Util\OrgConfig
     */
    protected $config;

    /**
     * The client object as an instance of
     * {@link http://api.guzzlephp.org/class-Guzzle.Http.Client.html \Guzzle\Http\Client}.
     *
     * @var \Guzzle\Http\Client
     */
    protected $client;

    /**
     * @var array
     * Contains raw data from the Management API in a format defined by
     * {@link Apigee\Util\DebugData}.
     * This format is compatible with older
     * PHP implementations of this library.
     */
    protected $debugData;

    /**
     * The HTTP response code returned by the request.
     * @var int
     */
    protected $responseCode;

    /**
     * The response body as a string.
     * @var string
     */
    protected $responseText;

    /**
     * The response object.
     * @var array
     */
    protected $responseObj;

    /**
     * The response length.
     * @var int
     */
    protected $responseLength;

    /**
     * The response MIME type.
     * @var string
     */
    protected $responseMimeType;

    /**
     * A logger that implements the \Psr\Log\LoggerInterface interface.
     * See the {@link Apigee\Drupal\WatchdogLogger} class for an example that
     * implements the Psr\Log\LoggerInterface.
     * @var \Psr\Log\LoggerInterface
     */
    public static $logger;

    /**
     * @var string
     */
    private $cachedBaseUrl;

    /**
     * @var array
     */
    private $subscribers;

    /**
     * Initializes the OrgConfig for this class.
     *
     * @param \Apigee\Util\OrgConfig $config
     * @param string $base_url
     */
    protected function init(OrgConfig $config, $base_url)
    {
        $this->subscribers = array();
        $this->config =& $config;
        $base_url = rtrim($config->endpoint, '/') . '/' . ltrim($base_url, '/');

        $opts = array();
        if (is_array($config->http_options) && !empty($config->http_options)) {
            foreach ($config->http_options as $key => $value) {
                if (!isset($opts[GuzzleClient::REQUEST_OPTIONS][$key])) {
                    $opts[GuzzleClient::REQUEST_OPTIONS][$key] = $value;
                }
            }
        }
        if (is_array($config->curl_options) && !empty($config->curl_options)) {
            foreach ($config->curl_options as $key => $value) {
                if (!isset($opts[GuzzleClient::CURL_OPTIONS][$key])) {
                    $opts[GuzzleClient::CURL_OPTIONS][$key] = $value;
                }
            }
        }
        $opts[GuzzleClient::DISABLE_REDIRECTS] = $config->redirect_disable;

        $this->client = new GuzzleClient($base_url, $opts);
        if (is_array($config->subscribers)) {
            foreach ($config->subscribers as $subscriber) {
                if ($subscriber instanceof EventSubscriberInterface) {
                    $this->client->addSubscriber($subscriber);
                    $this->subscribers[] = $subscriber;
                }
            }
        }
        if (!empty($config->user_agent)) {
            $this->client->setUserAgent($config->user_agent, true);
        }
        self::$logger = $config->logger;
    }

    /**
     * Clears any subscribers that may have been attached to the HTTP client.
     */
    protected function clearSubscribers()
    {
        if (!($this->client instanceof \Guzzle\Http\Client) || empty($this->subscribers)) {
            return;
        }
        foreach ($this->subscribers as $subscriber) {
            $this->client->getEventDispatcher()->removeSubscriber($subscriber);
        }
    }

    /**
     * Restores any subscribers that were cleared by self::clearSubscribers().
     */
    protected function restoreSubscribers()
    {
        if (!($this->client instanceof \Guzzle\Http\Client) || empty($this->subscribers)) {
            return;
        }
        foreach ($this->subscribers as $subscriber) {
            $this->client->addSubscriber($subscriber);
        }
    }

    /**
     * Overwrites the base URL defined in $client.
     *
     * You can restore the base URL by calling restoreBaseUrl().
     *
     * @param string $base_url
     */
    protected function setBaseUrl($base_url)
    {
        $this->cachedBaseUrl = $this->client->getBaseUrl();
        $base_url = rtrim($this->config->endpoint, '/') . '/' . ltrim($base_url, '/');
        $this->client->setBaseUrl($base_url);
    }

    /**
     * Restores the base URL in $client after a cal to setBaseUrl().
     */
    protected function restoreBaseUrl()
    {
        $this->client->setBaseUrl($this->cachedBaseUrl);
    }

    /**
     * Returns the OrgConfig in use by this class, so it can be reused by other
     * instances of Base.
     *
     * @return \Apigee\Util\OrgConfig
     * @see OrgConfig
     * @see \Apigee\ManagementAPI\Base
     */
    public function getConfig()
    {
        return $this->config;
    }

    private function exec(RequestInterface $request)
    {
        $start = microtime(true);
        $this->responseCode = 0;
        // Get snapshot of request headers.
        $request_headers = $request->getRawHeaders();
        // Mask authorization for logs.
        $request_headers = preg_replace(
            '!\n(Authentication|Authorization): (Basic|Digest|Bearer) [^\r\n]+\r!i',
            "\n$1: $2 [**masked**]\r",
            $request_headers
        );
        try {
            $response = $request->send();
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
        } catch (CurlException $e) {
            // Timeouts etc.
            DebugData::$raw = '';
            DebugData::$code = $e->getErrorNo();
            DebugData::$code_status = $e->getError();
            DebugData::$code_class = 0;
            DebugData::$exception = $e->getMessage();
            DebugData::$opts = array('request_headers' => $request_headers);
            DebugData::$data = null;
            $exception = new ResponseException($e->getError(), $e->getErrorNo(), $request->getUrl(), DebugData::$opts);
            $exception->requestObj = $request;
            // Log Exception
            $headerCollection = $request->getHeaders();
            $headerCollection->offsetUnset('Authorization');
            $headers_array = $headerCollection->toArray();
            $headerString = '';
            foreach ($headers_array as $name => $value) {
                if (!is_array($value)) {
                    $value = array($value);
                }
                foreach ($value as $v) {
                    $headerString .= $name . ': ' . $v . " ";
                }
            }
            $log_message = '{code_status} ({code}) Request Details:[ {r_method} {r_resource} {r_scheme} {r_headers} ]';
            $httpScheme = strtoupper(str_replace('https', 'http', $request->getScheme()))
                . $request->getProtocolVersion();
            $log_params = array(
                'code' => $e->getErrorNo(),
                'code_status' => $e->getError(),
                'r_method' => $request->getUrl(),
                'r_resource' => $request->getRawHeaders(),
                'r_scheme' => $httpScheme,
                'r_headers' => $headerString,
            );
            self::$logger->emergency($log_message, $log_params);
            throw $exception;
        }
        $this->responseCode = $response->getStatusCode();
        $this->responseText = trim($response->getBody(true));
        $this->responseLength = $response->getContentLength();
        $this->responseMimeType = $response->getContentType();
        $this->responseObj = array();
        $content_type = $response->getContentType();
        $firstChar = substr($this->responseText, 0, 1);
        if (strpos($content_type, '/json') !== false && ($firstChar == '{' || $firstChar == '[')) {
            $response_obj = @json_decode($this->responseText, true);
            if (is_array($response_obj)) {
                $this->responseObj = $response_obj;
            }
        }

        $status = self::getStatusMessage($this->responseCode);
        $code_class = floor($this->responseCode / 100);

        DebugData::$raw = $this->responseText;
        DebugData::$opts = array(
            'request_headers' => $request_headers,
            'response_headers' => $response->getRawHeaders()
        );

        if ($request instanceof EntityEnclosingRequestInterface) {
            DebugData::$opts['request_body'] = (string)$request->getBody();
        }
        DebugData::$opts['request_type'] = class_implements($request);
        DebugData::$data = $this->responseObj;
        DebugData::$code = $this->responseCode;
        DebugData::$code_status = $status;
        DebugData::$code_class = $code_class;
        DebugData::$exception = null;
        DebugData::$time_elapsed = microtime(true) - $start;

        if ($code_class != 2) {
            $uri = $request->getUrl();
            if (!empty($this->responseCode) && isset($this->responseObj['message'])) {
                $message = 'Code: ' . $this->responseCode . '; Message: ' . $this->responseObj['message'];
            } else {
                $message = 'API returned HTTP code of ' . $this->responseCode . ' when fetching from ' . $uri;
            }

            DebugData::$exception = $message;
            $this->debugCallback(DebugData::toArray());
            self::$logger->error($this->responseText);

            // Create better status to show up in logs
            $status .= ': ' . $request->getMethod() . ' ' . $uri;
            if ($request instanceof EntityEnclosingRequestInterface) {
                $body = $request->getBody();
                if ($body instanceof EntityBodyInterface) {
                    $status .= ' with Content-Length of ' . $body->getContentLength()
                        . ' and Content-Type of ' . $body->getContentType();
                }
            }

            $exception = new ResponseException(
                $status,
                $this->responseCode,
                $uri,
                DebugData::$opts,
                $this->responseText
            );
            $exception->requestObj = $request;
            $exception->responseObj = $response;
            throw $exception;
        }
        $this->debugCallback(DebugData::toArray());
    }

    private function debugCallback(array $debug)
    {
        if (is_array($this->config->debug_callbacks)) {
            foreach ($this->config->debug_callbacks as $callback) {
                if (is_callable($callback)) {
                    call_user_func($callback, $debug);
                }
            }
        }
    }

    /**
     * Performs an HTTP GET on a URI. The result can be read from
     * $this->response* variables.
     *
     * @param string|null $uri
     * @param string $accept_mime_type
     * @param array $custom_headers
     * @param array $options
     */
    public function get(
        $uri = null,
        $accept_mime_type = 'application/json; charset=utf-8',
        array $custom_headers = array(),
        array $options = array()
    ) {
        $headers = array('accept' => $accept_mime_type);
        foreach ($custom_headers as $key => $value) {
            $headers[strtolower($key)] = $value;
        }
        $request = $this->client->get($uri, $headers, $options);
        $this->exec($request);
    }

    /**
     * Performs an HTTP POST on a URI. The result can be read from
     * $this->response* variables.
     *
     * @param string $uri
     * @param mixed $payload
     * @param string $content_type
     * @param string $accept_type
     * @param array $custom_headers
     * @param array $options
     */
    public function post(
        $uri = null,
        $payload = '',
        $content_type = 'application/json; charset=utf-8',
        $accept_type = 'application/json; charset=utf-8',
        array $custom_headers = array(),
        array $options = array()
    ) {
        self::preparePayload($content_type, $payload);
        $headers = array(
            'accept' => $accept_type,
            'content-type' => $content_type
        );
        foreach ($custom_headers as $key => $value) {
            $headers[strtolower($key)] = $value;
        }
        if (strlen($payload) == 0) {
            $headers['content-type'] = '';
        }
        $request = $this->client->post($uri, $headers, $payload, $options);
        $this->exec($request);
    }

    /**
     * Performs an HTTP DELETE on a URI. The result can be read from
     * $this->response* variables.
     *
     * This method is named httpDelete() to avoid a name clash with objects that inherit
     * from this one, which usually have a delete() method.
     *
     * @param string $uri
     * @param string $accept
     * @param array $custom_headers
     * @param array $options
     */
    public function httpDelete(
        $uri = null,
        $accept = 'application/json; charset=utf-8',
        array $custom_headers = array(),
        array $options = array()
    ) {
        $headers = array('accept' => $accept);
        foreach ($custom_headers as $key => $value) {
            $headers[strtolower($key)] = $value;
        }
        $request = $this->client->delete($uri, $headers, null, $options);
        $this->exec($request);
    }

    /**
     * Performs an HTTP PUT on a URI. The result can be read from
     * $this->response* variables.
     *
     * @param string|null $uri
     * @param mixed $payload
     * @param string $content_type
     * @param string $accept_type
     * @param array $custom_headers
     * @param array $options
     */
    public function put(
        $uri = null,
        $payload = '',
        $content_type = 'application/json; charset=utf-8',
        $accept_type = 'application/json; charset=utf-8',
        array $custom_headers = array(),
        array $options = array()
    ) {
        self::preparePayload($content_type, $payload);
        $headers = array(
            'accept' => $accept_type,
            'content-type' => $content_type
        );
        foreach ($custom_headers as $key => $value) {
            $headers[strtolower($key)] = $value;
        }
        if (strlen($payload) == 0) {
            $headers['content-type'] = '';
        }
        $request = $this->client->put($uri, $headers, $payload, $options);
        $this->exec($request);
    }

    /**
     * Performs an HTTP HEAD on a URI. $this->responseText and $this->responseObj
     * empty after this call, but you can read the content-length from
     * $this->responseLength.
     *
     * @param string $uri
     * @param string $accept_mime_type
     * @param array $custom_headers
     * @param array $options
     */
    public function head(
        $uri = null,
        $accept_mime_type = 'application/json; charset=utf-8',
        array $custom_headers = array(),
        array $options = array()
    ) {
        $headers = array('accept' => $accept_mime_type);
        foreach ($custom_headers as $key => $value) {
            $headers[strtolower($key)] = $value;
        }
        $request = $this->client->head($uri, $headers, $options);
        $this->exec($request);
    }

    /**
     * Intercepts any snake_case method invocations that aren't already
     * defined, turns them into camelCase, and tries to invoke them.
     *
     * Incoming snake_case method names must contain no uppercase to
     * qualify for this transmogrification. In the interest of efficiency,
     * we also don't process any method names not containing an underscore.
     *
     * @TODO When we require PHP 5.4, make this a mix-in.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     * @throws \Apigee\Exceptions\IllegalMethodException
     * @internal
     */
    public function __call($method, array $args)
    {
        $class = get_class();
        $exceptionText = sprintf('Class “%s” contains no such static method “%s”', $class, $method);

        if ($method == strtolower($method) && strpos($method, '_') !== false) {
            $parts = explode('_', $method);
            $camel_case = array_shift($parts);
            foreach ($parts as $part) {
                $camel_case .= ucfirst($part);
            }
            if (method_exists($this, $camel_case)) {
                self::warnDeprecated($class, $method);
                return call_user_func_array(array($this, $camel_case), $args);
            }
            throw new IllegalMethodException($exceptionText . ' (even after camelCasing)');
        }
        throw new IllegalMethodException($exceptionText);
    }

    /**
     * Same as above, except for static methods
     *
     * @param $method
     * @param $args
     * @return mixed
     * @throws \Apigee\Exceptions\IllegalMethodException
     * @internal
     */
    public static function __callstatic($method, array $args)
    {
        $class = get_class();

        $exceptionText = sprintf('Class “%s” contains no such static method “%s”', $class, $method);

        if ($method == strtolower($method) && strpos($method, '_') !== false) {
            $parts = explode('_', $method);
            $camel_case = array_shift($parts);
            foreach ($parts as $part) {
                $camel_case .= ucfirst($part);
            }
            if (method_exists($class, $camel_case)) {
                self::warnDeprecated($class, $method);
                return forward_static_call_array(array($class, $camel_case), $args);
            }
            throw new IllegalMethodException($exceptionText . ' (even after camelCasing)');
        }
        throw new IllegalMethodException($exceptionText);
    }

    /**
     * Given a status code, returns the proper human-readable message which
     * corresponds to that code.
     *
     * @param int $code
     * @return string
     */
    private static function getStatusMessage($code)
    {
        static $responses = array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing', // WebDAV

            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status', // WebDAV
            208 => 'Already Reported', // WebDAV
            226 => 'IM Used',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => 'Switch Proxy',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Time-out',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Large',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot', // RFC 2324 ;-)
            420 => 'Enhance Your Calm', // Twitter ;-)
            422 => 'Unprocessable Entity', // WebDAV
            423 => 'Locked', // WebDAV
            424 => 'Failed Dependency', // WebDAV
            425 => 'Unordered Collection',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            444 => 'No Response', // nginx
            449 => 'Retry With', // Microsoft
            450 => 'Blocked By Parental Controls', // Microsoft
            451 => 'Unavailable for Legal Reasons',
            494 => 'Request Header Too Large', // nginx
            495 => 'Cert Error', // nginx
            496 => 'No Cert', // nginx
            497 => 'HTTP to HTTPS', // nginx
            499 => 'Client Closed Request', // nginx

            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version not supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage', // WebDAV
            508 => 'Loop Detected', // WebDAV
            509 => 'Bandwidth Limit Exceeded', // apache?
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
            598 => 'Network read timeout error', // Microsoft
            599 => 'Network connect timeout error', // Microsoft
        );

        if (!isset($responses[$code])) {
            // According to RFC 2616, all unknown HTTP codes must be treated the same
            // as the base code in their class.
            $code = floor($code / 100) * 100;
        }
        if (!isset($responses[$code])) {
            // Something is seriously screwy; treat it as an internal server error.
            $code = 500;
        }
        return $responses[$code];
    }


    /**
     * If payload is not already a string, convert it to a string based on its content-type.
     *
     * @static
     * @param $content_type
     * @param $payload
     */
    protected static function preparePayload($content_type, &$payload)
    {
        // If content_type includes charset, strip it off.
        if (($i = strpos($content_type, ';')) !== false) {
            $content_type = trim(substr($content_type, 0, $i));
        }
        if ($content_type == 'application/json' && (is_object($payload) || is_array($payload))) {
            // Turn objects/arrays into JSON strings.
            $payload = json_encode($payload);
        } elseif ($content_type == 'application/xml') {
            // Turn XML document representations into strings.
            if ($payload instanceof \DOMDocument) {
                $payload = $payload->saveXML($payload->documentElement);
            } elseif ($payload instanceof \SimpleXMLElement) {
                $payload = $payload->asXML();
                // strip off processing instruction if present
                $payload = preg_replace('!^<\?[^?]+\?>!', '', $payload);
            }
        }
    }

    private static function warnDeprecated($class, $method)
    {
        if (version_compare(PHP_VERSION, '5.4.0', 'ge')) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        } else {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }
        $frame = $backtrace[3];
        $message = 'Deprecated method %s:%s was invoked in file %s, line %s. Please use camelCase method name instead.';
        $messageArgs = array(
            $class,
            $method,
            $frame['file'],
            $frame['line'],
        );
        self::$logger->notice(vsprintf($message, $messageArgs), array('type' => $class));
    }
}
