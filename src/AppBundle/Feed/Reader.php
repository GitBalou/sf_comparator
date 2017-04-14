<?php

namespace AppBundle\Feed;

use AppBundle\Entity\Merchant;
use AppBundle\Entity\Offer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Validator\Validator\RecursiveValidator;

/**
 * Class Reader
 * @package AppBundle\Feed
 * @author  Etienne Dauvergne <contact@ekyna.com>
 */
class Reader
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /** @var  RecursiveValidator */
    private $validator;

    /**
     * Constructor.
     *
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager,RecursiveValidator $validator)
    {
        $this->em = $entityManager;
        $this->validator = $validator;
    }

    /**
     * Reads the merchant's feed and creates or update the resulting offers.
     *
     * @param Merchant $merchant
     *
     * @return int The number of created or updated offers.
     */
    public function read(Merchant $merchant)
    {
        // $count = 0;
        $count = 0;

        // Lire le flux de données du marchand
        $url = $merchant->getFeedUrl();

        // Convertir les données JSON en tableau
        $ext = explode('.', $url);
        $ext = end($ext);
        switch($ext) {
            case 'json':
                $array = $this->jsonFluxToArray($url);
                break;

            case 'xml':
                $array = $this->xmlFluxToArray($url);
                break;

            case 'csv':
                $array = $this->csvFluxToArray($url);
                break;

            default:
                throw new Exception("Extension de flux non valide : $ext");
        }

        // repo
        $productRepo = $this->em->getRepository('AppBundle:Product');
        $offerRepo = $this->em->getRepository('AppBundle:Offer');

        // Pour chaque couple de données "code ean / prix"
        foreach($array as $data) {

            // Trouver le produit correspondant
            $product = $productRepo->findOneByEanCode($data['ean_code']);

                // Sinon passer à l'itération suivante
                if( $product == null) {
                    continue;
                }

            // Trouver l'offre correspondant à ce produit et ce marchand
            $offer = $offerRepo->findOneByProduct($product);

                // Sinon créer l'offre
                if( $offer == null) {
                    $offer = new Offer();
                    $offer->setMerchant($merchant);
                    $offer->setProduct($product);
                }

            // Mettre à jour le prix et la date de mise à jour de l'offre
            $offer->setPrice($data['price']);
            $offer->setUpdatedAt(new \DateTime());

            // Enregistrer l'offre et incrémenter le compteur d'offres
            $errors = $this->validator->validate($offer);

            if( count($errors) == 0) {
                $this->em->persist($offer);
                ++$count;
            }
        }

        //flush
        if($count > 0) {
            $this->em->flush();
        }

        // Renvoyer le nombre d'offres
        return $count;
    }

    /**
     * convert JSON flux to associative array
     * @param string $url
     * @return array
     */
    private function jsonFluxToArray($url){
        $content = file_get_contents($url);
        return json_decode($content, true);
    }

    /**
     * convert CSV flux to associative array
     * @param string $url
     * @return array
     */
    private function csvFluxToArray($url){
        $handle = fopen($url, 'r');

        $array = array();
        while (false !== $data = fgetcsv($handle, null, ';')) {
            // $data = [<eandCode>, <price>];
            $tmp = array();
            $tmp['ean_code'] = intval($data[0]);
            $tmp['price'] = floatval($data[1]);
            array_push($array, $tmp);
        }
        fclose($handle);

        return $array;
    }

    /**
     * convert XML flux to associative array
     * @param string $url
     * @return array
     */
    private function xmlFluxToArray($url){
        $dom = new \DOMDocument();
        $dom->load($url);

        $offers = $dom->getElementsByTagName('offer');

        /** @var \DomElement $offer */
        $array = array();
        foreach ($offers as $offer) {
            // public function \DomElement::getAttribute(string $name);
            $tmp = array();
            $tmp['ean_code'] = intval($offer->getAttribute('ean_code'));
            $tmp['price'] = floatval($offer->getAttribute('price'));
            array_push($array, $tmp);
        }

        return $array;
    }
}
