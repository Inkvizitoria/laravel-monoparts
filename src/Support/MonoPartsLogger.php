<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Support;

use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final class MonoPartsLogger
{
    public function __construct(
        private readonly LogManager $logManager,
        private readonly array $loggingConfig
    ) {
    }

    /**
     * Resolve logger instance with graceful fallbacks.
     */
    public function get(): LoggerInterface
    {
        $channel = $this->loggingConfig['channel'] ?? null;
        $fallback = $this->loggingConfig['fallback_channel'] ?? 'stack';

        try {
            if ($channel) {
                return $this->logManager->channel($channel);
            }
        } catch (Throwable) {
            // fall through to build / fallback
        }

        if (!empty($this->loggingConfig['channel_config'])) {
            try {
                return $this->logManager->build($this->loggingConfig['channel_config']);
            } catch (Throwable) {
                // ignore build failures and fallback
            }
        }

        try {
            return $this->logManager->channel($fallback);
        } catch (Throwable) {
            return new NullLogger();
        }
    }
}
