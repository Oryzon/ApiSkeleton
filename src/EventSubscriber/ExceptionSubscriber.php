<?php

namespace App\EventSubscriber;

use Doctrine\DBAL\Exception\DriverException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Entity\HttpCode;

class ExceptionSubscriber implements EventSubscriberInterface {

    public function onKernelException(ExceptionEvent $event) {
        $exception = $event->getThrowable();

        if ($exception instanceof NotFoundHttpException) {
            $data = [
                'status' => HttpCode::NOT_FOUND,
                'message' => 'Resource not found.'
            ];
        } else if ($exception instanceof DriverException) {
            $data = [
                'status' => HttpCode::INTERNAL_SERVER_ERROR,
                'message' => $exception->getMessage()
            ];
        } else if ($exception instanceof AccessDeniedHttpException) {
            $data = [
                'status' => HttpCode::FORBIDDEN,
                'message' => $exception->getMessage()
            ];
        } else if ($exception instanceof MethodNotAllowedHttpException) {
            $data = [
                'statut' => HttpCode::METHOD_NOT_ALLOWED,
                'message' => str_replace('"', "'", $exception->getMessage())
            ];
        } else {
            $data = [
                'statut' => HttpCode::I_M_A_TEAPOT,
                'message' => str_replace('"', "'", $exception->getMessage()),
                'class' => get_class($exception)
            ];
        }

        $event->setResponse(new JsonResponse($data));
    }

    public static function getSubscribedEvents() {
        return [
            'kernel.exception' => 'onKernelException',
        ];
    }
}
