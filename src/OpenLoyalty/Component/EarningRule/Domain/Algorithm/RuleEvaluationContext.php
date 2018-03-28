<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Component\EarningRule\Domain\Algorithm;

use OpenLoyalty\Component\Transaction\Domain\ReadModel\TransactionDetails;

/**
 * Class RuleEvaluationContext.
 */
class RuleEvaluationContext implements RuleEvaluationContextInterface
{
    /** @var array */
    private $products;

    /**
     * @var TransactionDetails
     */
    private $transaction;

    /**
     * @var array
     */
    private $earningRuleNames = [];

    /**
     * RuleEvaluationContext constructor.
     *
     * @param TransactionDetails $transaction
     */
    public function __construct(TransactionDetails $transaction)
    {
        $this->products = [];
        $this->transaction = $transaction;
    }

    /**
     * {@inheritdoc}
     */
    public function addEarningRuleName($earningRuleId, $earningRuleName)
    {
        $this->earningRuleNames[$earningRuleId] = $earningRuleName;
    }

    /**
     * {@inheritdoc}
     */
    public function getEarningRuleNames()
    {
        return implode(', ', $this->earningRuleNames);
    }

    /**
     * {@inheritdoc}
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * {@inheritdoc}
     */
    public function getProductPoints($sku)
    {
        if (!array_key_exists($sku, $this->products)) {
            return 0;
        }

        return round($this->products[$sku], 2);
    }

    /**
     * {@inheritdoc}
     */
    public function addProductPoints($sku, $points)
    {
        $current = $this->getProductPoints($sku);
        $this->setProductPoints($sku, $current + $points);
    }

    /**
     * {@inheritdoc}
     */
    public function setProductPoints($sku, $points)
    {
        if (!array_key_exists($sku, $this->products)) {
            $this->products[$sku] = 0;
        }

        $this->products[$sku] = $points;
    }

    /**
     * {@inheritdoc}
     */
    public function getProducts()
    {
        return $this->products;
    }
}
