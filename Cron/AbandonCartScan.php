<?php
declare(strict_types = 1);

namespace Groove\Hubshoply\Cron;

use Groove\Hubshoply\Model\AbandonedCartFactory;
use Groove\Hubshoply\Model\Config;
use Groove\Hubshoply\Model\ResourceModel\AbandonedCart as ResourcAbandonedCart;
use Groove\Hubshoply\Model\ResourceModel\AbandonedCart\CollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class AbandonCartScan
 *
 * @package Groove\Hubshoply\Cron
 */
class AbandonCartScan
{
    private const BULK_SIZE = 5000;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var int
     */
    private $minutesUntilAbandoned;

    /**
     * @var \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory
     */
    private $quoteCollectionFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var \Groove\Hubshoply\Model\ResourceModel\AbandonedCartFactory
     */
    private $abandonedResourceCartFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ResourcAbandonedCart
     */
    private $abandonedResource;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    private $dateTime;

    /**
     * AbandonCartScan constructor.
     *
     * @param Config                                                     $config
     * @param \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory $quoteCollectionFactory
     * @param StoreManagerInterface                                      $storeManager
     * @param \Groove\Hubshoply\Model\ResourceModel\AbandonedCartFactory $abandonedResourceCartFactory
     * @param LoggerInterface                                            $logger
     * @param int                                                        $minutesUntilAbandoned
     */
    public function __construct(
        Config $config,
        \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory $quoteCollectionFactory,
        StoreManagerInterface $storeManager,
        \Groove\Hubshoply\Model\ResourceModel\AbandonedCartFactory $abandonedResourceCartFactory,
        LoggerInterface $logger,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        $minutesUntilAbandoned = 60
    ) {
        $this->config = $config;
        $this->minutesUntilAbandoned = $minutesUntilAbandoned;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->logger = $logger;
        $this->abandonedResourceCartFactory = $abandonedResourceCartFactory;
        $this->storeManager = $storeManager;
        $this->dateTime = $dateTime;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $stores = $this->storeManager->getStores();
        $storeIds = [];
        foreach ($stores as $store) {
            if ($this->config->isEnabled($store->getId())) {
                $storeIds[] = $store->getId();
            }
        }
        if (!$storeIds) {
            return;
        }
        /**
         * @var $quoteCollection \Magento\Quote\Model\ResourceModel\Quote\Collection
         */
        $quoteCollection = $this->quoteCollectionFactory->create();
        $adapter = $quoteCollection->getConnection();
        $userConfig = $this->config->getUserConfig($store->getId());
        if ($userConfig['minutes_until_abandoned']) {
            $abandonAge = (int)$userConfig['minutes_until_abandoned'];
        } else {
            $abandonAge = $this->minutesUntilAbandoned;
        }
        $now = $this->dateTime->date();
        $upperBound = $adapter->getDateSubSql($adapter->quote($now), $abandonAge, $adapter::INTERVAL_MINUTE);
        $lowerBound = $adapter->getDateSubSql(
            $adapter->quote($now),
            $userConfig['max_cart_age_days'],
            $adapter::INTERVAL_DAY
        );
        $quoteCollection->addFieldToFilter(
            'main_table.updated_at',
            [
                'from' => $lowerBound,
                'to'   => $upperBound
            ]
        )->addFieldToFilter(
            'main_table.store_id',
            ['in' => $storeIds]
        )->addFieldToFilter(
            'converted_at',
            [
                ['eq' => '0000-00-00 00:00:00'],
                ['null' => null],
            ]
        )->addFieldToFilter('customer_email', ['notnull' => ''])
            ->addFieldToFilter('is_active', 1)
            ->addFieldToSelect(['entity_id', 'store_id', 'created_at', 'updated_at']);
        $select = $quoteCollection->getSelect();
        $select->joinLeft(
            ['ac' => $this->getAbandonedResource()->getMainTable()],
            'ac.quote_id = main_table.entity_id',
            ['ac_updated_at' => 'ac.updated_at', 'cart_id', 'enqueued']
        );
        $statement = $adapter->query($select);
        $quoteIds = [];
        $insertData = [];
        try {
            while (($row = $statement->fetch())) {
                $entity = [
                    'store_id'   => $row['store_id'],
                    'quote_id'   => $row['entity_id'],
                    'enqueued'   => false,
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                    'cart_id'    => $row['cart_id']
                ];
                if (!empty($row['cart_id']) && $row['updated_at'] == $row['ac_updated_at']) {
                    $entity['enqueued'] = $row['enqueued'];
                }
                $insertData[] = $entity;
                $quoteIds[] = $row['entity_id'];
                if (count($insertData) == self::BULK_SIZE) {
                    $this->getAbandonedResource()->massInsertUpdate($insertData);
                    $insertData = [];
                }
            }
            if ($insertData) {
                $this->getAbandonedResource()->massInsertUpdate($insertData);
            }
            // @todo consider process deferment or other refactor
            $this->getAbandonedResource()->deleteExcept($quoteIds);
        } catch (\Zend_Db_Statement_Exception $e) {
            $this->logger->error(
                sprintf('Error while updating abandonedcart carts. %s', $e->getMessage())
            );
        }

        $this->logger->info(sprintf('Queued %d abandoned carts.', count($quoteIds)));
    }

    /**
     * @return ResourcAbandonedCart
     */
    private function getAbandonedResource(): ResourcAbandonedCart
    {
        if (!isset($this->abandonedResource)) {
            $this->abandonedResource = $this->abandonedResourceCartFactory->create();
        }

        return $this->abandonedResource;
    }
}
