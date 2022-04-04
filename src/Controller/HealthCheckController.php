<?php

namespace App\Controller;


use Symfony\Component\HttpFoundation\Response;

/** Controller for default route required by AWS ElasticBeanstalk health check */

class HealthCheckController extends Controller
{
    public function checkAction()
    {
        return new Response('OK');
    }
}
