<?php
namespace Experius\Cleanup\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Input\InputArgument;
use Magento\Framework\App\Filesystem\DirectoryList;

class RemoveGalleryMediaCommand extends Command
{

    /**
     * Init command
     */
    protected function configure()
    {
        $this
            ->setName('experius_cleanup:media:remove-gallery-images')
            ->setDescription('Remove images by type from')
            ->addOption('dry-run')
            ->addArgument('media-type', InputArgument::REQUIRED);
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
        $isDryRun = $input->getOption('dry-run');
        $mediaType = $input->getArgument('media-type');

        if (!$isDryRun) {
            $output->writeln('WARNING: this is not a dry run. If you want to do a dry-run, add --dry-run.');
            $question = new ConfirmationQuestion('Are you sure you want to continue? [No] ', false);
            $this->questionHelper = $this->getHelper('question');
            if (!$this->questionHelper->ask($input, $output, $question)) {
                return;
            }
        }

        $table = array();
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $mediaGallery = $resource->getConnection()->getTableName('catalog_product_entity_media_gallery');
        $coreRead = $resource->getConnection('core_read');

        $databaseImages = $coreRead->fetchAll('SELECT value FROM ' . $mediaGallery . ' WHERE media_type = ?', array($mediaType));

        if (!$isDryRun) {
            $coreRead->delete($mediaGallery, "media_type = '$mediaType'" );
        }

        $countRows = count($databaseImages);

        if (!$isDryRun) {
            $output->writeln("Removed " . $countRows . " records from database.");
        } else {
            $output->writeln("Found " . $countRows . " records in database which will be removed when running without dry-run option.");
        }
    }

}