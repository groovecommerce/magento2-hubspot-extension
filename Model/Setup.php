<?php
declare(strict_types = 1);

namespace Groove\Hubshoply\Model;

use Groove\Hubshoply\Model\ResourceModel\Token as TokenAlias;
use Groove\Hubshoply\Model\Setup\SetupException;
use Magento\Backend\Model\Auth\Session;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\IntegrationException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Integration\Model\Oauth\Token;
use Magento\User\Model\User;
use Psr\Log\LoggerInterface;

class Setup
{
    private const TABLES = [
        TokenAlias::TABLE_NAME,
        \Groove\Hubshoply\Model\ResourceModel\QueueItem::TABLE_NAME
    ];

    /**
     * @var User
     */
    private $user;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var IntegrationServiceInterface
     */
    private $integrationService;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Token
     */
    private $token;

    /**
     * @var OauthServiceInterface
     */
    private $oauthService;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(
        User $user,
        Session $session,
        IntegrationServiceInterface $integrationService,
        LoggerInterface $logger,
        Config $config,
        Token $token,
        OauthServiceInterface $oauthService,
        DateTime $dateTime,
        ResourceConnection $resource
    ) {
        $this->user = $user;
        $this->session = $session;
        $this->logger = $logger;
        $this->integrationService = $integrationService;
        $this->config = $config;
        $this->token = $token;
        $this->oauthService = $oauthService;
        $this->dateTime = $dateTime;
        $this->resource = $resource;
    }

    public function autoInstall($targetUserId = null, $storeId = null)
    {
        if ($targetUserId === null) {
            $targetUserId = $this->session->getUser() ? $this->session->getUser()->getId() : 0;
        }
        $user = $this->user->load($targetUserId);
        if (!$user->getId()) {
            throw new SetupException(sprintf('Failed to find admin user by ID "%d"', $targetUserId));
        }
        $integration = $this->integrationService->create([
            'name'          => $this->config->getIntegrationName($storeId),
            'endpoint'      => $this->config->getAuthUrl($storeId),
            "resource" => [
                "Magento_Catalog::catalog",
                "Magento_Catalog::catalog_inventory",
                "Magento_Catalog::products",
                "Magento_PricePermissions::read_product_price",
                "Magento_Sales::actions",
                "Magento_Sales::actions_view",
                "Magento_Sales::sales",
                "Magento_Sales::sales_operation",
                "Magento_Sales::sales_order",
            ]
        ]);
        $this->token->createVerifierToken($integration->getConsumerId());
        $consumer = $this->oauthService->loadConsumer($integration->getConsumerId());
        $consumer->setUpdatedAt($this->dateTime->date());
        $consumer->save();
        $this->logger->info(__('HubShop.ly system installation completed.'));

        return $this;
    }

    public function reset($storeId)
    {
        $connection = $this->resource->getConnection();
        try {
            $connection->beginTransaction();
            $integrationName = $this->config->getIntegrationName($storeId);
            $integration = $this->integrationService->findByName($integrationName);
            $this->integrationService->delete($integration->getId());
            $connection->commit();
//            $this->oauthService->deleteIntegrationToken($integration->getConsumerId());
//            $this->oauthService->deleteConsumer($integration->getConsumerId());

            foreach (self::TABLES as $table) {
                $connection->truncateTable($table);
            }
        } catch (IntegrationException $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
