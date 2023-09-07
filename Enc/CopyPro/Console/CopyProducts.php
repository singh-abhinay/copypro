<?php

namespace Enc\CopyPro\Console;

use Enc\CopyPro\Helper\Data;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State as AppState;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class CopyProducts
 * @package Enc\CopyPro\Console
 */
class CopyProducts extends Command
{
    /**
     * Collection LIMIT for products
     */
    const LIMIT = 'limit';

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * CopyProducts constructor.
     * @param ResourceConnection $resource
     * @param AppState\Proxy $appState
     * @param Data $helperData
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ResourceConnection $resource,
        AppState\Proxy $appState,
        Data $helperData,
        StoreManagerInterface $storeManager
    )
    {
        parent::__construct();
        $this->_resource = $resource;
        $this->_appState = $appState;
        $this->_storeManager = $storeManager;
        $this->helperData = $helperData;
    }

    /**
     * Configure CLI Command For Copy Product(s)
     */
    protected function configure()
    {
        $this->setName('enc:copyproducts');
        $this->setDescription('Copy All Products');
        $this->addOption(
            self::LIMIT,
            null,
            InputOption::VALUE_REQUIRED,
            'Limit'
        );
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $productLimit = 20;
        $this->_appState->setAreaCode('frontend');
        if ($input->getOption(self::LIMIT)) {
            $productLimit = $input->getOption(self::LIMIT);
        }
        $collection = $this->helperData->getCollectionForCli($productLimit);
        foreach ($collection as $product):
            $productSku = $product->getSku();
            $output->writeln('<error>' . $productSku . '</error>');
            $usedSku = str_replace("NEW", "USED", $productSku);
            if ($this->helperData->getCheckSkuStatus($usedSku)) {
                $output->writeln('<error>' . $productSku . ' exist with copy product.</error>');
                continue;
            }
            $copyResponse = $this->helperData->createCopyUsedProduct($product);
            $output->writeln('<info>' . $copyResponse . '</info>');
            $refurbishedSku = str_replace("NEW", "REFURBISHED", $productSku);
            if ($this->helperData->getCheckSkuStatus($refurbishedSku)) {
                $output->writeln('<error>' . $productSku . ' exist with refurbished product.</error>');
                continue;
            }
            $refurbishedResponse = $this->helperData->createCopyRefurbishedProduct($product);
            $output->writeln('<info>' . $refurbishedResponse . '</info>');
        endforeach;
    }
}