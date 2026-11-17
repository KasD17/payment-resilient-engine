<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /**
     * @var array<string, PaymentGatewayInterface>
     */
    protected array $gateways = [];

    public function __construct(
        protected StripePaymentGateway $stripeGateway,
        protected PayPalPaymentGateway $paypalGateway
    ) {
        $this->gateways['stripe'] = $this->stripeGateway;
        $this->gateways['paypal'] = $this->paypalGateway;
    }

    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        $gatewayName = $name ?? config('payment.default', 'stripe');

        if (! isset($this->gateways[$gatewayName])) {
            throw new InvalidArgumentException("Payment gateway [{$gatewayName}] is not supported.");
        }

        return $this->gateways[$gatewayName];
    }

    public function extend(string $name, PaymentGatewayInterface $gateway): self
    {
        $this->gateways[$name] = $gateway;

        return $this;
    }
}
