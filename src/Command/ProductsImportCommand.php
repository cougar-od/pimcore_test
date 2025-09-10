<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace App\Command;

use Carbon\Carbon;
use CustomerManagementFrameworkBundle\Model\ActionTrigger\Rule\Listing;
use Exception;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Product;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProductsImportCommand extends AbstractCommand
{
    public function configure(): void
    {
        $this->setName('app:products:import');
        $this->addOption(
            'url',
            null,
            InputOption::VALUE_REQUIRED,
            'Products import URL'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {

        $url = $input->getOption('url');
        if (!$url) {
            $output->writeln("Please provide JSON data URL");
            return Command::FAILURE;
        }

        try {
            $jsonData = file_get_contents($url);
        } catch (Exception $ex) {
            $output->writeln("Error: {$ex->getMessage()}");
            return Command::FAILURE;
        }

        if ($jsonData === false) {
            $output->writeln("Unable to load JSON data");
            return Command::FAILURE;
        }

        try {
            $productsToImport = json_decode($jsonData, true, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $ex) {
            $output->writeln("Error while reading JSON data: {$ex->getMessage()}");
            return Command::FAILURE;
        }

        if (!is_array($productsToImport) || !isset($productsToImport['products'])) {
            $output->writeln("Invalid JSON file");
            return Command::FAILURE;
        }

        foreach ($productsToImport['products'] as $productItem) {

            if (!isset($productItem['gtin'])) {
                $output->writeln("Missing gtin, skipping");
                continue;
            } elseif (!is_numeric($productItem['gtin'])) {
                $output->writeln("Invalid or ({$productItem['gtin']}), skipping");
                continue;
            }

            $output->write("Importing product with gtin {$productItem['gtin']}... ");
            if (!isset($productItem['name']) || empty(trim($productItem['name']))) {
                $output->writeln("Name should not be empty, skipping");
                continue;
            }

            $product = Product::getByGtin($productItem['gtin'], 1) ?: new Product();

            $product->setName($productItem['name']);
            $product->setGtin($productItem['gtin']);
            $date = Carbon::createFromFormat("Y-m-d", $productItem['date'] ?? null);
            if ($date) {
                $product->setDate($date);
            }

            if (isset($productItem['image'])) {
                $imageAsset = Asset::getByPath($productItem['image']);
                if ($imageAsset) {
                    $product->setImage($imageAsset);
                } else {
                    $output->write("Image with path '{$productItem['image']}' not found.");
                }
            }

            try {
                $product->setKey($productItem['name']);
                $product->setParentId(1);
                $product->save();
                $output->writeln(' Product imported');
            } catch (Exception $ex) {
                $output->writeln("Error: {$ex->getMessage()}, skipping");
            }
        }
        return Command::SUCCESS;
    }
}
