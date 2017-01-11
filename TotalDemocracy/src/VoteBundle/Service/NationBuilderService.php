<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 27/06/2016
 * Time: 6:49 AM
 */

namespace VoteBundle\Service;

use GuzzleHttp\Client as HttpClient;
use Symfony\Component\DomCrawler\Crawler;

use OAuth2\Client;
use Carbon\Carbon;

use VoteBundle\Entity\Volunteer;

/**
 * Class NationBuilderService
 * @package VoteBundle\Service
 */
class NationBuilderService {

    private $container;
    private $em;
    private $logger;

    private $base_url;
    private $client_id;
    private $secret;

    private $api_token;
    private $required_fields;

//    public function

    // sync function that
    // 1. uses person/match with email
    // 2. if no match, create
    // 3. checks fields
    // 4. sends an update if any mismatch occurred

    /**
     * @param $request
     * @return null
     */
    public function getTrackingEvent($request) {

        $cookies = $request->cookies->all();
        if(array_key_exists("tracking_token", $cookies)) {
            $events = $this->em->getRepository('VoteBundle:ServerEvent')->findByJson("registration.track", $cookies['tracking_token'], false);
            if(count($events) > 0) {
                $track_event = $events[0];
                $json = $track_event->getJsonArray();
                $this->setToken($json["nationbuilder.api_token"]);
                return $track_event;
            }
        }
        return NULL;
    }


    /**
     * Checks to see if a person exists. If they do, returns their details
     *
     * @param $email
     * @return null
     */
    public function getPerson($email) {

        list($success, $result) = $this->communicate("people/match", array("email" => $email));
        if(!$success) {
            return NULL;
        }
        return $result['person'];
    }

    /**
     * @param $new
     * @param $old
     * @return null
     */
    private function recursiveDiff($new, $old) {

        $diff = array();
        foreach($new as $key => $value) {
            $old_val = $old[$key];
            if(is_array($value)) {
                if(is_array($old_val)) {
                    $recurse_diff = $this->recursiveDiff($value, $old_val);
                    if($recurse_diff !== NULL) {
                        $diff[$key] = $recurse_diff;
                    }
                } else {
                    $diff[$key] = $value;
                }
                continue;
            }

            if(array_key_exists($key, $old) && ($old_val === $value)) {
                continue;
            }
            $diff[$key] = $value;
        }
        if(count($diff) <= 0) {
            return NULL;
        }
        return $diff;
    }

