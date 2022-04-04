<?php

namespace App\Handler\Modules\Handler;

/**
 * Class Params
 *
 * @package App\Handler\Modules\Handler
 */
class Params
{
    private $data = [];

    /**
     * Params constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * @param string $name
     * @param null|mixed $default
     *
     * @return mixed|null
     */
    public function get(string $name, $default = null)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        return $default;
    }
}
