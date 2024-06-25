<?php

namespace Caracom\Drivers\Rest\Http;


use Illuminate\Http\Request;

class CaraRequest extends Request
{
    public function __construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
    {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);

        if ($this->isJson() && isset($this->request)) {
            $this->setJson($this->request);
        }
    }
}
