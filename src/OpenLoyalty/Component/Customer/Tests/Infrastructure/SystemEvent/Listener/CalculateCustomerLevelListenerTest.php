<?php

namespace OpenLoyalty\Component\Customer\Tests\Infrastructure\SystemEvent\Listener;

use Broadway\CommandHandling\CommandBus;
use Broadway\EventDispatcher\EventDispatcher;
use OpenLoyalty\Bundle\UserBundle\Model\CustomerStatus;
use OpenLoyalty\Bundle\UserBundle\Status\CustomerStatusProvider;
use OpenLoyalty\Component\Account\Domain\AccountId;
use OpenLoyalty\Component\Account\Domain\SystemEvent\AccountCreatedSystemEvent;
use OpenLoyalty\Component\Account\Domain\SystemEvent\AvailablePointsAmountChangedSystemEvent;
use OpenLoyalty\Component\Customer\Domain\Command\MoveCustomerToLevel;
use OpenLoyalty\Component\Customer\Domain\CustomerId;
use OpenLoyalty\Component\Customer\Domain\LevelId;
use OpenLoyalty\Component\Customer\Domain\LevelIdProvider;
use OpenLoyalty\Component\Customer\Domain\ReadModel\CustomerDetails;
use OpenLoyalty\Component\Customer\Domain\ReadModel\CustomerDetailsRepository;
use OpenLoyalty\Component\Customer\Domain\SystemEvent\CustomerRemovedManuallyLevelSystemEvent;
use OpenLoyalty\Component\Customer\Infrastructure\SystemEvent\Listener\CalculateCustomerLevelListener;
use OpenLoyalty\Component\Level\Domain\Level;
use OpenLoyalty\Component\Customer\Domain\LevelId as CustomerLevelId;
use OpenLoyalty\Component\Level\Domain\LevelRepository;
use OpenLoyalty\Component\Level\Domain\Model\Reward;
use OpenLoyalty\Component\Transaction\Domain\SystemEvent\CustomerAssignedToTransactionSystemEvent;
use OpenLoyalty\Component\Transaction\Domain\TransactionId;
use OpenLoyalty\Component\Customer\Infrastructure\ExcludeDeliveryCostsProvider;
use OpenLoyalty\Component\Customer\Infrastructure\TierAssignTypeProvider;
use OpenLoyalty\Component\Transaction\Domain\CustomerId as TransactionCustomerId;
use OpenLoyalty\Component\Account\Domain\CustomerId as AccountCustomerId;
use OpenLoyalty\Component\Level\Domain\LevelId as LevelLevelId;

/**
 * Class CalculateCustomerLevelListenerTest.
 */
class CalculateCustomerLevelListenerTest extends \PHPUnit_Framework_TestCase
{
    const LEVEL_WITH_REWARD_10_FROM_0 = '00000000-0000-0000-0000-000000000000';
    const LEVEL_WITH_REWARD_200_FROM_20 = '00000000-0000-0000-0000-000000000001';
    const LEVEL_WITH_REWARD_300_FROM_30 = '00000000-0000-0000-0000-000000000002';

    /**
     * @test
     */
    public function it_moves_customer_to_correct_level_after_remove_manually_level()
    {
        $customerId = '00000000-0000-0000-0000-000000000000';
        $levelId = new LevelLevelId('00000000-0000-0000-0000-000000000003');
        $level = new Level($levelId, 'test', 10);

        $commandBus = $this->getMockBuilder(CommandBus::class)->getMock();
        $commandBus->expects($this->once())->method('dispatch')->with(
            $this->equalTo(
                new MoveCustomerToLevel(
                    new CustomerId($customerId),
                    new LevelId($levelId->__toString()),
                    true,
                    true
                )
            )
        );

        $listener = new CalculateCustomerLevelListener(
            $this->getLevelIdProvider($level),
            $this->getCustomerDetailsRepository(),
            $commandBus,
            $this->getTierTypeAssignProvider(TierAssignTypeProvider::TYPE_TRANSACTIONS),
            $this->getExcludeDeliveryCostsProvider(false),
            $this->getLevelRepository(),
            $this->getDispatcher(),
            $this->getCustomerStatusProvider(100)
        );

        $listener->handle(new CustomerRemovedManuallyLevelSystemEvent(
            new CustomerId($customerId)
        ));
    }

