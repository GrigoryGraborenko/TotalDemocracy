<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 08-Sep-15
 * Time: 11:39 AM
 */

namespace VoteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Carbon\Carbon;

use GuzzleHttp\Client as HttpClient;
use Symfony\Component\DomCrawler\Crawler;

use VoteBundle\Entity\Document;

/**
 * Class ScrapeCommand
 * @package VoteBundle\Command
 */
class ScrapeCommand extends ContainerAwareCommand {

    private $container;
    private $em;

    private $logger;
    private $output;

    /**
     * Initializes the container required for grabbing services
     *
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null) {
        $this->container = $container;
    }

    /**
     * Configures the command line name of this command, sets the help and description
     */
    protected function configure()
    {
        $this->setName('pd:scrape')
            ->setDescription('Obtain legislation and development applications from government websites')
            ->setHelp("The <info>pd:scrape</info> command scrapes government websites for data.");
    }

    /**
     * Executes the scrape command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output) {

        $this->container = $this->getContainer();
        $this->logger = $this->container->get('logger');
        $this->output = $output;
        $this->em = $this->container->get('doctrine')->getManager();

        $this->log('=============================SCRAPE===================================');

//        $fed_result = $this->processFederalAustralia();
//        if($fed_result === true) {
//            $this->log("Successfully processed federal bills");
//        }

        $this->processQueensland();
//        $this->processBrisbaneCityCouncil();

        $this->log('----------------------------------------------------------------------');

    }

    /**
     *
     */
    private function processBrisbaneCityCouncil() {

        $this->log("Processing Brisbane City Council...");

//        $result = $this->container->get("vote.pdf")->processElectoralPDF('C:\0805 Coorparoo.pdf', $this->output);
//        $result = $this->container->get("vote.pdf")->processElectoralPDF('C:\Electoral\0801 Bracken Ridge.pdf', $this->output);
//        $result = $this->container->get("vote.pdf")->processElectoralPDF('C:\qld15_73_mr.pdf', $this->output);

//        $this->container->get('vote.electoral_roll')->processDirectory("C:\\Electoral\\");
//        $this->container->get('vote.electoral_roll')->processNationBuilder("C:\\Electoral\\nationbuilder-people-export-29-2016-04-15.csv");
//        return;

        $doc_repo = $this->em->getRepository('VoteBundle:Document');
        $domain = $this->em->getRepository('VoteBundle:Domain')->findOneBy(array("name" => "BRISBANE CITY"));;

        $client = new HttpClient(array(
//            'verify' => false
            'verify' => true
        ));

        // TODO: return to this week
        $timeframe = "thismonth";
//        $timeframe = "thisweek";
        $response = $client->request("GET", "https://pdonline.brisbane.qld.gov.au/masterviewUI/modules/ApplicationMaster/default.aspx?page=found&1=$timeframe&6=F");
        $crawler = new Crawler($response->getBody()->getContents());

        $applications = array();

        $num_new = 0;
        $num_existing = 0;
        $num_closed = 0;

        // check old open applicatiobs
        foreach($doc_repo->findBy(array("domain" => $domain, "state" => "open")) as $exist) {
            if($exist->getExternalId() === NULL) {
                continue;
            }
            $applications[$exist->getExternalId()] = array("doc" => $exist);
            $num_existing++;
        }

        foreach($crawler->filter('.rgNumPart a') as $link) {
            $page_url = str_replace("javascript:__doPostBack('", "", str_replace("','')", "", $link->getAttribute("href")));

            $response = $client->request("POST", "https://pdonline.brisbane.qld.gov.au/masterviewUI/modules/ApplicationMaster/default.aspx?page=found&1=$timeframe&6=F", array(
                "form_params" => array(
                    "__EVENTTARGET" => $page_url
                )
            ));
            $page_crawler = new Crawler($response->getBody()->getContents());
            foreach($page_crawler->filter('.rgMasterTable tbody tr') as $app_info) {
                $sub_crawler = new Crawler($app_info);
                $url = $sub_crawler->filter("a")->getNode(0)->getAttribute("href");
                $date = explode("/", $sub_crawler->filter("td")->getNode(3)->textContent);
                $date = Carbon::createFromDate($date[2], $date[1], $date[0], "UTC")->startOfDay();
                $app_id = str_replace("default.aspx?page=wrapper&key=", "", $url);
                if(!array_key_exists($app_id, $applications)) {
                    $applications[$app_id] = array("date" => $date);
                }
            }
        }
        $this->log("Loaded indices, scraping individual documents...");

        foreach($applications as $app_id => $app_info) {

            $url = "https://pdonline.brisbane.qld.gov.au/masterviewUI/modules/ApplicationMaster/default.aspx?page=wrapper&key=$app_id";

            $response = $client->request("GET", $url);
            $crawler = new Crawler($response->getBody()->getContents());

            $detail_tags = $crawler->filter("#lblDetails")->getNode(0)->childNodes;

            $activities = array();
            $activity_names = array();

            $is_impact = false;
            //$activity =
            $num_activities = ($detail_tags->length - 1) / 9;
            for($a = 0; $a < $num_activities; $a++) {

                $activity = array(
                    "activity" => trim($detail_tags->item($a * 9 + 2)->textContent)
                    ,"level" => trim($detail_tags->item($a * 9 + 5)->textContent)
                    ,"permit" => trim($detail_tags->item($a * 9 + 8)->textContent)
                );

                if(strpos($activity["level"], "Impact") !== false) {
                    $is_impact = true;
                }
                if(strlen($activity['activity']) > 0) {
                    $activity_names[] = $activity['activity'];
                }
                $activities[] = $activity;
            }

            if(!$is_impact) {
                continue;
            }

            $decision_text = $crawler->filter("#lblDecision")->getNode(0)->textContent;
            if(strpos($decision_text, "No decision made") === false) {
                $state = "closed";
                $num_closed++;
            } else {
                $state = "open";
            }

            $people_texts = $crawler->filter("#lblPeople")->filterXPath('//text()')->extract(['_text']);
            $properties_texts = $crawler->filter("#lblProperties")->filterXPath('//text()')->extract(['_text']);

            $activity = implode(", ", $activity_names);
            $applicant = trim($people_texts[0]);
            $properties = array();
            foreach($properties_texts as $property) {
                $trimmed = trim($property);
                if(strlen($trimmed) <= 0) {
                    continue;
                }
                $properties[] = $trimmed;
            }
            $details = array(
                "properties" => $properties
                ,"applicant" => $applicant
                ,"activities" => $activities
            );


//            $this->logger->info($app_id);
//            $this->logger->info(json_encode($detail_texts));
//            $this->logger->info(json_encode($people_texts));
//            $this->logger->info(json_encode($properties_texts));
//            $this->logger->info($crawler->filter("#lblDetails")->first()->html());

//            $app_info['url'] = $url;
//            $app_info['state'] = $state;
//            $text = "Applicant: $applicant. Properties: $properties";

            if(array_key_exists("doc", $app_info)) {
                $document = $app_info['doc'];
            } else {
                $properties = implode(", ", $properties);
                $summary = "Applicant: $applicant. Properties: $properties";

                $document = new Document($domain, "application", $activity, $summary, $app_info['date']);
                $document->setExternalID($app_id);
                $document->setExternalURL($url);
                $this->em->persist($document);
                $num_new++;
            }
            $document->setState($state);
            $document->setCustomData($details);
        }

        $this->em->flush();

        $this->log("Finished, created $num_new new documents, checked $num_existing old ones and closed $num_closed of them.");

    }

