<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Controllers;

use Inkvizitoria\MonoParts\Contracts\CallbackHandlerInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CallbackController
{
    public function __construct(
        private readonly CallbackHandlerInterface $handler,
    ) {
    }

    /**
     * Forward callback to configured handler.
     */
    public function __invoke(Request $request): Response
    {
        return $this->handler->handle($request);
    }
}