    /**
     * Syncs this user into the nationbuilder database
     *
     * @param $user
     * @return array
     */
    public function syncPerson($user) {

        $errors = array();
        $this->logger->info("Syncing user " . $user->getEmail());

        $person = $this->getPerson($user->getEmail());

        $sync_user = array(
            "email" => $user->getEmail()
            ,"last_name" => $user->getSurname()
            ,"primary_address" => array(
                "zip" => $user->getPostcode()
                ,"country_code" => "AU"
            )
        );
//        $this->logger->info("PERSON " . json_encode($person));

        if(substr($user->getPhone(), 0, 2) === "04") {
            $sync_user["mobile"] = $user->getPhone();
        } else {
            $sync_user["phone"] = $user->getPhone();
        }

        $names = explode(" ", $user->getGivenNames());
        if(count($names) <= 1) {
            $sync_user["first_name"] = $user->getGivenNames();
            $sync_user["middle_name"] = "";
        } else {
            $sync_user["first_name"] = $names[0];
            $sync_user["middle_name"] = implode(" ", array_slice($names, 1));
        }
        if($user->getDOB() !== NULL) {
            $sync_user['birthdate'] = $user->getDOB()->format("Y-m-d");
        }
//        if($user->getVolunteer() !== NULL) {
//            $person['tags'] = array(
//            );
//        }

        if($person === NULL) {
            $person_data = $sync_user;
        } else {
            $person_data = $this->recursiveDiff($sync_user, $person);
        }
//        $this->logger->info("REFRESH: " . json_encode($person_data));

        if($person_data !== NULL) {
            if($person === NULL) {
                $result = $this->sendData("people", array('person' => $person_data), "POST");
            } else {
                $result = $this->sendData("people/" . $person['id'], array('person' => $person_data), "PUT");
            }
            if(!$result["success"]) {
                $errors[] = $result["result"];
            }
            $this->logger->debug("Nationbuilder Sync Result: " . json_encode($result));
        }

        $volunteer = $user->getVolunteer();
        $user_tags = array();
        if($volunteer !== NULL) {

            if($volunteer->getWillPollBooth()) {
                $user_tags[] = "how-to-vote volunteer";
            }
            if($volunteer->getWillDoorKnock()) {
                $user_tags[] = "doorknock volunteer";
            }
            if($volunteer->getWillSignage()) {
                $user_tags[] = "signage house";
            }
            if($volunteer->getWillCall()) {
                $user_tags[] = "callback volunteer";
            }
            if($volunteer->getWillHouseParty()) {
                $user_tags[] = "host house party";
            }
            if($volunteer->getWillEnvelopes()) {
                $user_tags[] = "office volunteer";
            }
            if($volunteer->getWillOther()) {
                $user_tags[] = "other volunteer";
            }
        }
        if($user->getIsMember()) {
            if($user->getWhenVerified() !== NULL) {
                $user_tags[] = "member verified";
            } else {
                $user_tags[] = "member";
            }
        }

        if(($person_data !== NULL) && (array_key_exists('primary_address', $person_data))) {
            $result = $this->pushAddress($user);
            if($result !== true) {
                $this->logger->info("Error pushing address: $result");
            }
//            if(($person !== NULL) && (array_search("manual", $person["tags"]) === false)) {
//                $person["tags"][] = "manual";
//            }
        }

        if($person === NULL) {
            $add_tags = $user_tags;
            $remove_tags = array();
//            $remove_tags = array("manual");
        } else {
            $add_tags = array_diff($user_tags, $person["tags"]);
            $remove_tags = array_values(array_diff($person["tags"], $user_tags));
        }

//        $this->logger->debug("TAG LIST " . json_encode($user_tags));
//        $this->logger->debug("Person TAG LIST " . json_encode($person["tags"]));
//        $this->logger->debug("aDD TAG LIST " . json_encode($add_tags));
//        $this->logger->debug("REMOVe TAG LIST " . json_encode($remove_tags));

        if(count($add_tags) > 0) {
            $result = $this->sendData("people/" . $person['id'] . "/taggings", array('tagging' => array("tag" => $user_tags)), "PUT");
            $this->logger->debug("Nationbuilder Tag Add Result: " . json_encode($result));
        }
        if(count($remove_tags) > 0) {

            foreach($remove_tags as $remove) {
                $tag = rawurlencode($remove);
//                $result = $this->sendData("people/" . $person['id'] . "/taggings/$tag", array(), "DELETE");
                list($success, $result) = $this->communicate("people/" . $person['id'] . "/taggings/$tag", array(), "DELETE");
                if($success) {
                    $this->logger->debug("Nationbuilder Tag Delete Success: " . json_encode($result));
                } else {
                    $this->logger->debug("Nationbuilder Tag Delete Failure: " . json_encode($result));
                }
            }

/*
            list($success, $result) = $this->communicate("people/" . $person['id'] . "/taggings", array('tagging' => array("tagx" => $remove_tags)), "DELETE");
            if($success) {
                $this->logger->debug("Nationbuilder Tag Delete Success: " . json_encode($result));
            } else {
                $this->logger->debug("Nationbuilder Tag Delete Failure: " . json_encode($result));
            }*/
        }

        return array(count($errors) <= 0, implode(", ", $errors));
    }

