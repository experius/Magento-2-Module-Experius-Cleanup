<?php
namespace Experius\Cleanup\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Magento\Framework\App\Filesystem\DirectoryList;

class RemoveNonExistingMediaCommand extends Command
{

    /**
     * Init command
     */
    protected function configure()
    {
        $this
            ->setName('experius_cleanup:media:remove-non-existing')
            ->setDescription('Remove non existing product images from database')
            ->addOption('dry-run');
    }

    /**
     * Execute Command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void;
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $fileRows = 0;
        $isDryRun = $input->getOption('dry-run');

        if(!$isDryRun) {
            $output->writeln('WARNING: this is not a dry run. If you want to do a dry-run, add --dry-run.');
            $question = new ConfirmationQuestion('Are you sure you want to continue? [No] ', false);
            $this->questionHelper = $this->getHelper('question');
            if (!$this->questionHelper->ask($input, $output, $question)) {
                return;
            }
        }

        $table = array();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $filesystem = $objectManager->get('Magento\Framework\Filesystem');
        $mediaGalleryResource = $objectManager->get('Magento\Catalog\Model\ResourceModel\Product\Gallery');
        $directory = $filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $imageDir = $directory->getAbsolutePath() . 'catalog' . DIRECTORY_SEPARATOR . 'product';
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $mediaGallery = $resource->getConnection()->getTableName('catalog_product_entity_media_gallery');
        $mediaGalleryValue = $resource->getConnection()->getTableName('catalog_product_entity_media_gallery_value');
        $productEntityTable = $resource->getConnection()->getTableName('catalog_product_entity');
        $coreRead = $resource->getConnection('core_read');
        $i = 0;
        
        $databaseImages = $coreRead->fetchCol('SELECT value FROM ' . $mediaGallery);
        
        foreach ($databaseImages as $file) {
                
            $filePath = $imageDir . $file;
            if (file_exists($filePath)){
                continue;
            }
            
            if (empty($filePath)) continue;
            $valueRow = $coreRead->fetchRow('
                SELECT products.sku, mediagallery.value_id FROM ' . $mediaGallery . ' as mediagallery 
                    INNER JOIN ' . $mediaGalleryValue . ' mediagalleryvalue ON mediagallery.value_id = mediagalleryvalue.value_id 
                    INNER JOIN ' . $productEntityTable . ' products ON products.entity_id = mediagalleryvalue.' . $this->getFieldName($mediaGallery) . '
                    WHERE value = ?', array($file));

            if ($valueRow !== false) {
                $row = array();
                $row['sku'] = $valueRow['sku'];
                $row['file'] = $file;
                $table[] = $row;
                $fileRows++;
                echo "## REMOVING: {$file}, from product sku: {$valueRow['sku']} ##";
                if (!$isDryRun) {
                    $mediaGalleryResource->deleteGallery($valueRow['value_id']);
                } else {
                    echo ' -- DRY RUN';
                }
                echo PHP_EOL;
                $i++;
            }
            break;
        }

        $headers = array();
        $headers[] = 'sku';
        $headers[] = 'filepath';
        $this->getHelper('table')
            ->setHeaders($headers)
            ->setRows($table)->render($output);
        $output->writeln("Found " . $fileRows . " records in database where the image did not exist");
    }

    /**
     * @return string
     */
    private function getFieldName($fullTableName)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Framework\App\ResourceConnection $db */
        $resConnection = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $db = $resConnection->getConnection();
        $results = $db->fetchAll("SHOW COLUMNS FROM `$fullTableName` LIKE 'entity_id'");
        if ($results) {
            return 'entity_id';
        }
        $db->closeConnection();
        return 'row_id';
    }

}