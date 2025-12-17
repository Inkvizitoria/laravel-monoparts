<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface CallbackHandlerInterface
{
    /**
     * Handle incoming callback and return HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request): Response;
}