    /**
     * @param $filename
     * @param $include_existing
     * @param bool $exclude_bad_emails
     * @return array|string
     */
    public function readFromCSV($filename, $include_existing, $exclude_bad_emails = true) {

        try {
            $handle = fopen($filename, "r");
            if (!$handle) {
                return "Could not open file";
            }
        } catch(\Exception $e) {
            return "Could not open file";
        }

        $user_repo = $this->em->getRepository('VoteBundle:User');

        $headers = NULL;
        $people = array();
        $scanned = 0;

        while(($line = fgets($handle)) !== false) {

            $chunks = str_getcsv($line);
            if($headers === NULL) {
                $header_diff = array_diff($this->required_fields, $chunks);
                if(count($header_diff) > 0) {
                    return "Headers not found: " . implode(", ", $header_diff);
                }
                $headers = $chunks;
                continue;
            }
            $details = array();

            $chunk_count = count($chunks);
            if(count($headers) !== $chunk_count) {
                return "Incorrect number of chunks at line $scanned, found $chunk_count, should be " . count($headers) . ": " . json_encode($chunks);
            }
            for($i = 0; $i < $chunk_count; $i++) {
                $details[$headers[$i]] = trim($chunks[$i]);
            }
            $scanned++;

            $email = $details['email'];
//            if($email === "") {
            if(($email === "") || (($details['email1_is_bad'] === "true") && $exclude_bad_emails)) {
                continue;
            }
            if(!$include_existing) {
                if($user_repo->findOneBy(array("email" => $email)) !== NULL) {
                    continue;
                }
            }

            $people[] = $details;
        }
        fclose($handle);

        return $people;
    }

    /**
     * @param $person
     * @return array
     */
    public function getAddressFromExport($person) {

        $address_chunks = explode(" ", $person['primary_address1']);
        $number_chunks = array();
        $street_chunks = array();
        foreach($address_chunks as $chunk) {
            if(strpbrk($chunk, '1234567890') !== false) {
                $number_chunks[] = $chunk;
            } else {
                $street_chunks[] = $chunk;
            }
        }
        $number_chunks = implode("/", $number_chunks);

        $street = "";
        if(count($street_chunks) > 0) {
            $street = implode(" ", $street_chunks);
        }
        if($person['primary_address2'] === "") {
            $street_num = $number_chunks;
        } else if(ctype_alpha($person['primary_address2'])) {
            $street_num = $person['primary_address2'] . " " . $number_chunks;
        } else {
            $street_num = $person['primary_address2'] . "/" . $number_chunks;
        }

        return array("number" => $street_num, "name" => $street);
    }

