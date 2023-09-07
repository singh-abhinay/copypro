<?php

namespace Enc\CopyPro\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product;
use \Magento\Framework\App\State;

/**
 * Class Data
 * @package Enc\CopyPro\Helper
 */
class Data extends AbstractHelper
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var \Magento\Catalog\Model\Product\Copier
     */
    protected $copier;

    /**
     * @var Product
     */
    protected $product;

    /**
     * Data constructor.
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param Product\Copier $copier
     * @param Product $product
     * @param State $state
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        \Magento\Catalog\Model\Product\Copier $copier,
        Product $product,
        \Magento\Framework\App\State $state,
        \Psr\Log\LoggerInterface $logger
    )
    {
        $this->collectionFactory = $collectionFactory;
        $this->product = $product;
        $this->logger = $logger;
        $this->copier = $copier;
        $this->state = $state;
        parent::__construct($context);
    }

    /**
     * Getting list of products for copy products
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getCopyProductList()
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect('*')->addAttributeToFilter('type_id', array('eq' => 'simple'))
            ->addFieldToFilter(array(array('attribute' => 'condition', 'eq' => 'new')))
            ->addAttributeToFilter('name', array(
                array('like' => '%New%')
            ))->addAttributeToFilter('sku', array(
                array('like' => '%NEW%')
            ))->addAttributeToFilter('url_key', array(
                array('like' => '%new%')
            ))->addAttributeToFilter('meta_title', array(
                array('like' => '%new%')
            ));
        return $collection;
    }

    /**
     * Using product Id(s) to get Collection and verify, create copy products
     * @param $ids
     */
    public function copyProduct($ids)
    {
        try {
            $collection = $this->collectionFactory->create()
                ->addAttributeToSelect('*');
            $collection->addFieldToFilter('entity_id', array('in' => $ids));

            foreach ($collection as $product):
                $sku = $product->getSku();
                $usedSku = str_replace("NEW", "USED", $sku);
                if ($this->product->getIdBySku($usedSku)) {
                    $this->logger->info('Product already used with given SKU: ' . $sku);
                    continue;
                }
                $this->logger->info('New used product is start processing');
                $this->createCopyUsedProduct($product);
                $refurbishedSku = str_replace("NEW", "REFURBISHED", $sku);
                if ($this->product->getIdBySku($refurbishedSku)) {
                    $this->logger->info('Product already refurbished with given SKU: ' . $sku);
                    continue;
                }
                $this->logger->info('New refurbished product is start processing');
                $this->createCopyRefurbishedProduct($product);
            endforeach;
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * Creating RefurbishedProduct
     * @param $product
     * @return string
     */
    public function createCopyRefurbishedProduct($product)
    {
        try {
            $oldId = $product->getId();
            $urlKey = $product->getUrlKey();
            $oldName = $product->getName();
            $oldSku = $product->getSku();
            $oldTitle = $product->getMetaTitle();
            $oldProduct = $this->product->load($oldId);
            $newSku = str_replace("NEW", "REFURBISHED", $oldSku);
            $newUrlKey = str_replace("new", "refurbished", $urlKey);
            $newMetaTitle = str_replace("new", "refurbished", $oldTitle);
            $newName = str_replace("New", "refurbished", $oldName);
            $newProduct = $this->copier->copy($oldProduct);
            $newProduct->setStoreId(0);
            $newProduct->setSku($newSku);
            $newProduct->setUrlKey($newUrlKey);
            $newProduct->setData('meta_title', $newMetaTitle);
            $newProduct->setName($newName);
            $newProduct->setData('condition', 2);
            $newProduct->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
            $newProduct->save();
            $this->logger->info('Successfully created a copy of refurbished product with SKU ' . $newProduct->getSku());
            return 'New refurbished product created with SKU: ' . $newProduct->getSku();
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * Creating UsedProduct
     * @param $product
     * @return string
     */
    public function createCopyUsedProduct($product)
    {
        try {
            $oldId = $product->getId();
            $oldName = $product->getName();
            $urlKey = $product->getUrlKey();
            $oldTitle = $product->getMetaTitle();
            $oldSku = $product->getSku();
            $oldProduct = $this->product->load($oldId);
            $newSku = str_replace("NEW", "USED", $oldSku);
            $newName = str_replace("New", "used", $oldName);
            $newMetaTitle = str_replace("new", "used", $oldTitle);
            $newUrlKey = str_replace("new", "used", $urlKey);
            $newProduct = $this->copier->copy($oldProduct);
            $newProduct->setName($newName);
            $newProduct->setSku($newSku);
            $newProduct->setStoreId(0);
            $newProduct->setUrlKey($newUrlKey);
            $newProduct->setData('condition', 1);
            $newProduct->setData('meta_title', $newMetaTitle);
            $newProduct->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
            $newProduct->save();
            $this->logger->info('Successfully created a copy of used product with SKU ' . $newProduct->getSku());
            return 'New refurbished product created with SKU: ' . $newProduct->getSku();
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * Checking SKU exist or not
     * @param $sku
     * @return int
     */
    public function getCheckSkuStatus($sku)
    {
        return $this->product->getIdBySku($sku);
    }

    /**
     * Getting Product Collection with limit for CLI copy products
     * @param $limit
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getCollectionForCli($limit)
    {
        return $this->getCopyProductList()->setPageSize($limit);
    }
}
