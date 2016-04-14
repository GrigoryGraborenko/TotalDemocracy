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

    /**
     * @param $filename
     * @return bool
     */
    public function processNationBuilder($filename) {

        $handle = fopen($filename, "r");
        if(!$handle) {
            return false;
        }
        $headers = NULL;
        $people = array();
        $scanned = 0;
        while(($line = fgets($handle)) !== false) {

            //$chunks = explode(",", $line);
            $chunks = $this->SplitCSVString($line); // handles inline commas

            if($headers === NULL) {
                $headers = $chunks;
                continue;
            }
            $details = array();
            $min_len = min(count($headers), count($chunks));
            for($i = 0; $i < $min_len; $i++) {
                $details[$headers[$i]] = $chunks[$i];
            }
            $details["_LINE"] = $line;
            $scanned++;

            if($details["primary_state"] !== "QLD") {
                continue;
            }
            if( ($details["primary_city"] === "") ||
                ($details["primary_zip"] === "") ||
                ($details["email"] === "")
                ) {
                continue;
            }
            if( ($details["phone_number"] === "") &&
                ($details["work_phone_number"] === "") &&
                ($details["mobile_number"] === "")
            ) {
                continue;
            }

            $people[] = $details;
        }
        fclose($handle);

        $this->logger->info("Scanned $scanned people");
        $this->logger->info("Found " . count($people) . " suitable people");

        $roll_repo = $this->em->getRepository('VoteBundle:ElectoralRollImport');

        $matches = array("DIRECT" => array(), 'INDIRECT' => array());
        foreach($people as $person) {

            $first_name = $person["first_name"];
            if($person["middle_name"] != "") {
                $first_name .= " " . $person["middle_name"];
            }
            $enrollments = $roll_repo->getBySurnameAndSimilarFirstName($person["last_name"], $first_name);
            if(count($enrollments) <= 0) {
                continue;
            }
            $found_address = false;
            foreach($enrollments as $enrolment) {
                if(($enrolment->getStreetNumber() . " " . $enrolment->getStreet() . " " . $enrolment->getStreetType()) !== $person["primary_address1"]) {
                    continue;
                }
//                if(($enrolment->getUnitNumber() !== NULL) && ($enrolment->getUnitNumber() !== $person["primary_address2"])) {
//                    continue;
//                }
                $found_address = true;
                $matches['DIRECT'][] = array("person" => $person, "enrollment" => $enrolment);
                break;
                //$this->logger->info("Person: " . $enrolment->getSurname() . ", " . $enrolment->getGivenNames() . ": " . $enrolment->getUnitNumber() . "/" . $enrolment->getStreetNumber() . " " . $enrolment->getStreet() . " " . $enrolment->getStreetType() . " " . $enrolment->getSuburb());
            }
            if((!$found_address) && (count($enrollments) === 1)) {
                $matches['INDIRECT'][] = array("person" => $person, "enrollment" => $enrollments[0]);
            }

            // there is only one person and their address matches
            // there is only one person and their address does not match
            // there are multiple people and one address matches
        }

        $this->logger->info("Directly Matched " . count($matches['DIRECT']) . " people");
        $this->logger->info("Indirectly Matched " . count($matches['INDIRECT']) . " people");

        foreach($matches as $type => $match_list) {

            $file_original = fopen("$type-original.csv", 'w');
            $file_party = fopen("$type-party.csv", 'w');

            fwrite($file_original, implode(",", $headers));

            fwrite($file_party, implode(",", array("Title", "First Name", "Surname", "Date of Birth", "Phone Number Home", "Phone Number Work", "Fax Number", "Mobile Number", "Email", "Address Line 1", "Address Line 2", "Address Line 3", "Suburb", "State", "Postcode")) . "\n");

            foreach($match_list as $match) {
                $person = $match['person'];
                $en = $match['enrollment'];
//                $en_str = $en->getSurname() . ", " . $en->getGivenNames() . ": " . $en->getUnitNumber() . "/" . $en->getStreetNumber() . " " . $en->getStreet() . " " . $en->getStreetType() . " " . $en->getSuburb();

                fwrite($file_original, $person['_LINE']);

                $address = $en->getStreetNumber() . " " . $en->getStreet() . " " . $en->getStreetType();
                if($en->getUnitNumber() !== NULL) {
                    $address = $en->getUnitNumber() . "/" . $address;
                }

                $party_data = array(
                    ""
                    ,$en->getGivenNames()
                    ,$en->getSurname()
                    ,$person['born_at']
                    ,$person["phone_number"]
                    ,$person["work_phone_number"]
                    ,$person["mobile_number"]
                    ,""
                    ,$person['email']
                    ,$address
                    ,""
                    ,""
                    ,""
                    ,$person['primary_city']
                    ,$person['primary_state']
                    ,$person['primary_zip']
                );

                fwrite($file_party, implode(",", $party_data) . "\n");

//                $this->logger->info("$type: [" . $en_str . "] " . $person["first_name"] . " " . $person["middle_name"] . " " . $person["last_name"] . ", " . $person["primary_address2"] . "/" . $person["primary_address1"] . ', ' . $person["primary_city"] . ', ' . $person["primary_zip"]);
            }
        }
    }

    /**
     * @param $str
     * @return array
     */
    public function SplitCSVString($str) {

        $len = strlen($str);
        if($len == 0) {
            return array();
        }

        $res = array();
        $curr = '';
        $lev = 0;
        $escape = false;
        for($i = 0; $i < $len; $i++) {
            $c = $str[$i];
            if($c === '\\') {
                $escape = true;
            } else if($c === '"') {
                if($escape) {
                    $curr .= $c;
                } else {
                    $lev++;
                    if($lev > 2) {
                        $lev = 1;
                        $curr .= $c;
                    }
                }
                $escape = false;
            } else if($c === ',') {
                if($lev == 1) {
                    $curr .= $c;
                } else {
                    $res[] = $curr;
                    $curr = '';
                    $lev = 0;
                }
                $escape = false;
            } else {
                $curr .= $c;
                if($lev == 2) {
                    $lev = 0;
                }
                $escape = false;
            }
        }
        $res[] = $curr;

        return $res;
    }

    /**
     * @param $dirname
     */
    public function processDirectory($dirname) {

        $files = scandir($dirname);

        foreach($files as $file) {
            if(substr($file, -4) !== ".pdf") {
                continue;
            }
            $this->processFile($dirname . $file, $file);
        }

    }

    /**
     * @param $filename
     */
    public function processFile($filepath, $filename) {

        $this->logger->info("Reading $filename");
        $result = $this->container->get("vote.pdf")->processElectoralPDF($filepath);

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
                    $this->logger->info("Skipped: " . json_encode($entry));
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

            $this->logger->info("SUBURBS: " . json_encode($result['suburbs']));
        }
    }
}