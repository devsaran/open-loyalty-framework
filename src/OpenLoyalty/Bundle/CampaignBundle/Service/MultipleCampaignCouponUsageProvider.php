<?php
/**
 * Copyright © 2018 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Bundle\CampaignBundle\Service;

use Assert\AssertionFailedException;
use OpenLoyalty\Bundle\CampaignBundle\Exception\CampaignUsageChange\CampaignUsageChangeException;
use OpenLoyalty\Bundle\CampaignBundle\Exception\CampaignUsageChange\InvalidDataProvidedException;
use OpenLoyalty\Bundle\CampaignBundle\Exception\CampaignUsageChange\MissingDataInRowsException;
use OpenLoyalty\Bundle\CampaignBundle\Exception\NoCouponsLeftException;
use OpenLoyalty\Component\Campaign\Domain\Campaign;
use OpenLoyalty\Component\Campaign\Domain\CampaignId;
use OpenLoyalty\Component\Campaign\Domain\CampaignRepository;
use OpenLoyalty\Component\Customer\Domain\CampaignId as CustomerCampaignId;
use OpenLoyalty\Component\Customer\Domain\Command\ChangeCampaignUsage;
use OpenLoyalty\Component\Customer\Domain\CustomerId;
use OpenLoyalty\Component\Customer\Domain\Model\CampaignPurchase;
use OpenLoyalty\Component\Customer\Domain\Model\Coupon;
use OpenLoyalty\Component\Customer\Domain\ReadModel\CustomerDetails;
use OpenLoyalty\Component\Customer\Domain\ReadModel\CustomerDetailsRepository;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Class MultipleCampaignCouponUsageProvider.
 */
class MultipleCampaignCouponUsageProvider
{
    /**
     * @var CampaignRepository
     */
    private $campaignRepository;

    /**
     * @var CustomerDetailsRepository
     */
    private $customerDetailsRepository;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * MultipleCampaignCouponUsageProvider constructor.
     *
     * @param CampaignRepository        $campaignRepository
     * @param CustomerDetailsRepository $customerDetailsRepository
     * @param TranslatorInterface       $translator
     */
    public function __construct(
        CampaignRepository $campaignRepository,
        CustomerDetailsRepository $customerDetailsRepository,
        TranslatorInterface $translator
    ) {
        $this->campaignRepository = $campaignRepository;
        $this->customerDetailsRepository = $customerDetailsRepository;
        $this->translator = $translator;
    }

    /**
     * @param array $coupons
     *
     * @return ChangeCampaignUsage[]
     *
     * @throws CampaignUsageChangeException
     * @throws NoCouponsLeftException
     */
    public function validateRequest(array $coupons): array
    {
        $result = [];

        foreach ($coupons as $key => $coupon) {
            if (!isset($coupon['used'], $coupon['code'], $coupon['customerId'], $coupon['campaignId'])) {
                throw new MissingDataInRowsException($this->translator->trans('campaign.missing_data_in_rows'));
            }
            try {
                $used = boolval($coupon['used']);
                $campaign = $this->campaignRepository->byId(new CampaignId($coupon['campaignId']));
                $customer = $this->customerDetailsRepository->find(new CustomerId($coupon['customerId']));
                $coupon = new Coupon($coupon['code']);

                $this->checkFields($used, $customer, $campaign, $coupon, (string) $key);
            } catch (AssertionFailedException $exception) {
                throw new InvalidDataProvidedException(
                    $this->translator->trans(
                        'campaign.invalid_value_campaign_id_in_row',
                        [
                            '%content%' => $exception->getMessage(),
                            '%row%' => $key,
                        ]
                    )
                );
            }

            $result[] = new ChangeCampaignUsage(
                $customer->getCustomerId(),
                new CustomerCampaignId($campaign->getCampaignId()->__toString()),
                $coupon,
                $used
            );
        }

        return $result;
    }

    /**
     * @param array           $coupons
     * @param CustomerDetails $customer
     *
     * @return array
     *
     * @throws CampaignUsageChangeException
     * @throws NoCouponsLeftException
     */
    public function validateRequestForCustomer(array $coupons, CustomerDetails $customer): array
    {
        $result = [];

        foreach ($coupons as $key => $coupon) {
            if (!isset($coupon['used'], $coupon['code'], $coupon['campaignId'])) {
                throw new MissingDataInRowsException();
            }
            try {
                $used = boolval($coupon['used']);
                $campaign = $this->campaignRepository->byId(new CampaignId($coupon['campaignId']));
                $coupon = new Coupon($coupon['code']);
                $this->checkFields($used, $customer, $campaign, $coupon, $key);
            } catch (AssertionFailedException $exception) {
                throw new InvalidDataProvidedException();
            }

            $result[] = new ChangeCampaignUsage(
                $customer->getCustomerId(),
                new CustomerCampaignId($campaign->getCampaignId()->__toString()),
                $coupon,
                $used
            );
        }

        return $result;
    }

    /**
     * @param bool            $used
     * @param CustomerDetails $customer
     * @param Campaign        $campaign
     * @param Coupon          $coupon
     * @param string          $key
     *
     * @throws InvalidDataProvidedException
     * @throws NoCouponsLeftException
     */
    private function checkFields(bool $used, CustomerDetails $customer, Campaign $campaign, Coupon $coupon, string $key): void
    {
        if (!is_bool($used)) {
            throw new InvalidDataProvidedException(
                $this->translator->trans('campaign.invalid_value_field_in_row', ['%name%' => 'used', '%row%' => $key])
            );
        }
        if (!$customer) {
            throw new InvalidDataProvidedException(
                $this->translator->trans('campaign.invalid_value_field_in_row', ['%name%' => 'customerId', '%row%' => $key])
            );
        }
        if (!$campaign) {
            throw new InvalidDataProvidedException(
                $this->translator->trans('campaign.invalid_value_field_in_row', ['%name%' => 'campaignId', '%row%' => $key])
            );
        }
        if (count(array_filter($customer->getCampaignPurchases(), function (CampaignPurchase $campaignPurchase) use ($campaign) {
            return $campaignPurchase->getCampaignId()->__toString() === $campaign->getCampaignId()->__toString() && $campaignPurchase->getStatus() === CampaignPurchase::STATUS_ACTIVE && !$campaignPurchase->isUsed();
        })) === 0) {
            throw new NoCouponsLeftException();
        }
        if (!$customer->canUsePurchase(new CustomerCampaignId((string) $campaign->getCampaignId()), $coupon)) {
            throw new InvalidDataProvidedException(
                $this->translator->trans('campaign.invalid_value_field_in_row', ['%name%' => 'code', '%row%' => $key])
            );
        }
    }
}
