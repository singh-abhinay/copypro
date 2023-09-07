<?php

namespace Enc\CopyPro\Model\Config\Source;

/**
 * Class Options
 * @package Enc\CopyPro\Model\Config\Source
 */
class Options extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * Product Attribute Condition Option(s)
     * @return array
     */
    public function getAllOptions()
    {
        $this->_options = [
            ['label' => __('New'), 'value' => '0'],
            ['label' => __('Used'), 'value' => '1'],
            ['label' => __('Refurbished'), 'value' => '2']
        ];

        return $this->_options;
    }
}