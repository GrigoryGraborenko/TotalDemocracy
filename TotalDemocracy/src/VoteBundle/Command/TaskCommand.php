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

        $mins = intval($input->getArgument('minutes'));

        $task_repo = $this->em->getRepository('VoteBundle:Task');
        $all_tasks = $task_repo->findBy(array("whenProcessed" => NULL), array("type" => "DESC", "minSeconds" => "ASC"));

        if(count($all_tasks) <= 0) {
            $this->log('No pending tasks found');
            return;
        }
        $this->log('Running tasks...');

        // sort tasks into buckets by type
        $outstanding = array();
        foreach($all_tasks as $task) {
            if(!array_key_exists($task->getType(), $outstanding)) {
                $outstanding[$task->getType()] = array("pending" => array(), "done" => array(), "latest" => NULL);

                $latest_task = $task_repo->findBy(array("type" => $task->getType()), array("whenProcessed" => "DESC"), 1);
                if(count($latest_task) > 0) {
                    $when_proc = $latest_task[0]->getWhenProcessed();
                    if($when_proc !== NULL) {
                        $outstanding[$task->getType()]["latest"] = floatval(Carbon::instance($latest_task[0]->getWhenProcessed())->getTimestamp());
                    }
                }
            }
            $outstanding[$task->getType()]["pending"][] = $task;
        }
        foreach($outstanding as $type => $tasks) {
            $this->log(count($tasks['pending']) . " pending tasks of type $type");
        }

        $cutoff = microtime(true) + 60.0 * $mins;
        while(true) {

            $soonest = NULL;

            foreach($outstanding as $type => &$tasks) {

                if(count($tasks['pending']) <= 0) {
                    continue;
                }
                $pending = $tasks['pending'][0];

                $next = NULL;
                if($tasks['latest'] !== NULL) {
                    $next = $tasks['latest'] + $pending->getMinSeconds();
                }
                if(($next === NULL) || ($next < microtime(true))) {
                    $tasks['done'][] = $pending;
                    array_shift($tasks['pending']);
                    $tasks['latest'] = microtime(true);
                    $this->runTask($pending);
                } else {
                    if($soonest === NULL) {
                        $soonest = $next;
                    } else {
                        $soonest = min($soonest, $next);
                    }
                }
            }
            unset($tasks); // remove dangling references just in case. PHP is silly.

            $now = microtime(true);
            if($now > $cutoff) {
                break;
            }
            if($soonest !== NULL) {
                if($soonest > $cutoff) {
                    break;
                }
                time_sleep_until($soonest);
            }

        }

        $this->log('Done.');
        foreach($outstanding as $type => $tasks) {
            $this->log("Completed " . count($tasks['done']) . " tasks of type $type, " . count($tasks['pending']) . " remaining.");
        }

    }

    /**
     * @param $task
     */
    private function runTask($task) {
        try {
            $service = $this->container->get($task->getService());
            $result = $service->{$task->getFunction()}($task->getJsonParamsArray(), $task->getUser());
            if(!is_array($result)) {
                $result = array("success" => false, "error" => $result);
            }
        } catch(\Exception $e) {
            $result = array("success" => false, "error" => ($e . ""));
        }
        $task->setJsonResult(json_encode($result));
        $task->setWhenProcessed(Carbon::now("UTC"));
        $this->em->flush();
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