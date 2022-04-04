<?php

namespace App\Controller;


use Symfony\Component\HttpFoundation\Response;

/** Controller for default route required by AWS ElasticBeanstalk health check */

class FallbackController extends Controller
{
    public function default()
    {
        return new Response('');
    }
}
