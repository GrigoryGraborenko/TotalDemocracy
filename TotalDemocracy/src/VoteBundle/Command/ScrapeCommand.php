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
            ->setHelp(
                <<<EOT
                The <info>pd:scrape</info> command scrapes government websites for data.
EOT
            );
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

        $this->processBrisbaneCityCouncil();

        $this->log('----------------------------------------------------------------------');

    }

    /**
     *
     */
    private function processBrisbaneCityCouncil() {

        $this->log("Processing Brisbane City Council...");

        $doc_repo = $this->em->getRepository('VoteBundle:Document');
        $domain = $this->em->getRepository('VoteBundle:Domain')->findOneBy(array("name" => "BRISBANE CITY"));;

        $client = new HttpClient([
            'verify' => false
        ]);

        // TODO: return to this week
        $timeframe = "thismonth";
//        $timeframe = "thisweek";
        $response = $client->request("GET", "https://pdonline.brisbane.qld.gov.au/masterviewUI/modules/ApplicationMaster/default.aspx?page=found&1=$timeframe&6=F");
        $crawler = new Crawler($response->getBody()->getContents());

        $application_ids = array();

        foreach($crawler->filter('.rgNumPart a') as $link) {
            $page_url = str_replace("javascript:__doPostBack('", "", str_replace("','')", "", $link->getAttribute("href")));

            $response = $client->request("POST", "https://pdonline.brisbane.qld.gov.au/masterviewUI/modules/ApplicationMaster/default.aspx?page=found&1=$timeframe&6=F", [
                "form_params" => [
                    "__EVENTTARGET" => $page_url
                ]
            ]);
            $sub_crawler = new Crawler($response->getBody()->getContents());
            foreach($sub_crawler->filter('.rgMasterTable tbody a') as $sub_link) {
                $url = $sub_link->getAttribute("href");

                $application_ids[] = str_replace("default.aspx?page=wrapper&key=", "", $url);
            }
        }

        $this->log("Loaded indices, scraping individual documents...");

        $found_num = 0;
        foreach($application_ids as $app_id) {

            // skip already added ones
            if($doc_repo->findOneBy(array("domain" => $domain, "externalID" => $app_id))) {
                continue;
            }

            $url = "https://pdonline.brisbane.qld.gov.au/masterviewUI/modules/ApplicationMaster/default.aspx?page=wrapper&key=$app_id";
            $response = $client->request("GET", $url);
            $crawler = new Crawler($response->getBody()->getContents());

//            $details_node = $crawler->filter("#lblDetails")->children();

            $texts = $crawler->filter("#lblDetails")->filterXPath('//text()')->extract(['_text']);
            $is_impact = strpos($texts[4], "Impact") !== false;

            if(!$is_impact) {
                continue;
            }

            $activity = trim($texts[2]);

//            $this->logger->info($app_id);
//            $this->logger->info(json_encode($texts));
//            $this->logger->info($crawler->filter("#lblDetails")->first()->html());

            $document = new Document($domain, $activity, "fill");
            $document->setExternalID($app_id);
            $document->setExternalURL($url);

            $this->em->persist($document);
            $found_num++;
        }

        $this->em->flush();

        $this->log("Finished, created $found_num new documents.");

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