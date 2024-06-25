<?php

namespace Haziqzahari\LaraMicroRest;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\HttpFoundation\Cookie;

trait MicroRest
{
    protected $version;
    protected $prefix;
    protected $headers = [];
    protected $cookies = [];

    public string $SERVER_NAME = '';
    public string $MODULE_PREFIX;

    /**
     * Get hostname for the application.
     *
     * @return string
     */
    protected function getHost(): string
    {

        $prefix = strtoupper($this->getConfigKey());

        if(Config::has("api.{$prefix}"))
        {
            return config("api.{$prefix}");
        }

        if (property_exists($this, 'SERVER_NAME') && !is_null($this->SERVER_NAME)) {
            return $this->SERVER_NAME;
        }

        throw new Exception("Property \$SERVER_NAME or config, api.{$this->getConfigKey()} is not set in class or might be null.");
    }

    /**
     * Get API Prefix for Request Forwarding.
     *
     * @return string
     */
    protected function getPrefix(): string
    {
        if (defined(get_class($this)."::PREFIX") && !is_null(static::PREFIX)) {
            return static::PREFIX;
        }

        throw new Exception("Constant PREFIX is not set in class or might be null.");
    }

    protected function getConfigKey(): string
    {
        if (defined(get_class($this)."::CONFIG_KEY") && !is_null(static::CONFIG_KEY)) {
            return static::CONFIG_KEY;
        }

        throw new Exception("Constant CONFIG_KEY is not set in class or might be null.");
    }

    /**
     * getUrl
     *
     * @return string
     */
    public function getUrl($uri): string
    {
        if (Str::startsWith($uri, '/')) {
            $uri = Str::replaceFirst('/', '', $uri);
        }

        if (empty($uri)) {
            return sprintf('%s/%s/%s', $this->getHost(), $this->getVersion(),  $this->getPrefix(), $uri);
        } else {
            return sprintf('%s/%s/%s/%s', $this->getHost(), $this->getVersion(),  $this->getPrefix(), $uri);
        }
    }

    public function getVersion(): string
    {
        if (is_null(Config::get('api.version'))) {
            throw new Exception('API Version is not set in config.');
        }

        if (is_null(Config::get('api.prefix'))) {
            throw new Exception('API Prefix is not set in config.');
        }

        return sprintf(
            '%s/%s',
            Config::get('api.prefix'),
            Config::get('api.version')
        );
    }

    /**
     * rest
     *
     * @param  mixed $verb
     * @param  mixed $uri
     * @param  mixed $parameters
     * @param  mixed $content
     * @return void
     */
    public function rest($verb, string $uri, array $parameters  = [], string $content = '')
    {
        return $this->makeRequest(
            $verb,
            $uri,
            $parameters,
            $content
        );
    }

    /**
     * makeRequest
     *
     * @param  mixed $verb
     * @param  mixed $uri
     * @param  mixed $parameters
     * @param  mixed $content
     * @param  mixed $baseUrl
     * @return void
     */
    private function makeRequest(string $verb, string $uri, array $parameters)
    {

        $hasFile = false;

        $http = Http::withHeaders($this->prepareServerVariables());

        $data = $this->mapParameters($parameters);

        collect($parameters)->filter(fn ($item) => $this->isFileParameter($item))->each(function ($file, $key) use (&$http, &$hasFile) {
            $hasFile = true;
            $http->attach(
                $key,
                fopen($file->getPathname(), 'r'),
                $file->getClientOriginalName()
            );
        });


        $response = $http->$verb($this->getUrl($uri), $hasFile ? $data : $parameters);

        if ($response->successful()) {
            return $response->json();
        }

        $response->throw(function (Response $response, RequestException $e) {

            $errorMessage = $response->body();

            throw new Exception(json_decode($errorMessage)->message, $e->getCode() != 0 ? $e->getCode() : 500);

        })->json();
    }

    protected function mapParameters(array $parameters, string $index = null)
    {
        $data = collect($parameters)->mapWithKeys(function ($param, $key) use ($index) {

            if (is_array($param)) {
                return $this->mapParameters($param, (is_null($index) ? $key : $index . "[{$key}]"));
            }

            return [(is_null($index) ? $key : $index . "[{$key}]")  => $this->isFileParameter($param) ? null : $param];
        })->filter(fn ($item) => $item != null)->toArray();

        return $data;
    }


    private function isFileParameter($value)
    {
        return $value instanceof UploadedFile && $value->isValid();
    }

    private function prepareServerVariables(): array
    {
        $server = [
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REQUEST_METHOD' => '',
            'Accept' => 'application/json',
        ];

        return $server;
    }


    protected function addPrefixToUri($uri): string
    {
        return sprintf('%s/%s/%s', $this->prefix, $this->version, $uri);
    }

    public function cookie(Cookie $cookie): static
    {
        $this->cookies[] = $cookie;
        return $this;
    }

    public function header(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }
}