    /**
     * @param $person
     * @param bool $ignore_existing
     * @return array
     */
    public function createUserFromExport($person, $ignore_existing = false) {

        $volunteer = NULL;
        $header_diff = array_diff($this->required_fields, array_keys($person));
        if(count($header_diff) > 0) {
            return "Headers not found: " . implode(", ", $header_diff);
        }

        $email = strtolower($person['email']);
//        $this->logger->info("PERSON " . json_encode($person));
//        $this->logger->info("tags " . json_encode($tags));

        $user_manager = $this->container->get("fos_user.user_manager");
        $user = $user_manager->findUserByEmail($email);
        if($user === NULL) {
            $user = $user_manager->createUser();
        } else if($ignore_existing) {
            return array(NULL, NULL);
        }

        $token_gen = $this->container->get('fos_user.util.token_generator');

        $user->setEmail($email);
        $user->setUsername($email);
        $user->setPlainPassword($token_gen->generateToken());
        $user->setConfirmationToken($token_gen->generateToken());

        $user->setWhenFromNationBuilder(Carbon::now("UTC"));
        $user->setDateCreated(Carbon::createFromFormat("m/d/Y g:i a", $person['created_at']));

        $params = array(
            "last_name" => "setSurname"
            ,"primary_zip" => "setPostcode"
            ,"primary_city" => "setSuburb"
            ,"sex" => "setGender"
            ,"occupation" => "setOccupation"
            ,"website" => "setWebsite"
            ,"twitter_id" => "setTwitter"
            ,"facebook_username" => "setFacebook"
            ,"nationbuilder_id" => "updateJson"
//            ,"twitter_id" => "updateJson"
//            ,"facebook_username" => "updateJson"
        );
        foreach($params as $param => $func) {
            $val = $person[$param];
            if($val === "") {
                continue;
            }
            if($func === "updateJson") {
                $user->{$func}($param, $val);
            } else {
                $user->{$func}($val);
            }
        }
        if($person['first_name'] !== "") {
            $name = $person['first_name'];
            if($person['middle_name'] !== "") {
                $name .= " " . $person['middle_name'];
            }
            $user->setGivenNames($name);
        }

        if($person['born_at'] !== "") {
            $dob = Carbon::createFromFormat('m/d/Y', $person['born_at']);
            $user->setDOB($dob);
        }

        $address = $this->getAddressFromExport($person);
        $user->setStreet($address['name']);
        $user->setStreetNumber($address['number']);

        $tags = explode(", ", $person['tag_list']);

        if($person['is_volunteer'] === "true") {

            $user->setIsVolunteer(true);
            if(($user->getPostcode() !== NULL) && ($user->getSuburb() !== NULL) && ($user->getStreet() !== NULL) && ($user->getStreetNumber() !== NULL)) {
                $volunteer = new Volunteer(
                    $user, $user->getPostcode(), $user->getSuburb(), $user->getStreet(), $user->getStreetNumber()
                    ,in_array("how-to-vote volunteer", $tags) // poll booth
                    ,in_array("doorknock volunteer", $tags) // door knock
                    ,in_array("signage house", $tags) // signage
                    ,in_array("callback volunteer", $tags) // call
                    ,in_array("host house party", $tags) // house party
                    ,in_array("office volunteer", $tags) // envelopes
                );
            }
        }
        if(in_array("Member", $tags) || in_array("Member verified", $tags)) {
            $user->setIsMember(true);
        }

        return array($user, $volunteer);
    }

