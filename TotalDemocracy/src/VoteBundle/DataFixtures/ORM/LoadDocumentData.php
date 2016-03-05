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

class LoadDocumentData extends AbstractFixture implements OrderedFixtureInterface {

    /**
     * {@inheritDoc}
     * Note that user Teams are loaded as part of the LoadUserData
     */
    public function load(ObjectManager $manager) {

        $manager->persist($this->createDocument('domain-federal-australia', "Prison Abolition Act", "Immediately shut down all prisons and punish with community service."));
        $manager->persist($this->createDocument('domain-state-queensland', "Heritage Protection Bill", "Heavy fines for demolition companies that knock down heritage listed buildings."));
        $manager->persist($this->createDocument('domain-local-brisbane-city-council', "Bike Path", "Build a bike path around the city."));

        $manager->flush();

    }

    /**
     * @param $domain_ref
     * @param $name
     * @param $text
     * @return Document
     */
    private function createDocument($domain_ref, $name, $text) {

        $domain = $this->getReference($domain_ref);
        $doc = new Document($domain, $name, $text);
        return $doc;
    }

    /**
     * {@inheritDoc}
     */
    public function getOrder() {
        return 2; // the order in which fixtures will be loaded
    }

}