<?php
/**
 * Created by PhpStorm.
 * User: Grisha
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
        $matches = array("DIRECT" => array(), 'INDIRECT' => array());

        $headers = NULL;
        $people = array();
        $scanned = 0;

        while(($line = fgets($handle)) !== false) {

            //$chunks = explode(",", $line);
            //$chunks = $this->SplitCSVString($line); // handles inline commas
            $chunks = str_getcsv($line);

            if($headers === NULL) {
                $headers = $chunks;
                continue;
            }
            $details = array();

            $chunk_count = count($chunks);
            if(count($headers) !== $chunk_count) {
                $this->logger->info("Incorrect number of chunks, found $chunk_count, should be " . count($headers) . ": " . json_encode($chunks));
                continue;
            }
            for($i = 0; $i < $chunk_count; $i++) {
                $details[$headers[$i]] = $chunks[$i];
            }
            $details["_LINE"] = $line;
            $scanned++;

//            if(($details["support_level"] === "1") && ($details["primary_state"] !== "QLD")) {
//                $this->logger->info("STRONG OUTSIDE QLD: " . json_encode($chunks));
//            }
            if(($details["primary_state"] !== "QLD") && ($details["support_level"] === "1")) {
                $matches['DIRECT'][] = array("person" => $details, "enrollment" => NULL);
            }
//            continue;

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

        $people_sort = function($a, $b) {
//            return strnatcmp($a['last_name'], $b['last_name']);
            $a_date = Carbon::parse($a['created_at']);
            $b_date = Carbon::parse($b['created_at']);
            if($a_date->gte($b_date)) {
                return -1;
            } else {
                return 1;
            }
        };
        usort($people, $people_sort);

        $roll_repo = $this->em->getRepository('VoteBundle:ElectoralRollImport');

        foreach($people as $person) {

            $first_name = $person["first_name"];
            $given_names = $first_name;
            if($person["middle_name"] != "") {
                $given_names .= " " . $person["middle_name"];
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
//            if(count($enrollments) === 1) {
//                $type = "INDIRECT";
//                if(($person["middle_name"] != "") && $found_address) {
//                    $type = "DIRECT";
//                }
//                $matches[$type][] = array("person" => $person, "enrollment" => $enrollments[0]);
//            }
            if((!$found_address) && (count($enrollments) === 1) && ($person["middle_name"] != "") && (strpos($enrolment->getGivenNames(), $given_names) === 0)) {
                $matches['INDIRECT'][] = array("person" => $person, "enrollment" => $enrollments[0]);
            }

            // there is only one person and their address matches
            // there is only one person and their address does not match
            // there are multiple people and one address matches
        }

        $this->logger->info("Directly Matched " . count($matches['DIRECT']) . " people");
        $this->logger->info("Indirectly Matched " . count($matches['INDIRECT']) . " people");

        $phone_format = function($number) {
            $filtered = filter_var($number, FILTER_SANITIZE_NUMBER_INT) . "";
//            $this->logger->info("NUMBER $number -> " . $filtered);
            if($filtered === "") {
                return "";
            }
            if(strlen($filtered) < 9) {
                return "ERROR";
            }
            if(strlen($filtered) == 9) {
                $filtered = "0" . $filtered;
            }
            return "'$filtered";
        };

        foreach($matches as $type => $match_list) {

            $file_original = fopen("$type-original.csv", 'w');
            $file_party = NULL;

            fwrite($file_original, implode(",", $headers));

            $num_matches = 0;
            foreach($match_list as $match) {

                if($file_party === NULL) {
                    $file_party = fopen("$type-party-$num_matches.csv", 'w');
                    fwrite($file_party, implode(",", array("Title", "First Name", "Surname", "Date of Birth", "Phone Number Home", "Phone Number Work", "Fax Number", "Mobile Number", "Email", "Address Line 1", "Address Line 2", "Address Line 3", "Suburb", "State", "Postcode")) . "\n");
                }

                $person = $match['person'];
                $en = $match['enrollment'];
//                $en_str = $en->getSurname() . ", " . $en->getGivenNames() . ": " . $en->getUnitNumber() . "/" . $en->getStreetNumber() . " " . $en->getStreet() . " " . $en->getStreetType() . " " . $en->getSuburb();

                fwrite($file_original, $person['_LINE']);

                if($en !== NULL) {
                    $address = $en->getStreetNumber() . " " . $en->getStreet() . " " . $en->getStreetType();
                    if ($en->getUnitNumber() !== NULL) {
                        $address = $en->getUnitNumber() . "/" . $address;
                    }
                    $given_names = $en->getGivenNames();
                } else {
                    $given_names = $person["first_name"] . " " . $person["middle_name"];
                    $address = $person["primary_address1"];
                }
                $title = ($person['sex'] === "F") ? "Ms" : (($person['sex'] === "M") ? "Mr" : "");

                $party_data = array(
                    $title
                    ,$given_names
                    ,$person["last_name"]
                    ,"'" . Carbon::parse($person['born_at'])->format("d/m/Y")
                    ,$phone_format($person["phone_number"])
                    ,$phone_format($person["work_phone_number"])
                    ,"" // fax
                    ,$phone_format($person["mobile_number"])
                    ,$person['email']
                    ,$address
                    ,""
                    ,""
                    ,$person['primary_city']
                    ,$person['primary_state']
                    ,$person['primary_zip']
                );

                fwrite($file_party, implode(",", $party_data) . "\n");

                $num_matches++;
                if(($num_matches % 550) === 0) {
                    fclose($file_party);
                    $file_party = NULL;
                }

//                $this->logger->info("$type: [" . $en_str . "] " . $person["first_name"] . " " . $person["middle_name"] . " " . $person["last_name"] . ", " . $person["primary_address2"] . "/" . $person["primary_address1"] . ', ' . $person["primary_city"] . ', ' . $person["primary_zip"]);
            }
        }
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

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return array|string
     */
    public function adminSync($container, $admin, $input) {

        try {
            $handle = fopen($input['file']->getPathName(), "r");
            if (!$handle) {
                return "Could not open file";
            }
        } catch(\Exception $e) {
            return "Could not open file";
        }

        set_time_limit(0);

        $dry_run = $input['dry'];
        $headers = array("num", "surname", "names", "unit", "street", "suburb", "state", "country", "postcode", "gender", "dob", "errors");
        $header_num = count($headers);

        $errors = 0;
        $items = array();
        while(($line = fgets($handle)) !== false) {

            $chunks = str_getcsv($line);
            if(count($chunks) === $header_num) {
                $item = array();
                for($i = 0; $i < $header_num; $i++) {
                    $item[$headers[$i]] = $chunks[$i];
                }
                if($item["errors"] !== "") {
                    $errors++;
                } else {
                    $items[] = $item;
                }
            }
        }

        $report = "Found " . count($items) . " people, excluded $errors errors.\n";

        $user_repo = $this->em->getRepository('VoteBundle:User');

        $matches = 0;

        $dob_changes = array();
        $address_changes = array();
        $multi_match = array();
        // find the peoples who match
        foreach($items as $item) {
            $users = $user_repo->findBy(array("surname" => $item['surname'], "givenNames" => $item['names']));
            $num_users = count($users);
            if($num_users <= 0) {
                continue;
            } else if($num_users > 1) {
                $msg = array();
                foreach($users as $potential) {
                    $msg[] = $potential->getEmail();
                }
                $multi_match[] = $item['names'] . " " . $item['surname'] . " matches " . implode(", ", $msg);
                continue;
            }
            $user = $users[0];
            $matches++;

            // unverify anyone you match whose dob is incorrect, and delete incorrect dob, if can’t find dob (ie on roll in another electorate) and they were added while door-knocking keep them verified; and
            $e_dob = Carbon::createFromFormat("Y/m/d", $item["dob"])->setTime(0, 0, 0);
            $user_dob = $user->getDOB();
            $user_dob = ($user_dob === NULL) ? NULL : Carbon::instance($user_dob)->setTime(0, 0, 0);
            if(($user->getDOB() === NULL) || (!$user_dob->eq($e_dob))) {
                if($user->getWhenVerified() !== NULL) {
                    $dob_changes[] = "Unverified " . $user->getEmail() . " due to incorrect DOB: $user_dob should be $e_dob";
                    $user->setWhenVerified(NULL);
                    $user->setDOB(NULL);
                    $user->updateJson("verify.error", "We have detected anomalies with your voter registration. Please re-verify with correct details.");
                }
            }

            $unit_num = str_replace("Unit ", "", $item["unit"]);
            $e_address = $this->container->get("vote.nationbuilder")->getAddressFromExport($item["street"], $unit_num);
//            $this->logger->info("ADDRESS: " . json_encode($e_address));

            $changes = array();
            if(($user->getStreetNumber() === NULL) && ($e_address['number'] !== "")) {
                $changes[] = "Street Number = " . $e_address['number'];
                $user->setStreetNumber($e_address['number']);
            }
            if(($user->getStreet() === NULL) && ($e_address['name'] !== "")) {
                $changes[] = "Street Name = " . $e_address['name'];
                $user->setStreet($e_address['name']);
            }
            if(($user->getSuburb() === NULL) && ($item['suburb'] !== "")) {
                $changes[] = "Suburb = " . $item['suburb'];
                $user->setSuburb($item['suburb']);
            }
            if(($user->getPostcode() === NULL) && ($item['postcode'] !== "")) {
                $changes[] = "Postcode = " . $item['postcode'];
                $user->setPostcode($item['postcode']);
            }
            if(count($changes)) {
                $address_changes[] = $user->getEmail() . ": " . implode(", ", $changes);
            }
        }

        $report .= "Matched $matches people, excluded " . count($multi_match) . " multi-matches.\n";
        $report .= implode("\n", $multi_match) . "\n";
        $report .= "Unverified DOB for " . count($dob_changes) . " people:\n";
        $report .= implode("\n", $dob_changes) . "\n";
        $report .= "Changed address for " . count($address_changes) . " people:\n";
        $report .= implode("\n", $address_changes) . "\n";

        if(!$dry_run) {
            $this->em->flush();
        } else {
            $report .= "No records modified, dry run only";
        }

        return array("report" => $report);
    }

    /**
     * @param $container
     * @param $admin
     * @return array
     */
    public function getAdminGlobalActions($container, $admin) {
        return array("sync_ocr" =>
            array(
                "callback" => "adminSync"
                ,"label" => "Sync Electoral OCR"
                ,"classes" => "btn btn-xs btn-warning"
                ,"description" => "Syncronizes the database with electoral roll data that was extracted by optical character recognition"
                ,"permission" => "ROLE_ADMIN"
                ,"input" => array(
                    "dry" => array("type" => "boolean", "label" => "Dry run?", "default" => true)
                    ,"file" => array("type" => "file", "label" => "Electoral roll CSV file", "required" => true)
                )
            )
        );
    }

}