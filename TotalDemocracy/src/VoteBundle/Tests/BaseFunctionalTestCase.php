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

/**
 * Class BaseFunctionalTestCase
 * @package VoteBundle\Tests
 */
abstract class BaseFunctionalTestCase extends WebTestCase {

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


    protected function login($username = 'bob@test.com', $password = 'test', $is_admin = false) {

        $this->session = $this->container->get('session');

        $user = $this->em->getRepository("VoteBundle:User")->findOneBy(array("username" => $username));

        $firewall = 'main';
        $token = new UsernamePasswordToken($user, $password, $firewall, array($is_admin?'ROLE_ADMIN':'ROLE_USER'));
        $this->session->set('_security_'.$firewall, serialize($token));
        $this->session->save();

        $cookie = new Cookie($this->session->getName(), $this->session->getId());
        $this->client->getCookieJar()->set($cookie);
        return $user;
    }

    protected function logout() {

        $this->container->get('security.token_storage')->setToken(null);
        $this->container->get('session')->invalidate();
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

    /**
     * @param $email
     * @param string $password
     * @param bool $should_succeed
     * @return null|object
     */
    protected function attemptLogin($email, $password = "password", $should_succeed = true) {

        $crawler = $this->client->request('GET', "/login");
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode(), "Failed to load login confirm page");

        $form = $crawler->selectButton('Login')->form();
        $this->assertNotNull($form, 'Could not find form');

        $this->client->submit($form, array(
            '_username'        => $email
            ,'_password'       => $password
        ));

        $response = $this->client->getResponse();
        $this->assertEquals(302, $response->getStatusCode(), "Did not redirect after login attempt");
        if($should_succeed) {
            $this->assertStringEndsNotWith("login", $response->headers->get('location'), "Failed to login in");
            return $this->em->getRepository("VoteBundle:User")->findOneBy(array("email" => $email));
        } else {
            $this->assertStringEndsWith("login", $response->headers->get('location'), "Failed to login in");
            return NULL;
        }
    }

}