<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 12/07/2016
 * Time: 6:28 AM
 */

namespace VoteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

use Carbon\Carbon;

use VoteBundle\Entity\Task;

/**
 * Class NationBuilderCommand
 * @package VoteBundle\Command
 */
class NationBuilderCommand extends ContainerAwareCommand {

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
    protected function configure() {
        $this->setName('pd:nationbuilder')
            ->setDescription('Import from nationbuilder')
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                'Can be "import".'
            )
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'Filename'
            )
            ->setHelp("The <info>pd:nationbuilder</info> command syncronizes data between nationbuilder and this platform.");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $this->logger = $this->container->get('logger');
        $this->output = $output;
        $this->em = $this->container->get('doctrine')->getManager();

        $action = $input->getArgument('action');
        $file = $input->getArgument('file');

        $this->log('==========================NATION_BUILDER==============================');

        if($action === "import") {
            $this->log("Importing file $file...");
            $result = $this->importFile($input, $file, false);
            if($result !== true) {
                $this->log('Error: ' . $result);
            }
        }

        $this->log('----------------------------------------------------------------------');

    }

    /**
     * @param InputInterface $input
     * @param $filename
     * @param $include_existing
     * @return bool|string
     */
    private function importFile(InputInterface $input, $filename, $include_existing) {

        $nb_service = $this->container->get("vote.nationbuilder");
        $people = $nb_service->readFromCSV($filename, $include_existing);
        if(!is_array($people)) {
            return $people;
        }

        $entries_msg = count($people) . " ";
        if($include_existing) {
            $entries_msg .= "valid new and existing";
        } else {
            $entries_msg .= "valid new";
        }

        if(count($people) <= 0) {
            return "No new people found";
        }

//        $this->log("Successfully read $scanned people, $entries_msg entries with emails detected.");
        $this->log("Successfully read $entries_msg entries with emails detected.");

        $question = new ConfirmationQuestion("Import these users? (y/n) ", false);
        $helper = $this->getHelper('question');
        if(!$helper->ask($input, $this->output, $question)) {
            return "Aborted";
        }

        $rate_limit = $this->container->getParameter("mailer_rate_limit_seconds");

        $num_volunteers = 0;
        foreach($people as $person) {
            list($new_user, $volunteer) = $nb_service->createUserFromExport($person);
            $this->em->persist($new_user);
            if($volunteer !== NULL) {
                $this->em->persist($volunteer);
                $num_volunteers++;
            }
            $task = new Task("email", "vote.email", "emailImported", $rate_limit, array(), $new_user);
            $this->em->persist($task);
        }
        $this->em->flush();

        $this->log("Successfully imported " . count($people) . " people ($num_volunteers volunteers)");

        return true;
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