    /**
     * @param $container
     * @param $admin
     * @param $input
     * @return array|string
     */
    public function adminSync($container, $admin, $input) {

        $dry_run = $input['dry'];
        $mode = $input['mode'];

        $people = $this->readFromCSV($input['file']->getPathName(), true, false);
        if(!is_array($people)) {
            return $people;
        }
        $report = "Loaded " . count($people) . " people from CSV\n";

        $fname_date = implode("-", array_slice(explode('-', explode('.', $input['file']->getClientOriginalName())[0]), 4));
        $ignore_time = Carbon::createFromFormat("Y-m-d", $fname_date);

        // find all "registration.track" events and their child events, delete them if they don't show up in the list
        if($mode === "delete") {
            $user_emails = array();
            $event_repo = $this->em->getRepository('VoteBundle:ServerEvent');
            $track_events = $event_repo->findBy(array("name" => "registration.track"));
            foreach($track_events as $track) {
                $reg_events = $event_repo->findBy(array("name" => "registration", "parent" => $track));
                foreach($reg_events as $registration) {
                    $delete_user = $registration->getUser();
                    $user_email = $delete_user->getEmail();

                    if(Carbon::instance($delete_user->getDateCreated()) > $ignore_time) {
                        continue;
                    }

                    $found = false;
                    foreach($people as $person) {
                        if(strtolower($person['email']) === strtolower($user_email)) {
                            $found = true;
                            break;
                        }
                    }
                    if(!$found) {
                        $user_emails[] = $user_email;
                        $this->em->remove($delete_user);
                        $this->em->remove($registration);
                    }
                }
            }
            $report .= "Deleted " . count($user_emails) . " people from database:\n";
            $report .= implode("\n", $user_emails) . "\n";
        } else if($mode === "add") {

            $user_emails = array();
            foreach($people as $person) {
                list($new_user, $new_volunteer) = $this->createUserFromExport($person, true);
                if($new_user) {
                    $this->em->persist($new_user);
                    $user_emails[] = $new_user->getEmail();
                    if($new_volunteer) {
                        $this->em->persist($new_volunteer);
                    }
                }
            }
            $report .= "Imported " . count($user_emails) . " people into database\n";
//            $report .= implode("\n", $user_emails) . "\n";
        } else if($mode === "sync") {

//            $num_users_synced = 0;

            $sync_fields = array(
                "sex" => "Gender"
                ,"occupation" => "Occupation"
                ,"website" => "Website"
                ,"twitter_id" => "Twitter"
                ,"facebook_username" => "Facebook"
            );
            $num_fields_updated = array();
            foreach($sync_fields as $key => $func) {
                $num_fields_updated[$key] = 0;
            }

            $num_core_fields_updated = array(
                "dob" => array()
                ,"address" => array()
                ,"name" => array()
                ,"phone" => array()
                ,"volunteer" => array()
            );

//            $cutoff_time = Carbon::now("UTC")->subDays(2);

            $user_repo = $this->em->getRepository('VoteBundle:User');
            foreach($people as $person) {
                $sync_user = $user_repo->findOneBy(array("email" => strtolower($person['email'])));
                if($sync_user === NULL) {
                    continue;
                }

                $tags = explode(", ", $person['tag_list']);

                //if(strpos($person["tag_list"], "manual") !== false) {
                if(in_array("manual", $tags)) {
//                    $num_users_synced++;

                    $nb_created = Carbon::createFromFormat("m/d/Y g:i a", $person['created_at']);
                    $address = $this->getAddressFromExport($person);

                    $cutoff_time = Carbon::instance($nb_created)->addDays(2);
                    $updated = ($sync_user->getWhenVerified() !== NULL) && (Carbon::instance($sync_user->getWhenVerified()) > $cutoff_time);

                    // people that have a different dob on the PD database compared to the dob on the NB database, UPDATE the dob on the PD database with the dob on the NB database, unless the person has later, 2 or more days AFTER NB creation date, updated the dob themselves on the PD database
                    if($person['born_at'] !== "") {
                        $dob = Carbon::createFromFormat('m/d/Y', $person['born_at']);
                        if($sync_user->getDOB() === NULL) {
                            $num_core_fields_updated["dob"][] = $sync_user->getEmail() . ": NULL => $dob";
                            $sync_user->setDOB($dob);
                        } else if(!$updated) {
                            $sync_dob = Carbon::instance($sync_user->getDOB());
                            if(($sync_dob->year !== $dob->year) || ($sync_dob->dayOfYear !== $dob->dayOfYear)) {
                                $num_core_fields_updated["dob"][] = $sync_user->getEmail() . ": " . Carbon::instance($sync_user->getDOB()) . " => $dob";
                                $sync_user->setDOB($dob);
                            }
                        }
                    }

                    // people that have a different COMPLETE (not missing any fields) address on the PD database compared to the address on the NB database OR the address is COMPLETELY missing on the PD database, UPDATE the address on the PD database with the address on the NB database (**if updating missing address accept partially completed address), unless the person has later, 2 or more days AFTER NB creation date, updated the address themselves on the PD database;
                    if((!$updated) && ($address['name'] !== "") && ($address['number'] !== "") && ($person['primary_zip'] !== "") && ($person['primary_city'] !== "") &&
                        ((strtolower($address['name']) !== strtolower($sync_user->getStreet())) || ($address['number'] !== $sync_user->getStreetNumber()) || ($person['primary_zip'] !== $sync_user->getPostcode()) || (strpos(strtolower($sync_user->getSuburb()), strtolower($person['primary_city'])) === false))) {
                        $num_core_fields_updated["address"][] = $sync_user->getEmail() . " updated change: " . $sync_user->getStreetNumber() . " " . $sync_user->getStreet() . " " . $sync_user->getSuburb() . " " . $sync_user->getPostcode() . " => "
                            . $address['number'] . " " . $address['name'] . " " . $person['primary_city'] . " " . $person['primary_zip'];
                        $sync_user->setStreetNumber($address['number']);
                        $sync_user->setStreet($address['name']);
                        $sync_user->setSuburb($person['primary_city']);
                        $sync_user->setPostcode($person['primary_zip']);
                    } else if(($sync_user->getStreet() === NULL) || ($sync_user->getStreetNumber() === NULL) || ($sync_user->getSuburb() === NULL) || ($sync_user->getPostcode() === NULL)) {
                        $changes = array();
                        if(($sync_user->getStreetNumber() === NULL) && ($address['number'] !== "")) {
                            $changes[] = "Street Number = " . $address['number'];
                            $sync_user->setStreetNumber($address['number']);
                        }
                        if(($sync_user->getStreet() === NULL) && ($address['name'] !== "")) {
                            $changes[] = "Street Name = " . $address['name'];
                            $sync_user->setStreet($address['name']);
                        }
                        if(($sync_user->getSuburb() === NULL) && ($person['primary_city'] !== "")) {
                            $changes[] = "Suburb = " . $person['primary_city'];
                            $sync_user->setSuburb($person['primary_city']);
                        }
                        if(($sync_user->getPostcode() === NULL) && ($person['primary_zip'] !== "")) {
                            $changes[] = "Postcode = " . $person['primary_zip'];
                            $sync_user->setPostcode($person['primary_zip']);
                        }
                        if(count($changes)) {
                            $num_core_fields_updated["address"][] = $sync_user->getEmail() . ": " . implode(", ", $changes);
                        }
                    }

                    // people that have a different or no name on the PD database with the one on the NB database, UPDATE the one of the PD database with the one on the NB database;
                    $nb_name = $person['first_name'];
                    if($person['middle_name'] !== "") {
                        $nb_name .= " " . $person['middle_name'];
                    }
                    if((strtolower($nb_name) !== strtolower(trim($sync_user->getGivenNames()))) || (strtolower($person['last_name']) !== strtolower(trim($sync_user->getSurname())))) {
                        $num_core_fields_updated["name"][] = $sync_user->getEmail() . ": " . $sync_user->getGivenNames() . " " . $sync_user->getSurname() . " => $nb_name " . $person['last_name'];
                        $sync_user->setGivenNames($nb_name);
                        $sync_user->setSurname($person['last_name']);
                    }

                    // people that have a different phone number or the phone is missing on the PD database compared to the phone number on the NB database, UPDATE the phone number on the PD database with the phone number on the NB database, unless the person has later, 2 or more days AFTER NB creation date, updated the phone number themselves on the PD database;
                    if((!$updated) && ($sync_user->getPhone() !== $person["phone_number"]) && ($sync_user->getPhone() !== $person["mobile_number"])) {
                        if($person["mobile_number"] !== "") {
                            $num_core_fields_updated["name"][] = $sync_user->getEmail() . ": " . $sync_user->getPhone() . " => " . $person["mobile_number"];
                            $sync_user->setPhone($person["mobile_number"]);
                        } else if($person["phone_number"] !== "") {
                            $num_core_fields_updated["name"][] = $sync_user->getEmail() . ": " . $sync_user->getPhone() . " => " . $person["phone_number"];
                            $sync_user->setPhone($person["phone_number"]);
                        }
                    }

                    if(($person['is_volunteer'] === "true") && ($sync_user->getVolunteer() === NULL)) {
                        $sync_user->setIsVolunteer(true);
                        if(($sync_user->getPostcode() !== NULL) && ($sync_user->getSuburb() !== NULL) && ($sync_user->getStreet() !== NULL) && ($sync_user->getStreetNumber() !== NULL)) {
                            $volunteer = new Volunteer(
                                $sync_user, $sync_user->getPostcode(), $sync_user->getSuburb(), $sync_user->getStreet(), $sync_user->getStreetNumber()
                                ,in_array("how-to-vote volunteer", $tags) // poll booth
                                ,in_array("doorknock volunteer", $tags) // door knock
                                ,in_array("signage house", $tags) // signage
                                ,in_array("callback volunteer", $tags) // call
                                ,in_array("host house party", $tags) // house party
                                ,in_array("office volunteer", $tags) // envelopes
                            );
                            $this->em->persist($volunteer);
                            $sync_user->setVolunteer($volunteer);
                            $num_core_fields_updated["volunteer"][] = $sync_user->getEmail();
                        }
                    }
                }

                // add gender, occupation, website, twitter and facebook fields
                foreach($sync_fields as $key => $func) {
                    if(($sync_user->{"get" . $func}() === NULL) && ($person[$key] !== "")) {
                        $num_fields_updated[$key]++;
                        $sync_user->{"set" . $func}($person[$key]);
                    }
                }
            }

            foreach($num_fields_updated as $name => $amount) {
                $report .= "$name: $amount updates \n";
            }
            $report .= "\n";
            foreach($num_core_fields_updated as $name => $array) {
                $amount = count($array);
                $report .= "$name: $amount updates \n";
                foreach($array as $msg) {
                    $report .= $msg . "\n";
                }
            }
            //$report .= ""

//            return "Not implemented yet: | " . json_encode($num_fields_updated);

        } else {
            return "Not implemented";
        }

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
        return array("sync" =>
            array(
                "callback" => "adminSync"
                ,"label" => "Sync with NB"
                ,"classes" => "btn btn-xs btn-warning"
                ,"description" => "Syncronizes nationbuilder with the database"
                ,"permission" => "ROLE_ADMIN"
                ,"visible" => array('VoteBundle\Entity\User')
                ,"input" => array(
                    "dry" => array("type" => "boolean", "label" => "Dry run?", "default" => true)
                    ,"mode" => array("type" => "select", "label" => "What sync action should be taken?", "choices" => array(
                        array("label" => "Delete test users", "value" => "delete")
                        ,array("label" => "Add missing users from NB", "value" => "add")
                        ,array("label" => "Sync user data from NB", "value" => "sync")
                    ))
//                    ,"ignore_time" => array("type" => "datetime", "label" => "Ignore users after this date")
                    ,"file" => array("type" => "file", "label" => "NationBuilder CSV file", "required" => true)
                )
            )
        );
    }