    /**
     * Downloads and stores new bills from federal parliament
     *
     * @return bool
     */
    private function processFederalAustralia() {

        $doc_repo = $this->em->getRepository('VoteBundle:Document');
        $domain = $this->em->getRepository('VoteBundle:Domain')->findOneBy(array("level" => "federal"));;

        $client = new HttpClient(array(
            'verify' => true
        ));

        $query = array(
            "page" => 1
            ,"drt" => 1 // date range
            ,"drv" => 0 // no date range
            ,"drvH" => 0
            ,"pnu" => 0
            ,"pnuH" => 0
            ,"ps" => 100 // page size
            ,"q" => "" // search string
            ,"bs" => 1 // before senate
            ,"pbh" => 1 // past both houses
            ,"bhor" => 1 // before house of reps
            ,"np" => 1 // not passed
            ,"pmb" => 1 // private members
            ,"g" => 1 // government
            ,"st" => 2 // which col to sort by, date is 1, title is 2
            ,"sr" => 0 // asc or desc
        );

        $response = $client->request("GET", "http://www.aph.gov.au/Parliamentary_Business/Bills_Legislation/Bills_Search_Results", array("query" => $query));
        if($response->getStatusCode() !== 200) {
            $this->log("Could not get first page");
            return false;
        }
        $crawler = new Crawler($response->getBody()->getContents());

        $num_txt = $crawler->filter("#main_0_content_0_pTotalResults strong")->getNode(0)->textContent;
        $num_items = 0;
        foreach(explode(" ", $num_txt) as $section) {
            $num = intval($section);
            if($num > 0) {
                $num_items = $num;
                break;
            }
        }

        if($num_items <= 0) {
            $this->log("Could not get number of items");
            return false;
        }
        $num_pages = ceil($num_items / 100);

        $this->log("Search found $num_items items across $num_pages pages");

        $num_new = 0;
        $num_existing = 0;

        $page = 1;
        while(true) {

            // already downloaded the first page, start with that
            $subnodes = $crawler->filter("ul.search-filter-results li");
            foreach($subnodes as $doc_node) {

                $doc_crawler = new Crawler($doc_node);
                $title_link = $doc_crawler->filter("h4 a")->getNode(0);
                $extra_links = $doc_crawler->filter(".extra a");
                if(($title_link === NULL) || (count($extra_links) <= 0)) {
                    $this->log("Found no documents on this page");
                    break;
                }
                $title = $title_link->textContent;
                $url = $title_link->getAttribute("href");
                $doc_query_params = array();
                parse_str(parse_url($url, PHP_URL_QUERY), $doc_query_params);
                if(!array_key_exists("bId", $doc_query_params)) {
                    $this->log("Malformed doc URL");
                    return false;
                }
                $doc_id = $doc_query_params['bId'];

                $external_url = NULL;
                $definitions = array();
                foreach($extra_links as $extra) {
                    if($extra->textContent === "Bill") {
                        $external_url = $extra->getAttribute("href");
                    } else if($extra->textContent === "Explanatory Memorandum") {
                        $definitions['summary_link'] = $extra->getAttribute("href");
                    }
                }
                if(($external_url === NULL) || ($external_url === "")) {
                    $external_url = "http://www.aph.gov.au" . $url;
                }

                $def_name = NULL;
                foreach($doc_crawler->filter("dl")->children() as $def) {
                    if($def->nodeName === "dt") {
                        $def_name = $def->textContent;
                    } else if(($def->nodeName === "dd") && ($def_name !== NULL)) {
                        $definitions[$def_name] = substr($def->textContent, 0, -2);
                        $def_name = NULL;
                    }
                }
                if(array_key_exists("Summary", $definitions)) {
                    $summary = $definitions["Summary"];
                    unset($definitions["Summary"]);
                } else {
                    $summary = "No summary available";
                }
                if(array_key_exists("Date", $definitions)) {
                    $date = Carbon::parse($definitions["Date"]);
                    unset($definitions["Date"]);
                } else {
                    $date = Carbon::now("UTC");
                }

                $document = $doc_repo->findOneBy(array("domain" => $domain, "externalID" => $doc_id));
                if($document === NULL) {
                    if(strpos($title, "Appropriation ") === 0) {
                        $type = "appropriations";
                    } else {
                        $type = "bill";
                    }
                    $document = new Document($domain, $type, $title, $summary, $date);
                    $document->setExternalID($doc_id);
                    $this->em->persist($document);
                    $num_new++;
                } else {
                    $num_existing++;
                }
                $document->setExternalURL($external_url);

                if(array_key_exists("Status", $definitions)) {
                    if($definitions["Status"] === "Not Proceeding") {
                        $document->setState("suspended");
                    } else {
                        $document->setState("open");
                    }
                    unset($definitions["Status"]);
                }
                $document->setCustomData($definitions);

            }

            $page++;
            if($page > $num_pages) {
                break;
            }
            $this->log("Downloading page $page...");
            $query['page'] = $page;
            $response = $client->request("GET", "http://www.aph.gov.au/Parliamentary_Business/Bills_Legislation/Bills_Search_Results", array("query" => $query));
            if($response->getStatusCode() !== 200) {
                $this->log("Could not get page $page");
                return false;
            }
            $crawler = new Crawler($response->getBody()->getContents());

        }

        $this->log("Found $num_new new documents and $num_existing existing ones");

        $this->em->flush();

        return true;
    }

