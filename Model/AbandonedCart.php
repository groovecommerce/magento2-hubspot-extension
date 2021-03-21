<?php

namespace Groove\Hubshoply\Model;

use Groove\Hubshoply\Api\Data\AbandonedCartInterface;
use Groove\Hubshoply\Model\ResourceModel\AbandonedCart\Collection;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * @method ResourceModel\AbandonedCart getResource()
 * @method Collection getCollection()
 */
class AbandonedCart extends AbstractModel implements
    AbandonedCartInterface,
    IdentityInterface
{

    const CACHE_TAG = 'groove_hubshoply_abandonedcart';

    /**
     * @var string
     */
    protected $_cacheTag = 'groove_hubshoply_abandonedcart';

    /**
     * @var string
     */
    protected $_eventPrefix = 'groove_hubshoply_abandonedcart';

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * AbandonedCart constructor.
     *
     * @param \Magento\Framework\Model\Context                             $context
     * @param \Magento\Framework\Registry                                  $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null           $resourceCollection
     * @param array                                                        $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        CartRepositoryInterface $cartRepository,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
        $this->cartRepository = $cartRepository;
    }

    /**
     *
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\AbandonedCart::class);
    }

    /**
     * @return string[]
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    /**
     * @param $quote_id
     * @param $store_id
     *
     * @return $this
     */
    public function loadByQuoteStore($quote_id, $store_id)
    {
        $id = $this->getCollection()
            ->addFieldToFilter('quote_id', $quote_id)
            ->addFieldToFilter('store_id', $store_id)
            ->getFirstItem()->getId();

        if ($id) {
            $this->load($id);
        }

        return $this;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPayload(): array
    {
        try {
            $cart = $this->cartRepository->get($this->getData('quote_id'));//get visible products in cart
            $product_ids = [];
            foreach ($cart->getAllVisibleItems() as $item) {
                $product_ids[] = (int)$item->getProductId();
            }
            //craft the payload
            $payload = [
                'quote_id'    => (int)$cart->getId(),
                'email'       => $cart->getCustomerEmail(),
                'created_at'  => date(\DateTime::W3C, strtotime($cart->getCreatedAt())),
                'updated_at'  => date(\DateTime::W3C, strtotime($cart->getUpdatedAt())),
                'total_price' => (string)number_format($cart->getGrandTotal(), 4),
                'product_ids' => $product_ids,
                'qty_in_cart' => $cart->getItemsQty(),
                'currency'    => $cart->getQuoteCurrencyCode()
            ];
        } catch (NoSuchEntityException $e) {
            $payload = [];
        }

        return $payload;
    }
}