    /**
     * NationBuilderService constructor.
     * @param $container
     * @param $baseURL
     * @param $clientID
     * @param $secret
     * @param $testToken
     */
    public function __construct($container, $baseURL, $clientID, $secret, $testToken) {

        $this->container = $container;
        $this->em = $container->get("doctrine.orm.entity_manager");
//        $this->user_manager = $container->get("doctrine.orm.entity_manager");
        $this->logger = $container->get("logger");
        $this->base_url = $baseURL;
        $this->client_id = $clientID;
        $this->secret = $secret;

        $session = $container->get("session");

        if($testToken !== NULL) {
            $this->api_token = $testToken;
        } else if($session->has("nationbuilder.api_token")) {
            $this->api_token = $session->get("nationbuilder.api_token");
        } else {
            $this->api_token = NULL;
        }

        $this->required_fields = array(
            "nationbuilder_id", "first_name", "middle_name", "last_name", "email", "email_opt_in", "email1_is_bad"
            ,"phone_number", "work_phone_number", "mobile_number", "tag_list", "created_at", "primary_state"
            ,"primary_city", "primary_zip", "primary_address1", "primary_address2", "born_at", "is_volunteer"
            ,"membership_names"
        );

    }

    /**
     * @param $token
     */
    public function setToken($token) {
        $this->api_token = $token;
    }