    /**
     * @test
     */
    public function it_moves_customer_to_correct_level_by_transaction()
    {
        $customerId = '00000000-0000-0000-0000-000000000000';
        $levelId = new LevelLevelId('00000000-0000-0000-0000-000000000003');
        $level = new Level($levelId, 'test', 10);

        $commandBus = $this->getMockBuilder(CommandBus::class)->getMock();
        $commandBus->expects($this->once())->method('dispatch')->with(
            $this->equalTo(
                new MoveCustomerToLevel(
                    new CustomerId($customerId),
                    new LevelId($levelId->__toString()),
                    false,
                    false
                )
            )
        );

        $listener = new CalculateCustomerLevelListener(
            $this->getLevelIdProvider($level),
            $this->getCustomerDetailsRepository(),
            $commandBus,
            $this->getTierTypeAssignProvider(TierAssignTypeProvider::TYPE_TRANSACTIONS),
            $this->getExcludeDeliveryCostsProvider(false),
            $this->getLevelRepository(),
            $this->getDispatcher(),
            $this->getCustomerStatusProvider()
        );

        $listener->handle(new CustomerAssignedToTransactionSystemEvent(
            new TransactionId('00000000-0000-0000-0000-000000000000'),
            new TransactionCustomerId($customerId),
            20,
            20
        ));
    }

    /**
     * @test
     * @dataProvider testLevelsWithAssigned
     *
     * @param CustomerLevelId $currentLevelId
     * @param CustomerLevelId $assignedLevel
     * @param $transactionAmount
     * @param CustomerLevelId|null $resultLevelId
     */
    public function it_moves_customer_to_correct_level_by_transaction_with_manually_assigned_level(
        CustomerLevelId $currentLevelId,
        CustomerLevelId $assignedLevel,
        $transactionAmount,
        CustomerLevelId $resultLevelId = null
    ) {
        $levels = $this->getSampleLevels();
        $levelsRepo = $this->getLevelRepositoryWithArray($levels);

        $customerId = '00000000-0000-0000-0000-000000000000';

        $commandBus = $this->getMockBuilder(CommandBus::class)->getMock();
        if ($resultLevelId == null) {
            $commandBus->expects($this->never())->method('dispatch');
        } else {
            $commandBus->expects($this->once())->method('dispatch')->with(
                $this->equalTo(
                    new MoveCustomerToLevel(
                        new CustomerId($customerId),
                        $resultLevelId,
                        false,
                        false
                    )
                )
            );
        }

        $listener = new CalculateCustomerLevelListener(
            $this->getLevelIdProvider($levels),
            $this->getCustomerDetailsRepository($currentLevelId, $assignedLevel),
            $commandBus,
            $this->getTierTypeAssignProvider(TierAssignTypeProvider::TYPE_TRANSACTIONS),
            $this->getExcludeDeliveryCostsProvider(false),
            $levelsRepo,
            $this->getDispatcher(),
            $this->getCustomerStatusProvider()
        );

        $listener->handle(new CustomerAssignedToTransactionSystemEvent(
            new TransactionId('00000000-0000-0000-0000-000000000000'),
            new TransactionCustomerId($customerId),
            $transactionAmount,
            $transactionAmount
        ));
    }

