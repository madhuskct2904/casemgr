<?php

namespace App\Service;

/**
 * Class Request
 */
class Request
{
    /** @var array */
    private $headers;

    /** @var array */
    private $params;

    /**
     * Request constructor.
     */
    public function __construct()
    {
        $headers = [];

        $params = file_get_contents('php://input');
        $params = str_replace(["\n", "\r"], "", $params);
        // makes invalid json ! eg. "foo:bar, foo:bar"
        //$params     = preg_replace('/([{,]+)(\s*)([^"]+?)\s*:/', '$1"$3":', $params);
        //$params     = preg_replace('/(,)\s*}$/','}', $params);

        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headers[str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))))] = $value;
            }
        }

        $params = json_decode($params, true);

        if (is_array($params) === false) {
            $params = [];
        }

        $this->params = $params;
        $this->headers = $headers;
    }

    /**
     * @return array
     */
    public function params(): array
    {
        return $this->params;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasParam(string $name): bool
    {
        if ($this->param($name) === null) {
            return false;
        }

        return true;
    }

    /**
     * @param string $name
     * @param null $default
     *
     * @return mixed|null
     */
    public function param(string $name, $default = null)
    {
        if (isset($this->params[$name]) === false or $this->params[$name] === null) {
            return $default;
        }

        return $this->params[$name];
    }

    /**
     * @param string $name
     *
     * @param mixed $value
     */
    public function setParam(string $name, $value)
    {
        $this->params[$name] = $value;
    }

    /**
     * @return array
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasHeader(string $name): bool
    {
        if ($this->header($name) === null) {
            return false;
        }

        return true;
    }

    /**
     * @param string $name
     * @param null $default
     *
     * @return mixed|null
     */
    public function header(string $name, $default = null)
    {
        if (isset($this->headers[$name]) === false or $this->headers[$name] === null) {
            return $default;
        }

        return $this->headers[$name];
    }

    /**
     * @param string $name
     * @param null|mixed $default
     * @param boolean $raw
     *
     * @return null|string
     */
    public function post(string $name, $default = null, $raw = false): ?string
    {
        if (isset($_POST[$name])) {
            if ($raw) {
                return $_POST[$name];
            }
            return stripcslashes($_POST[$name]);
        }

        return $default;
    }

    public function query(): array
    {
        return $_GET;
    }

    public function hasQuery(string $key): bool
    {
        return array_key_exists($key, $this->query());
    }

    public function getQuery(string $key): ?string
    {
        return $this->query()[$key] ?? null;
    }

    /**
     * @return array
     */
    public function files(): array
    {
        return $_FILES;
    }
}
