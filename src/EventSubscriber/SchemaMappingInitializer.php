<?php

namespace Cisse\Bundle\As400\EventSubscriber;

use Cisse\Bundle\As400\Service\SchemaMapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to ensure SchemaMapper is initialized before any request.
 * This ensures the schema mapping is applied before any database queries.
 */
class SchemaMappingInitializer implements EventSubscriberInterface
{
    private bool $initialized = false;

    public function __construct(
        private readonly SchemaMapper $schemaMapper
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // High priority to ensure it runs before any controller
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Only initialize once and only for master requests
        if ($this->initialized || !$event->isMainRequest()) {
            return;
        }

        // Force instantiation of SchemaMapper (constructor already sets the mapping)
        // Just accessing the service is enough as it sets the mapping in its constructor
        $this->initialized = true;
    }
}
