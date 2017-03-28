<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\DomCrawler\Crawler;

class EmailExtractorController extends Controller {

    /**
     * @Route("/", name="email-extractor")
     */
    public function extractorAction(Request $request) {

        $names_to_search = ['Andreas'];

        $defaultData = array('website' => 'Type url here');
        $form = $this->createFormBuilder($defaultData)
                ->add('website', UrlType::class)
                ->add('send', SubmitType::class)
                ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // the url of webpage to scan from twig form
            $data = $form->getData();

            $host_to_scan = parse_url($data['website'], PHP_URL_HOST);

            //it can be optimized using cUrl but that is more verbose
            $home_page = @file_get_contents($data['website']);

            dump($host_to_scan);

            $crawler = new Crawler($home_page);
            //it is the most common parttern to put navigation into a webpage
            $crawler = $crawler->filter('body  li>a');

            $children_pages = array();
            for ($ii = 0; $ii < $crawler->count(); $ii++) {
                $hrefs = $crawler->getNode($ii)->getAttribute('href');
                $url_parts = parse_url($hrefs);
                if (array_key_exists("path", $url_parts)) {
                    if ((array_key_exists("host", $url_parts) && strpos($host_to_scan, $url_parts["host"])) || !array_key_exists("host", $url_parts)) {
                        //              $children_pages[$ii] = explode("/", trim($url_parts["path"], "/"))[0];
                        $children_pages[$ii] = trim($url_parts["path"], "/");
                    }
                }
            }

            $children_pages = array_unique($children_pages);

            $matches = array();

            //regular expression that matches most email addresses, courtesy of @Eric-Karl.
            $pattern = '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,4}/i';

            //perform global regular expression match, ie search the entire web page for a particular thing, and store it in the previously initialised array.

            $email_found = array();
            $name_found = array();

            //Seach on the homepage

            preg_match_all($pattern, $home_page, $matches);
            foreach ($matches as $match) {
                if (!empty($match)) {
                    $email_found[$data['website']] = array_unique($match);
                }
            }

            foreach ($names_to_search as $name) {
                preg_match_all($pattern, $home_page, $matches);
            }

            dump('$matches');



            //Seach on the found children_pages
            foreach ($children_pages as $page) {
                $child_url = $data['website'] . "/" . $page;

                $child_code = @file_get_contents($child_url);
                if ($child_code === FALSE) {
                    continue;
                }
                preg_match_all($pattern, $child_code, $matches);

                foreach ($matches as $match) {
                    if (!empty($match)) {
                        $email_found[$child_url] = array_unique($match);
                    }
                }

                //dump($child_url);
                //dump(array_values(array_unique($matches[0])));
            }

            dump($email_found);
            die();
        }

        return $this->render('emailextractor/emailextractor.html.twig', array(
                    'form' => $form->createView(),
        ));
    }

}
