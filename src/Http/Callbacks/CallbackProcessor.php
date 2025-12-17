<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Http\Callbacks;

use Inkvizitoria\MonoParts\Config\MonoPartsConfig;
use Inkvizitoria\MonoParts\Contracts\CallbackHandlerInterface;
use Inkvizitoria\MonoParts\Contracts\SignerInterface;
use Inkvizitoria\MonoParts\Exceptions\PayloadValidationException;
use Inkvizitoria\MonoParts\Exceptions\SignatureValidationException;
use Inkvizitoria\MonoParts\Events\CallbackFailed;
use Inkvizitoria\MonoParts\Events\CallbackReceived;
use Inkvizitoria\MonoParts\Events\CallbackValidated;
use Inkvizitoria\MonoParts\Http\Responses\OrderStateInfo;
use Inkvizitoria\MonoParts\Support\MonoPartsLogger;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class CallbackProcessor implements CallbackHandlerInterface
{
    public function __construct(
        private readonly SignerInterface $signer,
        private readonly MonoPartsConfig $config,
        private readonly Dispatcher $events,
        private readonly MonoPartsLogger $logger,
    ) {
    }

    /**
     * Validate incoming callback signature and dispatch domain events.
     */
    public function handle(Request $request): Response
    {
        $payload = $request->all();
        $signature = $request->header($this->config->signatureHeader);

        $this->events->dispatch(new CallbackReceived($payload, $signature));

        try {
            $this->assertSignature($payload, $signature);
            $validated = $this->validatePayload($payload);
            $stateInfo = OrderStateInfo::fromPayload($validated);

            $this->events->dispatch(new CallbackValidated($validated, $signature ?? '', $stateInfo));

            return new JsonResponse(['message' => 'ok'], Response::HTTP_OK);
        } catch (SignatureValidationException $e) {
            $this->events->dispatch(new CallbackFailed($payload, $signature, $e));
            $this->logger->get()->warning('monoparts.callback.rejected', ['reason' => $e->getMessage()]);

            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (PayloadValidationException $e) {
            $this->events->dispatch(new CallbackFailed($payload, $signature, $e));
            $this->logger->get()->warning('monoparts.callback.invalid_payload', ['reason' => $e->getMessage()]);

            return new JsonResponse(['message' => $e->getMessage(), 'errors' => $e->errors()], Response::HTTP_BAD_REQUEST);
        } catch (Throwable $e) {
            $this->events->dispatch(new CallbackFailed($payload, $signature, $e));
            $this->logger->get()->error('monoparts.callback.failed', [
                'exception' => $e,
            ]);

            return new JsonResponse(['message' => 'error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertSignature(array $payload, ?string $signature): void
    {
        if (!$signature || !$this->signer->verify($payload, $signature)) {
            throw new SignatureValidationException('Invalid callback signature.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function validatePayload(array $payload): array
    {
        $validator = Validator::make($payload, [
            'order_id' => ['required', 'string', 'min:1', 'max:100', 'regex:/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/'],
            'state' => ['required', 'string', 'in:SUCCESS,FAIL,IN_PROCESS'],
            'order_sub_state' => ['nullable', 'string', 'regex:/^[A-Z_]+$/'],
            'message' => ['nullable', 'string'],
        ]);

        try {
            return $validator->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw new PayloadValidationException($e->errors(), $e->getMessage());
        }
    }
}
