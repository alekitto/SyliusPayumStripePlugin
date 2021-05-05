<?php

declare(strict_types=1);

namespace Tests\FluxSE\SyliusPayumStripePlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Event;
use Sylius\Behat\Page\Shop\Checkout\CompletePageInterface;
use Sylius\Behat\Page\Shop\Order\ShowPageInterface;
use Tests\FluxSE\SyliusPayumStripePlugin\Behat\Mocker\StripeCheckoutSessionMocker;
use Tests\FluxSE\SyliusPayumStripePlugin\Behat\Page\External\StripeCheckoutPage;

class StripeShopContext extends MinkContext implements Context
{
    /** @var StripeCheckoutSessionMocker */
    private $stripeSessionCheckoutMocker;

    /** @var CompletePageInterface */
    private $summaryPage;

    /** @var ShowPageInterface */
    private $orderDetails;

    /** @var StripeCheckoutPage */
    private $paymentPage;

    public function __construct(
        StripeCheckoutSessionMocker $stripeSessionCheckoutMocker,
        CompletePageInterface $summaryPage,
        ShowPageInterface $orderDetails,
        StripeCheckoutPage $paymentPage
    ) {
        $this->stripeSessionCheckoutMocker = $stripeSessionCheckoutMocker;
        $this->summaryPage = $summaryPage;
        $this->orderDetails = $orderDetails;
        $this->paymentPage = $paymentPage;
    }

    /**
     * @When I confirm my order with Stripe payment
     * @Given I have confirmed my order with Stripe payment
     */
    public function iConfirmMyOrderWithStripePayment()
    {
        $this->stripeSessionCheckoutMocker->mockCreatePayment(function () {
            $this->summaryPage->confirmOrder();
        });
    }

    /**
     * @When I get redirected to Stripe and complete my payment
     */
    public function iGetRedirectedToStripe(): void
    {
        $this->stripeSessionCheckoutMocker->mockSuccessfulPayment(
            function () {
                $jsonEvent = [
                    'id' => 'evt_1',
                    'type' => Event::CHECKOUT_SESSION_COMPLETED,
                    'object' => 'event',
                    'data' => [
                        'object' => [
                            'id' => 'cs_1',
                            'object' => Session::OBJECT_NAME,
                            'payment_intent' => 'pi_1',
                            'metadata' => [
                                'token_hash' => '%s',
                            ],
                        ],
                    ],
                ];
                $payload = json_encode($jsonEvent);

                $this->paymentPage->notify($payload);
            },
            function () {
                $this->paymentPage->captureOrAuthorizeThenGoToAfterUrl();
            }
        );
    }

    /**
     * @When I get redirected to Stripe and complete my payment using authorize
     */
    public function iGetRedirectedToStripeUsingAuthorize(): void
    {
        $this->stripeSessionCheckoutMocker->mockAuthorizePayment(
            function () {
                $jsonEvent = [
                    'id' => 'evt_1',
                    'type' => Event::CHECKOUT_SESSION_COMPLETED,
                    'object' => 'event',
                    'data' => [
                        'object' => [
                            'id' => 'cs_1',
                            'object' => Session::OBJECT_NAME,
                            'payment_intent' => 'pi_1',
                            'metadata' => [
                                'token_hash' => '%s',
                            ],
                        ],
                    ],
                ];
                $payload = json_encode($jsonEvent);

                $this->paymentPage->notify($payload);
            },
            function () {
                $this->paymentPage->captureOrAuthorizeThenGoToAfterUrl();
            }
        );
    }

    /**
     * @When I get redirected to Stripe and complete my payment without webhook
     */
    public function iGetRedirectedToStripeWithoutWebhooks(): void
    {
        $this->stripeSessionCheckoutMocker->mockSuccessfulPaymentWithoutWebhook(function () {
            $this->paymentPage->captureOrAuthorizeThenGoToAfterUrl();
        });
    }

    /**
     * @When I get redirected to Stripe and complete my payment without webhook using authorize
     */
    public function iGetRedirectedToStripeWithoutWebhookUsingAuthorize(): void
    {
        $this->stripeSessionCheckoutMocker->mockSuccessfulPaymentWithoutWebhookUsingAuthorize(function () {
            $this->paymentPage->captureOrAuthorizeThenGoToAfterUrl();
        });
    }

    /**
     * @Given I have clicked on "go back" during my Stripe payment
     * @When I click on "go back" during my Stripe payment
     */
    public function iClickOnGoBackDuringMyStripePayment()
    {
        $this->stripeSessionCheckoutMocker->mockGoBackPayment(function () {
            $this->paymentPage->captureOrAuthorizeThenGoToAfterUrl();
        });
    }

    /**
     * @When I try to pay again with Stripe payment
     */
    public function iTryToPayAgainWithStripePayment(): void
    {
        $this->stripeSessionCheckoutMocker->mockCreatePayment(function () {
            $this->orderDetails->pay();
        });
    }

    /**
     * @Then I should be notified that my payment has been authorized
     */
    public function iShouldBeNotifiedThatMyPaymentHasBeenAuthorized()
    {
        $this->assertNotification('Payment has been authorized.');
    }

    private function assertNotification(string $expectedNotification)
    {
        $notifications = $this->orderDetails->getNotifications();
        $hasNotifications = '';

        foreach ($notifications as $notification) {
            $hasNotifications .= $notification;
            if ($notification === $expectedNotification) {
                return;
            }
        }

        throw new RuntimeException(sprintf('There is no notification with "%s". Got "%s"', $expectedNotification, $hasNotifications));
    }
}
