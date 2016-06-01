<?php

namespace VoteBundle\Tests;

use Carbon\Carbon;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;


class BaseFunctionalTestCase extends WebTestCase {

    /**
     * @var \Doctrine\ORM\EntityManager $em
     */
    protected $em;

    /** @var  ContainerInterface */
    protected $container;
    protected $session;

    /** @var  Client */
    protected $client;

    protected function getFixtureList() {
        return array(
            'VoteBundle\DataFixtures\ORM\LoadDomainData'
            ,'VoteBundle\DataFixtures\ORM\LoadDocumentData'
            ,'VoteBundle\DataFixtures\ORM\LoadUserData'
        );
    }

    /**
     * {@inheritDoc}
     */
    public function setUp() {

        static::$kernel = static::createKernel();
        static::$kernel->boot();
        $this->container = static::$kernel->getContainer();
        $this->em = $this->container
            ->get('doctrine')
            ->getManager();

        $this->loadFixtures($this->getFixtureList());

        $this->client = static::createClient();

        Carbon::setTestNow(NULL);
    }


    protected function logIn($username = 'bob@test.com', $password = 'test', $is_admin = false) {

        $this->session = $this->container->get('session');

        $userManager = $this->container->get('fos_user.user_manager');
        $user = $userManager->findUserBy(array('username' => $username));
        $this->user = $user;

        $firewall = 'main';
        $token = new UsernamePasswordToken($user, $password, $firewall, array($is_admin?'ROLE_ADMIN':'ROLE_USER'));
        $this->session->set('_security_'.$firewall, serialize($token));
        $this->session->save();

        $cookie = new Cookie($this->session->getName(), $this->session->getId());
        $this->client->getCookieJar()->set($cookie);
        return $user;
    }

    /**
     * Runs the command given in a test environment
     *
     * @return string
     */
    public function runCommandLine($command, $args = array()) {

        $kernel = $this->client->getContainer()->get('kernel');

        $application = new Application($kernel);
        $application->add($command);
        $command = $application->find($command->getName());
        $command->setContainer($this->client->getContainer());
        $commandTester = new CommandTester($command);
        $commandTester->execute($args);

        return $commandTester->getDisplay();
    }

    /**
     * Convenience function for grabbing a service when running a test
     *
     * @param $serviceId
     * @return object
     */
    public function getService($serviceId)
    {
        return self::$kernel->getContainer()->get($serviceId);
    }

    /**
     * Convenience function for grabbing a parameter when running a test
     *
     * @param $paramName
     * @return object
     */
    public function getParameter($paramName)
    {
        return self::$kernel->getContainer()->getParameter($paramName);
    }

    /**
     * Convenience function for outputting messages into the info stream
     */
    protected function infoLog($msg, $to_display = false) {
        $this->client->getContainer()->get('logger')->info($msg);
        if($to_display) {
            print_r("\n" . $msg . "\n");
        }
    }

    /**
     * Gets JSON decoded data from the last request
     *
     * @param $client Testing server instance
     * @param $uri URL to send get request
     * @return mixed Array that contains JSON decoded data
     */
    protected function responseJSON() {
        return json_decode($this->client->getResponse()->getContent(), true);
    }

    /**
     * Ensure objects are garbage collected so it does not use massive amounts of memory
     */
    protected function tearDown() {
        parent::tearDown();
        if (($container = $this->container) != null) {
            $refl = new \ReflectionObject($container);
            foreach ($refl->getProperties() as $prop) {
                $prop->setAccessible(true);
                $prop->setValue($container, null);
            }
        }

        // isolate tests to ensure that carbon::now is always real
        Carbon::setTestNow();
    }

}