<?php

namespace Groove\Hubshoply\Model\ResourceModel\AbandonedCart;

use Groove\Hubshoply\Model\AbandonedCart;
use Groove\Hubshoply\Model\ResourceModel\AbandonedCart as AbandonedCartResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    /**
     * @var string
     */
    protected $_idFieldName = 'cart_id';

    /**
     *
     */
    protected function _construct()
    {
        $this->_init(AbandonedCart::class, AbandonedCartResource::class);
    }

}
