<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Component\Campaign\Infrastructure\Repository;

use OpenLoyalty\Component\Campaign\Domain\ReadModel\CampaignBoughtRepository;
use OpenLoyalty\Component\Core\Infrastructure\Repository\OloyElasticsearchRepository;

/**
 * Class SegmentedCustomersElasticsearchRepository.
 */
class CampaignBoughtElasticsearchRepository extends OloyElasticsearchRepository implements CampaignBoughtRepository
{
    /**
     * {@inheritdoc}
     */
    public function findByTransactionIdAndCustomerId(string $transactionId, string $customerId): array
    {
        $query = [
            'bool' => [
                'must' => [
                    [
                        'match' => [
                            'customerId' => $customerId,
                        ],
                    ],
                    [
                        'match' => [
                            'transactionId' => $transactionId,
                        ],
                    ],
                ],
            ],
        ];

        return $this->query($query);
    }

    /**
     * {@inheritdoc}
     */
    public function findByCustomerIdAndUsed(string $customerId, bool $used): array
    {
        $filter = [
            'must_not' => [
                [
                    'term' => [
                        'used' => !$used,
                    ],
                ],
            ],
        ];

        if ($used === true) {
            $filter = [
                'must' => [
                    [
                        'term' => [
                            'used' => $used,
                        ],
                    ],
                ],
            ];
        }
        $filter['must'][]['term'] = ['customerId' => $customerId];

        $query = [
            'bool' => $filter,
        ];

        return $this->query($query);
    }

    /**
     * {@inheritdoc}
     */
    public function findByCustomerId(string $customerId): array
    {
        $query = [
            'bool' => [
                'must' => [
                    [
                        'term' => [
                            'customerId' => $customerId,
                        ],
                    ],
                ],
            ],
        ];

        return $this->query($query);
    }
}
