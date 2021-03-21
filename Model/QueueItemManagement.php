<?php
declare(strict_types = 1);

namespace Groove\Hubshoply\Model;

use Groove\Hubshoply\Api\Data\QueueItemInterface;
use Groove\Hubshoply\Api\QueueItemManagementInterface;
use Groove\Hubshoply\Model\ResourceModel\QueueItem as QueueItemResource;
use Groove\Hubshoply\Model\ResourceModel\QueueItem\Collection;
use Groove\Hubshoply\Model\ResourceModel\QueueItem\CollectionFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Groove\Hubshoply\Model\ResourceModel\AbandonedCart\CollectionFactory as AbandonedCartCollectionFactory;
use Groove\Hubshoply\Model\ResourceModel\AbandonedCart\Collection as AbandonedCartCollection;

/**
 * Class QueueItemManagement
 *
 * @package Groove\Hubshoply\Model
 */
class QueueItemManagement implements QueueItemManagementInterface
{
    /**
     * @var QueueItemResource
     */
    private $queueItemResource;

    /**
     * @var QueueItemFactory
     */
    private $queueItemFactory;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var AbandonedCartCollectionFactory
     */
    private $abandonedCartCollectionFactory;

    /**
     * QueueItemManagement constructor.
     *
     * @param QueueItemResource $queueItemResource
     * @param QueueItemFactory  $queueItemFactory
     * @param Json              $json
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        QueueItemResource $queueItemResource,
        QueueItemFactory $queueItemFactory,
        Json $json,
        CollectionFactory $collectionFactory,
        AbandonedCartCollectionFactory $abandonedCartCollectionFactory
    ) {
        $this->queueItemResource = $queueItemResource;
        $this->queueItemFactory = $queueItemFactory;
        $this->json = $json;
        $this->collectionFactory = $collectionFactory;
        $this->abandonedCartCollectionFactory = $abandonedCartCollectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function create(string $entity, string $event, array $payload, string $storeId): QueueItemInterface
    {
        /**
         * @var $queueItem QueueItem
         */
        $queueItem = $this->queueItemFactory->create();
        $queueItem->setEventEntity($entity);
        $queueItem->setEventType($event);
        $queueItem->setStoreId($storeId);
        $queueItem->setPayloadJson($this->json->serialize($payload));
        $this->queueItemResource->save($queueItem);

        return $queueItem;
    }

    /**
     * @param string $from
     * @param string $to
     *
     * @return mixed|void
     */
    public function delete(string $from, string $to)
    {
        /**
         * @var $collection Collection
         */
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(QueueItem::FIELD_ID, ['from' => $from, 'to' => $to]);
        $collection->walk('delete');
    }

    /**
     *
     */
    public function queueCarts()
    {
        /**
         * @var $abandonedCartCollection AbandonedCartCollection
         */
        $abandonedCartCollection = $this->abandonedCartCollectionFactory->create();
        $abandonedCartCollection->addFieldToFilter('enqueued', false);
        $idsToUnEnQueue = [];
        foreach ($abandonedCartCollection->getItems() as $abandonedCart) {
            $payload = $abandonedCart->getPayload();
            if (!$payload) {
                continue;
            }
            $this->create(
                'cart',
                'abandoned',
                $payload,
                $abandonedCart->getStoreId()
            );
            $idsToUnEnQueue[] = $abandonedCart->getCartId();
        }
        if ($idsToUnEnQueue) {
            $abandonedCartResource = $abandonedCartCollection->getResource();
            $abandonedCartResource->unEnqueue($idsToUnEnQueue);
        }
    }
}
