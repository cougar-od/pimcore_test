<?php

namespace App\EventListener;

use Pimcore\Event\Model\DataObjectEvent;
use Pimcore\Model\DataObject\Product;

class ProductListener
{
    public function onPreAdd(DataObjectEvent $event): void
    {
        $product = $event->getObject();

        if ($product instanceof Product) {
            $this->setUppercaseName($product);
        }
    }

    public function onPreUpdate(DataObjectEvent $event): void
    {
        $product = $event->getObject();

        if ($product instanceof Product) {
            $this->setUppercaseName($product);
        }
    }

    private function setUppercaseName(Product $product): void
    {
        $product->setName(mb_strtoupper($product->getName()));
    }
}
