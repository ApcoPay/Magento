<?php

namespace Apcopay\Magento\Model\Config;

class TransactionTypes implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 1, 'label' => __('Purchase')], 
            ['value' => 4, 'label' => __('Authorisation')]
        ];
    }
}