    /**
     * Downloads and stores new bills from queensland parliament
     *
     * @return bool
     */
    private function processQueensland() {

        $doc_repo = $this->em->getRepository('VoteBundle:Document');
        $domain = $this->em->getRepository('VoteBundle:Domain')->findOneBy(array("level" => "state", "name" => "Queensland"));

        $client = new HttpClient(array(
            'verify' => true
        ));

        $base_url = "https://www.parliament.qld.gov.au";

        $response = $client->request("GET", "$base_url/work-of-assembly/bills-and-legislation/Bills-before-the-House");
        if($response->getStatusCode() !== 200) {
            $this->log("Could not get bills index");
            return false;
        }
        $crawler = new Crawler($response->getBody()->getContents());

        $num_new = 0;
        $num_existing = 0;

        foreach($crawler->filter(".bill") as $doc_node) {
            $doc_crawler = new Crawler($doc_node);

            $title_link = $doc_crawler->filter(".bill-title a")->getNode(0);
            $info_tags = $doc_crawler->filter(".bill-info p")->getNode(0)->childNodes;
            $doc_links = $doc_crawler->filter(".bill-docs a");
            if(($title_link === NULL) || (count($info_tags) <= 0) || (count($doc_links) <= 0)) {
                continue;
            }

            $title = $title_link->textContent;
            $url = $title_link->getAttribute("href");

            if(strripos($url, "http") !== 0) {
                $url = $base_url . $url;
            }

            $doc_fname = substr($url, strripos($url, "id=") + 3);
//            $doc_fname = substr($url, strripos($url, "/") + 1);
            if((substr($doc_fname, 0, 1) === "B") && (substr($doc_fname, 3, 1) === "_") && (substr($doc_fname, 8, 1) === "_")) {
                $doc_id = substr($doc_fname, 0, 8);
            } else {
                $doc_id = str_replace(".pdf", "", $doc_fname);
            }

//            $this->log("BILL: $doc_id $title : $url");

            $extra = array();

            $date = NULL;
            for($i = 0; $i < count($info_tags); $i++) {
                $tag = $info_tags->item($i);
                if(($tag->nodeName === "span") && (strstr($tag->textContent, "Introduced") !== false)) {
                    $extra['introducer'] = $info_tags->item($i + 1)->textContent;
                    $date = explode(" ", $info_tags->item($i + 2)->textContent);
                    $date = explode("/", $date[count($date) - 1]);
                    $date = Carbon::createFromDate($date[2], $date[1], $date[0])->startOfDay();
                }
            }
            $document = $doc_repo->findOneBy(array("domain" => $domain, "externalID" => $doc_id));
            if($document === NULL) {

                if(strpos($title, "Appropriation ") === 0) {
                    $type = "appropriations";
                } else {
                    $type = "bill";
                }

                $document = new Document($domain, $type, $title, "No summary available", $date);
                $document->setExternalID($doc_id);
                $this->em->persist($document);
                $num_new++;
            } else {
                $num_existing++;
            }
            $document->setExternalURL($url);
            $document->setCustomData($extra);
        }

        $this->em->flush();

        $this->log("Found $num_new new documents and $num_existing existing ones");

        return true;
    }

