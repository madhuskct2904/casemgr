<?php
declare(strict_types=1);

namespace App\EventListener;

use App\Exception\ResponseException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException'
        ];
    }

    public function onKernelException(GetResponseForExceptionEvent $event): void
    {
        $e = $event->getThrowable();
        if (!$e instanceof ResponseException) {
            return;
        }

        $response = new JsonResponse(
            [
                'status' => 'error',
                'message' => $e->getMessage(),
                'code' => $e->getStatusCode()
            ],
            $e->getStatusCode()
        );
        $response->headers->set('Content-Type', 'application/problem+json');

        $event->allowCustomResponseCode();
        $event->setResponse($response);
    }
}