    /**
     * @param $user
     * @return bool|string
     */
    private function pushAddress($user) {

        if(($user->getPostcode() === NULL) && ($user->getStreet() === NULL)) {
            return true;
        }

        $unit_street = $user->getStreetUnitNumber();
        if($unit_street['unit'] !== null) {
            $unit_number = $unit_street['unit'] . ", ";
        } else {
            $unit_number = "";
        }
        $suburb_state = $user->getSuburbState();

        $email = $user->getEmail();
        $address = $unit_street['street'] . " " . $user->getStreet() . ", $unit_number" . $suburb_state['suburb'] . ", " . $suburb_state['state'] . ", " . $user->getPostcode();

        $this->logger->info("Pushing address for user $email: '$address'");

        $client = new HttpClient(array('verify' => true, 'exceptions' => false));
        $response = $client->request("GET", "http://www.peopledecide.org.au/manual");
        if($response->getStatusCode() !== 200) {
            return "Could not get manual form page, status code " . $response->getStatusCode();
        }

        $crawler = new Crawler($response->getBody()->getContents());
        $form_nodes = $crawler->filter('#manual_page_new_signup_form')->filter("input");
        if($form_nodes->count() <= 0) {
            return "Could not find form input";
        }
        $form_token = $form_nodes->eq(0)->attr("value");

        $token = NULL;
        foreach($response->getHeader("Set-Cookie") as $cookie) {
            if(strpos($cookie, "_nbuild_token") !== 0) {
                continue;
            }
            $token = explode("=", explode(";", $cookie)[0])[1];
        }
        if($token === NULL) {
            return "Could not get token";
        }

        $boundary = "WebKitFormBoundaryeXjDcXxozsaBdRLz";
//        $this->logger->info("TOKEN $token, FORM TOKEN: $form_token");

        $response = $client->request("POST", "http://www.peopledecide.org.au/forms/signups", array(
            "headers" => array(
                "Accept" => "text/javascript"
                ,"Content-Type" => "multipart/form-data; boundary=----$boundary"
                ,"Cookie" => "_nbuild_token=$token"
            )
            ,'body' =>
"------$boundary
Content-Disposition: form-data; name=\"authenticity_token\"

$form_token
------$boundary
Content-Disposition: form-data; name=\"page_id\"

29
------$boundary
Content-Disposition: form-data; name=\"signup[email]\"

$email
------$boundary
Content-Disposition: form-data; name=\"signup[submitted_address]\"

$address
AU
------$boundary--"
        ));
        if($response->getStatusCode() !== 200) {
            return "Could not send address page, status code " . $response->getStatusCode();
        }

//        $contents = $response->getBody()->getContents();
//        $this->logger->info("Successfully pushed address for user " . $user->getEmail() . ": $contents");

        return true;
    }

