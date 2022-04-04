<?php
declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

class RequestListener
{
    public function onKernelResponse(FilterResponseEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $response = $event->getResponse();

        if (!$response) {
            return;
        }

        $build = file_exists('build.txt') ? file_get_contents('build.txt') : 'undefined';

        $response->headers->set("X-Build-Id", $build);
    }
}