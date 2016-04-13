<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 14/09/2015
 * Time: 9:11 AM
 */

namespace VoteBundle\Service;

use Carbon\Carbon;
use Doctrine\ORM\EntityManager;

use VoteBundle\Entity\ElectoralRollImport;

/**
 * Class ElectoralRollService
 * @package VoteBundle\Service
 *
 * Processes electoral roll data and stores it.
 *
 */
class ElectoralRollService
{

    /** @var EntityManager em */
    private $em;

    /** @var Service Container container */
    private $container;

    /** @var Logger logger */
    private $logger;

    /**
     * OptionService constructor.
     * @param $em
     * @param $container
     * @param $logger
     */
    public function __construct($em, $container, $logger) {
        $this->em = $em;
        $this->container = $container;
        $this->logger = $logger;
    }

    public function processNationBuilder($filename) {
        //file_get_contents();
    }

    /**
     * @param $dirname
     */
    public function processDirectory($dirname) {

        $files = scandir($dirname);

//        $files = array('0801 Bracken Ridge.pdf');
//        $files = array('0803 Central.pdf');
//        $files = array_slice($files, 3);

        foreach($files as $file) {
            if(substr($file, -4) !== ".pdf") {
                continue;
            }
            $this->processFile($dirname . $file);
        }

    }

    /**
     * @param $filename
     */
    public function processFile($filename) {

        $this->logger->info("Reading $filename");
        $result = $this->container->get("vote.pdf")->processElectoralPDF($filename);

        $this->logger->info("Pre Mem: " . number_format(memory_get_usage() / (1024 * 1024), 3) . " mb");

        $roll_repo = $this->em->getRepository('VoteBundle:ElectoralRollImport');
        if(is_string($result)) {
            $this->logger->info("ERROR: " . $result);
        } else {

            $this->logger->info("Found " . count($result['entries']) . " valid for " . $result['valid_date']);

            $persisted = 0;
            foreach($result['entries'] as $entry) {

                $priors = $roll_repo->findBy(array(
                    "surname" => $entry['surname']
                    ,"given_names" => $entry['given_names']
                    ,"valid_date" => $result['valid_date']
                ));
                $found = false;
                foreach($priors as $prior) {
                    if(($prior->getStreetNumber() === $entry['street_number']) && ($prior->getStreet() === $entry['street'])) {
                        $found = true;
                        break;
                    }
                }
                if($found) {
                    continue;
                }

                $json = json_encode(array(
                    "file" => $filename
                    ,"index" => $entry['index']
                    ,"name" => $entry['name']
                    ,"address" => $entry['address']
                ));
                $record = new ElectoralRollImport($result['valid_date'], $entry['surname'], $entry['given_names'], $json, $entry['unit'], $entry['street_number'], $entry['street'], $entry['street_type'], $entry['suburb']);
                $this->em->persist($record);
                $persisted++;
            }
            $this->em->flush();
            $this->em->clear();

            $this->logger->info("Persisted: $persisted");

            $this->logger->info("Post Mem: " . number_format(memory_get_usage() / (1024 * 1024), 3) . " mb");

            $this->logger->info("SUBURBS: " . json_encode($result['suburbs']));
        }
    }


}