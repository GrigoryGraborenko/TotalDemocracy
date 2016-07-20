<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 20/07/2016
 * Time: 8:12 AM
 */

namespace VoteBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use JMS\DiExtraBundle\Annotation as DI;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Carbon\Carbon;

/**
 * Class TaskCommand
 * @package VoteBundle\Command
 */
class TaskCommand extends ContainerAwareCommand {

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
        $this->setName('pd:task')
            ->setDescription('Execute tasks')
            ->addArgument(
                'minutes',
                InputArgument::REQUIRED,
                'How many minutes to run for'
            )
            ->setHelp("The <info>pd:task</info> command performs a series of tasks with rate limiting.");
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $this->logger = $this->container->get('logger');
        $this->output = $output;
        $this->em = $this->container->get('doctrine')->getManager();
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