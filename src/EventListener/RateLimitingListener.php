<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)] // 30
final class RateLimitingListener
{
    public function __construct(
        #[Autowire(service: 'limiter.anonymous_api')]
        private readonly RateLimiterFactory $anonymousApiLimiterFactory,
        #[Autowire(service: 'limiter.authenticated_api')]
        private readonly RateLimiterFactory $authenticatedApiLimiterFactory,
        private readonly Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api') && !str_starts_with($path, '/admin/api')) {
            return;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();

        if ($user) {
            $limiter = $this->authenticatedApiLimiterFactory->create((string) $user->getId());
        } else {
            $limiter = $this->anonymousApiLimiterFactory->create($request->getClientIp());
        }

        $limiter->consume(1)->ensureAccepted();
    }

    /*public function onRequestEvent(RequestEvent $event): void
    {
        // ...
    }*/
}
