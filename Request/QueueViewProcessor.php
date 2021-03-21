<?php
declare(strict_types = 1);

namespace Groove\Hubshoply\Request;

use Groove\Hubshoply\Model\ResourceModel\QueueItem\Collection;
use Magento\Framework\App\RequestInterface;

class QueueViewProcessor
{
    /**
     * @param RequestInterface $request
     * @param Collection       $collection
     *
     * @return Collection
     */
    public function process(RequestInterface $request, Collection $collection): Collection
    {
        if ($request->getParam('first')) {
            $collection
                ->setOrder('created_at', 'ASC')
                ->setPageSize($request->getParam('first'));
        } else {
            if ($request->getParam('last')) {
                $collection
                    ->setOrder('created_at', 'DESC')
                    ->setPageSize($request->getParam('last'));
            } else {
                if ($limit = $request->getParam('limit')) {
                    $limit = explode(',', $limit);
                    $collection->setOrder('created_at', 'ASC');

                    $collection->getSelect()->limit($limit[1], $limit[0]);
                }
            }
        }

        if ($request->getParam('type')) {
            $collection->addFieldToFilter('event_type', $request->getParam('type'));
        }

        if ($request->getParam('entity')) {
            $collection->addFieldToFilter('event_entity', $request->getParam('entity'));
        }

        if ($request->getParam('store')) {
            $collection->addFieldToFilter('store_id', $request->getParam('store'));
        }

        return $collection;
    }
}
