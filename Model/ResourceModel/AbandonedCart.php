<?php

namespace Groove\Hubshoply\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AbandonedCart extends AbstractDb
{
    /**
     *
     */
    protected function _construct()
    {
        $this->_init('hubshoply_abandonedcart', 'cart_id');
    }

    /**
     * @param array $quoteIds
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteExcept(array $quoteIds)
    {
        $this->getConnection()->delete(
            $this->getMainTable(),
            empty($quoteIds) ? [] : ['quote_id NOT IN (?)' => $quoteIds]
        );
    }

    /**
     * @param array $rows
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function massInsertUpdate(array $rows)
    {
        $this->getConnection()->insertOnDuplicate($this->getMainTable(), $rows);
    }

    /**
     * @param array $ids
     */
    public function unEnqueue(array $ids)
    {
        $this->getConnection()->update($this->getMainTable(), ['enqueued' => true], $where = ['cart_id IN(?)' => $ids]);
    }
}