    /**
     * Gets/sends data from the nation builder site - use for GET and DELETE
     *
     * @param $api_call
     * @param $params
     * @param $method
     * @return null
     */
    private function communicate($api_call, $params = array(), $method = "GET") {

        if($this->api_token === NULL) {
            return array(false, "No API token");
        }

        $send_url = $this->base_url . "api/v1/" . $api_call;

        $this->logger->debug("Sending $method request to $send_url");

        $client = new Client($this->client_id, $this->secret);
        $client->setAccessToken($this->api_token);

        $response = $client->fetch($send_url, $params, $method);

        $expected_code = $method === "DELETE" ? 204 : 200;
        if($response['code'] !== $expected_code) {
            return array(false, $response['result']);
        }
        return array(true, $response['result']);
    }

    /**
     * Gets/sends data from the nation builder site - use for POST and PUT
     *
     * @param $api_call
     * @param $method
     * @param $params
     * @return null
     */
    private function sendData($api_call, $params, $method = "POST") {

        if($this->api_token === NULL) {
            return array("success" => false, "result" => "API token not present");
        }

        $token = $this->api_token;
        $send_url = $this->base_url . "api/v1/" . $api_call;

        $this->logger->debug("Sending $method request to $send_url");

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($ch,CURLOPT_URL, $send_url . "?access_token=$token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if($method === "POST") {
            curl_setopt($ch, CURLOPT_POST, 1);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($params) );
        curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: application/json'));

        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

//        if($method === "POST") {
//            $expected_code = 201;
//        } else if($method === "DELETE ") {
        $expected_code = $method === "POST" ? 201 : 200;

        if($http_code !== $expected_code) {
            $this->logger->info("Error code $http_code: $result");
            return array('result' => $result, 'code' => $http_code, 'success' => false);
        }

        $json_decode = json_decode($result, true);

        return array(
            'result' => (null === $json_decode) ? $result : $json_decode,
            'code' => $http_code,
            'success' => true
        );
    }

}