    /**
     * @test
     */
    public function it_moves_customer_to_correct_level_on_registration()
    {
        $customerId = '00000000-0000-0000-0000-000000000000';
        $levelId = new LevelLevelId('00000000-0000-0000-0000-000000000003');
        $level = new Level($levelId, 'test', 0);

        $commandBus = $this->getMockBuilder(CommandBus::class)->getMock();
        $commandBus->expects($this->once())->method('dispatch')->with(
            $this->equalTo(
                new MoveCustomerToLevel(
                    new CustomerId($customerId),
                    new LevelId($levelId->__toString()),
                    false,
                    false
                )
            )
        );

        $listener = new CalculateCustomerLevelListener(
            $this->getLevelIdProvider($level),
            $this->getCustomerDetailsRepository(),
            $commandBus,
            $this->getTierTypeAssignProvider(TierAssignTypeProvider::TYPE_TRANSACTIONS),
            $this->getExcludeDeliveryCostsProvider(false),
            $this->getLevelRepository(),
            $this->getDispatcher(),
            $this->getCustomerStatusProvider()
        );

        $listener->handle(new AccountCreatedSystemEvent(
            new AccountId('00000000-0000-0000-0000-000000000000'),
            new AccountCustomerId($customerId)
        ));
    }

    /**
     * @test
     */
    public function it_moves_customer_to_correct_level_by_points()
    {
        $customerId = '00000000-0000-0000-0000-000000000000';
        $levelId = new LevelLevelId('00000000-0000-0000-0000-000000000003');
        $level = new Level($levelId, 'test', 10);

        $commandBus = $this->getMockBuilder(CommandBus::class)->getMock();
        $commandBus->expects($this->once())->method('dispatch')->with(
            $this->equalTo(
                new MoveCustomerToLevel(
                    new CustomerId($customerId),
                    new LevelId($levelId->__toString()),
                    false,
                    false
                )
            )
        );

        $listener = new CalculateCustomerLevelListener(
            $this->getLevelIdProvider($level),
            $this->getCustomerDetailsRepository(),
            $commandBus,
            $this->getTierTypeAssignProvider(TierAssignTypeProvider::TYPE_POINTS),
            $this->getExcludeDeliveryCostsProvider(true),
            $this->getLevelRepository(),
            $this->getDispatcher(),
            $this->getCustomerStatusProvider()
        );
        $listener->handle(new AvailablePointsAmountChangedSystemEvent(
            new AccountId('00000000-0000-0000-0000-000000000000'),
            new AccountCustomerId($customerId),
            20,
            20
        ));
    }

    protected function getTierTypeAssignProvider($type)
    {
        $mock = $this->getMockBuilder(TierAssignTypeProvider::class)->getMock();
        $mock->method('getType')->willReturn($type);

        return $mock;
    }

    protected function getCustomerDetailsRepository(CustomerLevelId $currentLevelId = null, CustomerLevelId $assignedLevelId = null)
    {
        $repo = $this->getMockBuilder(CustomerDetailsRepository::class)->getMock();
        $repo->method('find')->with($this->isType('string'))->willReturnCallback(function ($id) use ($currentLevelId, $assignedLevelId) {
            $customer = $this->getMockBuilder(CustomerDetails::class);
            $customer->disableOriginalConstructor();
            $customer = $customer->getMock();
            $customer->method('getCustomerId')->willReturn(new CustomerId($id));
            $customer->method('getLevelId')->willReturn($currentLevelId);
            $customer->method('getManuallyAssignedLevelId')->willReturn($assignedLevelId);

            return $customer;
        });

        return $repo;
    }

    protected function getLevelIdProvider($levels)
    {
        if (!is_array($levels)) {
            $levels = [$levels];
        }
        $repo = $this->getMockBuilder(LevelIdProvider::class)->getMock();
        $repo->method('findLevelIdByConditionValueWithTheBiggestReward')
            ->with($this->greaterThanOrEqual(0))
            ->will($this->returnCallback(function ($conditionValue) use ($levels) {
                $current = null;
                if (count($levels) == 1) {
                    $level = reset($levels);
                    if ($level->getConditionValue() <= $conditionValue) {
                        return $level->getLevelId()->__toString();
                    } else {
                        return;
                    }
                }
                /** @var Level $level */
                foreach ($levels as $level) {
                    if ($level->getConditionValue() <= $conditionValue
                        && (!$current || $level->getReward()->getValue() > $current->getReward()->getValue())
                    ) {
                        $current = $level;
                    }
                }

                return $current ? $current->getLevelId()->__toString() : null;
            }));

        return $repo;
    }

