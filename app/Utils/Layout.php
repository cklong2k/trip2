<?php

namespace App\Utils;

class Layout
{
    protected $name;
    protected $with;
    protected $cached;

    public function __construct($layout)
    {
        $this->layout = $layout;
        $this->with = collect();
        $this->cached = true;
    }

    public function with($key, $value)
    {
        $this->with->put($key, $value);

        return $this;
    }

    public function __call($method, $parameters)
    {
        $with = str_after($method, 'with');
        $this->with->put(strtolower(snake_case($with)), $parameters ? $parameters[0] : null);
        return $this;
    }

    public function cached($condition)
    {
        $this->cached = $condition;

        return $this;
    }

    public function render()
    {
        $response = response()->view("layouts.$this->layout.$this->layout", $this->with);

        return $this->cached
            ? $response->header('Cache-Control', 'public, s-maxage=' . config('cache.headers.default'))
            : $response;
    }
}
