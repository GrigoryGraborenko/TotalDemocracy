<?php
/**
 * Created by PhpStorm.
 * User: Grigory
 * Date: 1/03/2016
 * Time: 6:16 AM
 */

namespace VoteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use VoteBundle\Entity\Document;
use VoteBundle\Entity\UserDocumentVote;

class LoadDocumentData extends AbstractFixture implements OrderedFixtureInterface {

    private $manager;

    /**
     * {@inheritDoc}
     * Note that user Teams are loaded as part of the LoadUserData
     */
    public function load(ObjectManager $manager) {

        $this->manager = $manager;

        $this->createDocument(  'domain-federal-australia', "Prison Abolition Act", "Immediately shut down all prisons and punish with community service."
                                ,array("user-bob", "user-sally"));
        $this->createDocument(  'domain-state-queensland', "Heritage Protection Bill", "Heavy fines for demolition companies that knock down heritage listed buildings."
                                ,array(), array("user-bob"));
        $this->createDocument(  'domain-state-new-south-wales', "Sydney Bill", "More Sydney.");
        $this->createDocument(  'domain-local-brisbane-city', "Bike Path", "Build a bike path around the city."
                                ,array("user-bob", "user-sally"), array("user-terry"));

        $manager->flush();
    }

    /**
     * @param $domain_ref
     * @param $name
     * @param $text
     * @param array $supporters
     * @param array $opponents
     */
    private function createDocument($domain_ref, $name, $text, $supporters = array(), $opponents = array()) {

        $domain = $this->getReference($domain_ref);
        $doc = new Document($domain, "bill", $name, $text);
        $this->manager->persist($doc);

        foreach($supporters as $supporter) {
            $vote = new UserDocumentVote($this->getReference($supporter), $doc, true);
            $this->manager->persist($vote);
        }
        foreach($opponents as $opponent) {
            $vote = new UserDocumentVote($this->getReference($opponent), $doc, false);
            $this->manager->persist($vote);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder() {
        return 3; // the order in which fixtures will be loaded
    }

}