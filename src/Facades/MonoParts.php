<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Facades;

use Inkvizitoria\MonoParts\Http\MonoPartsClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Inkvizitoria\MonoParts\Http\Responses\CheckPaidResult checkPaid(string $orderId)
 * @method static \Inkvizitoria\MonoParts\Http\Responses\CreateOrderResult createOrder(array $payload)
 * @method static \Inkvizitoria\MonoParts\Http\Responses\OrderStateInfo confirmOrder(string $orderId)
 * @method static \Inkvizitoria\MonoParts\Http\Responses\OrderShortInfo orderData(string $orderId)
 * @method static \Inkvizitoria\MonoParts\Http\Responses\OrderStateInfo rejectOrder(string $orderId)
 * @method static \Inkvizitoria\MonoParts\Http\Responses\ReturnResponse returnOrder(string $orderId, float $sum, bool $returnMoneyToCard, string $storeReturnId, array $additionalParams = [])
 * @method static \Inkvizitoria\MonoParts\Http\Responses\OrderStateInfo orderState(string $orderId)
 * @method static \Inkvizitoria\MonoParts\Http\Responses\DailyReport storeReport(string $date)
 * @method static \Inkvizitoria\MonoParts\Http\Responses\ValidateClientResponse validateClientV2(?string $phone = null)
 * @method static \Inkvizitoria\MonoParts\Http\Responses\InstallmentAvailabilityResponse brokerAvailability(float $amount, string $employeeId, string $inn, string $outletId, string $phone, ?string $brokerId = null)
 */
final class MonoParts extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MonoPartsClient::class;
    }
}
