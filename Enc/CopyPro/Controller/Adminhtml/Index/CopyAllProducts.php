<?php

namespace Enc\CopyPro\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Enc\CopyPro\Helper\Data;
use Magento\AsynchronousOperations\Api\Data\OperationInterface;
use Magento\AsynchronousOperations\Api\Data\OperationInterfaceFactory;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Bulk\BulkManagementInterface;
use Magento\Framework\DataObject\IdentityGeneratorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\UrlInterface;

/**
 * Class CopyAllProducts
 * @package Enc\CopyPro\Controller\Adminhtml\Index
 */
class CopyAllProducts extends Action
{

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var BulkManagementInterface
     */

    private $bulkManagement;


    /**
     * @var OperationInterfaceFactory
     */

    private $operationFactory;


    /**
     * @var IdentityGeneratorInterface
     */

    private $identityService;


    /**
     * @var UrlInterface
     */

    private $urlBuilder;


    /**
     * @var UserContextInterface
     */

    private $userContext;


    /**
     * @var JsonHelper
     */

    private $jsonHelper;

    /**
     * CopyAllProducts constructor.
     * @param Context $context
     * @param Data $helperData
     * @param BulkManagementInterface $bulkManagement
     * @param OperationInterfaceFactory $operationFactory
     * @param IdentityGeneratorInterface $identityService
     * @param UserContextInterface $userContextInterface
     * @param UrlInterface $urlBuilder
     * @param JsonHelper $jsonHelper
     */
    public function __construct(
        Context $context,
        Data $helperData,
        BulkManagementInterface $bulkManagement,
        OperationInterfaceFactory $operationFactory,
        IdentityGeneratorInterface $identityService,
        UserContextInterface $userContextInterface,
        UrlInterface $urlBuilder,
        JsonHelper $jsonHelper
    )
    {
        $this->helperData = $helperData;
        $this->userContext = $userContextInterface;
        $this->bulkManagement = $bulkManagement;
        $this->operationFactory = $operationFactory;
        $this->identityService = $identityService;
        $this->urlBuilder = $urlBuilder;
        $this->jsonHelper = $jsonHelper;
        parent::__construct($context);
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Redirect|\Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $ids = $this->helperData->getCopyProductList()->getAllIds();
            $bulkUuid = $this->identityService->generateId();
            $bulkDescription = 'Copy All Products';
            $operations = [];
            $serializedData = [
                'entity_id' => $ids
            ];
            $data = [
                'data' => [
                    'bulk_uuid' => $bulkUuid,
                    'topic_name' => 'copy.products',
                    'serialized_data' => $this->jsonHelper->jsonEncode($serializedData),
                    'status' => OperationInterface::STATUS_TYPE_OPEN,
                ]
            ];
            /** @var OperationInterface $operation */
            $operation = $this->operationFactory->create($data);
            $operations[] = $operation;
            $userId = $this->userContext->getUserId();
            $result = $this->bulkManagement->scheduleBulk($bulkUuid, $operations, $bulkDescription, $userId);
            if (!$result) {
                throw new LocalizedException(
                    __('Something went wrong while processing the request.')
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('catalog/product/index');
    }
}