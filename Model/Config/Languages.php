<?php

namespace Apcopay\Magento\Model\Config;

class Languages implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'en', 'label' => __('English')],
            ['value' => 'mt', 'label' => __('Maltese')],
            ['value' => 'it', 'label' => __('Italian')],
            ['value' => 'fr', 'label' => __('French')],
            ['value' => 'de', 'label' => __('German')],
            ['value' => 'es', 'label' => __('Spanish')],
            ['value' => 'hr', 'label' => __('Croatian')],
            ['value' => 'se', 'label' => __('Swedish')],
            ['value' => 'ro', 'label' => __('Romanian')],
            ['value' => 'hu', 'label' => __('Hungarian')],
            ['value' => 'tr', 'label' => __('Turkish')],
            ['value' => 'gr', 'label' => __('Greek')],
            ['value' => 'fi', 'label' => __('Finnish')],
            ['value' => 'dk', 'label' => __('Danish')],
            ['value' => 'pt', 'label' => __('Portuguese')],
            ['value' => 'sb', 'label' => __('Serbian')],
            ['value' => 'si', 'label' => __('Slovenian')],
            ['value' => 'ni', 'label' => __('Dutch')],
            ['value' => 'zh', 'label' => __('Chinese simplified')],
            ['value' => 'no', 'label' => __('Norwegian')],
            ['value' => 'ru', 'label' => __('Russian')],
            ['value' => 'us', 'label' => __('American')],
            ['value' => 'pl', 'label' => __('Polish')],
            ['value' => 'cz', 'label' => __('Chechz')],
            ['value' => 'sk', 'label' => __('Slovak')],
            ['value' => 'bg', 'label' => __('Bulgarian')],
            ['value' => 'jp', 'label' => __('Japanese')]
        ];
    }
}