    protected function getExcludeDeliveryCostsProvider($returnValue)
    {
        $mock = $this->getMockBuilder(ExcludeDeliveryCostsProvider::class)->getMock();
        $mock->method('areExcluded')->willReturn($returnValue);

        return $mock;
    }

    protected function getDispatcher()
    {
        $mock = $this->getMockBuilder(EventDispatcher::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $mock;
    }

    protected function getLevelRepository()
    {
        $mock = $this->getMockBuilder(LevelRepository::class)
        ->disableOriginalConstructor()
        ->getMock();
        $levelId = new LevelLevelId('00000000-0000-0000-0000-000000000003');
        $mock
            ->method('byId')
            ->will($this->returnValue(new Level($levelId, 'abcd', 20)));

        return $mock;
    }

    protected function getLevelRepositoryWithArray($levels)
    {
        $mock = $this->getMockBuilder(LevelRepository::class)
        ->disableOriginalConstructor()
        ->getMock();
        $mock
            ->method('byId')
            ->with($this->isInstanceOf(LevelLevelId::class))
            ->will($this->returnCallback(function (LevelLevelId $id) use ($levels) {
                if (isset($levels[$id->__toString()])) {
                    return $levels[$id->__toString()];
                }

                return;
            }));

        return $mock;
    }

    /**
     * @param float $points
     *
     * @return CustomerStatusProvider
     */
    protected function getCustomerStatusProvider(float $points = 0)
    {
        $mock = $this->getMockBuilder(CustomerStatusProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock
            ->method('getStatus')
            ->with($this->isInstanceOf(CustomerId::class))
            ->will($this->returnCallback(function (CustomerId $id) use ($points) {
                $status = new CustomerStatus($id);
                $status->setPoints($points);

                return $status;
            }));

        return $mock;
    }

    public function testLevelsWithAssigned()
    {
        return [
            [
                new CustomerLevelId(static::LEVEL_WITH_REWARD_200_FROM_20),
                new CustomerLevelId(static::LEVEL_WITH_REWARD_200_FROM_20),
                40,
                new CustomerLevelId(static::LEVEL_WITH_REWARD_300_FROM_30),
            ],
            [
                new CustomerLevelId(static::LEVEL_WITH_REWARD_300_FROM_30),
                new CustomerLevelId(static::LEVEL_WITH_REWARD_200_FROM_20),
                0,
                new CustomerLevelId(static::LEVEL_WITH_REWARD_200_FROM_20),
            ],
            [
                new CustomerLevelId(static::LEVEL_WITH_REWARD_200_FROM_20),
                new CustomerLevelId(static::LEVEL_WITH_REWARD_200_FROM_20),
                21,
                null, // do not change level
            ],
            [
                new CustomerLevelId(static::LEVEL_WITH_REWARD_300_FROM_30),
                new CustomerLevelId(static::LEVEL_WITH_REWARD_10_FROM_0),
                21,
                new CustomerLevelId(static::LEVEL_WITH_REWARD_200_FROM_20),
            ],
        ];
    }

    /**
     * @return mixed
     */
    protected function getSampleLevels()
    {
        $level = [];
        $level[static::LEVEL_WITH_REWARD_10_FROM_0] = new Level(
            new LevelLevelId(static::LEVEL_WITH_REWARD_10_FROM_0),
            'level_0',
            0
        );
        $level[static::LEVEL_WITH_REWARD_10_FROM_0]->setReward(new Reward('level_0_reward', 10, 'level'));

        $level[static::LEVEL_WITH_REWARD_200_FROM_20] = new Level(
            new LevelLevelId(static::LEVEL_WITH_REWARD_200_FROM_20),
            'level_1',
            20
        );
        $level[static::LEVEL_WITH_REWARD_200_FROM_20]->setReward(new Reward('level_1_reward', 200, 'level'));

        $level[static::LEVEL_WITH_REWARD_300_FROM_30] = new Level(
            new LevelLevelId(static::LEVEL_WITH_REWARD_300_FROM_30),
            'level_2',
            30
        );
        $level[static::LEVEL_WITH_REWARD_300_FROM_30]->setReward(new Reward('level_2_reward', 300, 'level'));

        return $level;
    }
}