    /**
     * Gets the mayoral electoral results per booth
     */
    private function BCCResults() {

        $client = new HttpClient(array(
            'verify' => true
        ));

        $character_mask = " \t\n\r\0\x0B\xa0\xc2";

        $booths = array();
        $candidates = array();

        for($booth_num = 1; $booth_num <= 26; $booth_num++) {
            $response = $client->request("GET", "http://results.ecq.qld.gov.au/elections/local/LG2016/BrisbaneCityCouncil/results/mayoral/booth$booth_num.html");
            $crawler = new Crawler($response->getBody()->getContents());

            $ward_name = $crawler->filter("title")->html();
            $ward_name = substr($ward_name, 60, -16);

            $table = $crawler->filter(".election-info-table .resultTableBorder")->eq(1);

            $rows = $table->filter("tr");
            $rows_count = $rows->count();

            if($booth_num === 1) {
                $headers = $rows->eq(1)->filter("td");
                $header_count = $headers->count();

                for ($headnum = 2; $headnum < ($header_count - 4); $headnum++) {
                    $candidate_texts = $headers->eq($headnum)->filterXPath('//div/text()')->extract(['_text']);

                    $candidate_surname = str_replace(",", "", trim($candidate_texts[0], $character_mask));
                    $candidate_fname = trim($candidate_texts[1], $character_mask);
                    $candidate_party = NULL;
                    $heading = "$candidate_fname $candidate_surname";
                    if(count($candidate_texts) > 2) {
                        $candidate_party = trim($candidate_texts[2], $character_mask);
                        $heading .= " [$candidate_party]";
                    }
                    $candidates[] = array("heading" => $heading, "name" => $candidate_fname, "surname" => $candidate_surname, "party" => $candidate_party, "offset" => (count($candidates) * 2 + 2));
                }
                $candidates[] = array("heading" => "Informal Votes", "name" => "Informal", "surname" => "Vote", "party" => NULL, "offset" => (count($candidates) * 2 + 3));
            }

            $num_candidates = count($candidates);

            for($rownum = 4; $rownum < ($rows_count - 12); $rownum++) {
                $cols = $rows->eq($rownum)->filter("td");

                $booth = trim($cols->eq(1)->filterXPath('//text()')->extract(['_text'])[0]);

                $booth_info = array("ward" => $ward_name, "name" => $booth, "votes" => array());
                for($res_num = 0; $res_num < $num_candidates; $res_num++) {
                    $cand = &$candidates[$res_num];
                    $votes = $cols->eq($cand['offset'])->html();
                    $booth_info['votes'][$cand['name']] = intval(trim($votes));
                }
                $booths[] = $booth_info;
            }

        }

        $rows = array(array("WARD", "BOOTH"));
        foreach($candidates as $candidate) {
            $rows[0][] = $candidate['heading'];
        }
        foreach($booths as $booth) {
            $row = array($booth['ward'], $booth['name']);
            $cand_ind = 0;
            foreach($booth['votes'] as $name => $vote) {
                if($candidates[$cand_ind]['name'] === $name) {
                    $row[] = $vote;
                }
                $cand_ind++;
            }
            $rows[] = $row;
        }

        $file = fopen("election_results.csv", "w");
        foreach($rows as $row) {
            $str = implode(',', $row) . "\r\n";
            fwrite($file, $str);
            //$this->log($str);
        }
        fclose($file);

    }

    /**
     * Logs message to both the console and a timestamped log file
     *
     * @param $message
     */
    private function log($message) {
        $this->output->write($message, true);
        $this->logger->debug($message);
    }

}